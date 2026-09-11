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

## Engineering rules for changes to StyleDesk
Before coding, read the existing implementation of the thing you are changing and follow its patterns. Prefer Laravel native functionality over custom code, and existing project dependencies over new packages — do not add a package for something Laravel, Vue, PHP or the browser already solves.

Reuse existing services, actions, models, composables, components, policies, notifications, jobs and utilities. Never duplicate business logic. Keep controllers thin; business logic belongs in the Support/Services/domain classes. Do not create a new abstraction until there are two genuine use cases, and do not build speculative functionality. Write the smallest maintainable implementation that satisfies the requirement.

Preserve multi-tenant isolation on every tenant-owned query, and never trust a tenant_id supplied by the browser. Wrap operations that modify several related records in a database transaction. Never weaken validation, authorization, security, accessibility or error handling to save code. Keep the existing design-system components; do not hand-roll UI that already exists.

Add or update tests for behaviour that matters.

When modifying an existing feature, work out first: the data model, the affected workflows, the permissions, the tenant boundaries, the side effects, the notifications and activity logging, and the tests that cover it. Then make the minimum required change.

Two places the codebase's own convention wins over the generic advice above, per rule 1: validation is inline `$request->validate([...])` in the controller, not Form Requests; and authorization goes through the `$this->allow($request, 'permission.key')` helper, not Gate::authorize. Follow the codebase.
