---
paths:
  - '**'
---

# General

## Never commit .env or any secret
`.env` must never be committed or pushed. It is in `.gitignore` and has never been tracked — keep it that way.

- Never `git add .env`, and never force-add it (`git add -f`).
- Never commit API keys, database passwords, SMTP credentials, DigitalOcean or Cloudflare keys, or any other secret.
- Before every commit or push, check it is still untracked: `git ls-files --error-unmatch .env` must fail.
- If it ever becomes tracked, untrack it without deleting the local file: `git rm --cached .env`.
- Configuration examples go in `.env.example`, which is tracked and must hold placeholders only — every secret-ish key there stays empty (`APP_KEY=`, `AWS_SECRET_ACCESS_KEY=`, `DO_SPACES_SECRET=`).
- Do not modify the local `.env` unless a configuration change was specifically asked for.

## Use codebase-memory-mcp first for code discovery
Reach for the `codebase-memory-mcp` knowledge graph before broad Grep/Glob or reading many files. This repo is indexed as `Users-rohitphilip-Sites-styledesk1.1`.

Order of tools:
1. `search_graph` / `trace_path` / `query_graph` to find symbols, routes, callers, callees and related tests; `get_code_snippet` or a targeted Read for the few files that actually matter.
2. Laravel Boost (`database-schema`, `laravel_idea_get_routes`, `search-docs`, `config:show`) for framework, route, model and configuration context.
3. PhpStorm MCP for inspections, navigation, refactors and run configurations.
4. Grep/Glob as the fallback — literal or non-code text (Blade markup, `lang/*` arrays, CSS class names), and anywhere graph coverage is thin.

Always prefer the graph for impact analysis, dependency tracing and architecture questions.

Traps:
- Run `check_index_coverage` before any negative or exhaustive claim ("nothing else calls this"). Absence from the graph is not proof; coverage is best-effort.
- Uncommitted new files may not be indexed yet. If a structural query looks thin, re-index or fall back to source reads rather than trusting the result.
- Blade views and `lang/*` are largely template and array data, so the graph reaches little of them — use grep there.
