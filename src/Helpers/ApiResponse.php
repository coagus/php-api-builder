<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Helpers;

use Coagus\PhpApiBuilder\Http\Response;
use Coagus\PhpApiBuilder\ORM\Entity;

class ApiResponse
{
    public static function success(mixed $data, int $code = 200, ?array $meta = null, ?array $links = null): Response
    {
        $body = ['data' => self::normalizeData($data)];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        if ($links !== null) {
            $body['links'] = $links;
        }

        return new Response($body, $code);
    }

    public static function created(mixed $data, ?string $location = null): Response
    {
        $response = self::success($data, 201);

        if ($location !== null) {
            $response->header('Location', $location);
        }

        return $response;
    }

    public static function noContent(): Response
    {
        return new Response(null, 204);
    }

    public static function error(string $title, int $status, ?string $detail = null, ?string $instance = null, ?array $errors = null): Response
    {
        $body = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== null) {
            $body['detail'] = $detail;
        }

        if ($instance !== null) {
            $body['instance'] = $instance;
        }

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return new Response($body, $status);
    }

    public static function paginated(array $data, array $meta): Response
    {
        return self::success(
            array_map(fn($item) => self::normalizeItem($item), $data),
            200,
            $meta
        );
    }

    private static function normalizeData(mixed $data): mixed
    {
        if ($data instanceof Entity) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return array_map(fn($item) => self::normalizeItem($item), $data);
        }

        return $data;
    }

    private static function normalizeItem(mixed $item): mixed
    {
        if ($item instanceof Entity) {
            return $item->toArray();
        }

        return $item;
    }
}
