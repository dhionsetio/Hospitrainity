<?php

namespace Tests\Feature\Concerns;

use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Services\Curriculum\CanonicalPackageReader;

trait InstallsCanonicalCurriculumFixture
{
    protected function installCanonicalCurriculumFixture(): CurriculumPackage
    {
        $source = app(CanonicalPackageReader::class)->read();
        $package = CurriculumPackage::query()->create([
            'package_name' => $source->metadata['package'],
            'content_version' => $source->metadata['content_version'],
            'schema_version' => $source->metadata['schema_version'],
            'namespace_uuid' => $source->metadata['namespace'],
            'lifecycle_status' => 'published',
            'source_path' => $source->evidence['package']['path'],
            'source_tree_sha256' => $source->treeSha256,
            'source_file_count' => count($source->sourceFiles),
            'source_byte_count' => $source->byteCount,
            'counts' => $source->counts,
            'projection_meta' => $source->evidence['projection_meta'] ?? [],
            'laravel_projection_sha256' => (new \ReflectionMethod(\App\Services\Curriculum\CanonicalCurriculumImporter::class, 'desiredProjectionSha256'))->invoke(app(\App\Services\Curriculum\CanonicalCurriculumImporter::class), $source),
            'standalone_sha256' => $source->evidence['standalone']['sha256'],
            'is_active' => true,
            'imported_at' => now(),
        ]);

        foreach ($source->entities as $entity) {
            CurriculumEntity::query()->create([
                'curriculum_package_id' => $package->getKey(),
                'entity_uuid' => $entity['entity_uuid'],
                'code' => $entity['code'],
                'entity_type' => $entity['entity_type'],
                'parent_code' => $entity['parent_code'],
                'position' => $entity['position'],
                'lifecycle_status' => $entity['lifecycle_status'],
                'content_version' => $entity['content_version'],
                'source_path' => $entity['source_path'],
                'source_sha256' => $entity['source_sha256'],
                'payload' => $entity['payload'],
            ]);
        }

        return $package;
    }
}
