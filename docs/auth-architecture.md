# Auth architecture — internal notes

> Scope : contributeurs core qui touchent à l'auth (login, 2FA, OAuth, admin
> bypass, audit). UX-first docs for end users go in the public README.

## 1. Model mental

Trois dimensions indépendantes — l'admin les combine librement dans
`/admin/auth-settings` :

- **Login local** (`auth_local_enabled` + `auth_local_registration_enabled`) —
  email/password classique Laravel.
- **OAuth providers** (`auth_shop_enabled` + `auth_providers.*.enabled`) —
  Shop (canonique, custom Socialite driver), Google / Discord / LinkedIn
  (Socialite core + socialiteproviders/discord).
- **2FA TOTP** (`auth_2fa_enabled` + `auth_2fa_required_admins`) — Filament 5
  native MFA columns (`app_authentication_secret` + `app_authentication_recovery_codes`),
  challenge state en Redis 5 min.

Il n'y a **plus de mode binaire** `AUTH_MODE=local|oauth` — cette clé `.env`
a disparu en Étape C.

## 2. Gate::before — whitelist admin

`AuthServiceProvider::boot()` :

```php
Gate::before(function (User $user, string $ability, array $arguments = []) {
    if (! $user->is_admin) return null;
    $resource = $arguments[0] ?? null;
    if ($resource instanceof \App\Models\Server) return true;
    return null;
});
```

**Règle** : n'ajoute JAMAIS un model sensible à cette whitelist (billing,
session, API token, 2FA secret, personal data). Un admin a un besoin
opérationnel légitime sur les **serveurs** — il n'a pas ce besoin sur le
wallet d'un user.

Ajouter une entrée = PR dédiée avec justification sécurité + test
dédié `ServerPolicyTest::gate_before_scoped` étendu.

**Complémentaire** : `User::hasServerPermission()` applique le même bypass
pour les chemins qui n'utilisent pas le Gate (ex. invitations plugin).
Même règle : scope strictement serveur.

## 3. Ajouter un nouveau provider OAuth

Trois niveaux selon le provider :

### 3a. Provider Socialite core (Twitter, GitHub, Facebook…)

1. Étendre `AuthProviderRegistry::SUPPORTED` avec le nouvel ID.
2. Étendre `AuthProviderRegistry::SOCIALITE_DRIVER` (mapping ID logique →
   driver Socialite).
3. Ajouter le provider aux defaults de `AuthSettingsSeeder`.
4. Ajouter une clé de check `email_verified` dans `SocialUserMatcher::isEmailVerifiedByProvider()`.
5. Ajouter une entrée dans `/admin/auth-settings` en appelant
   `AuthSettingsFormSchema::socialProvider()`.
6. Ajouter la trad dans `auth.providers.*` (EN + FR) + icône SVG dans
   `SocialLoginButtons.tsx` (`ICON` + `COLOR` maps).
7. Ajouter la route dans `routes/api.php` — le regex `where('provider', 'shop|google|discord|linkedin|newprovider')`.
8. Tests : `SocialAuthTest` — matcher_rejects_{provider}_without_verified.

### 3b. Provider SocialiteProviders (ex: Instagram, Spotify)

Identique à 3a, plus :
- `composer require socialiteproviders/newprovider`
- `SocialAuthServiceProvider::boot()` — `Event::listen(SocialiteWasCalled::class, [Provider\NewProviderExtendSocialite::class, 'handle'])`

### 3c. Provider custom (comme Shop)

1. Classe étendant `Laravel\Socialite\Two\AbstractProvider` dans
   `app/Services/Auth/` (modèle : `ShopSocialiteProvider`).
2. `Socialite::extend('myprovider', fn() => ...)` dans
   `SocialAuthServiceProvider::boot()`.
3. Reste : idem 3a.

## 4. Plan §S-references (les garde-fous qui ne bougent pas)

| Code | Contrainte | Emplacement |
|---|---|---|
| S1 | `email_verified` requis pour auto-linking | `SocialUserMatcher::isEmailVerifiedByProvider()` |
| S2 | Recovery codes = bcrypt hash, pas encrypt | `TwoFactorService` + traits Filament MFA |
| S3 | Challenge pending state en Redis (UUID, 5 min TTL) | `TwoFactorChallengeStore` |
| S4 | Rate limits explicites | `AppServiceProvider::boot()` |
| S5 | `Gate::before` whitelist scopée | `AuthServiceProvider::boot()` |
| S6 | Audit log via event dispatch, pas middleware | `AdminActionPerformed::dispatchIfCrossUser()` + `LogAdminAction` |
| S7 | Unlink dernière méthode bloqué | `SocialAuthService::unlink()` |
| S8 | Désactivation provider bloquée si users exclusifs | `AuthProviderRegistry::providerHasExclusiveUsers()` + `AuthSettings::save()` |
| S9 | Notifications queued sur events sensibles | `App\Notifications\*` + listeners |

Tout test qui casse une de ces propriétés est un régression — fix la cause
racine, ne skip jamais le test.

## 5. Email templates

5 templates d'auth éditables depuis `/admin/email-templates` :

| Template ID | Dispatch | Variables custom |
|---|---|---|
| `auth_2fa_enabled` | `TwoFactorEnabled` event | — |
| `auth_2fa_disabled` | `TwoFactorDisabled` event | — |
| `auth_2fa_recovery_regenerated` | `RecoveryCodesRegenerated` event | — |
| `auth_social_linked` | `OAuthProviderLinked` event | `{provider}` |
| `auth_social_unlinked` | `OAuthProviderUnlinked` event | `{provider}` |

Variables communes : `{name}`, `{server_name}`, `{timestamp}`, `{ip}`,
`{user_agent}`, `{manage_url}`. `{app_name}` reste comme alias rétro-compat
de `{server_name}`.

Contrat : `MailTemplateService` lit l'override admin en settings table,
sinon fallback sur `MailTemplateRegistry` defaults. Sauvegarde admin
n'écrit que les valeurs qui diffèrent du default (purge auto sinon).

## 6. Colonnes legacy

`users.oauth_provider_legacy` + `oauth_id_legacy` ont été droppées en
Étape E (migration `000022`). Dump forensic avant drop :
`php artisan auth:backup-oauth-legacy`.

La table `oauth_identities` est maintenant la seule source de vérité pour
les identités OAuth.
