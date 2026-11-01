<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Les erreurs de validation arrivent en français, avec le nom lisible du champ : Laravel ne
 * fournit que l'anglais (« The depot url field is required. »), lang/fr/validation.php le remplace.
 */
class ValidationFrancaiseTest extends TestCase
{
    public function test_une_regle_par_defaut_repond_en_francais_avec_le_nom_lisible_du_champ(): void
    {
        $erreurs = Validator::make(['prix' => -5], [
            'depot_url' => ['required'],
            'prix' => ['integer', 'min:0'],
        ])->errors();

        $this->assertSame('fr', app()->getLocale());
        $this->assertSame('Le champ adresse du dépôt est obligatoire.', $erreurs->first('depot_url'));
        $this->assertSame('Le champ prix doit valoir au moins 0.', $erreurs->first('prix'));
    }
}
