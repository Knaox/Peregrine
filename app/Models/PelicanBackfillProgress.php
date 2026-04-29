<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PelicanBackfillProgress extends Model
{
    protected $table = 'pelican_backfill_progress';

    /** @var list<string> */
    protected $fillable = [
        'resource_type',
        'last_processed_id',
        'total_count',
        'processed_count',
        'started_at',
        'completed_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_processed_id' => 'integer',
            'total_count' => 'integer',
            'processed_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
