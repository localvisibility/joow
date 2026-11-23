<?php

namespace Tests\Feature\Public;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Site;
use App\Models\User;
use App\Services\Modules\StayPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StayImportTest extends TestCase
{
    use RefreshDatabase;

    private function setUpSite(): array
    {
        $user = User::factory()->create(['email' => 'proprio@yahoo.fr']);
        $site = Site::create(['slug' => 'clos', 'name' => 'Le Clos', 'sector' => 'hebergement', 'status' => 'paid', 'owner_email' => 'proprio@yahoo.fr', 'user_id' => $user->id, 'site_data' => [], 'modules' => ['rooms' => ['enabled' => true]]]);
        $t = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'La Terrasse', 'sort_order' => 0, 'active' => true]);
        $l = Room::create(['site_id' => $site->id, 'site_slug' => 'clos', 'name' => 'Le Lodge', 'sort_order' => 1, 'active' => true]);

        return [$user, $site, $t, $l];
    }

    public function test_csv_export_is_analysed_then_imported_as_confirmed_bookings(): void
    {
        [$user, $site, $t, $l] = $this->setUpSite();
        $y = now()->addYear()->year;
        $csv = "Export Bed-Booking\n\nArrivée;Départ;Chambre;Client;Téléphone;E-mail;Personnes;Montant;Statut\n"
            ."12/03/$y;15/03/$y;Terrasse;Jean Dupont;0601020304;jean@exemple.fr;2;540,00 €;Confirmée\n"
            ."12/03/$y;14/03/$y;Lodge;Marie Curie;;;2;400;Confirmée\n"
            ."01/04/$y;03/04/$y;Suite inconnue;Paul;;;2;;\n"
            ."05/05/$y;07/05/$y;Terrasse;Annulé Client;;;2;;Annulée\n"
            ."05/01/2020;07/01/2020;Terrasse;Ancien;;;2;;\n"
            ."12/03/$y;15/03/$y;Terrasse;Jean Dupont;;;2;;\n";
        $file = UploadedFile::fake()->createWithContent('export.csv', mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

        $r = $this->actingAs($user)->post(route('reservations.import'), ['site_slug' => 'clos', 'file' => $file, 'dry' => 1], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['ok' => 2, 'duplicate' => 1, 'past' => 1, 'cancelled' => 1, 'no_room' => 1, 'invalid' => 0], $r->json('summary'));
        $this->assertSame(0, RoomBooking::count());

        $file = UploadedFile::fake()->createWithContent('export.csv', mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));
        $this->actingAs($user)->post(route('reservations.import'), ['site_slug' => 'clos', 'file' => $file, 'room_id' => $l->id], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('imported', 3);
        $b = RoomBooking::where('name', 'Jean Dupont')->first();
        $this->assertSame([$t->id, 'confirmed', 540.0, 3], [$b->room_id, $b->status, (float) $b->total, (int) $b->nights]);
        $this->assertFalse(app(StayPlanner::class)->free($t, "$y-03-13", "$y-03-14"));
        $this->assertSame($l->id, RoomBooking::where('name', 'Paul')->first()->room_id); // chambre choisie pour les lignes sans correspondance

        // Réimport : tout est déjà présent
        $file = UploadedFile::fake()->createWithContent('export.csv', $csv);
        $this->actingAs($user)->post(route('reservations.import'), ['site_slug' => 'clos', 'file' => $file, 'room_id' => $l->id], ['Accept' => 'application/json'])->assertJsonPath('imported', 0);
    }

    public function test_xlsx_with_excel_dates_and_nights_column(): void
    {
        [$user, , $t] = $this->setUpSite();
        $serial = (int) now()->addMonths(3)->startOfDay()->diffInDays(\Illuminate\Support\Carbon::create(1899, 12, 30)->startOfDay(), true);
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Date d\'arrivée</t></si><si><t>Nuits</t></si><si><t>Logement</t></si><si><t>Nom</t></si><si><t>La Terrasse</t></si><si><t>Famille Martin</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
            .'<row r="2"><c r="A2"><v>'.$serial.'</v></c><c r="B2"><v>4</v></c><c r="C2" t="s"><v>4</v></c><c r="D2" t="s"><v>5</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $this->actingAs($user)->post(route('reservations.import'), ['site_slug' => 'clos', 'file' => new UploadedFile($path, 'reservations.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('imported', 1);
        $b = RoomBooking::first();
        $this->assertSame([$t->id, now()->addMonths(3)->toDateString(), 4], [$b->room_id, $b->check_in->toDateString(), (int) $b->nights]);
    }

    public function test_upcoming_history_stays_are_activated(): void
    {
        [, $site, $t, $l] = $this->setUpSite();
        $d = fn ($n) => now()->addDays($n)->toDateString();
        RoomBooking::create(['site_id' => $site->id, 'site_slug' => 'clos', 'room_id' => $t->id, 'check_in' => $d(10), 'check_out' => $d(12), 'nights' => 2, 'guests' => 2, 'name' => 'Confirmé', 'notes' => 'Demande reçue sur l\'ancien calendrier (statut : confirmed).', 'status' => 'archived', 'source' => 'ancien-calendrier']);
        RoomBooking::create(['site_id' => $site->id, 'site_slug' => 'clos', 'room_id' => $l->id, 'check_in' => $d(20), 'check_out' => $d(22), 'nights' => 2, 'guests' => 2, 'name' => 'Sans statut', 'notes' => 'Demande reçue sur l\'ancien calendrier.', 'status' => 'archived', 'source' => 'ancien-calendrier']);

        $this->artisan('lv:activate', ['site' => 'clos', '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, RoomBooking::where('status', 'confirmed')->count());
        $this->artisan('lv:activate', ['site' => 'clos'])->assertSuccessful();
        $this->assertSame(['Confirmé'], RoomBooking::where('status', 'confirmed')->pluck('name')->all());
        $this->artisan('lv:activate', ['site' => 'clos', '--all' => true])->assertSuccessful();
        $this->assertSame(2, RoomBooking::where('status', 'confirmed')->count());
    }
}
