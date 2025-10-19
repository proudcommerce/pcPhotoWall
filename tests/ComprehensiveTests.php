<?php
/**
 * Comprehensive Tests for All PC PhotoWall Functions
 * Tests every function in the codebase
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';
require_once __DIR__ . '/../app/config/database.php';

class ComprehensiveTests {
    private $testRunner;
    private $testDataPath;
    
    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->testDataPath = __DIR__ . '/data';
        $this->setupTestData();
    }
    
    private function setupTestData() {
        if (!is_dir($this->testDataPath)) {
            mkdir($this->testDataPath, 0755, true);
        }
    }
    
    public function runAllTests() {
        $this->addVersionFunctionTests();
        $this->addCSRFTokenTests();
        $this->addEventHashTests();
        $this->addEventSlugTests();
        $this->addFileUploadTests();
        $this->addImageProcessingTests();
        $this->addResponseFunctionTests();
        $this->addUtilityFunctionTests();
        $this->addSessionFunctionTests();
        $this->addDisplayConfigurationTests();
        $this->addGeoUtilsTests();
        $this->addDatabaseTests();

        return $this->testRunner->runAll();
    }
    
    private function addVersionFunctionTests() {
        $this->testRunner->addTest('Version Functions - getCurrentVersion', function() {
            $version = getCurrentVersion();
            
            assertTrue(is_string($version), 'Version should be a string');
            assertTrue(preg_match('/^\d+\.\d+\.\d+$/', $version), 'Version should match semantic versioning');
            assertTrue(strlen($version) >= 5, 'Version should be at least 5 characters (e.g., "1.0.0")');
            
            return true;
        });
    }
    
    private function addCSRFTokenTests() {
        $this->testRunner->addTest('CSRF Token - generateCSRFToken', function() {
            $token1 = generateCSRFToken();
            $token2 = generateCSRFToken();
            
            assertTrue(strlen($token1) === 64, 'CSRF token should be 64 characters');
            assertTrue($token1 === $token2, 'CSRF token should be consistent in session');
            assertTrue(ctype_xdigit($token1), 'CSRF token should be hexadecimal');
            
            return true;
        });
        
        $this->testRunner->addTest('CSRF Token - validateCSRFToken', function() {
            $token = generateCSRFToken();
            
            assertTrue(validateCSRFToken($token), 'Valid CSRF token should pass validation');
            assertFalse(validateCSRFToken('invalid'), 'Invalid CSRF token should fail validation');
            assertFalse(validateCSRFToken(''), 'Empty CSRF token should fail validation');
            
            return true;
        });
    }
    
    private function addEventHashTests() {
        $this->testRunner->addTest('Event Hash - generateEventHash', function() {
            $hash1 = generateEventHash();
            $hash2 = generateEventHash();
            
            assertTrue(strlen($hash1) === 32, 'Event hash should be 32 characters');
            assertTrue(ctype_xdigit($hash1), 'Event hash should be hexadecimal');
            assertNotEquals($hash1, $hash2, 'Event hashes should be unique');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Hash - getEventByHash (Mock)', function() {
            // Test with non-existent hash (should return false)
            $result = getEventByHash('nonexistenthash123456789012345678901234');
            assertFalse($result, 'Non-existent hash should return false');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Hash - getEventHashById (Mock)', function() {
            // Test with non-existent ID (should return false)
            $result = getEventHashById(99999);
            assertFalse($result, 'Non-existent ID should return false');
            
            return true;
        });
    }
    
    private function addEventSlugTests() {
        $this->testRunner->addTest('Event Slug - generateSlug', function() {
            $slug1 = generateSlug('Test Event 2024');
            $slug2 = generateSlug('Test-Event-2024');
            $slug3 = generateSlug('Test@Event#2024');
            $slug4 = generateSlug('Test Event with Special Characters!@#$%');
            
            assertEquals('test-event-2024', $slug1, 'Slug should be lowercase with hyphens');
            assertEquals('test-event-2024', $slug2, 'Existing hyphens should be preserved');
            assertEquals('test-event-2024', $slug3, 'Special characters should be replaced');
            assertTrue(strlen($slug4) > 0, 'Complex slug should be generated');
            assertTrue(preg_match('/^[a-z0-9-]+$/', $slug4), 'Slug should contain only lowercase letters, numbers, and hyphens');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - validateSlug', function() {
            assertTrue(validateSlug('test-event'), 'Valid slug should pass');
            assertTrue(validateSlug('test-event-2024'), 'Valid slug with numbers should pass');
            assertTrue(validateSlug('my-awesome-event'), 'Long valid slug should pass');
            
            assertFalse(validateSlug('admin'), 'Reserved word should fail');
            assertFalse(validateSlug('api'), 'Reserved word should fail');
            assertFalse(validateSlug('ab'), 'Too short slug should fail');
            assertFalse(validateSlug('test_event'), 'Underscores should fail');
            assertFalse(validateSlug('Test-Event'), 'Uppercase should fail');
            assertFalse(validateSlug('test-event-'), 'Trailing hyphen should fail');
            assertFalse(validateSlug('-test-event'), 'Leading hyphen should fail');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - isSlugUnique (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('isSlugUnique'), 'isSlugUnique function should exist');
            assertTrue(is_callable('isSlugUnique'), 'isSlugUnique should be callable');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - generateUniqueSlug (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('generateUniqueSlug'), 'generateUniqueSlug function should exist');
            assertTrue(is_callable('generateUniqueSlug'), 'generateUniqueSlug should be callable');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - getEventBySlug (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('getEventBySlug'), 'getEventBySlug function should exist');
            assertTrue(is_callable('getEventBySlug'), 'getEventBySlug should be callable');
            
            return true;
        });
    }
    
    private function addFileUploadTests() {
        $this->testRunner->addTest('File Upload - validateFileUpload Valid JPEG', function() {
            $validFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => $this->createTestImage('jpeg'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid JPEG should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('File Upload - validateFileUpload Valid HEIC', function() {
            $validFile = [
                'name' => 'test.heic',
                'type' => 'image/heic',
                'size' => 1024,
                'tmp_name' => $this->createTestFile('heic'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid HEIC should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('File Upload - validateFileUpload Invalid Type', function() {
            $invalidFile = [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'size' => 1024,
                'tmp_name' => $this->createTestFile('txt'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($invalidFile, 2048);
            assertFalse(empty($errors), 'Invalid file type should fail validation');
            assertContains('Dateityp nicht erlaubt', $errors[0], 'Error message should mention file type');
            
            unlink($invalidFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('File Upload - validateFileUpload File Too Large', function() {
            $largeFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 2049,
                'tmp_name' => $this->createTestImage('jpeg'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($largeFile, 2048);
            assertFalse(empty($errors), 'File too large should fail validation');
            assertContains('Datei zu groß', $errors[0], 'Error message should mention file size');
            
            unlink($largeFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('File Upload - validateFileUpload Upload Error', function() {
            $errorFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE
            ];
            
            $errors = validateFileUpload($errorFile, 2048);
            assertFalse(empty($errors), 'Upload error should fail validation');
            assertContains('Keine Datei hochgeladen', $errors[0], 'Error message should mention no file');
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - generateUniqueFilename', function() {
            $filename1 = generateUniqueFilename('test.jpg');
            $filename2 = generateUniqueFilename('test.jpg');
            
            assertNotEquals($filename1, $filename2, 'Generated filenames should be unique');
            assertContains('.jpg', $filename1, 'Extension should be preserved');
            assertContains('.jpg', $filename2, 'Extension should be preserved');
            assertTrue(strlen($filename1) > 10, 'Filename should be reasonably long');
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - calculateFileHash', function() {
            $testFile = $this->testDataPath . '/test.txt';
            file_put_contents($testFile, 'test content');
            
            $hash1 = calculateFileHash($testFile);
            $hash2 = calculateFileHash($testFile);
            
            assertTrue($hash1 !== false, 'File hash should be calculated');
            assertEquals($hash1, $hash2, 'Same file should produce same hash');
            assertTrue(strlen($hash1) === 64, 'Hash should be 64 characters');
            assertTrue(ctype_xdigit($hash1), 'Hash should be hexadecimal');
            
            unlink($testFile);
            return true;
        });
        
        $this->testRunner->addTest('File Upload - calculateFileHash Non-existent', function() {
            $hash = calculateFileHash('/non/existent/file.txt');
            assertFalse($hash, 'Non-existent file should return false');
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - isDuplicatePhoto (Mock)', function() {
            // Test with non-existent hash (should return false)
            $result = isDuplicatePhoto('nonexistenthash123456789012345678901234567890123456789012345678901234', 1);
            assertFalse($result, 'Non-existent hash should return false');
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - getEventUploadPaths', function() {
            $eventSlug = 'test-event';
            $paths = getEventUploadPaths($eventSlug);
            
            assertTrue(is_array($paths), 'Should return array');
            assertTrue(isset($paths['photos_path']), 'Should have photos_path');
            assertTrue(isset($paths['logos_path']), 'Should have logos_path');
            assertTrue(isset($paths['thumbnails_path']), 'Should have thumbnails_path');
            assertTrue(isset($paths['photos_url']), 'Should have photos_url');
            assertTrue(isset($paths['logos_url']), 'Should have logos_url');
            assertTrue(isset($paths['thumbnails_url']), 'Should have thumbnails_url');
            
            assertContains($eventSlug, $paths['photos_path'], 'Photos path should contain event slug');
            assertContains($eventSlug, $paths['logos_path'], 'Logos path should contain event slug');
            assertContains($eventSlug, $paths['thumbnails_path'], 'Thumbnails path should contain event slug');
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - ensureEventDirectories', function() {
            $eventSlug = 'test-event-' . time();
            $paths = getEventUploadPaths($eventSlug);
            
            ensureEventDirectories($eventSlug);
            
            assertTrue(is_dir($paths['photos_path']), 'Photos directory should be created');
            assertTrue(is_dir($paths['logos_path']), 'Logos directory should be created');
            assertTrue(is_dir($paths['thumbnails_path']), 'Thumbnails directory should be created');
            
            // Cleanup
            rmdir($paths['photos_path']);
            rmdir($paths['logos_path']);
            rmdir($paths['thumbnails_path']);
            rmdir(dirname($paths['photos_path']));
            
            return true;
        });
        
        $this->testRunner->addTest('File Upload - formatBytes', function() {
            assertEquals('1 B', formatBytes(1), '1 byte should format correctly');
            assertEquals('1 KB', formatBytes(1024, 0), '1024 bytes should be 1 KB');
            assertEquals('1 MB', formatBytes(1024 * 1024, 0), '1 MB should format correctly');
            assertEquals('1 GB', formatBytes(1024 * 1024 * 1024, 0), '1 GB should format correctly');
            assertEquals('1.5 MB', formatBytes(1024 * 1024 * 1.5), '1.5 MB should format correctly');
            assertEquals('1.23 MB', formatBytes(1024 * 1024 * 1.23, 2), '1.23 MB should format with precision');
            
            return true;
        });
    }
    
    private function addImageProcessingTests() {
        $this->testRunner->addTest('Image Processing - resizeImage JPEG', function() {
            $testImage = $this->createTestImage('jpeg');
            $resizedPath = $this->testDataPath . '/resized.jpg';
            
            $result = resizeImage($testImage, $resizedPath, 200, 200, 85);
            
            assertTrue($result, 'JPEG resize should succeed');
            assertFileExists($resizedPath, 'Resized file should exist');
            
            $imageInfo = getimagesize($resizedPath);
            assertTrue($imageInfo !== false, 'Resized image should be valid');
            assertTrue($imageInfo[0] <= 200, 'Resized width should be <= 200px');
            assertTrue($imageInfo[1] <= 200, 'Resized height should be <= 200px');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - resizeImage PNG', function() {
            $testImage = $this->createTestImage('png');
            $resizedPath = $this->testDataPath . '/resized.png';
            
            $result = resizeImage($testImage, $resizedPath, 200, 200, 85);
            
            assertTrue($result, 'PNG resize should succeed');
            assertFileExists($resizedPath, 'Resized PNG file should exist');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - createThumbnail JPEG', function() {
            $testImage = $this->createTestImage('jpeg');
            $thumbnailPath = $this->testDataPath . '/thumbnail.jpg';
            
            $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
            
            assertTrue($result, 'JPEG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'Thumbnail file should exist');
            
            $imageInfo = getimagesize($thumbnailPath);
            assertTrue($imageInfo !== false, 'Thumbnail should be valid image');
            assertTrue($imageInfo[0] <= 100, 'Thumbnail width should be <= 100px');
            assertTrue($imageInfo[1] <= 100, 'Thumbnail height should be <= 100px');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - createThumbnail PNG with Transparency', function() {
            $testImage = $this->createTestImageWithTransparency('png');
            $thumbnailPath = $this->testDataPath . '/thumbnail.png';
            
            $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
            
            assertTrue($result, 'PNG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'PNG thumbnail file should exist');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - autoRotateImage', function() {
            $testImage = $this->createTestImage('jpeg');
            
            // Test with image that doesn't need rotation
            $result = autoRotateImage($testImage);
            
            // Should return false for images that don't need rotation
            assertTrue($result === false || $result === true, 'Auto rotate should return boolean');
            
            unlink($testImage);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Invalid Image File', function() {
            $invalidFile = $this->testDataPath . '/invalid.txt';
            file_put_contents($invalidFile, 'not an image');
            
            $thumbnailPath = $this->testDataPath . '/invalid_thumb.jpg';
            $result = createThumbnail($invalidFile, $thumbnailPath, 100, 100, 85);
            
            assertFalse($result, 'Invalid image should fail thumbnail creation');
            assertFileNotExists($thumbnailPath, 'Thumbnail should not be created for invalid image');
            
            unlink($invalidFile);
            return true;
        });
    }
    
    private function addResponseFunctionTests() {
        $this->testRunner->addTest('Response Functions - sendJSONResponse (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('sendJSONResponse'), 'sendJSONResponse function should exist');
            assertTrue(is_callable('sendJSONResponse'), 'sendJSONResponse should be callable');
            
            return true;
        });
        
        $this->testRunner->addTest('Response Functions - sendErrorResponse (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('sendErrorResponse'), 'sendErrorResponse function should exist');
            assertTrue(is_callable('sendErrorResponse'), 'sendErrorResponse should be callable');
            
            return true;
        });
        
        $this->testRunner->addTest('Response Functions - sendSuccessResponse (Mock)', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('sendSuccessResponse'), 'sendSuccessResponse function should exist');
            assertTrue(is_callable('sendSuccessResponse'), 'sendSuccessResponse should be callable');
            
            return true;
        });
    }
    
    private function addUtilityFunctionTests() {
        $this->testRunner->addTest('Utility Functions - sanitizeInput', function() {
            $maliciousInput = '<script>alert("xss")</script>';
            $sanitized = sanitizeInput($maliciousInput);
            
            assertNotEquals($maliciousInput, $sanitized, 'Input should be sanitized');
            assertFalse(strpos($sanitized, '<script>') !== false, 'Script tags should be removed');
            assertTrue(strpos($sanitized, '&lt;script&gt;') !== false, 'Script tags should be HTML-encoded');
            
            // Test with normal input
            $normalInput = 'Hello World';
            $sanitizedNormal = sanitizeInput($normalInput);
            assertEquals($normalInput, $sanitizedNormal, 'Normal input should remain unchanged');
            
            // Test with whitespace
            $whitespaceInput = '  Hello World  ';
            $sanitizedWhitespace = sanitizeInput($whitespaceInput);
            assertEquals('Hello World', $sanitizedWhitespace, 'Whitespace should be trimmed');
            
            return true;
        });
    }
    
    private function addSessionFunctionTests() {
        $this->testRunner->addTest('Session Functions - setUserSession', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('setUserSession'), 'setUserSession function should exist');
            assertTrue(is_callable('setUserSession'), 'setUserSession should be callable');

            return true;
        });

        $this->testRunner->addTest('Session Functions - getUserSession', function() {
            // Test that function exists and is callable
            assertTrue(function_exists('getUserSession'), 'getUserSession function should exist');
            assertTrue(is_callable('getUserSession'), 'getUserSession should be callable');

            return true;
        });
    }

    private function addDisplayConfigurationTests() {
        $this->testRunner->addTest('Display Configuration - getDisplayConfiguration exists', function() {
            assertTrue(function_exists('getDisplayConfiguration'), 'getDisplayConfiguration function should exist');
            assertTrue(is_callable('getDisplayConfiguration'), 'getDisplayConfiguration should be callable');

            return true;
        });

        $this->testRunner->addTest('Display Configuration - Default values', function() {
            $event = [
                'display_mode' => 'newest',
                'display_count' => 5,
                'display_interval' => 10,
                'layout_type' => 'grid',
                'grid_columns' => 3,
                'show_logo' => true,
                'show_qr_code' => false
            ];

            $config = getDisplayConfiguration($event, []);

            assertTrue(is_array($config), 'Should return an array');
            assertEquals('newest', $config['displayMode'], 'Display mode should match event');
            assertEquals(5, $config['displayCount'], 'Display count should match event');
            assertEquals(10, $config['displayInterval'], 'Display interval should match event');
            assertEquals('grid', $config['layoutType'], 'Layout type should match event');
            assertEquals(3, $config['gridColumns'], 'Grid columns should match event');
            assertEquals(true, $config['showLogo'], 'Show logo should match event');
            assertEquals(false, $config['showQrCode'], 'Show QR code should match event');

            return true;
        });

        $this->testRunner->addTest('Display Configuration - GET parameter override', function() {
            $event = [
                'display_mode' => 'newest',
                'display_count' => 5,
                'display_interval' => 10
            ];

            $getParams = [
                'display_mode' => 'random',
                'display_count' => '15',
                'display_interval' => '20'
            ];

            $config = getDisplayConfiguration($event, $getParams);

            assertEquals('random', $config['displayMode'], 'GET param should override display mode');
            assertEquals(15, $config['displayCount'], 'GET param should override and convert display count');
            assertEquals(20, $config['displayInterval'], 'GET param should override and convert display interval');
            assertTrue(is_int($config['displayCount']), 'Display count should be integer');
            assertTrue(is_int($config['displayInterval']), 'Display interval should be integer');

            return true;
        });

        $this->testRunner->addTest('Display Configuration - Validation and clamping', function() {
            $event = [
                'display_mode' => 'invalid_mode',
                'display_count' => 999,
                'display_interval' => 1,
                'grid_columns' => 99,
                'layout_type' => 'invalid_layout'
            ];

            $config = getDisplayConfiguration($event, []);

            assertEquals('random', $config['displayMode'], 'Invalid mode should default to random');
            assertEquals(50, $config['displayCount'], 'Count should be clamped to max 50');
            assertEquals(3, $config['displayInterval'], 'Interval should be clamped to min 3');
            assertEquals(6, $config['gridColumns'], 'Grid columns should be clamped to max 6');
            assertEquals('grid', $config['layoutType'], 'Invalid layout should default to grid');

            return true;
        });

        $this->testRunner->addTest('Display Configuration - Boolean parameters', function() {
            $event = ['show_logo' => false, 'show_qr_code' => false];

            $getParams1 = ['show_logo' => '1', 'show_qr_code' => '0'];
            $config1 = getDisplayConfiguration($event, $getParams1);

            assertEquals(true, $config1['showLogo'], 'String "1" should convert to true');
            assertEquals(false, $config1['showQrCode'], 'String "0" should convert to false');

            $getParams2 = ['show_logo' => 1, 'show_qr_code' => 0];
            $config2 = getDisplayConfiguration($event, $getParams2);

            assertEquals(true, $config2['showLogo'], 'Integer 1 should convert to true');
            assertEquals(false, $config2['showQrCode'], 'Integer 0 should convert to false');

            return true;
        });
    }
    
    private function addGeoUtilsTests() {
        $this->testRunner->addTest('GeoUtils - calculateDistance', function() {
            // Test distance calculation between two known points
            $lat1 = 52.5200; // Berlin
            $lon1 = 13.4050;
            $lat2 = 48.8566; // Paris
            $lon2 = 2.3522;
            
            $distance = GeoUtils::calculateDistance($lat1, $lon1, $lat2, $lon2);
            
            assertTrue($distance > 0, 'Distance should be positive');
            assertTrue($distance > 800000, 'Distance between Berlin and Paris should be > 800km');
            assertTrue($distance < 900000, 'Distance between Berlin and Paris should be < 900km');
            
            // Test same point distance
            $samePointDistance = GeoUtils::calculateDistance($lat1, $lon1, $lat1, $lon1);
            assertTrue($samePointDistance < 1, 'Same point distance should be < 1m');
            
            return true;
        });
        
        $this->testRunner->addTest('GeoUtils - isWithinRadius', function() {
            $lat1 = 52.5200; // Berlin
            $lon1 = 13.4050;
            $lat2 = 52.5210; // Close to Berlin
            $lon2 = 13.4060;
            
            $isWithin = GeoUtils::isWithinRadius($lat1, $lon1, $lat2, $lon2, 1000);
            assertTrue($isWithin, 'Close points should be within 1000m radius');
            
            $lat3 = 48.8566; // Paris
            $lon3 = 2.3522;
            
            $isNotWithin = GeoUtils::isWithinRadius($lat1, $lon1, $lat3, $lon3, 1000);
            assertFalse($isNotWithin, 'Distant points should not be within 1000m radius');
            
            return true;
        });
        
        $this->testRunner->addTest('GeoUtils - validateCoordinates', function() {
            assertTrue(GeoUtils::validateCoordinates(0, 0), 'Valid coordinates (0,0) should pass');
            assertTrue(GeoUtils::validateCoordinates(90, 180), 'Valid coordinates (90,180) should pass');
            assertTrue(GeoUtils::validateCoordinates(-90, -180), 'Valid coordinates (-90,-180) should pass');
            assertTrue(GeoUtils::validateCoordinates(52.5200, 13.4050), 'Valid coordinates (Berlin) should pass');
            
            assertFalse(GeoUtils::validateCoordinates(91, 0), 'Invalid latitude > 90 should fail');
            assertFalse(GeoUtils::validateCoordinates(-91, 0), 'Invalid latitude < -90 should fail');
            assertFalse(GeoUtils::validateCoordinates(0, 181), 'Invalid longitude > 180 should fail');
            assertFalse(GeoUtils::validateCoordinates(0, -181), 'Invalid longitude < -180 should fail');
            
            return true;
        });
        
        $this->testRunner->addTest('GeoUtils - formatDistance', function() {
            assertEquals('0 m', GeoUtils::formatDistance(0), '0 meters should format as "0 m"');
            assertEquals('100 m', GeoUtils::formatDistance(100), '100 meters should format as "100 m"');
            assertEquals('999 m', GeoUtils::formatDistance(999), '999 meters should format as "999 m"');
            assertEquals('1 km', GeoUtils::formatDistance(1000), '1000 meters should format as "1 km"');
            assertEquals('1.5 km', GeoUtils::formatDistance(1500), '1500 meters should format as "1.5 km"');
            assertEquals('10.2 km', GeoUtils::formatDistance(10200), '10200 meters should format as "10.2 km"');
            
            assertEquals('0 m', GeoUtils::formatDistance(null), 'Null distance should format as "0 m"');
            assertEquals('0 m', GeoUtils::formatDistance(''), 'Empty distance should format as "0 m"');
            
            return true;
        });
        
        $this->testRunner->addTest('GeoUtils - extractGPSCoordinates (Mock)', function() {
            // Test with non-existent file
            $result = GeoUtils::extractGPSCoordinates('/non/existent/file.jpg');
            assertFalse($result, 'Non-existent file should return false');
            
            return true;
        });
    }
    
    private function addDatabaseTests() {
        $this->testRunner->addTest('Database - Class Exists', function() {
            assertTrue(class_exists('Database'), 'Database class should exist');
            
            return true;
        });
        
        $this->testRunner->addTest('Database - Constructor', function() {
            $db = new Database();
            assertTrue($db instanceof Database, 'Should create Database instance');
            
            return true;
        });
        
        $this->testRunner->addTest('Database - getConnection (Mock)', function() {
            // Test that method exists
            $db = new Database();
            assertTrue(method_exists($db, 'getConnection'), 'getConnection method should exist');
            
            return true;
        });
        
        $this->testRunner->addTest('Database - createTables (Mock)', function() {
            // Test that method exists
            $db = new Database();
            assertTrue(method_exists($db, 'createTables'), 'createTables method should exist');
            
            return true;
        });
    }
    
    private function createTestImage($format) {
        $path = $this->testDataPath . '/test.' . $format;
        
        $image = imagecreate(100, 100);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 3, 30, 40, 'TEST', $text);
        
        switch ($format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, $path, 90);
                break;
            case 'png':
                imagepng($image, $path, 9);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
        }
        
        imagedestroy($image);
        return $path;
    }
    
    private function createTestImageWithTransparency($format) {
        $path = $this->testDataPath . '/test_transparent.' . $format;
        
        $image = imagecreate(100, 100);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 3, 30, 40, 'TEST', $text);
        
        switch ($format) {
            case 'png':
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path, 9);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
        }
        
        imagedestroy($image);
        return $path;
    }
    
    private function createTestFile($extension) {
        $path = $this->testDataPath . '/test.' . $extension;
        file_put_contents($path, 'test content');
        return $path;
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new ComprehensiveTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
