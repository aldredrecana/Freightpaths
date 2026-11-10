# Database administration notes

PostgreSQL holds all of FreightPath's data. This document explains who can do what in the database,
how Laravel connects, the rules the database enforces on its own, how to read a query plan, and how to back up and restore.

All examples were run against the seeded demo data. The automated tests in `tests/Feature/DatabaseRulesTest.php` check
the triggers, constraints, indexes, view, and privileges described here.

## 1. Roles and privileges

Three database roles with different privileges (created by `database-admin/setup.sql`):

| Role | Used for | Privileges |
|---|---|---|
| `freightpath_owner` | Owns the database and every table. Runs migrations, seeding, tests, and backups. | Everything on its own objects, including `CREATE` |
| `freightpath_app` | The running web application | `SELECT, INSERT, UPDATE, DELETE` on tables; `USAGE, SELECT` on sequences; **`SELECT` only** on the view; **no `CREATE`** |
| `freightpath_readonly` | Reports and inspection | `SELECT` only |

**Why separate roles?** If the web app has a bug or is attacked, it cannot drop or alter tables, because its role cannot.
Day-to-day use needs only data access; changing the structure is a separate, deliberate action by the owner.

How the privileges are granted (`setup.sql`):

```sql
REVOKE ALL ON SCHEMA public FROM PUBLIC;            -- nobody gets access by default
GRANT USAGE ON SCHEMA public TO freightpath_app, freightpath_readonly;

-- Every table the owner creates later (the migrations) gets these privileges automatically:
ALTER DEFAULT PRIVILEGES FOR ROLE freightpath_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO freightpath_app;
ALTER DEFAULT PRIVILEGES FOR ROLE freightpath_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO freightpath_app;
ALTER DEFAULT PRIVILEGES FOR ROLE freightpath_owner IN SCHEMA public
    GRANT SELECT ON TABLES TO freightpath_readonly;
```

PostgreSQL treats a view like a table, so the app role would also receive write privileges on the view.
The migration `restrict_active_schedules_privileges` removes `INSERT`, `UPDATE`, and `DELETE` from it.

### Proving the privileges

```sql
SELECT grantee, string_agg(privilege_type, ',' ORDER BY privilege_type)
FROM information_schema.role_table_grants
WHERE table_name = 'schedules' AND grantee LIKE 'freightpath%'
GROUP BY grantee ORDER BY 1;
```

| grantee | privileges |
|---|---|
| freightpath_app | DELETE, INSERT, SELECT, UPDATE |
| freightpath_owner | DELETE, INSERT, REFERENCES, SELECT, TRIGGER, TRUNCATE, UPDATE |
| freightpath_readonly | SELECT |

Two live demonstrations:

```php
// php artisan tinker  (runs as freightpath_app)
DB::statement('create table should_fail (id int)');   // permission denied for schema public
```

```text
psql -h 127.0.0.1 -U freightpath_readonly -d freightpath
freightpath=> SELECT count(*) FROM drivers;      -- works
freightpath=> DELETE FROM drivers;               -- ERROR: permission denied for table drivers
```

## 2. Laravel connection settings

Two connections are defined in `config/database.php`, both reading `.env`:

| Connection | `.env` keys | Used by |
|---|---|---|
| `pgsql` (default) | `DB_USERNAME=freightpath_app`, `DB_PASSWORD` | The running web application |
| `pgsql_owner` | `DB_OWNER_USERNAME=freightpath_owner`, `DB_OWNER_PASSWORD` | `migrate*`, `db:seed`, `db:wipe`, and the test suite |

Both use the same `DB_HOST`, `DB_PORT`, and `DB_DATABASE`.

`AppServiceProvider::boot()` switches the default connection to `pgsql_owner` when Artisan runs a `migrate*`, `db:seed`, or `db:wipe`
command, so a plain `php artisan migrate:fresh --seed` works while the web app itself keeps the restricted role.
`phpunit.xml` points the tests at the `freightpath_test` database with `pgsql_owner`, so tests never touch the real data.

Passwords live only in `.env` (ignored by Git). `.env.example` and `setup.sql` contain placeholders.

