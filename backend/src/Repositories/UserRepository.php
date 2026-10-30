<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository
{
    private const SELECT = 'SELECT u.id, u.name, u.email, u.password_hash, r.name AS role
                            FROM users u JOIN roles r ON r.id = u.role_id';

    public function findByEmail(string $email): ?array
    {
        $st = Database::connection()->prepare(self::SELECT . ' WHERE u.email = ?');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $st = Database::connection()->prepare(self::SELECT . ' WHERE u.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function create(string $name, string $email, string $passwordHash, string $role = 'user'): int
    {
        $db = Database::connection();
        $st = $db->prepare('INSERT INTO users (name, email, password_hash, role_id)
                            VALUES (?, ?, ?, (SELECT id FROM roles WHERE name = ?))');
        $st->execute([$name, $email, $passwordHash, $role]);
        return (int) $db->lastInsertId();
    }

    /** @return list<array{id:int,name:string,email:string,role:string}> */
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT u.id, u.name, u.email, r.name AS role
                     FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.name')
            ->fetchAll();
    }

    public static function publicView(array $u): array
    {
        return ['id' => (int) $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
    }
}
