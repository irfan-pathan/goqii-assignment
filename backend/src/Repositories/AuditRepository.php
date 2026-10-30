<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AuditRepository
{
    public function log(int $adminId, string $action, string $entity, ?int $entityId, array $details = []): void
    {
        Database::connection()
            ->prepare('INSERT INTO audit_logs (admin_id, action, entity, entity_id, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$adminId, $action, $entity, $entityId, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}
