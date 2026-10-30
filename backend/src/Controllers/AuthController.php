<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\UserRepository;
use App\Validation\Validator;

final class AuthController
{
    private const COOKIE = 'refresh_token';
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RefreshTokenRepository $refreshTokens = new RefreshTokenRepository()
    ) {
    }

    public function register(Request $r): void
    {
        $in = Validator::register($r->json());
        if ($this->users->findByEmail($in['email']) !== null) {
            throw new HttpException(409, 'An account with this email already exists.', ['email' => 'Email already registered.']);
        }
        $id = $this->users->create($in['name'], $in['email'], password_hash($in['password'], PASSWORD_DEFAULT));
        $user = $this->users->findById($id);
        Response::json($this->session($user), 201);
    }

    public function login(Request $r): void
    {
        $in = Validator::login($r->json());
        $user = $this->users->findByEmail($in['email']);
        $ok = password_verify($in['password'], $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT));
        if (!$user || !$ok) {
            throw new HttpException(401, 'Invalid email or password.');
        }
        Response::json($this->session($user));
    }

    public function refresh(Request $r): void
    {
        $plain = $_COOKIE[self::COOKIE] ?? null;
        $userId = is_string($plain) ? $this->refreshTokens->consume($plain) : null;
        $user = $userId !== null ? $this->users->findById($userId) : null;
        if ($user === null) {
            $this->clearCookie();
            throw new HttpException(401, 'Session expired. Please log in again.');
        }
        Response::json($this->session($user));
    }

    public function logout(Request $r): void
    {
        $plain = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($plain)) {
            $this->refreshTokens->revoke($plain);
        }
        $this->clearCookie();
        Response::json(null, 204);
    }

    public function me(Request $r): void
    {
        $user = $this->users->findById($r->user['id']);
        if ($user === null) {
            throw new HttpException(401, 'Account no longer exists.');
        }
        Response::json(['user' => UserRepository::publicView($user)]);
    }

    private function session(array $user): array
    {
        $ttl = (int) Env::get('REFRESH_TOKEN_TTL', '604800');
        $refresh = $this->refreshTokens->issue((int) $user['id'], $ttl);
        setcookie(self::COOKIE, $refresh, [
            'expires' => time() + $ttl,
            'path' => '/',
            'httponly' => true,
            'secure' => Env::get('COOKIE_SECURE', 'false') === 'true',
            'samesite' => 'Lax',
        ]);
        return [
            'user' => UserRepository::publicView($user),
            'access_token' => Jwt::issue((int) $user['id'], $user['role']),
            'expires_in' => Jwt::accessTtl(),
        ];
    }

    private function clearCookie(): void
    {
        setcookie(self::COOKIE, '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}
