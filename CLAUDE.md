# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PC PhotoWall is an event photo collection and display system written in PHP. It allows users to upload photos to events with GPS validation, displays them in various modes (slideshow, gallery), and provides admin tools for event management and photo moderation.

**Key Features:**
- Event-based photo upload system with GPS validation
- Live photo display with multiple modes (random, newest, chronological)
- Admin interface for event and photo management
- Optional photo moderation
- Duplicate detection via file hashing
- Automatic EXIF orientation correction
- QR code generation for events
- Multiple image format support (JPEG, PNG, GIF, WebP, HEIC/HEIF)

## Technology Stack

- **Backend:** PHP 8.4+ with PDO for database access
- **Database:** MariaDB 10.11 with utf8mb4 charset
- **Image Processing:** GD extension + ImageMagick for thumbnails and format conversion
- **Containerization:** Docker with Apache and MariaDB
- **No Framework:** Vanilla PHP with manual routing and direct file-based architecture

## Common Commands

### Development Environment

```bash
# Start development environment (includes phpMyAdmin at localhost:8081)
make dev-up

# Start production environment
make prod-up

# Stop environments
make dev-down   # Development
make prod-down  # Production

# View logs
make dev-logs   # Development
make prod-logs  # Production

# Restart
make dev-restart
make prod-restart
```

### Testing

**Wichtig:** Tests benötigen eine laufende Dev-Umgebung mit Datenbankzugriff. Das Test-Script prüft automatisch, ob die Dev-Umgebung läuft und blockiert Tests, falls nicht.

```bash
# Dev-Umgebung starten (erforderlich für Tests)
make dev-up

# Run all tests
make test

# Quick tests (without integration/real images)
make test-quick

# PHP syntax check (benötigt keine Dev-Umgebung)
make test-syntax
```

### Backup & Restore

```bash
# Backup specific event (photos + database)
make backup demo-event
# or: make backup EVENT=demo-event

# Backup all events
make backup-all

# List available backups
make list-backups

# Restore from backup (full: database + photos)
make restore backups/picturewall_backup_demo-event_20250115_143022.tar.gz
# or: make restore FILE=path/to/backup.tar.gz

# Restore database only (without photos)
make restore-db backups/picturewall_backup_demo-event_20250115_143022.tar.gz
# or: make restore-db FILE=path/to/backup.tar.gz
```

**Note:** Backups include event photos and database dump. Stored in `backups/` directory.
Use `restore-db-only` when photos are intact but database needs recovery.

### Access URLs

Host-Ports sind in `.env` konfigurierbar (`APP_PORT`, `DB_PORT`, `PHPMYADMIN_PORT`). Defaults unten in Klammern, falls die Keys fehlen.

- Main app: `http://localhost:${APP_PORT:-4000}`
- Admin interface: `http://localhost:${APP_PORT:-4000}/admin/`
- Display mode: `http://localhost:${APP_PORT:-4000}/[event-slug]/display`
- Gallery: `http://localhost:${APP_PORT:-4000}/[event-slug]/gallery`
- phpMyAdmin (dev only): `http://localhost:${PHPMYADMIN_PORT:-8081}`

## Architecture Overview

### Application Structure

All application files are organized in the `app/` directory (wwwroot), with tests residing at the project root for security reasons.

**Project Root:**
- `app/` - All application files (wwwroot)
- `tests/` - Test suite (outside wwwroot for security)
- `Dockerfile`, `docker-compose*.yml` - Infrastructure
- `Makefile` - Command shortcuts
- `.env`, `.env.example` - Configuration
- Documentation files (README.md, CLAUDE.md, etc.)

**Entry Points (in app/):**
- `app/index.php` - Main upload page for events
- `app/display.php` - Photo display/slideshow page
- `app/gallery.php` - Photo gallery grid view
- `app/admin/` - Admin interface for managing events and photos

**Core Backend (in app/):**
- `app/config/config.php` - Configuration constants and session setup
- `app/config/database.php` - Database class with PDO connection and table creation
- `app/includes/functions.php` - Core utility functions (CSRF, events, file handling, image processing)
- `app/includes/geo.php` - GPS validation and distance calculations
- `app/api/` - API endpoints for AJAX operations (upload, photos, config, etc.)

**Key Directories:**
- `app/uploads/[event-slug]/photos/` - Event-specific full-size photos
- `app/uploads/[event-slug]/thumbnails/` - Event-specific thumbnail images
- `app/uploads/[event-slug]/logo/` - Event-specific logo
- `app/data/` - General upload directory
- `app/logs/` - Application logs
- `tests/` - Comprehensive test suite with test runner (root level)

