<?php

namespace App\Jobs;

use App\Models\CurriculumImport;
use App\Services\Curriculum\CurriculumImportCompiler;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompileCurriculumImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public CurriculumImport $import)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->import->public_id;
    }

    public function handle(CurriculumImportCompiler $compiler): void
    {
        $compiler->compile($this->import);
    }
}
