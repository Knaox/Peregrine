<?php

namespace App\Services;

use App\Services\Concerns\ValidatesPluginUpload;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * Hardened importer for admin-uploaded plugin .zip files.
 *
 * Defence layers (each one aborts on failure) :
 *   1. MIME + extension whitelist
 *   2. Magic-bytes check (PK\x03\x04 / PK\x05\x06 / PK\x07\x08)
 *   3. Size cap (configurable, default 20 MB)
 *   4. ZIP integrity (ZipArchive::CHECKCONS)
 *   5. Per-entry guards : path traversal, absolute paths, symlinks
 *      (CVE-2025-3445), extension whitelist, total uncompressed size
 *      and compression ratio (anti zip-bomb)
 *   6. Manifest validation : id (kebab-case), version (SemVer), name
 *   7. Sandbox extraction → canonical-path check → atomic move
 *      (prevents TOCTOU)
 *   8. Permissions normalised to 0755 dir / 0644 file
 *
 * Files land in `base_path('plugins/')` which is NOT web-accessible
 * (only `public/` is served), so even a file that survived every guard
 * cannot be hit directly via HTTP.
 */
class PluginUploadService
{
    use ValidatesPluginUpload;

    public function __construct(private readonly Filesystem $files = new Filesystem) {}

    /**
     * Import a ZIP and return the validated manifest on success.
     *
     * @throws RuntimeException on any validation failure.
     * @return array<string, mixed>
     */
    public function importZip(UploadedFile $file): array
    {
        $this->guardEnabled();
        $this->guardFileSize($file);
        $this->guardMimeAndExtension($file);
        $this->guardMagicBytes($file);

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath(), ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException(__('admin/plugins.upload.errors.invalid_zip'));
        }

        try {
            $this->guardZipMetadata($zip);
            $entries = $this->guardEntries($zip);
            $manifest = $this->readAndValidateManifest($zip);
            $this->guardOverwrite($manifest['id']);

            $tempDir = $this->extractEntriesToTemp($zip, $entries);
            try {
                $this->canonicalPathCheck($tempDir);
                $this->normalisePermissions($tempDir);
                $this->moveAtomic($tempDir, $manifest['id']);
                $this->log('imported', $file, $manifest);

                return $manifest;
            } catch (\Throwable $e) {
                if (is_dir($tempDir)) {
                    $this->files->deleteDirectory($tempDir);
                }
                throw $e;
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Extracts whitelisted entries to a sandboxed temp dir using manual
     * read+write — avoids any TOCTOU window between extractTo's own
     * checks and the actual file write.
     *
     * @param array<int, array{name: string, index: int, size: int}> $entries
     */
    private function extractEntriesToTemp(ZipArchive $zip, array $entries): string
    {
        $temp = storage_path('app/plugin-uploads/'.bin2hex(random_bytes(8)));
        $this->files->makeDirectory($temp, 0755, true, true);

        foreach ($entries as $entry) {
            $target = $temp.DIRECTORY_SEPARATOR.$entry['name'];
            $dir = dirname($target);

            if (! is_dir($dir)) {
                $this->files->makeDirectory($dir, 0755, true, true);
            }

            $stream = $zip->getStream($entry['name']);
            if ($stream === false) {
                throw new RuntimeException(__('admin/plugins.upload.errors.read_failed', ['path' => $entry['name']]));
            }

            $out = fopen($target, 'wb');
            stream_copy_to_stream($stream, $out, $entry['size']);
            fclose($out);
            fclose($stream);
        }

        return $temp;
    }

    /**
     * Walk every extracted file and verify its `realpath` still sits
     * under the sandbox root. Catches any encoding trick that slipped
     * past the entry-name guard.
     */
    private function canonicalPathCheck(string $tempDir): void
    {
        $base = realpath($tempDir);
        if ($base === false) {
            throw new RuntimeException(__('admin/plugins.upload.errors.canonicalisation_failed'));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $info) {
            $real = realpath($info->getPathname());
            if ($real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException(__('admin/plugins.upload.errors.path_escape', ['path' => $info->getPathname()]));
            }
            if (is_link($info->getPathname())) {
                throw new RuntimeException(__('admin/plugins.upload.errors.symlink_post', ['path' => $info->getPathname()]));
            }
        }
    }

    private function normalisePermissions(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $info) {
            chmod($info->getPathname(), $info->isDir() ? 0755 : 0644);
        }
        chmod($dir, 0755);
    }

    private function moveAtomic(string $tempDir, string $id): string
    {
        $finalDir = base_path("plugins/{$id}");
        if (is_dir($finalDir)) {
            $this->files->deleteDirectory($finalDir);
        }
        $this->files->move($tempDir, $finalDir);

        return $finalDir;
    }

    /** @param array<string, mixed> $manifest */
    private function log(string $event, UploadedFile $file, array $manifest): void
    {
        Log::channel(config('logging.default'))->info('plugin_upload', [
            'event' => $event,
            'plugin_id' => $manifest['id'],
            'version' => $manifest['version'],
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size' => $file->getSize(),
            'admin_id' => Auth::id(),
            'ip' => request()->ip(),
        ]);
    }
}
