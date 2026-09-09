---
paths:
  - 'app/Models/Client.php,database/seeders/**,app/Http/Controllers/ClientController.php'
---

# Http Controllers

## clients.mobile and clients.email are a cache — write contacts through syncPhones/syncEmails
The record of a client's contact details is `client_phones` / `client_emails`. `clients.mobile` and `clients.email` are a denormalised cache of the PRIMARY row, maintained by `Client::writeContacts()`. Never fill those columns directly when creating or importing a client — always go through `syncPhones()` / `syncEmails()`, which enforce one primary, dedupe, and refresh the cache.

Filling only the columns produces a client whose number appears in the clients LISTING (which reads the cache, for speed on a paginated grid) and nowhere on their PROFILE (which reads the record, because it shows every number and their types). That drift is silent — nothing errors, the data just half-exists. `SmileSpaBookingsSeeder` did exactly this and left 25 seeded clients in that state; `2026_09_09_105456_backfill_client_contact_rows` repairs any row where the cache holds a value and no child row exists.

The backfill only touches clients with NO rows of that kind. A client who already has contacts has a record and the cache is downstream of it — writing from the cache there would be the tail wagging the dog. Its `down()` is deliberately a no-op: these are real people's contact details, and by rollback time the client may have several numbers the cache never knew about.
