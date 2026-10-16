<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Categorías habituales para empezar; el administrador puede cambiarlas.
        // El orden va de 10 en 10 para poder meter otras entre medias.
        $ahora = now();
        $categorias = ['Prebenjamín', 'Benjamín', 'Alevín', 'Infantil', 'Cadete', 'Juvenil', 'Senior'];

        DB::table('categories')->insert(array_map(fn ($nombre, $i) => [
            'nombre' => $nombre,
            'orden' => ($i + 1) * 10,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $categorias, array_keys($categorias)));
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
