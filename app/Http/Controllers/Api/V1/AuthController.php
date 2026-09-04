<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Actions\IssueCheckInApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use Illuminate\Http\JsonResponse;

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
}
