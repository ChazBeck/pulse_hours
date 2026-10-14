# High Fives → Zoom Team Chat

Each High Five is posted to a Zoom Team Chat channel as it is given. Delivery
uses Zoom's **Incoming Webhook** app, the same route as the client news
digest. Code: `includes/high_fives_zoom.php`.

Without credentials, Zoom is off: High Fives are saved as `zoom_status = 'off'`
and never posted, including after Zoom is switched on later.

## 1. Connect the channel (in Zoom)

In the target channel, type `/inc connect high-fives`. Zoom replies with an
**endpoint** and a **verification token**. Use a separate test channel for dev.

## 2. Credentials file (on the server)

One file per environment, outside the docroot, readable by PHP-FPM only:

```
# /etc/pulse/zoom-high-fives.env       (prod)
# /etc/pulse-dev/zoom-high-fives.env   (dev → test channel)
ZOOM_WEBHOOK_ENDPOINT=https://integrations.zoom.us/chat/webhooks/incomingwebhook/...
ZOOM_WEBHOOK_TOKEN=...
```

```bash
sudo install -m 640 -o root -g www-data zoom-high-fives.env /etc/pulse/zoom-high-fives.env
```

## 3. nginx

In the `/pulse/` block's nested `location ~ \.php$`, next to `SSO_JWT_INCLUDE`:

```nginx
fastcgi_param PULSE_ZOOM_HIGH_FIVES_FILE /etc/pulse/zoom-high-fives.env;
```

Then `sudo nginx -t && sudo systemctl reload nginx`.

## 4. Retry cron

The page posts right after saving (after the browser has moved on). Anything
that fails (Zoom down, timeout) is retried by a CLI script, at most 10 attempts
per High Five:

```cron
*/15 * * * * www-data php /var/www/sites/apps.veerless.net/pulse/scripts/post_high_fives.php /etc/pulse/zoom-high-fives.env >> /var/log/pulse-high-fives-zoom.log 2>&1
```

(in `/etc/cron.d/pulse-high-fives`; create the log file owned by www-data.)

## Checking

```sql
SELECT zoom_status, COUNT(*) FROM high_fives GROUP BY zoom_status;
SELECT id, zoom_attempts, zoom_error FROM high_fives WHERE zoom_status = 'failed';
```

`posted` = Zoom answered 200. Zoom returns a bare `OK` with no message id, so
that is the only confirmation available.
