<?php

namespace App\Services\Curriculum;

final readonly class CanonicalPackage
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array{path: string, sha256: string, bytes: int}>  $sourceFiles
     * @param  list<array<string, mixed>>  $entities
     * @param  list<array<string, mixed>>  $links
     * @param  array<string, int>  $counts
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public string $root,
        public array $metadata,
        public array $sourceFiles,
        public array $entities,
        public array $links,
        public array $counts,
        public array $evidence,
        public string $treeSha256,
        public int $byteCount,
    ) {}

    /** @return list<array<string, mixed>> */
    public function entitiesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->entities,
            static fn (array $entity): bool => $entity['entity_type'] === $type,
        ));
    }

    /** @return array<string, array<string, mixed>> */
    public function entitiesByCode(): array
    {
        $entities = [];
        foreach ($this->entities as $entity) {
            if ($entity['code'] !== null) {
                $entities[$entity['code']] = $entity;
            }
        }

        return $entities;
    }
}
