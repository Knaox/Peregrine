# Worker queue — exploitation

Peregrine utilise les queues Laravel pour tout travail qui ne doit pas
bloquer une réponse HTTP : provisioning Bridge, handlers de webhook
Stripe, jobs de sync, notifications mail, dispatch de mail des plugins.

Le driver par défaut est `database` (`QUEUE_CONNECTION=database`), donc
les jobs sont persistés dans la table `jobs`. **Sans un processus worker
qui consomme cette table, les jobs s'accumulent mais ne s'exécutent
jamais** — le client paie, Stripe acquitte le webhook, mais aucun serveur
Pelican n'est provisionné.

## Développement

Le script `composer dev` du dépôt lance tout en parallèle :

```bash
composer dev
# Runs concurrently :
#   - php artisan serve
#   - vite (frontend HMR)
#   - php artisan queue:listen --tries=1 --timeout=0
#   - php artisan pail (live log viewer)
```

Si vous n'avez besoin que du worker (lorsque vous développez le frontend
buildé par Vite déjà en `pnpm run dev`) :

```bash
php artisan queue:listen --tries=3 --timeout=60
```

`queue:listen` recharge à chaque job — pratique en développement.
**Ne l'utilisez pas en production**, c'est lent.

## Production

Utilisez `queue:work` (pas de rechargement par job) sous un superviseur
de processus qui redémarre automatiquement les workers crashés.

### Option A — Supervisor (la plus courante)

Créez `/etc/supervisor/conf.d/peregrine-queue.conf` :

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

Puis :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start peregrine-queue:*
```

`numprocs=2` signifie deux workers en parallèle. Augmentez la valeur si
vous avez beaucoup de provisionings concurrents (chaque
`ProvisionServerJob` prend ~5–15 s).

`--max-time=3600` recycle chaque worker toutes les heures — défense en
profondeur contre les fuites mémoire des processus longue durée.

### Option B — systemd (serveurs récents, sans supervisor)

Créez `/etc/systemd/system/peregrine-queue.service` :

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

Puis :

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now peregrine-queue.service
sudo systemctl status peregrine-queue.service
```

Pour plusieurs workers, copiez l'unit en `peregrine-queue@.service` et
démarrez `peregrine-queue@1`, `peregrine-queue@2`, etc.

### Option C — Docker / Compose

Ajoutez un service sidecar à votre `docker-compose.prod.yml` :

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

## Recharger après un déploiement

Les workers mettent en cache l'application bootée. Après un déploiement,
vous devez leur signaler de recharger (sinon l'ancien code continue à
tourner) :

```bash
php artisan queue:restart
```

Ajoutez cette commande à votre script de déploiement après
`composer install` et les migrations.

## Scheduler (cron)

Le schedule de `routes/console.php` doit être déclenché — ajoutez ceci
au crontab :

```cron
* * * * * cd /var/www/peregrine && php artisan schedule:run >> /dev/null 2>&1
```

Le scheduler tourne toutes les minutes et dispatche les jobs planifiés
(purge Bridge des serveurs annulés à 03:00, jobs de sync toutes les
5 min, etc.).

## Supervision

Sans Horizon (non installé dans ce projet), les vérifications de base
sont :

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

Si le compteur de la table `jobs` croît de façon monotone, votre worker
est mort — vérifiez le statut supervisor / systemd.

## Modes de défaillance courants

| Symptôme | Diagnostic | Correctif |
|---|---|---|
| Les webhooks Stripe renvoient 200, mais aucun serveur n'est provisionné | Le worker ne tourne pas | Démarrer le service supervisor / systemd. Vérifier `queue:failed`. |
| `Server.status='provisioning_failed'` avec retry épuisé | Pelican injoignable trop longtemps, ou config de plan invalide | Inspecter la colonne `provisioning_error`. Corriger la cause racine, puis `queue:retry all`. |
| L'ancien code tourne après un déploiement | Workers non redémarrés | Ajouter `php artisan queue:restart` au script de déploiement. |
| Jobs qui s'empilent de façon exponentielle | Trop peu de workers pour le trafic | Augmenter `numprocs` dans supervisor (ou scaler horizontalement). |
| Fuite mémoire sur un worker longue durée | Comportement par défaut sur d'anciennes versions de PHP | `--max-time=3600` recycle les workers toutes les heures. |

## Futur : Horizon

Quand le trafic augmente, installez [Laravel Horizon](https://laravel.com/docs/horizon)
pour un vrai tableau de bord avec métriques de débit, UI de rejeu des
jobs échoués et pools de workers à autoscaling. Horizon nécessite Redis
(`QUEUE_CONNECTION=redis`). La migration depuis le driver database prend
~30 min : installer le package, `php artisan horizon:install`, basculer
le `.env`, retirer l'entrée supervisor, ajouter le supervisor ou l'unit
systemd propre à Horizon.
