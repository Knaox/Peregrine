<div align="center">
  <img src="https://raw.githubusercontent.com/Knaox/Peregrine/main/public/images/logo.webp" width="320" alt="Peregrine" />

  <h1>Peregrine</h1>
  <p><strong>Le panel open-source pour serveurs de jeux qui donne à <a href="https://pelican.dev">Pelican</a> l'UX joueur, l'outillage admin et la personnalisation qu'il mérite.</strong></p>

  <p>
    <a href="https://github.com/Knaox/Peregrine/releases"><img alt="Version" src="https://img.shields.io/github/v/release/Knaox/Peregrine?include_prereleases&label=version&color=e11d48"></a>
    <a href="https://github.com/Knaox/Peregrine/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue"></a>
    <a href="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml"><img alt="Docker build" src="https://github.com/Knaox/Peregrine/actions/workflows/docker.yml/badge.svg"></a>
    <a href="https://github.com/Knaox/Peregrine/pkgs/container/peregrine"><img alt="Image" src="https://img.shields.io/badge/image-ghcr.io%2Fknaox%2Fperegrine-2496ED?logo=docker&logoColor=white"></a>
    <img alt="PHP 8.3" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white">
    <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
    <img alt="React 19" src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black">
  </p>

  <p>
    <a href="#-captures">Captures</a> ·
    <a href="#-pourquoi-peregrine">Pourquoi Peregrine ?</a> ·
    <a href="#-démarrage-rapide-docker">Démarrage rapide</a> ·
    <a href="#-fonctionnalités">Fonctionnalités</a> ·
    <a href="#-theme-studio">Theme Studio</a> ·
    <a href="#-marketplace-de-plugins">Plugins</a> ·
    <a href="#-intégrations-shop">Bridge shop</a> ·
    <a href="#-roadmap">Roadmap</a>
  </p>

  <p><a href="README.md">🇬🇧 Read in English</a></p>
</div>

---

## C'est quoi Peregrine ?

