<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\PelicanUser;
use Illuminate\Http\Client\RequestException;

/**
 * Pelican Application API — user CRUD + password management.
 */
class PelicanUserClient
{
    public function __construct(private PelicanHttpClient $http) {}

    /**
     * @throws RequestException
     */
    public function createUser(string $email, string $username, string $name): PelicanUser
    {
        $response = $this->http->request()
            ->post('/api/application/users', [
                'email' => $email,
                'username' => $username,
                'name' => $name,
            ])
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * @throws RequestException
     */
    public function deleteUser(int $pelicanUserId): void
    {
        $this->http->request()
            ->delete("/api/application/users/{$pelicanUserId}")
            ->throw();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    public function updateUser(int $pelicanUserId, array $data): PelicanUser
    {
        $response = $this->http->request()
            ->patch("/api/application/users/{$pelicanUserId}", $data)
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * @throws RequestException
     */
    public function getUser(int $pelicanUserId): PelicanUser
    {
        $response = $this->http->request()
            ->get("/api/application/users/{$pelicanUserId}")
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * @return PelicanUser[]
     *
     * @throws RequestException
     */
    public function listUsers(): array
    {
        return $this->http->fetchAllPages('/api/application/users', PelicanUser::class);
    }

    /**
     * @throws RequestException
     */
    public function findUserByEmail(string $email): ?PelicanUser
    {
        $response = $this->http->request()
            ->get('/api/application/users', ['filter[email]' => $email])
            ->throw();

        $data = $response->json('data') ?? [];

        if (empty($data)) {
            return null;
        }

        return PelicanUser::fromApiResponse($data[0]);
    }

    /**
     * @throws RequestException
     */
    public function updateUserPassword(int $pelicanUserId, string $password): void
    {
        $user = $this->getUser($pelicanUserId);
        $this->http->request()
            ->patch("/api/application/users/{$pelicanUserId}", [
                'email' => $user->email,
                'username' => $user->username,
                'name' => $user->name,
                'password' => $password,
            ])
            ->throw();
    }

    /**
     * Change a Pelican user's email. Pelican's PATCH /users/{id} validates
     * `username` (and `name`) as required even on a partial update — sending
     * just `{email}` returns 422. We fetch the current user first to preserve
     * username + name. Hides this quirk from callers (UserController profile
     * edit, SocialAuthService canonical login sync).
     *
     * @throws RequestException
     */
    public function changeUserEmail(int $pelicanUserId, string $newEmail): PelicanUser
    {
        $current = $this->getUser($pelicanUserId);

        return $this->updateUser($pelicanUserId, [
            'email' => $newEmail,
            'username' => $current->username,
            'name' => $current->name,
        ]);
    }
}
