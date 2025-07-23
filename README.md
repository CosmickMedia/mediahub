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

Upload the project to your PHP host using rsync, Git, or FTP. The previous
`deploy.sh` helper script has been removed, so use whichever deployment method
best fits your environment.

## Versioning

The current version is tracked in the `VERSION` file. Run `php scripts/bump_version.php` after committing changes to automatically bump the patch number and append the latest commit message to `CHANGELOG.md`. The admin interface displays this version in the bottom-right corner.

### Groundhogg CRM Integration

To sync new store contacts with your Groundhogg installation, open **Admin → Settings** and enter the following details under **Groundhogg CRM Integration**:

1. **Groundhogg Site URL** – the base URL of the site where Groundhogg is installed. The API endpoints are automatically appended (e.g. `/wp-json/gh/v4`).
2. **Groundhogg API Username** – the WordPress user associated with your API keys.
3. **Public Key / Token / Secret Key** – credentials for the advanced API authentication.

After saving, use the **Test Connection** button to verify communication with your Groundhogg REST API. If public key credentials are provided, the advanced authentication method is used.

If you need to troubleshoot API issues, enable **Debug Logging** in the settings panel. When enabled, detailed request and response information is written to `logs/groundhogg.log` in the project root.

To debug Google Drive uploads, check **Drive Debug Logging** under **Admin → Settings → General**. This writes Drive API activity to `logs/drive.log`.

### Calendar Import

To populate the calendar from Google Sheets, open **Admin → Settings** and paste the sheet's public URL in the **Google Sheet URL** field. The application automatically converts the standard editing link into the required CSV export format.

## Recent Improvements

Recent releases introduced several enhancements:

- The admin uploads list now refreshes automatically so new content appears in
  real time.
- Admins receive email notifications when a store submits a quick upload.
- Uploaded files can be downloaded directly from the content history page.
- The chat interface shows each store user's full name and displays emoji
  reactions.
- A handy "Upload" button is available on the dashboard widget.

See `CHANGELOG.md` for a complete history of changes.
