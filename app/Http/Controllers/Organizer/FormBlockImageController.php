<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Form\Actions\RemoveFormBlockImage;
use App\Domain\Form\Actions\SaveFormBlockImage;
use App\Domain\Form\Models\Form;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\UploadFormBlockImageRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Image d'un bloc « Texte, image, vidéo » du constructeur : envoyée avant
 * l'enregistrement du bloc, dont la configuration ne garde que son chemin.
 */
final class FormBlockImageController extends Controller
{
    public function store(UploadFormBlockImageRequest $request, int $form, SaveFormBlockImage $saveFormBlockImage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $image */
        $image = $request->file('image');

        return response()->json($saveFormBlockImage->handle(Form::query()->findOrFail($form), $user, $image));
    }

    public function destroy(Request $request, int $form, RemoveFormBlockImage $removeFormBlockImage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removeFormBlockImage->handle(Form::query()->findOrFail($form), $user, (string) $request->input('path'));

        return response()->json(null, 204);
    }
}
