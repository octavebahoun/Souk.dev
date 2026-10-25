<?php

namespace Tests\Unit\Moteur;

use App\Moteur\Exceptions\LancementEchoue;
use App\Moteur\VerificateurCompose;
use PHPUnit\Framework\Attributes\DataProvider;
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
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
      - cap_chown
    security_opt:
      - no-new-privileges:true
    pids_limit: 100
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

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function composeDangereux(): array
    {
        return [
            'clé entre guillemets' => ["services:\n  web:\n    image: nginx\n    \"privileged\": true\n", 'privileged'],
            'échappement dans une clé' => ["services:\n  web:\n    image: nginx\n    \"\\x70rivileged\": true\n", 'échappement'],
            'cap_add' => ["services:\n  web:\n    image: nginx\n    cap_add:\n      - SYS_ADMIN\n", 'cap_add'],
            'devices' => ["services:\n  web:\n    image: nginx\n    devices:\n      - /dev/sda:/dev/sda\n", 'devices'],
            'pid host' => ["services:\n  web:\n    image: nginx\n    pid: host\n", 'pid'],
            'container_name' => ["services:\n  web:\n    image: nginx\n    container_name: plateforme\n", 'container_name'],
            'volumes_from' => ["services:\n  web:\n    image: nginx\n    volumes_from:\n      - autre\n", 'volumes_from'],
            'réseau d\'un autre conteneur' => ["services:\n  web:\n    image: nginx\n    network_mode: container:soukdev-db\n", 'network_mode'],
            'build hors du dépôt' => ["services:\n  web:\n    build: /\n", 'hors du dépôt'],
            'build remonte au parent' => ["services:\n  web:\n    build:\n      context: ../..\n", 'hors du dépôt'],
            'dockerfile hors du dépôt' => ["services:\n  web:\n    build:\n      context: .\n      dockerfile: /etc/Dockerfile\n", 'hors du dépôt'],
            'build.network' => ["services:\n  web:\n    build:\n      context: .\n      network: host\n", 'build.network'],
            'env_file du serveur' => ["services:\n  web:\n    image: nginx\n    env_file: /etc/environment\n", 'env_file'],
            'variable dans un volume' => ["services:\n  web:\n    image: nginx\n    volumes:\n      - \${SOURCE}:/data\n", 'variable'],
            'secrets' => ["services:\n  web:\n    image: nginx\nsecrets:\n  cle:\n    file: /etc/shadow\n", 'secrets'],
            'volume externe' => ["services:\n  web:\n    image: nginx\nvolumes:\n  data:\n    external: true\n", 'externe'],
            'volume nommé' => ["services:\n  web:\n    image: nginx\nvolumes:\n  data:\n    name: soukdev_db\n", 'name'],
            'réseau externe' => ["services:\n  web:\n    image: nginx\nnetworks:\n  plateforme:\n    external: true\n", 'externe'],
            'cap_add NET_RAW' => ["services:\n  web:\n    image: nginx\n    cap_add:\n      - NET_RAW\n", 'NET_RAW'],
            'cap_add ALL' => ["services:\n  web:\n    image: nginx\n    cap_add: [ALL]\n", 'cap_add'],
            'cap_add avec préfixe' => ["services:\n  web:\n    image: nginx\n    cap_add:\n      - CAP_SYS_PTRACE\n", 'CAP_SYS_PTRACE'],
            'seccomp désactivé' => ["services:\n  web:\n    image: nginx\n    security_opt:\n      - seccomp:unconfined\n", 'security_opt'],
            'apparmor désactivé' => ["services:\n  web:\n    image: nginx\n    security_opt:\n      - apparmor=unconfined\n", 'security_opt'],
            'no-new-privileges faux' => ["services:\n  web:\n    image: nginx\n    security_opt:\n      - no-new-privileges:false\n", 'security_opt'],
            'pids_limit illimité' => ["services:\n  web:\n    image: nginx\n    pids_limit: -1\n", 'pids_limit'],
            'pids_limit zéro' => ["services:\n  web:\n    image: nginx\n    pids_limit: 0\n", 'pids_limit'],
            'pids_limit énorme' => ["services:\n  web:\n    image: nginx\n    pids_limit: 100000\n", 'pids_limit'],
            'network_mode bridge partagé' => ["services:\n  web:\n    image: nginx\n    network_mode: bridge\n", 'network_mode'],
            'réseau réservé à la racine' => ["services:\n  web:\n    image: nginx\nnetworks:\n  soukdev-copie:\n    driver: bridge\n", 'réservé'],
            'réseau réservé dans un service' => ["services:\n  web:\n    image: nginx\n    networks:\n      - soukdev-copie\n", 'réservé'],
        ];
    }

    #[DataProvider('composeDangereux')]
    public function test_refuse_les_reglages_qui_sortent_de_la_copie(string $yaml, string $message): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage($message);

        $this->verificateur->verifier($yaml);
    }

    public function test_accepte_un_build_et_un_env_file_dans_le_depot(): void
    {
        $this->verificateur->verifier(<<<'YAML'
services:
  web:
    build:
      context: ./front
      dockerfile: Dockerfile
      args:
        NODE_ENV: production
    env_file:
      - .env
    environment:
      DB_PASSWORD: ${DB_PASSWORD}
    x-note: interne
networks:
  interne:
    driver: bridge
YAML);

        $this->expectNotToPerformAssertions();
    }

    public function test_refuse_un_document_json_privileged(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('Le service « web » est privileged.');

        $this->verificateur->verifierDocument([
            'services' => [
                'web' => [
                    'image' => 'nginx',
                    'privileged' => true,
                ],
            ],
        ]);
    }

    public function test_la_config_finale_doit_porter_le_durcissement(): void
    {
        $document = [
            'services' => [
                'web' => ['image' => 'nginx:alpine', 'pids_limit' => 256],
            ],
        ];

        try {
            $this->verificateur->verifierDocument($document, 'petite');
            $this->fail('Une config finale sans durcissement aurait dû être refusée.');
        } catch (LancementEchoue $exception) {
            $this->assertStringContainsString('cap_drop: ALL', $exception->getMessage());
            $this->assertStringContainsString('no-new-privileges', $exception->getMessage());
            $this->assertStringContainsString('réseau de la copie', $exception->getMessage());
        }
    }

    public function test_la_config_finale_durcie_passe(): void
    {
        $this->verificateur->verifierDocument([
            'name' => 'copie',
            'services' => [
                'web' => [
                    'image' => 'nginx:alpine',
                    'pids_limit' => 256,
                    'cap_drop' => ['ALL'],
                    'cap_add' => ['CHOWN', 'SETUID'],
                    'security_opt' => ['no-new-privileges:true'],
                    'networks' => ['soukdev-copie' => null],
                ],
            ],
            'networks' => ['soukdev-copie' => ['driver' => 'bridge']],
        ], 'petite');

        $this->expectNotToPerformAssertions();
    }

    public function test_la_config_finale_refuse_un_plafond_de_processus_trop_haut(): void
    {
        $this->expectException(LancementEchoue::class);
        $this->expectExceptionMessage('pids_limit');

        $this->verificateur->verifierDocument([
            'services' => [
                'web' => [
                    'image' => 'nginx:alpine',
                    'pids_limit' => 1024,
                    'cap_drop' => ['ALL'],
                    'security_opt' => ['no-new-privileges:true'],
                    'networks' => ['soukdev-copie'],
                ],
            ],
            'networks' => ['soukdev-copie' => null],
        ], 'petite');
    }
}