### Database Schema

**Main Tables:**
- `events` - Event configuration (GPS coords, display settings, moderation flags, etc.)
- `photos` - Uploaded photos with metadata (filename, GPS coords, is_active flag, file hash)
- Key fields: `event_slug` (URL-friendly identifier), `file_hash` (duplicate detection with SHA256), `is_active` (moderation: 0=pending, 1=approved)

### Request Flow

1. **Upload Flow:**
   - User uploads via `index.php` → `api/upload.php`
   - CSRF token validation
   - Event lookup by slug
   - Check if upload is enabled for event
   - File validation (type, size, format)
   - GPS validation if required by event
   - Duplicate detection via file hash
   - Image processing (auto-rotation, thumbnail creation, HEIC conversion)
   - Database insert with is_active flag (0=pending, 1=approved based on moderation setting and GPS validation)
   - JSON response

2. **Display Flow:**
   - `display.php` loads event config
   - `api/photos.php` returns approved photos with thumbnail paths
   - JavaScript rotates through images based on display_mode and display_interval
   - Supports URL parameters: `show_logo`, `display_count`, `display_mode`, `display_interval`

3. **Admin Flow:**
   - Password authentication via session
   - `admin/index.php` - Event list
   - `admin/create-event.php` - Event creation with GPS setup
   - `admin/edit-event.php` - Event editing
   - `admin/event-photos.php` - Photo moderation interface with rotation capability

### Key Functions (app/includes/functions.php)

**Event Management:**
- `generateSlug($name)` - Creates URL-friendly slug
- `isSlugUnique($slug, $excludeId)` - Checks slug uniqueness
- `getEventBySlug($slug)` - Retrieves event by slug
- `getEventUploadPaths($slug)` - Returns event-specific paths
- `ensureEventDirectories($slug)` - Creates upload directories

**File & Image Processing:**
- `validateFileUpload($file, $maxSize)` - Validates uploaded files
- `generateUniqueFilename($originalName)` - Creates unique timestamped filename
- `calculateFileHash($filePath)` - SHA-256 hash for duplicate detection
- `autoRotateImage($imagePath)` - Corrects EXIF orientation
- `createThumbnail($sourcePath, $destPath, $maxWidth, $maxHeight)` - Creates thumbnails
- `convertHeicToJpeg($sourcePath, $destPath)` - Converts HEIC/HEIF to JPEG using ImageMagick

**Security:**
- `generateCSRFToken()` / `validateCSRFToken($token)` - CSRF protection
- `sanitizeInput($input)` - XSS prevention via htmlspecialchars

**GPS Functions (app/includes/geo.php):**
- `calculateDistance($lat1, $lon1, $lat2, $lon2)` - Haversine distance calculation
- `isWithinRadius($userLat, $userLon, $eventLat, $eventLon, $radius)` - GPS validation
- `validateCoordinates($lat, $lon)` - Coordinate range validation

### Image Processing Pipeline

1. Upload validation (file type, size)
2. Move to event-specific photos directory
3. Check file hash for duplicates
4. Auto-rotate based on EXIF orientation
5. Convert HEIC/HEIF to JPEG if needed (ImageMagick)
6. Create thumbnail (GD or ImageMagick)
7. Store metadata in database

### Testing Infrastructure

**Test Framework:** Custom lightweight test runner ([tests/TestRunner.php](tests/TestRunner.php))

**Prerequisites:** Tests require a running Dev environment (`make dev-up`). The test script automatically checks if the Dev environment is running and blocks tests if not. Only `make test-syntax` runs without Dev environment.

**Test Files:**
- `ComprehensiveTests.php` - All core functions
- `SimpleIntegrationTests.php` - Integration without DB dependencies
- `UploadFunctionalityTests.php` - Upload-specific scenarios
- `RealImageUploadTests.php` - Tests with actual image files from `tests/pics/`
- `ImageRotationAnalysisTests.php` - EXIF and rotation analysis
- `PhotoRotationTests.php` - Manual rotation functionality
- `EventConfigurationTests.php` - Event config validation
- `EventManagementTests.php` - Event CRUD operations
- `DisplayConfigurationTests.php` - Display settings validation
- `SchemaIntegrityTests.php` - Datenbankschema- und Index-Integrität
- `SecurityUnitTests.php` - Security-Unit-Tests (CSRF, Auth, Sanitization)
- `SecurityIntegrationTests.php` - Security-Integration-Tests (End-to-End)

Tests can be run via `make test` or directly with `php tests/[TestFile].php`.

## Important Technical Details

