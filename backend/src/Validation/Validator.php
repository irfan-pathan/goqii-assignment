<?php
declare(strict_types=1);

namespace App\Validation;

use App\Core\HttpException;

final class Validator
{
    public const STATUSES = ['todo', 'in-progress', 'done'];
    public const PRIORITIES = ['low', 'medium', 'high'];

    /** @return array{name:string,email:string,password:string} */
    public static function register(array $in): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Name is required (max 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            // bcrypt only uses the first 72 bytes
            $errors['password'] = 'Password must be 8-72 characters.';
        }
        self::throwIf($errors);
        return compact('name', 'email', 'password');
    }

    /** @return array{email:string,password:string} */
    public static function login(array $in): array
    {
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }
        self::throwIf($errors);
        return compact('email', 'password');
    }

    /**
     *
     * @return array<string,mixed>
     */
    public static function task(array $in, bool $isCreate): array
    {
        $errors = [];
        $out = [];

        if ($isCreate || array_key_exists('title', $in)) {
            $title = trim((string) ($in['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 150) {
                $errors['title'] = 'Title is required (max 150 characters).';
            }
            $out['title'] = $title;
        }

        if (array_key_exists('description', $in)) {
            $desc = $in['description'] === null ? null : trim((string) $in['description']);
            if ($desc !== null && mb_strlen($desc) > 5000) {
                $errors['description'] = 'Description is too long (max 5000 characters).';
            }
            $out['description'] = ($desc === '') ? null : $desc;
        } elseif ($isCreate) {
            $out['description'] = null;
        }

        if ($isCreate || array_key_exists('status', $in)) {
            $status = $in['status'] ?? 'todo';
            if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
                $errors['status'] = 'Status must be one of: ' . implode(', ', self::STATUSES) . '.';
            }
            $out['status'] = $status;
        }

        if ($isCreate || array_key_exists('priority', $in)) {
            $priority = $in['priority'] ?? 'medium';
            if (!is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
                $errors['priority'] = 'Priority must be one of: ' . implode(', ', self::PRIORITIES) . '.';
            }
            $out['priority'] = $priority;
        }

        if (array_key_exists('due_date', $in)) {
            $due = $in['due_date'];
            if ($due === null || $due === '') {
                $out['due_date'] = null;
            } else {
                $d = is_string($due) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $due) : false;
                if (!$d || $d->format('Y-m-d') !== $due) {
                    $errors['due_date'] = 'Due date must be a valid date in YYYY-MM-DD format.';
                }
                $out['due_date'] = $due;
            }
        } elseif ($isCreate) {
            $out['due_date'] = null;
        }

        if (array_key_exists('user_id', $in)) {
            if (!is_int($in['user_id']) || $in['user_id'] < 1) {
                $errors['user_id'] = 'user_id must be a positive integer.';
            }
            $out['user_id'] = $in['user_id'];
        }

        self::throwIf($errors);
        if (!$isCreate && $out === []) {
            throw new HttpException(422, 'No updatable fields supplied.');
        }
        return $out;
    }

    private static function throwIf(array $errors): void
    {
        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed.', $errors);
        }
    }
}
