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
