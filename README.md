# Store Uploader

Standalone PHP application for uploading store photos/videos to Google Drive.

## Installation

1. Copy `config.example.php` to `config.php` and update the settings.
2. Run `php setup.php` to create the database tables and default admin user.
3. Upload the project files to your PHP host.
4. Access `public/index.php` for store uploads and `admin/login.php` for the admin portal.

### Upgrading

If you update the application and encounter errors related to missing database columns,
run `php update_database.php` to apply the latest schema changes.
This includes the new `first_name` and `last_name` columns for store users.

### Google Login

Fill in the `google_oauth` settings in `config.php` to enable "Login with Google" on the admin login page. The email returned by Google must match a username in the `users` table.

## Deployment

A simple `deploy.sh` script is provided to rsync the files to a remote host. Edit the script with your server details.
