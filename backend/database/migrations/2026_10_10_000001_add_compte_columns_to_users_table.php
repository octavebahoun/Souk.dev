<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('username')->nullable()->unique();
            $table->string('github_id')->nullable()->unique();
            $table->string('avatar_url')->nullable();
            $table->string('profil')->default('dev');
            $table->boolean('github_lie')->default(false);
            $table->boolean('est_admin')->default(false);
            $table->string('pays', 2)->nullable();
            $table->string('bio', 500)->nullable();
            $table->json('competences')->nullable();
            $table->json('specialites')->nullable();
            $table->boolean('disponible')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'github_id',
                'avatar_url',
                'profil',
                'github_lie',
                'est_admin',
                'pays',
                'bio',
                'competences',
                'specialites',
                'disponible',
            ]);
        });
    }
};
