<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use App\Observers\SaleObserver;
use App\Models\Sale;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Binding pour FilePrintConnector (USB)
        $this->app->bind(FilePrintConnector::class, function ($app, $params) {
            $path = $params['path'] ?? '';
            \Log::info('FilePrintConnector créé avec chemin: ' . $path);
            return new FilePrintConnector($path);
        });

        // Binding pour NetworkPrintConnector (Réseau)
        $this->app->bind(NetworkPrintConnector::class, function ($app, $params) {
            return new NetworkPrintConnector(
                $params['ip'] ?? 'localhost',
                $params['port'] ?? 9100,
                $params['timeout'] ?? 30
            );
        });

        // Binding pour CupsPrintConnector (CUPS)
        $this->app->bind(CupsPrintConnector::class, function ($app, $params) {
            return new CupsPrintConnector($params['printerName'] ?? '');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route model binding for Role
        Route::model('role', Role::class);

        // Règle de validation pour les images en base64
        Validator::extend('base64image', function ($attribute, $value, $parameters, $validator) {
            try {
                $decoded = base64_decode($value, true);
                if ($decoded === false) return false;

                $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $decoded);
                return in_array($mime, ['image/jpeg', 'image/png', 'image/gif']);
            } catch (\Exception $e) {
                return false;
            }
        });

        // Règle de validation pour la taille maximale en base64
        Validator::extend('base64max', function ($attribute, $value, $parameters, $validator) {
            $size = (strlen($value) * 3 / 4) - (substr_count($value, '=') ?: 0);
            return $size <= ($parameters[0] * 1024);
        });
        Sale::observe(SaleObserver::class);
    }
  
}