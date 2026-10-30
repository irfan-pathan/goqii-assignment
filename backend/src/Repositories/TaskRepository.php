<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TaskRepository
{
    private const SELECT = 'SELECT t.id, t.user_id, t.title, t.description, t.status, t.priority,
                                   t.due_date, t.created_at, t.updated_at,
                                   u.name AS owner_name, u.email AS owner_email
                            FROM tasks t JOIN users u ON u.id = t.user_id';

    /**
     * @param int|null    $userId
     * @param string|null $status
     * @return array{items:list<array>,total:int}
     */
    public function paginate(?int $userId, ?string $status, int $page, int $perPage): array
    {
        $where = [];
        $args = [];
        if ($userId !== null) {
            $where[] = 't.user_id = ?';
            $args[] = $userId;
        }
        if ($status !== null) {
            $where[] = 't.status = ?';
            $args[] = $status;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $db = Database::connection();

        $st = $db->prepare('SELECT COUNT(*) FROM tasks t' . $whereSql);
        $st->execute($args);
        $total = (int) $st->fetchColumn();

        $st = $db->prepare(self::SELECT . $whereSql . ' ORDER BY t.id DESC LIMIT ? OFFSET ?');
        $i = 1;
        foreach ($args as $a) {
            $st->bindValue($i++, $a, is_int($a) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $st->bindValue($i++, $perPage, PDO::PARAM_INT);
        $st->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();

        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $st = Database::connection()->prepare(self::SELECT . ' WHERE t.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $db = Database::connection();
        $db->prepare('INSERT INTO tasks (user_id, title, description, status, priority, due_date)
                      VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $data['title'], $data['description'], $data['status'], $data['priority'], $data['due_date']]);
        return (int) $db->lastInsertId();
    }

    /** @param array<string,mixed> $data whitelisted column => value */
    public function update(int $id, array $data): void
    {
        $allowed = ['title', 'description', 'status', 'priority', 'due_date', 'user_id'];
        $sets = [];
        $args = [];
        foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) { // column names come from this whitelist, never from input
                $sets[] = "$col = ?";
                $args[] = $val;
            }
        }
        if ($sets === []) {
            return;
        }
        $args[] = $id;
        Database::connection()->prepare('UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    }

    public function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
    }

    public function countsPerUser(): array
    {
        return Database::connection()->query(
            "SELECT u.id, u.name, u.email,
                    COUNT(t.id) AS total,
                    COALESCE(SUM(t.status = 'todo'), 0) AS todo,
                    COALESCE(SUM(t.status = 'in-progress'), 0) AS in_progress,
                    COALESCE(SUM(t.status = 'done'), 0) AS done
             FROM users u LEFT JOIN tasks t ON t.user_id = u.id
             GROUP BY u.id, u.name, u.email
             ORDER BY total DESC, u.name"
        )->fetchAll();
    }
}
