<?php

namespace Tests\Feature;

use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\StandaloneGenerator;
use Tests\TestCase;

class CanonicalPackageReaderTest extends TestCase
{
    public function test_source_fidelity_package_passes_checksum_count_and_reference_validation(): void
    {
        $package = app(CanonicalPackageReader::class)->read();

        $this->assertFileExists(config('curriculum.evidence_path'));
        $this->assertSame($package->evidence['package']['tree_sha256'], $package->treeSha256);
        $this->assertSame($package->evidence['package']['file_count'], count($package->sourceFiles));
        $this->assertSame($package->evidence['package']['byte_count'], $package->byteCount);
        $this->assertSame([
            'chapters' => 7,
            'sections' => 85,
            'activities' => 25,
            'prompts' => 124,
            'answer_models' => 96,
            'feedback_models' => 96,
            'rubrics' => 6,
            'outcomes' => 21,
            'competencies' => 7,
            'cefr_references' => 23,
            'source_provenance' => 7,
            'migration_edges' => 8,
        ], $package->counts);
        $this->assertCount(514, $package->entities);
        $this->assertNotEmpty($package->links);
        $this->assertSame('CF-7', $package->evidence['projection_meta']['checkpoint']);
        $this->assertSame('development', $package->evidence['projection_meta']['status']);
        $this->assertSame(['id' => 'CF-7', 'status' => 'open'], $package->evidence['workflow_gate']);
        $this->assertSame('open', $package->evidence['human_gates']['status']);
        $this->assertSame('retain_draft_until_every_human_and_technical_gate_passes', $package->evidence['release_decision']['decision']);
        $this->assertStringContainsString(
            'chapter lifecycle status does not constitute release approval',
            $package->evidence['projection_meta']['disclaimer'],
        );
        $this->assertStringNotContainsString(
            'closed CP-02 approval gate',
            $package->evidence['projection_meta']['disclaimer'],
        );
        $this->assertStringContainsString(
            'Hospitrainity.docx via the deterministic scripts/curriculum source compiler',
            $package->evidence['projection_meta']['generated_from'],
        );
        $this->assertStringNotContainsString(
            'phase-09/curriculum.sql',
            $package->evidence['projection_meta']['generated_from'],
        );

        $sections = array_filter($package->entities, static fn (array $entity): bool => $entity['entity_type'] === 'lesson-section');
        $this->assertCount(85, $sections);
        $this->assertSame(774, array_sum(array_map(static fn (array $section): int => count($section['payload']['blocks']), $sections)));
    }

    public function test_standalone_projection_is_deterministic_and_matches_the_recorded_artifact(): void
    {
        $package = app(CanonicalPackageReader::class)->read();
        $generator = app(StandaloneGenerator::class);
        $rendered = $generator->render($package);

        $this->assertSame($package->evidence['standalone']['sha256'], hash('sha256', $rendered));
        $this->assertSame(file_get_contents(config('curriculum.standalone_output')), $rendered);
        $this->assertStringContainsString('CF-7 release pending', $rendered);
        $this->assertStringContainsString('development verification only', $rendered);
        $this->assertStringNotContainsString('closed CP-02 approval gate', $rendered);
        $this->assertStringNotContainsString('phase-09/curriculum.sql', $rendered);
        $this->assertStringNotContainsString('24 activities / 102 prompts', $rendered);
    }
}
