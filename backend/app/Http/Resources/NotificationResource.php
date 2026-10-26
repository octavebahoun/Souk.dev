<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'titre' => (string) ($notification->data['titre'] ?? ''),
            'texte' => (string) ($notification->data['texte'] ?? ''),
            'lien' => $notification->data['lien'] ?? null,
            'lue' => $notification->read_at !== null,
            'cree_le' => $notification->created_at?->toIso8601String(),
        ];
    }
}
