<?php

namespace Plugins\Invitations\Models;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $table = 'server_invitations';

    /** @var list<string> */
    protected $fillable = [
        'token',
        'email',
        'server_id',
        'permissions',
        'inviter_user_id',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
        'revoked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /**
     * Scope: active invitations (not accepted, not revoked, not expired).
     *
     * @param Builder<Invitation> $query
     * @return Builder<Invitation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: filter by email.
     *
     * @param Builder<Invitation> $query
     * @return Builder<Invitation>
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', strtolower($email));
    }

    public function isActive(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
