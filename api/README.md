# Pulse machine API

JSON endpoints for other apps on the same server, such as the Chief-of-Staff "168 Hours" planner.
They are **not** behind SSO, because a server makes these calls, not a browser.

| Endpoint | Purpose |
|---|---|
| `GET  api/catalog.php` | The client → task list. It uses the same filters as `apps/hours.php`, so other apps offer the same choices. |
| `POST api/hours-drafts.php` | Send a user's hours for a range of up to 7 days as **drafts**. The user confirms them on the hours page. Sending again replaces the earlier unconfirmed send. |

The request and response shapes are documented at the top of each file.

## Errors a caller should handle
- `401 unauthorized`: missing or wrong token. Send `Authorization: Bearer <token>`; `X-Pulse-Token: <token>` is also accepted.
- `400 unknown_task`: a task id that doesn't exist, listed in `taskIds`.
- `400 task_not_loggable`: the task exists but can no longer be logged, listed in `taskIds`. It is completed, or its project or client is inactive. This is the same rule as the hours page.

## Rules
- **Hand-entered hours (`hours.source IS NULL`) are never changed.**
  - A draft for the same task and day is reported as a conflict.
  - When the user confirms, that draft stays behind as a draft.
- **Confirming a send makes `hours` match it for that source and date range.**
  - It updates, adds and removes rows, but only rows carrying that source.
  - If the user edits one of those rows by hand on the hours page, the row becomes theirs (`source` is cleared).
- **Reports only ever read `hours`.** Drafts are invisible to them until the user confirms.

## Deploy
1. **Migration:** run `php database/migrate_add_hours_drafts.php`. It is safe to re-run. Until it has run, `apps/hours.php` behaves exactly as before.
2. **Token file:** put it outside the docroot, e.g. `/etc/pulse/api-tokens`, readable by the php-fpm user. Each line is:
   ```
   <source> <sha256-of-token> [email,email|*]
   cos-168 <64-hex-sha256> charlie@veerless.com
   ```
   To generate a token and its hash:
   ```
   openssl rand -hex 32 | tee /dev/stderr | tr -d '\n' | sha256sum
   ```
   Give the raw token to the calling app; only the hash goes in this file.
3. **Pool environment:** set `env[PULSE_API_TOKENS_FILE] = /etc/pulse/api-tokens` in the php-fpm pool.
4. **nginx:** add a location that skips `auth_request` and only answers the server itself:
   ```nginx
   location ~ ^/pulse/api/(catalog|hours-drafts)\.php$ {
       allow 127.0.0.1; allow <server public IP>; deny all;
       # no auth_request: token-authenticated
       include snippets/fastcgi-php.conf;
       fastcgi_param HTTP_AUTHORIZATION $http_authorization;
       fastcgi_pass <pulse php-fpm socket>;
   }
   ```
   `api/_api.php` (shared helpers) and this README must not be served.
