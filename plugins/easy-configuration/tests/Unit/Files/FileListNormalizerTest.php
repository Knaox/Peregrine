<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Files;

use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Http\Controllers\Admin\ServerFileBrowserController;

final class FileListNormalizerTest extends TestCase
{
    public function test_it_flattens_the_attributes_envelope(): void
    {
        $raw = [
            ['object' => 'file_object', 'attributes' => ['name' => 'server.properties', 'is_file' => true, 'mimetype' => 'text/plain', 'size' => 120]],
        ];

        $entries = ServerFileBrowserController::normalizeEntries($raw);

        self::assertCount(1, $entries);
        self::assertSame('server.properties', $entries[0]['name']);
        self::assertSame(120, $entries[0]['size']);
        self::assertFalse($entries[0]['is_directory']);
    }

    public function test_it_synthesizes_is_directory_from_mimetype(): void
    {
        $raw = [
            ['attributes' => ['name' => 'config', 'is_file' => true, 'mimetype' => 'inode/directory']],
        ];

        $entries = ServerFileBrowserController::normalizeEntries($raw);

        self::assertTrue($entries[0]['is_directory']);
    }

    public function test_it_synthesizes_is_directory_from_is_file_false(): void
    {
        $raw = [
            ['attributes' => ['name' => 'plugins', 'is_file' => false]],
        ];

        $entries = ServerFileBrowserController::normalizeEntries($raw);

        self::assertTrue($entries[0]['is_directory']);
    }

    public function test_it_keeps_an_explicit_is_directory_flag(): void
    {
        $raw = [
            ['attributes' => ['name' => 'weird', 'is_file' => true, 'is_directory' => false]],
        ];

        $entries = ServerFileBrowserController::normalizeEntries($raw);

        self::assertFalse($entries[0]['is_directory']);
    }

    public function test_it_handles_already_flat_entries(): void
    {
        $raw = [
            ['name' => 'eula.txt', 'is_file' => true, 'mimetype' => 'text/plain'],
        ];

        $entries = ServerFileBrowserController::normalizeEntries($raw);

        self::assertSame('eula.txt', $entries[0]['name']);
        self::assertFalse($entries[0]['is_directory']);
    }
}
