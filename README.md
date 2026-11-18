# Auto-RCA on Docker: triage every 500, then a Claude Code agent for the code bugs

A Symfony app that, on every **distinct** server error in production (a 500 in the web app, or a message its worker
gives up on after its retries), asks a small model to triage it, then starts a
short-lived Docker container running [Claude Code](https://docs.claude.com/en/docs/claude-code) headless for the
incidents a change in the code can fix. The coding agent finds the root cause, writes a test that reproduces the bug,
fixes it, and opens a pull request that closes the triage's issue. GitHub Actions runs the tests on the pull request.
A human reviews and merges.

```
500 → app (subscriber) ──────────┐
                                 ├→ queue → worker (Symfony AI triage, Claude Haiku) → GitHub issue
message failed, retries spent ───┘                └─ code bug → runner (Docker socket) → agent container (Claude Code) → PR → CI
  (worker, subscriber)
```

Three episodes: [`episode-1`](../../tree/episode-1) (a coding agent on every distinct 500),
[`episode-2`](../../tree/episode-2) (a triage with Symfony AI first) and episode 3, on `main` (the worker's failures
too).

| Part | What it does |
|---|---|
| `app/` | Symfony 8.1 on FrankenPHP, with four planted bugs. `GET /invoices/{id}/total/{currency}` calls an exchange-rates provider that is down (`rates-api` doesn't exist): no change in the code fixes it. `POST /invoices/{id}/payments` records a payment in a deferred SQLite transaction (read, then write): it only fails under load, with `database is locked`. `GET /invoices/3` divides by zero (a draft invoice has no lines). `POST /invoices/2/reminders` answers 202, and the worker fails to send the reminder: that customer has no email address. `src/AutoRca/` claims each incident once (`mkdir` on a volume shared by the app and the worker) and queues it (Messenger, SQLite) after the response: the web app holds no API key nor token. |
| `app/src/Triage/` | The worker (same image, `messenger:consume`). A [Symfony AI](https://symfony.com/doc/current/ai/index.html) agent on Claude Haiku 5.5 reads the incident and the code with two read-only tools (`SourceCode`), and answers with a `TriageVerdict` (structured output): category, cause, evidence, confidence, whether to start a coding agent. Every incident gets an issue (an issues-only token); a code bug goes to the runner, signed with HMAC, with the issue number and the triage's hypothesis. A message the worker gives up on, after its 3 retries (about 2 minutes), is an incident too (`WorkerFailureSubscriber`): its origin is the message's class, never its content. A failure a retry gets past is not one, and a triage that fails is never triaged in turn. |
| `load/` | A [k6](https://k6.io) ramp-up from 1 to 40 virtual users paying invoice 1, then a check that the payments add up. |
| `runner/` | ~120 lines of Node, no dependency. The only service with the Docker socket, no published port. Checks the signature, reads the last log lines of the container where it failed (the app or the worker), starts one agent container per incident, 2 at a time, killed after 20 minutes. |
| `agent/` | PHP 8.4, Composer, git and Claude Code. `run.mjs` opens the issue, clones the repo at the running revision, runs `claude -p` with an allowlist of tools, then commits, pushes and opens the pull request. Claude Code never gets the GitHub token. |

## Run it

You need Docker, a GitHub repository you can push to (a fork of this one), and an Anthropic API key.

```bash
git clone https://github.com/flovntp/auto-rca-docker-demo && cd auto-rca-docker-demo
cp .env.example .env   # fill it in: secrets, API key, GitHub token and repository
echo "APP_REVISION=$(git rev-parse --short HEAD)" >> .env
docker compose --profile build-only build      # app, runner and the agent image
docker compose up -d app runner
curl -i http://localhost:8090/invoices/1       # 200
curl -i http://localhost:8090/invoices/1/total/USD   # the rates provider is down: an issue, no agent
curl -i http://localhost:8090/invoices/3             # a code bug: an issue, then a pull request
curl -i -X POST http://localhost:8090/invoices/2/reminders   # 202, then the worker fails: an issue ~2 minutes later
docker compose --profile load run --rm load          # the ramp-up: ~10% of the payments fail, another one
docker compose logs -f worker runner                 # the triage, then the coding agents
```

`RCA_ENABLED=0 docker compose up -d app` runs the app without starting agents, to load it on its own.

### On a Synology NAS

Its kernel (3.10 on DSM 7) is older than what the agent's tools expect. Add to `.env`:

```bash
AGENT_CPU_SHARES=512                                    # --cpus fails: no CFS scheduler
AGENT_PRELOAD=/usr/local/lib/libgetrandom-shim.so       # git fails: no getrandom() syscall
```

`--pids-limit` is silently ignored there. Use `sudo docker-compose -f compose.yaml …` (Compose v1).

Use two [fine-grained tokens](https://github.com/settings/personal-access-tokens/new) limited to your repository:
`GITHUB_TOKEN` for the runner (**Contents**, **Issues**, **Pull requests**, read and write) and `GITHUB_ISSUES_TOKEN`
for the worker (**Issues** only).

To let the same bug start an agent again, empty the claims:
`docker compose exec app rm -rf var/share/prod/auto-rca`.

## Security, in short

- The app never touches the Docker socket, nor any API key or token: an RCE in the app must not be root on the host. The runner has no published
  port and only accepts signed requests (from the worker).
- The agent container runs on the default bridge network (internet, no route to the app or the runner), as a non-root
  user, with no capabilities, `no-new-privileges`, and CPU, memory and PID limits.
- Claude Code may read and edit files, and run `php`, the tests and read-only git commands. Anything else is refused.
  `php` itself can run any code: the boundary is the container, not the allowlist.
- The exception message and the logs come from production: the prompt marks them as data, but treat prompt injection
  as possible. The agent can't push to `main` or merge: a human reviews every pull request.
- The incident (message, stack trace) ends up in a GitHub issue: on a public repository, mind personal data in your
  exception messages.
