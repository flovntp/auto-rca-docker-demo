# PHP to run the app's tests, Node for Claude Code, git to work on the repo,
# bash for Claude Code's Bash tool (without it, it can run no command at all).
FROM php:8.4-cli-alpine
RUN apk add --no-cache bash git nodejs npm libgcc libstdc++ ripgrep \
 && npm install -g @anthropic-ai/claude-code@2.1.312 \
 && adduser -D -h /home/agent agent
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# Claude Code's own ripgrep binary doesn't run on Alpine (musl): use the system one.
ENV USE_BUILTIN_RIPGREP=0 \
    DISABLE_AUTOUPDATER=1 \
    CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1
COPY run.mjs /opt/agent/run.mjs
USER agent
WORKDIR /home/agent
CMD ["node", "/opt/agent/run.mjs"]
