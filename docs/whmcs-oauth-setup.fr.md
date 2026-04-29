# OAuth WHMCS — Guide de configuration

Cette page décrit comment câbler votre installation WHMCS comme
**fournisseur d'identité OpenID Connect canonique** pour Peregrine. Une
fois configuré, vos clients cliquent sur *Se connecter avec WHMCS* sur
la page de login Peregrine, sont redirigés vers votre système de
facturation WHMCS, s'authentifient là-bas, puis reviennent sur Peregrine
avec une session active — pas de second mot de passe à maintenir.

> ℹ️ Cette page traite uniquement de **l'authentification** (login / SSO).
> Elle est indépendante du mode *Bridge — orchestrateur webhook* qui
> mirror l'état des serveurs Pelican depuis un workflow piloté par WHMCS.
> Vous pouvez activer l'un, l'autre, les deux ou aucun. Voir
> `/docs/bridge-webhook-orchestrator` pour la partie provisioning.

## Comment ça marche

WHMCS embarque un fournisseur d'identité OpenID Connect natif depuis la
version 8.5 (*Configuration → System Settings → OpenID Connect*). Il
expose les endpoints standards OIDC sous `/oauth/*` et supporte les
scopes `openid profile email`. Peregrine les consomme via un driver
Socialite custom (`WhmcsSocialiteProvider`) — même pattern déjà utilisé
pour Paymenter et le fournisseur Shop OAuth.

```
   1. Le client clique « Se connecter avec WHMCS »
                  │
                  ▼
   /api/auth/social/whmcs/redirect (Peregrine)
                  │
                  ▼
   <whmcs>/oauth/authorize.php  (WHMCS affiche sa page de login)
                  │
                  ▼
   /api/auth/social/whmcs/callback (Peregrine, avec code d'auth)
                  │
                  ├─► <whmcs>/oauth/token.php       (échange code → access token)
                  ▼
   <whmcs>/oauth/userinfo.php (récupère sub / email / nom)
                  │
                  ▼
   Session Peregrine  ✓  (crée automatiquement l'utilisateur local au 1er login)
```

## Prérequis

| Composant | Version minimale | Pourquoi |
|---|---|---|
| WHMCS | 8.5+ | Fournisseur OpenID Connect intégré |
| HTTPS sur WHMCS | requis | OIDC impose SSL sur tous les flux OAuth |
| Peregrine | actuel | Embarque le driver Socialite `whmcs` |

## 1. Générer les credentials OpenID WHMCS

Dans votre admin WHMCS :

1. Ouvrez **Configuration → System Settings → OpenID Connect**.
2. Cliquez **Generate New Client API Credentials**.
3. Remplissez :
   - **Name** : `Peregrine SSO` (ou tout libellé qui vous aide à
     l'identifier).
   - **Description** : `Single sign-on pour le panel de jeu Peregrine`.
   - **URL** : l'URL publique de votre installation Peregrine.
   - **Authorized redirect URIs** : exactement
     `https://VOTRE-DOMAINE-PEREGRINE/api/auth/social/whmcs/callback`
4. Cliquez **Generate Credentials**.
5. WHMCS affiche un **Client ID** et un **Client secret**. Copiez-les
   en lieu sûr — le secret n'est affiché qu'une seule fois.

> ⚠️ Le redirect URI doit correspondre **EXACTEMENT**, slash final inclus.
> WHMCS fait une comparaison stricte de chaîne ; `https://x.com/cb` et
> `https://x.com/cb/` ne sont pas la même URL.

## 2. (Optionnel) Activer l'endpoint de discovery

WHMCS expose son document de configuration OIDC à
`/oauth/openid-configuration.php`. Peregrine n'en a pas besoin (les
quatre paths d'endpoints sont dérivés de l'URL de base), mais si vous
voulez que WHMCS soit découvrable par d'autres clients OIDC sur le
chemin standard `/.well-known/openid-configuration`, ajoutez ceci dans
le `.htaccess` racine WHMCS (Apache) ou la rewrite Nginx équivalente :

```apache
RewriteRule ^.well-known/openid-configuration ./oauth/openid-configuration.php [L,NC]
```

## 3. Configurer Peregrine

Ouvrez l'admin Peregrine → **Auth & Sécurité** → onglet **WHMCS** :

1. Activez **Activer WHMCS comme fournisseur d'identité**.
2. **URL de base WHMCS** : l'URL racine de votre installation WHMCS
   (sans slash final). Exemple : `https://billing.example.com`.
   Peregrine en dérive `/oauth/authorize.php`, `/oauth/token.php` et
   `/oauth/userinfo.php` automatiquement — vous n'avez pas à les
   remplir séparément.
