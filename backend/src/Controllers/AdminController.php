<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;

final class AdminController
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TaskRepository $tasks = new TaskRepository()
    ) {
    }

    public function users(Request $r): void
    {
        Response::json(['data' => $this->users->all()]);
    }

    public function stats(Request $r): void
    {
        $rows = array_map(static fn (array $row) => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'total' => (int) $row['total'],
            'todo' => (int) $row['todo'],
            'in_progress' => (int) $row['in_progress'],
            'done' => (int) $row['done'],
        ], $this->tasks->countsPerUser());
        Response::json(['data' => $rows]);
    }
}
