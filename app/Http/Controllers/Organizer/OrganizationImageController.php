<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\DeleteOrganizationImage;
use App\Domain\Organization\Models\OrganizationImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\DeleteOrganizationImageRequest;
use App\Support\Images\PresentOrganizationImages;
use Illuminate\Http\JsonResponse;

/**
 * Onglet « Mes images » du constructeur : la bibliothèque d'images de
 * l'organisation, alimentée par les envois de logo, de fond et de blocs.
 */
final class OrganizationImageController extends Controller
{
    public function index(PresentOrganizationImages $presentOrganizationImages): JsonResponse
    {
        return response()->json($presentOrganizationImages->handle());
    }

    public function destroy(
        DeleteOrganizationImageRequest $request,
        OrganizationImage $image,
        DeleteOrganizationImage $deleteOrganizationImage,
    ): JsonResponse {
        $deleteOrganizationImage->handle($image);

        return response()->json(null, 204);
    }
}
