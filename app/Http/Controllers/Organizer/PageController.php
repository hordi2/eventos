<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Domain\Page\Actions\SavePageBanner;
use App\Domain\Page\Actions\StorePageMedia;
use App\Domain\Page\Actions\UpdatePage;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Support\PageBlocks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Page\UpdatePageRequest;
use App\Http\Requests\Organizer\Page\UploadPageBannerRequest;
use App\Http\Requests\Organizer\Page\UploadPageImageRequest;
use App\Http\Requests\Organizer\Page\UploadPageMediaRequest;
use App\Models\User;
use App\Support\Guest\GuestFonts;
use App\Support\Invitation\BuildInvitationPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class PageController extends Controller
{
    public function edit(int $event): InertiaResponse
    {
        $eventModel = $this->findEvent($event);
        Gate::authorize('updateEvents', $eventModel->organization);

        $page = Page::query()->where('event_id', $eventModel->id)->first();

        return Inertia::render('Pages/Edit', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title, 'slug' => $eventModel->slug],
            'publicUrl' => route('guest.registration.start', [$eventModel->organization->slug, $eventModel->slug]),
            'page' => [
                'banner_url' => $page !== null && $page->banner_path !== null ? Storage::disk('public')->url($page->banner_path) : null,
                'meta_description' => $page?->meta_description,
                'cover_eyebrow' => $page?->cover_eyebrow,
                'cover_script' => $page?->cover_script,
                'cover_monogram' => $page?->cover_monogram,
                'cover_overlay' => $page === null ? 50 : $page->cover_overlay,
                'cover_cta_label' => $page?->cover_cta_label,
                'heading_font' => ($page === null ? null : $page->heading_font) ?? 'bodoni',
                'body_font' => ($page === null ? null : $page->body_font) ?? 'jakarta',
                'script_font' => ($page === null ? null : $page->script_font) ?? 'aucune',
                'blocks' => $this->presentBlocks($page),
            ],
            'blockTypes' => PageBlockType::options(),
            'fonts' => [
                'heading' => GuestFonts::options('heading'),
                'body' => GuestFonts::options('body'),
                'script' => GuestFonts::options('script'),
            ],
            // Aperçu du faire-part, sans quitter l'éditeur.
            'invitationPdfUrl' => route('events.page.invitation-pdf', $eventModel->id),
        ]);
    }

    /**
     * Faire-part en PDF, tel que le recevra un invité : de quoi le relire
     * avant de l'envoyer. Sans invité désigné, il porte le lien public de
     * l'événement et aucun code d'entrée.
     */
    public function invitationPdf(int $event, BuildInvitationPdf $buildInvitationPdf): Response
    {
        $eventModel = $this->findEvent($event);
        Gate::authorize('updateEvents', $eventModel->organization);

        return response($buildInvitationPdf->handle($eventModel), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($eventModel->title) ?: 'invitation').'.pdf"',
        ]);
    }

    public function update(int $event, UpdatePageRequest $request, UpdatePage $action): JsonResponse
    {
        $eventModel = $this->findEvent($event);

        $action->handle(
            organization: $eventModel->organization,
            eventId: $eventModel->id,
            metaDescription: $request->string('meta_description')->toString() ?: null,
            blocks: $request->input('blocks', []),
            user: $request->user(),
            cover: $request->only([
                'cover_eyebrow', 'cover_script', 'cover_monogram', 'cover_overlay', 'cover_cta_label',
                'heading_font', 'body_font', 'script_font',
            ]),
        );

        return response()->json(['status' => 'ok']);
    }

    public function uploadBanner(int $event, UploadPageBannerRequest $request, SavePageBanner $action): JsonResponse
    {
        $eventModel = $this->findEvent($event);

        $page = $action->handle($eventModel->organization, $eventModel->id, $request->file('banner'), $request->user());

        return response()->json(['banner_url' => Storage::disk('public')->url($page->banner_path)]);
    }

    /**
     * Image d'un bloc : elle rejoint la bibliothèque de l'organisation,
     * comme celles du constructeur de formulaire, et y est protégée tant
     * qu'une page s'en sert (FindOrganizationImageUsage).
     */
    public function uploadImage(int $event, UploadPageImageRequest $request, StoreOrganizationImage $action): JsonResponse
    {
        $eventModel = $this->findEvent($event);
        /** @var User $user */
        $user = $request->user();
        $image = $action->handle($eventModel->organization, $user, $request->file('image'));

        return response()->json(['path' => $image->path, 'url' => Storage::disk('public')->url($image->path)]);
    }

    /**
     * Mot d'accueil déposé dans Itaza : le fichier part en quarantaine et
     * ne se jouera qu'une fois l'analyse antivirus passée. L'éditeur en est
     * averti par le statut renvoyé.
     */
    public function uploadMedia(int $event, UploadPageMediaRequest $request, StorePageMedia $action): JsonResponse
    {
        $eventModel = $this->findEvent($event);
        /** @var User $user */
        $user = $request->user();
        $media = $action->handle($eventModel->organization, $eventModel->id, $user, $request->file('media'));

        return response()->json([
            'token' => $media->token,
            'name' => $media->original_name,
            'status' => $media->scan_status->value,
            'statusLabel' => $media->scan_status->label(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentBlocks(?Page $page): array
    {
        return array_map(
            fn (array $block): array => [
                ...$block,
                'url' => isset($block['path']) ? Storage::disk('public')->url($block['path']) : null,
                'backgroundUrl' => isset($block['background']) ? Storage::disk('public')->url($block['background']) : null,
                // Chaque photo de galerie porte aussi son adresse d'aperçu.
                'items' => array_map(
                    fn (array $item): array => [
                        ...$item,
                        'url' => isset($item['path']) ? Storage::disk('public')->url($item['path']) : null,
                    ],
                    $block['items'] ?? [],
                ),
            ],
            PageBlocks::resolve($page),
        );
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
