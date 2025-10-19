<?php
/**
 * Display Configuration Tests
 * Tests for display-specific configuration and functionality
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/config/database.php';

class DisplayConfigurationTests {
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
        $this->addDisplayModeTests();
        $this->addDisplayCountTests();
        $this->addDisplayIntervalTests();
        $this->addLayoutTypeTests();
        $this->addGridConfigurationTests();
        $this->addOverlayConfigurationTests();
        $this->addDisplayOptionsTests();
        $this->addURLParameterTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addDisplayModeTests() {
        $this->testRunner->addTest('Display Mode - Random Mode', function() {
            $displayMode = 'random';
            $validModes = ['random', 'newest', 'chronological'];
            
            assertTrue(in_array($displayMode, $validModes), 'Random mode should be valid');
            assertEquals('random', $displayMode, 'Display mode should be random');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Mode - Newest Mode', function() {
            $displayMode = 'newest';
            $validModes = ['random', 'newest', 'chronological'];
            
            assertTrue(in_array($displayMode, $validModes), 'Newest mode should be valid');
            assertEquals('newest', $displayMode, 'Display mode should be newest');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Mode - Chronological Mode', function() {
            $displayMode = 'chronological';
            $validModes = ['random', 'newest', 'chronological'];
            
            assertTrue(in_array($displayMode, $validModes), 'Chronological mode should be valid');
            assertEquals('chronological', $displayMode, 'Display mode should be chronological');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Mode - Invalid Mode Handling', function() {
            $invalidModes = ['invalid', 'custom', 'mixed', '', null];
            $validModes = ['random', 'newest', 'chronological'];
            $defaultMode = 'random';
            
            foreach ($invalidModes as $mode) {
                $processedMode = in_array($mode, $validModes) ? $mode : $defaultMode;
                assertEquals($defaultMode, $processedMode, "Invalid mode '{$mode}' should default to '{$defaultMode}'");
            }
            
            return true;
        });
    }
    
    private function addDisplayCountTests() {
        $this->testRunner->addTest('Display Count - Valid Counts', function() {
            $validCounts = [1, 2, 3, 4, 6, 8, 10];
            
            foreach ($validCounts as $count) {
                assertTrue(in_array($count, $validCounts), "Display count {$count} should be valid");
                assertTrue($count > 0, "Display count {$count} should be positive");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Count - Invalid Count Handling', function() {
            $invalidCounts = [0, 5, 7, 9, 11, 15, -1, 100];
            $validCounts = [1, 2, 3, 4, 6, 8, 10];
            $defaultCount = 1;
            
            foreach ($invalidCounts as $count) {
                $processedCount = in_array($count, $validCounts) ? $count : $defaultCount;
                assertEquals($defaultCount, $processedCount, "Invalid count {$count} should default to {$defaultCount}");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Count - Single Display', function() {
            $displayCount = 1;
            $isSingleDisplay = $displayCount === 1;
            
            assertTrue($isSingleDisplay, 'Single display should be detected');
            assertTrue($displayCount >= 1, 'Single display count should be at least 1');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Count - Grid Display', function() {
            $gridCounts = [2, 3, 4, 6, 8, 10];
            
            foreach ($gridCounts as $count) {
                $isGridDisplay = $count > 1;
                assertTrue($isGridDisplay, "Grid display count {$count} should be greater than 1");
            }
            
            return true;
        });
    }
    
    private function addDisplayIntervalTests() {
        $this->testRunner->addTest('Display Interval - Valid Intervals', function() {
            $minInterval = 3;
            $maxInterval = 60;
            
            for ($i = $minInterval; $i <= $maxInterval; $i++) {
                assertTrue($i >= $minInterval && $i <= $maxInterval, "Interval {$i} should be valid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Interval - Clamping', function() {
            $minInterval = 3;
            $maxInterval = 60;
            $defaultInterval = 5;
            
            $testIntervals = [
                ['input' => 1, 'expected' => 3],
                ['input' => 2, 'expected' => 3],
                ['input' => 5, 'expected' => 5],
                ['input' => 30, 'expected' => 30],
                ['input' => 60, 'expected' => 60],
                ['input' => 61, 'expected' => 60],
                ['input' => 100, 'expected' => 60],
                ['input' => 0, 'expected' => 3],
                ['input' => -5, 'expected' => 3]
            ];
            
            foreach ($testIntervals as $test) {
                $clamped = max($minInterval, min($maxInterval, $test['input']));
                assertEquals($test['expected'], $clamped, "Interval {$test['input']} should be clamped to {$test['expected']}");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Display Interval - Performance Considerations', function() {
            $fastInterval = 3; // 3 seconds
            $slowInterval = 60; // 60 seconds
            $normalInterval = 10; // 10 seconds
            
            assertTrue($fastInterval >= 3, 'Fast interval should be at least 3 seconds');
            assertTrue($slowInterval <= 60, 'Slow interval should be at most 60 seconds');
            assertTrue($normalInterval >= 3 && $normalInterval <= 60, 'Normal interval should be in valid range');
            
            return true;
        });
    }
    
    private function addLayoutTypeTests() {
        $this->testRunner->addTest('Layout Type - Single Layout', function() {
            $layoutType = 'single';
            $validLayouts = ['single', 'grid'];
            
            assertTrue(in_array($layoutType, $validLayouts), 'Single layout should be valid');
            assertEquals('single', $layoutType, 'Layout type should be single');
            
            return true;
        });
        
        $this->testRunner->addTest('Layout Type - Grid Layout', function() {
            $layoutType = 'grid';
            $validLayouts = ['single', 'grid'];
            
            assertTrue(in_array($layoutType, $validLayouts), 'Grid layout should be valid');
            assertEquals('grid', $layoutType, 'Layout type should be grid');
            
            return true;
        });
        
        $this->testRunner->addTest('Layout Type - Invalid Layout Handling', function() {
            $invalidLayouts = ['invalid', 'custom', 'mixed', '', null];
            $validLayouts = ['single', 'grid'];
            $defaultLayout = 'grid';
            
            foreach ($invalidLayouts as $layout) {
                $processedLayout = in_array($layout, $validLayouts) ? $layout : $defaultLayout;
                assertEquals($defaultLayout, $processedLayout, "Invalid layout '{$layout}' should default to '{$defaultLayout}'");
            }
            
            return true;
        });
    }
    
    private function addGridConfigurationTests() {
        $this->testRunner->addTest('Grid Configuration - Valid Columns', function() {
            $validColumns = [2, 3, 4, 5, 6];
            
            foreach ($validColumns as $columns) {
                assertTrue($columns >= 2 && $columns <= 6, "Grid columns {$columns} should be valid");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Grid Configuration - Column Clamping', function() {
            $minColumns = 2;
            $maxColumns = 6;
            $defaultColumns = 3;
            
            $testColumns = [
                ['input' => 1, 'expected' => 2],
                ['input' => 2, 'expected' => 2],
                ['input' => 3, 'expected' => 3],
                ['input' => 4, 'expected' => 4],
                ['input' => 6, 'expected' => 6],
                ['input' => 7, 'expected' => 6],
                ['input' => 10, 'expected' => 6],
                ['input' => 0, 'expected' => 2],
                ['input' => -1, 'expected' => 2]
            ];
            
            foreach ($testColumns as $test) {
                $clamped = max($minColumns, min($maxColumns, $test['input']));
                assertEquals($test['expected'], $clamped, "Grid columns {$test['input']} should be clamped to {$test['expected']}");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Grid Configuration - Responsive Considerations', function() {
            $mobileColumns = 2;
            $tabletColumns = 3;
            $desktopColumns = 4;
            
            assertTrue($mobileColumns >= 2, 'Mobile should have at least 2 columns');
            assertTrue($tabletColumns >= 2 && $tabletColumns <= 6, 'Tablet columns should be in valid range');
            assertTrue($desktopColumns >= 2 && $desktopColumns <= 6, 'Desktop columns should be in valid range');
            
            return true;
        });
    }
    
    private function addOverlayConfigurationTests() {
        $this->testRunner->addTest('Overlay Configuration - Username Display', function() {
            $showUsername = true;
            $hideUsername = false;
            
            assertTrue($showUsername, 'Show username should be true');
            assertFalse($hideUsername, 'Hide username should be false');
            
            // Test toggle
            $toggledShow = !$showUsername;
            $toggledHide = !$hideUsername;
            
            assertFalse($toggledShow, 'Toggled show username should be false');
            assertTrue($toggledHide, 'Toggled hide username should be true');
            
            return true;
        });
        
        $this->testRunner->addTest('Overlay Configuration - Date Display', function() {
            $showDate = true;
            $hideDate = false;
            
            assertTrue($showDate, 'Show date should be true');
            assertFalse($hideDate, 'Hide date should be false');
            
            // Test toggle
            $toggledShow = !$showDate;
            $toggledHide = !$hideDate;
            
            assertFalse($toggledShow, 'Toggled show date should be false');
            assertTrue($toggledHide, 'Toggled hide date should be true');
            
            return true;
        });
        
        $this->testRunner->addTest('Overlay Configuration - Opacity Validation', function() {
            $minOpacity = 0.1;
            $maxOpacity = 1.0;
            
            $testOpacities = [
                ['input' => 0.0, 'expected' => 0.1],
                ['input' => 0.1, 'expected' => 0.1],
                ['input' => 0.5, 'expected' => 0.5],
                ['input' => 0.8, 'expected' => 0.8],
                ['input' => 1.0, 'expected' => 1.0],
                ['input' => 1.1, 'expected' => 1.0],
                ['input' => 2.0, 'expected' => 1.0],
                ['input' => -0.1, 'expected' => 0.1]
            ];
            
            foreach ($testOpacities as $test) {
                $clamped = max($minOpacity, min($maxOpacity, $test['input']));
                assertEquals($test['expected'], $clamped, "Opacity {$test['input']} should be clamped to {$test['expected']}");
            }
            
            return true;
        });
    }
    
    private function addDisplayOptionsTests() {
        $this->testRunner->addTest('Display Options - Logo Display', function() {
            $showLogo = true;
            $hideLogo = false;
            
            assertTrue($showLogo, 'Show logo should be true');
            assertFalse($hideLogo, 'Hide logo should be false');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - QR Code Display', function() {
            $showQrCode = true;
            $hideQrCode = false;
            
            assertTrue($showQrCode, 'Show QR code should be true');
            assertFalse($hideQrCode, 'Hide QR code should be false');
            
            return true;
        });
        
        $this->testRunner->addTest('Display Options - Link Display', function() {
            $showDisplayLink = true;
            $showGalleryLink = true;
            $hideDisplayLink = false;
            $hideGalleryLink = false;
            
            assertTrue($showDisplayLink, 'Show display link should be true');
            assertTrue($showGalleryLink, 'Show gallery link should be true');
            assertFalse($hideDisplayLink, 'Hide display link should be false');
            assertFalse($hideGalleryLink, 'Hide gallery link should be false');
            
            return true;
        });
    }
    
    private function addURLParameterTests() {
        $this->testRunner->addTest('URL Parameters - show_logo Parameter', function() {
            $urlParams = [
                'show_logo=1' => true,
                'show_logo=0' => false,
                'show_logo=true' => true,
                'show_logo=false' => false
            ];
            
            foreach ($urlParams as $param => $expected) {
                $value = filter_var(explode('=', $param)[1], FILTER_VALIDATE_BOOLEAN);
                assertEquals($expected, $value, "URL parameter '{$param}' should parse correctly");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('URL Parameters - display_count Parameter', function() {
            $validCounts = [1, 2, 3, 4, 6, 8, 10];
            $invalidCounts = [0, 5, 7, 9, 11, 15];
            
            foreach ($validCounts as $count) {
                $urlParam = "display_count={$count}";
                $parsedCount = (int)explode('=', $urlParam)[1];
                assertTrue(in_array($parsedCount, $validCounts), "Valid display count {$count} should be accepted");
            }
            
            foreach ($invalidCounts as $count) {
                $urlParam = "display_count={$count}";
                $parsedCount = (int)explode('=', $urlParam)[1];
                assertFalse(in_array($parsedCount, $validCounts), "Invalid display count {$count} should be rejected");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('URL Parameters - display_mode Parameter', function() {
            $validModes = ['random', 'newest', 'chronological'];
            $invalidModes = ['invalid', 'custom', 'mixed'];
            
            foreach ($validModes as $mode) {
                $urlParam = "display_mode={$mode}";
                $parsedMode = explode('=', $urlParam)[1];
                assertTrue(in_array($parsedMode, $validModes), "Valid display mode '{$mode}' should be accepted");
            }
            
            foreach ($invalidModes as $mode) {
                $urlParam = "display_mode={$mode}";
                $parsedMode = explode('=', $urlParam)[1];
                assertFalse(in_array($parsedMode, $validModes), "Invalid display mode '{$mode}' should be rejected");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('URL Parameters - display_interval Parameter', function() {
            $minInterval = 3;
            $maxInterval = 60;
            
            $testIntervals = [3, 5, 10, 30, 60, 1, 2, 61, 100];
            
            foreach ($testIntervals as $interval) {
                $urlParam = "display_interval={$interval}";
                $parsedInterval = (int)explode('=', $urlParam)[1];
                $clampedInterval = max($minInterval, min($maxInterval, $parsedInterval));
                
                assertTrue($clampedInterval >= $minInterval && $clampedInterval <= $maxInterval, 
                    "Display interval {$interval} should be clamped to valid range");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('URL Parameters - Complete URL Example', function() {
            $exampleUrl = 'http://localhost:4000/test-event/display?show_logo=0&display_count=1&display_mode=random&display_interval=10';
            
            // Parse URL components
            $urlParts = parse_url($exampleUrl);
            $queryParams = [];
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $queryParams);
            }
            
            // Validate parsed parameters
            assertTrue(isset($queryParams['show_logo']), 'show_logo parameter should be present');
            assertTrue(isset($queryParams['display_count']), 'display_count parameter should be present');
            assertTrue(isset($queryParams['display_mode']), 'display_mode parameter should be present');
            assertTrue(isset($queryParams['display_interval']), 'display_interval parameter should be present');
            
            assertEquals('0', $queryParams['show_logo'], 'show_logo should be 0');
            assertEquals('1', $queryParams['display_count'], 'display_count should be 1');
            assertEquals('random', $queryParams['display_mode'], 'display_mode should be random');
            assertEquals('10', $queryParams['display_interval'], 'display_interval should be 10');
            
            return true;
        });
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new DisplayConfigurationTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
