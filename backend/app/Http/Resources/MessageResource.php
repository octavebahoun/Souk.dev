<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Message $message */
        $message = $this->resource;
        $message->loadMissing('auteur');

        return [
            'id' => $message->id,
            'texte' => $message->texte,
            'auteur' => DevResource::champs($message->auteur),
            'cree_le' => $message->created_at?->toIso8601String(),
        ];
    }
}
