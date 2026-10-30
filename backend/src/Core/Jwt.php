<?php
declare(strict_types=1);

namespace App\Core;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

final class Jwt
{
    private const ALG = 'HS256';
    private const ISSUER = 'goqii-task-manager';

    public static function issue(int $userId, string $role): string
    {
        $now = time();
        return FirebaseJwt::encode([
            'iss' => self::ISSUER,
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + self::accessTtl(),
        ], self::secret(), self::ALG);
    }

    /** @return array{id:int,role:string} */
    public static function verify(string $token): array
    {
        try {
            $p = FirebaseJwt::decode($token, new Key(self::secret(), self::ALG));
        } catch (\Throwable) {
            throw new HttpException(401, 'Invalid or expired access token.');
        }
        if (($p->iss ?? null) !== self::ISSUER) {
            throw new HttpException(401, 'Invalid access token.');
        }
        return ['id' => (int) $p->sub, 'role' => (string) $p->role];
    }

    public static function accessTtl(): int
    {
        return (int) Env::get('ACCESS_TOKEN_TTL', '900');
    }

    private static function secret(): string
    {
        $s = Env::get('JWT_SECRET');
        if ($s === null || strlen($s) < 32) {
            throw new \RuntimeException('JWT_SECRET must be set to at least 32 characters.');
        }
        return $s;
    }
}
