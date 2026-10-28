<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\BackendIntegre\Jeton;
use App\Models\CodeConnexion;
use App\Models\Deploiement;
use App\Models\UtilisateurFinal;
use App\Notifications\CodeConnexionCopie;
use App\Sms\FournisseurSms;
use App\Sms\SmsNonEnvoye;
use App\Sms\SmsSimule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Connexion des utilisateurs d'une appli à son backend intégré (comme Supabase Auth) : un code
 * à 6 chiffres par SMS ou par e-mail, échangé contre un jeton signé avec le secret de l'API de la
 * copie. Appelée par l'appli depuis le navigateur, sans cookie ni session Souk.dev.
 */
final class ConnexionUtilisateurFinalController extends Controller
{
    private const MINUTES_CODE = 10;

    private const ESSAIS = 5;

    public function code(Request $request, string $cle, FournisseurSms $sms): JsonResponse
    {
        $copie = $this->copie($cle);
        [$canal, $destinataire] = $this->destinataire($request);
        $empreinte = $this->empreinte($canal, $destinataire);

        // Trois codes par quart d'heure pour un même destinataire, vingt par heure pour une même adresse IP.
        foreach (['code:'.$copie->id.':'.$empreinte => [3, 900], 'code-ip:'.$request->ip() => [20, 3600]] as $limite => [$nombre, $duree]) {
            if (RateLimiter::tooManyAttempts($limite, $nombre)) {
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($limite), 'Trop de codes demandés : réessayez dans quelques minutes.');
            }
            RateLimiter::hit($limite, $duree);
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($copie, $empreinte, $code): void {
            CodeConnexion::query()->where('deploiement_id', $copie->id)->where('empreinte', $empreinte)->delete();
            CodeConnexion::query()->create([
                'deploiement_id' => $copie->id,
                'empreinte' => $empreinte,
                'code' => Hash::make($code),
                'expire_le' => now()->addMinutes(self::MINUTES_CODE),
            ]);
        });

        $nom = (string) ($copie->appli?->nom ?? 'votre appli');
        if ($canal === 'sms') {
            try {
                $sms->envoyer($destinataire, "{$code} : votre code de connexion à {$nom}. Il expire dans ".self::MINUTES_CODE.' minutes.');
            } catch (SmsNonEnvoye) {
                throw new ServiceUnavailableHttpException(60, 'Le SMS n’a pas pu partir. Réessayez dans un instant, ou connectez-vous par e-mail.');
            }
        } else {
            try {
                Notification::route('mail', $destinataire)->notify(new CodeConnexionCopie($code, $nom, self::MINUTES_CODE));
            } catch (Throwable $erreur) {
                report($erreur);

                throw new ServiceUnavailableHttpException(60, 'L’e-mail n’a pas pu partir. Réessayez dans un instant, ou connectez-vous par SMS.');
            }
        }

        return response()->json(['expire_dans' => self::MINUTES_CODE * 60], 202);
    }

    public function session(Request $request, string $cle): JsonResponse
    {
        $copie = $this->copie($cle);
        [$canal, $destinataire] = $this->destinataire($request);
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']], ['code.regex' => 'Le code a 6 chiffres.', 'code.required' => 'Saisissez le code reçu.']);
        $empreinte = $this->empreinte($canal, $destinataire);

        // Le verdict sort de la transaction avant toute exception : un essai faux reste compté
        // (une exception dans la transaction l'annulerait, et les essais seraient illimités).
        $utilisateur = DB::transaction(function () use ($copie, $empreinte, $canal, $destinataire, $request): UtilisateurFinal|string {
            $code = CodeConnexion::query()->where('deploiement_id', $copie->id)->where('empreinte', $empreinte)->lockForUpdate()->latest('id')->first();
            if ($code === null || $code->expire_le->isPast()) {
                return 'expire';
            }
            if ($code->essais >= self::ESSAIS) {
                return 'bloque';
            }
            if (! Hash::check((string) $request->input('code'), $code->code)) {
                $code->increment('essais');

                return 'faux';
            }
            $code->delete();

            $utilisateur = UtilisateurFinal::query()->firstOrCreate(
                ['deploiement_id' => $copie->id, 'empreinte' => $empreinte],
                ['canal' => $canal, 'destinataire' => $destinataire],
            );
            $utilisateur->forceFill(['derniere_connexion_le' => now()])->save();

            return $utilisateur;
        });

        if (is_string($utilisateur)) {
            match ($utilisateur) {
                'expire' => throw ValidationException::withMessages(['code' => 'Ce code a expiré : demandez-en un nouveau.']),
                'bloque' => throw new TooManyRequestsHttpException(60, 'Trop d’essais avec ce code : demandez-en un nouveau.'),
                default => throw ValidationException::withMessages(['code' => 'Ce code est incorrect.']),
            };
        }

        $expire = now()->addDay();
        $jeton = Jeton::signer([
            'role' => 'soukdev_utilisateur',
            'sub' => $utilisateur->id,
            'canal' => $canal,
            'iat' => now()->getTimestamp(),
            'exp' => $expire->getTimestamp(),
        ], (string) $copie->backend_secret);

        return response()->json([
            'jeton' => $jeton,
            'expire_le' => $expire->toIso8601String(),
            'utilisateur' => [
                'id' => $utilisateur->id,
                'canal' => $canal,
                'destinataire' => $canal === 'sms' ? SmsSimule::masquer($destinataire) : self::masquerEmail($destinataire),
            ],
        ]);
    }

    /** La copie en ligne dont le backend intégré a un secret ; sinon, rien à connecter. */
    private function copie(string $cle): Deploiement
    {
        $copie = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cle) === 1
            ? Deploiement::query()->with('appli')->where('cle_publique', strtolower($cle))->first()
            : null;

        if ($copie === null || $copie->etat !== 'en_ligne' || (string) $copie->backend_secret === '') {
            throw new NotFoundHttpException('Cette appli n’a pas de connexion par code.');
        }

        return $copie;
    }

    /**
     * @return array{string, string}
     */
    private function destinataire(Request $request): array
    {
        $request->validate([
            'canal' => ['required', 'in:sms,email'],
            'destinataire' => ['required', 'string', 'max:254'],
        ], ['canal.in' => 'Le canal est « sms » ou « email ».']);

        $canal = (string) $request->input('canal');
        $destinataire = trim((string) $request->input('destinataire'));
        if ($canal === 'sms') {
            $destinataire = (string) preg_replace('/[\s.-]/', '', $destinataire);
            if (preg_match('/^\+[1-9]\d{7,14}$/', $destinataire) !== 1) {
                throw ValidationException::withMessages(['destinataire' => 'Indiquez le numéro au format international, par exemple +229 01 97 00 00 00.']);
            }
        } elseif (filter_var($destinataire, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['destinataire' => 'Indiquez une adresse e-mail valide.']);
        }

        return [$canal, $canal === 'email' ? mb_strtolower($destinataire) : $destinataire];
    }

    private function empreinte(string $canal, string $destinataire): string
    {
        return hash_hmac('sha256', $canal.':'.$destinataire, (string) config('app.key'));
    }

    private static function masquerEmail(string $adresse): string
    {
        [$nom, $domaine] = explode('@', $adresse, 2) + [1 => ''];

        return mb_substr($nom, 0, 1).'•••@'.$domaine;
    }
}
