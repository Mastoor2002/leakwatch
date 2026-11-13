<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the mobile app with a personal access token
 * ("Authorization: Bearer lwu_...").
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByPlainToken((string) $request->bearerToken());

        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