### Event Configuration
Events have extensive configuration stored in the `events` table:
- `gps_validation_required` - Boolean for GPS enforcement
- `moderation_required` - Boolean for photo approval workflow
- `upload_enabled` - Boolean to enable/disable uploads (e.g., when event is over)
- `display_mode` - random|newest|chronological
- `display_count` - Number of photos shown at once (NULL for dynamic)
- `display_interval` - Seconds between photo changes
- `layout_type` - single|grid
- `grid_columns` - Number of columns for grid layout
- `show_username`, `show_date`, `overlay_opacity` - Display overlay settings
- `show_logo`, `show_qr_code`, `show_display_link`, `show_gallery_link` - Feature toggles
- `max_upload_size` - Event-specific upload limit in bytes

### Photo Moderation
When `moderation_required` is enabled for an event:
- Uploads set `is_active=0` (pending) instead of `is_active=1` (approved)
- Only approved photos (`is_active=1`) appear in display and gallery
- Admin can toggle status via `api/toggle-photo-status.php`
- When GPS validation fails with required GPS, photos automatically go to pending for manual review

### Upload Control
The `upload_enabled` field allows administrators to disable uploads for an event:
- When `upload_enabled=0`, uploads are blocked via `api/upload.php`
- Upload page shows "Upload deaktiviert" message instead of upload form
- Useful for events that have ended or when upload period is over
- Default value is `1` (enabled) for new events

### Duplicate Detection
- File hash (SHA256) calculated on upload for secure duplicate detection
- `file_hash` column in `photos` table prevents duplicate uploads per event
- Event hash uses MD5 for unique event identification (not security-critical)
- Prevents same file being uploaded multiple times to same event

### URL Routing
No framework routing - uses `app/.htaccess` rewriting:
- `/[event-slug]` → `app/index.php?event_slug=[slug]`
- `/[event-slug]/display` → `app/display.php?event_slug=[slug]`
- `/[event-slug]/gallery` → `app/gallery.php?event_slug=[slug]`

### Session Management
- Sessions started in `app/config/config.php` (HttpOnly, Secure, SameSite-Cookie-Flags, kein doppeltes `session_start()`)
- CSRF tokens stored in session
- Admin-Auth via `password_verify()` gegen `ADMIN_PASSWORD_HASH` (bevorzugt); `ADMIN_PASSWORD` als Klartext-Fallback per `hash_equals()`. Login-Erfolg triggert `session_regenerate_id(true)`.
- No user accounts - single admin credential aus `app/.env`

### Environment Variables (.env)

Configuration loaded from `app/.env` file (copied from root `.env` during deployment). All parameters have sensible defaults - see `.env.example` for complete reference.

**Critical Configuration:**

- **Database:** DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_ROOT_PASS (Docker-Root), MYSQL_ROOT_PASSWORD/MYSQL_DATABASE/MYSQL_USER/MYSQL_PASSWORD (Container-Init)
- **App:** APP_NAME, APP_URL, APP_ENV (development|production)
- **Security:** ADMIN_PASSWORD_HASH (bevorzugt), ADMIN_PASSWORD (Fallback, Klartext), SESSION_TIMEOUT, CSRF_TOKEN_NAME
- **Upload:** UPLOAD_ALLOWED_TYPES, DEFAULT_MAX_UPLOAD_SIZE, MAX_EXECUTION_TIME, MEMORY_LIMIT
- **Image Processing:** IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT, IMAGE_QUALITY_HIGH/MEDIUM, THUMBNAIL_MAX_WIDTH/HEIGHT/QUALITY
- **Display Defaults:** DEFAULT_DISPLAY_COUNT, DEFAULT_DISPLAY_INTERVAL, DEFAULT_DISPLAY_MODE, DEFAULT_GRID_COLUMNS
- **GPS:** GPS_DEFAULT_RADIUS_METERS, DISTANCE_KM_THRESHOLD
- **QR Code:** QR_CODE_DEFAULT_SIZE, QR_CODE_MARGIN
- **Logging:** LOG_ERRORS, DISPLAY_ERRORS

All configuration constants in `config.php` read from ENV with fallback defaults. Event creation and demo data respect these settings.

### Docker Setup
- `docker-compose.yml` - Production (web + db)
- `docker-compose.dev.yml` - Development (adds phpMyAdmin on port 8081)
- Volumes mount `app/` directory to `/var/www/html` for live changes
- DocumentRoot set to `/var/www/html` (maps to `app/`)
- PHP extensions installed: pdo, pdo_mysql, mysqli, gd, exif, zip, imagick

## Development Workflow

