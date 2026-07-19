<?php

namespace App\Console\Commands;

use App\Models\CurriculumPackage;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalJson;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumArtifactStore;
use App\Services\Curriculum\StandaloneGenerator;
use Illuminate\Console\Command;
use Throwable;

class ManageCanonicalCurriculum extends Command
{
    protected $signature = 'hospitrainity:curriculum
        {action=dry-run : dry-run, import, verify, generate, or rollback}
        {--package= : Optional canonical package directory (defaults to the recorded active package, then configuration)}
        {--evidence= : Optional matching evidence JSON path}
        {--rollback= : Recorded rollback artifact path (required for rollback)}
        {--report= : Optional explicit JSON report path}';

    protected $description = 'Validate, diff, import, verify, generate, or roll back the canonical Hospitrainity curriculum';

    public function handle(
        CanonicalPackageReader $reader,
        CanonicalCurriculumImporter $importer,
        StandaloneGenerator $standalone,
        CurriculumArtifactStore $artifacts,
    ): int {
        $action = strtolower((string) $this->argument('action'));
        $reportPath = $this->option('report') ?: null;

        if (! in_array($action, ['dry-run', 'import', 'verify', 'generate', 'rollback'], true)) {
            $this->error("Unsupported action: {$action}");

            return self::INVALID;
        }

        try {
            if ($action === 'rollback') {
                $rollback = $this->option('rollback');
                if (! is_string($rollback) || trim($rollback) === '') {
                    $this->error('The rollback action requires --rollback=<recorded artifact path>.');

                    return self::INVALID;
                }
                $report = $importer->rollback($rollback, $reportPath);
            } else {
                [$packagePath, $evidencePath] = $this->sourcePaths();
                $source = $reader->read($packagePath, $evidencePath);
                $report = match ($action) {
                    'dry-run' => $importer->plan($source),
                    'import' => $importer->import($source, $reportPath),
                    'verify' => $importer->verify($source),
                    'generate' => [
                        'mode' => 'generate',
                        'status' => 'generated',
                        'standalone' => $standalone->write($source),
                        'source_tree_sha256' => $source->treeSha256,
                    ],
                };

                if ($reportPath !== null && in_array($action, ['dry-run', 'verify', 'generate'], true)) {
                    $reportArtifact = $artifacts->writeJson($reportPath, $report);
                    $report['report'] = $reportArtifact;
                }
            }

            $this->line(CanonicalJson::encode($report, pretty: true));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array{string|null, string|null} */
    private function sourcePaths(): array
    {
        $packageOption = $this->option('package');
        $evidenceOption = $this->option('evidence');
        if (is_string($packageOption) && trim($packageOption) !== '') {
            return [$packageOption, is_string($evidenceOption) && trim($evidenceOption) !== '' ? $evidenceOption : config('curriculum.evidence_path')];
        }

        $active = CurriculumPackage::active();
        $activeEvidence = $active?->projection_meta['evidence_path'] ?? null;
        if ($active !== null && is_dir($active->source_path) && is_string($activeEvidence) && is_file($activeEvidence)) {
            return [$active->source_path, $activeEvidence];
        }

        return [config('curriculum.package_path'), config('curriculum.evidence_path')];
    }
}
