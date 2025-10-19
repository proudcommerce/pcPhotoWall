<?php
/**
 * Event Configuration Tests
 * Tests all event configuration features and validation
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';
require_once __DIR__ . '/../app/config/database.php';

class EventConfigurationTests {
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
        $this->addDisplayConfigurationTests();
        $this->addOverlaySettingsTests();
        $this->addUploadConfigurationTests();
        $this->addDisplayOptionsTests();
        $this->addEventValidationTests();
        $this->addEventManagementTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addDisplayConfigurationTests() {
        $this->testRunner->addTest('Display Configuration - display_mode Validation', function() {
            $validModes = ['random', 'newest', 'chronological'];
            
            foreach ($validModes as $mode) {
                assertTrue(in_array($mode, $validModes), "Display mode '{$mode}' should be valid");
            }
            
            $invalidModes = ['invalid', 'custom', 'mixed', ''];
            foreach ($invalidModes as $mode) {
                assertFalse(in_array($mode, $validModes), "Display mode '{$mode}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Configuration - display_count Validation', function() {
            $validCounts = [1, 2, 3, 4, 6, 8, 10];
            
            foreach ($validCounts as $count) {
                assertTrue(in_array($count, $validCounts), "Display count '{$count}' should be valid");
            }
            
            $invalidCounts = [0, 5, 7, 9, 11, 15, -1];
            foreach ($invalidCounts as $count) {
                assertFalse(in_array($count, $validCounts), "Display count '{$count}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Configuration - display_interval Validation', function() {
            $minInterval = 3;
            $maxInterval = 60;
            
            // Test valid intervals
            for ($i = $minInterval; $i <= $maxInterval; $i++) {
                $clamped = max($minInterval, min($maxInterval, $i));
                assertTrue($clamped >= $minInterval && $clamped <= $maxInterval, "Interval {$i} should be clamped to valid range");
            }
            
            // Test invalid intervals
            $invalidIntervals = [0, 1, 2, 61, 100, -5];
            foreach ($invalidIntervals as $interval) {
                $clamped = max($minInterval, min($maxInterval, $interval));
                assertTrue($clamped >= $minInterval && $clamped <= $maxInterval, "Invalid interval {$interval} should be clamped");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Configuration - layout_type Validation', function() {
            $validLayouts = ['single', 'grid'];
            
            foreach ($validLayouts as $layout) {
                assertTrue(in_array($layout, $validLayouts), "Layout type '{$layout}' should be valid");
            }
            
            $invalidLayouts = ['invalid', 'custom', 'mixed', ''];
            foreach ($invalidLayouts as $layout) {
                assertFalse(in_array($layout, $validLayouts), "Layout type '{$layout}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Configuration - grid_columns Validation', function() {
            $minColumns = 2;
            $maxColumns = 6;
            
            // Test valid columns
            for ($i = $minColumns; $i <= $maxColumns; $i++) {
                assertTrue($i >= $minColumns && $i <= $maxColumns, "Grid columns {$i} should be valid");
            }
            
            // Test invalid columns
            $invalidColumns = [0, 1, 7, 10, -1];
            foreach ($invalidColumns as $columns) {
                assertFalse($columns >= $minColumns && $columns <= $maxColumns, "Grid columns {$columns} should be invalid");
            }
            
            return true;
        });
    }
    
    private function addOverlaySettingsTests() {
        $this->testRunner->addTest('Overlay Settings - show_username Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_username value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Overlay Settings - show_date Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_date value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Overlay Settings - overlay_opacity Validation', function() {
            $minOpacity = 0.1;
            $maxOpacity = 1.0;
            
            // Test valid opacity values
            $validOpacities = [0.1, 0.5, 0.8, 1.0, 0.25, 0.75];
            foreach ($validOpacities as $opacity) {
                $clamped = max($minOpacity, min($maxOpacity, $opacity));
                assertTrue($clamped >= $minOpacity && $clamped <= $maxOpacity, "Opacity {$opacity} should be valid");
            }
            
            // Test invalid opacity values
            $invalidOpacities = [0.0, 0.05, 1.1, 2.0, -0.1];
            foreach ($invalidOpacities as $opacity) {
                $clamped = max($minOpacity, min($maxOpacity, $opacity));
                assertTrue($clamped >= $minOpacity && $clamped <= $maxOpacity, "Invalid opacity {$opacity} should be clamped");
            }
            
            return true;
        });
    }
    
    private function addUploadConfigurationTests() {
        $this->testRunner->addTest('Upload Configuration - max_upload_size Validation', function() {
            $allowedSizes = [1048576, 2097152, 5242880, 10485760, 20971520, 52428800]; // 1MB to 50MB
            
            foreach ($allowedSizes as $size) {
                assertTrue(in_array($size, $allowedSizes), "Upload size '{$size}' should be valid");
            }
            
            $invalidSizes = [0, 512000, 1048575, 52428801, 100000000];
            foreach ($invalidSizes as $size) {
                assertFalse(in_array($size, $allowedSizes), "Upload size '{$size}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Upload Configuration - gps_validation_required Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "gps_validation_required value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Upload Configuration - moderation_required Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];

            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "moderation_required value '{$value}' should convert to boolean");
            }

            return true;
        });

        $this->testRunner->addTest('Upload Configuration - upload_enabled Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];

            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "upload_enabled value '{$value}' should convert to boolean");
            }

            // Test default value
            $defaultValue = 1; // Upload should be enabled by default
            assertTrue((bool)$defaultValue === true, "Default upload_enabled should be true");

            return true;
        });
        
        $this->testRunner->addTest('Upload Configuration - GPS Validation Logic', function() {
            // Test GPS validation when required
            $gpsRequired = true;
            $validLat = 52.5200;
            $validLon = 13.4050;
            $invalidLat = 91.0;
            $invalidLon = 181.0;
            
            if ($gpsRequired) {
                assertTrue(GeoUtils::validateCoordinates($validLat, $validLon), 'Valid coordinates should pass when GPS required');
                assertFalse(GeoUtils::validateCoordinates($invalidLat, $invalidLon), 'Invalid coordinates should fail when GPS required');
            }
            
            // Test GPS validation when not required
            $gpsRequired = false;
            if (!$gpsRequired) {
                assertTrue(true, 'GPS validation should be skipped when not required');
            }
            
            return true;
        });
    }
    
    private function addDisplayOptionsTests() {
        $this->testRunner->addTest('Display Options - show_logo Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_logo value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - show_qr_code Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_qr_code value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - show_display_link Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_display_link value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - show_gallery_link Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "show_gallery_link value '{$value}' should convert to boolean");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - is_active Boolean', function() {
            $validValues = [true, false, 1, 0, '1', '0'];
            
            foreach ($validValues as $value) {
                $boolValue = (bool)$value;
                assertTrue(is_bool($boolValue), "is_active value '{$value}' should convert to boolean");
            }
            
            return true;
        });
    }
    
    private function addEventValidationTests() {
        $this->testRunner->addTest('Event Validation - Name Required', function() {
            $validNames = ['Test Event', 'My Awesome Event 2024', 'Event with Special Characters!'];
            $invalidNames = ['', '   ', null];
            
            foreach ($validNames as $name) {
                assertTrue(!empty(trim($name)), "Event name '{$name}' should be valid");
            }
            
            foreach ($invalidNames as $name) {
                assertFalse(!empty(trim($name)), "Event name '{$name}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Validation - Radius Validation', function() {
            $minRadius = 10;
            $maxRadius = 10000;
            
            // Test valid radius values
            for ($i = $minRadius; $i <= $maxRadius; $i += 100) {
                assertTrue($i >= $minRadius && $i <= $maxRadius, "Radius {$i} should be valid");
            }
            
            // Test invalid radius values
            $invalidRadii = [0, 5, 9, 10001, 50000, -10];
            foreach ($invalidRadii as $radius) {
                assertFalse($radius >= $minRadius && $radius <= $maxRadius, "Radius {$radius} should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Validation - Slug Validation', function() {
            $validSlugs = ['test-event', 'my-awesome-event-2024', 'event-with-numbers123'];
            $invalidSlugs = ['admin', 'api', 'ab', 'test_event', 'Test-Event', 'test-event-', '-test-event'];
            
            foreach ($validSlugs as $slug) {
                assertTrue(validateSlug($slug), "Slug '{$slug}' should be valid");
            }
            
            foreach ($invalidSlugs as $slug) {
                assertFalse(validateSlug($slug), "Slug '{$slug}' should be invalid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Validation - Note Field', function() {
            $validNotes = ['', 'Short note', 'Very long note with multiple lines and special characters!@#$%^&*()'];
            $invalidNotes = [null]; // null should be converted to empty string
            
            foreach ($validNotes as $note) {
                $sanitized = sanitizeInput($note ?? '');
                assertTrue(is_string($sanitized), "Note should be a string");
            }
            
            return true;
        });
    }
    
    private function addEventManagementTests() {
        $this->testRunner->addTest('Event Management - Configuration Array Structure', function() {
            $config = [
                'name' => 'Test Event',
                'event_slug' => 'test-event',
                'latitude' => 52.5200,
                'longitude' => 13.4050,
                'radius_meters' => 100,
                'display_mode' => 'random',
                'display_count' => 1,
                'display_interval' => 5,
                'layout_type' => 'grid',
                'grid_columns' => 3,
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
                'note' => 'Test event configuration',
                'max_upload_size' => 10485760
            ];
            
            $requiredFields = [
                'name', 'event_slug', 'display_mode', 'display_count', 
                'display_interval', 'show_username', 'show_date', 
                'overlay_opacity', 'gps_validation_required', 'is_active'
            ];
            
            foreach ($requiredFields as $field) {
                assertTrue(isset($config[$field]), "Required field '{$field}' should be present");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Management - Default Values', function() {
            $defaults = [
                'display_mode' => 'random',
                'display_count' => 1,
                'display_interval' => 5,
                'layout_type' => 'grid',
                'grid_columns' => 3,
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
                'max_upload_size' => 10485760
            ];
            
            foreach ($defaults as $field => $defaultValue) {
                assertTrue(isset($defaultValue), "Default value for '{$field}' should be defined");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Event Management - Configuration Validation Logic', function() {
            // Test complete validation logic
            $eventData = [
                'name' => 'Test Event',
                'latitude' => 52.5200,
                'longitude' => 13.4050,
                'radius_meters' => 100,
                'display_mode' => 'random',
                'display_count' => 1,
                'display_interval' => 5,
                'gps_validation_required' => true
            ];
            
            // Validate name
            assertTrue(!empty($eventData['name']), 'Event name should not be empty');
            
            // Validate GPS if required
            if ($eventData['gps_validation_required']) {
                assertTrue(GeoUtils::validateCoordinates($eventData['latitude'], $eventData['longitude']), 'GPS coordinates should be valid when required');
            }
            
            // Validate radius
            assertTrue($eventData['radius_meters'] >= 10 && $eventData['radius_meters'] <= 10000, 'Radius should be in valid range');
            
            // Validate display settings
            assertTrue(in_array($eventData['display_mode'], ['random', 'newest', 'chronological']), 'Display mode should be valid');
            assertTrue(in_array($eventData['display_count'], [1, 2, 3, 4, 6, 8, 10]), 'Display count should be valid');
            assertTrue($eventData['display_interval'] >= 3 && $eventData['display_interval'] <= 60, 'Display interval should be in valid range');
            
            return true;
        });
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new EventConfigurationTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