## 3. Rules enforced by the database itself

These hold even if the application has a bug or someone uses SQL directly. All are created in migrations,
so a clean `migrate:fresh --seed` always recreates them.

### CHECK constraints

| Constraint | Rule |
|---|---|
| `drivers_status_check` | status in (available, on_trip, off_duty) |
| `trucks_status_check` | status in (available, on_trip, maintenance) |
| `schedules_status_check`, `logs_status_check` | status in the six trip statuses |
| `schedules_time_check` | `expected_arrival_at > departure_at` |

### Function and trigger: finished trips are read-only

```sql
CREATE FUNCTION protect_finished_trips() RETURNS trigger AS $$
BEGIN
    IF OLD.status IN ('delivered', 'cancelled') THEN
        RAISE EXCEPTION 'Trip % is % and can no longer be changed.', OLD.id, OLD.status;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_protect_finished_trips
BEFORE UPDATE ON schedules FOR EACH ROW EXECUTE FUNCTION protect_finished_trips();
```

A trigger is code the database runs automatically when an event happens. This one runs before any update on `schedules`
and refuses the change when the trip is already delivered or cancelled, so Trip history cannot be altered.

### Function and trigger: the status history is append-only

`block_log_updates()` with `trg_logs_append_only` rejects every `UPDATE` on `delivery_status_logs`.
New entries can be added, and a pending trip's entries are removed together with it (`ON DELETE CASCADE`), but no one can rewrite history.

### Partial unique indexes: one trip on the road at a time

```sql
CREATE UNIQUE INDEX schedules_one_active_trip_per_driver ON schedules (driver_id) WHERE status = 'en_route';
CREATE UNIQUE INDEX schedules_one_active_trip_per_truck  ON schedules (truck_id)  WHERE status = 'en_route';
```

Only rows whose status is `en_route` are in these indexes, and they must be unique per driver and per truck.
PostgreSQL itself refuses a second trip on the road for the same driver or truck.

### The view `active_schedules`

```sql
CREATE VIEW active_schedules AS
SELECT s.id AS schedule_id, u.name AS driver_name, t.plate_no, s.origin_address, s.destination_address,
       s.departure_at, s.expected_arrival_at, s.status
FROM schedules s
JOIN drivers d ON d.id = s.driver_id
JOIN users u   ON u.id = d.user_id
JOIN trucks t  ON t.id = s.truck_id
WHERE s.status NOT IN ('delivered', 'cancelled');
```

It joins four tables into the list a dispatcher needs and hides finished trips. It is part of the migrations,
so it survives `migrate:fresh`.

## 4. Indexes

PostgreSQL creates indexes for primary keys and UNIQUE constraints automatically. It does **not** index foreign key columns,
so we add those that our queries use.

| Index | Why |
|---|---|
| `schedules_driver_id_index`, `schedules_truck_id_index` | Joins and the overlap checks when planning a trip |
| `schedules_status_index` | The Schedules page filters by status; the dashboard counts by status |
| `delivery_status_logs_schedule_id_index` | Loading a trip's timeline |
| `schedules_one_active_trip_per_driver/truck` | Enforce one trip on the road (see section 3) |
| unique indexes on `users.email`, `drivers.license_no`, `drivers.user_id`, `trucks.plate_no`, `roles.name` | Uniqueness, and fast lookups for logins and searches |

### Reading EXPLAIN output

Query: `SELECT * FROM schedules WHERE status = 'pending';`. Run each statement on its own.

**1. Right after seeding** (the planner has no statistics yet, so it uses default estimates):

```text
Bitmap Heap Scan on schedules  (cost=4.16..9.50 rows=2 width=186) (actual time=0.017..0.018 rows=1 loops=1)
  Recheck Cond: ((status)::text = 'pending'::text)
  ->  Bitmap Index Scan on schedules_status_index  (cost=0.00..4.16 rows=2 ...)
Planning Time: 0.435 ms   Execution Time: 0.042 ms
```

**2. After `ANALYZE schedules;`** (the table has only 2 rows):

