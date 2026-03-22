<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    public function update(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    public function delete(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    public function sendCommand(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    public function manageFiles(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }

    public function controlPower(User $user, Server $server): bool
    {
        return $user->id === $server->user_id;
    }
}
