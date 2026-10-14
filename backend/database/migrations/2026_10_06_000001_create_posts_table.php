<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('titulo');
            $table->text('contenido')->nullable();
            $table->string('tipo');
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('temporada')->nullable();
            $table->string('visibilidad')->default('entrenadores');
            $table->timestamps();
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
