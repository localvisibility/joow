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
