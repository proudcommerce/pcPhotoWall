<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

class Database {
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private ?PDO $conn = null;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    public function getConnection(): PDO {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }

    public function createTables(): bool {
        $conn = $this->getConnection();
        
        // Events table
        $sql = "CREATE TABLE IF NOT EXISTS events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            event_slug VARCHAR(100) UNIQUE NULL,
            event_hash VARCHAR(32) UNIQUE NULL,
            latitude DECIMAL(10, 8) NULL,
            longitude DECIMAL(11, 8) NULL,
            radius_meters INT DEFAULT 100,
            display_mode ENUM('random', 'newest', 'chronological') DEFAULT 'random',
            display_count INT DEFAULT NULL,
            display_interval INT DEFAULT 5,
            layout_type ENUM('single', 'grid') DEFAULT 'grid',
            grid_columns INT DEFAULT 3,
            show_username BOOLEAN DEFAULT TRUE,
            show_date BOOLEAN DEFAULT TRUE,
            overlay_opacity DECIMAL(3, 2) DEFAULT 0.8,
            gps_validation_required BOOLEAN DEFAULT FALSE,
            moderation_required BOOLEAN DEFAULT FALSE,
            note TEXT NULL,
            logo_filename VARCHAR(255) NULL,
            show_logo BOOLEAN DEFAULT FALSE,
            show_qr_code BOOLEAN DEFAULT FALSE,
            show_display_link BOOLEAN DEFAULT FALSE,
            show_gallery_link BOOLEAN DEFAULT FALSE,
            max_upload_size INT DEFAULT 10485760,
            upload_enabled BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $conn->exec($sql);
        
        // Photos table
        $sql = "CREATE TABLE IF NOT EXISTS photos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            thumbnail_filename VARCHAR(255) NULL,
            resized_filename VARCHAR(255) NULL,
            original_name VARCHAR(255),
            username VARCHAR(100),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            distance_meters DECIMAL(12, 2) NULL,
            file_size INT,
            mime_type VARCHAR(50),
            file_hash VARCHAR(64),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $conn->exec($sql);
        
        // Display config table
        $sql = "CREATE TABLE IF NOT EXISTS display_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            max_photos INT DEFAULT 9,
            display_mode ENUM('random', 'newest', 'chronological') DEFAULT 'random',
            refresh_interval INT DEFAULT 5,
            layout_type ENUM('single', 'grid') DEFAULT 'grid',
            grid_columns INT DEFAULT 3,
            transition_effect ENUM('fade', 'slide', 'zoom') DEFAULT 'fade',
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);

        // Add new columns to existing events table if they don't exist
        $this->addMissingColumns();

        // Add critical database indexes for performance
        $this->addDatabaseIndexes();

