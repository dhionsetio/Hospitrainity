<?php

namespace App\Console\Commands;

use App\Services\SearchIndexBuilder;
use Illuminate\Console\Command;
use JsonException;

class RebuildSearchIndex extends Command
{
    protected $signature = 'hospitrainity:search-rebuild';

    protected $description = 'Build and atomically activate a new bounded Hospitrainity search-index generation';

    /** @throws JsonException */
    public function handle(SearchIndexBuilder $index): int
    {
        $this->line(json_encode($index->rebuild(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
