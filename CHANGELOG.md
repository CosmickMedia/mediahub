# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.1] - 2025-12-27
### Added
- "Add More Images" button with themed styling for multi-file uploads
- Progress bar with animated stages during post submission
- Rebuilt complete changelog from git history (1.4.0 - 2.2.0)
### Changed
- Day view modal now displays posts as responsive card grid
- Source labels renamed: "API" to "MediaHub", "Sheet" to "External"
- Close button repositioned to top-right in day view modal
### Fixed
- Individual profile fallback now properly handles all uploaded media files

## [2.2.0] - 2025-12-26
### Added
- Multiple image upload support (up to 4 files per post)
- Platform-specific character limit validation with auto-truncation
- Platform media requirement warnings before posting
- WebP image format support
### Fixed
- Backend fallback for multiple media uploads

## [2.1.0] - 2025-11-15
### Added
- HEIC/HEIF image format support
- Media preview modal on uploads admin page
- Article export and download features
### Changed
- Increased upload file size limit
- Enhanced article admin UI
- Improved Hootsuite profile selection in store editor

## [2.0.0] - 2025-10-01
### Added
- Platform-specific image cropping with admin controls
- Groundhogg CRM integration
- Status cleanup scripts
- PWA manifest and app icons
- Favicons for all pages
### Changed
- Refactored Groundhogg contact structure and API headers
- Improved login page layout with "Remember Me" feature
### Fixed
- Mobile modal display and visibility issues
- Calendar modal positioning on mobile devices

## [1.5.2] - 2025-08-10
### Changed
- Articles UI improvements and dedicated stylesheet
- Stats dashboard now displays horizontally
### Fixed
- Missing CSS styles on articles page

## [1.5.1] - 2025-08-09
### Changed
- Public schedule modal layout aligned with admin version
- Unified login page styling across public and admin
### Fixed
- Admin page header offset issue
- CSS refactoring and organization

## [1.5.0] - 2025-08-08
### Added
- Admin calendar management page with full functionality
- Social Health Report dashboard
- Admin can now delete calendar posts
- Technical overview documentation
### Changed
- Refactored admin navigation for reports and settings
- Reordered admin header icons with unified styles
- Redesigned schedule report filters and layout
- Moved inline calendar styles to stylesheet
- Admin calendar now uses hootsuite_posts data
### Fixed
- Schedule modal z-index issues
- Calendar library path resolution
- Calendar media gallery and posted-by display

## [1.4.0] - 2025-08-07
### Added
- Store users can now schedule Hootsuite posts from calendar
- Support for scheduling to multiple Hootsuite profiles
- Multi-file media upload support for posts
- Hootsuite OAuth authentication in admin settings
- Hootsuite profile sync with automatic refresh
- Campaign tracking for Hootsuite posts
- Profile search in Hootsuite selector
- Display toggles for customer calendar
### Changed
- Renamed "hoot" directory to "hootsuite"
- Moved cron scripts to dedicated directory
- Improved schedule modal time picker and media preview
- Redesigned schedule post modal with better UX
- Enhanced Hootsuite profile UI selectors
### Fixed
- Hootsuite profile refresh transaction handling
- Customer calendar display toggle
- Hootsuite sync date range and pagination

## [1.3.17] - 2025-07-31
### Changed
- Updated README with deployment instructions and a summary of recent features.
### Removed
- Deleted the obsolete `config.xml` file.

## [1.3.16] - 2025-07-30
### Changed
- Auto-refresh the admin uploads list to show new content in real-time.

## [1.3.15] - 2025-07-30
### Changed
- Bumped version number for release deployment.

## [1.3.14] - 2025-07-28
### Changed
- Display the store user's full name in the admin chat interface for better identification.
### Fixed
- Corrected a validation issue on the article submission form.

## [1.3.13] - 2025-07-27
### Added
- Implemented email notifications to alert admins of new quick uploads.
### Changed
- The chat window now shows a "You" label for messages sent by the current admin.

## [1.3.12] - 2025-07-26
### Added
- Added cache and session reset options to the admin tools page.
### Changed
- Improved the efficiency of background notification polling.
### Fixed
- Resolved an issue that prevented scrolling within the calendar event modal.

## [1.3.11] - 2025-07-25
### Changed
- The public-facing page title is now "Store Login" for improved clarity.
### Removed
- Hid the modern footer on the admin login page for a cleaner interface.

## [1.3.10] - 2025-07-24
### Added
- Users can now upload images to accompany articles.
### Fixed
- Corrected various issues with logout functionality and improved session cleanup for both admin and public users.

## [1.3.9] - 2025-07-24
### Changed
- Increased spacing for store selection options to improve mobile usability.

## [1.3.8] - 2025-07-23
### Added
- The admin chat window now displays reactions made by store users.
- An "Upload" button was added to the main dashboard widget for quick access.

## [1.3.7] - 2025-07-22
### Added
- Implemented a "Download" action on the content history page.
### Fixed
- Corrected button styling for broadcast actions.

## [1.3.6] - 2025-07-22
### Fixed
- Adjusted action button layout and icons for visual consistency.

## [1.2.7] - 2025-07-16
### Added
- Admins can now configure social networks with custom icons and colors for calendar entries.
### Changed
- Calendar events now display the associated network's icon and use its assigned color.

## [1.2.6] - 2025-07-15
### Fixed
- Ensured `primary_phone` is stored in the same format as `mobile_phone` when syncing with third-party services.

## [1.0.1] - 2025-07-14
### Added
- Created `full-changelog.md` to begin documenting project history.

## [1.0.0] - 2025-07-14
### Added
- **Initial Application**: Created a PHP web application with a store-facing upload portal and an admin management interface.
- **Authentication**: Implemented Google OAuth for secure admin authentication.
- **User Management**: Added multi-user support with distinct roles for Admins and Stores, including pages for user management.
- **Database**: Created the initial database schema (cmuploader.sql) for users, uploads, settings, and articles.
- **Chat**: Introduced real-time chat, message reactions, user presence indicators, emojis, and a "Quick Messages" feature.
- **Content Management**: Built a system for article submission, review, and management.
- **Admin Tools**: Added a marketing report, content review status tracking, and upload status history.
- **Integrations**: Included a skeleton for future Hootsuite calendar integration.
- **Development**: Set up version tracking and a CI/CD pipeline with `codemagic.yaml`.

### Changed
- **UI/UX**: Migrated the entire UI to the Bootstrap Material theme for a modern look and feel.
- **Styling**: Refactored all pages to use shared headers/footers and extracted inline styles into separate CSS files.
- **Navigation**: Redesigned the admin and public navigation with a responsive hamburger menu and dynamic chat counts.
- **Branding**: Updated all branding with MediaHub logos and redesigned the store PIN login page.
- **Admin Panel**: Improved the styling, colors, and layout of the admin dashboard and content review pages.

### Fixed
- **Configuration**: Added a fallback loader to prevent errors if `config.php` is missing.
- **Core**: Resolved various PHP parse errors and incorrect file include paths throughout the application.
- **Assets**: Fixed asset loading issues by serving Bootstrap files locally instead of from a CDN.
