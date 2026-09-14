<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Actions\IssueCheckInApiToken;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function store(LoginRequest $request, IssueCheckInApiToken $issueCheckInApiToken): JsonResponse
    {
        $token = $issueCheckInApiToken->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name')->toString(),
            $request->ip() ?? '',
        );

        return response()->json([
            'token' => $token->plainTextToken,
        ], 201);
    }

    /**
     * Point d'entrée de test de connexion : Zapier et n8n l'appellent au
     * moment où l'utilisateur colle sa clé API, pour la valider et afficher
     * à qui elle appartient (« Connecté en tant que … »). Une clé invalide
     * ressort en 401 via auth:sanctum, ce qu'ils savent interpréter.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Organization $organization */
        $organization = $request->attributes->get('apiOrganization');

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }
}
