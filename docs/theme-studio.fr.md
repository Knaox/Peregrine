# Theme Studio

Le Theme Studio est le point d'entrée principal pour personnaliser le
branding et l'apparence de votre panel Peregrine. C'est un éditeur React
plein écran sur `/theme-studio` avec preview live, accessible depuis
**Admin → Paramètres → Thème → "Open Theme Studio"**.

Cette doc s'adresse aux opérateurs. Pour contribuer au code du studio
lui-même, voir la section `## Theme Studio` du `CLAUDE.md` du projet.

- [Démarrage rapide](#démarrage-rapide)
- [Sections du studio](#sections-du-studio)
- [Polices personnalisées](#polices-personnalisées)
- [CSS personnalisé — ce qui est autorisé](#css-personnalisé--ce-qui-est-autorisé)
- [Sauvegardes, export et import](#sauvegardes-export-et-import)
- [Vider les caches après une édition SQL directe](#vider-les-caches-après-une-édition-sql-directe)
- [Dépannage](#dépannage)

## Démarrage rapide

1. Ouvre `/admin/theme-settings` et clique **Open Theme Studio**. Le
   studio s'ouvre dans un nouvel onglet.
2. Choisis un **preset** dans la première section (Orange, Amber, Crimson,
   Emerald, Indigo, Violet, Slate). La preview à droite se met à jour
   instantanément.
3. Ajuste les valeurs — couleurs, largeurs sidebar, template de login,
   intensité hover, CSS personnalisé. Les changements sont live dans la
   preview mais **pas persistés** tant que tu n'as pas cliqué **Publier**.
4. Utilise la barre au-dessus de la preview pour switcher de **scène**
   (dashboard, vue serveur, console, fichiers, login, register…), de
   **mode** (sombre / clair), et de **breakpoint** (mobile / tablet /
   desktop). Teste toutes les scènes qui t'importent avant de publier.
5. Clique **Publier** pour valider. Tout le panel récupère les changements
   sans redémarrage (le cache est invalidé automatiquement).
6. Si tu regrettes un changement, clique **Discard** pour rétablir l'état
   publié. **Reset to defaults** est l'option nucléaire — voir
   [Sémantique du Reset](#sémantique-du-reset) ci-dessous.

### Sémantique du Reset

Le bouton Reset est volontairement difficile à déclencher :

- Il ouvre un modal avec des warnings concrets (volume de CSS personnalisé,
  présence d'images de login uploadées).
- Tu dois taper la chaîne littérale `RESET` (en majuscules) pour activer
  le bouton destructeur.
- L'action est irréversible via l'UI. **Lance toujours `php artisan
  theme:export` avant** si tu veux conserver la config en cours.

## Sections du studio

| Section | Ce qu'elle contrôle |
|---|---|
| **Preset de marque** | Swap one-click du jeu de couleurs (primary / secondary / accents). Bascule sur "Custom" dès que tu modifies une couleur individuellement. |
| **Couleurs de marque** | Les 4 couleurs principales (primary, primary hover, secondary, ring). |
| **Couleurs de statut** | Sémantiques danger / warning / success / info / suspended / installing. |
| **Surfaces & bordures** | Background, variantes de surface, bordures, hiérarchie de texte. |
| **Typographie** | Police + radius de base + densité (compact / comfortable / spacious). |
| **Layout shell** | Hauteur du header, sticky vs static, alignement, max-width container, padding de page. |
| **Sidebar (in-server)** | Largeurs classique / rail / mobile, intensité du blur, mode flottant. |
| **Nav sidebar** | Position, style, visibilité et ordre par entrée (éditeur legacy). |
| **Cards (liste serveurs)** | 14 champs : visibilité, effet hover, tri/groupe, nombre de colonnes par breakpoint. |
| **Templates de login** | Centered / Split / Overlay / Minimal, plus image de fond / blur / pattern. Mode carousel pour rotation multi-images. |
| **Overrides par page** | Console fullwidth / Files fullwidth / Dashboard 4 colonnes étendu. |
| **Footer** | Toggle, texte libre, repeater de liens `{label, url}`. |
| **Refinements** | Vitesse d'animation, scale au hover, épaisseur de bordure, blur glass, échelle de taille de police, pattern de fond app. |
| **CSS personnalisé** | Textarea libre injectée dans un seul `<style>` global. Sanitisée — voir plus bas. |

## Polices personnalisées

Le dropdown de police propose une liste curée (Inter, Plus Jakarta Sans,
Space Grotesk, Sora, Outfit, IBM Plex Sans, Manrope, JetBrains Mono,
system-ui). Pour utiliser autre chose :

1. Tape le nom exact de la famille Google Fonts dans l'input "Other" du
   dropdown. Le studio accepte jusqu'à 64 caractères.
2. Au **Publish**, `ThemeProvider` injecte une balise `<link
   rel="stylesheet" href="https://fonts.googleapis.com/css2?family=...">`
   dans le head. Les noms multi-mots sont URL-encodés automatiquement.
3. Pour self-host une police au lieu de passer par Google Fonts : place
   la déclaration `@font-face` dans **CSS personnalisé**, pointe `src:
   url(...)` vers un chemin sous `/storage/...` (tu peux uploader les
   fichiers de police via SFTP). Puis renseigne le nom de la famille
   dans le dropdown.

> Précision : les requêtes externes vers `https://fonts.googleapis.com/...`
> sont autorisées car ce sont des balises `<link>` injectées par
> Peregrine — la sanitisation du CSS personnalisé bloque uniquement les
> `@import` et `url()` externes *à l'intérieur* du textarea.

## CSS personnalisé — ce qui est autorisé

Le CSS personnalisé est rendu via un seul `<style>`. Pour empêcher
l'exfiltration de données via `Referer` + cookies (et les vecteurs IE
legacy d'exécution de script), les patterns suivants sont **rejetés au
save** avec une erreur 422 :

| Pattern | Exemple rejeté | Raison |
|---|---|---|
| `@import` | `@import url("https://evil.tld/x.css");` | Déclencherait un fetch cross-origin authentifié à chaque rendu. |
| `url(...)` externe | `background: url("https://evil.tld/x.png");` | Même risque ; le `Referer` exposerait l'URL du panel. |
| `url(//...)` protocol-relatif | `background: url("//evil.tld/x.png");` | Même risque sous le scheme actif. |
| `expression(...)` | `width: expression(alert(1));` | Exécution JS legacy IE in-CSS. |
| `behavior:` | `behavior: url(xss.htc);` | Comportements binaires legacy IE. |
| URIs `javascript:` | `cursor: url(javascript:alert(1));` | Tentative directe d'exécution. |
| Balises `<script` | `<script>...</script>` | Accident de copier-coller HTML dans la textarea. |

Contournements :
- Self-host l'asset (dépose-le sous `storage/app/public/branding/...`) et
  référence-le en `url("/storage/branding/ton-asset.png")`.
- Pour les polices externes, passe par le dropdown (qui utilise un
  `<link>`, hors de la textarea sanitisée).

## Sauvegardes, export et import

Le studio n'a pas encore d'export UI. Utilise le CLI Artisan :

```bash
# Snapshot du thème courant vers un fichier JSON
php artisan theme:export --output=mon-theme.json

# Appliquer un thème exporté à cette install
php artisan theme:import mon-theme.json
# ou sauter le dry-run / la confirmation :
php artisan theme:import mon-theme.json --force
```

Le payload JSON contient tous les settings `theme_*` + card config +
sidebar config + footer links. **Les images uploadées sont référencées
par leur chemin, pas embarquées.** Un thème exporté depuis une install
n'apporte pas son image de fond de login — copie le fichier sous
`storage/app/public/branding/` séparément si tu veux une vraie
portabilité.

## Vider les caches après une édition SQL directe

Si tu édites la table `settings` directement (SQL manuel, session
tinker, restauration de backup), le studio ne reflètera pas les
nouvelles valeurs tant que la couche de cache n'est pas vidée. Les clés
concernées vivent dans Redis (ou le driver fichier en dev) :

- `theme_full` — structure de thème résolue (TTL 1h)
- `theme_css_vars` — variables CSS émises (TTL 1h)
- `theme_mode_variants` — payloads `dark` / `light` pré-calculés (TTL 1h)

Le plus simple :

```bash
php artisan cache:clear
```

Si tu veux uniquement invalider les clés de thème sans nuker le reste :

```bash
php artisan tinker --execute="\
  Cache::forget('theme_full'); \
  Cache::forget('theme_css_vars'); \
  Cache::forget('theme_mode_variants');"
```

Après le flush, la prochaine requête reconstruit le cache depuis la DB.

## Dépannage

### « Voile blanc sur l'image de l'egg en mode light »

Hard refresh du navigateur (`Cmd+Shift+R` / `Ctrl+Shift+R`) et vérifie
que les trois clés de cache de thème sont vidées. L'overlay du banner
est hardcoded en sombre dans les deux modes (convention Steam /
Spotify) — si tu vois un voile blanc, ton client affiche un payload
`theme_mode_variants` périmé.

### « Le carousel de login affiche un panneau noir »

Un des chemins dans `theme_login_background_images` n'existe plus sur
le disque (fichier supprimé manuellement, ou déplacé). Le carousel
précharge chaque image au mount et retire silencieusement les chemins
cassés de la rotation. Si tous les chemins échouent, c'est le gradient
de fallback qui s'affiche.

Pour reconstruire la liste : ouvre le studio, retire l'entrée cassée
dans la section carousel, re-publie.

### « J'ai édité le thème mais ma preview ne suit pas »

Tout consommateur doit lire le thème via `useResolvedTheme()` (le
Context exposé par `ThemeProvider`). Un consommateur qui fait son
propre `useQuery(['theme'])` voit la réponse cachée du serveur et rate
les updates postMessage de l'iframe studio. Si tu as écrit un composant
custom qui affiche des valeurs themées, audite-le pour ce piège.

### « Sauvegarder depuis `/admin/theme-settings` (Filament) écrase ma config Vague 3 »

Ce bug existait avant mai 2026 et est désormais corrigé : la boucle de
save Filament ignore les keys absentes du form schema au lieu de les
nuller. Si tu es sur un déploiement plus ancien, upgrade — ou utilise
**uniquement le Theme Studio** (`/theme-studio`), qui a toujours été
défensif sur ce point.

### « Deux admins se marchent dessus en sauvegardant »

L'endpoint de save utilise un optimistic lock via `theme_revision`. Si
deux admins ouvrent le studio en même temps et publient tous les deux,
le second reçoit un `409 Conflict` et un bandeau l'invitant à recharger.
Le revision integer est incrémenté à chaque save (studio + Filament +
reset).

### « J'ai rollback la migration ; mon thème a disparu »

Le `down()` de la migration de seed est volontairement no-op — un
rollback ne doit pas détruire la config admin. Si tu veux vraiment un
wipe propre, truncate la table `settings` directement, ou lance `php
artisan migrate:fresh --seed`.
