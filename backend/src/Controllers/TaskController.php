<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Validation\Validator;

final class TaskController
{
    public function __construct(
        private readonly TaskRepository $tasks = new TaskRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly AuditRepository $audit = new AuditRepository()
    ) {
    }

    public function index(Request $r): void
    {
        $status = $r->query['status'] ?? null;
        if ($status !== null && $status !== '' && !in_array($status, Validator::STATUSES, true)) {
            throw new HttpException(422, 'Validation failed.', ['status' => 'Invalid status filter.']);
        }
        $status = ($status === '' ? null : $status);

        $page = filter_var($r->query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($r->query['per_page'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($page === false || $perPage === false) {
            throw new HttpException(422, 'Validation failed.', ['page' => 'page must be >= 1 and per_page between 1 and 100.']);
        }

        if ($this->isAdmin($r)) {
            $owner = filter_var($r->query['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $ownerId = $owner === false ? null : $owner;
        } else {
            $ownerId = $r->user['id'];
        }

        $result = $this->tasks->paginate($ownerId, $status, $page, $perPage);
        Response::json([
            'data' => array_map([self::class, 'present'], $result['items']),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'total_pages' => max(1, (int) ceil($result['total'] / $perPage)),
            ],
        ]);
    }

    public function show(Request $r): void
    {
        Response::json(['data' => self::present($this->authorized($r))]);
    }

    public function store(Request $r): void
    {
        $data = Validator::task($r->json(), true);
        $ownerId = $r->user['id'];

        if (isset($data['user_id']) && $data['user_id'] !== $ownerId) {
            $this->assertAdmin($r);
            if ($this->users->findById($data['user_id']) === null) {
                throw new HttpException(422, 'Validation failed.', ['user_id' => 'User does not exist.']);
            }
            $ownerId = $data['user_id'];
        }

        $id = $this->tasks->create($ownerId, $data);
        if ($ownerId !== $r->user['id']) {
            $this->audit->log($r->user['id'], 'task.create', 'task', $id, ['for_user' => $ownerId, 'title' => $data['title']]);
        }
        Response::json(['data' => self::present($this->tasks->find($id))], 201);
    }

    public function update(Request $r): void
    {
        $task = $this->authorized($r);
        $data = Validator::task($r->json(), false);

        if (isset($data['user_id'])) {
            if ($data['user_id'] === (int) $task['user_id']) {
                unset($data['user_id']); // no-op reassignment
            } else {
                $this->assertAdmin($r); // only admins can move a task to another owner
                if ($this->users->findById($data['user_id']) === null) {
                    throw new HttpException(422, 'Validation failed.', ['user_id' => 'User does not exist.']);
                }
            }
        }

        $this->tasks->update((int) $task['id'], $data);
        if ((int) $task['user_id'] !== $r->user['id']) {
            $this->audit->log($r->user['id'], 'task.update', 'task', (int) $task['id'], ['owner' => (int) $task['user_id'], 'changes' => $data]);
        }
        Response::json(['data' => self::present($this->tasks->find((int) $task['id']))]);
    }

    public function destroy(Request $r): void
    {
        $task = $this->authorized($r);
        $this->tasks->delete((int) $task['id']);
        if ((int) $task['user_id'] !== $r->user['id']) {
            $this->audit->log($r->user['id'], 'task.delete', 'task', (int) $task['id'], ['owner' => (int) $task['user_id'], 'title' => $task['title']]);
        }
        Response::json(null, 204);
    }

    private function authorized(Request $r): array
    {
        $id = filter_var($r->params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $task = $id === false ? null : $this->tasks->find($id);
        if ($task === null) {
            throw new HttpException(404, 'Task not found.');
        }
        if (!$this->isAdmin($r) && (int) $task['user_id'] !== $r->user['id']) {
            throw new HttpException(403, 'You do not have access to this task.');
        }
        return $task;
    }

    private function isAdmin(Request $r): bool
    {
        return $r->user['role'] === 'admin';
    }

    private function assertAdmin(Request $r): void
    {
        if (!$this->isAdmin($r)) {
            throw new HttpException(403, 'Only admins can assign tasks to other users.');
        }
    }

    private static function present(array $t): array
    {
        return [
            'id' => (int) $t['id'],
            'user_id' => (int) $t['user_id'],
            'title' => $t['title'],
            'description' => $t['description'],
            'status' => $t['status'],
            'priority' => $t['priority'],
            'due_date' => $t['due_date'],
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at'],
            'owner' => ['name' => $t['owner_name'], 'email' => $t['owner_email']],
        ];
    }
}