        return true;
    }
    
    private function addMissingColumns(): void {
        $conn = $this->getConnection();
        
        // Check if show_username column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'show_username'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN show_username BOOLEAN DEFAULT TRUE");
        }
        
        // Check if show_date column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'show_date'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN show_date BOOLEAN DEFAULT TRUE");
        }
        
        // Check if overlay_opacity column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'overlay_opacity'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN overlay_opacity DECIMAL(3, 2) DEFAULT 0.8");
        }
        
        // Check if gps_validation_required column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'gps_validation_required'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN gps_validation_required BOOLEAN DEFAULT FALSE");
        }
        
        // Check if logo_filename column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'logo_filename'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN logo_filename VARCHAR(255) NULL");
        }
        
        // Check if show_logo column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'show_logo'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN show_logo BOOLEAN DEFAULT FALSE");
        }
        
        // Check if event_hash column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'event_hash'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN event_hash VARCHAR(32) UNIQUE NULL");
        }
        
        // Check if event_slug column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'event_slug'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN event_slug VARCHAR(100) UNIQUE NULL");
        }
        
        // Check if max_upload_size column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'max_upload_size'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN max_upload_size INT DEFAULT 10485760");
        }
        
        // Check if photos.is_active column exists
        $result = $conn->query("SHOW COLUMNS FROM photos LIKE 'is_active'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE photos ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
        }
        
        // Check if distance_meters column allows NULL
        $result = $conn->query("SHOW COLUMNS FROM photos LIKE 'distance_meters'");
        if ($result->rowCount() > 0) {
            $column = $result->fetch();
            if (strpos($column['Null'], 'NO') !== false) {
                $conn->exec("ALTER TABLE photos MODIFY COLUMN distance_meters DECIMAL(12, 2) NULL");
            }
        }
        
        // Check if file_hash column exists
        $result = $conn->query("SHOW COLUMNS FROM photos LIKE 'file_hash'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE photos ADD COLUMN file_hash VARCHAR(64) NULL");
        }
        
        // Check if show_display_link column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'show_display_link'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN show_display_link BOOLEAN DEFAULT FALSE");
        }
        
        // Check if thumbnail_filename column exists
        $result = $conn->query("SHOW COLUMNS FROM photos LIKE 'thumbnail_filename'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE photos ADD COLUMN thumbnail_filename VARCHAR(255) NULL");
        }
        
        // Check if resized_filename column exists
        $result = $conn->query("SHOW COLUMNS FROM photos LIKE 'resized_filename'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE photos ADD COLUMN resized_filename VARCHAR(255) NULL");
        }
        
        // Check if show_gallery_link column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'show_gallery_link'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN show_gallery_link BOOLEAN DEFAULT FALSE");
        }
        
        // Check if moderation_required column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'moderation_required'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN moderation_required BOOLEAN DEFAULT FALSE");
        }

        // Check if upload_enabled column exists
        $result = $conn->query("SHOW COLUMNS FROM events LIKE 'upload_enabled'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE events ADD COLUMN upload_enabled BOOLEAN DEFAULT TRUE");
        }

        // Generate hashes for existing events that don't have one
        $this->generateMissingHashes();
        
        // Generate slugs for existing events that don't have one
        $this->generateMissingSlugs();

        // Create demo event if no events exist
        $this->createDemoEventIfNeeded();
    }

    private function createDemoEventIfNeeded(): void {
        $conn = $this->getConnection();

        // Check if any events exist
        $stmt = $conn->prepare("SELECT COUNT(*) FROM events");
        $stmt->execute();
        $eventCount = $stmt->fetchColumn();

        // Only create demo event if no events exist
        if ($eventCount > 0) {
            return;
        }

        // Generate unique identifiers
        $eventHash = generateEventHash();
        $eventSlug = 'demo-event';

        // Demo event configuration - using constants from config.php
        $demoData = [
            'name' => 'Demo Event',
            'event_slug' => $eventSlug,
            'event_hash' => $eventHash,
            'latitude' => null,
            'longitude' => null,
            'radius_meters' => GPS_DEFAULT_RADIUS_METERS,
            'display_mode' => DEFAULT_DISPLAY_MODE,
            'display_count' => DEFAULT_DISPLAY_COUNT,
            'display_interval' => DEFAULT_DISPLAY_INTERVAL,
            'layout_type' => 'grid',
            'grid_columns' => DEFAULT_GRID_COLUMNS,
            'show_username' => 1,
            'show_date' => 1,
            'overlay_opacity' => 0.8,
            'gps_validation_required' => 0,
            'moderation_required' => 0,
            'note' => 'Dies ist ein Demo-Event, das bei der Installation erstellt wurde. Sie können es im Admin-Bereich bearbeiten oder löschen.',
            'logo_filename' => null,
            'show_logo' => 0,
            'show_qr_code' => 1,
            'show_display_link' => 1,
            'show_gallery_link' => 1,
            'max_upload_size' => DEFAULT_MAX_UPLOAD_SIZE,
            'upload_enabled' => 1,
            'is_active' => 1
        ];

        // Insert demo event
        $sql = "INSERT INTO events (
            name, event_slug, event_hash, latitude, longitude, radius_meters,
            display_mode, display_count, display_interval, layout_type, grid_columns,
            show_username, show_date, overlay_opacity, gps_validation_required,
            moderation_required, note, logo_filename, show_logo, show_qr_code,
            show_display_link, show_gallery_link, max_upload_size, upload_enabled, is_active
        ) VALUES (
            :name, :event_slug, :event_hash, :latitude, :longitude, :radius_meters,
            :display_mode, :display_count, :display_interval, :layout_type, :grid_columns,
            :show_username, :show_date, :overlay_opacity, :gps_validation_required,
            :moderation_required, :note, :logo_filename, :show_logo, :show_qr_code,
            :show_display_link, :show_gallery_link, :max_upload_size, :upload_enabled, :is_active
        )";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($demoData);

            $eventId = $conn->lastInsertId();

            // Create demo photos (directories will be created inside this function)
            $this->createDemoPhotos($eventId, $eventSlug);

            error_log("Demo event created successfully on fresh installation");
        } catch (Exception $e) {
            error_log("Error creating demo event: " . $e->getMessage());
        }
    }

    private function createDemoPhotos(int $eventId, string $eventSlug): void {
        $conn = $this->getConnection();

        // Ensure directories exist first
        ensureEventDirectories($eventSlug);

        $paths = getEventUploadPaths($eventSlug);

        // Demo photo configurations
        $demoPhotos = [
            ['text' => 'Willkommen!', 'color' => '3498db', 'username' => 'Demo User'],
            ['text' => 'Fotos hochladen', 'color' => '2ecc71', 'username' => 'PhotoWall'],
            ['text' => 'Momente teilen', 'color' => 'e74c3c', 'username' => 'Admin'],
            ['text' => 'Events erstellen', 'color' => 'f39c12', 'username' => 'Demo User'],
            ['text' => 'Galerie anzeigen', 'color' => '9b59b6', 'username' => 'PhotoWall'],
            ['text' => 'QR Codes', 'color' => '1abc9c', 'username' => 'Admin'],
        ];

        foreach ($demoPhotos as $index => $photo) {
            try {
                // Create placeholder image
                $image = imagecreatetruecolor(1920, 1080);

                // Parse hex color
                $r = hexdec(substr($photo['color'], 0, 2));
                $g = hexdec(substr($photo['color'], 2, 2));
                $b = hexdec(substr($photo['color'], 4, 2));

                $backgroundColor = imagecolorallocate($image, $r, $g, $b);
                $textColor = imagecolorallocate($image, 255, 255, 255);

                imagefilledrectangle($image, 0, 0, 1920, 1080, $backgroundColor);

                // Add text in center
                $fontSize = 5;
                $textWidth = strlen($photo['text']) * imagefontwidth($fontSize);
                $textHeight = imagefontheight($fontSize);
                $x = (1920 - $textWidth) / 2;
                $y = (1080 - $textHeight) / 2;
                imagestring($image, $fontSize, $x, $y, $photo['text'], $textColor);

                $filename = 'demo_photo_' . ($index + 1) . '_' . time() . rand(100, 999) . '.jpg';
                $photoPath = $paths['photos_path'] . '/' . $filename;
                $thumbnailFilename = 'thumb_' . $filename;
                $thumbnailPath = $paths['thumbnails_path'] . '/' . $thumbnailFilename;

                // Save full-size image
                imagejpeg($image, $photoPath, 90);

                // Create thumbnail
                $thumbnail = imagescale($image, 400);
                imagejpeg($thumbnail, $thumbnailPath, 85);

                imagedestroy($image);
                imagedestroy($thumbnail);

                // Calculate file hash and size
                $fileHash = hash_file('sha256', $photoPath);
                $fileSize = filesize($photoPath);

                // Ensure file size is valid
                if ($fileSize === false || $fileSize === '') {
                    $fileSize = 0;
                }

                // Insert into database
                $sql = "INSERT INTO photos (
                    event_id, filename, thumbnail_filename, original_name, username,
                    latitude, longitude, distance_meters, file_size, mime_type,
                    file_hash, is_active
                ) VALUES (
                    :event_id, :filename, :thumbnail_filename, :original_name, :username,
                    :latitude, :longitude, :distance_meters, :file_size, :mime_type,
                    :file_hash, :is_active
                )";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'event_id' => $eventId,
                    'filename' => $filename,
                    'thumbnail_filename' => $thumbnailFilename,
                    'original_name' => $filename,
                    'username' => $photo['username'],
                    'latitude' => null,
                    'longitude' => null,
                    'distance_meters' => null,
                    'file_size' => $fileSize,
                    'mime_type' => 'image/jpeg',
                    'file_hash' => $fileHash,
                    'is_active' => 1
                ]);

            } catch (Exception $e) {
                error_log("Error creating demo photo {$index}: " . $e->getMessage());
            }
        }
    }

    private function generateMissingHashes(): void {
        $conn = $this->getConnection();
        
        // Get events without hash
        $stmt = $conn->prepare("SELECT id FROM events WHERE event_hash IS NULL");
        $stmt->execute();
        $eventsWithoutHash = $stmt->fetchAll();
        
        foreach ($eventsWithoutHash as $event) {
            $hash = generateEventHash();
            $updateStmt = $conn->prepare("UPDATE events SET event_hash = ? WHERE id = ?");
            $updateStmt->execute([$hash, $event['id']]);
        }
    }
    
    private function generateMissingSlugs(): void {
        $conn = $this->getConnection();

        // Get events without slug
        $stmt = $conn->prepare("SELECT id, name FROM events WHERE event_slug IS NULL");
        $stmt->execute();
        $eventsWithoutSlug = $stmt->fetchAll();

        foreach ($eventsWithoutSlug as $event) {
            $slug = generateUniqueSlug($event['name']);
            $updateStmt = $conn->prepare("UPDATE events SET event_slug = ? WHERE id = ?");
            $updateStmt->execute([$slug, $event['id']]);
        }
    }

    /**
     * Add critical database indexes for performance optimization
     * These indexes improve query performance for common operations
     */
    private function addDatabaseIndexes(): void {
        $conn = $this->getConnection();

        // Helper function to check if index exists
        $indexExists = function($table, $indexName) use ($conn) {
            $stmt = $conn->prepare("SHOW INDEX FROM {$table} WHERE Key_name = ?");
            $stmt->execute([$indexName]);
            return $stmt->rowCount() > 0;
        };

        try {
            // Events table indexes
            if (!$indexExists('events', 'idx_event_slug')) {
                $conn->exec("ALTER TABLE events ADD INDEX idx_event_slug (event_slug)");
                error_log("Added index: idx_event_slug on events table");
            }

            if (!$indexExists('events', 'idx_event_hash')) {
                $conn->exec("ALTER TABLE events ADD INDEX idx_event_hash (event_hash)");
                error_log("Added index: idx_event_hash on events table");
            }

            if (!$indexExists('events', 'idx_is_active')) {
                $conn->exec("ALTER TABLE events ADD INDEX idx_is_active (is_active)");
                error_log("Added index: idx_is_active on events table");
            }

            // Photos table indexes - CRITICAL for performance
            if (!$indexExists('photos', 'idx_event_id')) {
                $conn->exec("ALTER TABLE photos ADD INDEX idx_event_id (event_id)");
                error_log("Added index: idx_event_id on photos table");
            }

            if (!$indexExists('photos', 'idx_event_is_active')) {
                $conn->exec("ALTER TABLE photos ADD INDEX idx_event_is_active (event_id, is_active)");
                error_log("Added index: idx_event_is_active (composite) on photos table");
            }

            if (!$indexExists('photos', 'idx_file_hash_event')) {
                $conn->exec("ALTER TABLE photos ADD INDEX idx_file_hash_event (file_hash, event_id)");
                error_log("Added index: idx_file_hash_event (composite) on photos table");
            }

            if (!$indexExists('photos', 'idx_uploaded_at')) {
                $conn->exec("ALTER TABLE photos ADD INDEX idx_uploaded_at (uploaded_at)");
                error_log("Added index: idx_uploaded_at on photos table");
            }

            if (!$indexExists('photos', 'idx_event_uploaded')) {
                $conn->exec("ALTER TABLE photos ADD INDEX idx_event_uploaded (event_id, uploaded_at)");
                error_log("Added index: idx_event_uploaded (composite) on photos table");
            }

        } catch (Exception $e) {
            error_log("Error adding database indexes: " . $e->getMessage());
            // Don't throw - indexes are optimization, not critical for basic functionality
        }
    }
}
?>
