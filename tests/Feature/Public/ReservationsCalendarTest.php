<?php

namespace Tests\Feature\Public;

use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Site;
use App\Models\User;
use App\Services\Modules\StayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le propriétaire ferme des dates et ajoute une réservation prise en direct : le site ne les propose plus. */
class ReservationsCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_closes_dates_and_adds_a_direct_booking(): void
    {
        $user = User::factory()->create(['email' => 'proprio@yahoo.fr']);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'hebergement', 'status' => 'paid', 'owner_email' => 'proprio@yahoo.fr', 'user_id' => $user->id, 'site_data' => [], 'modules' => ['rooms' => ['enabled' => true]]]);
        $t = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'La Terrasse', 'price_night' => 180, 'sort_order' => 0, 'active' => true]);
        $l = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'Le Lodge', 'price_night' => 200, 'sort_order' => 1, 'active' => true]);
        $p = app(StayPlanner::class);
        $d = fn ($n) => now()->addDays($n)->toDateString();

        // Fermer La Terrasse du J+10 au J+12 inclus
        $this->actingAs($user)->post(route('reservations.block.store'), ['site_slug' => 'clos', 'room_id' => $t->id, 'start' => $d(10), 'end' => $d(12)])->assertRedirect();
        $this->assertFalse($p->free($t, $d(12), $d(13)));
        $this->assertTrue($p->free($t, $d(13), $d(14)));
        $this->assertTrue($p->free($l, $d(10), $d(12)));

        // Tout l'établissement
        $this->actingAs($user)->post(route('reservations.block.store'), ['site_slug' => 'clos', 'room_id' => null, 'start' => $d(20), 'end' => $d(20)])->assertRedirect();
        $this->assertFalse($p->free($l, $d(20), $d(21)));

        // Réservation directe : la chambre est obligatoire, puis ses dates sont fermées
        $this->actingAs($user)->post(route('reservations.stay.store'), ['site_slug' => 'clos', 'check_in' => $d(30), 'check_out' => $d(33), 'guests' => 2, 'name' => 'M. Durand'])->assertSessionHasErrors('room_id');
        $this->actingAs($user)->post(route('reservations.stay.store'), ['site_slug' => 'clos', 'room_id' => $l->id, 'check_in' => $d(30), 'check_out' => $d(33), 'guests' => 2, 'name' => 'M. Durand'])->assertSessionHasNoErrors();
        $this->assertFalse($p->free($l, $d(31), $d(32)));

        $this->actingAs($user)->get(route('reservations.index'))->assertOk()->assertInertia(fn ($pg) => $pg->has('blocks', 2)->has('rooms', 2));

        // Rouvrir
        $b = RoomBlock::where('room_id', $t->id)->first();
        $this->actingAs($user)->delete(route('reservations.block.destroy', $b->id))->assertRedirect();
        $this->assertTrue($p->free($t, $d(10), $d(13)));

        // Un autre compte ne peut rien fermer sur ce site
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('reservations.block.store'), ['site_slug' => 'clos', 'start' => $d(40), 'end' => $d(41)])->assertForbidden();
    }
}
