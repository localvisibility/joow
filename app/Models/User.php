<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /** E-mail « mot de passe oublié » en français, aux couleurs de Joow. */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token]).'?'.http_build_query(['email' => $this->email]);
        app(\App\Services\Accounts::class)->send($this, 'Choisissez un nouveau mot de passe', [
            'preheader' => 'Le lien pour changer votre mot de passe Joow.',
            'title' => 'Nouveau mot de passe',
            'intro' => \App\Services\Accounts::greeting($this)."\n\nVous avez demandé à changer votre mot de passe. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.",
            'cta' => ['label' => 'Choisir mon nouveau mot de passe', 'url' => $url],
            'note' => 'Ce lien est valable 60 minutes. Si vous n\'avez rien demandé, ignorez simplement cet e-mail : votre mot de passe ne change pas.',
        ]);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    /** Opérateur : voit l'ensemble du parc (emails listés dans config joow.admins). */
    public function isAdmin(): bool
    {
        return in_array(strtolower($this->email), config('joow.admins', []), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nurture' => 'array',
            'marketing_optout_at' => 'datetime',
            'password_set_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Exécute une opération Cashier en tolérant un changement de mode Stripe (test ↔ live) :
     * si le client Stripe mémorisé n'existe pas dans le mode courant, on l'oublie et on réessaie
     * une fois (Cashier recrée alors un client dans le bon mode).
     */
    public function withValidStripeCustomer(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            if ($this->stripe_id && str_contains($e->getMessage(), 'No such customer')) {
                $this->forceFill(['stripe_id' => null, 'pm_type' => null, 'pm_last_four' => null])->save();

                return $operation();
            }
            throw $e;
        }
    }
}
