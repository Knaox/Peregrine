<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'stripe_price_id',
        'egg_id',
        'nest_id',
        'ram',
        'cpu',
        'disk',
        'node_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'egg_id' => 'integer',
            'nest_id' => 'integer',
            'ram' => 'integer',
            'cpu' => 'integer',
            'disk' => 'integer',
            'node_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the egg associated with this plan.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * Get the nest associated with this plan.
     */
    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    /**
     * Get the node associated with this plan.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
