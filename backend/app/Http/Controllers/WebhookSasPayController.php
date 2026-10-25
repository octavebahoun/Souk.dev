<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\TraiterWebhookSasPay;
use App\Models\WebhookSasPay;
use App\Paiement\SignatureWebhook;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/saspay : signature et âge vérifiés sur le corps brut, puis traitement en
 * arrière-plan (revérification par l'API). Répond vite (SasPay coupe à 15 secondes) et
 * idempotent : un même corps rejoué n'est traité qu'une fois.
 */
final class WebhookSasPayController extends Controller
{
    public function recevoir(Request $request): JsonResponse
    {
        $corps = $request->getContent();
        $signature = new SignatureWebhook(
            (string) config('services.saspay.secret_webhook'),
            (int) config('services.saspay.tolerance_webhook', 300),
        );

        if (! $signature->verifier($corps, $request->header('X-Webhook-Signature'), $request->header('X-Webhook-Timestamp'))) {
            Log::warning('saspay: webhook refusé (signature ou horodatage)', ['evenement' => $request->header('X-Webhook-Event')]);

            return response()->json(['message' => 'Signature invalide ou horodatage hors tolérance.'], 401);
        }

        $donnees = json_decode($corps, true);

        if (! is_array($donnees) || ! is_string($donnees['event'] ?? null)) {
            return response()->json(['message' => 'Corps illisible.'], 400);
        }

        $transaction = is_array($donnees['data'] ?? null) && is_string($donnees['data']['id'] ?? null) ? $donnees['data']['id'] : null;

        try {
            $webhook = WebhookSasPay::query()->create([
                'empreinte' => hash('sha256', $corps),
                'evenement' => mb_substr($donnees['event'], 0, 100),
                'transaction_id' => $transaction !== null ? mb_substr($transaction, 0, 64) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Déjà reçu.']);
        }

        if (str_starts_with($donnees['event'], 'transaction.')) {
            TraiterWebhookSasPay::dispatch($webhook->id);
        }

        return response()->json(['message' => 'Reçu.']);
    }
}
