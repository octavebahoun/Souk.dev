<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Pagination;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Notifications\TypesNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Centre de notifications du compte connecté et ses préférences par type. Une notification ne
 * se lit que par son destinataire : celle d'un autre compte répond 404.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['filtre' => ['sometimes', 'in:toutes,non_lues']], ['filtre.in' => 'Le filtre est « toutes » ou « non_lues ».']);
        $parPage = Pagination::parPage($request);
        /** @var User $compte */
        $compte = $request->user();

        $notifications = $compte->notifications()
            ->when($request->input('filtre') === 'non_lues', fn ($requete) => $requete->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();

        return NotificationResource::collection($notifications)
            ->additional(['non_lues' => $compte->unreadNotifications()->count()])
            ->response();
    }

    public function lire(Request $request, string $notification): JsonResponse
    {
        /** @var User $compte */
        $compte = $request->user();
        $trouvee = $compte->notifications()->findOrFail($notification);
        $trouvee->markAsRead();

        return response()->json((new NotificationResource($trouvee))->resolve($request));
    }

    public function toutLire(Request $request): Response
    {
        /** @var User $compte */
        $compte = $request->user();
        $compte->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json($this->reglages($request->user()));
    }

    public function modifierPreferences(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'distinct', Rule::in(array_keys(TypesNotification::TYPES))],
            'preferences.*.application' => ['required', 'boolean'],
            'preferences.*.email' => ['required', 'boolean'],
        ], [
            'preferences.*.type.in' => 'Ce type de notification n’existe pas.',
            'preferences.*.type.distinct' => 'Chaque type ne se règle qu’une fois.',
        ]);

        // Lu et réécrit sous verrou : deux réglages envoyés coup sur coup ne s'écrasent pas.
        $compte = DB::transaction(function () use ($request, $donnees): User {
            $compte = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $preferences = $compte->preferences_notifications ?? [];
            foreach ($donnees['preferences'] as $preference) {
                $preferences[$preference['type']] = [
                    'application' => (bool) $preference['application'],
                    'email' => (bool) $preference['email'],
                ];
            }
            $compte->forceFill(['preferences_notifications' => $preferences])->save();

            return $compte;
        });

        return response()->json($this->reglages($compte));
    }

    /**
     * @return array{types: list<array<string, mixed>>}
     */
    private function reglages(User $compte): array
    {
        $types = [];
        foreach (TypesNotification::TYPES as $type => $definition) {
            $types[] = [
                'type' => $type,
                'libelle' => $definition['libelle'],
                'description' => $definition['description'],
                'application' => $compte->recoitNotification($type, 'application'),
                'email' => $compte->recoitNotification($type, 'email'),
                'email_obligatoire' => $definition['email_obligatoire'],
            ];
        }

        return ['types' => $types];
    }
}
