# Platform-Specific Image Cropping for Hootsuite Integration

## Overview

This feature automatically crops and resizes uploaded images to match each social media platform's optimal aspect ratio requirements when scheduling posts through Hootsuite. This ensures compatibility and prevents image rejection errors, especially on platforms like Instagram that have strict aspect ratio requirements.

## What Was Implemented

### 1. Database Changes

**New Table: `social_network_image_settings`**

This table stores platform-specific image requirements with the following fields:
- `network_name` - Platform identifier (instagram, facebook, linkedin, x, threads, etc.)
- `enabled` - Enable/disable auto-cropping for this platform
- `aspect_ratio` - Target aspect ratio (e.g., "1:1", "16:9", "4:5")
- `target_width` - Target width in pixels
- `target_height` - Target height in pixels
- `min_width` - Minimum width requirement (pixels)
- `max_file_size_kb` - Maximum file size in KB

**Migration File**: `/add_platform_image_settings.sql`

To apply the database changes, run this SQL file on your database:
```bash
mysql -u [username] -p [database_name] < add_platform_image_settings.sql
```

### 2. Default Platform Settings

The following platforms are pre-configured with optimal settings:

| Platform  | Aspect Ratio | Dimensions    | Max Size | Notes                              |
|-----------|--------------|---------------|----------|------------------------------------|
| Instagram | 1:1          | 1080×1080px   | 5MB      | Square required for API reliability|
| Facebook  | 1.91:1       | 1200×630px    | 10MB     | Landscape preferred                |
| LinkedIn  | 1.91:1       | 1200×627px    | 10MB     | Business-friendly landscape        |
| X/Twitter | 1:1          | 1080×1080px   | 5MB      | Square safest for cross-device     |
| Threads   | 9:16         | 1080×1920px   | 10MB     | Vertical/portrait preferred        |
| Pinterest | 2:3          | 1000×1500px   | 20MB     | Tall vertical preferred            |

### 3. Admin Controls

Navigate to **Admin → Settings → Calendar Tab → Hootsuite Integration**

#### Platform Image Requirements Section

Configure each platform's cropping behavior:
- **Enable/Disable** - Toggle auto-cropping per platform
- **Aspect Ratio** - Set custom aspect ratio (e.g., "16:9", "1:1", "4:5")
- **Target Dimensions** - Width × Height in pixels
- **Max File Size** - Maximum allowed file size in KB

#### Image Processing Settings Section

Fine-tune compression and upload behavior:
- **Enable Automatic Compression** - Compress images before upload
- **Compression Quality** - Slider from 50-100 (default: 85)
- **Target File Size** - Optimal size for fast processing (default: 100KB)
- **Max File Size** - Safety limit before upload (default: 800KB)
- **Upload Timeout** - Maximum wait time for media processing (default: 60s)
- **Polling Interval** - How often to check READY status (default: 3s)
- **Max Polling Attempts** - Maximum retries before timeout (default: 20)
- **On Media Upload Failure** - Choose behavior:
  - Post without media (default)
  - Fail entire post
  - Skip this platform only
- **Store Original Images** - Keep original uploads for reference
- **Enable Media Upload Logging** - Detailed logging for troubleshooting
- **Test Mode** - Generate crops without actually posting (for testing)

### 4. How It Works

When a user schedules a post with an image to multiple platforms:

1. **Upload**: User uploads one image (e.g., 1920×1080 landscape)
2. **Platform Detection**: System detects which platforms are selected (e.g., Instagram + Facebook + Threads)
3. **Auto-Crop Generation**:
   - For Instagram: Crops from center to 1080×1080 square
   - For Facebook: Crops to 1200×630 landscape (minimal crop needed)
   - For Threads: Crops from center to 1080×1920 vertical portrait
4. **Compression**: Each cropped version is compressed based on admin settings
5. **Upload**: Platform-specific version uploaded to Hootsuite for each platform
6. **Storage**: All versions stored locally in `/public/calendar_media/YYYY/MM/` with naming:
   - `{timestamp}_{platform}_{filename}.jpg`

### 5. File Naming Convention

Generated files follow this pattern:
```
{timestamp}_{platform}_{original_filename}.jpg
```

Examples:
- `1699876543_instagram_sunset.jpg` - Square 1:1 crop for Instagram
- `1699876543_facebook_sunset.jpg` - Landscape crop for Facebook
- `1699876543_threads_sunset.jpg` - Vertical crop for Threads

### 6. Code Changes

#### `/public/hootsuite_post.php`

**New Functions**:
- `getPlatformImageSettings($pdo, $network)` - Retrieves platform settings from DB or defaults
- `getDefaultPlatformSettings($network)` - Returns hardcoded defaults as fallback
- `cropImageToAspectRatio($sourcePath, $targetWidth, $targetHeight, $outputPath)` - Crops and resizes images from center

