FROM oven/bun:1.1.30
WORKDIR /app
RUN bun add mysql2
COPY tests/synthetic-audit.ts /app/synthetic-audit.ts
CMD ["bun","run","/app/synthetic-audit.ts"]
