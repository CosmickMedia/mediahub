# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.16] - 2025-07-30
### Changed
- Auto-refresh the admin uploads list to show new content in real-time.

## [1.3.17] - 2025-07-31
### Changed
- Updated README with deployment instructions and a summary of recent features.
### Removed
- Deleted the obsolete `config.xml` file.

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