<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo se llamaban los tipos cuando estaban escritos en el código.
     */
    private const TIPOS_ANTIGUOS = [
        'video' => 'Vídeo',
        'entrenamiento' => 'Entrenamiento',
        'tarea' => 'Tarea',
        'jugada' => 'Jugada',
        'recurso' => 'Recurso',
    ];

    /**
     * Ejecuta la migración: las publicaciones pasan de tener el tipo escrito
     * a apuntar a una fila de la tabla de tipos.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('contenido')->constrained('post_types')->restrictOnDelete();
        });

        foreach (DB::table('posts')->select('id', 'tipo')->get() as $post) {
            $nombre = self::TIPOS_ANTIGUOS[$post->tipo] ?? ucfirst($post->tipo);

            $tipoId = DB::table('post_types')->where('nombre', $nombre)->value('id')
                ?? DB::table('post_types')->insertGetId([
                    'nombre' => $nombre,
                    'orden' => 1000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('posts')->where('id', $post->id)->update(['type_id' => $tipoId]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }

    /**
     * Deshace la migración.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('tipo')->default('')->after('contenido');
        });

        $nombresAntiguos = array_flip(self::TIPOS_ANTIGUOS);

        foreach (DB::table('posts')->select('id', 'type_id')->get() as $post) {
            $nombre = DB::table('post_types')->where('id', $post->type_id)->value('nombre') ?? '';

            DB::table('posts')->where('id', $post->id)->update([
                'tipo' => $nombresAntiguos[$nombre] ?? mb_strtolower($nombre),
            ]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
    }
};
