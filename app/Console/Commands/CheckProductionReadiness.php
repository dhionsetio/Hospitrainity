<?php

namespace App\Console\Commands;

use App\Services\ProductionReadinessChecker;
use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'hospitrainity:deployment-check {--json : Emit machine-readable JSON}';

    protected $description = 'Fail unless the current release satisfies Hospitrainity production safeguards';

    public function handle(ProductionReadinessChecker $checker): int
    {
        $checks = $checker->inspect();
        $failed = array_filter($checks, static fn (array $check): bool => ! $check['passed']);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ready' => $failed === [],
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $prefix = $check['passed'] ? 'PASS' : 'FAIL';
                $this->line("[{$prefix}] {$check['name']}: {$check['message']}");
            }
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
