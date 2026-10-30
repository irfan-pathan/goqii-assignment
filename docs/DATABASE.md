# Task 3 — Database design & query optimization

Full DDL: [`backend/database/schema.sql`](../backend/database/schema.sql). Raw EXPLAIN output: [`explain-output.txt`](explain-output.txt).

## Schema

```
roles(id PK, name UNIQUE)
  └─< users(id PK, name, email UNIQUE, password_hash, role_id FK→roles)
        ├─< tasks(id PK, user_id FK→users ON DELETE CASCADE, title, description,
        │         status ENUM, priority ENUM, due_date, created_at, updated_at)
        ├─< refresh_tokens(id PK, user_id FK, token_hash UNIQUE, expires_at, revoked_at)
        └─< audit_logs(id PK, admin_id FK→users, action, entity, entity_id, details JSON, created_at)
```

Design decisions

| Decision | Why |
|---|---|
| `roles` is its own table, `users.role_id` is a FK | Roles are data, not a string typed into a column. Adding e.g. `manager` is an INSERT, and a typo cannot create a bogus role. |
| `status` / `priority` are `ENUM` | The DB rejects invalid values even if the API validation is bypassed; 1 byte each. (Trade-off: adding a value needs an `ALTER`.) |
| `tasks.user_id ... ON DELETE CASCADE` | No orphaned tasks when a user is removed. |
| `users.email UNIQUE` | Enforces one account per email at the DB level, so a race between two registrations cannot create duplicates (the API also checks first for a friendly 409). |
| `refresh_tokens.token_hash CHAR(64)` | Only the SHA-256 of the token is stored. A DB leak does not give the attacker usable tokens. `UNIQUE` makes lookup an index probe. |
| `utf8mb4` + InnoDB everywhere | Full Unicode, transactions, FK enforcement. |

## Indexes on `tasks`

| Index | Serves |
|---|---|
| `PRIMARY (id)` | Lookup by id; `ORDER BY id DESC` is "newest first" (auto-increment ⇒ chronological), so no sort on `created_at` is needed. |
| `idx_tasks_user_status_id (user_id, status, id)` | A user's tasks, with or without a status filter. Equality on the leading columns, then `id` is already ordered ⇒ **no filesort**. With only `user_id`, the leftmost prefix is used. Also covers the per-user `COUNT(*)` and the per-user stats query. |
| `idx_tasks_status_id (status, id)` | Admin list filtered by status (not scoped to a user), newest first. |

The FK on `user_id` needs an index anyway; `idx_tasks_user_status_id` provides it, so there is no redundant single-column `user_id` index.

## The queries the app actually runs

All in [`TaskRepository.php`](../backend/src/Repositories/TaskRepository.php). Values are bound parameters (never concatenated); only the whitelisted column names in `update()` are interpolated.

**Q1 — a user's tasks by status (paginated)**
```sql
SELECT t.*, u.name AS owner_name, u.email AS owner_email
FROM tasks t JOIN users u ON u.id = t.user_id
WHERE t.user_id = ? AND t.status = ?
ORDER BY t.id DESC LIMIT ? OFFSET ?;
```

**Q2 — admin view of all tasks with owner info** (same statement, no `user_id` predicate; optional `status`)

**Q3 — task counts per user (admin dashboard)**
```sql
SELECT u.id, u.name, u.email, COUNT(t.id) total,
       COALESCE(SUM(t.status='todo'),0) todo,
       COALESCE(SUM(t.status='in-progress'),0) in_progress,
       COALESCE(SUM(t.status='done'),0) done
FROM users u LEFT JOIN tasks t ON t.user_id = u.id
GROUP BY u.id, u.name, u.email ORDER BY total DESC, u.name;
```

## EXPLAIN results

Measured on MySQL 8.4 with a temporary data set of 100,000 tasks over 200 users (removed afterwards). Raw output in [`explain-output.txt`](explain-output.txt).

| Query | Plan | Reading |
|---|---|---|
| **Q1** `user_id=50 AND status='todo' ORDER BY id DESC LIMIT 10` | `type=ref`, `key=idx_tasks_user_status_id`, `key_len=5` (user_id + status), `rows≈167`, `Backward index scan`, no `Using filesort` | Both predicates are used by the index, and the order comes from the index, so MySQL reads ~10 index entries instead of scanning. `key_len=5` proves both columns are used. |
| **Q2** `status='done' ... JOIN users` | `t`: `ref` on `idx_tasks_status_id`, backward scan; `u`: `eq_ref` on `PRIMARY`, `rows=1` | Owner info costs one primary-key lookup per returned row (10 per page). `rows=49913` is the estimate of matching rows, but `LIMIT 10` stops the backward scan after 10, so it is not read in full. |
| **Q3** counts per user | `u`: `ALL` (203 rows); `t`: `ref`, `key=idx_tasks_user_status_id`, **`Using index`** | The scan of `users` is intentional (every user must appear, including those with 0 tasks). The `tasks` side is a *covering* index read: `user_id`, `status`, `id` are all in the index, so the table rows are never touched. `Using temporary; Using filesort` comes from `GROUP BY` + `ORDER BY total` on a computed value; with the number of users (not tasks) as input this is small. |
| `COUNT(*)` for pagination meta | `ref`, `Using index` | Counted from the index alone. This query deliberately has no join, since the filters only touch `tasks`. |

### Tuning notes and honest caveats

- **Count query without the JOIN**: the pagination total does not need owner info, so it avoids the join entirely.
- **Index column order matters**: `(user_id, status, id)` puts the equality columns first and the `ORDER BY` column last. `(status, user_id)` or `(user_id, created_at)` would not give filesort-free ordering for these queries.
- **Baseline caveat**: with both indexes ignored the optimizer chose a backward `PRIMARY` scan and *estimated* `rows=10`. That estimate assumes matching rows are spread densely along the primary key. It is an estimate and is not reliable for rare combinations (e.g. a user with few `todo` tasks would force reading much more). The composite index gives a bounded cost regardless of data distribution, which is the real reason for it; I did not time the baseline, so I'm not claiming a measured speedup number.
- **Offset pagination** (`LIMIT ? OFFSET ?`) is fine for this scale, but deep pages get slower because skipped rows are still read. Keyset pagination (`WHERE id < :last_id`) is the next step if lists grow large.
- **Not indexed on purpose**: `due_date` and `priority` are never used in filters, so indexing them would only slow writes.
