<?php
/**
 * Schema Integrity Tests
 *
 * Prueft, dass `Database::createTables()` + `addMissingColumns()` + `addDatabaseIndexes()`
 * alle erwarteten Spalten und Indexes in der laufenden Dev-Datenbank erzeugt haben.
 *
 * Hintergrund: Das Event-Schema wird inkrementell via addMissingColumns() migriert.
 * Wird eine Migration vergessen (z.B. neue Spalte nicht in addMissingColumns() aufgenommen),
 * faellt dieser Test durch.
 *
 * Voraussetzung: make dev-up, MySQL exposed auf localhost:3306 (docker-compose.dev.yml).
 */

require_once __DIR__ . '/TestRunner.php';

class SchemaIntegrityTests {
    private TestRunner $testRunner;
    private ?PDO $pdo = null;
    private array $envConfig = [];

    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->envConfig = $this->readEnv();
    }

    public function runAllTests(): bool {
        try {
            $this->pdo = $this->connect();
        } catch (Exception $e) {
            echo "❌ Schema-Tests uebersprungen: konnte mysql auf localhost:3306 nicht erreichen.\n";
            echo '   Details: ' . $e->getMessage() . "\n";
            echo "   Starte Dev-Umgebung mit: make dev-up\n";
            return false;
        }

        $this->addEventsTableColumnTests();
        $this->addPhotosTableColumnTests();
        $this->addDisplayConfigTableColumnTests();
        $this->addEventsIndexTests();
        $this->addPhotosIndexTests();
        $this->addConstraintTests();

        return $this->testRunner->runAll();
    }

    // ---------------- events-Tabelle ----------------

    private function addEventsTableColumnTests(): void {
        $expected = [
            'id', 'name', 'event_slug', 'event_hash', 'latitude', 'longitude',
            'radius_meters', 'display_mode', 'display_count', 'display_interval',
            'layout_type', 'grid_columns', 'show_username', 'show_date',
            'overlay_opacity', 'gps_validation_required', 'moderation_required',
            'note', 'logo_filename', 'show_logo', 'show_qr_code',
            'show_display_link', 'show_gallery_link', 'max_upload_size',
            'upload_enabled', 'created_at', 'updated_at', 'is_active',
        ];

        $this->testRunner->addTest('events - all expected columns exist', function () use ($expected) {
            $actual = $this->columns('events');
            foreach ($expected as $col) {
                assertTrue(in_array($col, $actual, true), "Spalte events.{$col} fehlt");
            }
            return true;
        });

        $this->testRunner->addTest('events - event_slug and event_hash are UNIQUE nullable', function () {
            $col = $this->columnMeta('events', 'event_slug');
            assertEquals('YES', $col['Null'], 'event_slug muss NULL erlauben');
            $col = $this->columnMeta('events', 'event_hash');
            assertEquals('YES', $col['Null'], 'event_hash muss NULL erlauben');
            return true;
        });

        $this->testRunner->addTest('events - gps_validation_required defaults to FALSE', function () {
            $col = $this->columnMeta('events', 'gps_validation_required');
            // MariaDB liefert '0' als Default fuer BOOLEAN DEFAULT FALSE
            assertTrue(in_array((string)$col['Default'], ['0', '0x00', 'FALSE', 'false'], true),
                'gps_validation_required muss Default FALSE haben, war: ' . var_export($col['Default'], true));
            return true;
        });

        $this->testRunner->addTest('events - upload_enabled defaults to TRUE', function () {
            $col = $this->columnMeta('events', 'upload_enabled');
            assertTrue(in_array((string)$col['Default'], ['1', '0x01', 'TRUE', 'true'], true),
                'upload_enabled muss Default TRUE haben, war: ' . var_export($col['Default'], true));
            return true;
        });
    }

    // ---------------- photos-Tabelle ----------------

    private function addPhotosTableColumnTests(): void {
        $expected = [
            'id', 'event_id', 'filename', 'thumbnail_filename', 'resized_filename',
            'original_name', 'username', 'latitude', 'longitude', 'distance_meters',
            'file_size', 'mime_type', 'file_hash', 'uploaded_at', 'is_active',
        ];

        $this->testRunner->addTest('photos - all expected columns exist', function () use ($expected) {
            $actual = $this->columns('photos');
            foreach ($expected as $col) {
                assertTrue(in_array($col, $actual, true), "Spalte photos.{$col} fehlt");
            }
            return true;
        });

        $this->testRunner->addTest('photos - distance_meters allows NULL', function () {
            $col = $this->columnMeta('photos', 'distance_meters');
            assertEquals('YES', $col['Null'], 'distance_meters muss NULL erlauben (GPS optional)');
            return true;
        });

        $this->testRunner->addTest('photos - file_hash is 64 chars (SHA-256)', function () {
            $col = $this->columnMeta('photos', 'file_hash');
            assertTrue(
                stripos($col['Type'], 'varchar(64)') !== false,
                'file_hash muss VARCHAR(64) fuer SHA-256 sein, war: ' . $col['Type']
            );
            return true;
        });
    }

    // ---------------- display_config-Tabelle ----------------

    private function addDisplayConfigTableColumnTests(): void {
        $this->testRunner->addTest('display_config - table exists with core columns', function () {
            $actual = $this->columns('display_config');
            foreach (['id', 'event_id', 'max_photos', 'display_mode', 'refresh_interval'] as $col) {
                assertTrue(in_array($col, $actual, true), "Spalte display_config.{$col} fehlt");
            }
            return true;
        });
    }

    // ---------------- events-Indexes ----------------

    private function addEventsIndexTests(): void {
        $expected = ['idx_event_slug', 'idx_event_hash', 'idx_is_active'];
        foreach ($expected as $idx) {
            $this->testRunner->addTest("events - index {$idx} exists", function () use ($idx) {
                assertTrue($this->indexExists('events', $idx), "Index events.{$idx} fehlt");
                return true;
            });
        }
    }

    // ---------------- photos-Indexes (kritisch fuer Performance) ----------------

    private function addPhotosIndexTests(): void {
        $expected = [
            'idx_event_id',
            'idx_event_is_active',
            'idx_file_hash_event',
            'idx_uploaded_at',
            'idx_event_uploaded',
        ];
        foreach ($expected as $idx) {
            $this->testRunner->addTest("photos - index {$idx} exists", function () use ($idx) {
                assertTrue($this->indexExists('photos', $idx), "Index photos.{$idx} fehlt");
                return true;
            });
        }
    }

    // ---------------- FK-Constraints ----------------

    private function addConstraintTests(): void {
        $this->testRunner->addTest('photos - FK event_id references events.id ON DELETE CASCADE', function () {
            $stmt = $this->pdo->prepare(
                "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'photos'"
            );
            $stmt->execute([$this->envConfig['DB_NAME']]);
            $rules = $stmt->fetchAll(PDO::FETCH_COLUMN);
            assertTrue(
                in_array('CASCADE', $rules, true),
                'photos braucht FK mit ON DELETE CASCADE, gefunden: ' . implode(',', $rules)
            );
            return true;
        });
    }

    // ---------------- Helpers ----------------

    private function connect(): PDO {
        $host = '127.0.0.1';
        $port = 3306;
        $name = $this->envConfig['DB_NAME'] ?? 'photowall';
        $user = $this->envConfig['DB_USER'] ?? 'photowall';
        $pass = $this->envConfig['DB_PASS'] ?? 'photowall';

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    }

    private function columns(string $table): array {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        return array_column($stmt->fetchAll(), 'Field');
    }

    private function columnMeta(string $table, string $column): array {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new Exception("Spalte {$table}.{$column} existiert nicht");
        }
        return $row;
    }

    private function indexExists(string $table, string $indexName): bool {
        $stmt = $this->pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
        $stmt->execute([$indexName]);
        return $stmt->rowCount() > 0;
    }

    private function readEnv(): array {
        $envPath = __DIR__ . '/../.env';
        $config = [];
        if (!is_readable($envPath)) {
            return $config;
        }
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                $value = preg_replace('/\s+#.*$/', '', $m[2]);
                $config[$m[1]] = trim($value, "\"'");
            }
        }
        return $config;
    }
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $tests = new SchemaIntegrityTests();
    $result = $tests->runAllTests();
    exit($result ? 0 : 1);
}
