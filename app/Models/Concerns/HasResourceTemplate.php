<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ResourceTemplate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mixin extracted from `ServerConfiguration` to keep the parent model
 * under the 300-line file rule.
 *
 * Provides :
 *  - the `resourceTemplate()` BelongsTo relation
 *  - 6 read-only accessors (ram / cpu / disk / swap_mb / io_weight /
 *    cpu_pinning) that surface the template's specs at the parent level,
 *    so the API + outbound webhook payloads keep their pre-extraction
 *    shape (consumers like SaaSykit see the same top-level keys)
 *  - a `setAttribute()` override that intercepts legacy spec writes
 *    (`$config->ram = 4096`, `Model::create(['cpu' => 200])`, factories)
 *    and routes them to the bound template, materialising one when
 *    missing
 *
 * The host model is responsible for :
 *  - listing the legacy keys in `$fillable` (so mass-assignment can
 *    reach the override)
 *  - listing the read-only attributes in `$appends`
 *  - persisting the template in its `static::saving()` hook (this trait
 *    only mutates the relation in memory ; the parent's save pipeline
 *    finalises the SQL).
 */
trait HasResourceTemplate
{
    /**
     * Legacy keys that used to live as columns on the host table and
     * are now delegated to `ResourceTemplate`.
     */
    public const LEGACY_SPEC_KEYS = ['ram', 'cpu', 'disk', 'swap_mb', 'io_weight', 'cpu_pinning'];

    public function resourceTemplate(): BelongsTo
    {
        return $this->belongsTo(ResourceTemplate::class);
    }

    public function getRamAttribute(): ?int
    {
        return $this->resourceTemplate?->ram;
    }

    public function getCpuAttribute(): ?int
    {
        return $this->resourceTemplate?->cpu;
    }

    public function getDiskAttribute(): ?int
    {
        return $this->resourceTemplate?->disk;
    }

    public function getSwapMbAttribute(): ?int
    {
        return $this->resourceTemplate?->swap_mb;
    }

    public function getIoWeightAttribute(): ?int
    {
        return $this->resourceTemplate?->io_weight;
    }

    public function getCpuPinningAttribute(): ?string
    {
        return $this->resourceTemplate?->cpu_pinning;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::LEGACY_SPEC_KEYS, true)) {
            $template = $this->resolveTemplateForWrite();
            $template->{$key} = $value;
            $this->setRelation('resourceTemplate', $template);
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Returns the bound template (loading it lazily, or materialising a
     * fresh `auto-…` one in memory if none is attached). NOT persisted
     * here — the host model's `saving()` hook saves the template + back-
     * fills `resource_template_id` so the parent commits with the
     * correct FK.
     */
    protected function resolveTemplateForWrite(): ResourceTemplate
    {
        if ($this->relationLoaded('resourceTemplate') && $this->resourceTemplate !== null) {
            return $this->resourceTemplate;
        }
        if ($this->resource_template_id) {
            $tpl = ResourceTemplate::find($this->resource_template_id);
            if ($tpl) {
                $this->setRelation('resourceTemplate', $tpl);
                return $tpl;
            }
        }

        $tpl = new ResourceTemplate([
            'name' => 'auto-'.bin2hex(random_bytes(6)),
            'swap_mb' => 0,
            'io_weight' => 500,
            'cpu_pinning' => null,
        ]);
        $this->setRelation('resourceTemplate', $tpl);

        return $tpl;
    }

    /**
     * Persists the in-memory template (when dirty or new) and back-fills
     * the host's FK column. Call from the host's `static::saving()` hook.
     *
     * The `ResourceTemplateObserver` separately fans out
     * `configuration.updated` webhook events to every bound config when
     * the template's specs change, so we don't need to fake a parent
     * mutation here just to trigger the catalog observer.
     */
    protected function persistResourceTemplateBeforeSave(): void
    {
        if (! $this->relationLoaded('resourceTemplate') || $this->resourceTemplate === null) {
            return;
        }
        $tpl = $this->resourceTemplate;
        if (! $tpl->exists || $tpl->isDirty()) {
            $tpl->save();
        }
        if ((int) $this->resource_template_id !== (int) $tpl->id) {
            $this->resource_template_id = $tpl->id;
        }
    }
}
