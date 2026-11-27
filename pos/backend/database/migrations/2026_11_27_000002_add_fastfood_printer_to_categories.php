<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Nouveau poste de préparation « fastfood », séparé de la cuisine.
    // Les catégories « Fast food … » qui partaient en cuisine y passent.
    // Sans imprimante nommée « fastfood » sur la machine, Electron imprime ces
    // bons sur l'imprimante cuisine (puis caisse) : rien ne se perd.
    public function up(): void
    {
        $this->setAllowedPrinters(['kitchen', 'fastfood', 'pizza', 'bar', 'receipt']);

        DB::table('categories')
            ->where('printer', 'kitchen')
            ->whereRaw('LOWER(name) LIKE ?', ['fast food%'])
            ->update(['printer' => 'fastfood']);
    }

    public function down(): void
    {
        DB::table('categories')->where('printer', 'fastfood')->update(['printer' => 'kitchen']);

        $this->setAllowedPrinters(['kitchen', 'bar', 'receipt', 'pizza']);
    }

    // categories.printer est un enum Laravel : sous PostgreSQL, une contrainte CHECK
    private function setAllowedPrinters(array $printers): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', array_map(fn ($p) => "'{$p}'::character varying", $printers));
        DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_printer_check');
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_printer_check CHECK (printer::text = ANY (ARRAY[{$list}]::text[]))");
    }
};
