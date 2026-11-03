<?php

namespace Tests\Feature\Paiement;

use App\Models\Ecriture;
use App\Models\Facture;
use App\Models\Paiement;
use App\Paiement\GrandLivre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** Grand livre en partie double : chaque opération s'équilibre et rien ne se modifie. */
class GrandLivreTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_encaissement_s_equilibre_et_rien_ne_va_a_souk_dev_sur_l_appli(): void
    {
        [$facture, $paiement] = $this->facturePayee(hebergement: 7000, appli: 25000);

        app(GrandLivre::class)->encaisser($facture, $paiement);

        $operation = Ecriture::query()->get();
        $this->assertCount(3, $operation);
        $this->assertSame(1, $operation->pluck('operation')->unique()->count());
        $this->assertSame(0, (int) $operation->sum('montant'));
        $this->assertSame(25000, app(GrandLivre::class)->soldeDev($facture->abonnement->dev_id));
        $this->assertSame(-7000, (int) $operation->firstWhere('compte', 'revenu_hebergement')->montant);
    }

    public function test_une_appli_gratuite_ne_cree_pas_de_ligne_a_zero(): void
    {
        [$facture, $paiement] = $this->facturePayee(hebergement: 3500, appli: 0);

        app(GrandLivre::class)->encaisser($facture, $paiement);

        $this->assertSame(['saspay', 'revenu_hebergement'], Ecriture::query()->orderBy('id')->pluck('compte')->all());
    }

    public function test_une_ecriture_ne_se_modifie_ni_ne_se_supprime(): void
    {
        [$facture, $paiement] = $this->facturePayee(hebergement: 3500, appli: 15000);
        app(GrandLivre::class)->encaisser($facture, $paiement);
        $ecriture = Ecriture::query()->firstOrFail();

        try {
            $ecriture->update(['montant' => 1]);
            $this->fail('Une écriture modifiée aurait dû être refusée.');
        } catch (LogicException) {
            $this->assertSame(18500, (int) $ecriture->refresh()->montant);
        }

        $this->expectException(LogicException::class);
        $ecriture->delete();
    }

    /**
     * @return array{0: Facture, 1: Paiement}
     */
    private function facturePayee(int $hebergement, int $appli): array
    {
        $facture = Facture::factory()->create([
            'montant_hebergement' => $hebergement, 'montant_appli' => $appli, 'montant_total' => $hebergement + $appli, 'statut' => 'payee',
        ]);
        $paiement = Paiement::query()->create([
            'facture_id' => $facture->id, 'client_id' => $facture->client_id, 'passerelle' => 'simulee',
            'montant' => $facture->montant_total, 'statut' => 'reussi',
        ]);

        return [$facture->load('abonnement'), $paiement];
    }
}
