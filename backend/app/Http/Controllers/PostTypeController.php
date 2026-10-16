<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PostType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostTypeController extends Controller
{
    /**
     * Crea un tipo de publicación.
     */
    public function store(Request $request): JsonResponse
    {
        // Si no se indica el orden, el tipo nuevo va al final.
        $tipo = PostType::create($request->validate($this->reglas()) + [
            'orden' => (PostType::max('orden') ?? 0) + 10,
        ]);

        AuditLog::registrar($request->user(), 'crear_tipo', $tipo);

        return response()->json($tipo, 201);
    }

    /**
     * Renombra o reordena un tipo; las publicaciones lo siguen viendo con el nombre nuevo.
     */
    public function update(Request $request, PostType $postType): JsonResponse
    {
        $postType->update($request->validate($this->reglas($postType)));

        AuditLog::registrar($request->user(), 'editar_tipo', $postType);

        return response()->json($postType);
    }

    /**
     * Cambia el orden de los tipos: recibe sus identificadores ya ordenados
     * y los renumera de 10 en 10.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:post_types,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $posicion => $id) {
                PostType::whereKey($id)->update(['orden' => ($posicion + 1) * 10]);
            }
        });

        AuditLog::registrar($request->user(), 'ordenar_tipos', entidad: 'post_types');

        return response()->json(PostType::ordenados()->get());
    }

    /**
     * Borra un tipo que no use ninguna publicación.
     */
    public function destroy(Request $request, PostType $postType): JsonResponse
    {
        $publicaciones = $postType->posts()->count();

        if ($publicaciones > 0) {
            return response()->json([
                'message' => "No se puede borrar «{$postType->nombre}»: lo usan {$publicaciones} publicación(es).",
            ], 422);
        }

        $postType->delete();

        AuditLog::registrar($request->user(), 'borrar_tipo', $postType);

        return response()->json([
            'message' => 'Tipo borrado.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?PostType $actual = null): array
    {
        return [
            'nombre' => [$actual ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('post_types', 'nombre')->ignore($actual)],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