**Modified Logic**:
- Individual profile posting loop now generates platform-specific crops before upload
- Compression function now uses admin settings instead of hardcoded values
- Improved cleanup of temporary files

#### `/admin/settings.php`

**New UI Sections**:
- Platform Image Requirements table (lines 1159-1240)
- Image Processing Settings card (lines 1242-1354)

**New POST Handlers**:
- Saves platform-specific image settings to database
- Saves global image processing settings to settings table

**New Settings Variables**:
- All hootsuite image processing settings loaded and displayed

## Usage Instructions

### For Administrators

1. **Initial Setup**:
   - Run the database migration: `add_platform_image_settings.sql`
   - Navigate to Admin → Settings → Calendar → Hootsuite Integration
   - Review default platform settings
   - Adjust if needed for your specific requirements

2. **Customizing Platform Settings**:
   - Toggle platforms on/off as needed
   - Modify aspect ratios if platform requirements change
   - Adjust max file sizes based on your server capabilities
   - Click "Save All Settings"

3. **Tuning Performance**:
   - Lower compression quality for faster uploads (60-70)
   - Increase target file size if quality is too low (150-200KB)
   - Adjust timeout settings for slower connections
   - Enable logging to troubleshoot issues

### For End Users

The cropping process is completely automatic. When scheduling a post:

1. Upload your image (any aspect ratio)
2. Select multiple platforms (Instagram + Facebook + Twitter, etc.)
3. Fill out post details
4. Click "Schedule Post"
5. System automatically:
   - Generates optimal crops for each platform
   - Compresses images for fast processing
   - Uploads platform-specific versions
   - Stores all versions locally

## Troubleshooting

### Images Being Rejected by Instagram

**Solution**: Ensure Instagram platform settings are:
- Enabled: ✓
- Aspect Ratio: 1:1
- Dimensions: 1080×1080

Instagram's API is very strict about square images via API.

### Images Too Small/Poor Quality

**Solution**: Increase compression quality in settings:
- Set compression quality to 90-95
- Increase target file size to 200-300KB

### Upload Timeouts

**Solution**: Adjust timeout settings:
- Increase upload timeout to 90-120 seconds
- Increase max polling attempts to 30-40
- Check server PHP max_execution_time settings

### Debug Mode

Enable detailed logging:
1. Check "Enable Media Upload Logging"
2. Reproduce the issue
3. Check PHP error logs for detailed step-by-step processing
4. Look for messages like:
   - "Platform instagram requires 1080x1080"
   - "Generated platform-specific crop for facebook"
   - "Image compressed: X → Y bytes"

### Test Mode

Test without actually posting:
1. Enable "Test Mode (Dry Run)"
2. Schedule a test post
3. Check `/public/calendar_media/` for generated crops
4. Verify dimensions and quality
5. Disable test mode when satisfied

## Technical Details

### Cropping Algorithm

The system uses **center-crop** methodology:

```
1. Calculate source and target aspect ratios
2. If source is wider than target:
   - Crop width from sides (keep height)
   - Center horizontally
3. If source is taller than target:
   - Crop height from top/bottom (keep width)
   - Center vertically
4. Resize to exact target dimensions
5. Save as JPEG with specified quality
```

This ensures the main subject (typically centered) remains in frame.

### Compression Strategy

Two-phase compression:

**Phase 1: Quality Reduction**
- Start at admin-configured quality (default 85)
- Reduce by 10 until reaching target size or quality 50

**Phase 2: Dimension Reduction** (if Phase 1 fails)
- Scale down dimensions by 20% per iteration
- Continue until reaching target size or 30% of original

### Performance Optimizations

- Temporary files cleaned up immediately after use
- Cropped images cached for multi-platform posts
- GD library used for fast image processing
- JPEG format used for optimal compression

## Future Enhancements

Possible improvements:
- Smart crop (detect faces/objects for better framing)
- Manual crop preview before posting
- Support for multiple images per post
- Platform-specific watermarks
- Batch processing for scheduled posts
- A/B testing different crops

## Support

For issues or questions:
1. Check error logs with media logging enabled
2. Verify database table exists and is populated
3. Test with test mode enabled
4. Contact support with specific error messages

## File Reference

**Modified Files**:
- `/public/hootsuite_post.php` - Main posting logic with crop generation
- `/admin/settings.php` - Admin UI for platform settings
- `/add_platform_image_settings.sql` - Database migration

**Generated Files** (stored in):
- `/public/calendar_media/YYYY/MM/` - All uploaded and cropped images

## Version History

**v1.0** - Initial Implementation
- Platform-specific cropping for Instagram, Facebook, LinkedIn, X, Threads, Pinterest
- Admin UI for customization
- Compression settings
- Test mode
- Detailed logging

---

**Last Updated**: October 2025
