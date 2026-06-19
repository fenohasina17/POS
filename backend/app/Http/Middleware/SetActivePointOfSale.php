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
            $queryPosId = $request->query('point_of_sale_id');
            
            \Log::info("SetActivePointOfSale DEBUG: User: {$user->id}, Header X-Active-POS-ID: " . ($activePosId ?? 'NULL') . ", Query point_of_sale_id: " . ($queryPosId ?? 'NULL'));

            // Prioritize header, fallback to query param for compatibility if middleware allows
            $posId = $activePosId ?? $queryPosId;

            if ($posId) {
                $posId = (int) $posId;

                // Vérifier si l'utilisateur est associé à ce point de vente
                if ($user->hasRole('admin') || $user->pointsOfSale->contains($posId)) {
                    $request->attributes->set('activePosId', $posId);
                } else {
                    \Log::warning("SetActivePointOfSale: User {$user->id} not associated with POS {$posId}");
                }
            }
        }

        return $next($request);
    }
}
