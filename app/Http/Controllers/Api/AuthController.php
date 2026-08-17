<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            if ($user?->firm_id) {
                Auditor::record(
                    action: 'failed_login',
                    module: 'security',
                    resourceName: 'User Session',
                    details: 'Failed login — incorrect password',
                    actor: $user,
                    firmId: $user->firm_id,
                );
            }

            return response()->json([
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        if ($user->firm && ! $user->firm->isActive()) {
            return response()->json([
                'message' => 'This workspace is currently unavailable.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $user->tokens()->where('name', 'web')->delete();

        $newDevice = Auditor::isNewDevice($user, $request->userAgent());
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ])->save();
        $user->load(['firm', 'role']);

        Auditor::record(
            action: $newDevice ? 'new_device_login' : 'login',
            module: 'security',
            resourceName: 'User Session',
            details: $newDevice ? 'Successful login from a new device' : 'Successful login',
            actor: $user,
            firmId: $user->firm_id,
            sessionId: (string) $token->accessToken->id,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $token = $user?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Signed out.',
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['firm', 'role']));
    }
}
