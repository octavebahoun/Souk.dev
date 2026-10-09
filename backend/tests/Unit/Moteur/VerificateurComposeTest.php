<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\VerificateurCompose;
use PHPUnit\Framework\TestCase;

class VerificateurComposeTest extends TestCase
{
    private VerificateurCompose $verificateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificateur = new VerificateurCompose;
    }

    public function test_accepte_un_compose_ferme(): void
    {
        $this->verificateur->verifier(<<<'YAML'
services:
  front:
    image: nginx:alpine
    privileged: false
    network_mode: bridge
    expose:
      - "3000"
    ports: []
    volumes:
      - dbdata:/var/lib/data
  db:
    image: postgres:16
    volumes:
      - dbdata:/var/lib/postgresql/data
volumes:
  dbdata:
YAML);

        $this->expectNotToPerformAssertions();
    }

    public function test_refuse_privileged(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Le service « web » est privileged.');

        $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    privileged: true
YAML);
    }

    public function test_refuse_network_mode_host(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('network_mode host');

        $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    network_mode: "host"
YAML);
    }

    public function test_refuse_un_port_publie_en_syntaxe_courte_et_longue(): void
    {
        try {
            $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    ports:
      - "8080:80"
      - target: 80
        published: 8080
YAML);
            $this->fail('Un port publié aurait dû être refusé.');
        } catch (LancementEchoue $exception) {
            $this->assertStringContainsString('publie des ports', $exception->getMessage());
        }
    }

    public function test_refuse_un_port_en_syntaxe_flux(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('publie des ports');

        $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    ports: ["8080:80"]
YAML);
    }

    public function test_refuse_les_volumes_de_l_hote(): void
    {
        try {
            $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./data:/data
      - type: bind
        source: /etc
        target: /host
volumes:
  donnees:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /opt/data
YAML);
            $this->fail('Un volume de l\'hôte aurait dû être refusé.');
        } catch (LancementEchoue $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('/var/run/docker.sock', $message);
            $this->assertStringContainsString('./data', $message);
            $this->assertStringContainsString('/etc', $message);
            $this->assertStringContainsString('/opt/data', $message);
        }
    }

    public function test_rassemble_les_quatre_refus(): void
    {
        try {
            $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: nginx
    privileged: yes
    network_mode: host
    ports:
      - "80:80"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
YAML);
            $this->fail('Le compose dangereux aurait dû être refusé.');
        } catch (LancementEchoue $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('privileged', $message);
            $this->assertStringContainsString('network_mode host', $message);
            $this->assertStringContainsString('publie des ports', $message);
            $this->assertStringContainsString('/var/run/docker.sock', $message);
        }
    }

    public function test_refuse_une_ancre_yaml(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('ancre YAML');

        $this->verificateur->verifier(<<<'YAML'
services:
  web:
    image: &img nginx
    privileged: *img
YAML);
    }
}
