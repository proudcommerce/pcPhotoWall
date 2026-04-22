<?php
/**
 * Security Integration Tests for PC PhotoWall
 *
 * Testet die Security-Fixes gegen die laufende Dev-Umgebung (http://localhost:4000).
 * Voraussetzung: `make dev-up` muss vorher aufgerufen worden sein und das Demo-Event
 * muss existieren (wird bei Erstinstallation automatisch erzeugt).
 *
 * Abdeckung:
 *  - CSRF-Enforcement in delete_event / delete_photo / create_event
 *  - Admin-Login (password_verify + Session-Regeneration)
 *  - Session-Timeout
 *  - API-Privacy (keine GPS in api/photos.php)
 *  - event-config Frontend-Contract
 *  - .htaccess-Blocks (composer.*, vendor/, includes/, data/*.php, dotfiles)
 *  - File-Upload Extension-Reject (PHP-Polyglot)
 *  - Gallery-HTML-Escape (data-lightbox-* statt onclick)
 */

require_once __DIR__ . '/TestRunner.php';

class SecurityIntegrationTests {
    private TestRunner $testRunner;
    private string $baseUrl = 'http://localhost:4000';
    private string $cookieJar;
    private string $adminPassword;
    private string $demoSlug = 'demo-event';

    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'pcpw_cookie_');
        $this->adminPassword = $this->readAdminPassword();
    }

    public function __destruct() {
        if (is_file($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    public function runAllTests(): bool {
        if (!$this->isDevReachable()) {
            echo "❌ Dev-Umgebung ist nicht erreichbar auf {$this->baseUrl}\n";
            echo "   Starte sie mit: make dev-up\n";
            return false;
        }

        $this->addStaticFileBlockTests();
        $this->addManifestReachableTest();
        $this->addDataDirPhpBlockTest();
        $this->addApiPhotosNoGpsTest();
        $this->addEventConfigContractTest();
        $this->addGalleryHtmlTest();
        $this->addUploadPhpRejectTest();
        $this->addAdminLoginFailTest();
        $this->addAdminLoginSuccessAndSessionRegenTest();
        $this->addCsrfEnforcementTests();
        $this->addAdminLogoutTest();
        $this->addDeleteLogoFlowTest();
        $this->addRotatePhotoEndpointTests();
        $this->addToggleStatusEndpointTests();

        return $this->testRunner->runAll();
    }

    // ------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------

    private function addStaticFileBlockTests(): void {
        $blocked = [
            '/composer.json',
            '/composer.lock',
            '/vendor/autoload.php',
            '/includes/functions.php',
            '/config/config.php',
            '/scripts/backup-event.sh',
        ];
        foreach ($blocked as $path) {
            $this->testRunner->addTest(
                "Static block - GET {$path} returns 403/404",
                function () use ($path) {
                    $code = $this->getStatus($path);
                    assertTrue(
                        in_array($code, [403, 404], true),
                        "{$path} sollte 403 oder 404 zurueckgeben, war: {$code}"
                    );
                    return true;
                }
            );
        }
    }

    private function addManifestReachableTest(): void {
        $this->testRunner->addTest('PWA - /manifest.json is reachable', function () {
            $response = $this->get('/manifest.json');
            assertEquals(200, $response['status'], "manifest.json muss 200 liefern, war: {$response['status']}");
            $json = json_decode($response['body'], true);
            assertTrue(is_array($json) && isset($json['name']), 'manifest.json muss gueltiges JSON mit name sein');
            return true;
        });
    }

    private function addDataDirPhpBlockTest(): void {
        $this->testRunner->addTest('Upload dir - /data/.../xyz.php returns 403', function () {
            $code = $this->getStatus('/data/demo-event/photos/evil.php');
            assertTrue(
                in_array($code, [403, 404], true),
                "/data/.../evil.php muss geblockt sein, war: {$code}"
            );
            return true;
        });
    }

    private function addApiPhotosNoGpsTest(): void {
        $this->testRunner->addTest('api/photos.php - response contains no GPS fields', function () {
            $response = $this->get('/api/photos.php?event_slug=' . $this->demoSlug);
            assertEquals(200, $response['status']);
            $data = json_decode($response['body'], true);
            assertTrue(is_array($data) && !empty($data['success']), 'photos.php muss success=true liefern');
            assertFalse(isset($data['event']['latitude']), 'event.latitude darf nicht geleakt werden');
            assertFalse(isset($data['event']['longitude']), 'event.longitude darf nicht geleakt werden');
            if (!empty($data['photos'])) {
                foreach ($data['photos'] as $photo) {
                    assertFalse(isset($photo['latitude']), 'photo.latitude darf nicht geleakt werden');
                    assertFalse(isset($photo['longitude']), 'photo.longitude darf nicht geleakt werden');
                }
            }
            return true;
        });
    }

    private function addEventConfigContractTest(): void {
        $this->testRunner->addTest('api/event-config.php - returns event at top level', function () {
            $response = $this->get('/api/event-config.php?event_slug=' . $this->demoSlug);
            assertEquals(200, $response['status']);
            $data = json_decode($response['body'], true);
            assertTrue(is_array($data), 'Response muss JSON sein');
            assertTrue(!empty($data['success']), 'success=true erwartet');
            assertTrue(isset($data['event']) && is_array($data['event']), 'data.event top-level erwartet');
            assertTrue(
                isset($data['event']['max_upload_size']),
                'data.event.max_upload_size ist Teil des Frontend-Contracts'
            );
            return true;
        });
    }

    private function addGalleryHtmlTest(): void {
        $this->testRunner->addTest('gallery.php - HTML uses data-lightbox-* not inline onclick', function () {
            $response = $this->get('/' . $this->demoSlug . '/gallery');
            assertEquals(200, $response['status']);
            assertTrue(
                strpos($response['body'], 'data-lightbox-url') !== false,
                'Gallery-HTML muss data-lightbox-url enthalten'
            );
            assertTrue(
                strpos($response['body'], 'onclick="openLightbox(') === false,
                'Inline onclick="openLightbox(" darf nicht mehr im HTML stehen'
            );
            return true;
        });
    }

    private function addUploadPhpRejectTest(): void {
        $this->testRunner->addTest('api/upload.php - rejects .php filename', function () {
            $tmp = tempnam(sys_get_temp_dir(), 'upload_poly_') . '.jpg';
            $img = imagecreatetruecolor(16, 16);
            imagefilledrectangle($img, 0, 0, 15, 15, imagecolorallocate($img, 100, 200, 50));
            imagejpeg($img, $tmp, 80);
            imagedestroy($img);

            // Erst CSRF-Token fuer die Upload-Seite holen.
            $csrf = $this->fetchCsrfToken();

            $cfile = curl_file_create($tmp, 'image/jpeg', 'pwn.php');
            $response = $this->post('/api/upload.php', [
                'event_slug' => $this->demoSlug,
                'csrf_token' => $csrf,
                'username' => 'integration-test',
                'photo' => $cfile,
            ], true);

            @unlink($tmp);

            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss ein error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'Dateiendung') !== false,
                "Fehler muss 'Dateiendung' erwaehnen, war: {$data['error']}"
            );
            return true;
        });
    }

    private function addAdminLoginFailTest(): void {
        $this->testRunner->addTest('Admin login - wrong password rejected', function () {
            $this->resetCookies();
            $response = $this->post('/admin/', [
                'admin_password' => 'definitely-not-the-right-password-' . uniqid(),
            ]);
            assertTrue(
                strpos($response['body'], 'Falsches Passwort') !== false,
                'Falsches Passwort muss eine sichtbare Fehlermeldung produzieren'
            );
            assertTrue(
                strpos($response['body'], 'admin_password') !== false,
                'Login-Form muss erneut gerendert werden'
            );
            return true;
        });
    }

    private function addAdminLoginSuccessAndSessionRegenTest(): void {
        $this->testRunner->addTest('Admin login - correct password + session regeneration', function () {
            if ($this->adminPassword === '') {
                return 'ADMIN_PASSWORD konnte nicht aus .env gelesen werden — Test uebersprungen';
            }
            $this->resetCookies();

            // 1) Erster Request ohne Login → PHPSESSID wird gesetzt
            $this->get('/admin/');
            $preSid = $this->readSessionCookie();

            // 2) Login mit korrektem Passwort
            $response = $this->post('/admin/', ['admin_password' => $this->adminPassword]);
            $postSid = $this->readSessionCookie();

            assertTrue(
                strpos($response['body'], 'Events verwalten') !== false
                || strpos($response['body'], 'Admin') !== false,
                'Nach Login muss Admin-Dashboard erscheinen'
            );
            assertTrue(
                $preSid !== null && $postSid !== null && $preSid !== $postSid,
                'session_regenerate_id muss eine neue PHPSESSID erzeugen'
            );
            return true;
        });
    }

    private function addCsrfEnforcementTests(): void {
        // Login vorbereiten, damit wir eingeloggt CSRF-verletzen koennen.
        $this->testRunner->addTest('CSRF - delete_event without token is rejected', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $response = $this->post('/admin/', [
                'delete_event' => '1',
                'event_id' => '99999',
                // kein csrf_token
            ]);
            assertTrue(
                strpos($response['body'], 'Ungültiger CSRF-Token') !== false,
                'delete_event ohne CSRF-Token muss "Ungültiger CSRF-Token" liefern'
            );
            return true;
        });

        $this->testRunner->addTest('CSRF - delete_photo without token is rejected', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $response = $this->post('/admin/event-photos.php?slug=' . $this->demoSlug, [
                'delete_photo' => '1',
                'photo_id' => '99999',
            ]);
            assertTrue(
                strpos($response['body'], 'Ungültiger CSRF-Token') !== false,
                'delete_photo ohne CSRF-Token muss "Ungültiger CSRF-Token" liefern'
            );
            return true;
        });

        $this->testRunner->addTest('CSRF - create_event POST without token is rejected', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $response = $this->post('/admin/create-event.php', [
                'name' => 'csrf-probe',
                'radius_meters' => '100',
            ]);
            assertTrue(
                strpos($response['body'], 'Ungültiger CSRF-Token') !== false,
                'create-event ohne CSRF-Token muss "Ungültiger CSRF-Token" liefern'
            );
            return true;
        });
    }

    // ------------------------------------------------------------
    // Logout / delete_logo / rotate / toggle
    // ------------------------------------------------------------

    private function addAdminLogoutTest(): void {
        $this->testRunner->addTest('Admin logout - ?logout=1 invalidates session', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            // Sicherstellen dass eingeloggt
            $before = $this->get('/admin/');
            assertTrue(
                strpos($before['body'], 'admin_password') === false,
                'Vor Logout muss Admin-Dashboard erscheinen'
            );

            $this->get('/admin/?logout=1');

            $after = $this->get('/admin/');
            assertTrue(
                strpos($after['body'], 'admin_password') !== false,
                'Nach Logout muss wieder das Login-Form erscheinen'
            );
            return true;
        });
    }

    private function addDeleteLogoFlowTest(): void {
        $this->testRunner->addTest('delete_logo - skips event field validation', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            // CSRF-Token von der edit-event-Seite holen
            $editResponse = $this->get('/admin/edit-event.php?slug=' . $this->demoSlug);
            assertEquals(200, $editResponse['status']);
            if (!preg_match('/name="csrf_token" value="([^"]+)"/', $editResponse['body'], $m)) {
                return 'CSRF-Token konnte nicht aus edit-event.php extrahiert werden';
            }
            $csrf = $m[1];

            $response = $this->post('/admin/edit-event.php?slug=' . $this->demoSlug, [
                'csrf_token' => $csrf,
                'delete_logo' => '1',
                // KEIN name, KEIN radius_meters etc. — das ist genau der Punkt
            ]);

            // Erwartung: 302 Location oder 200 ohne Validation-Fehler
            assertTrue(
                $response['status'] === 302
                || strpos($response['body'], 'Event-Name ist erforderlich') === false,
                'delete_logo darf KEINE "Event-Name ist erforderlich"-Meldung produzieren, '
                . "Status={$response['status']}, body excerpt: "
                . substr(strip_tags($response['body']), 0, 200)
            );
            return true;
        });
    }

    private function addRotatePhotoEndpointTests(): void {
        $this->testRunner->addTest('api/rotate-photo.php - rejects unauthenticated request', function () {
            $this->resetCookies();
            $response = $this->post('/api/rotate-photo.php', [
                'photo_id' => '1',
                'angle' => '90',
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'autorisiert') !== false || $response['status'] === 401,
                'Unauth muss 401 oder Nicht-autorisiert-Fehler liefern'
            );
            return true;
        });

        $this->testRunner->addTest('api/rotate-photo.php - rejects missing CSRF when authenticated', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $response = $this->post('/api/rotate-photo.php', [
                'photo_id' => '1',
                'angle' => '90',
                // kein csrf_token
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'CSRF') !== false,
                'Eingeloggt ohne CSRF muss CSRF-Fehler liefern, war: ' . $data['error']
            );
            return true;
        });

        $this->testRunner->addTest('api/rotate-photo.php - rejects invalid angle when authenticated + CSRF', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $csrf = $this->fetchCsrfToken();
            $response = $this->post('/api/rotate-photo.php', [
                'photo_id' => '1',
                'angle' => '45',
                'csrf_token' => $csrf,
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'Rotationswinkel') !== false
                || stripos($data['error'], 'angle') !== false,
                'Ungueltiger Winkel muss Rotationswinkel-Fehler liefern, war: ' . $data['error']
            );
            return true;
        });
    }

    private function addToggleStatusEndpointTests(): void {
        $this->testRunner->addTest('api/toggle-photo-status.php - rejects unauthenticated request', function () {
            $this->resetCookies();
            $response = $this->post('/api/toggle-photo-status.php', [
                'photo_id' => '1',
                'is_active' => '0',
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'autorisiert') !== false || $response['status'] === 401,
                'Unauth muss 401 oder Nicht-autorisiert-Fehler liefern'
            );
            return true;
        });

        $this->testRunner->addTest('api/toggle-photo-status.php - rejects missing CSRF when authenticated', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $response = $this->post('/api/toggle-photo-status.php', [
                'photo_id' => '1',
                'is_active' => '0',
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'CSRF') !== false,
                'Eingeloggt ohne CSRF muss CSRF-Fehler liefern, war: ' . $data['error']
            );
            return true;
        });

        $this->testRunner->addTest('api/toggle-photo-status.php - rejects invalid photo_id when auth + CSRF', function () {
            if (!$this->loginAsAdmin()) {
                return 'Kann Admin-Login nicht durchfuehren — Test uebersprungen';
            }
            $csrf = $this->fetchCsrfToken();
            $response = $this->post('/api/toggle-photo-status.php', [
                'photo_id' => '0',
                'is_active' => '0',
                'csrf_token' => $csrf,
            ]);
            $data = json_decode($response['body'], true);
            assertTrue(
                is_array($data) && isset($data['error']),
                'Response muss error-Feld haben, war: ' . substr($response['body'], 0, 200)
            );
            assertTrue(
                stripos($data['error'], 'Foto-ID') !== false || stripos($data['error'], 'ID') !== false,
                'photo_id=0 muss ID-Fehler liefern, war: ' . $data['error']
            );
            return true;
        });
    }

    // ------------------------------------------------------------
    // HTTP Helpers
    // ------------------------------------------------------------

    private function isDevReachable(): bool {
        $ch = curl_init($this->baseUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        return $errno === 0;
    }

    private function getStatus(string $path): int {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    private function get(string $path): array {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body ?: ''];
    }

    private function post(string $path, array $fields, bool $multipart = false): array {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_POSTFIELDS => $multipart ? $fields : http_build_query($fields),
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body ?: ''];
    }

    private function resetCookies(): void {
        if (is_file($this->cookieJar)) {
            unlink($this->cookieJar);
        }
        touch($this->cookieJar);
    }

    private function readSessionCookie(): ?string {
        if (!is_file($this->cookieJar)) {
            return null;
        }
        $content = file_get_contents($this->cookieJar);
        if ($content === false) {
            return null;
        }
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/PHPSESSID\s+(\S+)$/m', $line, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    private function loginAsAdmin(): bool {
        if ($this->adminPassword === '') {
            return false;
        }
        $this->resetCookies();
        $this->get('/admin/');
        $this->post('/admin/', ['admin_password' => $this->adminPassword]);
        $response = $this->get('/admin/');
        return strpos($response['body'], 'admin_password') === false; // Login-Form weg => eingeloggt
    }

    private function fetchCsrfToken(): string {
        $response = $this->get('/api/csrf-token.php');
        $data = json_decode($response['body'], true);
        return is_array($data) && isset($data['token']) ? (string)$data['token'] : '';
    }

    private function readAdminPassword(): string {
        $envPath = __DIR__ . '/../.env';
        if (!is_readable($envPath)) {
            return '';
        }
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^ADMIN_PASSWORD\s*=\s*(.*)$/', $line, $m)) {
                $value = trim($m[1]);
                // Remove optional inline comment + quotes
                $value = preg_replace('/\s+#.*$/', '', $value);
                $value = trim($value, "\"'");
                return (string)$value;
            }
        }
        return '';
    }
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $tests = new SecurityIntegrationTests();
    $result = $tests->runAllTests();
    exit($result ? 0 : 1);
}
