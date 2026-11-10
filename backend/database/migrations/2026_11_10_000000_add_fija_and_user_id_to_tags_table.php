<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración: las etiquetas de la lista fija (las pone el administrador)
     * se distinguen de las que crean los entrenadores al publicar, y se guarda quién la creó.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->boolean('fija')->default(false)->after('nombre');
            $table->foreignId('user_id')->nullable()->after('fija')->constrained()->nullOnDelete();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('fija');
        });
    }
};
