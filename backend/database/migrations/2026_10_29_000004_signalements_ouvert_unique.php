<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Un seul signalement ouvert par membre et par cible, garanti par la base :
        // deux envois concurrents (double-clic, deux onglets) ne créent pas deux
        // ouverts. Index partiel, compris par PostgreSQL comme par SQLite.
        DB::statement("CREATE UNIQUE INDEX signalements_ouvert_unique ON signalements (user_id, cible, cible_id) WHERE statut = 'ouvert'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS signalements_ouvert_unique');
    }
};
