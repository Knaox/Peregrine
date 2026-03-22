<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pelican_server_id',
        'name',
        'status',
        'egg_id',
        'plan_id',
        'stripe_subscription_id',
        'payment_intent_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'pelican_server_id' => 'integer',
            'status' => 'string',
            'egg_id' => 'integer',
            'plan_id' => 'integer',
        ];
    }

    /**
     * Get the user that owns this server.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the egg used by this server.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * Get the plan associated with this server.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServerPlan::class, 'plan_id');
    }
}
