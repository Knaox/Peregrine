# Authentification

Peregrine est livré avec une stack d'authentification flexible et multi-fournisseurs
que les administrateurs configurent entièrement depuis le panel Filament — pas
d'édition de `.env`, pas de redéploiement à la rotation des clés.

- [Vue d'ensemble](#vue-densemble)
- [Authentification locale](#authentification-locale)
- [Fournisseurs OAuth](#fournisseurs-oauth)
- [Authentification à deux facteurs](#authentification-à-deux-facteurs)
- [Forcer la 2FA pour les admins](#forcer-la-2fa-pour-les-admins)
- [Comptes liés](#comptes-liés)
- [Templates email personnalisés](#templates-email-personnalisés)

## Vue d'ensemble

Trois capacités indépendantes qui peuvent être combinées librement :

1. **Email/mot de passe local** — auth Laravel classique contre `users.password`.
2. **Fournisseurs OAuth** — Google, Discord, LinkedIn, ou tout serveur OAuth2
   générique (utile pour déléguer le login à un fournisseur d'identité existant).
3. **2FA (TOTP)** — mots de passe à usage unique basés sur le temps depuis
   n'importe quelle application d'authentification (Google Authenticator, Authy,
   1Password, …) + codes de récupération hashés en bcrypt.

Tout se pilote depuis **Panel admin → Paramètres → Auth & Sécurité**.

## Authentification locale

Activée par défaut. Deux toggles indépendants :

| Paramètre | Effet quand désactivé |
|---|---|
| **Login local activé** | Le formulaire email/mot de passe disparaît de `/login`. Les utilisateurs doivent se connecter via un fournisseur OAuth. |
| **Inscription locale activée** | La page `/register` et son lien disparaissent. Les nouveaux utilisateurs ne peuvent arriver que via OAuth. |

Une configuration typique pour les installs auto-hébergées garde les deux
activés. Une configuration où vous déléguez toute l'identité à un fournisseur
externe désactive les deux — alors seuls les boutons OAuth apparaissent sur la
page de login.

Le changement de mot de passe se fait depuis la page de profil de l'utilisateur.
Il est automatiquement désactivé pour les utilisateurs qui se sont inscrits via
OAuth et n'ont jamais défini de mot de passe local.

## Fournisseurs OAuth

Cinq fournisseurs sont supportés out of the box :

| Fournisseur | Driver | Notes |
|---|---|---|
| **Google** | `laravel/socialite` core | Nécessite un Client OAuth 2.0 Google Cloud avec la redirect URI que Peregrine vous indique |
| **Discord** | `socialiteproviders/discord` | Nécessite une application Discord Developer Portal |
| **LinkedIn** | `linkedin-openid` (flow OIDC moderne) | Nécessite une application LinkedIn OAuth 2.0 avec "Sign In with LinkedIn using OpenID Connect" |
| **Custom / Shop** | Driver custom in-tree | Tout serveur OAuth2 avec un endpoint de profil utilisateur retournant du JSON `{id, email, name}`. Utile pour déléguer à un backend SaaS existant. |
| **Paymenter** | Driver custom in-tree | Plateforme de billing open-source (paymenter.org). Agit comme IdP canonique. Configuration à URL de base unique ; `/oauth/authorize`, `/api/oauth/token`, `/api/me` sont dérivés. |

Les fournisseurs sont répartis en deux catégories :

- **IdP canoniques** (Shop, Paymenter) : agissent comme sources d'identité primaires — ils créent automatiquement les utilisateurs locaux à la première connexion, synchronisent l'email de l'utilisateur dans Pelican, et peuvent exposer une URL d'inscription sur la page de login. **Un seul IdP canonique peut être actif à la fois.** Filament bloque la sauvegarde quand les deux sont activés.
- **Fournisseurs sociaux** (Google, Discord, LinkedIn) : sign-in uniquement. Quand un IdP canonique est activé, les fournisseurs sociaux ne peuvent pas créer de nouveaux utilisateurs — ils sont destinés aux utilisateurs qui existent déjà (par ex. inscrits sur le Shop ou Paymenter), leur offrant un raccourci SSO.

### Ajouter un fournisseur

1. Créez une application OAuth côté fournisseur (Google Cloud Console,
   Discord Developer Portal, LinkedIn Developers, etc.). Utilisez la **redirect
   URI** que Peregrine affiche à côté de chaque fournisseur dans la page admin —
   correspondance exacte requise.
2. Ouvrez **Admin → Paramètres → Auth & Sécurité**, dépliez la section du
   fournisseur, collez votre Client ID et Client secret, basculez le toggle
   d'activation, sauvegardez.
3. Rechargez la page de login. Votre nouveau bouton apparaît.

Les Client secrets sont chiffrés au repos avec la clé d'application Laravel.

### Serveur OAuth2 custom (le fournisseur "Shop")

Le fournisseur générique "Shop" vous permet de déléguer le login à n'importe
quel serveur compatible OAuth2 — par exemple un SaaS existant qui possède déjà
vos comptes utilisateurs. Configurez quatre URLs dans sa section de paramètres :

- **Authorize URL** — où les utilisateurs sont redirigés pour consentir
- **Token URL** — où Peregrine échange le code contre un access token
- **User profile URL** — retourne du JSON `{id, email, name}` pour le Bearer
  token
- **Redirect URI** — lecture seule, à copier dans votre application OAuth côté
  serveur

Peregrine traite le Shop comme un fournisseur d'identité *canonique* quand il
est activé : l'inscription locale peut être fermée pour que les comptes
utilisateurs ne puissent provenir que du Shop, et les utilisateurs peuvent
toujours se connecter via n'importe quel fournisseur social lié (même email).

### Paymenter (alternative canonique open-source)

[Paymenter](https://paymenter.org/) est une plateforme de billing open-source
basée sur Laravel + Filament avec un serveur OAuth2 intégré (Laravel Passport).
Il joue le même rôle que le Shop dans Peregrine, pour les installations
auto-hébergées qui n'utilisent pas le Shop BiomeBounty. Étapes de configuration :

1. Dans votre admin Paymenter, allez dans **OAuth Clients → Create OAuth Client**.
   - Nom de l'application : ce que vous voulez (par ex. "Peregrine").
   - Redirect URL : copiez la redirect URI affichée dans la page Peregrine
     `Auth & Sécurité` à côté de Paymenter — correspondance exacte requise.
2. Paymenter affiche un Client ID + Client secret — collez-les dans Peregrine,
   ainsi que votre URL de base Paymenter (par ex. `https://billing.example.com`),
   puis activez le toggle et sauvegardez.
3. Le driver dérive `/oauth/authorize`, `/api/oauth/token` et `/api/me` à partir
   de l'URL de base. Le flow OAuth utilise le scope `profile` (le seul que
   Paymenter expose actuellement).

Les attributs utilisateur Paymenter (`first_name`, `last_name`, `email`,
`email_verified_at`) sont mappés sur les utilisateurs Peregrine
automatiquement. Le timestamp `email_verified_at` conditionne l'auto-linking par
email — un utilisateur Paymenter qui n'a pas confirmé son email ne peut pas se
connecter à Peregrine.

Shop et Paymenter sont **mutuellement exclusifs** — Filament bloque la
sauvegarde quand les deux sont activés. Choisissez celui qui correspond à votre
install.

#### Provisionner des serveurs de jeux depuis les achats Paymenter

L'intégration Peregrine documentée ici couvre **uniquement l'identité** — les
utilisateurs se connectent à Peregrine via SSO Paymenter. Pour réellement
**créer / suspendre / résilier des serveurs de jeux** depuis les achats
Paymenter, installez côté Paymenter :

1. L'**[extension Pelican-Paymenter](https://builtbybit.com/resources/pelican-paymenter.63526/)** —
   le bridge de provisioning de serveurs qui transforme les produits Paymenter en
   serveurs Pelican (gère install, suspend, unsuspend, terminate via la
   Application API Pelican).
2. Activez le **bridge mode** sur l'extension.
3. Activez les **webhooks** pour que les events de cycle de vie (achat,
   renouvellement, annulation) atteignent Pelican en temps réel.

Sans cette extension, Paymenter ne fournit que le login — il ne peut pas pousser
les specs de serveurs vers Pelican par lui-même. Le module bridge côté Peregrine
qui mirror ce flow de billing dans la table de serveurs propre à Peregrine est
planifié (voir "P3 — Bridge plugin" dans `CLAUDE.md`) ; pour l'instant,
configurez le provisioning entièrement côté Paymenter.

### Liaison de compte par email

Quand un utilisateur se connecte via un fournisseur OAuth pour la première fois
et qu'aucun compte local ne correspond, le comportement dépend de l'état de
l'IdP canonique :

- **Aucun IdP canonique activé** — Peregrine crée un nouveau compte local et
  lie l'identité (peu importe le fournisseur).
- **IdP canonique activé (Shop ou Paymenter)** — Peregrine refuse la création
  et invite l'utilisateur à s'inscrire d'abord sur l'IdP canonique, puis à
  revenir sur Peregrine. Les fournisseurs sociaux (Google/Discord/LinkedIn) sont
  en sign-IN uniquement dans ce mode. L'IdP canonique lui-même contourne cette
  règle — il EST le canal d'inscription.

Quand un compte local existe déjà avec le même email, Peregrine lie
automatiquement l'identité **uniquement si le fournisseur a marqué l'email comme
vérifié** (Google `email_verified`, Discord `verified`, LinkedIn
`email_verified`, Paymenter `email_verified_at`, Shop implicitement de
confiance). Sinon le login est rejeté avec un message indiquant à l'utilisateur
de se connecter avec sa méthode primaire, puis de lier le fournisseur depuis sa
page de profil. Cela empêche la prise de contrôle de compte via un compte
fournisseur contrôlé par un attaquant utilisant l'email de quelqu'un d'autre.

### Collision d'email sur login canonique

Quand un login d'IdP canonique arrive avec un nouvel email (l'utilisateur l'a
changé côté IdP), Peregrine tente de propager le changement vers la ligne
utilisateur locale + le compte Pelican correspondant. Si un autre utilisateur
local possède déjà cet email (typique quand un compte en double existe), la
synchro est **skippée gracieusement** — le login réussit quand même avec
l'ancien email local de l'utilisateur, et un warning est loggé pour qu'un admin
puisse fusionner le doublon. Cela évite que les utilisateurs soient verrouillés
hors de leur compte par une ligne en double obsolète.

## Authentification à deux facteurs

Tout utilisateur peut activer la 2FA depuis **Profil → Sécurité**. Le flow :

1. Scannez un QR code avec n'importe quelle application TOTP (Google
   Authenticator, Authy, 1Password, Microsoft Authenticator, …).
2. Saisissez le code à 6 chiffres pour confirmer.
3. Sauvegardez les 8 codes de récupération à usage unique affichés — ce sont la
   seule façon de revenir si l'application d'authentification est perdue.

À la connexion suivante, après une étape de mot de passe (ou OAuth) réussie,
l'utilisateur arrive sur une page de challenge qui demande le code à 6 chiffres.
Un lien "Utiliser un code de récupération" leur permet de basculer sur l'un des
codes sauvegardés — chaque code consommé est immédiatement invalidé.

Les codes de récupération peuvent être régénérés à tout moment depuis la même
page de profil — la génération invalide tous les codes précédents.

### Comment l'état du challenge est stocké

Entre l'étape mot de passe/OAuth et le challenge TOTP, Peregrine stocke un état
pending de courte durée dans Redis (TTL 5 minutes) clé par un UUID. Le
navigateur ne reçoit pas de cookie de session avant que le challenge réussisse —
c'est intentionnel, cela maintient le flow safe à travers plusieurs onglets de
navigateur et après un reload SPA.

Si l'utilisateur prend trop de temps ou échoue trop de fois, il est renvoyé sur
le formulaire de login.

## Forcer la 2FA pour les admins

Activez **Require 2FA for admins** dans la même page admin.

Quand activé :

- Tout admin sans 2FA configurée qui essaie d'atteindre un endpoint admin reçoit
  une réponse **403** avec une URL de redirection pointant vers
  `/2fa/setup?enforced=1`.
- L'intercepteur HTTP frontend récupère cette réponse et navigue automatiquement
  le navigateur vers la page de setup forcée, pour que les admins ne voient pas
  une erreur brute — ils arrivent sur une page focalisée qui leur dit "votre
  administrateur exige la 2FA" et les guide à travers le setup.
- Une fois configurée, l'admin peut accéder à `/admin` et aux routes API admin.

**Attention** : l'action de sauvegarde Filament refuse d'appliquer le toggle si
*vous* (l'admin actuellement connecté) n'avez pas encore la 2FA — sinon vous
vous verrouilleriez hors de votre propre panel à la requête suivante.

## Comptes liés

Depuis **Profil → Sécurité → Fournisseurs de connexion liés**, les utilisateurs
peuvent :

- Voir quels fournisseurs sont actuellement liés (avec l'email côté fournisseur)
- Lier un nouveau fournisseur en cliquant sur "Lier" (redirige à travers le flow
  OAuth)
- Délier un fournisseur avec le bouton "Délier"

Le bouton de déliaison est automatiquement désactivé si le retirer laisserait
l'utilisateur sans aucun moyen de se connecter — c'est-à-dire quand
l'utilisateur n'a pas de mot de passe défini ET une seule identité liée.
Définissez d'abord un mot de passe ou liez un second fournisseur avant de
délier.

Quand un admin désactive un fournisseur dans la page Auth & Sécurité, Peregrine
compte combien d'utilisateurs dépendent de ce fournisseur comme leur *unique*
méthode de connexion et bloque la sauvegarde avec un avertissement si ce
décompte est non nul. Un toggle explicite "Je comprends le risque" dans la
section Safety permet à l'admin de passer outre, mais jamais silencieusement.

## Templates email personnalisés

Toutes les notifications liées à la sécurité sont éditables depuis **Admin →
Paramètres → Email Templates** avec sujet + corps HTML en anglais et français :

- 2FA activée
- 2FA désactivée
- Codes de récupération régénérés
- Fournisseur OAuth lié
- Fournisseur OAuth délié

Variables disponibles dans chaque template :

- `{name}` — nom d'affichage de l'utilisateur
- `{server_name}` — nom configuré du panel (le même que votre nom d'application)
- `{timestamp}` — quand l'event s'est produit (timezone serveur)
- `{ip}` — adresse IP de la requête
- `{user_agent}` — identifiant navigateur/appareil (tronqué)
- `{manage_url}` — lien vers la page des paramètres de sécurité de l'utilisateur

Les templates de liaison/déliaison OAuth exposent aussi `{provider}` avec le nom
lisible du fournisseur (localisé dans la langue de l'utilisateur).

Laisser un champ vide ou identique au défaut conserve le contenu intégré — le
override admin ne s'active que quand le contenu diffère réellement. Un bouton
"Réinitialiser aux défauts" efface tous les overrides en un clic.

## Voir aussi

- [Configuration](configuration.md) — vue d'ensemble des variables d'env et paramètres
- [Plugins](plugins.md) — étendre Peregrine avec vos propres modules
