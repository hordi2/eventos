<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\StoreOrganizationImage;
use App\Domain\Page\Actions\SavePageBanner;
use App\Domain\Page\Actions\UpdatePage;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageBlockType;
use App\Domain\Page\Support\PageBlocks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Page\UpdatePageRequest;
use App\Http\Requests\Organizer\Page\UploadPageBannerRequest;
use App\Http\Requests\Organizer\Page\UploadPageImageRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
                'blocks' => $this->presentBlocks($page),
            ],
            'blockTypes' => PageBlockType::options(),
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
     * @return list<array<string, mixed>>
     */
    private function presentBlocks(?Page $page): array
    {
        return array_map(
            fn (array $block): array => [
                ...$block,
                'url' => isset($block['path']) ? Storage::disk('public')->url($block['path']) : null,
            ],
            PageBlocks::resolve($page),
        );
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
