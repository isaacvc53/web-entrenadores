<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión de etiquetas por el administrador. Los entrenadores no pasan por aquí:
 * sus etiquetas se crean al guardar una publicación (ver PostController).
 */
class TagController extends Controller
{
    /**
     * Crea una etiqueta. Las que crea el administrador van a la lista fija, salvo que diga lo contrario.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->reglas(), $this->mensajes());

        $etiqueta = new Tag($data + ['fija' => true]);
        $etiqueta->user_id = $request->user()->id;
        $etiqueta->save();

        AuditLog::registrar($request->user(), 'crear_etiqueta', $etiqueta);

        return response()->json($etiqueta, 201);
    }

    /**
     * Renombra una etiqueta o la pasa a la lista fija (o la saca de ella).
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $tag->update($request->validate($this->reglas($tag), $this->mensajes()));

        // Una etiqueta que sale de la lista fija y no usa nadie ya no hace falta.
        Tag::borrarSinUso();

        AuditLog::registrar($request->user(), 'editar_etiqueta', $tag);

        return response()->json($tag);
    }

    /**
     * Borra una etiqueta. Las publicaciones que la tenían se quedan sin ella, pero no se borran.
     */
    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $tag->delete();

        AuditLog::registrar($request->user(), 'borrar_etiqueta', $tag);

        return response()->json([
            'message' => "Etiqueta «{$tag->nombre}» borrada.",
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?Tag $actual = null): array
    {
        return [
            'nombre' => [$actual ? 'sometimes' : 'required', 'string', 'max:30', Rule::unique('tags', 'nombre')->ignore($actual)],
            'fija' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function mensajes(): array
    {
        return [
            'nombre.unique' => 'Ya hay una etiqueta con ese nombre.',
        ];
    }
}
