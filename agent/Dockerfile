# PHP to run the app's tests, Node for Claude Code, git to work on the repo,
# bash for Claude Code's Bash tool (without it, it can run no command at all).
FROM php:8.4-cli-alpine AS shim
RUN apk add --no-cache build-base
COPY getrandom-shim.c /tmp/
RUN gcc -shared -fPIC -O2 -o /tmp/libgetrandom-shim.so /tmp/getrandom-shim.c

FROM php:8.4-cli-alpine
RUN apk add --no-cache bash git nodejs npm libgcc libstdc++ ripgrep \
 && npm install -g @anthropic-ai/claude-code@2.1.312 \
 && adduser -D -h /home/agent agent
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Only loaded when the runner sets LD_PRELOAD (AGENT_PRELOAD): old kernels.
COPY --from=shim /tmp/libgetrandom-shim.so /usr/local/lib/libgetrandom-shim.so
# Claude Code's own ripgrep binary doesn't run on Alpine (musl): use the system one.
ENV USE_BUILTIN_RIPGREP=0 \
    DISABLE_AUTOUPDATER=1 \
    CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
COPY run.mjs /opt/agent/run.mjs
USER agent
WORKDIR /home/agent
CMD ["node", "/opt/agent/run.mjs"]
