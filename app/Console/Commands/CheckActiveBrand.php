<?php

namespace App\Console\Commands;

use App\Services\Curriculum\CanonicalJson;
use App\Services\Curriculum\CurriculumArtifactStore;
use App\Services\Quality\ActiveBrandGuard;
use Illuminate\Console\Command;

final class CheckActiveBrand extends Command
{
    protected $signature = 'hospitrainity:brand-guard {--report= : Optional JSON report path}';

    protected $description = 'Fail if the retired predecessor brand appears in active product artifacts';

    public function handle(ActiveBrandGuard $guard, CurriculumArtifactStore $artifacts): int
    {
        $report = $guard->inspect();
        $reportPath = $this->option('report');
        if (is_string($reportPath) && trim($reportPath) !== '') {
            $report['report'] = $artifacts->writeJson($reportPath, $report);
        }
        $this->line(CanonicalJson::encode($report, pretty: true));

        return $report['status'] === 'verified' ? self::SUCCESS : self::FAILURE;
    }
}
