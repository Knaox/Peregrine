<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Plugins\MinecraftModpackInstaller\Models\ModpackConfig;
use RuntimeException;

/**
 * Resolves the Java major and Docker image to use for a given modpack
 * (Minecraft version + loader). **Zero hardcode**: every value comes from
 *
 *   1. the admin override in `modpack_configs` (DB), if present, or
 *   2. the bundled `config/java-compatibility.php` plugin defaults.
 *
 * Rules are evaluated top-down — the first match wins. Loader-specific
 * rules MUST come before generic (loader=null) ones in the source list.
 *
 * Image lookup falls back to the default-Java image if the requested major
 * isn't mapped, and finally throws if neither is configured. We prefer
 * raising a visible error to silently substituting a wrong image (per the
 * Pelican-integration principle: visible failure > silent rescue).
 */
final class JavaCompatibilityMatrix
{
    /** Top-level config key under which the plugin defaults are merged. */
    private const CONFIG_KEY = 'modpack-installer.java';

    public function __construct(
        private readonly ModpackConfig $config,
        private readonly ConfigRepository $configRepository,
    ) {}

    /**
     * Pick the Java major for a (mc_version, loader) pair. Returns the
     * configured `default_java` when no rule matches.
     */
    public function requiredJava(?string $mcVersion, ?string $loader): int
    {
        return self::resolveRequiredJava(
            $this->resolveRules(),
            $this->defaultJava(),
            $mcVersion,
            $loader,
        );
    }

    /**
     * Look up the Docker image for a Java major. Falls back to the
     * default-Java image if the major isn't explicitly mapped, then
     * throws if neither is configured (visible failure on purpose —
     * silent fallback would mask a config typo for weeks).
     */
    public function imageForJava(int $java): string
    {
        return self::resolveImageForJava(
            $this->resolveImages(),
            $java,
            $this->defaultJava(),
        );
    }

    /**
     * Pure resolution: given a rule list, a default Java major, an MC
     * version, and a loader, return the Java major to use. Exposed
     * static so tests can exercise the logic without booting Laravel
     * or building a ModpackConfig instance.
     *
     * @param  list<array<string, mixed>>|array<int|string, array<string, mixed>>  $rules
     */
    public static function resolveRequiredJava(
        array $rules,
        int $defaultJava,
        ?string $mcVersion,
        ?string $loader,
    ): int {
        $mc = self::normalize($mcVersion, false);
        $resolvedLoader = self::normalize($loader, true);

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            if (! self::ruleMatches($rule, $mc, $resolvedLoader)) {
                continue;
            }
            $java = (int) ($rule['java'] ?? 0);
            if ($java >= 8) {
                return $java;
            }
        }

        return $defaultJava >= 8 ? $defaultJava : 17;
    }

    /**
     * Pure resolution: given an image map, a target Java major, and a
     * default Java major, return the Docker image. Throws when neither
     * is mapped — visible failure on purpose.
     *
     * @param  array<string, string>  $images
     */
    public static function resolveImageForJava(array $images, int $java, int $defaultJava): string
    {
        $key = (string) $java;
        if (isset($images[$key]) && is_string($images[$key]) && trim($images[$key]) !== '') {
            return $images[$key];
        }

        $fallbackKey = (string) $defaultJava;
        if (
            $fallbackKey !== $key
            && isset($images[$fallbackKey])
            && is_string($images[$fallbackKey])
            && trim($images[$fallbackKey]) !== ''
        ) {
            return $images[$fallbackKey];
        }

        throw new RuntimeException(sprintf(
            'No Docker image configured for Java %d (default Java %d also missing). '
            .'Check config/java-compatibility.php or the Modpack admin page.',
            $java,
            $defaultJava,
        ));
    }

    /**
     * Configured default Java major. DB override beats plugin config.
     * Capped to a sane minimum (8) so a misconfigured 0 doesn't propagate.
     */
    public function defaultJava(): int
    {
        $override = $this->config->default_java ?? null;
        if (is_int($override) && $override >= 8) {
            return $override;
        }

        $fromConfig = (int) $this->configRepository->get(self::CONFIG_KEY.'.default_java', 17);

        return $fromConfig >= 8 ? $fromConfig : 17;
    }

    /**
     * Effective rule list. DB override **replaces** the bundled list (rules
     * are an ordered sequence — partial merging would silently drop
     * loader-specific rules and yield the wrong Java).
     *
     * @return list<array<string, mixed>>
     */
    private function resolveRules(): array
    {
        $override = $this->config->java_rules ?? null;
        if (is_array($override) && $override !== []) {
            return array_values($override);
        }

        $fromConfig = $this->configRepository->get(self::CONFIG_KEY.'.rules', []);

        return is_array($fromConfig) ? array_values($fromConfig) : [];
    }

    /**
     * Effective image map. DB override **merges** over the bundled map
     * per-key — admins can override only `21` to point at a private
     * mirror without re-declaring `8 / 11 / 17`.
     *
     * @return array<string, string>
     */
    private function resolveImages(): array
    {
        $defaults = $this->configRepository->get(self::CONFIG_KEY.'.images', []);
        $defaults = is_array($defaults) ? $defaults : [];

        $override = $this->config->java_images ?? null;
        if (! is_array($override) || $override === []) {
            return $this->normalizeImageKeys($defaults);
        }

        return $this->normalizeImageKeys(array_merge($defaults, $override));
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private static function ruleMatches(array $rule, ?string $mc, ?string $loader): bool
    {
        $ruleLoader = $rule['loader'] ?? null;
        $ruleLoader = is_string($ruleLoader) ? strtolower(trim($ruleLoader)) : null;
        if ($ruleLoader === '') {
            $ruleLoader = null;
        }
        // null loader on the rule is a wildcard (matches any loader, incl.
        // vanilla / unknown). Otherwise, exact match on the normalized
        // loader name.
        if ($ruleLoader !== null && $ruleLoader !== $loader) {
            return false;
        }

        $min = isset($rule['mc_min']) && is_string($rule['mc_min']) && $rule['mc_min'] !== '' ? $rule['mc_min'] : null;
        $max = isset($rule['mc_max']) && is_string($rule['mc_max']) && $rule['mc_max'] !== '' ? $rule['mc_max'] : null;

        if ($mc === null) {
            // Modpack manifest gave us no MC version. Only "open-ended on
            // both sides" rules can be safely picked — anything stricter
            // would be a guess. Without an open-ended rule, fall through
            // to the default Java.
            return $min === null && $max === null;
        }

        if ($min !== null && version_compare($mc, $min, '<')) {
            return false;
        }
        if ($max !== null && version_compare($mc, $max, '>')) {
            return false;
        }

        return true;
    }

    private static function normalize(?string $value, bool $lowercase): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $lowercase ? strtolower($trimmed) : $trimmed;
    }

    /**
     * JSON casts round-trip integer keys as strings, so a hand-edited
     * override row may end up with a mix. Normalize to string-keyed for
     * predictable lookups.
     *
     * @param  array<int|string, mixed>  $images
     * @return array<string, string>
     */
    private function normalizeImageKeys(array $images): array
    {
        $out = [];
        foreach ($images as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
