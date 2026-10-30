<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Console\Command;

/**
 * Import en lot des photos produits.
 *
 *   php artisan catalog:import-images /chemin/photos            → fichiers nommés par référence (PZ01.jpg…)
 *   php artisan catalog:import-images /chemin/photos --map=photos.json → {"PZ01": "pizza.jpeg", …}
 *
 * Passage à blanc par défaut ; --appliquer pour enregistrer.
 */
class ImportProductImages extends Command
{
    protected $signature = 'catalog:import-images {dir} {--map=} {--appliquer}';
    protected $description = 'Importe les photos des produits du catalogue (par référence)';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function handle(ProductImageService $images): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        if (!is_dir($dir)) {
            $this->error("Dossier introuvable : {$dir}");
            return self::FAILURE;
        }

        $map = $this->option('map')
            ? json_decode(file_get_contents($this->option('map')), true, flags: JSON_THROW_ON_ERROR)
            : $this->mapFromFileNames($dir);

        $apply = (bool) $this->option('appliquer');
        $this->info($apply ? '=== APPLICATION ===' : '=== PASSAGE À BLANC (rien n\'est modifié) ===');

        $done = 0;
        $errors = 0;
        foreach ($map as $ref => $file) {
            $product = Product::where('ref', $ref)->first();
            $path = "{$dir}/{$file}";
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (!$product) {
                $this->warn("  {$ref} : produit introuvable");
                $errors++;
                continue;
            }
            if (!is_file($path) || !in_array($extension, self::EXTENSIONS, true)) {
                $this->warn("  {$ref} : fichier image introuvable ou non pris en charge ({$file})");
                $errors++;
                continue;
            }

            $this->line("  {$ref} {$product->name} ← {$file}");
            if ($apply) {
                $images->store($product, file_get_contents($path), $extension);
            }
            $done++;
        }

        $this->info(($apply ? 'Photos enregistrées' : 'Photos à enregistrer') . " : {$done} — erreurs : {$errors}");
        if (!$apply) {
            $this->line('Pour appliquer : relancer avec --appliquer');
        }

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    private function mapFromFileNames(string $dir): array
    {
        $map = [];
        foreach (scandir($dir) as $file) {
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                $map[pathinfo($file, PATHINFO_FILENAME)] = $file;
            }
        }

        return $map;
    }
}
