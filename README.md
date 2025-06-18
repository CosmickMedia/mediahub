# Store Uploader

Standalone PHP application for uploading store photos/videos to Google Drive.

## Installation

1. Copy `config.example.php` to `config.php` and update the settings.
2. Run `php setup.php` to create the database tables and default admin user.
3. Upload the project files to your PHP host.
4. Access `public/index.php` for store uploads and `admin/login.php` for the admin portal.

## Deployment

A simple `deploy.sh` script is provided to rsync the files to a remote host. Edit the script with your server details.
