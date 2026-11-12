<?php

namespace Tests\Feature;

use App\Models\Appli;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_plan_du_site_liste_les_pages_publiques_du_front(): void
    {
        $appli = Appli::factory()->create();
        $discussion = Discussion::factory()->create();
        User::factory()->create(['username' => 'awa-dev']);
        $suspendu = User::factory()->create(['username' => 'compte-suspendu']);
        $suspendu->forceFill(['suspendu_le' => now()])->save();

        $reponse = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', (string) $reponse->headers->get('Content-Type'));
        $reponse->assertSee('<loc>http://localhost:5173/store</loc>', false);
        $reponse->assertSee("<loc>http://localhost:5173/store/{$appli->id}</loc>", false);
        $reponse->assertSee("<loc>http://localhost:5173/echange/{$discussion->id}</loc>", false);
        $reponse->assertSee('<loc>http://localhost:5173/devs/awa-dev</loc>', false);
        $reponse->assertDontSee('compte-suspendu', false);
        $this->assertNotFalse(simplexml_load_string($reponse->getContent()));
    }
}
