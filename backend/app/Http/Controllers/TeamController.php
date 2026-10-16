<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    /**
     * Crea un equipo.
     */
    public function store(Request $request): JsonResponse
    {
        $equipo = Team::create($this->validar($request));

        AuditLog::registrar($request->user(), 'crear_equipo', $equipo);

        return response()->json($equipo->load('category:id,nombre'), 201);
    }

    /**
     * Edita un equipo.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $team->update($this->validar($request, $team));

        AuditLog::registrar($request->user(), 'editar_equipo', $team);

        return response()->json($team->load('category:id,nombre'));
    }

    /**
     * Borra un equipo que no tenga publicaciones ni partidos.
     */
    public function destroy(Request $request, Team $team): JsonResponse
    {
        if ($team->estaEnUso()) {
            return response()->json([
                'message' => "No se puede borrar «{$team->nombre}»: tiene publicaciones o partidos.",
            ], 422);
        }

        $team->delete();

        AuditLog::registrar($request->user(), 'borrar_equipo', $team);

        return response()->json([
            'message' => 'Equipo borrado.',
        ]);
    }

    /**
     * Copia los equipos de una temporada a otra, con el mismo nombre y categoría.
     * Los que ya existan en la temporada de destino no se duplican.
     */
    public function copySeason(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => ['required', 'string', 'max:20', Rule::exists('teams', 'temporada')],
            'hasta' => ['required', 'string', 'max:20', 'different:desde'],
        ]);

        $existentes = Team::where('temporada', $data['hasta'])->pluck('nombre')->map(fn ($nombre) => mb_strtolower($nombre));
        $creados = 0;

        foreach (Team::where('temporada', $data['desde'])->get() as $equipo) {
            if ($existentes->contains(mb_strtolower($equipo->nombre))) {
                continue;
            }

            Team::create([
                'nombre' => $equipo->nombre,
                'category_id' => $equipo->category_id,
                'temporada' => $data['hasta'],
            ]);
            $creados++;
        }

        AuditLog::registrar($request->user(), 'copiar_temporada', entidad: 'teams');

        return response()->json([
            'message' => "Se han creado {$creados} equipo(s) en la temporada {$data['hasta']}.",
            'creados' => $creados,
        ]);
    }

    /**
     * Valida los datos del equipo; no puede haber dos con el mismo nombre en la misma temporada.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Team $actual = null): array
    {
        $obligatorio = $actual ? 'sometimes' : 'required';
        $temporada = $request->input('temporada', $actual?->temporada);

        return $request->validate([
            'nombre' => [
                $obligatorio, 'string', 'max:100',
                Rule::unique('teams', 'nombre')->where('temporada', $temporada)->ignore($actual),
            ],
            'category_id' => [$obligatorio, 'integer', 'exists:categories,id'],
            'temporada' => [$obligatorio, 'string', 'max:20'],
        ], [
            'nombre.unique' => 'Ya existe un equipo con ese nombre en esa temporada.',
        ]);
    }
}
