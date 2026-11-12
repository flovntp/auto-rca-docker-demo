# Auto-RCA on Docker: a Claude Code agent on every 500

A Symfony app that, on every **distinct** server error in production, starts a short-lived Docker container running
[Claude Code](https://docs.claude.com/en/docs/claude-code) headless. The agent finds the root cause, writes a test that
reproduces the bug, fixes it, and opens a pull request that closes a tracking issue. GitHub Actions then runs the tests
on the pull request. A human reviews and merges.

```
GET /invoices/3 → 500 → app (subscriber) → runner (Docker socket) → agent container → GitHub issue + PR → CI
```

| Part | What it does |
|---|---|
| `app/` | Symfony 8.1 on FrankenPHP, with two planted bugs. `POST /invoices/{id}/payments` records a payment in a deferred SQLite transaction (read, then write): it only fails under load, with `database is locked`. `GET /invoices/3` divides by zero (a draft invoice has no lines). `src/AutoRca/` claims each incident once (`mkdir` on a volume) and sends it, signed with HMAC, to the runner after the response. |
| `load/` | A [k6](https://k6.io) ramp-up from 1 to 40 virtual users paying invoice 1, then a check that the payments add up. |
| `runner/` | ~120 lines of Node, no dependency. The only service with the Docker socket, no published port. Checks the signature, reads the app's last log lines, starts one agent container per incident, 2 at a time, killed after 20 minutes. |
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
docker compose --profile load run --rm load    # the ramp-up: ~10% of the payments fail, an agent starts
docker compose logs -f runner                  # follow it
curl -i http://localhost:8090/invoices/3       # the other bug: a second agent
```

`RCA_ENABLED=0 docker compose up -d app` runs the app without starting agents, to load it on its own.

### On a Synology NAS

Its kernel (3.10 on DSM 7) is older than what the agent's tools expect. Add to `.env`:

```bash
AGENT_CPU_SHARES=512                                    # --cpus fails: no CFS scheduler
AGENT_PRELOAD=/usr/local/lib/libgetrandom-shim.so       # git fails: no getrandom() syscall
```

`--pids-limit` is silently ignored there. Use `sudo docker-compose -f compose.yaml …` (Compose v1).

The GitHub token should be a [fine-grained token](https://github.com/settings/personal-access-tokens/new) limited to
your repository, with **Contents**, **Issues** and **Pull requests** in read and write.

To let the same bug start an agent again, empty the claims:
`docker compose exec app rm -rf var/share/prod/auto-rca`.

## Security, in short

- The app never touches the Docker socket: an RCE in the app must not be root on the host. The runner has no published
  port and only accepts signed requests.
- The agent container runs on the default bridge network (internet, no route to the app or the runner), as a non-root
  user, with no capabilities, `no-new-privileges`, and CPU, memory and PID limits.
- Claude Code may read and edit files, and run `php`, the tests and read-only git commands. Anything else is refused.
  `php` itself can run any code: the boundary is the container, not the allowlist.
- The exception message and the logs come from production: the prompt marks them as data, but treat prompt injection
  as possible. The agent can't push to `main` or merge: a human reviews every pull request.
- The incident (message, stack trace) ends up in a GitHub issue: on a public repository, mind personal data in your
  exception messages.
