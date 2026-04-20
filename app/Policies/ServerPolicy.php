<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return $this->hasAccess($user, $server);
    }

    public function update(User $user, Server $server): bool
    {
        return $this->isOwner($user, $server);
    }

    public function delete(User $user, Server $server): bool
    {
        return $this->isOwner($user, $server);
    }

    // --- Overview ---
    public function readOverview(User $user, Server $server): bool { return $this->perm($user, $server, 'overview.read'); }
    public function readStats(User $user, Server $server): bool { return $this->perm($user, $server, 'overview.stats'); }
    public function readServerInfo(User $user, Server $server): bool { return $this->perm($user, $server, 'overview.server-info'); }

    // --- Control / power / console ---
    public function sendCommand(User $user, Server $server): bool { return $this->perm($user, $server, 'control.console'); }
    public function startServer(User $user, Server $server): bool { return $this->perm($user, $server, 'control.start'); }
    public function stopServer(User $user, Server $server): bool { return $this->perm($user, $server, 'control.stop'); }
    public function restartServer(User $user, Server $server): bool { return $this->perm($user, $server, 'control.restart'); }
    /** @deprecated kept for backward compat; prefer start/stop/restart */
    public function controlPower(User $user, Server $server): bool { return $this->perm($user, $server, 'control.start'); }

    // --- User management (subusers) ---
    public function createUser(User $user, Server $server): bool { return $this->perm($user, $server, 'user.create'); }
    public function readUser(User $user, Server $server): bool { return $this->perm($user, $server, 'user.read'); }
    public function updateUser(User $user, Server $server): bool { return $this->perm($user, $server, 'user.update'); }
    public function deleteUser(User $user, Server $server): bool { return $this->perm($user, $server, 'user.delete'); }

    // --- Files ---
    public function createFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.create'); }
    public function readFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.read'); }
    public function readFileContent(User $user, Server $server): bool { return $this->perm($user, $server, 'file.read-content'); }
    public function updateFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.update'); }
    public function deleteFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.delete'); }
    public function archiveFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.archive'); }
    public function sftpFile(User $user, Server $server): bool { return $this->perm($user, $server, 'file.sftp'); }
    /** @deprecated replaced by granular file.* abilities */
    public function manageFiles(User $user, Server $server): bool { return $this->perm($user, $server, 'file.read'); }

    // --- Backups ---
    public function createBackup(User $user, Server $server): bool { return $this->perm($user, $server, 'backup.create'); }
    public function readBackup(User $user, Server $server): bool { return $this->perm($user, $server, 'backup.read'); }
    public function deleteBackup(User $user, Server $server): bool { return $this->perm($user, $server, 'backup.delete'); }
    public function downloadBackup(User $user, Server $server): bool { return $this->perm($user, $server, 'backup.download'); }
    public function restoreBackup(User $user, Server $server): bool { return $this->perm($user, $server, 'backup.restore'); }

    // --- Databases ---
    public function createDatabase(User $user, Server $server): bool { return $this->perm($user, $server, 'database.create'); }
    public function readDatabase(User $user, Server $server): bool { return $this->perm($user, $server, 'database.read'); }
    public function updateDatabase(User $user, Server $server): bool { return $this->perm($user, $server, 'database.update'); }
    public function deleteDatabase(User $user, Server $server): bool { return $this->perm($user, $server, 'database.delete'); }
    public function viewDatabasePassword(User $user, Server $server): bool { return $this->perm($user, $server, 'database.view_password'); }

    // --- Schedules ---
    public function createSchedule(User $user, Server $server): bool { return $this->perm($user, $server, 'schedule.create'); }
    public function readSchedule(User $user, Server $server): bool { return $this->perm($user, $server, 'schedule.read'); }
    public function updateSchedule(User $user, Server $server): bool { return $this->perm($user, $server, 'schedule.update'); }
    public function deleteSchedule(User $user, Server $server): bool { return $this->perm($user, $server, 'schedule.delete'); }

    // --- Allocations ---
    public function readAllocation(User $user, Server $server): bool { return $this->perm($user, $server, 'allocation.read'); }
    public function createAllocation(User $user, Server $server): bool { return $this->perm($user, $server, 'allocation.create'); }
    public function updateAllocation(User $user, Server $server): bool { return $this->perm($user, $server, 'allocation.update'); }
    public function deleteAllocation(User $user, Server $server): bool { return $this->perm($user, $server, 'allocation.delete'); }

    // --- Startup ---
    public function readStartup(User $user, Server $server): bool { return $this->perm($user, $server, 'startup.read'); }
    public function updateStartup(User $user, Server $server): bool { return $this->perm($user, $server, 'startup.update'); }

    // --- Settings ---
    public function renameServer(User $user, Server $server): bool { return $this->perm($user, $server, 'settings.rename'); }
    public function reinstallServer(User $user, Server $server): bool { return $this->perm($user, $server, 'settings.reinstall'); }

    // --- Internals ---

    /**
     * Any access to the server (owner or subuser).
     */
    private function hasAccess(User $user, Server $server): bool
    {
        return DB::table('server_user')
            ->where('user_id', $user->id)
            ->where('server_id', $server->id)
            ->exists();
    }

    /**
     * Owner of the server.
     */
    private function isOwner(User $user, Server $server): bool
    {
        return DB::table('server_user')
            ->where('user_id', $user->id)
            ->where('server_id', $server->id)
            ->where('role', 'owner')
            ->exists();
    }

    /**
     * Check a granular permission. Owners are granted everything.
     */
    private function perm(User $user, Server $server, string $permission): bool
    {
        $pivot = DB::table('server_user')
            ->where('user_id', $user->id)
            ->where('server_id', $server->id)
            ->first();

        if (! $pivot) {
            return false;
        }

        if ($pivot->role === 'owner') {
            return true;
        }

        $permissions = is_string($pivot->permissions)
            ? json_decode($pivot->permissions, true)
            : ($pivot->permissions ?? []);

        return in_array($permission, is_array($permissions) ? $permissions : [], true);
    }
}
