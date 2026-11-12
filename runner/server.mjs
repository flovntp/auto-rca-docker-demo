// The runner: the only service with access to the Docker socket. It accepts a
// signed incident from the app, reads the app's recent logs, and starts one
// short-lived agent container per incident, a few at a time.
import { createServer } from 'node:http';
import { createHmac, timingSafeEqual } from 'node:crypto';
import { spawn } from 'node:child_process';

const env = (name, fallback) => {
  const value = process.env[name] ?? fallback;
  if (value === undefined || value === '') throw new Error(`${name} is not set`);
  return value;
};

const SECRET = env('RCA_RUNNER_SECRET');
const AGENT_IMAGE = env('AGENT_IMAGE', 'rca-agent:latest');
const APP_CONTAINER = env('APP_CONTAINER');
const MAX_PARALLEL = Number(env('MAX_PARALLEL', '2'));
const MAX_QUEUED = 20;
const RUN_TIMEOUT_MS = Number(env('RUN_TIMEOUT_MINUTES', '20')) * 60_000;
// Some kernels (Synology's) have no CFS scheduler: `--cpus` fails there, and
// a relative weight with `--cpu-shares` is all there is.
const CPU_LIMIT = process.env.AGENT_CPU_SHARES
  ? ['--cpu-shares', process.env.AGENT_CPU_SHARES]
  : ['--cpus', env('AGENT_CPUS', '1')];
// Passed to the agent as they are, from the runner's own environment.
const AGENT_ENV = ['ANTHROPIC_API_KEY', 'GITHUB_TOKEN', 'GITHUB_REPO', 'CLAUDE_MODEL', 'MAX_TURNS'];
for (const name of ['ANTHROPIC_API_KEY', 'GITHUB_TOKEN', 'GITHUB_REPO']) env(name);

const queue = [];
const active = new Map(); // signature -> container name

const log = (...args) => console.log(new Date().toISOString(), ...args);

function verify(body, header) {
  const expected = Buffer.from('sha256=' + createHmac('sha256', SECRET).update(body).digest('hex'));
  const given = Buffer.from(header ?? '');
  return given.length === expected.length && timingSafeEqual(given, expected);
}

/** Runs a command, resolves with its output (stdout and stderr). */
function run(command, args, options = {}) {
  return new Promise((resolve) => {
    const child = spawn(command, args, { ...options, stdio: ['ignore', 'pipe', 'pipe'] });
    let output = '';
    child.stdout.on('data', (d) => (output += d));
    child.stderr.on('data', (d) => (output += d));
    child.on('close', (code) => resolve({ code, output }));
  });
}

/** The app's last log lines: the agent gets them, never the Docker socket. */
async function appLogs() {
  const { output } = await run('docker', ['logs', '--since', '15m', '--tail', '300', APP_CONTAINER]);
  return output.slice(-48_000);
}

async function start(incident) {
  const name = `rca-${incident.signature}`;
  active.set(incident.signature, name);
  log(`▶ ${name}: ${incident.class} at ${incident.file}:${incident.line}`);

  const agentEnv = { INCIDENT_JSON: JSON.stringify(incident), APP_LOGS: await appLogs() };
  for (const key of AGENT_ENV) if (process.env[key]) agentEnv[key] = process.env[key];

  // Values go through the environment (-e NAME without a value), never on the
  // command line where `ps` would show them.
  const args = [
    'run', '--rm', '--name', name,
    // Default bridge network: internet for the Anthropic and GitHub APIs, no
    // route to the app, its database or the runner.
    '--network', 'bridge',
    // --pids-limit is silently ignored by kernels without the pids cgroup.
    '--memory', '2g', ...CPU_LIMIT, '--pids-limit', '512',
    '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
    ...Object.keys(agentEnv).flatMap((key) => ['-e', key]),
    AGENT_IMAGE,
  ];
  const child = spawn('docker', args, { env: { PATH: process.env.PATH, ...agentEnv }, stdio: ['ignore', 'pipe', 'pipe'] });
  const prefix = (stream) => stream.on('data', (d) => d.toString().trimEnd().split('\n').forEach((l) => console.log(`[${name}] ${l}`)));
  prefix(child.stdout);
  prefix(child.stderr);

  const timer = setTimeout(() => {
    log(`⏱ ${name}: over ${RUN_TIMEOUT_MS / 60_000} min, stopping it`);
    run('docker', ['kill', name]);
  }, RUN_TIMEOUT_MS);

  child.on('close', (code) => {
    clearTimeout(timer);
    active.delete(incident.signature);
    log(`■ ${name}: exited with ${code}`);
    next();
  });
}

function next() {
  while (active.size < MAX_PARALLEL && queue.length > 0) start(queue.shift());
}

function reply(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json' }).end(JSON.stringify(body));
}

createServer((req, res) => {
  if (req.method === 'GET' && req.url === '/health') return reply(res, 200, { active: active.size, queued: queue.length });
  if (req.method !== 'POST' || req.url !== '/runs') return reply(res, 404, { error: 'not found' });

  const chunks = [];
  let size = 0;
  req.on('data', (chunk) => {
    size += chunk.length;
    if (size > 64_000) req.destroy();
    else chunks.push(chunk);
  });
  req.on('end', () => {
    const body = Buffer.concat(chunks);
    if (!verify(body, req.headers['x-rca-signature'])) return reply(res, 401, { error: 'bad signature' });

    let incident;
    try {
      incident = JSON.parse(body);
    } catch {
      return reply(res, 400, { error: 'invalid JSON' });
    }
    if (!/^[a-f0-9]{12}$/.test(incident.signature ?? '')) return reply(res, 400, { error: 'invalid signature' });

    // The app already sends each bug once; this also holds if its claims are lost.
    if (active.has(incident.signature) || queue.some((i) => i.signature === incident.signature)) {
      return reply(res, 200, { status: 'already running' });
    }
    if (queue.length >= MAX_QUEUED) return reply(res, 429, { error: 'queue full' });

    queue.push(incident);
    next();
    reply(res, 202, { status: active.has(incident.signature) ? 'started' : 'queued' });
  });
}).listen(8080, () => log(`runner listening on :8080, ${MAX_PARALLEL} agents at a time, image ${AGENT_IMAGE}`));
