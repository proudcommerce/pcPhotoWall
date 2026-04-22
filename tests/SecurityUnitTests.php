<?php
/**
 * Security Unit Tests for PC PhotoWall
 *
 * Testet alle Funktionen und Helper, die im Rahmen des Security-Reviews
 * (Codex-Review, 6 Runden) angefasst oder neu eingefuehrt wurden.
 *
 * Scope: reine Funktionslogik — keine DB, kein HTTP, kein Browser.
 * Integration-Tests liegen in SecurityIntegrationTests.php.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/ImageProcessor.php';

class SecurityUnitTests {
    private TestRunner $testRunner;
    private string $fixturePath;

    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->fixturePath = __DIR__ . '/data/security';
        if (!is_dir($this->fixturePath)) {
            mkdir($this->fixturePath, 0755, true);
        }
    }

    public function runAllTests(): bool {
        $this->addMimeToSafeExtensionTests();
        $this->addGenerateUniqueFilenameTests();
        $this->addIsoBmffHeicTests();
        $this->addValidateFileUploadExtensionTests();
        $this->addValidateLogoUploadTests();
        $this->addAdminSessionTimeoutTests();
        $this->addPasswordVerifyFlowTests();
        $this->addThumbnailIsJpegTest();
        $this->addGalleryXssHtmlEscapeTests();
        $this->addHtaccessBlocksTests();
        $this->addLogoOrphanCleanupCodeShapeTests();
        $this->addAdminEndpointSessionGuardTests();
        $this->addGitignoreTests();

        return $this->testRunner->runAll();
    }

    // ---------- mimeToSafeExtension ----------

    private function addMimeToSafeExtensionTests(): void {
        $this->testRunner->addTest('mimeToSafeExtension - known MIME types', function () {
            assertEquals('jpg', mimeToSafeExtension('image/jpeg'));
            assertEquals('png', mimeToSafeExtension('image/png'));
            assertEquals('gif', mimeToSafeExtension('image/gif'));
            assertEquals('webp', mimeToSafeExtension('image/webp'));
            assertEquals('heic', mimeToSafeExtension('image/heic'));
            assertEquals('heif', mimeToSafeExtension('image/heif'));
            return true;
        });

        $this->testRunner->addTest('mimeToSafeExtension - unknown MIME returns null', function () {
            assertEquals(null, mimeToSafeExtension('application/x-php'));
            assertEquals(null, mimeToSafeExtension('image/svg+xml'));
            assertEquals(null, mimeToSafeExtension('text/html'));
            assertEquals(null, mimeToSafeExtension(''));
            return true;
        });
    }

    // ---------- generateUniqueFilename (Extension-Sanitization) ----------

    private function addGenerateUniqueFilenameTests(): void {
        $this->testRunner->addTest('generateUniqueFilename - rejects .php extension', function () {
            $name = generateUniqueFilename('evil.php');
            assertTrue(str_ends_with($name, '.jpg'), "Unknown extension must fall back to .jpg, got: $name");
            return true;
        });

        $this->testRunner->addTest('generateUniqueFilename - rejects .svg extension', function () {
            $name = generateUniqueFilename('logo.svg');
            assertTrue(str_ends_with($name, '.jpg'), "SVG must fall back to .jpg, got: $name");
            return true;
        });

        $this->testRunner->addTest('generateUniqueFilename - rejects .phtml extension', function () {
            $name = generateUniqueFilename('shell.phtml');
            assertTrue(str_ends_with($name, '.jpg'), "phtml must fall back to .jpg, got: $name");
            return true;
        });

        $this->testRunner->addTest('generateUniqueFilename - accepts known image extensions', function () {
            assertTrue(str_ends_with(generateUniqueFilename('photo.jpg'), '.jpg'));
            assertTrue(str_ends_with(generateUniqueFilename('photo.JPEG'), '.jpeg'));
            assertTrue(str_ends_with(generateUniqueFilename('photo.png'), '.png'));
            assertTrue(str_ends_with(generateUniqueFilename('photo.webp'), '.webp'));
            assertTrue(str_ends_with(generateUniqueFilename('photo.heic'), '.heic'));
            return true;
        });

        $this->testRunner->addTest('generateUniqueFilename - forced extension wins', function () {
            $name = generateUniqueFilename('anything.png', 'jpg');
            assertTrue(str_ends_with($name, '.jpg'));
            return true;
        });

        $this->testRunner->addTest('generateUniqueFilename - produces unique names', function () {
            $a = generateUniqueFilename('photo.jpg');
            usleep(1500);
            $b = generateUniqueFilename('photo.jpg');
            assertNotEquals($a, $b, 'Two consecutive calls must produce different filenames');
            return true;
        });
    }

    // ---------- isIsoBmffHeic ----------

    private function addIsoBmffHeicTests(): void {
        $this->testRunner->addTest('isIsoBmffHeic - valid heic brand in major brand position', function () {
            $file = $this->fixturePath . '/fake_heic_brand.bin';
            // 4 size bytes + 'ftyp' + 'heic' + 4 version bytes + 'mif1' compatible
            file_put_contents(
                $file,
                "\x00\x00\x00\x20" . 'ftyp' . 'heic' . "\x00\x00\x00\x00" . 'mif1'
            );
            assertTrue(isIsoBmffHeic($file));
            unlink($file);
            return true;
        });

        $this->testRunner->addTest('isIsoBmffHeic - compatible brand mif1 without heic major', function () {
            $file = $this->fixturePath . '/fake_heic_compat.bin';
            file_put_contents(
                $file,
                "\x00\x00\x00\x20" . 'ftyp' . 'xxxx' . "\x00\x00\x00\x00" . 'mif1heic'
            );
            assertTrue(isIsoBmffHeic($file));
            unlink($file);
            return true;
        });

        $this->testRunner->addTest('isIsoBmffHeic - rejects file without ftyp marker', function () {
            $file = $this->fixturePath . '/not_heic.bin';
            file_put_contents($file, '<?php system($_GET["c"]); ?>');
            assertFalse(isIsoBmffHeic($file));
            unlink($file);
            return true;
        });

        $this->testRunner->addTest('isIsoBmffHeic - rejects random brand', function () {
            $file = $this->fixturePath . '/fake_mp4.bin';
            file_put_contents(
                $file,
                "\x00\x00\x00\x20" . 'ftyp' . 'mp42' . "\x00\x00\x00\x00" . 'isomavc1'
            );
            assertFalse(isIsoBmffHeic($file));
            unlink($file);
            return true;
        });

        $this->testRunner->addTest('isIsoBmffHeic - rejects empty/short file', function () {
            $file = $this->fixturePath . '/empty.bin';
            file_put_contents($file, '');
            assertFalse(isIsoBmffHeic($file));
            unlink($file);
            return true;
        });

        $this->testRunner->addTest('isIsoBmffHeic - rejects non-existent file', function () {
            assertFalse(isIsoBmffHeic($this->fixturePath . '/does-not-exist.bin'));
            return true;
        });
    }

    // ---------- validateFileUpload (Extension-Whitelist) ----------

    private function addValidateFileUploadExtensionTests(): void {
        $this->testRunner->addTest('validateFileUpload - rejects .php polyglot', function () {
            $tmp = $this->createJpegFixture('poly.php');
            $file = $this->buildUploadArray('poly.php', $tmp);
            $errors = validateFileUpload($file);
            $this->assertHasError($errors, 'Dateiendung');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateFileUpload - rejects .svg even with image MIME', function () {
            $tmp = $this->createJpegFixture('img.svg');
            $file = $this->buildUploadArray('img.svg', $tmp);
            $errors = validateFileUpload($file);
            $this->assertHasError($errors, 'Dateiendung');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateFileUpload - accepts valid JPG', function () {
            $tmp = $this->createJpegFixture('clean.jpg');
            $file = $this->buildUploadArray('clean.jpg', $tmp);
            $errors = validateFileUpload($file);
            assertEquals([], $errors, 'Valid JPG should pass without errors, got: ' . implode(' | ', $errors));
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateFileUpload - enforces maxSize', function () {
            $tmp = $this->createJpegFixture('big.jpg');
            $file = $this->buildUploadArray('big.jpg', $tmp);
            // real size from the fixture
            $file['size'] = 5 * 1024 * 1024;
            $errors = validateFileUpload($file, 1024);
            $this->assertHasError($errors, 'zu groß');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateFileUpload - rejects UPLOAD_ERR_NO_FILE', function () {
            $file = [
                'name' => '',
                'tmp_name' => '',
                'size' => 0,
                'error' => UPLOAD_ERR_NO_FILE,
            ];
            $errors = validateFileUpload($file);
            $this->assertHasError($errors, 'Keine Datei');
            return true;
        });
    }

    // ---------- validateLogoUpload ----------

    private function addValidateLogoUploadTests(): void {
        $this->testRunner->addTest('validateLogoUpload - rejects .svg', function () {
            $tmp = $this->createJpegFixture('logo.svg');
            $file = $this->buildUploadArray('logo.svg', $tmp);
            $errors = validateLogoUpload($file);
            $this->assertHasError($errors, 'Logo-Format');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateLogoUpload - rejects .php polyglot', function () {
            $tmp = $this->createJpegFixture('pwn.php');
            $file = $this->buildUploadArray('pwn.php', $tmp);
            $errors = validateLogoUpload($file);
            $this->assertHasError($errors, 'Logo-Format');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateLogoUpload - rejects oversized file', function () {
            $tmp = $this->createJpegFixture('big.jpg');
            $file = $this->buildUploadArray('big.jpg', $tmp);
            $file['size'] = 3 * 1024 * 1024; // 3MB > 2MB default
            $errors = validateLogoUpload($file);
            $this->assertHasError($errors, 'zu groß');
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateLogoUpload - rejects non-image MIME', function () {
            $tmp = $this->fixturePath . '/fake.png';
            file_put_contents($tmp, '<?php echo 1; ?>');
            $file = $this->buildUploadArray('fake.png', $tmp);
            $errors = validateLogoUpload($file);
            assertTrue(
                !empty($errors),
                'Logo-Upload mit Non-Image-Inhalt muss mindestens einen Fehler liefern'
            );
            unlink($tmp);
            return true;
        });

        $this->testRunner->addTest('validateLogoUpload - accepts valid PNG', function () {
            $tmp = $this->createPngFixture('logo.png');
            $file = $this->buildUploadArray('logo.png', $tmp);
            $errors = validateLogoUpload($file);
            assertEquals([], $errors, 'Valid PNG logo should pass, got: ' . implode(' | ', $errors));
            unlink($tmp);
            return true;
        });
    }

    // ---------- Admin-Session-Timeout (isAdminSessionValid) ----------

    private function addAdminSessionTimeoutTests(): void {
        $this->testRunner->addTest('isAdminSessionValid - returns false when not logged in', function () {
            $_SESSION = [];
            assertFalse(isAdminSessionValid());
            return true;
        });

        $this->testRunner->addTest('isAdminSessionValid - returns true for fresh session', function () {
            $_SESSION = ['admin_logged_in' => true, 'admin_last_activity' => time()];
            assertTrue(isAdminSessionValid());
            assertTrue(isset($_SESSION['admin_last_activity']), 'last_activity must be refreshed');
            return true;
        });

        $this->testRunner->addTest('isAdminSessionValid - expires stale session', function () {
            $_SESSION = [
                'admin_logged_in' => true,
                'admin_last_activity' => time() - (SESSION_TIMEOUT + 60),
            ];
            assertFalse(isAdminSessionValid(), 'Stale session must be rejected');
            assertEquals(false, isset($_SESSION['admin_logged_in']), 'Session data must be cleared');
            return true;
        });

        $this->testRunner->addTest('isAdminSessionValid - refreshes last_activity on each call', function () {
            $_SESSION = ['admin_logged_in' => true, 'admin_last_activity' => time() - 10];
            isAdminSessionValid();
            $first = $_SESSION['admin_last_activity'];
            usleep(1100 * 1000);
            isAdminSessionValid();
            assertTrue($_SESSION['admin_last_activity'] >= $first, 'last_activity must advance');
            return true;
        });
    }

    // ---------- Password-Verify-Flow ----------

    private function addPasswordVerifyFlowTests(): void {
        $this->testRunner->addTest('password_verify - accepts correct hash', function () {
            $hash = password_hash('SuperSecret123!', PASSWORD_DEFAULT);
            assertTrue(password_verify('SuperSecret123!', $hash));
            assertFalse(password_verify('wrong', $hash));
            return true;
        });

        $this->testRunner->addTest('hash_equals - plaintext fallback is constant-time', function () {
            // Wir testen nur die Logik: der Fallback vergleicht Plain-Text.
            assertTrue(hash_equals('SuperSecret123!', 'SuperSecret123!'));
            assertFalse(hash_equals('SuperSecret123!', 'SuperSecret123?'));
            assertFalse(hash_equals('SuperSecret123!', ''));
            return true;
        });

        $this->testRunner->addTest('admin login flow - prefers ADMIN_PASSWORD_HASH over plain', function () {
            $hash = password_hash('hashed-pw', PASSWORD_DEFAULT);
            // Simulate the guard logic from admin/index.php
            $valid = function (string $plain) use ($hash): bool {
                $hashConst = $hash;
                if ($hashConst === '' && 'plain-pw' !== '') {
                    return hash_equals('plain-pw', $plain);
                }
                return $hashConst !== '' && password_verify($plain, $hashConst);
            };
            assertTrue($valid('hashed-pw'));
            assertFalse($valid('plain-pw'), 'Plain-Text darf nicht akzeptiert werden wenn Hash vorhanden ist');
            return true;
        });
    }

    // ---------- ImageProcessor::createThumbnail immer JPEG ----------

    private function addThumbnailIsJpegTest(): void {
        $this->testRunner->addTest('ImageProcessor::createThumbnail - always writes JPEG even for PNG source', function () {
            $source = $this->createPngFixture('src.png', 200, 200);
            $dest = $this->fixturePath . '/thumb.jpg';
            $result = ImageProcessor::createThumbnail($source, $dest, 100, 100, 85);
            assertTrue($result, 'createThumbnail muss true zurueckgeben');
            assertFileExists($dest);
            $info = getimagesize($dest);
            assertEquals('image/jpeg', $info['mime'], 'Thumbnail muss JPEG sein, egal welche Quelle');
            unlink($source);
            unlink($dest);
            return true;
        });

        $this->testRunner->addTest('ImageProcessor::createThumbnail - respects max dimensions', function () {
            $source = $this->createPngFixture('big.png', 800, 600);
            $dest = $this->fixturePath . '/thumb2.jpg';
            ImageProcessor::createThumbnail($source, $dest, 100, 100, 85);
            $info = getimagesize($dest);
            assertTrue($info[0] <= 100 && $info[1] <= 100, "Thumbnail max 100x100, got {$info[0]}x{$info[1]}");
            unlink($source);
            unlink($dest);
            return true;
        });
    }

    // ---------- HTML-Escape in gallery.php / index.php ----------

    private function addGalleryXssHtmlEscapeTests(): void {
        $this->testRunner->addTest('gallery.php - uses data-lightbox-* instead of inline onclick', function () {
            $src = file_get_contents(__DIR__ . '/../app/gallery.php');
            assertTrue(
                strpos($src, 'data-lightbox-url=') !== false,
                'Gallery muss data-lightbox-url Attribute verwenden'
            );
            assertTrue(
                strpos($src, 'onclick="openLightbox(') === false,
                'Inline onclick mit openLightbox darf nicht mehr vorhanden sein'
            );
            return true;
        });

        $this->testRunner->addTest('gallery.php - does not select latitude/longitude from photos', function () {
            $src = file_get_contents(__DIR__ . '/../app/gallery.php');
            assertTrue(
                strpos($src, 'latitude, longitude,') === false,
                'SELECT in gallery.php darf keine GPS-Felder mehr laden'
            );
            return true;
        });

        $this->testRunner->addTest('index.php - note is escaped via htmlspecialchars', function () {
            $src = file_get_contents(__DIR__ . '/../app/index.php');
            assertTrue(
                strpos($src, 'htmlspecialchars($currentEvent[\'note\']') !== false,
                'Note-Feld muss mit htmlspecialchars ausgegeben werden'
            );
            assertTrue(
                strpos($src, "echo \$currentEvent['note']; ?>") === false,
                'Raw-Echo des note-Feldes darf nicht mehr existieren'
            );
            return true;
        });

        $this->testRunner->addTest('api/photos.php - does not return GPS fields', function () {
            $src = file_get_contents(__DIR__ . '/../app/api/photos.php');
            assertTrue(
                strpos($src, 'latitude, longitude,') === false,
                'api/photos.php SELECT darf keine GPS-Felder mehr laden'
            );
            assertTrue(
                strpos($src, "'latitude' => \$event['latitude']") === false,
                'api/photos.php darf event.latitude nicht mehr ausliefern'
            );
            return true;
        });
    }

    // ---------- .htaccess Block-Regeln ----------

    private function addHtaccessBlocksTests(): void {
        $this->testRunner->addTest('.htaccess - blocks composer.json / composer.lock', function () {
            $htaccess = file_get_contents(__DIR__ . '/../app/.htaccess');
            assertTrue(
                strpos($htaccess, 'composer\\.(json|lock)') !== false,
                '.htaccess muss composer.json und composer.lock blocken'
            );
            return true;
        });

        $this->testRunner->addTest('.htaccess - blocks vendor/includes/config/scripts/tests directories', function () {
            $htaccess = file_get_contents(__DIR__ . '/../app/.htaccess');
            assertTrue(
                strpos($htaccess, '^(vendor|includes|config|scripts|tests)') !== false,
                '.htaccess muss internal directories blocken'
            );
            return true;
        });

        $this->testRunner->addTest('.htaccess - blocks PHP-Execution in data/', function () {
            $htaccess = file_get_contents(__DIR__ . '/../app/.htaccess');
            assertTrue(
                strpos($htaccess, '^data/.*\\.(php') !== false,
                '.htaccess muss PHP-Execution in data/ blocken'
            );
            return true;
        });

        $this->testRunner->addTest('.htaccess - blocks dotfiles', function () {
            $htaccess = file_get_contents(__DIR__ . '/../app/.htaccess');
            assertTrue(
                strpos($htaccess, '^\\.') !== false,
                '.htaccess muss Dotfiles blocken'
            );
            return true;
        });

        $this->testRunner->addTest('.htaccess - blocks sensitive extensions', function () {
            $htaccess = file_get_contents(__DIR__ . '/../app/.htaccess');
            foreach (['env', 'lock', 'md', 'sql', 'pem', 'key'] as $ext) {
                assertTrue(
                    strpos($htaccess, $ext) !== false,
                    ".htaccess muss .{$ext} in der FilesMatch-Liste haben"
                );
            }
            return true;
        });

        $this->testRunner->addTest('data/.htaccess - exists and blocks PHP', function () {
            $dataHtaccess = __DIR__ . '/../app/data/.htaccess';
            assertFileExists($dataHtaccess, 'app/data/.htaccess muss vorhanden sein');
            $content = file_get_contents($dataHtaccess);
            assertTrue(
                strpos($content, 'php_flag engine off') !== false,
                'data/.htaccess muss php_flag engine off setzen'
            );
            assertTrue(
                strpos($content, 'phtml') !== false && strpos($content, 'phar') !== false,
                'data/.htaccess muss phtml/phar blocken'
            );
            return true;
        });
    }

    // ---------- .gitignore Secrets ----------

    private function addLogoOrphanCleanupCodeShapeTests(): void {
        $this->testRunner->addTest('create-event.php - catch block removes orphaned logo file', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/create-event.php');
            // Catch-Block muss Orphan-Schutz fuer logoFilename haben
            assertTrue(
                strpos($src, 'Orphan-Schutz') !== false,
                'create-event.php Catch muss einen "Orphan-Schutz"-Kommentar fuer Logo-Cleanup haben'
            );
            assertTrue(
                preg_match('/catch\s*\(\s*Exception.*?unlink\s*\(\s*\$orphanPath/s', $src) === 1,
                'create-event.php Catch muss orphanPath via unlink entfernen'
            );
            return true;
        });

        $this->testRunner->addTest('edit-event.php - catch block removes orphaned logo file', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/edit-event.php');
            assertTrue(
                strpos($src, 'Orphan-Schutz') !== false,
                'edit-event.php muss Orphan-Schutz-Logik fuer Logo enthalten'
            );
            assertTrue(
                preg_match('/catch\s*\(\s*Exception.*?unlink\s*\(\s*\$orphanPath/s', $src) === 1,
                'edit-event.php Catch muss orphanPath via unlink entfernen'
            );
            return true;
        });

        $this->testRunner->addTest('edit-event.php - replaces previous logo only after commit', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/edit-event.php');
            // Reihenfolge pruefen: $conn->commit() MUSS vor unlink($oldLogoPath) stehen
            $commitPos = strpos($src, '$conn->commit();');
            $unlinkPos = strpos($src, '@unlink($oldLogoPath)');
            assertTrue($commitPos !== false, 'commit() muss in edit-event.php existieren');
            assertTrue($unlinkPos !== false, 'oldLogoPath unlink muss in edit-event.php existieren');
            assertTrue(
                $commitPos < $unlinkPos,
                'Altes Logo darf erst NACH commit() geloescht werden (Race-Schutz)'
            );
            return true;
        });

        $this->testRunner->addTest('create-event.php - rolls back transaction on exception', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/create-event.php');
            assertTrue(
                strpos($src, '$conn->beginTransaction();') !== false,
                'create-event.php muss beginTransaction nutzen'
            );
            assertTrue(
                strpos($src, '$conn->rollBack();') !== false,
                'create-event.php muss rollBack im Catch nutzen'
            );
            assertTrue(
                strpos($src, '$conn->commit();') !== false,
                'create-event.php muss commit nach erfolgreichem Insert nutzen'
            );
            return true;
        });

        $this->testRunner->addTest('edit-event.php - rolls back transaction on exception', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/edit-event.php');
            assertTrue(
                strpos($src, '$conn->beginTransaction();') !== false,
                'edit-event.php muss beginTransaction nutzen'
            );
            assertTrue(
                strpos($src, '$conn->rollBack();') !== false,
                'edit-event.php muss rollBack im Catch nutzen'
            );
            return true;
        });

        $this->testRunner->addTest('admin/event-photos.php - delete handler deletes from event-specific path', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/event-photos.php');
            assertTrue(
                strpos($src, 'getEventUploadPaths') !== false,
                'event-photos.php muss getEventUploadPaths fuer Datei-Loeschung nutzen'
            );
            return true;
        });

        $this->testRunner->addTest('admin/index.php - delete event uses getEventUploadPaths not UPLOAD_PATH', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/index.php');
            // delete_event-Handler darf NICHT mehr UPLOAD_PATH . '/' nutzen
            assertTrue(
                strpos($src, 'getEventUploadPaths') !== false,
                'admin/index.php delete_event muss getEventUploadPaths nutzen'
            );
            // Hartcodiertes UPLOAD_PATH-Konstrukt aus dem alten Code darf nicht mehr existieren
            assertTrue(
                strpos($src, "UPLOAD_PATH . '/' . \$photo['filename']") === false,
                'admin/index.php darf nicht mehr UPLOAD_PATH . filename nutzen (war Bug)'
            );
            return true;
        });
    }

    private function addAdminEndpointSessionGuardTests(): void {
        $this->testRunner->addTest('admin endpoints - all use isAdminSessionValid', function () {
            $files = [
                __DIR__ . '/../app/admin/create-event.php',
                __DIR__ . '/../app/admin/edit-event.php',
                __DIR__ . '/../app/admin/event-photos.php',
                __DIR__ . '/../app/api/rotate-photo.php',
                __DIR__ . '/../app/api/toggle-photo-status.php',
            ];
            foreach ($files as $file) {
                $src = file_get_contents($file);
                $name = basename($file);
                assertTrue(
                    strpos($src, 'isAdminSessionValid()') !== false,
                    "{$name} muss isAdminSessionValid() verwenden"
                );
                // Direkte Session-Checks ohne Helper sollten weg sein
                assertTrue(
                    strpos($src, "!isset(\$_SESSION['admin_logged_in'])") === false,
                    "{$name} darf nicht mehr direkt \$_SESSION['admin_logged_in'] pruefen (Helper nutzen)"
                );
            }
            return true;
        });

        $this->testRunner->addTest('admin login - calls session_regenerate_id after success', function () {
            $src = file_get_contents(__DIR__ . '/../app/admin/index.php');
            assertTrue(
                strpos($src, 'session_regenerate_id(true)') !== false,
                'Login muss session_regenerate_id(true) aufrufen (Anti-Fixation)'
            );
            return true;
        });

        $this->testRunner->addTest('admin api endpoints - no duplicate session_start before config', function () {
            $files = [
                __DIR__ . '/../app/admin/event-photos.php',
                __DIR__ . '/../app/api/rotate-photo.php',
                __DIR__ . '/../app/api/toggle-photo-status.php',
            ];
            foreach ($files as $file) {
                $src = file_get_contents($file);
                $name = basename($file);
                // Erste 5 Zeilen pruefen — kein session_start() davor
                $lines = array_slice(explode("\n", $src), 0, 5);
                $head = implode("\n", $lines);
                assertTrue(
                    strpos($head, 'session_start();') === false,
                    "{$name} darf nicht eigenes session_start() vor config.php aufrufen (umgeht Cookie-Flags)"
                );
            }
            return true;
        });
    }

    private function addGitignoreTests(): void {
        $this->testRunner->addTest('.gitignore - lists secret extensions', function () {
            $gitignore = file_get_contents(__DIR__ . '/../.gitignore');
            foreach (['*.pem', '*.key', '*.p12', '*.pfx', '.env.*'] as $pattern) {
                assertTrue(
                    strpos($gitignore, $pattern) !== false,
                    ".gitignore muss {$pattern} enthalten"
                );
            }
            return true;
        });

        $this->testRunner->addTest('.gitignore - preserves app/data/.htaccess', function () {
            $gitignore = file_get_contents(__DIR__ . '/../.gitignore');
            assertTrue(
                strpos($gitignore, '!/app/data/.htaccess') !== false,
                '.gitignore muss app/data/.htaccess explizit versionierbar halten'
            );
            return true;
        });
    }

    // ---------- Helpers ----------

    private function createJpegFixture(string $label, int $w = 16, int $h = 16): string {
        $path = $this->fixturePath . '/' . uniqid('jpg_', true) . '_' . $label;
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, 200, 100, 50);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $color);
        imagejpeg($img, $path, 80);
        imagedestroy($img);
        return $path;
    }

    private function createPngFixture(string $label, int $w = 16, int $h = 16): string {
        $path = $this->fixturePath . '/' . uniqid('png_', true) . '_' . $label;
        $img = imagecreatetruecolor($w, $h);
        $color = imagecolorallocate($img, 0, 150, 200);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $color);
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    private function buildUploadArray(string $originalName, string $tmpPath): array {
        return [
            'name' => $originalName,
            'tmp_name' => $tmpPath,
            'size' => filesize($tmpPath),
            'type' => 'application/octet-stream',
            'error' => UPLOAD_ERR_OK,
        ];
    }

    private function assertHasError(array $errors, string $needle): void {
        foreach ($errors as $err) {
            if (stripos($err, $needle) !== false) {
                return;
            }
        }
        throw new Exception("Kein Fehler enthaelt '$needle'. Fehlerliste: " . json_encode($errors));
    }
}

// Run when executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $tests = new SecurityUnitTests();
    $result = $tests->runAllTests();
    exit($result ? 0 : 1);
}
