<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LanceurCompose;
use PHPUnit\Framework\TestCase;

class LanceurComposeTest extends TestCase
{
    public function test_trouve_le_service_web(): void
    {
        $lanceur = new LanceurCompose;
        $repertoire = $this->depot("services:\n  front:\n    image: nginx:alpine\n  db:\n    image: postgres\n");

        try {
            $chemin = $lanceur->verifier($repertoire, 'front');
            $this->assertSame($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', $chemin);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_refuse_un_service_absent(): void
    {
        $lanceur = new LanceurCompose;
        $repertoire = $this->depot("services:\n  api:\n    image: nginx:alpine\n");

        try {
            $this->expectException(LancementEchoue::class);
            $this->expectExceptionMessage('front');
            $lanceur->verifier($repertoire, 'front');
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_refuse_un_port_publie(): void
    {
        $lanceur = new LanceurCompose;
        $repertoire = $this->depot("services:\n  front:\n    image: nginx\n    ports:\n      - \"80:80\"\n");

        try {
            $this->expectException(LancementEchoue::class);
            $this->expectExceptionMessage('publie des ports');
            $lanceur->verifier($repertoire, 'front');
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_lit_un_compose_indenté_à_4_espaces(): void
    {
        $yaml = <<<'YAML'
version: "3.8"
services:
    web:
        image: nginx:alpine
YAML;

        $this->assertSame(['web'], (new LanceurCompose)->nomsServices($yaml));
    }

    private function depot(string $compose): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-compose-'.bin2hex(random_bytes(4));
        mkdir($repertoire);
        file_put_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml', $compose);

        return $repertoire;
    }

    private function nettoyer(string $repertoire): void
    {
        @unlink($repertoire.DIRECTORY_SEPARATOR.'docker-compose.yml');
        @rmdir($repertoire);
    }
}
