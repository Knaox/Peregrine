<?php

namespace App\Services\Sync;

use App\Models\User;
use App\Services\DTOs\SyncComparison;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * User sync — compare Pelican users with local DB + import selected ones.
 */
class UserSync
{
    public function __construct(
        private PelicanApplicationService $pelicanService,
    ) {}

    /**
     * Compare Pelican users with local database users.
     *
     * - new:      Users that exist in Pelican but have no matching local record.
     * - synced:   Users that exist in both Pelican and the local database.
     * - orphaned: Local users whose pelican_user_id no longer exists on Pelican.
     */
    public function compareUsers(): SyncComparison
    {
        $pelicanUsers = $this->pelicanService->listUsers();
        $localUsers = User::whereNotNull('pelican_user_id')->get();

        $localPelicanIds = $localUsers->pluck('pelican_user_id')->toArray();
        $remotePelicanIds = array_map(fn ($u) => $u->id, $pelicanUsers);

        $new = [];
        $synced = [];
        $orphaned = [];

        foreach ($pelicanUsers as $pelicanUser) {
            if (in_array($pelicanUser->id, $localPelicanIds, true)) {
                $synced[] = $pelicanUser;
            } else {
                $new[] = $pelicanUser;
            }
        }

        foreach ($localUsers as $localUser) {
            if (! in_array($localUser->pelican_user_id, $remotePelicanIds, true)) {
                $orphaned[] = $localUser;
            }
        }

        return new SyncComparison(
            new: $new,
            synced: $synced,
            orphaned: $orphaned,
        );
    }

    /**
     * Import selected Pelican users into the local database.
     *
     * @param int[] $pelicanUserIds
     *
     * @return int Number of users imported.
     */
    public function importUsers(array $pelicanUserIds): int
    {
        $imported = 0;

        foreach ($pelicanUserIds as $pelicanUserId) {
            $pelicanUser = $this->pelicanService->getUser($pelicanUserId);

            // Already linked by pelican_user_id — skip
            if (User::where('pelican_user_id', $pelicanUser->id)->exists()) {
                continue;
            }

            // Exists by email but not yet linked — update pelican_user_id
            $existingUser = User::where('email', $pelicanUser->email)->first();
            if ($existingUser) {
                $existingUser->update(['pelican_user_id' => $pelicanUser->id]);
                $imported++;
                continue;
            }

            // New user — create
            User::create([
                'name' => $pelicanUser->name,
                'email' => $pelicanUser->email,
                'password' => Hash::make(Str::random(32)),
                'pelican_user_id' => $pelicanUser->id,
            ]);

            $imported++;
        }

        return $imported;
    }
}
