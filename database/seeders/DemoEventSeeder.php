<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\EventType;
use App\Domain\Event\Models\Venue;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Models\Form;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Actions\SavePageBanner;
use App\Domain\Page\Actions\UpdatePage;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Une invitation de démonstration, complète : le mariage de Michel et
 * Allegria. Elle sert à voir d'un coup d'œil ce que reçoit un invité — la
 * page web feuillet par feuillet et le faire-part en PDF, qui doivent se
 * ressembler page pour page.
 *
 * Rejouable : tout se retrouve par son slug, rien n'est créé deux fois.
 * Les photos sont dessinées ici même, pour ne pas alourdir le dépôt.
 */
final class DemoEventSeeder extends Seeder
{
    private const ORGANIZATION = 'michel-allegria';

    private const EVENT = 'mariage-michel-allegria';

    public function run(): void
    {
        $organization = Organization::withoutGlobalScopes()->where('slug', self::ORGANIZATION)->first()
            ?? Organization::query()->create(['name' => 'Michel & Allegria', 'slug' => self::ORGANIZATION]);

        app(CurrentOrganization::class)->set($organization);

        try {
            $admin = $this->admin($organization);
            $event = $this->event($organization, $admin);
            $this->form($organization, $event, $admin);
            $this->page($organization, $event, $admin);
        } finally {
            app(CurrentOrganization::class)->clear();
        }

        $this->command->info("Invitation de démonstration : /r/{$organization->slug}/{$event->slug}");
    }

    private function admin(Organization $organization): User
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'demo@itaza.test'],
            ['name' => 'Hordy Lusala', 'password' => 'mot-de-passe-de-demonstration'],
        );

        if ($admin->memberships()->where('organization_id', $organization->id)->doesntExist()) {
            $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
        }

        return $admin;
    }

    private function event(Organization $organization, User $admin): Event
    {
        $event = Event::query()->where('slug', self::EVENT)->first();

        if ($event !== null) {
            return $event;
        }

        $venue = Venue::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Salle Concorde',
            'address' => '12, avenue de la Justice, Gombe, Kinshasa',
        ]);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $admin->id,
            'venue_id' => $venue->id,
            'title' => 'Mariage de Michel & Allegria',
            'slug' => self::EVENT,
            'type' => EventType::Wedding,
            'status' => EventStatus::Published,
            'description' => 'Deux familles, une seule joie : nous vous attendons pour célébrer avec nous.',
            // Toujours à venir, quel que soit le jour où la démonstration tourne.
            'start_at' => CarbonImmutable::now('Africa/Kinshasa')->addMonths(3)->setTime(14, 0)->utc(),
            'end_at' => CarbonImmutable::now('Africa/Kinshasa')->addMonths(3)->setTime(23, 0)->utc(),
            'timezone' => 'Africa/Kinshasa',
        ]);
    }

    private function form(Organization $organization, Event $event, User $admin): void
    {
        if (Form::query()->where('event_id', $event->id)->exists()) {
            return;
        }

        $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Réponse', 'fields' => []]);
        app(PublishFormVersion::class)->handle($form, $admin);
    }

    private function page(Organization $organization, Event $event, User $admin): void
    {
        $cover = $this->photo('couverture', [46, 38, 33], [140, 112, 84]);
        $full = $this->photo('pleine-page', [107, 84, 71], [219, 204, 184]);
        $first = $this->photo('galerie-1', [184, 168, 143], [242, 237, 227]);
        $second = $this->photo('galerie-2', [77, 87, 84], [199, 194, 179]);

        app(SavePageBanner::class)->handle($organization, $event->id, $cover, $admin);
        $photo = app(StoreOrganizationImage::class)->handle($organization, $admin, $full);
        $one = app(StoreOrganizationImage::class)->handle($organization, $admin, $first);
        $two = app(StoreOrganizationImage::class)->handle($organization, $admin, $second);

        app(UpdatePage::class)->handle(
            organization: $organization,
            eventId: $event->id,
            metaDescription: 'Le mariage de Michel et Allegria, le 12 novembre à Kinshasa.',
            blocks: [
                ['id' => 'std', 'type' => 'save_the_date', 'title' => 'Save the date', 'body' => "Les familles Lusala et Mbuyi ont l'honneur de vous convier au mariage de leurs enfants."],
                ['id' => 'prog', 'type' => 'program', 'title' => 'Programme', 'showIcons' => true, 'items' => [
                    ['time' => '14h00', 'title' => 'Accueil des invités', 'icon' => 'accueil'],
                    ['time' => '15h00', 'title' => 'Cérémonie', 'icon' => 'ceremonie'],
                    ['time' => '18h00', 'title' => 'Dîner', 'icon' => 'repas'],
                    ['time' => '21h00', 'title' => 'Première danse', 'icon' => 'danse'],
                ]],
                ['id' => 'det', 'type' => 'details', 'title' => 'Bon à savoir', 'items' => [
                    ['title' => 'Thème', 'time' => 'Blanc et or'],
                    ['title' => 'Dress code', 'time' => 'Tenue de soirée'],
                ]],
                ['id' => 'photo', 'type' => 'full_photo', 'title' => 'À très bientôt', 'path' => $photo->path, 'body' => 'Le grand jour, à Kinshasa.'],
                ['id' => 'gal', 'type' => 'gallery', 'title' => 'Nos photos', 'items' => [
                    ['path' => $one->path, 'description' => 'Les fiançailles'],
                    ['path' => $two->path, 'description' => 'Le jour des familles'],
                ]],
                ['id' => 'mot', 'type' => 'welcome_message', 'title' => 'Le mot des mariés', 'url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'body' => "Deux minutes, pour vous dire merci d'être là."],
            ],
            user: $admin,
            cover: [
                'cover_eyebrow' => 'Vous êtes invité',
                'cover_script' => 'Save the date',
                'cover_monogram' => 'M & A',
                'cover_overlay' => 55,
                'cover_cta_label' => "Répondre à l'invitation",
                'heading_font' => 'cormorant',
                'body_font' => 'jost',
                'script_font' => 'great-vibes',
            ],
        );
    }

    /**
     * Une photo de démonstration : un dégradé et sa lune. Dessinée plutôt
     * qu'embarquée — le dépôt n'a pas à porter des images d'exemple.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function photo(string $name, array $from, array $to): UploadedFile
    {
        $width = 1600;
        $height = 2000;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $step = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) round($from[0] + ($to[0] - $from[0]) * $step),
                (int) round($from[1] + ($to[1] - $from[1]) * $step),
                (int) round($from[2] + ($to[2] - $from[2]) * $step),
            );
            imageline($image, 0, $y, $width, $y, (int) $color);
        }

        $this->moon($image, $width, $height);

        $path = tempnam(sys_get_temp_dir(), 'itaza-demo').'.jpg';
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        return new UploadedFile($path, "{$name}.jpg", 'image/jpeg', null, true);
    }

    private function moon(GdImage $image, int $width, int $height): void
    {
        $white = imagecolorallocatealpha($image, 255, 255, 255, 105);

        if ($white === false) {
            return;
        }

        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2.4), (int) ($width / 1.6), (int) ($width / 1.6), $white);
    }
}
