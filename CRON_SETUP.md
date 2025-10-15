# Hootsuite Token Refresh - Cron Setup Instructions

## Overview
The Hootsuite OAuth token needs to be refreshed hourly to maintain API access. This is done automatically via a cron job.

## Setup Instructions

### Step 1: Find Your PHP Executable Path
First, determine the full path to your PHP executable. Common locations:
- MAMP: `/Applications/MAMP/bin/php/php8.x.x/bin/php`
- XAMPP: `/Applications/XAMPP/xamppfiles/bin/php`
- Homebrew: `/usr/local/bin/php` or `/opt/homebrew/bin/php`
- System: `/usr/bin/php`

You can find it by running one of these commands in Terminal:
```bash
which php
# OR
find /Applications -name "php" -type f 2>/dev/null | grep bin/php
```

### Step 2: Add Cron Job
Once you have your PHP path, add the cron job:

1. Open the crontab editor:
```bash
crontab -e
```

2. Add this line (replace `/path/to/php` with your actual PHP path from Step 1):
```bash
0 * * * * /path/to/php /Users/carleykuehner/www/mediahub/crons/hootsuite_token_cron.php >> /Users/carleykuehner/www/mediahub/logs/cron_token_refresh.log 2>&1
```

Example with MAMP PHP 8.2:
```bash
0 * * * * /Applications/MAMP/bin/php/php8.2.0/bin/php /Users/carleykuehner/www/mediahub/crons/hootsuite_token_cron.php >> /Users/carleykuehner/www/mediahub/logs/cron_token_refresh.log 2>&1
```

3. Save and exit (in vim: press `ESC`, then type `:wq` and press ENTER)

### Step 3: Verify Cron Job
Check that the cron job was added:
```bash
crontab -l
```

You should see your new entry in the list.

### Step 4: Test the Cron Script Manually
Test the script manually before waiting for the cron to run:
```bash
/path/to/php /Users/carleykuehner/www/mediahub/crons/hootsuite_token_cron.php
```

### Cron Schedule Explanation
`0 * * * *` means "run at minute 0 of every hour"
- This will execute hourly at :00 (1:00, 2:00, 3:00, etc.)

## Monitoring

### Check Cron Logs
View the cron execution log:
```bash
tail -f /Users/carleykuehner/www/mediahub/logs/cron_token_refresh.log
```

### Check Token Refresh Status
In the admin settings (/admin/settings.php), navigate to the Calendar tab to see:
- Token Refresh Interval (hours): Should be set to `1`
- Last token refresh timestamp (stored in database)

### Manual Token Refresh
You can also manually refresh the token via the admin interface:
1. Go to `/admin/settings.php`
2. Navigate to the Calendar tab
3. Click the "Refresh Token" button

## Troubleshooting

### Cron Not Running
- Check system logs: `log show --predicate 'process == "cron"' --last 1h`
- Verify cron service is running on macOS (it should be by default)
- Ensure the PHP path is correct and executable

### Permission Issues
If you see permission errors, ensure:
- The PHP script is readable: `chmod +r /Users/carleykuehner/www/mediahub/crons/hootsuite_token_cron.php`
- The logs directory exists and is writable: `mkdir -p /Users/carleykuehner/www/mediahub/logs && chmod 755 /Users/carleykuehner/www/mediahub/logs`

### Token Refresh Failures
Check the Hootsuite debug logs:
```bash
tail -f /Users/carleykuehner/www/mediahub/logs/hootsuite.log
```

Enable debug mode in admin settings (Calendar tab) for detailed logging.

## Additional Cron Jobs

The system has other cron scripts that you may also want to schedule:

### Hootsuite Posts Sync (every 24 hours at 2 AM)
```bash
0 2 * * * /path/to/php /Users/carleykuehner/www/mediahub/crons/hootsuite_cron.php >> /Users/carleykuehner/www/mediahub/logs/cron_hootsuite.log 2>&1
```

### Hootsuite Profiles Sync (every 24 hours at 3 AM)
```bash
0 3 * * * /path/to/php /Users/carleykuehner/www/mediahub/crons/hootsuite_profiles_cron.php >> /Users/carleykuehner/www/mediahub/logs/cron_profiles.log 2>&1
```

### Calendar Update (every 24 hours at 1 AM)
```bash
0 1 * * * /path/to/php /Users/carleykuehner/www/mediahub/crons/update_calendar.php >> /Users/carleykuehner/www/mediahub/logs/cron_calendar.log 2>&1
```
