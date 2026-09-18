<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Form\Actions\SaveFormBlockImage;
use App\Domain\Form\Models\Form;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Form\UploadFormBlockImageRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Envoi de l'image d'un bloc « Texte, image, vidéo ». Retirer l'image d'un
 * bloc ne passe pas par ici : la configuration du bloc oublie simplement son
 * chemin, le fichier restant dans la bibliothèque de l'organisation.
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
}
