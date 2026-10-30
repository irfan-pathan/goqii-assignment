<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class RefreshTokenRepository
{
    public function issue(int $userId, int $ttlSeconds): string
    {
        $plain = bin2hex(random_bytes(32));
        $st = Database::connection()->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
        );
        $st->execute([$userId, self::hash($plain), $ttlSeconds]);
        return $plain;
    }

    public function consume(string $plain): ?int
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'SELECT id, user_id FROM refresh_tokens
                 WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW() FOR UPDATE'
            );
            $st->execute([self::hash($plain)]);
            $row = $st->fetch();
            if (!$row) {
                $db->rollBack();
                return null;
            }
            $db->prepare('UPDATE refresh_tokens SET revoked_at = NOW() WHERE id = ?')->execute([$row['id']]);
            $db->commit();
            return (int) $row['user_id'];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function revoke(string $plain): void
    {
        Database::connection()
            ->prepare('UPDATE refresh_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL')
            ->execute([self::hash($plain)]);
    }

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
