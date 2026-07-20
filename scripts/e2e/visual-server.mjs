import startServer from './start-server.mjs';

const stopServer = await startServer();
let stopping = false;

async function stop() {
    if (stopping) return;
    stopping = true;
    await stopServer();
    process.exit(0);
}

process.stdin.setEncoding('utf8');
process.stdin.on('data', (value) => {
    if (value.trim().toLowerCase() === 'stop') void stop();
});
process.stdin.resume();
process.on('SIGINT', () => void stop());
process.on('SIGTERM', () => void stop());
