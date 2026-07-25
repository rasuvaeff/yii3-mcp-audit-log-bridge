# Examples

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| `audit-trail.php` | A tool call recorded into an in-memory audit log: actor from the handshake, per-argument changes with a masked `password`, outcome + duration | no |
| `user-actor.php` | The same call credited to the authenticated user (`IdentityAuditActorResolver`) instead of the connection, and the guest fallback | no |

Run from the package root (after `composer install`):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/audit-trail.php
```
