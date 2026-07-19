import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const repoRoot = path.resolve(fileURLToPath(new URL('../../', import.meta.url)));
export const artifactRoot = path.join(repoRoot, 'storage', 'framework', 'testing', 'e2e');
export const databasePath = path.join(artifactRoot, 'database.sqlite');
export const baseURL = 'http://127.0.0.1:8010';
export const phpBinary = process.env.PHP_BINARY
    || (process.platform === 'win32' && existsSync('C:\\php\\php.exe') ? 'C:\\php\\php.exe' : 'php');

export const e2eEnv = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: baseURL,
    BCRYPT_ROUNDS: '4',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    DB_FOREIGN_KEYS: 'true',
    LOG_CHANNEL: 'stderr',
    LOG_LEVEL: 'debug',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'file',
    SESSION_FILES: path.join(artifactRoot, 'sessions'),
    VIEW_COMPILED_PATH: path.join(artifactRoot, 'views'),
    VITE_HOT_FILE: path.join(artifactRoot, 'hot'),
    // Laravel treats cache-path environment values as base-path-relative unless
    // they start with / or \\. Relative paths also avoid Windows drive-prefix
    // duplication while keeping every artifact inside the disposable root.
    APP_CONFIG_CACHE: path.join('storage', 'framework', 'testing', 'e2e', 'cache', 'config.php'),
    APP_EVENTS_CACHE: path.join('storage', 'framework', 'testing', 'e2e', 'cache', 'events.php'),
    APP_PACKAGES_CACHE: path.join('storage', 'framework', 'testing', 'e2e', 'cache', 'packages.php'),
    APP_ROUTES_CACHE: path.join('storage', 'framework', 'testing', 'e2e', 'cache', 'routes.php'),
    APP_SERVICES_CACHE: path.join('storage', 'framework', 'testing', 'e2e', 'cache', 'services.php'),
    CURRICULUM_REPORT_DIRECTORY: path.join(artifactRoot, 'curriculum', 'reports'),
    CURRICULUM_ROLLBACK_DIRECTORY: path.join(artifactRoot, 'curriculum', 'rollbacks'),
    CURRICULUM_STANDALONE_OUTPUT: path.join(artifactRoot, 'curriculum', 'Hospitrainity-Standalone.html'),
};

export const testAccounts = Object.freeze({
    learner: Object.freeze({ email: 'user@example.com', password: 'password' }),
    superadmin: Object.freeze({ email: 'superadmin@example.com', password: 'password' }),
});
