<?php

namespace App\Services\Auth;

use App\Events\RecoveryCodesRegenerated;
use App\Events\TwoFactorDisabled;
use App\Events\TwoFactorEnabled;
use App\Models\User;
use App\Services\SettingsService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;

class TwoFactorService
{
    /**
     * Window for TOTP verification — 8 × 30s either side (matches Filament default).
     * Tolerates clock drift on the user's phone without widening too far.
     */
    private const CODE_WINDOW = 8;

    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly Google2FAQRCode $google2faWithQr,
        private readonly SettingsService $settings,
    ) {}

    public function generateSecret(User $user): string
    {
        return $this->google2fa->generateSecretKey(16);
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            $this->brandName(),
            $user->getAppAuthenticationHolderName(),
            $secret,
        );
    }

    /**
     * Returns a base64 data:image/svg+xml URI for the QR code. Always uses the
     * SVG renderer so no imagick/GD extension is required.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $inline = $this->google2faWithQr->getQRCodeInline(
            $this->brandName(),
            $user->getAppAuthenticationHolderName(),
            $secret,
        );

        if (class_exists(Writer::class) && class_exists(ImageRenderer::class) && ! extension_loaded('imagick')) {
            return 'data:image/svg+xml;base64,'.base64_encode($inline);
        }

        return $inline;
    }

    /**
     * Validates the TOTP code for the unconfirmed secret, then persists the
     * secret + freshly-generated recovery codes + timestamp. Returns the
     * plaintext recovery codes (display-once — never persisted in plaintext).
     *
     * @return array<int, string>
     */
    public function verifyAndActivate(User $user, string $secret, string $code): array
    {
        if (! $this->google2fa->verifyKey($secret, $code, self::CODE_WINDOW)) {
            return [];
        }

        // Atomically mark confirmed_at — only one winner if two parallel
        // confirm requests race (controller check + service write were not
        // atomic, leading to double "2FA enabled" emails). The loser gets
        // an empty array back and skips the event dispatch.
        $affected = User::query()
            ->where('id', $user->id)
            ->whereNull('two_factor_confirmed_at')
            ->update(['two_factor_confirmed_at' => now()]);

        if ($affected === 0) {
            return [];
        }

        $plaintext = $this->freshRecoveryCodes();

        $user->saveAppAuthenticationSecret($secret);
        $user->saveAppAuthenticationRecoveryCodes(array_map(
            fn (string $code): string => Hash::make($code),
            $plaintext,
        ));

        event(new TwoFactorEnabled($user->refresh(), request()->ip(), (string) request()->userAgent()));

        return $plaintext;
    }

    /**
     * Verifies a challenge code — tries TOTP first, falls back to recovery
     * code. On recovery match, drops the consumed hash from the stored list.
     */
    public function verifyChallenge(User $user, string $code): bool
    {
        $secret = $user->getAppAuthenticationSecret();

        if ($secret === null) {
            return false;
        }

        if ($this->google2fa->verifyKey($secret, $code, self::CODE_WINDOW)) {
            return true;
        }

        $codes = $user->getAppAuthenticationRecoveryCodes() ?? [];
        $remaining = [];
        $matched = false;

        foreach ($codes as $hash) {
            if (! $matched && Hash::check($code, $hash)) {
                $matched = true;

                continue;
            }
            $remaining[] = $hash;
        }

        if ($matched) {
            $user->saveAppAuthenticationRecoveryCodes($remaining);
        }

        return $matched;
    }

    public function disable(User $user): void
    {
        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        $user->forceFill(['two_factor_confirmed_at' => null])->save();

        event(new TwoFactorDisabled($user, request()->ip(), (string) request()->userAgent()));
    }

    /**
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $plaintext = $this->freshRecoveryCodes();

        $user->saveAppAuthenticationRecoveryCodes(array_map(
            fn (string $code): string => Hash::make($code),
            $plaintext,
        ));

        event(new RecoveryCodesRegenerated($user, request()->ip(), (string) request()->userAgent()));

        return $plaintext;
    }

    /**
     * @return array<int, string>
     */
    private function freshRecoveryCodes(): array
    {
        return array_map(
            static fn (): string => Str::random(10).'-'.Str::random(10),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    private function brandName(): string
    {
        $name = $this->settings->get('app_name', 'Peregrine');

        return strip_tags(is_string($name) ? $name : 'Peregrine');
    }
}