**Peregrine est un panel moderne pour serveurs de jeux.** Il dialogue avec [Pelican](https://pelican.dev) (le fork activement maintenu de Pterodactyl) via son API et ajoute par-dessus :

- une **SPA React 19 pour les joueurs** : console WebSocket, gestionnaire de fichiers complet, SFTP, bases de données, sauvegardes, planifications, allocations réseau, invitations de sous-utilisateurs,
- un **panel admin Filament 5** pour les utilisateurs / serveurs / plans / eggs / nodes — avec sync Pelican en un clic,
- un **Theme Studio** avec preview live en split-screen pour rebrander le panel sans toucher à une ligne de code,
- une **Marketplace de plugins** alimentée par un registre GitHub public — install / update / uninstall en un clic,
- **Docker-first**, image multi-arch publiée sur GHCR à chaque push sur `main`,
- UI bilingue **FR / EN**, chaque chaîne traduite.

Fonctionne en **standalone** pour un hébergeur unique, ou se branche sur **Paymenter**, **WHMCS** (via le module Pelican-WHMCS) ou **votre propre shop custom** (webhooks Stripe) grâce au [Bridge intégré](#-intégrations-shop) pour du provisioning piloté par les abonnements.

---

## 📸 Captures

<table>
  <tr>
    <td colspan="2" align="center">
      <a href="docs/screenshots/server-overview.png"><img src="docs/screenshots/server-overview.png" alt="Vue d'ensemble serveur" /></a>
      <br/><sub><strong>Vue d'ensemble serveur</strong> — CPU / RAM / disque / réseau live via Wings WebSocket, contrôles d'alimentation, image de bannière.</sub>
    </td>
  </tr>
  <tr>
    <td width="50%" align="center" valign="top">
      <a href="docs/screenshots/theme-studio.png"><img src="docs/screenshots/theme-studio.png" alt="Theme Studio" /></a>
      <br/><sub><strong>Theme Studio</strong> — preview live en split-screen, 7 presets, ~60 tokens, templates de login, overrides par page.</sub>
    </td>
    <td width="50%" align="center" valign="top">
      <a href="docs/screenshots/plugins-marketplace.png"><img src="docs/screenshots/plugins-marketplace.png" alt="Marketplace de plugins" /></a>
      <br/><sub><strong>Marketplace de plugins</strong> — parcourir, installer, activer, mettre à jour, désinstaller — registre GitHub public.</sub>
    </td>
  </tr>
</table>

---

## 🦅 Pourquoi Peregrine ?

Pelican fait déjà le gros du travail côté daemon. Peregrine est ce qui se met **par-dessus**, pour les gens qui utilisent vraiment le panel tous les jours.

| | Panel Pelican (par défaut) | **Peregrine** |
|---|---|---|
| UX joueur | Fonctionnel, classique | SPA React moderne, dark/light, responsive |
| Gestionnaire de fichiers | Basique | Parité complète (chmod, pull, drag-drop, bulk, archives) |
| Personnalisation | Overrides CSS | **Theme Studio** avec preview live — sans code |
| Plugins | Aucun first-party | **Marketplace** alimentée par un registre GitHub public |
| Invitations sous-utilisateurs | Manuel | Plugin first-class, permissions granulaires, email |
| SSO / Bridge billing | Aucun | OAuth2 + Stripe + webhooks Pelican |
| i18n | EN | FR + EN (chaque chaîne), extensible |
| Déploiement | Multi-étapes | `docker compose up -d` → wizard navigateur 7 étapes |

Si vous lancez une activité d'hébergement ou voulez offrir à votre communauté un panel qui ne ressemble pas à un formulaire admin de 2014, Peregrine est fait pour vous.

---

## ⚡ Démarrage rapide (Docker)

> Le gros du paramétrage se fait dans votre navigateur. Une fois le container lancé, ouvrez le port 8080 et un **wizard d'installation en 7 étapes** vous guide : langue, base de données, compte admin, identifiants Pelican, mode d'auth, Bridge optionnel, récap. Vous ne touchez jamais au `.env` à la main.

Deux fichiers compose dans le repo, c'est tout :

| Fichier | Quand l'utiliser |
|---|---|
| **[`docker-compose.yml`](docker-compose.yml)** *(défaut)* | All-in-one — MySQL 8.4 + Redis embarqués. Install production clé en main. |
| **[`docker-compose.external-db.yml`](docker-compose.external-db.yml)** | Vous avez déjà un MySQL / MariaDB / PostgreSQL managé — gardez-le, récupérez Redis embarqué. |

### Option A — all-in-one (recommandé)

```bash
curl -fsSLO https://raw.githubusercontent.com/Knaox/Peregrine/main/docker-compose.yml
docker compose up -d
open http://localhost:8080
```

Fonctionne en Stack Portainer — collez le compose, cliquez Deploy. Le wizard pré-remplit l'étape DB avec `mysql` / `peregrine` / `peregrine` (surchargez `DB_PASSWORD` au déploiement).

### Option B — votre propre base de données

```bash
curl -fsSLO https://raw.githubusercontent.com/Knaox/Peregrine/main/docker-compose.external-db.yml
# Définir DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD via .env ou variables Portainer
docker compose -f docker-compose.external-db.yml up -d
open http://localhost:8080
```

### Ce qui tourne dans le container

Une seule image, supervisée par `supervisord` et auto-redémarrée en cas de crash :

- **`nginx`** — serveur HTTP sur le port 8080
- **`php-fpm`** — pool de workers PHP 8.3
- **`php artisan queue:work`** — traite les webhooks Bridge / Stripe / Pelican-mirror, les emails plugins, les jobs de sync

**Pas de container worker séparé, pas de supervisor / systemd à configurer côté hôte.**

### Option C — bare metal (sans Docker)

```bash
git clone https://github.com/Knaox/Peregrine.git && cd Peregrine
composer install --no-dev --optimize-autoloader
pnpm install && pnpm run build
cp .env.example .env && php artisan key:generate && php artisan storage:link
php artisan serve &                   # HTTP sur :8000
php artisan queue:work --daemon &     # mails + jobs sync
```

Reverse-proxy avec nginx / Caddy / Traefik comme d'habitude.

---

## ✨ Fonctionnalités

### Panel joueur (SPA React 19)
- **Vue d'ensemble** — CPU / RAM / disque / réseau live via Wings WebSocket, uptime, image de bannière, actions rapides selon les permissions.
- **Console** — terminal xterm.js, historique de commandes persistant par utilisateur, Start / Stop / Restart / Kill avec gating granulaire `control.*`.
- **Gestionnaire de fichiers** — parité complète Pelican : list, read, edit, write, rename, delete, copy, compress, decompress, `chmod` (octal), `pull` URL distante, drag-and-drop upload, création de dossier, actions bulk. Mode lecture seule pour les users sans `file.update`.
- **SFTP** — panneau identifiants, copie clipboard, reset mot de passe SFTP séparé.
- **Bases de données** — créer, rotation mot de passe, supprimer, voir identifiants.
- **Sauvegardes** — créer, télécharger, verrouiller, restaurer, supprimer.
- **Planifications** — presets cron + éditeur avancé, run-now, gestion des tâches.
- **Réseau** — liste allocations, notes, primaire, suppression bulk, ajout.
- **Invitations** (plugin livré) — inviter par email avec permissions granulaires, éditer pending et sous-users actifs.

### Panel admin (Filament 5)
- Resources : Users, Servers, Plans, Eggs, Nests, Nodes — sync Pelican en un clic.
- **Settings** — nom de l'app, logo, favicon, liens custom dans le header, identifiants Pelican, mode auth, bridge.
- **Templates email** — sujet + corps HTML par locale, placeholders variables, favicon-as-logo automatique.
- **About & Updates** — check live des releases GitHub, commandes update Docker-aware avec bouton clipboard.

### Plateforme
- **Permissions strictes** — chaque clé de permission sous-user Pelican mappe une ability de policy dédiée. L'UI cache ce que les users ne peuvent pas faire ; l'API renvoie 403 s'ils tentent quand même.
- **Multi-provider auth** — local, OAuth2 (Shop SaaSykit-compatible, Paymenter), Google, Discord, LinkedIn — coexistent, configurables depuis `/admin/auth-settings`. 2FA TOTP natif avec option d'enforcement admin.
- **Cache Redis** — branding, thème, allocations, identifiants SFTP, listes backups/databases/schedules, settings.
- **Queue-safe** — les Mailables des plugins ne sont jamais sérialisés dans la queue.
- **Bilingue FR + EN** — chaque nouvelle string atterrit dans les deux fichiers i18n, même commit.
- **Image multi-arch** — `linux/amd64` + `linux/arm64`, build auto à chaque push sur `main`.

---

## 🎨 Theme Studio

Un studio React full-screen, admin-only, à `/theme-studio` avec **preview live en split-screen** — éditez à gauche, voyez votre panel se mettre à jour en temps réel à droite. Accessible depuis Filament → **Settings → Apparence → Open Theme Studio**.

<div align="center">
  <a href="docs/screenshots/theme-studio.png"><img src="docs/screenshots/theme-studio.png" alt="Capture Theme Studio" width="900" /></a>
</div>

Ce que vous pouvez faire sans toucher à une ligne de code :

- **7 presets de marque** (Orange / Amber / Crimson / Emerald / Indigo / Violet / Slate), chacun avec variantes dark + light complètes.
- **~60 design tokens** — couleurs, radius, fonts, ombres, densité, largeurs de layout, styles de sidebar, largeurs de bordure, hover scale, glass blur, vitesse de transition, échelle de fonts.
- **4 templates de login** (Centered / Split / Overlay / Minimal) avec upload d'image et 8 patterns de fond.
- **Overrides par page** — console fullwidth, gestionnaire de fichiers fullwidth, dashboard 4 colonnes.
- **Configurateur sidebar** — largeurs, blur, floating, classic/rail/mobile, entrées nav custom avec réordonnancement.
- **Builder de footer** — toggle, texte libre, liste de liens.
- **Toolbar de preview** — switcher entre 8 scènes (4 pages user, 4 pages serveur), toggle dark/light, changement de breakpoint (mobile / tablet / desktop).
- **Upload d'assets** — drag-drop votre image de fond de login directement dans le studio.
- **Échappatoire CSS custom** — pour les 1% que les tokens ne couvrent pas.

Les settings sont stockés dans la table `settings` (cachés en Redis 1 h) et rendus comme variables CSS sur chaque page. Reset aux valeurs par défaut en un clic.

---

## 🧩 Marketplace de plugins

Peregrine a un vrai système de plugins — pas de simples overrides de thème ou hooks. Les plugins sont des mini-apps React + Laravel qui peuvent enregistrer routes, entrées de navigation, permissions, schémas de settings, et resources Filament.

<div align="center">
  <a href="docs/screenshots/plugins-marketplace.png"><img src="docs/screenshots/plugins-marketplace.png" alt="Capture Marketplace de plugins" width="900" /></a>
</div>

### Registre marketplace

Registre public hébergé sur GitHub : **[`Knaox/peregrine-plugins`](https://github.com/Knaox/peregrine-plugins)**. Peregrine fetch le dernier `registry.json` depuis `raw.githubusercontent.com`, liste les plugins disponibles dans **Admin → Plugins → Marketplace**, et gère **install / activate / deactivate / update / uninstall** en un clic.

### Livrés par défaut

- **Server Invitations** — invitez des joueurs sur vos serveurs par email avec permissions Pelican granulaires, éditez les invitations en attente et sous-users actifs, self-protection contre le verrouillage hors du compte, dispatch email queue-safe.

### Créez le vôtre

```bash
php artisan make:plugin my-plugin
```

Scaffold `plugins/my-plugin/` avec un service provider, un manifest, un dossier migrations et un point d'entrée React. Voir [`plugins/invitations/`](plugins/invitations/) pour l'implémentation de référence et [`docs/plugins.fr.md`](docs/plugins.fr.md) pour le guide développeur complet.

Lancez un registre privé en définissant `MARKETPLACE_REGISTRY_URL` dans votre `.env`.

---

## 🔌 Intégrations shop

Peregrine embarque un **Bridge** qui le connecte à votre plateforme de billing pour automatiser le provisioning, les upgrades, suspensions et suppressions de serveurs — pilotés par les événements de cycle de vie des abonnements. Trois modes mutuellement exclusifs, sélectionnés dans **Admin → Bridge Settings** :

| Mode | À utiliser pour | Comment ça marche |
|---|---|---|
| **Mode Paymenter** *(canal webhook Pelican)* | [**Paymenter**](https://paymenter.org) **et WHMCS** (via le [module Pelican-WHMCS](https://github.com/pelican-dev/whmcs-pelican)) — n'importe quel shop qui provisionne via l'API Pelican | Pelican forwarde ses événements webhook natifs (`/api/pelican/webhook`) à Peregrine, qui miroir le state des serveurs. Votre shop possède les plans, le billing et les emails. **Aucun code shop-spécifique nécessaire** — si Pelican déclenche les événements, Peregrine les attrape. |
| **Custom shop (Stripe)** | Votre propre billing (SaaSykit, fait maison, …) | Votre shop pousse les plans à Peregrine via requêtes signées HMAC (`/api/bridge/plans/upsert`). Stripe envoie les événements de paiement directement à Peregrine (`/api/stripe/webhook`). Période de grâce + suppression différée gérées. |
| **Désactivé** | Install standalone, pas de shop | Provisionnez les serveurs manuellement depuis le panel admin. |

Le setup de chaque mode est guidé par formulaire dans `/admin/bridge-settings`. Les pistes d'audit sont visibles dans `/admin/pelican-webhook-logs` (mode Paymenter / WHMCS) ou `/admin/bridge-sync-logs` (mode Custom shop). Docs publiques :

- [`docs/bridge-paymenter.fr.md`](docs/bridge-paymenter.fr.md) — setup Paymenter / WHMCS (Pelican ≥ 0.46 requis)
- [`docs/bridge-api.fr.md`](docs/bridge-api.fr.md) — API HMAC plan-sync pour shops custom
- [`docs/whmcs-oauth-setup.fr.md`](docs/whmcs-oauth-setup.fr.md) — optionnel : WHMCS comme provider d'identité OAuth2 pour le login Peregrine

---

## 🧾 Configuration

Tout se configure **dans le navigateur** pendant le wizard d'installation 7 étapes. La seule valeur `.env` à définir manuellement avant le premier boot est `APP_URL` (URL absolue de base pour les emails, callbacks OAuth et le check de mises à jour).

Le wizard écrit :

| Étape | Écrit |
|---|---|
| Base de données (testée live) | Variables `DB_*` |
| Compte admin | Premier user admin |
| Pelican (testé live) | `PELICAN_URL`, `PELICAN_ADMIN_API_KEY`, `PELICAN_CLIENT_API_KEY` |
| Mode auth | Local / OAuth2 / Providers sociaux, tous reconfigurables après install |
| Bridge (optionnel) | `BRIDGE_ENABLED`, `STRIPE_WEBHOOK_SECRET` |
| Récap | Passe `PANEL_INSTALLED=true`, lance les migrations |

Re-jouez le wizard à n'importe quel moment en remettant `PANEL_INSTALLED=false`. Les données existantes sont préservées.

Voir [`.env.example`](.env.example) pour la liste complète des variables reconnues.

---

## 🔄 Mises à jour

Admin → **About & Updates** affiche la version installée, vérifie GitHub pour la dernière release du panel (les releases plugins sont filtrées), et donne la commande exacte avec un bouton clipboard.

```bash
docker compose pull && docker compose up -d
```

Les migrations tournent automatiquement au démarrage du container quand `PANEL_INSTALLED=true`.

---

## 🗺️ Roadmap

Livré (dernier : `v1.0.0-alpha.1`) :

- ✅ SPA React joueur — parité complète gestionnaire de fichiers Pelican, console, SFTP, bases, backups, schedules, network
- ✅ Panel admin Filament 5 avec sync Pelican
- ✅ Theme Studio (Vagues 1 + 3 + parité + refinements)
- ✅ Marketplace de plugins + plugin Server Invitations
- ✅ Multi-provider auth (local / Shop / Paymenter / Google / Discord / LinkedIn) + 2FA TOTP
- ✅ Bridge webhooks Stripe + Pelican pour provisioning piloté par les abos
- ✅ Image Docker multi-arch publiée sur GHCR à chaque push

À venir :

- 🛠️ **Marketplace de thèmes** (Theme Studio Vague 4) — partage / import / export de thèmes en JSON, fork d'un preset, registre public sur le même pattern que la marketplace plugins
- 🛠️ **Token system v2** (Theme Studio Vague 2) — échelles de couleurs 50→950, type roles Material, 5 niveaux d'ombres, gradients nommés, patterns de fond par section
- 🛠️ **Polish & accessibilité** (Theme Studio Vague 5) — checker contraste WCAG live, simulateur daltonisme, éditeur Monaco pour le CSS custom, overrides par-utilisateur

Suivez le tout dans les [issues GitHub](https://github.com/Knaox/Peregrine/issues) et les [milestones](https://github.com/Knaox/Peregrine/milestones).

---

## 🐳 Tags d'image Docker

Multi-arch (`linux/amd64` + `linux/arm64`), publié à chaque push sur `main` et sur chaque tag de version.

| Tag | Produit par |
|---|---|
| `ghcr.io/knaox/peregrine:latest` | push sur `main` |
| `ghcr.io/knaox/peregrine:main-<sha>` | push sur `main` |
| `ghcr.io/knaox/peregrine:1.2.3` / `:1.2` / `:1` | tag `v*.*.*` |

Workflow : [`.github/workflows/docker.yml`](.github/workflows/docker.yml).

---

## 🧱 Stack technique

| Couche | Choix |
|---|---|
| Backend | PHP 8.3 · Laravel 13 · Filament 5 (Livewire 4) |
| Frontend | React 19 · TypeScript · Vite 6 · Tailwind 4 · TanStack Query · React Router 7 · Motion |
| Base de données | MySQL 8 (SQLite supporté) |
| Cache / Queue | Redis (fallback database) |
| Temps réel | Wings WebSocket + xterm.js |
| Container | Docker multi-stage · GHCR · nginx + php-fpm + supervisord |

---

## 🛠 Développer en local

```bash
composer install
pnpm install
pnpm run dev            # Vite HMR sur :5173
php artisan serve       # PHP sur :8000
php artisan queue:work  # emails + jobs de sync
```

Guide contributeur complet dans [`CONTRIBUTING.md`](CONTRIBUTING.md). Notes d'architecture interne dans [`docs/`](docs/) (auth, bridge, plugins, webhooks Pelican, setup queue worker).

---

## 🔒 Sécurité

Si vous trouvez une vulnérabilité, **n'ouvrez pas une issue publique**. Voir [`SECURITY.md`](SECURITY.md) pour la procédure de divulgation responsable.

---

## Licence

[MIT](LICENSE). Self-hostez librement, forkez, modifiez, revendez — pas de string attachée.

Peregrine est un projet indépendant et n'est **pas affilié à Pelican, Pterodactyl ou Laravel**.

---

## Crédits

Construit sur [Pelican](https://pelican.dev), [Laravel](https://laravel.com), [Filament](https://filamentphp.com), [React](https://react.dev), [Tailwind](https://tailwindcss.com), et la communauté open-source qui rend tout ça possible.
