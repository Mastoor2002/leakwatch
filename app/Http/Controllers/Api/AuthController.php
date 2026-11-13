<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for the mobile app: exchange email + password for a bearer token.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $issued = ApiToken::issue($user, $data['device_name']);

        return response()->json([
            'token' => $issued['token'],
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('api_token');

        $token?->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing('teams', 'currentTeam');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'current_team' => $user->currentTeam ? $this->teamPayload($user, $user->currentTeam) : null,
            'teams' => $user->teams->map(fn (Team $team) => $this->teamPayload($user, $team))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamPayload(User $user, Team $team): array
    {
        return [
            'id' => $team->id,
            'slug' => $team->slug,
            'name' => $team->name,
            'role' => $user->teamRole($team)?->value,
            'alerts_enabled' => (bool) $team->alerts_enabled,
        ];
    }
}
