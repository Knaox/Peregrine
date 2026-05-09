<?php

namespace App\Support;

/**
 * Resolves the OS user/group running the current PHP process.
 *
 * Used by error messages that suggest a `chown` fix : we need the actual
 * web-server user (typically www-data, nginx, php-fpm-pool-X, …) — NOT
 * the user invoking the artisan CLI from a shell. The previous hint
 * `chown -R $(id -un):$(id -gn)` was actively misleading : when an admin
 * copy-pasted it into their terminal, $(id -un) evaluated to their shell
 * user (e.g. `damien`), which is exactly the wrong owner — the bug came
 * from PHP-FPM running as `www-data` and not being able to delete files
 * owned by `damien`. Reapplying `damien` ownership reproduced the bug.
 *
 * Falls back to "www-data" (the Debian/Ubuntu/Mint default) when the
 * posix extension is unavailable — Windows or stripped-down PHP builds.
 */
final class PhpProcessUser
{
    /**
     * @return array{user: string, group: string}
     */
    public static function current(): array
    {
        $user = 'www-data';
        $group = 'www-data';

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = @posix_getpwuid(posix_geteuid());
            if (is_array($pw) && ! empty($pw['name'])) {
                $user = (string) $pw['name'];
            }
        }

        if (function_exists('posix_getegid') && function_exists('posix_getgrgid')) {
            $gr = @posix_getgrgid(posix_getegid());
            if (is_array($gr) && ! empty($gr['name'])) {
                $group = (string) $gr['name'];
            }
        }

        return ['user' => $user, 'group' => $group];
    }

    /**
     * Convenience : "user:group" suitable for splicing into a chown hint.
     */
    public static function ownerSpec(): string
    {
        $u = self::current();
        return "{$u['user']}:{$u['group']}";
    }
}
