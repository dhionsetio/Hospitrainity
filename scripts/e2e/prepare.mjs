import { closeSync, mkdirSync, openSync, rmSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

import { artifactRoot, credentialsPath, databasePath, e2eEnv, phpBinary, repoRoot } from './environment.mjs';

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

const randomSecret = () => randomBytes(32).toString('base64url');
const accounts = {
    learner: { email: 'user@example.com', password: randomSecret() },
    superadmin: { email: 'superadmin@example.com', password: randomSecret() },
    supervisor: { email: 'supervisor@example.com', password: randomSecret() },
};
writeFileSync(credentialsPath, JSON.stringify(accounts), { encoding: 'utf8', mode: 0o600 });

const seedEnv = {
    ...e2eEnv,
    HOSPITRAINITY_DEMO_SEED: 'true',
    HOSPITRAINITY_DEMO_LEARNER_PASSWORD: accounts.learner.password,
    HOSPITRAINITY_DEMO_SUPERADMIN_PASSWORD: accounts.superadmin.password,
    HOSPITRAINITY_DEMO_SUPERVISOR_PASSWORD: accounts.supervisor.password,
};

for (const args of [
    ['artisan', 'migrate', '--force', '--no-interaction'],
    ['artisan', 'db:seed', '--force', '--no-interaction'],
]) {
    const result = spawnSync(phpBinary, args, {
        cwd: repoRoot,
        env: seedEnv,
        stdio: 'inherit',
    });

    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status ?? 1);
}
