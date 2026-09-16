<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Migration des données Supabase -> Postgres local.
 *
 * Nécessite SUPABASE_DB_URL (chaîne de connexion directe Supabase) dans .env.
 * Idempotent : rejouable sans créer de doublons (upsert par email / slug).
 *
 *   php artisan migrate:from-supabase             # comptes + sites
 *   php artisan migrate:from-supabase --only=users
 *   php artisan migrate:from-supabase --dry-run
 */
class MigrateFromSupabase extends Command
{
    protected $signature = 'migrate:from-supabase
        {--only= : users|sites (par défaut : les deux)}
        {--paid : ne migrer que les sites payés/publiés (exclut les previews de test)}
        {--dry-run : compte sans écrire}';

    protected $description = 'Migre les comptes (auth.users) et les sites depuis Supabase vers Postgres';

    /** Colonnes de `sites` reprises depuis Supabase (intersection des schémas). */
    private const SITE_COLUMNS = [
        'id', 'slug', 'name', 'sector', 'status', 'place_id',
        'city', 'department', 'address', 'phone', 'email', 'rating', 'reviews_count', 'lat', 'lng', 'maps_url',
        'preview_url', 'live_url', 'subdomain', 'custom_domain', 'domain_verified', 'domain_ssl_active',
        'owner_email', 'owner_name', 'owner_phone',
        'stripe_customer_id', 'stripe_subscription_id', 'subscription_status', 'hosting_plan',
        'subscription_period_end', 'cancel_at_period_end', 'paid_at', 'payment_failed_at', 'purchase_type',
        'html_content', 'site_data', 'modules', 'legal_content', 'contact_form_config',
        'source', 'notes', 'published_at', 'created_at', 'updated_at',
    ];

    private const JSON_COLUMNS = ['site_data', 'modules', 'legal_content', 'contact_form_config'];

    public function handle(): int
    {
        if (! config('database.connections.supabase.url')) {
            $this->error('SUPABASE_DB_URL manquant dans .env — impossible de se connecter à la source.');
            return self::FAILURE;
        }

        $only = $this->option('only');
        $dry = (bool) $this->option('dry-run');

        try {
            DB::connection('supabase')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Connexion Supabase impossible : '.$e->getMessage());
            return self::FAILURE;
        }

        if ($only === null || $only === 'users') {
            $this->migrateUsers($dry);
        }
        if ($only === null || $only === 'sites') {
            $this->migrateSites($dry, (bool) $this->option('paid'));
        }

        $this->info($dry ? 'Dry-run terminé (aucune écriture).' : 'Migration terminée.');
        return self::SUCCESS;
    }

    private function migrateUsers(bool $dry): void
    {
        $rows = DB::connection('supabase')->select(
            "select id, email, encrypted_password, raw_user_meta_data, email_confirmed_at, created_at
             from auth.users where email is not null"
        );
        $this->line("Comptes trouvés : ".count($rows));

        $created = 0;
        foreach ($rows as $r) {
            $meta = json_decode($r->raw_user_meta_data ?? '{}', true) ?: [];
            $name = $meta['name'] ?? $meta['full_name'] ?? Str::before($r->email, '@');
            // bcrypt Supabase = compatible Laravel ; null (OAuth/magic-link) -> mot de passe aléatoire (reset requis)
            $password = $r->encrypted_password ?: bcrypt(Str::random(40));

            if ($dry) { $created++; continue; }

            User::updateOrCreate(
                ['email' => $r->email],
                [
                    'name'              => $name,
                    'password'          => $password,
                    'email_verified_at' => $r->email_confirmed_at,
                    'created_at'        => $r->created_at,
                ]
            );
            $created++;
        }
        $this->info("Comptes migrés : $created".($dry ? ' (simulé)' : ''));
    }

    private function migrateSites(bool $dry, bool $paidOnly = false): void
    {
        $sql = 'select * from public.sites';
        if ($paidOnly) {
            $sql .= " where status in ('paid','published') or stripe_subscription_id is not null or paid_at is not null";
        }
        $rows = DB::connection('supabase')->select($sql);
        $this->line("Sites à migrer : ".count($rows).($paidOnly ? ' (filtre --paid)' : ' (tous)'));

        $usersByEmail = $dry ? collect() : User::pluck('id', 'email');
        $count = 0;

        foreach ($rows as $row) {
            $src = (array) $row;
            $data = [];
            foreach (self::SITE_COLUMNS as $col) {
                if (! array_key_exists($col, $src)) {
                    continue;
                }
                $val = $src[$col];
                if (in_array($col, self::JSON_COLUMNS, true) && is_string($val)) {
                    $val = json_decode($val, true); // le cast Eloquent ré-encodera
                }
                $data[$col] = $val;
            }
            // Rattachement au compte applicatif par email
            $email = $src['owner_email'] ?? $src['email'] ?? null;
            $data['user_id'] = $email ? ($usersByEmail[$email] ?? null) : null;

            if ($dry) { $count++; continue; }

            Site::updateOrCreate(['slug' => $data['slug']], $data);
            $count++;
        }
        $this->info("Sites migrés : $count".($dry ? ' (simulé)' : ''));
    }
}
