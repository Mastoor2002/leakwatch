<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Surfacewatch agent with its per-site bearer token.
 * Only a SHA-256 hash of the token is stored, like a password.
 */
class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = Site::findByAgentToken((string) $request->bearerToken());

        if ($site === null) {
            return response()->json(['message' => 'Invalid or missing agent token.'], 401);
        }

        $request->attributes->set('site', $site);

        return $next($request);
    }
}
