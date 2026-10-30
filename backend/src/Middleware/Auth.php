<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Request;

final class Auth
{
    public static function authenticate(Request $request): void
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new HttpException(401, 'Authentication required.');
        }
        $request->user = Jwt::verify($token);
    }
    public static function requireAdmin(Request $request): void
    {
        if (($request->user['role'] ?? null) !== 'admin') {
            throw new HttpException(403, 'Admin access required.');
        }
    }
}
