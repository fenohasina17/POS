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
            
            $posId = $activePosId ?? $queryPosId;

            if ($posId) {
                $posId = (int) $posId;

                if ($user->hasRole('admin') || $user->pointsOfSale->contains($posId)) {
                    $request->attributes->set('activePosId', $posId);
                }
            }
        }

        return $next($request);
    }
}
