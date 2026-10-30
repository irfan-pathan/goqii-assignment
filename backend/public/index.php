<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\TaskController;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\Auth;

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

// --- CORS (the React dev server is a different origin; credentials are needed for the refresh cookie) ---
$origin = Env::get('CORS_ORIGIN', 'http://localhost:5173');
if (($_SERVER['HTTP_ORIGIN'] ?? '') === $origin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('X-Content-Type-Options: nosniff');

$auth = [[Auth::class, 'authenticate']];
$admin = [[Auth::class, 'authenticate'], [Auth::class, 'requireAdmin']];

$router = new Router();
$authC = new AuthController();
$tasksC = new TaskController();
$adminC = new AdminController();

$router->add('POST', '/auth/register', [$authC, 'register']);
$router->add('POST', '/auth/login', [$authC, 'login']);
$router->add('POST', '/auth/refresh', [$authC, 'refresh']);
$router->add('POST', '/auth/logout', [$authC, 'logout']);
$router->add('GET', '/auth/me', [$authC, 'me'], $auth);

$router->add('GET', '/tasks', [$tasksC, 'index'], $auth);
$router->add('POST', '/tasks', [$tasksC, 'store'], $auth);
$router->add('GET', '/tasks/{id}', [$tasksC, 'show'], $auth);
$router->add('PUT', '/tasks/{id}', [$tasksC, 'update'], $auth);
$router->add('PATCH', '/tasks/{id}', [$tasksC, 'update'], $auth);
$router->add('DELETE', '/tasks/{id}', [$tasksC, 'destroy'], $auth);

$router->add('GET', '/admin/users', [$adminC, 'users'], $admin);
$router->add('GET', '/admin/stats', [$adminC, 'stats'], $admin);

try {
    $router->dispatch(Request::fromGlobals());
} catch (HttpException $e) {
    $body = ['error' => $e->getMessage()];
    if ($e->errors) {
        $body['errors'] = $e->errors;
    }
    Response::json($body, $e->status);
} catch (\Throwable $e) {
    error_log((string) $e);
    Response::json(['error' => 'Internal server error.'], 500);
}
