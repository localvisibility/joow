<?php

namespace App\Services\Modules;

use App\Models\Site;

/**
 * Génère les mentions légales + politique de confidentialité (RGPD)
 * à partir des informations saisies dans le module "legal".
 */
class LegalGenerator
{
    public static function defaults(): array
    {
        return [
            'enabled'        => false,
            'company_name'   => '', 'company_type' => 'Entreprise individuelle', 'capital' => '',
            'siret'          => '', 'rcs' => '', 'tva' => '', 'director' => '',
            'address'        => '', 'email' => '', 'phone' => '',
            'host_name'      => 'Joow (Hostinger International Ltd.)', 'host_address' => '61 Lordou Vironos Street, 6023 Larnaca, Chypre',
            'custom_mentions' => '', 'custom_privacy' => '',
        ];
    }

    public function html(Site $site): string
    {
        $l = array_replace(self::defaults(), $site->module('legal'));
        $b = $site->site_data['business'] ?? [];
        $name = $l['company_name'] ?: ($b['name'] ?? $site->name);
        $addr = $l['address'] ?: ($b['address'] ?? '');
        $mail = $l['email'] ?: ($b['email'] ?? $site->owner_email ?? '');
        $tel  = $l['phone'] ?: ($b['phone'] ?? '');
        $e = fn ($v) => e((string) $v);
        $line = fn ($label, $v) => $v ? "<p><strong>{$label} :</strong> ".$e($v).'</p>' : '';

        return <<<HTML
<h2>Mentions légales</h2>
<h3>Éditeur du site</h3>
{$line('Dénomination', $name)}{$line('Forme juridique', $l['company_type'])}{$line('Capital social', $l['capital'])}
{$line('SIRET', $l['siret'])}{$line('RCS', $l['rcs'])}{$line('N° TVA intracommunautaire', $l['tva'])}
{$line('Directeur de la publication', $l['director'] ?: $name)}
{$line('Adresse', $addr)}{$line('Email', $mail)}{$line('Téléphone', $tel)}
<h3>Hébergement</h3>
{$line('Hébergeur', $l['host_name'])}{$line('Adresse', $l['host_address'])}
<h3>Propriété intellectuelle</h3>
<p>L'ensemble des contenus de ce site (textes, images, logos) est la propriété de {$e($name)} ou de ses partenaires et est protégé par le droit d'auteur. Toute reproduction sans autorisation est interdite.</p>
<h3>Responsabilité</h3>
<p>Les informations publiées sont fournies à titre indicatif. {$e($name)} ne saurait être tenu responsable des erreurs, omissions ou de l'utilisation faite de ces informations.</p>
{$e($l['custom_mentions'])}
<h2>Politique de confidentialité</h2>
<p>Les données personnelles collectées via les formulaires de ce site (nom, téléphone, email, message) sont destinées exclusivement à {$e($name)} afin de répondre à vos demandes (réservation, devis, contact). Elles ne sont jamais cédées à des tiers.</p>
<p>Base légale : votre consentement et l'exécution de mesures précontractuelles. Durée de conservation : 3 ans à compter du dernier contact.</p>
<p>Conformément au RGPD et à la loi Informatique et Libertés, vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation et d'opposition. Pour l'exercer : {$e($mail)}. Vous pouvez également saisir la CNIL (www.cnil.fr).</p>
<p>Ce site utilise uniquement des cookies techniques strictement nécessaires à son fonctionnement et une mesure d'audience anonymisée.</p>
{$e($l['custom_privacy'])}
HTML;
    }
}
