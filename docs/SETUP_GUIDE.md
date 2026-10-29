# Setup guide

The app needs PHP 8.3 or newer (`composer.json` requires `^8.3`), Composer, Node 20 or newer, and MySQL 8 or MariaDB 10.6+.

Run these commands in order from the project root.

1. Install PHP dependencies:
   ```
   composer install
   ```
2. Configure `.env`. If you do not have one yet, copy `.env.example` to `.env`. Then set these values:
   ```
   APP_NAME="Development Operations Center"
   APP_URL=https://your-host
   APP_TIMEZONE=Asia/Kolkata
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_user
   DB_PASSWORD=your_password
   DOC_CURRENCY=INR
   ```
   - Never commit `.env`.
   - Use a dedicated database user that only has rights on this database.
3. Generate the application key. Do this only if `APP_KEY` is empty:
   ```
   php artisan key:generate
   ```
4. Create the tables:
   ```
   php artisan migrate
   ```
5. Create the first administrator. You will be asked for a name and a hidden password. Nothing is stored in project files.
   ```
   php artisan doc:make-admin you@example.com
   ```
6. Build the frontend:
   ```
   npm install && npm run build
   ```
7. Set up the scheduler for the daily reminders at 09:00.
   - On Linux, use cron:
     ```
     * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
     ```
   - On Windows, add a Task Scheduler entry that runs `php artisan schedule:run` every minute.
   - For local development, `php artisan schedule:work` is enough.
8. Queue: notifications are written straight to the database, so no queue worker is needed for them. If you keep `QUEUE_CONNECTION=database`, run `php artisan queue:work` for anything you queue later.

## Optional

- `resources/views/welcome.blade.php` is the default Laravel page and nothing uses it any more. You can delete it.
- Uploaded files go to `storage/app/private`. Include that folder in your backups.

## Tests

```
php artisan test
```

The tests use an in-memory SQLite database (see `phpunit.xml`) and never touch your MySQL database. They need the PHP `pdo_sqlite` extension.
