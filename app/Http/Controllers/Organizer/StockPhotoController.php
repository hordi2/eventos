<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\ImportStockPhoto;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\SearchStockPhotosRequest;
use App\Models\User;
use App\Support\Images\PexelsClient;
use App\Support\Images\PresentOrganizationImages;
use App\Support\Images\StockPhotosUnavailableException;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Onglet « Bibliothèque » du constructeur : recherche de photos libres de
 * droits (Pexels), puis copie de celle choisie dans « Mes images ».
 */
final class StockPhotoController extends Controller
{
    public function index(SearchStockPhotosRequest $request, PexelsClient $pexelsClient): JsonResponse
    {
        try {
            return response()->json($pexelsClient->search((string) $request->input('query', ''), $request->integer('page', 1)));
        } catch (StockPhotosUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function store(Request $request, int $photo, ImportStockPhoto $importStockPhoto, PresentOrganizationImages $presentOrganizationImages): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());

        try {
            return response()->json($presentOrganizationImages->row($importStockPhoto->handle($organization, $user, $photo)), 201);
        } catch (StockPhotosUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
