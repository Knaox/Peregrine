# Queue worker — operations

Peregrine uses Laravel queues for any work that shouldn't block an HTTP
response : Bridge provisioning, Stripe webhook handlers, sync jobs, mail
notifications, plugin mail dispatch.

The default driver is `database` (`QUEUE_CONNECTION=database`), so jobs
are persisted to the `jobs` table. **Without a worker process consuming
that table, jobs accumulate but never run** — the customer pays, Stripe
acknowledges the webhook, but no Pelican server is provisioned.

## Development

The repo's `composer dev` script launches everything together :

```bash
composer dev
# Runs concurrently :
#   - php artisan serve
#   - vite (frontend HMR)
#   - php artisan queue:listen --tries=1 --timeout=0
#   - php artisan pail (live log viewer)
```

If you only need the worker (when developing the Vite-built frontend
already on `pnpm run dev`) :

```bash
php artisan queue:listen --tries=3 --timeout=60
```

`queue:listen` reloads on every job — convenient for development. **Don't
use it in production**, it's slow.

## Production

Use `queue:work` (no per-job reload) under a process supervisor that
restarts crashed workers automatically.

### Option A — Supervisor (most common)

Create `/etc/supervisor/conf.d/peregrine-queue.conf` :

```ini
[program:peregrine-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/peregrine/artisan queue:work database --queue=default --tries=3 --max-time=3600 --backoff=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/peregrine/queue.log
stopwaitsecs=3600
```

Then :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start peregrine-queue:*
```

`numprocs=2` means two parallel workers. Bump it if you have many
concurrent provisionings (each `ProvisionServerJob` is ~5–15 s).

`--max-time=3600` recycles each worker every hour — defense-in-depth
against long-lived process memory leaks.

### Option B — systemd (newer servers, no supervisor)

Create `/etc/systemd/system/peregrine-queue.service` :

```ini
[Unit]
Description=Peregrine queue worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/peregrine
ExecStart=/usr/bin/php artisan queue:work database --queue=default --tries=3 --max-time=3600 --backoff=60
Restart=always
RestartSec=5
StandardOutput=append:/var/log/peregrine/queue.log
StandardError=append:/var/log/peregrine/queue.log

[Install]
WantedBy=multi-user.target
```

Then :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now peregrine-queue.service
sudo systemctl status peregrine-queue.service
```

For multiple workers, copy the unit to `peregrine-queue@.service` and
start `peregrine-queue@1`, `peregrine-queue@2`, etc.

### Option C — Docker / Compose

Add a sidecar service to your `docker-compose.prod.yml` :

```yaml
services:
  queue:
    build: .
    command: php artisan queue:work database --tries=3 --max-time=3600
    restart: unless-stopped
    depends_on:
      - app
      - mysql
    environment:
      - DB_HOST=mysql
      - QUEUE_CONNECTION=database
```

## Reload after deploys

Workers cache the booted application. After a deploy, you must signal
them to reload (otherwise old code keeps running) :

```bash
php artisan queue:restart
```

Add this to your deploy script after `composer install` and migrations.

## Scheduler (cron)

The `routes/console.php` schedule needs to fire — add this to crontab :

```cron
* * * * * cd /var/www/peregrine && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs every minute and dispatches scheduled jobs (Bridge
purge of cancelled servers at 03:00, sync jobs every 5 min, etc.).

## Monitoring

Without Horizon (not installed in this project), the basic checks are :

```bash
# Pending jobs in queue
php artisan tinker --execute="echo DB::table('jobs')->count();"

# Failed jobs (after exhausting retries)
php artisan queue:failed

# Retry all failed
php artisan queue:retry all

# Forget a specific failed job
php artisan queue:forget <id>

# Live log
tail -f storage/logs/laravel.log | grep -E "Job|ProvisionServer|Suspend"
```

If the `jobs` table count grows monotonically, your worker is dead — check
supervisor / systemd status.

## Common failure modes

| Symptom | Diagnosis | Fix |
|---|---|---|
| Stripe webhooks return 200, but no server is provisioned | Worker is not running | Start supervisor / systemd service. Check `queue:failed`. |
| `Server.status='provisioning_failed'` with retry exhausted | Pelican unreachable for too long, or invalid plan config | Inspect `provisioning_error` column. Fix the underlying issue, then `queue:retry all`. |
| Old code runs after deploy | Workers not restarted | Add `php artisan queue:restart` to deploy script. |
| Jobs piling up exponentially | Worker count too low for traffic | Bump `numprocs` in supervisor (or scale horizontally). |
| Memory leak on long-running worker | Default behavior with old PHP versions | `--max-time=3600` recycles workers hourly. |

## Future : Horizon

When traffic grows, install [Laravel Horizon](https://laravel.com/docs/horizon)
for a real dashboard with throughput metrics, failed job replay UI, and
auto-scaling worker pools. Horizon requires Redis (`QUEUE_CONNECTION=redis`).
Migration from the database driver is ~30 min : install package,
`php artisan horizon:install`, switch `.env`, remove the supervisor entry,
add Horizon's own supervisor or systemd unit.
