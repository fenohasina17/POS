<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCentralApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('app.central_api_key');

        if (empty($expected)) {
            return response()->json(['message' => 'Serveur central non configuré (CENTRAL_API_KEY manquant).'], 503);
        }

        $provided = $request->bearerToken();

        if (! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Clé API invalide.'], 401);
        }

        return $next($request);
    }
}
