<?php

namespace App\Services\Concerns;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * All "guard" steps for the plugin .zip importer. Split out of
 * PluginUploadService so each file stays under the project's per-file
 * ceiling and the service can focus on orchestration.
 *
 * Each method throws RuntimeException on failure with a translated
 * message — never returns a bool. Order matters : cheap-fast checks
 * first (size, MIME), expensive checks last (entry-by-entry walk).
 */
trait ValidatesPluginUpload
{
    protected function guardEnabled(): void
    {
        if (! config('panel.plugin_upload.enabled', true)) {
            throw new RuntimeException(__('admin/plugins.upload.errors.disabled'));
        }
    }

    protected function guardFileSize(UploadedFile $file): void
    {
        $max = (int) config('panel.plugin_upload.max_size');
        if ($file->getSize() > $max) {
            throw new RuntimeException(__('admin/plugins.upload.errors.too_large', [
                'max' => $this->humanBytes($max),
            ]));
        }
    }

    protected function guardMimeAndExtension(UploadedFile $file): void
    {
        $allowedMimes = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
        $mime = $file->getMimeType() ?? '';
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext !== 'zip' || ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException(__('admin/plugins.upload.errors.not_a_zip'));
        }
    }

    protected function guardMagicBytes(UploadedFile $file): void
    {
        $bytes = file_get_contents($file->getRealPath(), false, null, 0, 4);
        $signatures = ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"];

        if (! in_array($bytes, $signatures, true)) {
            throw new RuntimeException(__('admin/plugins.upload.errors.bad_signature'));
        }
    }

    protected function guardZipMetadata(ZipArchive $zip): void
    {
        $maxEntries = (int) config('panel.plugin_upload.max_entries');
        $maxSize = (int) config('panel.plugin_upload.max_extracted_size');
        $maxRatio = (int) config('panel.plugin_upload.max_compression_ratio');

        if ($zip->numFiles > $maxEntries) {
            throw new RuntimeException(__('admin/plugins.upload.errors.too_many_entries', ['max' => $maxEntries]));
        }

        $totalUncompressed = 0;
        $totalCompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $totalUncompressed += $stat['size'] ?? 0;
            $totalCompressed += $stat['comp_size'] ?? 0;
        }

        if ($totalUncompressed > $maxSize) {
            throw new RuntimeException(__('admin/plugins.upload.errors.too_large_extracted', [
                'max' => $this->humanBytes($maxSize),
            ]));
        }

        // Classic zip-bomb signature : tiny compressed → huge uncompressed.
        if ($totalCompressed > 0 && ($totalUncompressed / $totalCompressed) > $maxRatio) {
            throw new RuntimeException(__('admin/plugins.upload.errors.zip_bomb'));
        }
    }

    /**
     * Per-entry validation : path traversal, absolute paths, symlinks
     * (CVE-2025-3445), forbidden substrings, extension whitelist.
     *
     * @return array<int, array{name: string, index: int, size: int}>
     */
    protected function guardEntries(ZipArchive $zip): array
    {
        $forbidden = (array) config('panel.plugin_upload.forbidden_paths');
        $allowedExt = (array) config('panel.plugin_upload.allowed_extensions');
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';

            // macOS junk : `__MACOSX/` resource-fork directory and `._*`
            // AppleDouble files are added by the Finder / `zip` CLI when
            // archiving extended attributes. They carry no payload we need
            // and would either pollute the install or fail the extension
            // whitelist. Skip silently — they're not a security concern.
            if (str_starts_with($name, '__MACOSX/') || str_starts_with(basename($name), '._')) {
                continue;
            }

            // Reject absolute paths and any traversal segment.
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new RuntimeException(__('admin/plugins.upload.errors.unsafe_path', ['path' => $name]));
            }

            foreach ($forbidden as $needle) {
                if (str_contains($name, $needle)) {
                    throw new RuntimeException(__('admin/plugins.upload.errors.forbidden_path', ['path' => $name]));
                }
            }

            // CVE-2025-3445 : symlinks were used to bypass extractTo guards.
            $attrs = 0;
            $opsys = 0;
            $zip->getExternalAttributesIndex($i, $opsys, $attrs);
            if ($opsys === ZipArchive::OPSYS_UNIX && (($attrs >> 16) & 0xA000) === 0xA000) {
                throw new RuntimeException(__('admin/plugins.upload.errors.symlink', ['path' => $name]));
            }

            if (str_ends_with($name, '/')) {
                continue;
            }

            $basename = strtolower(basename($name));
            $matched = false;
            foreach ($allowedExt as $ext) {
                if (str_ends_with($basename, '.'.$ext) || $basename === $ext) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                throw new RuntimeException(__('admin/plugins.upload.errors.bad_extension', ['path' => $name]));
            }

            $entries[] = ['name' => $name, 'index' => $i, 'size' => (int) ($stat['size'] ?? 0)];
        }

        return $entries;
    }

    /** @return array<string, mixed> */
    protected function readAndValidateManifest(ZipArchive $zip): array
    {
        // Manifest must live at the archive root (manifest.json or
        // plugin.json — both names are used by existing Peregrine plugins).
        $raw = false;
        foreach (['manifest.json', 'plugin.json'] as $candidate) {
            $raw = $zip->getFromName($candidate);
            if ($raw !== false) {
                break;
            }
        }

        if (! $raw) {
            throw new RuntimeException(__('admin/plugins.upload.errors.no_manifest'));
        }

        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException(__('admin/plugins.upload.errors.bad_manifest_json'));
        }

        foreach (['id', 'name', 'version'] as $required) {
            if (empty($data[$required])) {
                throw new RuntimeException(__('admin/plugins.upload.errors.manifest_missing_field', ['field' => $required]));
            }
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $data['id'])) {
            throw new RuntimeException(__('admin/plugins.upload.errors.bad_id'));
        }

        if (! preg_match('/^\d+\.\d+\.\d+(?:-[\w.]+)?$/', (string) $data['version'])) {
            throw new RuntimeException(__('admin/plugins.upload.errors.bad_version'));
        }

        return $data;
    }

    protected function guardOverwrite(string $id): void
    {
        $target = base_path("plugins/{$id}");
        if (is_dir($target) && ! config('panel.plugin_upload.allow_overwrite')) {
            throw new RuntimeException(__('admin/plugins.upload.errors.already_installed', ['id' => $id]));
        }
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
