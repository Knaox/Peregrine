{{--
    Hardened drop zone for the .zip plugin import. The actual import logic
    lives in PluginUploadService — every guard runs server-side, this
    partial only provides a comfortable affordance for the admin.

    Variables : (none — relies on Livewire properties on the parent page)
      $uploadedZip — Livewire TemporaryUploadedFile (null when idle)
--}}
<div
    class="pg-upload"
    x-data="{
        dragOver: false,
        showDoc: false,
    }"
    @dragover.prevent="dragOver = true"
    @dragleave.prevent="dragOver = false"
    @drop.prevent="dragOver = false"
    :class="{ 'is-dragover': dragOver }"
    wire:loading.class="is-uploading"
    wire:target="uploadedZip, importPluginZip"
>
    <div class="pg-upload-inner">
        <span class="pg-upload-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15M9 12l3 3m0 0 3-3m-3 3V2.25" />
            </svg>
        </span>
        <div class="pg-upload-text">
            <p class="pg-upload-title">{{ __('admin/plugins.upload.title') }}</p>
            <p class="pg-upload-hint">
                {{ __('admin/plugins.upload.hint', [
                    'max' => round((int) config('panel.plugin_upload.max_size') / 1024 / 1024).' MB',
                ]) }}
            </p>
        </div>

        <div class="pg-upload-actions">
            <label class="pg-btn pg-btn-primary" style="cursor: pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                <span wire:loading.remove wire:target="uploadedZip, importPluginZip">{{ __('admin/plugins.upload.browse') }}</span>
                <span wire:loading wire:target="uploadedZip, importPluginZip">{{ __('admin/plugins.upload.uploading') }}</span>
                {{--
                    No wire:change here — the import is triggered by the
                    Livewire `updatedUploadedZip()` hook on the page once
                    the upload to livewire-tmp/ has actually completed.
                    Adding wire:change would race the upload and force a
                    double-import (see HandlesPluginUpload).
                --}}
                <input
                    type="file"
                    accept=".zip,application/zip,application/x-zip-compressed"
                    wire:model="uploadedZip"
                    style="display: none;"
                />
            </label>

            <button type="button" class="pg-btn pg-btn-ghost" @click="showDoc = !showDoc" :aria-expanded="showDoc.toString()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <span x-text="showDoc ? @js(__('admin/plugins.upload.hide_help')) : @js(__('admin/plugins.upload.show_help'))"></span>
            </button>
        </div>
    </div>

    <div x-show="showDoc" x-collapse x-cloak>
        @include('filament.pages.partials.plugins.help-doc')
    </div>

    @error('uploadedZip')
        <p class="pg-upload-error">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
            {{ $message }}
        </p>
    @enderror
</div>

{{--
    @once garantit que ce <style> ne sera émis qu'une seule fois par
    request, même si le partial est inclus plusieurs fois (best
    practice Blade — évite la duplication CSS dans le DOM).
--}}
@once
<style>
    .pg-plugins .pg-upload { position: relative; padding: 1.25rem 1.5rem; border: 1.5px dashed rgba(255,255,255,0.14); border-radius: 0.875rem; background: rgba(255,255,255,0.02); transition: border-color 200ms, background 200ms; margin-bottom: 1.25rem; }
    .pg-plugins .pg-upload:hover { border-color: rgba(var(--primary-500), 0.35); background: rgba(255,255,255,0.035); }
    .pg-plugins .pg-upload.is-dragover { border-color: rgba(var(--primary-500), 0.7); background: rgba(var(--primary-500), 0.08); }
    .pg-plugins .pg-upload.is-uploading { opacity: 0.7; pointer-events: none; }
    .pg-plugins .pg-upload-inner { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .pg-plugins .pg-upload-icon { flex-shrink: 0; width: 2.75rem; height: 2.75rem; border-radius: 0.625rem; background: rgba(var(--primary-500), 0.16); color: rgb(var(--primary-300)); display: flex; align-items: center; justify-content: center; }
    .pg-plugins .pg-upload-text { flex: 1; min-width: 200px; }
    .pg-plugins .pg-upload-title { font-size: 0.9375rem; font-weight: 600; color: var(--pg-text-primary); margin: 0; }
    .pg-plugins .pg-upload-hint { font-size: 0.8125rem; color: var(--pg-text-muted); margin: 0.25rem 0 0; }
    .pg-plugins .pg-upload-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .pg-plugins .pg-upload-error { font-size: 0.8125rem; color: rgb(var(--pg-danger)); margin: 0.625rem 0 0; display: inline-flex; align-items: center; gap: 0.375rem; }
</style>
@endonce
