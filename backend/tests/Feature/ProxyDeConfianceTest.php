<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyDeConfianceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-adresse', fn () => ['ip' => request()->ip(), 'securise' => request()->isSecure()]);
    }

    public function test_derriere_traefik_chaque_visiteur_garde_son_adresse_et_le_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeaders(['X-Forwarded-For' => '41.202.10.5', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/test-adresse')
            ->assertExactJson(['ip' => '41.202.10.5', 'securise' => true]);
    }

    public function test_un_client_direct_ne_peut_pas_se_donner_une_autre_adresse(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '41.202.10.5'])
            ->withHeaders(['X-Forwarded-For' => '1.2.3.4', 'X-Forwarded-Proto' => 'https'])
            ->getJson('/test-adresse')
            ->assertExactJson(['ip' => '41.202.10.5', 'securise' => false]);
    }
}
