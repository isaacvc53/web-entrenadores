<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración: los equipos pasan de tener la categoría escrita
     * a apuntar a una fila de la tabla de categorías.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('nombre')->constrained()->restrictOnDelete();
        });

        // Si ya hay equipos, se busca su categoría por el nombre, o se crea si no existe.
        foreach (DB::table('teams')->select('id', 'categoria')->get() as $equipo) {
            $categoriaId = DB::table('categories')->where('nombre', $equipo->categoria)->value('id')
                ?? DB::table('categories')->insertGetId([
                    'nombre' => $equipo->categoria,
                    'orden' => 1000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('teams')->where('id', $equipo->id)->update(['category_id' => $categoriaId]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('categoria')->default('')->after('nombre');
        });

        foreach (DB::table('teams')->select('id', 'category_id')->get() as $equipo) {
            DB::table('teams')->where('id', $equipo->id)->update([
                'categoria' => DB::table('categories')->where('id', $equipo->category_id)->value('nombre') ?? '',
            ]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
