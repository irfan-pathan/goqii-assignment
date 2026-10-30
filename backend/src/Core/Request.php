<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,string> route params, filled by the Router */
    public array $params = [];
    /** @var array{id:int,role:string}|null set by AuthMiddleware */
    public ?array $user = null;

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly ?string $rawBody,
        private readonly array $headers
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Allow mounting under a sub-folder (e.g. WAMP): keep everything after "/api".
        $pos = strpos($uri, '/api');
        $path = $pos === false ? $uri : substr($uri, $pos + 4);
        $path = '/' . trim($path, '/');

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] ??= $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            file_get_contents('php://input') ?: null,
            $headers
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $h = $this->header('authorization');
        return ($h !== null && preg_match('/^Bearer\s+(.+)$/i', $h, $m)) ? $m[1] : null;
    }

    public function json(): array
    {
        if ($this->rawBody === null || trim($this->rawBody) === '') {
            return [];
        }
        $data = json_decode($this->rawBody, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Request body must be a valid JSON object.');
        }
        return $data;
    }
}
