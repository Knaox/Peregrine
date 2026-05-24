<?php

declare(strict_types=1);

namespace App\Services\Pelican;

/**
 * Pure helper that turns an egg's `docker_images` map into a Java-version
 * picker for the console quick-fix. No HTTP, no state — fully unit-testable.
 *
 * It (1) labels each egg image with the Java major it appears to provide,
 * (2) supplements the list with the canonical pelican-eggs/yolks images when
 *     the egg exposes fewer than two usable ones (so a single-image egg still
 *     lets the player switch Java versions — the whole point of the feature),
 *     and (3) flags the image that best satisfies a required Java major.
 *
 * Forward compatibility rule: Minecraft/mod-loaders run fine on a Java newer
 * than required but break on an older one, so the recommendation is the
 * SMALLEST available major that is >= the requirement (never a downgrade).
 */
final class JavaImageMatcher
{
    /** Canonical pelican-eggs/yolks images, keyed by Java major. */
    public const YOLKS_FALLBACK = [
        8 => 'ghcr.io/pelican-eggs/yolks:java_8',
        11 => 'ghcr.io/pelican-eggs/yolks:java_11',
        17 => 'ghcr.io/pelican-eggs/yolks:java_17',
        21 => 'ghcr.io/pelican-eggs/yolks:java_21',
    ];

    /**
     * @param  array<string, string>  $eggImages  label → image URL
     * @return list<array{label: string, image: string, java_major: int|null, is_recommended: bool}>
     */
    public function catalog(array $eggImages, ?int $requiredJava = null): array
    {
        $items = [];
        $seen = [];

        foreach ($eggImages as $label => $image) {
            $image = trim($image);
            if ($image === '' || isset($seen[$image])) {
                continue;
            }
            $seen[$image] = true;
            $label = trim($label);
            $items[] = [
                'label' => $label !== '' ? $label : $image,
                'image' => $image,
                'java_major' => $this->javaMajor($label.' '.$image),
                'is_recommended' => false,
            ];
        }

        // A single-image egg would make the picker pointless — supplement with
        // the yolks images so the player can actually switch Java version.
        if (count($items) < 2) {
            foreach (self::YOLKS_FALLBACK as $major => $image) {
                if (isset($seen[$image])) {
                    continue;
                }
                $seen[$image] = true;
                $items[] = [
                    'label' => "Java {$major}",
                    'image' => $image,
                    'java_major' => $major,
                    'is_recommended' => false,
                ];
            }
        }

        $recommended = $requiredJava !== null ? $this->pickRecommended($items, $requiredJava) : null;
        if ($recommended !== null) {
            foreach ($items as $i => $item) {
                $items[$i]['is_recommended'] = $item['image'] === $recommended;
            }
        }

        return $items;
    }

    /**
     * The set of image strings a server is allowed to switch to (egg images
     * plus the yolks fallback). Used server-side to reject arbitrary images.
     *
     * @param  array<string, string>  $eggImages
     * @return list<string>
     */
    public function allowedImages(array $eggImages): array
    {
        $allowed = [];
        foreach ($this->catalog($eggImages) as $item) {
            $allowed[] = $item['image'];
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Best image for a required Java major: the smallest available major that
     * is >= the requirement (forward compatible), falling back to the highest
     * available major when nothing satisfies it.
     *
     * @param  list<array{label: string, image: string, java_major: int|null, is_recommended: bool}>  $items
     */
    private function pickRecommended(array $items, int $requiredJava): ?string
    {
        $best = null;
        $bestMajor = null;
        $highest = null;
        $highestMajor = null;

        foreach ($items as $item) {
            $major = $item['java_major'];
            if ($major === null) {
                continue;
            }
            if ($highestMajor === null || $major > $highestMajor) {
                $highestMajor = $major;
                $highest = $item['image'];
            }
            if ($major >= $requiredJava && ($bestMajor === null || $major < $bestMajor)) {
                $bestMajor = $major;
                $best = $item['image'];
            }
        }

        return $best ?? $highest;
    }

    /** Extract the Java major a label/image advertises, if any. */
    public function javaMajor(string $haystack): ?int
    {
        // java_21 / jdk17 / "jre 11" / temurin-21 / openjdk:17 …
        if (preg_match('/(?:java|jdk|jre|temurin)[\s_:.\-]*(\d{1,2})(?!\d)/i', $haystack, $m)) {
            return (int) $m[1];
        }

        // Conservative tail match for bare images: "...21-jre", "...-17-jdk".
        if (preg_match('/(?<!\d)(\d{1,2})-(?:jre|jdk)(?![a-z0-9])/i', $haystack, $m)) {
            $val = (int) $m[1];
            if ($val >= 7 && $val <= 32) {
                return $val;
            }
        }

        return null;
    }
}
