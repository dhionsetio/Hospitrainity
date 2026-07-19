import { closeSync, mkdirSync, openSync, rmSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

import { artifactRoot, databasePath, e2eEnv, phpBinary, repoRoot } from './environment.mjs';

const permittedRoot = path.join(repoRoot, 'storage', 'framework', 'testing');
const relativeArtifactPath = path.relative(permittedRoot, artifactRoot);

if (!relativeArtifactPath || relativeArtifactPath.startsWith('..') || path.isAbsolute(relativeArtifactPath)) {
    throw new Error(`Refusing to reset E2E artifacts outside ${permittedRoot}`);
}

rmSync(artifactRoot, { recursive: true, force: true });
mkdirSync(path.join(artifactRoot, 'cache'), { recursive: true });
mkdirSync(path.join(artifactRoot, 'sessions'), { recursive: true });
mkdirSync(path.join(artifactRoot, 'views'), { recursive: true });
closeSync(openSync(databasePath, 'w'));

for (const args of [
    ['artisan', 'migrate', '--force', '--no-interaction'],
    ['artisan', 'db:seed', '--force', '--no-interaction'],
]) {
    const result = spawnSync(phpBinary, args, {
        cwd: repoRoot,
        env: e2eEnv,
        stdio: 'inherit',
    });

    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}
