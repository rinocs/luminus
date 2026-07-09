# Database Migrations

Luminus provides a lightweight, raw SQL-based database migration system. It allows developers to version-control the database schema using simple `.up.sql` and `.down.sql` scripts.

## Directory Structure

All migration files are stored in the `database/migrations/` directory:

```
database/
  migrations/
    20260709120000_create_users_table.up.sql
    20260709120000_create_users_table.down.sql
```

Migrations are prefixed with a timestamp (`YYYYMMDDHHMMSS`) to ensure chronological order of execution and avoid conflicts in team environments.

---

## Commands

All migration operations are controlled via the `bin/migrate` CLI script.

### Create a Migration
Generate a new pair of migration files containing empty placeholders:
```bash
php bin/migrate create create_users_table
```
This will create:
* `database/migrations/YYYYMMDDHHMMSS_create_users_table.up.sql`
* `database/migrations/YYYYMMDDHHMMSS_create_users_table.down.sql`

### Run Pending Migrations
Execute all unapplied migrations in chronological order:
```bash
php bin/migrate up
```
Applied migrations are recorded in the `migrations` table in the database under a new batch number.

### Rollback last batch
Revert the last batch of executed migrations:
```bash
php bin/migrate rollback
```

### Check Migration Status
View the status of each migration file:
```bash
php bin/migrate status
```

---

## Writing Migrations

Luminus migrations use raw database-native SQL. 

### Example `up.sql`
```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Example `down.sql`
```sql
DROP TABLE IF EXISTS `users`;
```