```text
Seq Scan on schedules  (cost=0.00..1.02 rows=1 width=112) (actual time=0.004..0.004 rows=1 loops=1)
  Filter: ((status)::text = 'pending'::text)
  Rows Removed by Filter: 1
```

Reading the whole table is cheaper than using an index for 2 rows, so the planner correctly skips the index.

**3. With `SET enable_seqscan = off;`** (only for the current session, to prove the index works):

```text
Index Scan using schedules_status_index on schedules  (cost=0.13..8.14 rows=1 ...)
  Index Cond: ((status)::text = 'pending'::text)
```

**4. With 5,000 finished trips and a few pending ones** (inside `BEGIN ... ROLLBACK`, then `ANALYZE schedules`):

```text
Index Scan using schedules_status_index on schedules  (cost=0.28..8.30 rows=1 width=77)
  Index Cond: ((status)::text = 'pending'::text)
```

The planner now chooses the index by itself, which is the situation the index exists for.

Script for case 4 (as `postgres` or `freightpath_owner`, after seeding):

```sql
BEGIN;
INSERT INTO schedules (driver_id, truck_id, origin_address, destination_address, departure_at, expected_arrival_at, status)
SELECT 1, 1, 'A', 'B', now() - (g || ' hours')::interval, now() - (g || ' hours')::interval + interval '1 hour', 'delivered'
FROM generate_series(1, 5000) g;
ANALYZE schedules;
EXPLAIN SELECT * FROM schedules WHERE status = 'pending';
ROLLBACK;
```

**How to read a plan:** each line is a step, read from the innermost (most indented) outward.
`cost=a..b` is the planner's estimate in arbitrary units (a = before the first row, b = total).
`rows` is the estimated row count, and with `ANALYZE`, `actual time` (milliseconds) and the real `rows` show what happened.
`Planning Time` and `Execution Time` are the totals at the bottom.
`EXPLAIN ANALYZE SELECT * FROM active_schedules;` shows the four tables joined with Hash Joins.

## 5. Backup and restore

Scripts in `database-admin/` (Windows). Edit `PGBIN` at the top if PostgreSQL is installed in a different folder.

| Task | Command | What it does |
|---|---|---|
| Back up | `.\database-admin\backup.bat` | Asks for the `freightpath_owner` password, then runs `pg_dump -Fc` and saves `backups\freightpath_<date>_<time>.backup` |
| Restore | `.\database-admin\restore.bat <file name>` | Asks for the `postgres` password, recreates a **separate** database `freightpath_restore`, and runs `pg_restore` into it |

`-Fc` is PostgreSQL's compressed custom format, which `pg_restore` can read (and restore selectively). The restore
goes into `freightpath_restore` so the real data is never overwritten by a test. Backup files contain real data,
so `database-admin/backups/` is ignored by Git.

Equivalent commands on Linux or macOS:

```bash
pg_dump -h 127.0.0.1 -U freightpath_owner -Fc -f freightpath.backup freightpath
createdb -U postgres -O freightpath_owner freightpath_restore
pg_restore -U postgres -d freightpath_restore --no-owner --role=freightpath_owner freightpath.backup
```

### Proving the restore

Run the same query on `freightpath` and on `freightpath_restore` and compare:

```sql
SELECT 'drivers' AS t, count(*) FROM drivers
UNION ALL SELECT 'trucks', count(*) FROM trucks
UNION ALL SELECT 'schedules', count(*) FROM schedules
UNION ALL SELECT 'delivery_status_logs', count(*) FROM delivery_status_logs;
```

With the demo data both give 3 drivers, 3 trucks, 2 schedules, and 5 log rows.
Do this once on your own machine before the defense so you can show it live.

## 6. Security notes and limitations

- Passwords are only in `.env` (ignored by Git); `backup.bat` prompts for the password instead of storing it.
- The roles and passwords in `setup.sql` are placeholders; change them before use.
- **No Row Level Security (RLS).** All authorization is in the application (middleware and ownership checks). RLS would need a per-request
  setting that tells PostgreSQL which user is logged in, plus policies on each table. It is a possible future improvement.
- Backups are manual; there is no schedule and no point-in-time recovery.
- A single PostgreSQL server; no replication.
