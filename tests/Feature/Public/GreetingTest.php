<?php

namespace Tests\Feature\Public;

use App\Services\Accounts;
use Tests\TestCase;

class GreetingTest extends TestCase
{
    public function test_greeting_uses_a_real_first_name_only(): void
    {
        $this->assertSame('Marie', Accounts::firstName('Marie Dupont', 'mdupont@gmail.com'));
        $this->assertSame('Jean-Luc', Accounts::firstName('jean-luc martin', 'jl@exemple.fr'));
        $this->assertNull(Accounts::firstName('Contact', 'contact@local-visibility.com')); // nom fabriqué depuis l'e-mail
        $this->assertNull(Accounts::firstName('Info', 'info@spazio.fr'));
        $this->assertNull(Accounts::firstName('Jean.Dupont', 'jean.dupont@gmail.com'));
        $this->assertNull(Accounts::firstName('Le Spazio', 'resa@spazio.fr'));             // nom d'établissement
        $this->assertNull(Accounts::firstName('marie', 'marie@gmail.com'));               // tiré de l'e-mail : pas sûr
        $this->assertNull(Accounts::firstName('', null));
    }
}
