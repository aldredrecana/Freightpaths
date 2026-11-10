# FreightPath

**An IoT-Enabled Web-Based Driver Management System for Trucking Operations**

FreightPath helps small and medium trucking businesses keep their drivers, trucks, trips, and delivery records in one place.
This repository is the **midterm project (Phase 1)** for COMP 016 Web Development and COMP 018 Database Administration,
Polytechnic University of the Philippines, Calauan Campus. It is also the foundation of our Capstone.

**Team:** [Member 1], [Member 2], [Member 3]

## What it does (Phase 1)

| Area | What you can do |
|---|---|
| Driver Management | Admin creates drivers (login account and profile together); admin and dispatcher view them; license-expiry warnings |
| Truck Management | Admin manages trucks; statuses available, on trip, maintenance |
| Schedule Management | Dispatcher plans trips (driver, truck, route, times) with overlap, license, and availability checks |
| Driver app screens | Drivers see only their own trips, then accept, start, and finish them; they can decline with a reason |
| Reassignment | A declined trip goes to the top of the dispatcher's list; they pick a replacement, who must accept again |
| Transaction History | Every status change is logged with who, when, and a remark; finished trips move to a Trip history page with a timeline |
| Dashboard | Staff: alerts, counts, today's trips, licenses to renew. Drivers: current trip and new trips |

**Not in Phase 1 (Capstone):** the ESP32 GPS tracker, GPS tracking with regularly updated location data, route visualization,
and the map. See [Limitations](docs/DESIGN_DECISIONS.md#known-limitations).

## Roles

| Role | Can do |
|---|---|
| `admin` | Everything: manage drivers, trucks, trips; cancel and reassign trips; view history |
| `dispatcher` | View drivers and trucks; create, edit, reassign, and cancel trips; view history |
| `driver` | See own trips; accept, start, deliver, or decline them |

Anyone who registers on the public Register page gets the `driver` role with no profile (least privilege) and cannot open any staff page.
Public registration can be switched off with `FREIGHTPATH_PUBLIC_REGISTRATION=false`.

## Tech stack

Laravel 13, PHP 8.3+, PostgreSQL, Blade, Tailwind CSS, Alpine.js (Laravel Breeze for authentication), PHPUnit.

## Quick start (Windows)

You need: PHP 8.3+, Composer, Node.js, PostgreSQL, and Laravel Herd (or `php artisan serve`).

1. **Clone** the repository and open a terminal in the project folder.
2. **Create the database and roles.** Open `database-admin/setup.sql`, change the three `change_me_...` passwords, then run it once as the PostgreSQL superuser:
   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -U postgres -f database-admin\setup.sql
   ```
   It is safe to run again. It creates the roles, the `freightpath` database, and a `freightpath_test` database for the tests.
3. **Configure the app.**
   ```powershell
   copy .env.example .env
   ```
   Put the same passwords in `.env` (`DB_PASSWORD` for `freightpath_app`, `DB_OWNER_PASSWORD` for `freightpath_owner`).
4. **Install and build.**
   ```powershell
   composer install
   php artisan key:generate
   npm.cmd install
   npm.cmd run build
   ```
   (`npm.cmd` avoids PowerShell's script-execution policy.)
5. **Create the tables and demo data** (runs from a clean database every time):
   ```powershell
   php artisan migrate:fresh --seed
   ```
6. **Run it:** open `http://freightpath.test` with Herd, or run `php artisan serve` and open `http://127.0.0.1:8000`.

## Demo accounts

Created by the seeders (log in on the **Dispatcher** tab for staff, **Driver** tab for drivers).

| Role | Email | Password |
|---|---|---|
| Admin | `admin@freightpath.test` | `Admin12345` |
| Dispatcher | `dispatcher@freightpath.test` | `Dispatcher12345` |
| Driver (has a finished trip) | `juan@freightpath.test` | `Driver12345` |
| Driver (has a pending trip) | `maria@freightpath.test` | `Driver12345` |
| Driver (free, good for reassignment) | `pedro@freightpath.test` | `Driver12345` |

These are demo passwords for local use only.

## Useful commands

| Task | Command |
|---|---|
| Rebuild everything from scratch | `php artisan migrate:fresh --seed` |
| Run the automated tests | `php artisan test` |
| Check/fix code style (Laravel Pint) | `php vendor/bin/pint --test` / `php vendor/bin/pint` |
| Back up the database | `.\database-admin\backup.bat` |
| Restore a backup into a test database | `.\database-admin\restore.bat <backup file name>` |

`migrate:fresh --seed` and the tests connect as the **owner** database role (the only one allowed to create tables);
the running web app connects as the restricted **app** role. See [docs/DATABASE_ADMIN.md](docs/DATABASE_ADMIN.md).

## How a request flows (MVC)

Example: the admin adds a driver.

1. The browser sends `POST /drivers`.
2. **Route** (`routes/web.php`) matches it and runs the **middleware** `auth` (must be logged in) and `role:admin` (`App\Http\Middleware\EnsureUserHasRole`, which returns 403 otherwise).
3. **Controller** `DriverController@store` validates the input.
4. Inside one database transaction, the **models** `User` and `Driver` create both rows (`$fillable` limits mass assignment, and `role_id` is set on the server, never taken from the form).
5. The controller redirects to the driver list, a **view** (Blade) that shows the data the controller prepared, and a success message appears.

## Project map

| Path | What is there |
|---|---|
| `app/Http/Controllers/` | Driver, Truck, Schedule, Transaction, Dashboard controllers |
| `app/Http/Middleware/EnsureUserHasRole.php` | Role check used by the routes |
| `app/Models/` | `User`, `Role`, `Driver`, `Truck`, `Schedule`, `DeliveryStatusLog` |
| `database/migrations/` | Tables, constraints, triggers, indexes, and the view |
| `database/seeders/` | Roles, admin account, demo drivers/trucks/trips |
| `database-admin/` | `setup.sql` (roles and privileges), `backup.bat`, `restore.bat` |
| `resources/views/` | Blade pages and shared components (`components/`) |
| `routes/web.php` | All routes and their role protection |
| `tests/` | Automated tests (PHPUnit, run on PostgreSQL) |
| `docs/` | ERD, database administration notes, design decisions |

## Documentation

- [docs/ERD.md](docs/ERD.md): entity-relationship diagram, data dictionary, normalization
- [docs/DATABASE_ADMIN.md](docs/DATABASE_ADMIN.md): roles and privileges, constraints, indexes, EXPLAIN, trigger, view, backup and restore
- [docs/DESIGN_DECISIONS.md](docs/DESIGN_DECISIONS.md): decisions and why, trip status flow, role matrix, limitations, defense questions