3. **Client ID** : collez la valeur de l'étape 1 WHMCS.
4. **Client secret** : collez le secret de l'étape 1 WHMCS.
5. **Redirect URI** : laissez la valeur par défaut
   (`https://VOTRE-DOMAINE-PEREGRINE/api/auth/social/whmcs/callback`),
   sauf si une particularité de reverse proxy impose un host différent.
   La valeur ici doit correspondre à ce que vous avez tapé dans l'étape
   1 WHMCS.
6. **URL de la page d'inscription WHMCS** *(optionnel)* : si vous
   voulez qu'un lien « Créer un compte sur le site de facturation »
   apparaisse sur la page de login Peregrine, remplissez l'URL
   d'inscription WHMCS (ex.
   `https://billing.example.com/register.php`). Laisser vide pour
   conserver uniquement le formulaire d'inscription local.
7. **Logo personnalisé pour le bouton** *(optionnel)* : SVG / PNG /
   JPEG / WebP / ICO, carré recommandé (max 1 Mo). Remplace l'icône
   par défaut sur le bouton « Se connecter avec WHMCS ».
8. Cliquez **Enregistrer les paramètres**.

> Mutuellement exclusif : un seul **fournisseur d'identité canonique**
> peut être actif à la fois. Activer WHMCS alors que Shop ou Paymenter
> est déjà actif fait échouer la sauvegarde avec un message clair.
> Désactivez l'autre d'abord.

## 4. Tester le flux

1. Déconnectez-vous de Peregrine (ou ouvrez une fenêtre de navigation
   privée).
2. Sur la page de login, vous devriez voir un bouton **Se connecter
   avec WHMCS** à côté du formulaire email/mot de passe local. Le
   bouton utilise votre logo personnalisé si vous en avez uploadé un.
3. Cliquez dessus. Vous êtes redirigé vers la page de login WHMCS.
4. Saisissez vos credentials client WHMCS. Approuvez l'écran de
   consentement OAuth (la première fois uniquement).
5. Vous revenez sur Peregrine, connecté. Si votre email ne correspondait
   à aucun utilisateur local, Peregrine en crée un automatiquement
   (sans mot de passe — seul le login via WHMCS fonctionne pour cet
   utilisateur).

## 5. Dépannage

| Symptôme | Diagnostic | Correction |
|---|---|---|
| `redirect_uri_mismatch` depuis WHMCS | Le redirect URI tapé dans Peregrine ne correspond pas à celui enregistré dans WHMCS | Re-collez les deux URIs côte à côte. Attention au `http` vs `https`, aux slashs finaux, et à la casse du host. |
| 401 depuis `/oauth/token.php` après auth | Mauvais Client ID ou Client Secret | Re-collez les deux champs dans Peregrine. Le secret est chiffré au repos ; sauvegarder vide conserve la valeur stockée. |
| `email_verified` toujours faux | Certaines installations WHMCS n'exposent pas la claim `email_verified` | Comportement par design : Peregrine refuse de lier automatiquement un email non vérifié à un compte local existant. Soit vérifiez l'email dans WHMCS, soit demandez à l'utilisateur de se connecter d'abord via mot de passe local pour amorcer le compte. |
| Erreur « Plusieurs IdP canoniques » | Shop et WHMCS (ou Paymenter et WHMCS) sont activés en même temps | Un seul fournisseur canonique peut être actif. Désactivez l'autre. |
| Le bouton de login n'apparaît pas | Le frontend cache la liste des providers pendant 60 s | Attendez, ou faites un hard-reload (Cmd+Shift+R). |

## 6. Liaison et déliaison

Un utilisateur authentifié via WHMCS peut aussi avoir un mot de passe
local (via la page **Compte → Sécurité** dans Peregrine) et des
identités sociales additionnelles (Google, Discord, …). La table
`oauth_identities` enregistre chaque liaison pour que la logique
d'audit / anti-verrouillage reste cohérente.

Désactiver le fournisseur WHMCS dans l'admin Peregrine conserve les
lignes `oauth_identities.provider = 'whmcs'` en DB mais bloque les
nouveaux logins depuis WHMCS. Si un utilisateur n'a que WHMCS comme
identité (pas de mot de passe, pas d'autre fournisseur lié), la section
*Sécurité anti-verrouillage* avertit l'admin avant la sauvegarde —
acceptez explicitement le risque pour continuer.

## 7. Compatibilité ascendante

Le nouveau provider `whmcs` a ses propres clés de settings
(`auth_whmcs_enabled`, `auth_whmcs_config`) et n'entre jamais en collision
avec les configurations Shop / Paymenter existantes. Migrer vers WHMCS
plus tard, ou revenir en arrière, c'est juste basculer les radios — pas
de migration de données nécessaire.
