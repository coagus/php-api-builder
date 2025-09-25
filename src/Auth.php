<?php
namespace ApiBuilder;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
  public function getToken($user)
  {
    $time = time();
    $payload = [
      "iat" => $time,
      "exp" => $time + 60 * $_ENV[EXP],
      "data" => $user
    ];

    $token = '';

    try {
      $token = JWT::encode($payload, $_ENV[KEY], $_ENV[ALG]);
    } catch (\Exception $e) {
      error($e->getMessage(), SC_ERROR_UNAUTHORIZED);
    }

    return $token;
  }

  public function validateSession()
  {
    $headers = apache_request_headers();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');

    try {
      JWT::decode($token, new Key($_ENV[KEY], $_ENV[ALG]));
      return true;
    } catch (\Exception $e) {
      error("Authentication required: " . $e->getMessage(), SC_ERROR_UNAUTHORIZED);
    }
  }
}