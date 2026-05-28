<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class SetActivePointOfSale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $activePosId = $request->header('X-Active-POS-ID');

            if ($activePosId) {
                $activePosId = (int) $activePosId;

                // Vérifier si l'utilisateur est associé à ce point de vente (ou s'il est admin)
                if ($user->hasRole('admin') || $user->pointsOfSale->contains($activePosId)) {
                    $request->attributes->set('activePosId', $activePosId);
                }
            }
        }

        return $next($request);
    }
}
