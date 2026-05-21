<?php

declare(strict_types=1);

namespace Tests\Unit\Bridge;

use App\Enums\PelicanEventKind;
use App\Services\Bridge\PelicanEventClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Locks the full event-string → PelicanEventKind mapping. Pelican ships the
 * same event under a short UI label ("created: Server") and a long raw label
 * ("eloquent.created: App\Models\Server" / "App\Events\Server\Installed"), so
 * every one of the 17 supported kinds is asserted in BOTH shapes. Unknown
 * labels must fall back to Ignored (audited, never dispatched).
 */
class PelicanEventClassifierTest extends TestCase
{
    private PelicanEventClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new PelicanEventClassifier;
    }

    #[DataProvider('shortFormEvents')]
    public function test_classifies_short_form_events(string $label, PelicanEventKind $expected): void
    {
        $this->assertSame($expected, $this->classifier->classify($label));
    }

    #[DataProvider('longFormEvents')]
    public function test_classifies_long_form_events(string $label, PelicanEventKind $expected): void
    {
        $this->assertSame($expected, $this->classifier->classify($label));
    }

    #[DataProvider('ignoredEvents')]
    public function test_unknown_events_resolve_to_ignored(string $label): void
    {
        $this->assertSame(PelicanEventKind::Ignored, $this->classifier->classify($label));
    }

    public function test_double_escaped_backslashes_are_normalised(): void
    {
        // Pelican sometimes ships the model path double-escaped in JSON.
        $this->assertSame(
            PelicanEventKind::ServerCreated,
            $this->classifier->classify('eloquent.created: App\\\\Models\\\\Server'),
        );
        $this->assertSame(
            PelicanEventKind::ServerInstalled,
            $this->classifier->classify('App\\\\Events\\\\Server\\\\Installed'),
        );
    }

    /** @return array<string, array{0: string, 1: PelicanEventKind}> */
    public static function shortFormEvents(): array
    {
        return [
            'server created' => ['created: Server', PelicanEventKind::ServerCreated],
            'server updated' => ['updated: Server', PelicanEventKind::ServerUpdated],
            'server deleted' => ['deleted: Server', PelicanEventKind::ServerDeleted],
            'server installed' => ['event: Server\\Installed', PelicanEventKind::ServerInstalled],
            'user created' => ['created: User', PelicanEventKind::UserCreated],
            'user updated' => ['updated: User', PelicanEventKind::UserUpdated],
            'user deleted' => ['deleted: User', PelicanEventKind::UserDeleted],
            'node created' => ['created: Node', PelicanEventKind::NodeCreated],
            'node updated' => ['updated: Node', PelicanEventKind::NodeUpdated],
            'node deleted' => ['deleted: Node', PelicanEventKind::NodeDeleted],
            'egg created' => ['created: Egg', PelicanEventKind::EggCreated],
            'egg updated' => ['updated: Egg', PelicanEventKind::EggUpdated],
            'egg deleted' => ['deleted: Egg', PelicanEventKind::EggDeleted],
            'egg variable created' => ['created: EggVariable', PelicanEventKind::EggVariableCreated],
            'egg variable updated' => ['updated: EggVariable', PelicanEventKind::EggVariableUpdated],
            'egg variable deleted' => ['deleted: EggVariable', PelicanEventKind::EggVariableDeleted],
        ];
    }

    /** @return array<string, array{0: string, 1: PelicanEventKind}> */
    public static function longFormEvents(): array
    {
        return [
            'server created' => ['eloquent.created: App\\Models\\Server', PelicanEventKind::ServerCreated],
            'server updated' => ['eloquent.updated: App\\Models\\Server', PelicanEventKind::ServerUpdated],
            'server deleted' => ['eloquent.deleted: App\\Models\\Server', PelicanEventKind::ServerDeleted],
            'server installed' => ['App\\Events\\Server\\Installed', PelicanEventKind::ServerInstalled],
            'user created' => ['eloquent.created: App\\Models\\User', PelicanEventKind::UserCreated],
            'user updated' => ['eloquent.updated: App\\Models\\User', PelicanEventKind::UserUpdated],
            'user deleted' => ['eloquent.deleted: App\\Models\\User', PelicanEventKind::UserDeleted],
            'node created' => ['eloquent.created: App\\Models\\Node', PelicanEventKind::NodeCreated],
            'node updated' => ['eloquent.updated: App\\Models\\Node', PelicanEventKind::NodeUpdated],
            'node deleted' => ['eloquent.deleted: App\\Models\\Node', PelicanEventKind::NodeDeleted],
            'egg created' => ['eloquent.created: App\\Models\\Egg', PelicanEventKind::EggCreated],
            'egg updated' => ['eloquent.updated: App\\Models\\Egg', PelicanEventKind::EggUpdated],
            'egg deleted' => ['eloquent.deleted: App\\Models\\Egg', PelicanEventKind::EggDeleted],
            'egg variable created' => ['eloquent.created: App\\Models\\EggVariable', PelicanEventKind::EggVariableCreated],
            'egg variable updated' => ['eloquent.updated: App\\Models\\EggVariable', PelicanEventKind::EggVariableUpdated],
            'egg variable deleted' => ['eloquent.deleted: App\\Models\\EggVariable', PelicanEventKind::EggVariableDeleted],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function ignoredEvents(): array
    {
        return [
            'backup created' => ['created: Backup'],
            'allocation updated' => ['updated: Allocation'],
            'activity logged custom event' => ['event: ActivityLogged'],
            'subuser eloquent' => ['eloquent.created: App\\Models\\Subuser'],
            'empty string' => [''],
            'garbage' => ['not-an-event'],
        ];
    }
}
