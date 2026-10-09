<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\LimitesTaille;
use PHPUnit\Framework\TestCase;

class LimitesTailleTest extends TestCase
{
    public function test_ecrit_le_plafond_de_chaque_service(): void
    {
        $repertoire = $this->repertoire();

        try {
            (new LimitesTaille)->ecrire($repertoire, ['front', 'db'], 'petite');

            $this->assertSame(<<<'YAML'
services:
  front:
    mem_limit: 512m
    memswap_limit: 512m
    cpus: "0.5"
    deploy:
      resources:
        limits:
          cpus: "0.5"
          memory: 512m
  db:
    mem_limit: 512m
    memswap_limit: 512m
    cpus: "0.5"
    deploy:
      resources:
        limits:
          cpus: "0.5"
          memory: 512m

YAML, file_get_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml'));
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_moyenne_et_grande_ont_leur_formule(): void
    {
        $repertoire = $this->repertoire();

        try {
            $limites = new LimitesTaille;
            $limites->ecrire($repertoire, ['web'], 'moyenne');
            $moyenne = file_get_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml');
            $limites->ecrire($repertoire, ['web'], 'grande');
            $grande = file_get_contents($repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml');

            $this->assertIsString($moyenne);
            $this->assertIsString($grande);
            $this->assertStringContainsString('mem_limit: 1g', $moyenne);
            $this->assertStringContainsString('cpus: "1"', $moyenne);
            $this->assertStringContainsString('mem_limit: 2g', $grande);
            $this->assertStringContainsString('cpus: "2"', $grande);
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_refuse_une_taille_ou_un_service_inconnu(): void
    {
        $repertoire = $this->repertoire();
        $limites = new LimitesTaille;

        try {
            $this->expectException(LancementEchoue::class);
            $limites->ecrire($repertoire, ['front'], 'enorme');
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    public function test_refuse_un_nom_de_service_injecte(): void
    {
        $repertoire = $this->repertoire();

        try {
            (new LimitesTaille)->ecrire($repertoire, ["front:\n  privileged: true"], 'petite');
            $this->fail('Un nom de service dangereux aurait dû être refusé.');
        } catch (LancementEchoue) {
            $this->assertFileDoesNotExist($repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml');
        } finally {
            $this->nettoyer($repertoire);
        }
    }

    private function repertoire(): string
    {
        $repertoire = sys_get_temp_dir().'/soukdev-limites-'.bin2hex(random_bytes(4));
        mkdir($repertoire);

        return $repertoire;
    }

    private function nettoyer(string $repertoire): void
    {
        @unlink($repertoire.DIRECTORY_SEPARATOR.'docker-compose.soukdev.yml');
        @rmdir($repertoire);
    }
}
