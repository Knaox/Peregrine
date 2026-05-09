<?php

namespace App\Filament\Pages\Concerns;

use App\Services\PluginUploadService;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Livewire wiring for the "Import a plugin from a .zip" feature.
 *
 * The file lifecycle is :
 *   1. Admin drops a file → Livewire stores it in livewire-tmp/
 *   2. We hand it to PluginUploadService::importZip() — every guard runs
 *      against the file in livewire-tmp before it reaches plugins/
 *   3. On success we reset $uploadedZip + reload the lists
 *   4. On failure we surface the message verbatim — every guard already
 *      throws translated user-facing errors.
 *
 * Consumers must implement loadPlugins() / loadMarketplace() and use
 * Livewire\WithFileUploads (re-exported here so a single `use` import
 * on the Page class is enough).
 */
trait HandlesPluginUpload
{
    use WithFileUploads;

    /** Livewire-managed temporary upload — bound to the drop zone input. */
    public ?TemporaryUploadedFile $uploadedZip = null;

    /**
     * Livewire hook : called automatically AFTER the file upload to
     * livewire-tmp/ has fully completed (not when the input changes).
     * Wiring it here instead of via `wire:change` on the input fixes a
     * race where importPluginZip() was invoked before $uploadedZip had
     * been streamed to the server, forcing the admin to upload twice.
     */
    public function updatedUploadedZip(): void
    {
        if ($this->uploadedZip) {
            $this->importPluginZip();
        }
    }

    /**
     * Validates the uploaded ZIP synchronously and either imports the
     * plugin or surfaces an actionable error notification. Public so
     * it remains callable from anywhere (tests, manual triggers, …).
     */
    public function importPluginZip(): void
    {
        if (! $this->uploadedZip) {
            return;
        }

        // Livewire's first-pass validation : keeps obviously-wrong files
        // (anything that isn't .zip / too big) from ever hitting our
        // service. The service then runs its own deeper guards.
        $this->validate([
            'uploadedZip' => [
                'required',
                'file',
                'mimes:zip',
                'max:'.((int) config('panel.plugin_upload.max_size') / 1024),
            ],
        ]);

        try {
            $manifest = app(PluginUploadService::class)->importZip($this->uploadedZip);

            Notification::make()
                ->title(__('admin/plugins.upload.success_title'))
                ->body(__('admin/plugins.upload.success_body', [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                ]))
                ->success()
                ->send();

            $this->reset('uploadedZip');
            $this->loadPlugins();
            $this->loadMarketplace();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/plugins.upload.failure_title'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->reset('uploadedZip');
        }
    }

    public function clearUploadedZip(): void
    {
        $this->reset('uploadedZip');
    }
}
