FROM node:24.22.1-alpine
# The Docker CLI only: the engine is the host's, through the mounted socket.
RUN apk add --no-cache docker-cli
WORKDIR /runner
COPY server.mjs .
CMD ["node", "server.mjs"]
