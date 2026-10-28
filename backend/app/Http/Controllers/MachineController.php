<?php

namespace App\Http\Controllers;

use App\Jobs\SonderMachine;
use App\Models\Machine;
use App\Moteur\MachineDocker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Serveurs Docker du moteur, réservés à l'équipe (voir le middleware « administrateur »).
 * Chaque nouvelle copie part sur la machine active et joignable qui a le plus de mémoire
 * libre ; en maintenance, une machine ne reçoit plus rien mais garde ses copies.
 */
class MachineController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Machine::query()->orderBy('nom')->get()->map->versApi()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:64', 'unique:machines,nom'],
            'hote_docker' => ['nullable', 'string', 'max:255', $this->regleHote()],
            'capacite_mo' => ['nullable', 'integer', 'min:512', 'max:1048576'],
        ]);

        $machine = Machine::query()->create($donnees);
        SonderMachine::dispatch($machine->id);

        return response()->json($machine->refresh()->versApi(), 201);
    }

    public function show(Machine $machine): JsonResponse
    {
        return response()->json($machine->versApi());
    }

    public function update(Request $request, Machine $machine): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['sometimes', 'string', 'max:64', Rule::unique('machines', 'nom')->ignore($machine->id)],
            'hote_docker' => ['sometimes', 'nullable', 'string', 'max:255', $this->regleHote()],
            'capacite_mo' => ['sometimes', 'nullable', 'integer', 'min:512', 'max:1048576'],
            'etat' => ['sometimes', 'string', Rule::in(['active', 'maintenance'])],
        ]);

        // Changer de démon avec des copies dessus les abandonnerait (arrêts et sauvegardes
        // partiraient sur le mauvais démon) : elles se terminent d'abord, ou la machine reste.
        if (array_key_exists('hote_docker', $donnees)
            && ($donnees['hote_docker'] ?? null) !== $machine->hote_docker
            && $machine->nbCopies() > 0) {
            throw new ConflictHttpException('Des copies tournent encore sur cette machine : le démon ne peut pas changer tant qu’elles n’ont pas été arrêtées.');
        }

        $machine->fill($donnees)->save();

        if (array_key_exists('hote_docker', $donnees)) {
            SonderMachine::dispatch($machine->id);
        }

        return response()->json($machine->refresh()->versApi());
    }

    public function destroy(Machine $machine): JsonResponse
    {
        if ($machine->nbCopies() > 0) {
            throw new ConflictHttpException('Des copies tournent encore sur cette machine : arrêtez-les avant de la retirer.');
        }

        $machine->delete();

        return response()->json(null, 204);
    }

    /**
     * La sonde tourne sur le moteur, seul à parler à Docker : 202 tout de suite avec l'état
     * stocké, à relire après quelques secondes.
     */
    public function sonder(Machine $machine): JsonResponse
    {
        SonderMachine::dispatch($machine->id);

        return response()->json($machine->versApi(), 202);
    }

    private function regleHote(): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec): void {
            if (! MachineDocker::hoteValide(is_string($valeur) ? $valeur : null)) {
                $echec('Le démon Docker doit être unix:///… ou tcp://hôte:port.');
            }
        };
    }
}
