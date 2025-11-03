#!/bin/bash

# Hootsuite Token Refresh - Cron Setup Helper Script
# This script helps set up the hourly token refresh cron job

echo "=========================================="
echo "Hootsuite Token Refresh - Cron Setup"
echo "=========================================="
echo ""

# Find PHP executable
echo "Looking for PHP executable..."
PHP_PATH=""

# Check common locations
if [ -f "/usr/bin/php" ]; then
    PHP_PATH="/usr/bin/php"
elif [ -f "/usr/local/bin/php" ]; then
    PHP_PATH="/usr/local/bin/php"
elif [ -f "/opt/homebrew/bin/php" ]; then
    PHP_PATH="/opt/homebrew/bin/php"
else
    # Search for MAMP PHP
    MAMP_PHP=$(find /Applications/MAMP/bin/php -name "php" -type f 2>/dev/null | sort -r | head -1)
    if [ -n "$MAMP_PHP" ]; then
        PHP_PATH="$MAMP_PHP"
    fi
fi

if [ -z "$PHP_PATH" ]; then
    echo "❌ Could not automatically find PHP executable."
    echo ""
    echo "Please manually enter the full path to your PHP executable:"
    read -r PHP_PATH

    if [ ! -f "$PHP_PATH" ]; then
        echo "❌ Error: PHP executable not found at: $PHP_PATH"
        exit 1
    fi
fi

echo "✅ Found PHP at: $PHP_PATH"

# Test PHP
PHP_VERSION=$("$PHP_PATH" -v 2>&1 | head -1)
echo "   $PHP_VERSION"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CRON_SCRIPT="$SCRIPT_DIR/crons/hootsuite_token_cron.php"
LOG_DIR="$SCRIPT_DIR/logs"
LOG_FILE="$LOG_DIR/cron_token_refresh.log"

# Create logs directory if it doesn't exist
if [ ! -d "$LOG_DIR" ]; then
    echo "Creating logs directory..."
    mkdir -p "$LOG_DIR"
    echo "✅ Created: $LOG_DIR"
fi

# Check if cron script exists
if [ ! -f "$CRON_SCRIPT" ]; then
    echo "❌ Error: Cron script not found at: $CRON_SCRIPT"
    exit 1
fi

echo "✅ Cron script found: $CRON_SCRIPT"
echo ""

# Test the cron script
echo "Testing cron script manually..."
TEST_OUTPUT=$("$PHP_PATH" "$CRON_SCRIPT" 2>&1)
TEST_EXIT_CODE=$?

echo "$TEST_OUTPUT"
echo ""

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo "✅ Cron script executed successfully"
else
    echo "⚠️  Cron script executed with exit code: $TEST_EXIT_CODE"
fi
echo ""

# Prepare cron entry
CRON_ENTRY="0 * * * * $PHP_PATH $CRON_SCRIPT >> $LOG_FILE 2>&1"

echo "=========================================="
echo "Cron Job Configuration"
echo "=========================================="
echo ""
echo "The following cron job will be added to run HOURLY (at minute 0 of every hour):"
echo ""
echo "$CRON_ENTRY"
echo ""
echo "This will:"
echo "  • Run every hour at :00 (1:00, 2:00, 3:00, etc.)"
echo "  • Refresh the Hootsuite OAuth token"
echo "  • Log output to: $LOG_FILE"
echo ""

# Check if cron entry already exists
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$CRON_SCRIPT")
if [ -n "$EXISTING_CRON" ]; then
    echo "⚠️  A similar cron job already exists:"
    echo "   $EXISTING_CRON"
    echo ""
    read -p "Do you want to replace it? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Setup cancelled."
        exit 0
    fi
    # Remove existing entry
    crontab -l 2>/dev/null | grep -v -F "$CRON_SCRIPT" | crontab -
    echo "✅ Removed existing cron job"
fi

# Confirm before adding
read -p "Do you want to add this cron job? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Setup cancelled."
    echo ""
    echo "To add this cron job manually, run:"
    echo "  crontab -e"
    echo ""
    echo "Then add this line:"
    echo "  $CRON_ENTRY"
    exit 0
fi

# Add cron entry
(crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -

if [ $? -eq 0 ]; then
    echo "✅ Cron job added successfully!"
    echo ""
    echo "=========================================="
    echo "Setup Complete"
    echo "=========================================="
    echo ""
    echo "The Hootsuite token will now be refreshed hourly."
    echo ""
    echo "View your cron jobs:"
    echo "  crontab -l"
    echo ""
    echo "Monitor the cron log:"
    echo "  tail -f $LOG_FILE"
    echo ""
    echo "To remove this cron job later:"
    echo "  crontab -e"
    echo "  (then delete the line containing 'hootsuite_token_cron.php')"
    echo ""
else
    echo "❌ Error: Failed to add cron job"
    exit 1
fi
