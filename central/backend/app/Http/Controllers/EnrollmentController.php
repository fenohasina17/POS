<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class EnrollmentController extends Controller
{
    /**
     * Enrôlement d'une nouvelle caisse : install.sh envoie les identifiants
     * d'un admin du siège et reçoit en retour la clé API partagée
     * (CENTRAL_API_KEY), sans copier-coller manuel.
     *
     * Endpoint dédié plutôt que /auth/login + jeton : login supprime les
     * jetons "dashboard" existants, ce qui déconnecterait l'admin de son
     * dashboard à chaque installation de caisse.
     *
     * POST /api/terminals/enroll
     */
    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'email'         => 'required|email',
            'password'      => 'required|string',
            'restaurant_id' => 'required|string|max:100',
            'terminal_id'   => 'required|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->audit()->warning('Enrôlement refusé : identifiants incorrects', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);
            return $this->reply(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($user->role !== 'admin') {
            $this->audit()->warning('Enrôlement refusé : compte non admin', [
                'email' => $user->email,
                'ip'    => $request->ip(),
            ]);
            return $this->reply(['message' => 'Accès réservé aux administrateurs.'], 403);
        }

        $apiKey = config('app.central_api_key');
        if (empty($apiKey)) {
            return $this->reply(['message' => 'Serveur central non configuré (CENTRAL_API_KEY manquant).'], 503);
        }

        // Trace d'audit : qui a enrôlé quelle caisse, depuis où
        $this->audit()->info('Caisse enrôlée', [
            'admin'         => $user->email,
            'restaurant_id' => $request->restaurant_id,
            'terminal_id'   => $request->terminal_id,
            'ip'            => $request->ip(),
        ]);

        return $this->reply(['api_key' => $apiKey]);
    }

    /**
     * Journal d'audit dédié (storage/logs/enrollment.log) : le .env de
     * production fixe LOG_LEVEL=error, ce qui ferait disparaître ces traces
     * info/warning du journal principal.
     */
    private function audit(): LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/enrollment.log'),
            'level'  => 'info',
        ]);
    }

    /**
     * JSON sans échappement des accents ni des "/" : install.sh lit cette
     * réponse avec sed (pas de jq sur une Debian minimale), il doit pouvoir
     * afficher le message et extraire la clé telles quelles.
     */
    private function reply(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
