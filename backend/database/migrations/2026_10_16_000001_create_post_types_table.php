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
        Schema::create('post_types', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Los mismos tipos que había fijos en el código; el administrador puede cambiarlos.
        $ahora = now();
        $tipos = ['Vídeo', 'Entrenamiento', 'Tarea', 'Jugada', 'Recurso'];

        DB::table('post_types')->insert(array_map(fn ($nombre, $i) => [
            'nombre' => $nombre,
            'orden' => ($i + 1) * 10,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $tipos, array_keys($tipos)));
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_types');
    }
};
