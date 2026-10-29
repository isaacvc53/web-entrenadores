<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración: solo quedan los roles administrador y entrenador,
     * y todas las publicaciones las ven todos, así que sobra la visibilidad.
     */
    public function up(): void
    {
        // Quien tuviera el rol de consulta pasa a ser entrenador; si no, el cambio de la columna fallaría.
        DB::table('users')->where('rol', 'consulta')->update(['rol' => 'entrenador']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'entrenador'])->default('entrenador')->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('visibilidad');
        });
    }

    /**
     * Deshace la migración. Las publicaciones vuelven como visibles para todos,
     * que es como se han visto mientras no existía la columna.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'entrenador', 'consulta'])->default('entrenador')->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('visibilidad')->default('entrenadores')->after('temporada');
        });

        DB::table('posts')->update(['visibilidad' => 'todos']);
    }
};
