<?php

namespace App\Notifications;

use App\Models\Rapprochement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EcartRapprochement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Rapprochement $rapprochement, public int $ecarts) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jour = $this->rapprochement->jour->locale('fr')->isoFormat('D MMMM YYYY');

        return Courriel::nouveau("[Admin] Rapprochement SasPay du {$jour} : {$this->ecarts} écart(s)")
            ->line("Le rapprochement du {$jour} a trouvé {$this->ecarts} écart(s) qui demandent une vérification.")
            ->line("Transactions SasPay : {$this->rapprochement->transactions_saspay}, paiements enregistrés : {$this->rapprochement->paiements_locaux}.")
            ->action('Voir les rapprochements', Courriel::front('/admin/finances'));
    }
}
