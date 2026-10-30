# Task Manager — PHP / MySQL / React

Full-stack task manager with JWT authentication and role-based access control.

| Task | Status |
|---|---|
| 1. Full-stack CRUD (PHP REST + MySQL + React) | ✅ Done |
| 2. Authentication & authorization (incl. refresh tokens + audit log bonus) | ✅ Done |
| 3. Schema design & query optimization | ✅ Done — see [docs/DATABASE.md](docs/DATABASE.md) |
| 4. Docker | ⏭️ Skipped |
| 5. Node.js / PostgreSQL variant | 📝 Described only (below) |
| 6. AWS / GCP deployment | 📝 Described only (below) |
| 7. AI feature | ⏭️ Skipped |

**Time spent:** _fill in_

## Stack

- **Backend:** PHP 8.1+ (no framework), PSR-4 autoloading via Composer, PDO prepared statements, `firebase/php-jwt`, `vlucas/phpdotenv`
- **Database:** MySQL 8
- **Frontend:** React 18 + Vite + React Router

I chose plain PHP over a framework so every layer (routing, validation, auth middleware, SQL) is explicit and explainable.

## Run it

Requirements: PHP 8.1+ with `pdo_mysql`, Composer, MySQL 8, Node.js 18+.

```bash
# 1. Backend
cd backend
composer install
cp .env.example .env        # then set DB_* and a long random JWT_SECRET
php bin/setup.php           # creates the database/tables and seeds demo accounts
php -S localhost:8000 -t public

# 2. Frontend (new terminal)
cd frontend
npm install
npm run dev                 # http://localhost:5173  (proxies /api to :8000)
```

Seeded accounts:

| Role | Email | Password |
|---|---|---|
| admin | admin@gmail.com | Admin@123 |
| user | user@gmail.com | User@1234 |

Self-registration always creates a `user`. Admins can only be created out-of-band (`bin/setup.php` / SQL), so the public endpoint can never be used to grant a role.

## API

All task/admin routes need `Authorization: Bearer <access token>`.

| Method | Path | Notes |
|---|---|---|
| POST | `/api/auth/register` | 201; 409 if the email exists; 422 on invalid input |
| POST | `/api/auth/login` | 401 for bad credentials (same message for unknown email / wrong password) |
| POST | `/api/auth/refresh` | Uses the httpOnly refresh cookie; rotates it |
| POST | `/api/auth/logout` | Revokes the refresh token, clears the cookie (204) |
| GET | `/api/auth/me` | Current user |
| GET | `/api/tasks?status=&page=&per_page=&user_id=` | Users: own tasks only. Admins: all (optional `user_id` filter). `per_page` max 100 |
| POST | `/api/tasks` | 201. Admins may pass `user_id` to create for someone else |
| GET/PUT/PATCH/DELETE | `/api/tasks/{id}` | 404 unknown · 403 not yours (unless admin) · 204 on delete |
| GET | `/api/admin/users` | Admin only (403 otherwise) |
| GET | `/api/admin/stats` | Admin only — task counts per user |

Errors are JSON: `{"error": "...", "errors": {"field": "..."}}` with 400 / 401 / 403 / 404 / 405 / 409 / 422 / 500 as appropriate.

## Auth & authorization design

- **Passwords:** `password_hash(PASSWORD_DEFAULT)` / `password_verify`. Login verifies against a real hash even for unknown emails (uniform timing), and returns one generic error.
- **Access token:** short-lived JWT (HS256, 15 min, `iss` checked, secret ≥ 32 chars enforced). Held **in memory** by the SPA, never in localStorage.
- **Refresh token:** random 256-bit value in an `HttpOnly; SameSite=Lax` cookie (7 days). Only its SHA-256 hash is stored. It is **single-use and rotated** on every refresh; replaying an old one fails (verified), and logout revokes it. A page reload restores the session through it.
- **Expiry handling:** the API client refreshes once on a 401 and retries; if refresh fails the user is sent to login.
- **Authorization:** enforced server-side on every request. Regular users are always scoped to their own `user_id` — a `?user_id=` query param or a `user_id` in the body is ignored/forbidden for them. The React hiding of admin UI is cosmetic only.
- **Audit log:** admin create/update/delete of *another user's* task is recorded in `audit_logs` (admin id, action, entity, details JSON).
- **Known limitation:** the role is read from the JWT, so a role change takes effect when the access token expires (≤ 15 min). Acceptable here; a production system could re-check the DB role on sensitive routes. No login rate-limiting is implemented either.

## Project layout

```
backend/   public/index.php (routes) · src/{Core,Controllers,Repositories,Middleware,Validation} · database/schema.sql · bin/setup.php
frontend/  src/{api,context,routes,pages,components}
docs/      DATABASE.md (schema + EXPLAIN analysis) · explain-output.txt
```

## Task 5 — Node.js / PostgreSQL port (described, not built)

What would change to port e.g. `GET /api/tasks` to Express + `pg`:

- **Routing/auth middleware:** `router.get('/tasks', authenticate, handler)` — same JWT verify (`jsonwebtoken`) as the PHP `Auth::authenticate`, attaching `req.user`. Express middleware is `async`/`next()` based; the PHP version throws `HttpException`, which maps to Express's error-handling middleware.
- **Connection pooling:** PHP-FPM opens a connection per request (PDO). Node is a long-lived process, so use a shared `pg.Pool` (`max`, `idleTimeoutMillis`) created once at start-up.
- **Query syntax:** positional `?` placeholders become `$1, $2…`; `LIMIT ? OFFSET ?` stays; `ENUM` becomes `CREATE TYPE … AS ENUM` (or a `CHECK`); `AUTO_INCREMENT` → `GENERATED ALWAYS AS IDENTITY`; `DATE_ADD(NOW(), INTERVAL ? SECOND)` → `NOW() + make_interval(secs => $1)`; `SUM(status = 'x')` → `COUNT(*) FILTER (WHERE status = 'x')`; `JSON` → `JSONB`. `INSERT … RETURNING id` replaces `lastInsertId()`.
- **Indexes:** the composite `(user_id, status, id)` index carries over unchanged and PostgreSQL can use it for the same backward ordered scan.
- **Passwords:** `password_hash` → `bcrypt`/`argon2` npm package (the stored `$2y$` bcrypt hashes are compatible with `bcrypt`).

## Task 6 — Cloud deployment (described, not built)

A free-tier layout I would use on AWS: **EC2 t2/t3.micro** (Nginx + PHP-FPM serving the API), **RDS MySQL** `db.t3.micro` (private subnet, security group only open to the EC2 instance), and the React `dist/` build on **S3 + CloudFront**. Secrets (`JWT_SECRET`, DB credentials) would live in **SSM Parameter Store / Secrets Manager**, read by the instance role — not committed or baked into an image. `COOKIE_SECURE=true` and `CORS_ORIGIN` set to the CloudFront domain (or route `/api` through CloudFront so the cookie stays first-party). CI/CD: a GitHub Actions workflow that runs `composer install --no-dev`, builds the frontend, syncs it to S3 and deploys the backend over SSH/CodeDeploy.
