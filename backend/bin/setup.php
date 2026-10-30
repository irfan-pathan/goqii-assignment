<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Repositories\UserRepository;

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306')),
    Env::get('DB_USER', 'root'),
    Env::get('DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec((string) file_get_contents(__DIR__ . '/../database/schema.sql'));
echo "Schema created.\n";

$users = new UserRepository();
$seed = [
    ['Admin', 'admin@gmail.com', 'Admin@123', 'admin'],
    ['Demo User', 'user@gmail.com', 'User@1234', 'user'],
];
foreach ($seed as [$name, $email, $password, $role]) {
    if ($users->findByEmail($email) === null) {
        $id = $users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), $role);
        echo "Created $role: $email / $password\n";
    }
}
echo "Done.\n";
