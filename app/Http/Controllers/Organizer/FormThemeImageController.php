<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Form\Actions\RemoveFormThemeImage;
use App\Domain\Form\Actions\SaveFormThemeImage;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\FormSettings;
use App\Domain\Organization\Models\OrganizationImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\UploadFormThemeImageRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logo et image de fond du thème d'un formulaire (panneau « Thème du
 * formulaire » du constructeur). La route limite {kind} à logo|background.
 * L'organisateur envoie une image ou en choisit une dans « Mes images ».
 */
final class FormThemeImageController extends Controller
{
    public function store(UploadFormThemeImageRequest $request, int $form, string $kind, SaveFormThemeImage $saveFormThemeImage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile|null $file */
        $file = $request->file('image');
        $image = $file ?? OrganizationImage::query()->findOrFail($request->integer('image_id'));

        $formModel = $saveFormThemeImage->handle(Form::query()->findOrFail($form), $user, $kind, $image);
        $path = (string) FormSettings::resolve($formModel->settings)['theme'][FormSettings::IMAGE_KINDS[$kind]];

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    public function destroy(Request $request, int $form, string $kind, RemoveFormThemeImage $removeFormThemeImage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removeFormThemeImage->handle(Form::query()->findOrFail($form), $user, $kind);

        return response()->json(null, 204);
    }
}
