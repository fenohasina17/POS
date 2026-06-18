<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;

class PrometheusMetrics
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $start;

        $registry = new CollectorRegistry(new APC());

        $route = $request->route() ? $request->route()->uri() : $request->path();

        // Compteur : combien de requêtes reçues, par route et code de statut
        $counter = $registry->getOrRegisterCounter(
            'laravel',
            'http_requests_total',
            'Nombre total de requêtes HTTP',
            ['method', 'route', 'status']
        );
        $counter->inc([
            $request->method(),
            $route,
            (string) $response->getStatusCode(),
        ]);

        // Histogramme : combien de temps chaque requête a pris
        $histogram = $registry->getOrRegisterHistogram(
            'laravel',
            'http_request_duration_seconds',
            'Durée des requêtes HTTP en secondes',
            ['method', 'route'],
            [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        $histogram->observe($duration, [
            $request->method(),
            $route,
        ]);

        return $response;
    }
}
