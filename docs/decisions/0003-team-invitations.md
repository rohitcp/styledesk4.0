# 0003 — Team invitations

Spec: "Team Invite — Email Sending Requirements", 2026-08-26.

## Requirement

An Owner or Administrator adding a colleague on `/onboarding/team` creates a
pending, tenant-scoped invitation and emails a secure single-use link. The
invited person joins by creating an account, or by signing in if they already
have one. Invitations expire, can be resent and revoked, and cannot be
duplicated while one is still pending.

## Database

`team_invitations`

| Column | Why |
| --- | --- |
| `tenant_id` | The business being joined. Read from the invitation, never the request. |
| `email` | Lower-cased. The invitation is bound to this address. |
| `first_name`, `last_name`, `job_title` | The team list names the person before they have an account. |
| `role` | A string, not `role_id` — see Deviations. |
| `location_id` | Nullable = all locations, which is what a single-site business wants. |
| `invited_by` | Who to show as the inviter, and who replies go to. |
| `message` | The inviter's own words, quoted in the email. |
| `token_hash` | SHA-256 of the token. The token itself is stored nowhere. Unique. |
| `status` | `pending` / `accepted` / `expired` / `revoked`. |
| `expires_at`, `sent_at`, `accepted_at`, `revoked_at` | The timeline. |
| `accepted_user_id` | Which account this invitation produced. |

`team_invitation_deliveries` — one row per send attempt (`queued` / `sent` /
`failed`) with the provider's error. Counters would answer "how many times did
we try" but not "when, and why did it fail", which is the question actually
asked when a colleague reports never receiving their invite.

`service_team_invitation` — services the person will provide, copied into
`service_staff` on acceptance.

`staff.location_id`, `users.avatar_path` added.

## Decisions

**The token is never stored.** Only its SHA-256 hash. SHA-256 rather than
bcrypt because the hash must be *searchable* — a salted hash would mean reading
every invitation to find a match — and the token is 64 CSPRNG characters, not a
human-chosen secret, so bcrypt's cost factor is guarding against an attack that
does not apply.

**Rotate, don't flag.** Resend, revoke and acceptance all mint a new token. The
old link then hashes to a value no row holds, so it stops working as a property
of the data rather than as a rule every future code path must remember.

**Expiry is derived, not stored.** A moment passing fires no event.
`effectiveStatus()` computes it so the UI and the acceptance check cannot
disagree because no scheduled job has run.

**An invitation is the team-list record.** A `Staff` row is only created on
acceptance, so "invited" and "works here" stay distinguishable and a revoked
invite leaves nothing a booking could point at.

**A queued job, not a queued Mailable.** Wrapping the send lets each attempt be
recorded and a failure be written against the invitation. Three tries backing
off 10s/30s; after that the invitation stays Pending and the screen offers
Resend. The job re-checks the token hash before sending, so a revoke between
queue and run cannot put a live-looking link in an inbox.

**Sent on Send, not on Continue.** The step's repeater was replaced: the
invitation is created and queued when the button is pressed, which is what lets
the list show real invitations with Resend and Cancel against them.

## Deviations from the spec

- `role`, not `role_id`. Roles here are a fixed set in code and `staff.role` is
  already a string; a roles table would be a second source of truth for four
  values.
- No tenant pivot. StyleDesk is one business per user, so acceptance sets
  `users.tenant_id`. An invitation to a user who already belongs to another
  business is refused and explained rather than silently moving them.
- Signing in to accept takes one further click on a POST button rather than
  joining during the redirect, because joining a business is a state change and
  a GET that mutates is one refresh away from happening twice.

## Acceptance criteria

All covered by `tests/Feature/TeamInvitationTest.php` (31 tests).
