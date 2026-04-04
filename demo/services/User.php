<?php

declare(strict_types=1);

namespace DemoApi;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Auth\Auth;
use Coagus\PhpApiBuilder\Auth\RefreshTokenStore;
use Coagus\PhpApiBuilder\Resource\APIDB;

class User extends APIDB
{
    protected string $entity = \DemoApi\Entities\User::class;

    #[PublicResource]
    public function postLogin(): void
    {
        $input = $this->getInput();

        if (!isset($input->email) || !isset($input->password)) {
            $this->error('Bad Request', 400, 'Email and password are required.');
            return;
        }

        // Find user by email
        $user = \DemoApi\Entities\User::query()
            ->where('email', $input->email)
            ->first();

        if ($user === null) {
            $this->error('Unauthorized', 401, 'Invalid credentials.');
            return;
        }

        // Generate tokens
        $userData = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
        $accessToken = Auth::generateAccessToken($userData, ['users:read', 'orders:read']);
        $refreshToken = Auth::generateRefreshToken($user->id);

        // Store refresh token
        $decoded = Auth::decodeToken($refreshToken);
        RefreshTokenStore::store(
            $decoded->jti,
            $user->id,
            hash('sha256', $refreshToken),
            $decoded->family_id,
            $decoded->exp
        );

        $this->success([
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'expiresIn' => 900,
            'tokenType' => 'Bearer',
        ]);
    }

    #[PublicResource]
    public function postRefresh(): void
    {
        $input = $this->getInput();

        if (!isset($input->refreshToken)) {
            $this->error('Bad Request', 400, 'Refresh token is required.');
            return;
        }

        try {
            $result = RefreshTokenStore::rotateToken($input->refreshToken);

            $user = \DemoApi\Entities\User::find($result['userId']);
            $userData = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
            $accessToken = Auth::generateAccessToken($userData, ['users:read', 'orders:read']);

            $this->success([
                'accessToken' => $accessToken,
                'refreshToken' => $result['refreshToken'],
                'expiresIn' => 900,
                'tokenType' => 'Bearer',
            ]);
        } catch (\RuntimeException $e) {
            $this->error('Unauthorized', 401, $e->getMessage());
        }
    }

    public function postDeactivate(): void
    {
        if ($this->resourceId === null) {
            $this->error('Resource ID required', 400);
            return;
        }

        $user = \DemoApi\Entities\User::find((int) $this->resourceId);
        if ($user === null) {
            $this->error('Not Found', 404);
            return;
        }

        $user->active = false;
        $user->save();

        $this->success(['message' => 'User deactivated']);
    }
}
