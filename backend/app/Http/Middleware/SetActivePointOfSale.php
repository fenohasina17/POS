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

                // Vérifier si l'utilisateur est associé à ce point de vente
                if ($user->pointsOfSale->contains($activePosId)) {
                    $request->attributes->set('activePosId', $activePosId);
                    // Stocker également dans la session pour les cas où le header n'est pas toujours envoyé (ex: formulaires web)
                    $request->session()->put('activePosId', $activePosId);
                } else {
                    // Si le POS n'est pas valide pour cet utilisateur, on peut décider de rejeter
                    // ou d'ignorer le header. Pour l'instant, on l'ignorera silencieusement.
                    // Ou bien, on pourrait retourner un 403:
                    // return response()->json(['message' => 'Accès refusé pour ce point de vente'], 403);
                }
            } else {
                // Si pas de header, tenter de récupérer depuis la session
                $sessionActivePosId = $request->session()->get('activePosId');
                if ($sessionActivePosId && $user->pointsOfSale->contains($sessionActivePosId)) {
                    $request->attributes->set('activePosId', $sessionActivePosId);
                }
            }
        }

        return $next($request);
    }
}
