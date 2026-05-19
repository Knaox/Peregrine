<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\VersionChanger;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\VersionChanger\Models\VersionChangerConfig;
use Plugins\VersionChanger\Services\JavaImageResolver;
use Plugins\VersionChanger\Services\VersionChangerSettingsService;
use Tests\TestCase;

class JavaImageResolverTest extends TestCase
{
    use ActivatesVersionChangerPlugin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->bootVersionChangerPlugin();

        parent::setUp();
    }

    public function test_returns_bundled_default_when_no_override(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->imageForJava(21));
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_17', $resolver->imageForJava(17));
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_8', $resolver->imageForJava(8));
    }

    public function test_admin_override_takes_precedence(): void
    {
        VersionChangerConfig::singleton()->update([
            'java_images' => ['21' => 'custom/java21:tag'],
        ]);

        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $this->assertSame('custom/java21:tag', $resolver->imageForJava(21));
        // Unaffected versions still come from defaults.
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_17', $resolver->imageForJava(17));
    }

    public function test_unknown_java_falls_back_to_default_image(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->imageForJava(999));
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->imageForJava(0));
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->imageForJava(-5));
    }

    public function test_egg_docker_images_win_over_bundled_defaults(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $eggImages = [
            'Java 8 LTS' => 'eclipse-temurin:8-jre',
            'Java 17 LTS' => 'eclipse-temurin:17-jre',
            'Java 21 LTS' => 'eclipse-temurin:21-jre',
        ];

        $this->assertSame('eclipse-temurin:21-jre', $resolver->resolve(21, $eggImages));
        $this->assertSame('eclipse-temurin:17-jre', $resolver->resolve(17, $eggImages));
        $this->assertSame('eclipse-temurin:8-jre', $resolver->resolve(8, $eggImages));
    }

    public function test_egg_matcher_handles_yolks_naming_convention(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $eggImages = [
            'Java 8' => 'ghcr.io/pelican-eggs/yolks:java_8',
            'Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21',
        ];

        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->resolve(21, $eggImages));
    }

    public function test_egg_matcher_upgrades_to_next_higher_java_when_exact_missing(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        // Egg only offers Java 17 and 21. Build asks for Java 16 — the
        // resolver should pick Java 17 (forward-compatible) and never
        // downgrade to a non-existent older image.
        $eggImages = [
            'Java 17' => 'eclipse-temurin:17-jre',
            'Java 21' => 'eclipse-temurin:21-jre',
        ];

        $this->assertSame('eclipse-temurin:17-jre', $resolver->resolve(16, $eggImages));
    }

    public function test_empty_egg_images_falls_back_to_bundled_yolks(): void
    {
        $resolver = new JavaImageResolver(new VersionChangerSettingsService);

        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_21', $resolver->resolve(21, []));
    }
}
