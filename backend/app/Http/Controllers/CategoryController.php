<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Crea una categoría.
     */
    public function store(Request $request): JsonResponse
    {
        // Si no se indica el orden, la categoría nueva va al final.
        $categoria = Category::create($request->validate($this->reglas()) + [
            'orden' => (Category::max('orden') ?? 0) + 10,
        ]);

        AuditLog::registrar($request->user(), 'crear_categoria', $categoria);

        return response()->json($categoria, 201);
    }

    /**
     * Renombra o reordena una categoría; los equipos la siguen viendo con el nombre nuevo.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $category->update($request->validate($this->reglas($category)));

        AuditLog::registrar($request->user(), 'editar_categoria', $category);

        return response()->json($category);
    }

    /**
     * Cambia el orden de las categorías: recibe sus identificadores ya ordenados
     * y las renumera de 10 en 10.
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $posicion => $id) {
                Category::whereKey($id)->update(['orden' => ($posicion + 1) * 10]);
            }
        });

        AuditLog::registrar($request->user(), 'ordenar_categorias', entidad: 'categories');

        return response()->json(Category::ordenadas()->get());
    }

    /**
     * Borra una categoría que no tenga equipos.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $equipos = $category->teams()->count();

        if ($equipos > 0) {
            return response()->json([
                'message' => "No se puede borrar «{$category->nombre}»: tiene {$equipos} equipo(s).",
            ], 422);
        }

        $category->delete();

        AuditLog::registrar($request->user(), 'borrar_categoria', $category);

        return response()->json([
            'message' => 'Categoría borrada.',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglas(?Category $actual = null): array
    {
        return [
            'nombre' => [$actual ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('categories', 'nombre')->ignore($actual)],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
