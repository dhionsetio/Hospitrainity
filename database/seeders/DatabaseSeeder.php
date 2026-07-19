<?php

namespace Database\Seeders;

use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Curriculum is imported from the checksum-locked versioned package through
     * the same validated pipeline used by the explicit Artisan command. The old
     * course.json/VocabularySeeder/MaterialSeeder/ExerciseSeeder path is retained
     * only as migration evidence and is not learner-delivery source data.
     */
    public function run(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);

        $this->call(UserSeeder::class);
    }
}
