<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Actions\SavePageBanner;
use App\Domain\Page\Actions\UpdatePage;
use App\Domain\Page\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Page\UpdatePageRequest;
use App\Http\Requests\Organizer\Page\UploadPageBannerRequest;
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
                'program_items' => $page !== null ? $page->program_items : [],
                'faq_items' => $page !== null ? $page->faq_items : [],
            ],
        ]);
    }

    public function update(int $event, UpdatePageRequest $request, UpdatePage $action): JsonResponse
    {
        $eventModel = $this->findEvent($event);

        $action->handle(
            organization: $eventModel->organization,
            eventId: $eventModel->id,
            metaDescription: $request->string('meta_description')->toString() ?: null,
            programItems: $request->input('program_items', []),
            faqItems: $request->input('faq_items', []),
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

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
