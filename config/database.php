<?php
require_once __DIR__ . '/config.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    public function getConnection() {
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

    public function createTables() {
        $conn = $this->getConnection();
        
        // Events table
        $sql = "CREATE TABLE IF NOT EXISTS events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
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
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        
        // Add note column to existing events table if it doesn't exist
        $conn->exec("ALTER TABLE events ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER gps_validation_required");
        
        // Photos table
        $sql = "CREATE TABLE IF NOT EXISTS photos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            event_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
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
        
        return true;
    }
    
    private function addMissingColumns() {
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
        
        // Generate hashes for existing events that don't have one
        $this->generateMissingHashes();
        
        // Generate slugs for existing events that don't have one
        $this->generateMissingSlugs();
    }
    
    private function generateMissingHashes() {
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
    
    private function generateMissingSlugs() {
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
}
?>
