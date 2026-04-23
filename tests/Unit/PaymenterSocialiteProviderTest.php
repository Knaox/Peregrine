<?php

namespace Tests\Unit;

use App\Services\Auth\PaymenterSocialiteProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymenterSocialiteProviderTest extends TestCase
{
    public function test_user_payload_synthesizes_email_verified_when_timestamp_present(): void
    {
        $provider = $this->buildProviderWithUserResponse([
            'id' => 7,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'email_verified_at' => '2026-04-22T15:00:00.000000Z',
        ]);

        $user = $provider->userFromToken('fake-token');

        $this->assertSame('7', $user->getId());
        $this->assertSame('Jane Doe', $user->getName());
        $this->assertSame('jane@example.com', $user->getEmail());
        $this->assertTrue($user->user['email_verified']);
    }

    public function test_user_payload_marks_email_unverified_when_timestamp_null(): void
    {
        $provider = $this->buildProviderWithUserResponse([
            'id' => 8,
            'first_name' => 'Pending',
            'last_name' => 'User',
            'email' => 'pending@example.com',
            'email_verified_at' => null,
        ]);

        $user = $provider->userFromToken('fake-token');

        $this->assertFalse($user->user['email_verified']);
    }

    public function test_name_falls_back_to_email_when_no_first_or_last_name(): void
    {
        $provider = $this->buildProviderWithUserResponse([
            'id' => 9,
            'first_name' => '',
            'last_name' => '',
            'email' => 'noname@example.com',
            'email_verified_at' => '2026-04-22T15:00:00Z',
        ]);

        $user = $provider->userFromToken('fake-token');

        $this->assertSame('noname@example.com', $user->getName());
    }

    public function test_avatar_is_derived_from_gravatar(): void
    {
        $provider = $this->buildProviderWithUserResponse([
            'id' => 10,
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'gravatar@example.com',
            'email_verified_at' => '2026-04-22T15:00:00Z',
        ]);

        $user = $provider->userFromToken('fake-token');

        $this->assertSame(
            'https://www.gravatar.com/avatar/'.md5('gravatar@example.com'),
            $user->getAvatar(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildProviderWithUserResponse(array $payload): PaymenterSocialiteProvider
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $provider = new PaymenterSocialiteProvider(
            new Request(),
            'client-id',
            'client-secret',
            'https://peregrine.test/api/auth/social/paymenter/callback',
        );
        $provider->setHttpClient($client);
        $provider->withExtraConfig([
            'authorize_url' => 'https://billing.test/oauth/authorize',
            'token_url' => 'https://billing.test/api/oauth/token',
            'user_url' => 'https://billing.test/api/me',
        ]);

        return $provider;
    }
}
