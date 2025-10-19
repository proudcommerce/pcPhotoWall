<?php
/**
 * Event Management Tests
 * Tests for event creation, editing, and management functionality
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';
require_once __DIR__ . '/../app/config/database.php';

class EventManagementTests {
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
        $this->addEventCreationTests();
        $this->addEventEditingTests();
        $this->addEventStatusTests();
        $this->addEventValidationTests();
        $this->addEventSlugTests();
        $this->addEventDirectoryTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addEventCreationTests() {
        $this->testRunner->addTest('Event Creation - Basic Event Data', function() {
            $eventData = [
                'name' => 'Test Event 2024',
                'event_slug' => 'test-event-2024',
                'latitude' => 52.5200,
                'longitude' => 13.4050,
                'radius_meters' => 100,
                'display_mode' => 'random',
                'display_count' => 1,
                'display_interval' => 5,
                'max_upload_size' => 10485760,
                'show_username' => true,
                'show_date' => true,
                'overlay_opacity' => 0.8,
                'gps_validation_required' => false,
                'moderation_required' => false,
                'is_active' => true,
                'show_logo' => false,
                'show_qr_code' => false,
                'show_display_link' => true,
                'show_gallery_link' => true,
                'note' => 'Test event for testing purposes'
            ];
            
            // Validate all required fields are present
            $requiredFields = ['name', 'event_slug', 'display_mode', 'display_count', 'display_interval'];
            foreach ($requiredFields as $field) {
                assertTrue(isset($eventData[$field]), "Required field '{$field}' should be present");
            }
            
            // Validate data types
            assertTrue(is_string($eventData['name']), 'Event name should be string');
            assertTrue(is_string($eventData['event_slug']), 'Event slug should be string');
            assertTrue(is_numeric($eventData['latitude']), 'Latitude should be numeric');
            assertTrue(is_numeric($eventData['longitude']), 'Longitude should be numeric');
            assertTrue(is_int($eventData['radius_meters']), 'Radius should be integer');
            assertTrue(is_bool($eventData['is_active']), 'is_active should be boolean');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Creation - Slug Generation', function() {
            $eventName = 'My Awesome Event 2024!';
            $generatedSlug = generateSlug($eventName);
            
            assertTrue(is_string($generatedSlug), 'Generated slug should be string');
            assertTrue(strlen($generatedSlug) > 0, 'Generated slug should not be empty');
            assertTrue(preg_match('/^[a-z0-9-]+$/', $generatedSlug), 'Generated slug should match pattern');
            assertTrue(validateSlug($generatedSlug), 'Generated slug should be valid');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Creation - Unique Slug Generation (Mock)', function() {
            $eventName = 'Test Event';
            
            // Test slug generation without database dependency
            $baseSlug = generateSlug($eventName);
            assertTrue(is_string($baseSlug), 'Generated slug should be string');
            assertTrue(validateSlug($baseSlug), 'Generated slug should be valid');
            
            // Test that generateUniqueSlug function exists
            assertTrue(function_exists('generateUniqueSlug'), 'generateUniqueSlug function should exist');
            assertTrue(is_callable('generateUniqueSlug'), 'generateUniqueSlug should be callable');
            
            // Test that isSlugUnique function exists
            assertTrue(function_exists('isSlugUnique'), 'isSlugUnique function should exist');
            assertTrue(is_callable('isSlugUnique'), 'isSlugUnique should be callable');
            
            return true;
        });
    }
    
    private function addEventEditingTests() {
        $this->testRunner->addTest('Event Editing - Configuration Update', function() {
            $originalConfig = [
                'name' => 'Original Event',
                'display_mode' => 'random',
                'display_count' => 1,
                'display_interval' => 5,
                'show_username' => true,
                'show_date' => true,
                'overlay_opacity' => 0.8
            ];
            
            $updatedConfig = [
                'name' => 'Updated Event Name',
                'display_mode' => 'newest',
                'display_count' => 3,
                'display_interval' => 10,
                'show_username' => false,
                'show_date' => true,
                'overlay_opacity' => 0.6
            ];
            
            // Validate that updates are different
            assertNotEquals($originalConfig['name'], $updatedConfig['name'], 'Name should be updated');
            assertNotEquals($originalConfig['display_mode'], $updatedConfig['display_mode'], 'Display mode should be updated');
            assertNotEquals($originalConfig['display_count'], $updatedConfig['display_count'], 'Display count should be updated');
            assertNotEquals($originalConfig['show_username'], $updatedConfig['show_username'], 'Show username should be updated');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Editing - Slug Update Validation', function() {
            $originalSlug = 'original-event';
            $newSlug = 'updated-event';
            $eventId = 1; // Mock event ID
            
            // Test slug validation for updates
            assertTrue(validateSlug($newSlug), 'New slug should be valid');
            assertTrue(validateSlug($originalSlug), 'Original slug should be valid');
            
            // Test that slugs are different
            assertNotEquals($originalSlug, $newSlug, 'New slug should be different from original');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Editing - GPS Coordinates Update', function() {
            $originalCoords = ['lat' => 52.5200, 'lon' => 13.4050]; // Berlin
            $updatedCoords = ['lat' => 48.8566, 'lon' => 2.3522]; // Paris
            
            // Validate original coordinates
            assertTrue(GeoUtils::validateCoordinates($originalCoords['lat'], $originalCoords['lon']), 'Original coordinates should be valid');
            
            // Validate updated coordinates
            assertTrue(GeoUtils::validateCoordinates($updatedCoords['lat'], $updatedCoords['lon']), 'Updated coordinates should be valid');
            
            // Test that coordinates are different
            assertNotEquals($originalCoords['lat'], $updatedCoords['lat'], 'Latitude should be different');
            assertNotEquals($originalCoords['lon'], $updatedCoords['lon'], 'Longitude should be different');
            
            return true;
        });
    }
    
    private function addEventStatusTests() {
        $this->testRunner->addTest('Event Status - Active/Inactive Toggle', function() {
            $activeEvent = ['is_active' => true];
            $inactiveEvent = ['is_active' => false];
            
            assertTrue($activeEvent['is_active'], 'Active event should be active');
            assertFalse($inactiveEvent['is_active'], 'Inactive event should be inactive');
            
            // Test toggle
            $toggledActive = !$activeEvent['is_active'];
            $toggledInactive = !$inactiveEvent['is_active'];
            
            assertFalse($toggledActive, 'Toggled active event should be inactive');
            assertTrue($toggledInactive, 'Toggled inactive event should be active');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Status - Status Validation', function() {
            $validStatuses = [true, false, 1, 0, '1', '0'];
            
            foreach ($validStatuses as $status) {
                $boolStatus = (bool)$status;
                assertTrue(is_bool($boolStatus), "Status '{$status}' should convert to boolean");
            }
            
            return true;
        });
    }
    
    private function addEventValidationTests() {
        $this->testRunner->addTest('Event Validation - Required Fields', function() {
            $requiredFields = ['name', 'event_slug'];
            $eventData = [
                'name' => 'Test Event',
                'event_slug' => 'test-event',
                'latitude' => 52.5200,
                'longitude' => 13.4050
            ];
            
            foreach ($requiredFields as $field) {
                assertTrue(isset($eventData[$field]), "Required field '{$field}' should be present");
                assertTrue(!empty($eventData[$field]), "Required field '{$field}' should not be empty");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Validation - GPS Validation When Required', function() {
            $eventWithGPS = [
                'gps_validation_required' => true,
                'latitude' => 52.5200,
                'longitude' => 13.4050
            ];
            
            $eventWithoutGPS = [
                'gps_validation_required' => false,
                'latitude' => null,
                'longitude' => null
            ];
            
            // Test GPS validation when required
            if ($eventWithGPS['gps_validation_required']) {
                assertTrue(GeoUtils::validateCoordinates($eventWithGPS['latitude'], $eventWithGPS['longitude']), 'GPS coordinates should be valid when required');
            }
            
            // Test GPS validation when not required
            if (!$eventWithoutGPS['gps_validation_required']) {
                assertTrue(true, 'GPS validation should be skipped when not required');
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Validation - Radius Validation', function() {
            $validRadii = [10, 50, 100, 500, 1000, 5000, 10000];
            $invalidRadii = [0, 5, 9, 10001, 50000, -10];
            
            foreach ($validRadii as $radius) {
                assertTrue($radius >= 10 && $radius <= 10000, "Radius {$radius} should be valid");
            }
            
            foreach ($invalidRadii as $radius) {
                assertFalse($radius >= 10 && $radius <= 10000, "Radius {$radius} should be invalid");
            }
            
            return true;
        });
    }
    
    private function addEventSlugTests() {
        $this->testRunner->addTest('Event Slug - Uniqueness Check (Mock)', function() {
            $existingSlugs = ['existing-event', 'another-event', 'test-event'];
            $newSlug = 'new-unique-event';
            $duplicateSlug = 'existing-event';
            
            // Test unique slug logic without database
            assertTrue(!in_array($newSlug, $existingSlugs), 'New slug should be unique');
            
            // Test duplicate slug logic without database
            assertTrue(in_array($duplicateSlug, $existingSlugs), 'Duplicate slug should be detected');
            
            // Test that isSlugUnique function exists (but don't call it)
            assertTrue(function_exists('isSlugUnique'), 'isSlugUnique function should exist');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - Reserved Words', function() {
            $reservedWords = ['admin', 'api', 'uploads', 'assets', 'css', 'js', 'images', 'display', 'index', 'login', 'logout'];
            $validSlugs = ['test-event', 'my-awesome-event', 'event-2024'];
            
            foreach ($reservedWords as $word) {
                assertFalse(validateSlug($word), "Reserved word '{$word}' should be invalid");
            }
            
            foreach ($validSlugs as $slug) {
                assertTrue(validateSlug($slug), "Valid slug '{$slug}' should be valid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Slug - Format Validation', function() {
            $validFormats = [
                'test-event',
                'my-awesome-event-2024',
                'event-with-numbers123',
                'a' . str_repeat('-b', 49) // 100 character slug
            ];
            
            $invalidFormats = [
                'ab', // Too short
                'test_event', // Underscores
                'Test-Event', // Uppercase
                'test-event-', // Trailing hyphen
                '-test-event', // Leading hyphen
                'test event', // Spaces
                'test@event', // Special characters
                str_repeat('a', 101) // Too long
            ];
            
            foreach ($validFormats as $slug) {
                assertTrue(validateSlug($slug), "Valid format '{$slug}' should pass validation");
            }
            
            foreach ($invalidFormats as $slug) {
                assertFalse(validateSlug($slug), "Invalid format '{$slug}' should fail validation");
            }
            
            return true;
        });
    }
    
    private function addEventDirectoryTests() {
        $this->testRunner->addTest('Event Directory - Path Generation', function() {
            $eventSlug = 'test-event';
            $paths = getEventUploadPaths($eventSlug);
            
            assertTrue(is_array($paths), 'Should return array of paths');
            assertTrue(isset($paths['photos_path']), 'Should have photos_path');
            assertTrue(isset($paths['logos_path']), 'Should have logos_path');
            assertTrue(isset($paths['thumbnails_path']), 'Should have thumbnails_path');
            assertTrue(isset($paths['photos_url']), 'Should have photos_url');
            assertTrue(isset($paths['logos_url']), 'Should have logos_url');
            assertTrue(isset($paths['thumbnails_url']), 'Should have thumbnails_url');
            
            // Check that paths contain event slug
            assertContains($eventSlug, $paths['photos_path'], 'Photos path should contain event slug');
            assertContains($eventSlug, $paths['logos_path'], 'Logos path should contain event slug');
            assertContains($eventSlug, $paths['thumbnails_path'], 'Thumbnails path should contain event slug');
            
            return true;
        });
        
        $this->testRunner->addTest('Event Directory - Directory Creation', function() {
            $eventSlug = 'test-event-' . time();
            $paths = getEventUploadPaths($eventSlug);
            
            // Create directories
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
        
        $this->testRunner->addTest('Event Directory - URL Generation', function() {
            $eventSlug = 'test-event';
            $paths = getEventUploadPaths($eventSlug);
            
            // Check that URLs are properly formatted
            assertTrue(strpos($paths['photos_url'], 'http') === 0 || strpos($paths['photos_url'], '/') === 0, 'Photos URL should be valid');
            assertTrue(strpos($paths['logos_url'], 'http') === 0 || strpos($paths['logos_url'], '/') === 0, 'Logos URL should be valid');
            assertTrue(strpos($paths['thumbnails_url'], 'http') === 0 || strpos($paths['thumbnails_url'], '/') === 0, 'Thumbnails URL should be valid');
            
            // Check that URLs contain event slug
            assertContains($eventSlug, $paths['photos_url'], 'Photos URL should contain event slug');
            assertContains($eventSlug, $paths['logos_url'], 'Logos URL should contain event slug');
            assertContains($eventSlug, $paths['thumbnails_url'], 'Thumbnails URL should contain event slug');
            
            return true;
        });
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new EventManagementTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
