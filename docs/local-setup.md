# StyleDesk — local setup (MAMP PRO)

## Stack as installed

| Layer | Choice | Notes |
|---|---|---|
| Framework | Laravel 13.29.0 | PHP 8.3.30 (MAMP) |
| Tenancy | `stancl/tenancy` 3.10 | single database, hybrid identification |
| Auth | `laravel/fortify` 1.39 | headless, no views |
| Real-time | `laravel/reverb` 1.11 + `laravel-echo` 2.4 | Pusher protocol, self-hosted |
| Queues | database driver | `jobs` table |
| Frontend | Tailwind 4 + Vue 3.5 | Vue islands inside Blade |
| Mail (local) | `redberry/mailbox-for-laravel` | dashboard at `/mailbox` |
| Mail (prod) | Postmark | `MAIL_MAILER=postmark` |
| SMS | Sendivo | credentials only; channel not yet written |
| AI tooling | `laravel/boost` 2.6 | MCP server + skills in `.claude/` |
| Quality | SonarQube | `sonar-project.properties` |

## Database

MAMP's MySQL 8.0.44 has TCP networking **disabled** (`@@port` reports 0), so the
app connects over the unix socket:

```
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
DB_DATABASE=styledesk_v2
```

`styledesk` and `styledesk_testing` are from an earlier, abandoned build and are
deliberately left untouched. Backups: `storage/app/backups/`.

CLI access:

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -u root -proot -S /Applications/MAMP/tmp/mysql/mysql.sock styledesk_v2
```

## Host names

Subdomain tenancy needs wildcard-ish DNS. macOS `/etc/hosts` does not support
wildcards, so each test tenant needs its own line:

```
127.0.0.1   styledesk.test
127.0.0.1   www.styledesk.test
127.0.0.1   acme.styledesk.test
```

Add with:

```bash
sudo sh -c 'printf "\n127.0.0.1 styledesk.test\n127.0.0.1 www.styledesk.test\n127.0.0.1 acme.styledesk.test\n" >> /etc/hosts'
```

To avoid editing `/etc/hosts` per tenant, either run `dnsmasq` for `*.test` or
switch to Laravel Herd, which handles wildcard `.test` DNS itself.

### MAMP PRO vhost

MAMP PRO manages Apache config from its GUI — hand-edited `httpd.conf` changes
are overwritten. In MAMP PRO:

1. **Hosts** → **+** → name `styledesk.test`
2. Document root: `/Users/rohitphilip/Sites/styledesk1.1/public`
3. Add `www.styledesk.test` and `acme.styledesk.test` as aliases (or as their
   own hosts with the same document root)
4. PHP version: 8.3.30
5. Save and restart servers

`CENTRAL_DOMAINS` in `.env` decides which of those hosts are treated as central
vs. tenant — it is not inferred from Apache.

## Running

```bash
php artisan serve          # or use the MAMP vhost
npm run dev                # Vite dev server
php artisan reverb:start   # websockets on :8080
php artisan queue:work     # background jobs
php artisan test           # runs against styledesk_v2_testing
```

Local mail lands at `/mailbox` and is never delivered. To capture *and* deliver,
set `MAILBOX_DECORATE=postmark`.

## Switching mail provider

`MAIL_MAILER=postmark` with `POSTMARK_API_KEY` set. Mailgun and Letter are
swapped the same way — add the mailer to `config/mail.php` and change the one
env var; no application code references a provider directly.
