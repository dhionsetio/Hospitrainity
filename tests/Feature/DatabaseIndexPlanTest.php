<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIndexPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_uses_the_measured_phase_five_indexes(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite-specific EXPLAIN QUERY PLAN assertions.');
        }

        $this->assertPlanUses(
            'SELECT * FROM modules WHERE is_published = ? ORDER BY `order`, id',
            [1],
            'modules_published_order_idx',
        );
        $this->assertPlanUses(
            'SELECT * FROM lessons WHERE module_id = ? ORDER BY `order`, id',
            [1],
            'lessons_module_order_idx',
        );
        $this->assertPlanUses(
            'SELECT * FROM users WHERE role = ? AND instansi = ? ORDER BY id LIMIT 20',
            ['user', 'Measured Hotel'],
            'users_role_instansi_id_idx',
        );
    }

    private function assertPlanUses(string $sql, array $bindings, string $index): void
    {
        $details = collect(DB::select("EXPLAIN QUERY PLAN {$sql}", $bindings))
            ->pluck('detail')
            ->implode("\n");

        $this->assertStringContainsString($index, $details);
        $this->assertStringNotContainsString('USE TEMP B-TREE', $details);
    }
}
