<?php

declare(strict_types=1);

namespace App;

use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Auth\Auth as JwtAuth;
use Coagus\PhpApiBuilder\Resource\APIDB;
use App\Entities\User;

#[PublicResource]
class Auth extends APIDB
{
    protected string $entity = User::class;

    public function postRegister(): void
    {
        $input = $this->getInput();

        $existing = User::query()->where('email', $input->email ?? '')->first();
        if ($existing) {
            $this->error('Conflict', 409, 'A user with this email already exists.');
            return;
        }

        $user = new User();
        $user->fill([
            'name' => $input->name ?? '',
            'email' => $input->email ?? '',
            'password' => password_hash($input->password ?? '', PASSWORD_DEFAULT),
        ]);
        $user->save();

        $token = JwtAuth::generateAccessToken($user->toArray());
        $this->created([
            'user' => $user->toArray(),
            'token' => $token,
        ]);
    }

    public function postLogin(): void
    {
        $input = $this->getInput();
        $user = User::query()->where('email', $input->email ?? '')->first();

        if (!$user || !password_verify($input->password ?? '', $user->password)) {
            $this->error('Unauthorized', 401, 'Invalid email or password.');
            return;
        }

        $accessToken = JwtAuth::generateAccessToken($user->toArray());
        $refreshToken = JwtAuth::generateRefreshToken($user->id);

        $this->success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function postRefresh(): void
    {
        $input = $this->getInput();
        $refreshToken = $input->refresh_token ?? ($input->refreshToken ?? null);

        if (!$refreshToken) {
            $this->error('Bad Request', 400, 'refresh_token is required.');
            return;
        }

        try {
            $decoded = JwtAuth::validateToken($refreshToken);

            if (($decoded->type ?? null) !== 'refresh') {
                $this->error('Unauthorized', 401, 'Invalid refresh token.');
                return;
            }

            $user = User::find((int) $decoded->sub);
            if (!$user) {
                $this->error('Unauthorized', 401, 'User not found.');
                return;
            }

            $accessToken = JwtAuth::generateAccessToken($user->toArray());
            $newRefreshToken = JwtAuth::generateRefreshToken($user->id, $decoded->family_id ?? null);

            $this->success([
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
            ]);
        } catch (\Exception $e) {
            $this->error('Unauthorized', 401, 'Invalid or expired refresh token.');
        }
    }
}
