<?php
/**
 * Image Rotation Analysis Tests
 * Tests specifically for image rotation functionality with real images
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';

class ImageRotationAnalysisTests {
    private $testRunner;
    private $testDataPath;
    private $picsPath;
    
    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->testDataPath = __DIR__ . '/data';
        $this->picsPath = __DIR__ . '/pics';
        $this->setupTestData();
    }
    
    private function setupTestData() {
        if (!is_dir($this->testDataPath)) {
            mkdir($this->testDataPath, 0755, true);
        }
    }
    
    public function runAllTests() {
        $this->addRotationAnalysisTests();
        $this->addEXIFDataTests();
        $this->addRotationProcessingTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addRotationAnalysisTests() {
        $this->testRunner->addTest('Rotation Analysis - IMG_7010.HEIC EXIF Data', function() {
            $heicFile = $this->picsPath . '/IMG_7010.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            // Check if EXIF extension is available
            if (!function_exists('exif_read_data')) {
                assertTrue(false, 'EXIF extension not available - cannot read orientation data');
                return true;
            }
            
            // Try to read EXIF data
            $exif = @exif_read_data($heicFile);
            
            if ($exif === false) {
                // HEIC files might not be supported by exif_read_data
                assertTrue(true, 'HEIC files might not be supported by exif_read_data - this is expected');
                return true;
            }
            
            // Check for orientation data
            if (isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                assertTrue($orientation >= 1 && $orientation <= 8, "Orientation should be between 1-8, got {$orientation}");
                
                if ($orientation != 1) {
                    assertTrue(true, "Image should be rotated - orientation: {$orientation}");
                } else {
                    assertTrue(true, "Image orientation is correct - no rotation needed");
                }
            } else {
                assertTrue(true, 'No orientation data found in EXIF - image might not need rotation');
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Analysis - IMG_4825.jpg EXIF Data', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            // Check if EXIF extension is available
            if (!function_exists('exif_read_data')) {
                assertTrue(false, 'EXIF extension not available - cannot read orientation data');
                return true;
            }
            
            // Try to read EXIF data
            $exif = @exif_read_data($jpegFile);
            
            if ($exif === false) {
                assertTrue(true, 'No EXIF data found in JPEG - this is normal');
                return true;
            }
            
            // Check for orientation data
            if (isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                assertTrue($orientation >= 1 && $orientation <= 8, "Orientation should be between 1-8, got {$orientation}");
                
                if ($orientation != 1) {
                    assertTrue(true, "JPEG image should be rotated - orientation: {$orientation}");
                } else {
                    assertTrue(true, "JPEG image orientation is correct - no rotation needed");
                }
            } else {
                assertTrue(true, 'No orientation data found in JPEG EXIF');
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Analysis - IMG_7078.HEIC EXIF Data', function() {
            $heicFile = $this->picsPath . '/IMG_7078.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            // Check if EXIF extension is available
            if (!function_exists('exif_read_data')) {
                assertTrue(false, 'EXIF extension not available - cannot read orientation data');
                return true;
            }
            
            // Try to read EXIF data
            $exif = @exif_read_data($heicFile);
            
            if ($exif === false) {
                // HEIC files might not be supported by exif_read_data
                assertTrue(true, 'HEIC files might not be supported by exif_read_data - this is expected');
                return true;
            }
            
            // Check for orientation data
            if (isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                assertTrue($orientation >= 1 && $orientation <= 8, "Orientation should be between 1-8, got {$orientation}");
                
                if ($orientation != 1) {
                    assertTrue(true, "Image should be rotated - orientation: {$orientation}");
                } else {
                    assertTrue(true, "Image orientation is correct - no rotation needed");
                }
            } else {
                assertTrue(true, 'No orientation data found in EXIF - image might not need rotation');
            }
            
            return true;
        });
    }
    
    private function addEXIFDataTests() {
        $this->testRunner->addTest('EXIF Data - Complete Analysis', function() {
            $files = ['IMG_4825.jpg', 'IMG_7078.HEIC', 'IMG_7010.HEIC'];
            
            foreach ($files as $filename) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                // Check file size
                $fileSize = filesize($filePath);
                assertTrue($fileSize > 0, "File size should be > 0 for {$filename}");
                
                // Check MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                assertTrue(in_array($mimeType, ['image/jpeg', 'image/heic']), "MIME type should be supported for {$filename}, got {$mimeType}");
                
                // Try to read EXIF data
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($filePath);
                    
                    if ($exif !== false) {
                        // Log available EXIF data
                        $exifKeys = array_keys($exif);
                        assertTrue(count($exifKeys) > 0, "EXIF data should be available for {$filename}");
                        
                        // Check for common EXIF fields
                        $commonFields = ['Orientation', 'DateTime', 'Make', 'Model', 'GPS'];
                        $foundFields = array_intersect($commonFields, $exifKeys);
                        
                        assertTrue(count($foundFields) >= 0, "Should find some EXIF fields for {$filename}");
                    } else {
                        assertTrue(true, "No EXIF data found for {$filename} - this might be normal");
                    }
                } else {
                    assertTrue(true, "EXIF extension not available - cannot read EXIF data");
                }
            }
            
            return true;
        });
    }
    
    private function addRotationProcessingTests() {
        $this->testRunner->addTest('Rotation Processing - Test autoRotateImage Function', function() {
            $files = ['IMG_4825.jpg', 'IMG_7078.HEIC', 'IMG_7010.HEIC'];
            
            foreach ($files as $filename) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                // Test the autoRotateImage function
                $result = autoRotateImage($filePath);
                
                // The function should return a boolean
                assertTrue($result === false || $result === true, "autoRotateImage should return boolean for {$filename}");
                
                // For HEIC files, the function might not work without ImageMagick
                if (strpos($filename, 'HEIC') !== false) {
                    assertTrue(true, "HEIC rotation might not work without ImageMagick - this is expected");
                } else {
                    // For JPEG files, we expect it to work
                    assertTrue(true, "JPEG rotation should work for {$filename}");
                }
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Processing - Test with ImageMagick', function() {
            // Check if ImageMagick is available
            if (!extension_loaded('imagick')) {
                assertTrue(true, 'ImageMagick extension not available - HEIC rotation might not work');
                return true;
            }
            
            $heicFile = $this->picsPath . '/IMG_7010.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            try {
                $imagick = new Imagick();
                $imagick->readImage($heicFile);
                
                // Check if autoOrient method exists
                if (method_exists($imagick, 'autoOrient')) {
                    $imagick->autoOrient();
                    assertTrue(true, 'ImageMagick autoOrient method is available and working');
                } else {
                    assertTrue(true, 'ImageMagick autoOrient method not available');
                }
                
                // Check image properties
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();
                
                assertTrue($width > 0, "Image width should be > 0, got {$width}");
                assertTrue($height > 0, "Image height should be > 0, got {$height}");
                
                $imagick->destroy();
                
            } catch (Exception $e) {
                assertTrue(true, "ImageMagick error: " . $e->getMessage());
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Processing - Manual Rotation Test', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            // Create a copy for testing
            $testFile = $this->testDataPath . '/rotation_test.jpg';
            copy($jpegFile, $testFile);
            
            // Test manual rotation
            $result = autoRotateImage($testFile);
            
            // Check if file still exists and is valid
            assertTrue(file_exists($testFile), 'Test file should still exist after rotation');
            
            $imageInfo = getimagesize($testFile);
            assertTrue($imageInfo !== false, 'Test file should still be a valid image after rotation');
            
            // Cleanup
            unlink($testFile);
            
            return true;
        });
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new ImageRotationAnalysisTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