1. Make changes to PHP files in `app/` - immediately reflected due to volume mounts
2. Run tests to verify: `make test` or specific test suite
3. Check logs: `make dev-logs` or `app/logs/php_errors.log`
4. Access phpMyAdmin for database inspection (dev mode only)
5. Use browser dev tools for frontend debugging

**Note:** Tests are located in `tests/` at project root (outside wwwroot for security), but test application code in `app/`.

## Version Management

- Version stored in `CHANGELOG.md` as `## [X.Y.Z]`
- Retrieved by `getCurrentVersion()` function
- Displayed in footer of all pages

## Code Quality Standards

### Type Hints (PHP 8.4+)

All functions use strict type hints:

```php
function getEventBySlug(string $slug): array|null
function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
function validateCSRFToken(string $token): bool
```

- Use union types (`string|false`, `array|null`) for multiple return types
- Use `never` return type for functions that always exit
- Use nullable types (`?string`, `?int`) where appropriate

### Exception Handling

Custom exception hierarchy in `app/includes/exceptions.php`:

- `PicturewallException` - Base class for all exceptions
- `ValidationException` - Input validation errors (400)
- `DatabaseException` - Database errors (500)
- `FileSystemException` - File operation errors (500)
- `ImageProcessingException` - Image processing errors (500)
- `GeoLocationException` - GPS validation errors (400)
- `AuthenticationException` - Authentication errors (403)
- `NotFoundException` - Resource not found (404)
- `DuplicateException` - Duplicate resource (409)

Each exception includes proper HTTP status codes and error categories.

### Constants Over Magic Numbers

All magic numbers replaced with named constants in `config.php`:

```php
IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT, IMAGE_QUALITY_HIGH
THUMBNAIL_MAX_WIDTH, THUMBNAIL_MAX_HEIGHT
GPS_MIN_LATITUDE, GPS_MAX_LATITUDE
ROTATION_ANGLE_90, ROTATION_ANGLE_180, ROTATION_ANGLE_270
```

All constants configurable via `.env` file with sensible defaults.

### Image Processing

Centralized `ImageProcessor` class (`app/includes/ImageProcessor.php`):

- `createImageResource()` - Unified image resource creation
- `saveImage()` - Format-agnostic image saving
- `preserveTransparency()` - PNG/GIF transparency handling
- `resize()` - Aspect ratio-aware resizing
- `createThumbnail()` - Thumbnail generation

Eliminates code duplication and ensures consistent image handling.

### Database Performance

Critical indexes for optimal query performance:

**Events table:**

- `idx_event_slug` - Fast event lookup by slug
- `idx_event_hash` - Fast event lookup by hash
- `idx_is_active` - Filter active events

**Photos table:**

- `idx_event_id` - Fast photo lookup by event
- `idx_event_is_active` - Composite index for active photos (critical)
- `idx_file_hash_event` - Fast duplicate detection (~95% faster)
- `idx_uploaded_at` - Chronological sorting
- `idx_event_uploaded` - Newest photos per event

### Configuration Management

All deployment-specific settings in `.env`:

- **Avoid hardcoding** values - use constants from `config.php`
- **Event defaults** use `DEFAULT_*` constants (e.g., `DEFAULT_DISPLAY_COUNT`)
- **GPS defaults** use `GPS_DEFAULT_RADIUS_METERS`
- **Image settings** use `IMAGE_MAX_WIDTH`, `THUMBNAIL_QUALITY`, etc.

Example:

```php
// ❌ Bad - hardcoded value
$radiusMeters = (int)($_POST['radius_meters'] ?? 100);

// ✅ Good - uses constant
$radiusMeters = (int)($_POST['radius_meters'] ?? GPS_DEFAULT_RADIUS_METERS);
```

## Important Development Notes

### File Hash Algorithm

- Uses **SHA-256** for file hashing (security-sensitive duplicate detection)
- Uses **MD5** for event hash generation (not security-critical, just for unique IDs)
- Never change hash algorithm without migration strategy - would break duplicate detection

### CSRF Protection

All forms require CSRF tokens:

```php
<?php echo generateCSRFToken(); ?>
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
```

API endpoints validate tokens via `validateCSRFToken()`.

### GPS Validation Flow

When `gps_validation_required` is enabled:

1. Photo uploaded with GPS coordinates
2. `isWithinRadius()` checks distance from event location
3. If within radius: `is_active=1` (approved)
4. If outside radius or no GPS: `is_active=0` (pending for manual review)
5. Admin can manually approve via photo moderation interface

### Demo Event Creation

On fresh installation, `Database::createDemoEventIfNeeded()`:

- Creates single demo event if no events exist
- Uses constants from `config.php` for all defaults
- Creates 6 placeholder demo photos with colored backgrounds
- Only runs once (checks event count first)
