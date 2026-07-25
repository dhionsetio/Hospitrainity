import { once } from 'node:events';
import { createConnection } from 'node:net';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';

import { baseURL, e2eEnv, phpBinary, repoRoot } from './environment.mjs';

const publicRoot = path.join(repoRoot, 'public');
const serverRouter = path.join(
    repoRoot,
    'vendor',
    'laravel',
    'framework',
    'src',
    'Illuminate',
    'Foundation',
    'resources',
    'server.php',
);

function delay(milliseconds) {
    return new Promise(resolve => setTimeout(resolve, milliseconds));
}

function isServerPortOccupied() {
    const url = new URL(baseURL);

    return new Promise((resolve) => {
        const socket = createConnection({ host: url.hostname, port: Number(url.port) });
        const finish = (occupied) => {
            socket.destroy();
            resolve(occupied);
        };

        socket.setTimeout(1_000, () => finish(false));
        socket.once('connect', () => finish(true));
        socket.once('error', () => finish(false));
    });
}

async function waitForServer(server) {
    const deadline = Date.now() + 30_000;
    while (Date.now() < deadline) {
        if (server.exitCode !== null) {
            throw new Error(`The PHP E2E server exited early with status ${server.exitCode}.`);
        }

        try {
            const response = await fetch(`${baseURL}/login`);
            if (response.ok) return;
        } catch {
            // The socket is expected to refuse connections during startup.
        }

        await delay(200);
    }

    throw new Error(`The PHP E2E server did not become ready at ${baseURL}.`);
}

async function stopServer(server) {
    if (server.exitCode !== null) return;

    server.kill();
    await Promise.race([once(server, 'exit'), delay(5_000)]);
    if (server.exitCode !== null) return;

    if (process.platform === 'win32') {
        spawnSync('taskkill', ['/PID', String(server.pid), '/T', '/F'], {
            stdio: 'ignore',
            windowsHide: true,
        });
    } else {
        server.kill('SIGKILL');
    }
}

export default async function startServer() {
    if (await isServerPortOccupied()) {
        throw new Error(`Refusing to start the isolated E2E server because ${baseURL} is already in use.`);
    }

    const server = spawn(phpBinary, [
        '-S',
        '127.0.0.1:8010',
        '-t',
        publicRoot,
        serverRouter,
    ], {
        cwd: publicRoot,
        env: e2eEnv,
        stdio: 'ignore',
        windowsHide: true,
    });

    await waitForServer(server);
    return () => stopServer(server);
}
