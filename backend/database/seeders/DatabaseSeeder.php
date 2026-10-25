<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Keep local databases empty until real accounts publish real content.
     */
    public function run(): void
    {
        // No fabricated users, applications, missions, or discussions are seeded.
    }
}
