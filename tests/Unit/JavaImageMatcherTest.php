<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Pelican\JavaImageMatcher;
use PHPUnit\Framework\TestCase;

class JavaImageMatcherTest extends TestCase
{
    private function matcher(): JavaImageMatcher
    {
        return new JavaImageMatcher;
    }

    public function test_it_reads_the_java_major_from_label_or_image(): void
    {
        $m = $this->matcher();

        $this->assertSame(21, $m->javaMajor('Java 21'));
        $this->assertSame(17, $m->javaMajor('ghcr.io/pelican-eggs/yolks:java_17'));
        $this->assertSame(8, $m->javaMajor('eclipse-temurin:8-jre'));
        $this->assertNull($m->javaMajor('itzg/minecraft-server:latest'));
    }

    public function test_catalog_keeps_egg_images_when_there_are_at_least_two(): void
    {
        $egg = [
            'Java 17' => 'ghcr.io/pelican-eggs/yolks:java_17',
            'Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21',
        ];

        $this->assertCount(2, $this->matcher()->catalog($egg));
    }

    public function test_catalog_supplements_a_single_image_egg_with_yolks_fallback(): void
    {
        $egg = ['Default' => 'ghcr.io/pelican-eggs/yolks:java_17'];

        $images = array_column($this->matcher()->catalog($egg), 'image');

        // The egg's single image stays, plus the yolks majors it didn't cover.
        $this->assertContains('ghcr.io/pelican-eggs/yolks:java_17', $images);
        $this->assertContains('ghcr.io/pelican-eggs/yolks:java_21', $images);
        $this->assertContains('ghcr.io/pelican-eggs/yolks:java_8', $images);
    }

    public function test_it_recommends_the_smallest_java_that_satisfies_the_requirement(): void
    {
        $egg = [
            'Java 8' => 'ghcr.io/pelican-eggs/yolks:java_8',
            'Java 17' => 'ghcr.io/pelican-eggs/yolks:java_17',
            'Java 21' => 'ghcr.io/pelican-eggs/yolks:java_21',
        ];

        $catalog = $this->matcher()->catalog($egg, 17);
        $recommended = array_values(array_filter($catalog, fn (array $i): bool => $i['is_recommended']));

        $this->assertCount(1, $recommended);
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_17', $recommended[0]['image']);
    }

    public function test_it_recommends_the_highest_available_when_nothing_satisfies(): void
    {
        // Egg only ships Java 8/11 but the server needs 21 → no downgrade
        // possible, fall back to the highest available (11).
        $egg = [
            'Java 8' => 'ghcr.io/pelican-eggs/yolks:java_8',
            'Java 11' => 'ghcr.io/pelican-eggs/yolks:java_11',
        ];

        $catalog = $this->matcher()->catalog($egg, 21);
        $recommended = array_values(array_filter($catalog, fn (array $i): bool => $i['is_recommended']));

        $this->assertCount(1, $recommended);
        $this->assertSame('ghcr.io/pelican-eggs/yolks:java_11', $recommended[0]['image']);
    }

    public function test_allowed_images_include_egg_and_fallback_but_not_arbitrary(): void
    {
        $allowed = $this->matcher()->allowedImages(['Default' => 'ghcr.io/pelican-eggs/yolks:java_17']);

        $this->assertContains('ghcr.io/pelican-eggs/yolks:java_21', $allowed);
        $this->assertNotContains('evil/image:latest', $allowed);
    }
}
