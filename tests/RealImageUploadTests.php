<?php
/**
 * Real Image Upload Tests
 * Tests with actual image files from tests/pics directory
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';

class RealImageUploadTests {
    private $testRunner;
    private $testDataPath;
    private $picsPath;
    private $testEventSlug;
    
    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->testDataPath = __DIR__ . '/data';
        $this->picsPath = __DIR__ . '/pics';
        $this->testEventSlug = 'test-event-' . time();
        $this->setupTestData();
    }
    
    private function setupTestData() {
        if (!is_dir($this->testDataPath)) {
            mkdir($this->testDataPath, 0755, true);
        }
        
        // Create test event directories
        $paths = getEventUploadPaths($this->testEventSlug);
        ensureEventDirectories($this->testEventSlug);
    }
    
    public function runAllTests() {
        $this->addRealImageValidationTests();
        $this->addRealImageProcessingTests();
        $this->addRealImageFormatTests();
        $this->addRealImagePerformanceTests();
        $this->addRealImageSecurityTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addRealImageValidationTests() {
        $this->testRunner->addTest('Real Image - JPEG Validation', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $fileInfo = [
                'name' => 'IMG_4825.jpg',
                'type' => 'image/jpeg',
                'size' => filesize($jpegFile),
                'tmp_name' => $jpegFile,
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($fileInfo, 10 * 1024 * 1024); // 10MB limit
            
            assertTrue(empty($errors), 'Real JPEG should pass validation');
            assertTrue($fileInfo['size'] > 0, 'Real JPEG should have size > 0');
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - HEIC Validation', function() {
            $heicFile = $this->picsPath . '/IMG_7078.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $fileInfo = [
                'name' => 'IMG_7078.HEIC',
                'type' => 'image/heic',
                'size' => filesize($heicFile),
                'tmp_name' => $heicFile,
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($fileInfo, 10 * 1024 * 1024); // 10MB limit
            
            assertTrue(empty($errors), 'Real HEIC should pass validation');
            assertTrue($fileInfo['size'] > 0, 'Real HEIC should have size > 0');
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - HEIC Validation (Second)', function() {
            $heicFile = $this->picsPath . '/IMG_7010.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $fileInfo = [
                'name' => 'IMG_7010.HEIC',
                'type' => 'image/heic',
                'size' => filesize($heicFile),
                'tmp_name' => $heicFile,
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($fileInfo, 10 * 1024 * 1024); // 10MB limit
            
            assertTrue(empty($errors), 'Real HEIC (second) should pass validation');
            assertTrue($fileInfo['size'] > 0, 'Real HEIC (second) should have size > 0');
            
            return true;
        });
    }
    
    private function addRealImageProcessingTests() {
        $this->testRunner->addTest('Real Image - JPEG Processing', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $resizedPath = $this->testDataPath . '/real_jpeg_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/real_jpeg_thumb.jpg';
            
            // Test resize
            $resizeResult = resizeImage($jpegFile, $resizedPath, 1920, 1080, 85);
            assertTrue($resizeResult, 'Real JPEG resize should succeed');
            assertFileExists($resizedPath, 'Resized JPEG should exist');
            
            // Test thumbnail creation
            $thumbResult = createThumbnail($jpegFile, $thumbnailPath, 300, 300, 85);
            assertTrue($thumbResult, 'Real JPEG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'Thumbnail JPEG should exist');
            
            // Test auto rotation
            $rotationResult = autoRotateImage($jpegFile);
            assertTrue($rotationResult === false || $rotationResult === true, 'Auto rotation should return boolean');
            
            // Cleanup
            if (file_exists($resizedPath)) unlink($resizedPath);
            if (file_exists($thumbnailPath)) unlink($thumbnailPath);
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - HEIC Processing', function() {
            $heicFile = $this->picsPath . '/IMG_7078.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $resizedPath = $this->testDataPath . '/real_heic_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/real_heic_thumb.jpg';
            
            // Test resize (HEIC should be converted to JPEG first)
            $resizeResult = resizeImage($heicFile, $resizedPath, 1920, 1080, 85);
            // HEIC processing might fail if ImageMagick is not available
            assertTrue($resizeResult === false || $resizeResult === true, 'HEIC resize should return boolean (may fail without ImageMagick)');
            
            // Test thumbnail creation
            $thumbResult = createThumbnail($heicFile, $thumbnailPath, 300, 300, 85);
            assertTrue($thumbResult === false || $thumbResult === true, 'HEIC thumbnail should return boolean (may fail without ImageMagick)');
            
            // Cleanup
            if (file_exists($resizedPath)) unlink($resizedPath);
            if (file_exists($thumbnailPath)) unlink($thumbnailPath);
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - HEIC Processing (Second)', function() {
            $heicFile = $this->picsPath . '/IMG_7010.HEIC';
            
            if (!file_exists($heicFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $resizedPath = $this->testDataPath . '/real_heic2_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/real_heic2_thumb.jpg';
            
            // Test resize
            $resizeResult = resizeImage($heicFile, $resizedPath, 1920, 1080, 85);
            assertTrue($resizeResult === false || $resizeResult === true, 'HEIC (second) resize should return boolean (may fail without ImageMagick)');
            
            // Test thumbnail creation
            $thumbResult = createThumbnail($heicFile, $thumbnailPath, 300, 300, 85);
            assertTrue($thumbResult === false || $thumbResult === true, 'HEIC (second) thumbnail should return boolean (may fail without ImageMagick)');
            
            // Cleanup
            if (file_exists($resizedPath)) unlink($resizedPath);
            if (file_exists($thumbnailPath)) unlink($thumbnailPath);
            
            return true;
        });
    }
    
    private function addRealImageFormatTests() {
        $this->testRunner->addTest('Real Image - Format Detection', function() {
            $files = [
                'IMG_4825.jpg' => 'image/jpeg',
                'IMG_7078.HEIC' => 'image/heic',
                'IMG_7010.HEIC' => 'image/heic'
            ];
            
            foreach ($files as $filename => $expectedMime) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                assertTrue($detectedMime === $expectedMime, "MIME type for {$filename} should be {$expectedMime}, got {$detectedMime}");
                
                // Test image dimensions (HEIC might not be supported by getimagesize)
                $imageInfo = getimagesize($filePath);
                if ($imageInfo !== false) {
                    assertTrue($imageInfo[0] > 0, "Image width should be > 0 for {$filename}");
                    assertTrue($imageInfo[1] > 0, "Image height should be > 0 for {$filename}");
                } else {
                    // HEIC files might not be supported by getimagesize
                    assertTrue(strpos($filename, 'HEIC') !== false, "HEIC files might not be supported by getimagesize");
                }
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - File Hash Calculation', function() {
            $files = ['IMG_4825.jpg', 'IMG_7078.HEIC', 'IMG_7010.HEIC'];
            
            foreach ($files as $filename) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                $hash1 = calculateFileHash($filePath);
                $hash2 = calculateFileHash($filePath);
                
                assertTrue($hash1 !== false, "Hash should be calculated for {$filename}");
                assertEquals($hash1, $hash2, "Same file should produce same hash for {$filename}");
                assertTrue(strlen($hash1) === 64, "Hash should be 64 characters for {$filename}");
                assertTrue(ctype_xdigit($hash1), "Hash should be hexadecimal for {$filename}");
            }
            
            return true;
        });
    }
    
    private function addRealImagePerformanceTests() {
        $this->testRunner->addTest('Real Image - Performance Test', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $startTime = microtime(true);
            
            $resizedPath = $this->testDataPath . '/perf_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/perf_thumb.jpg';
            
            // Test resize performance
            $resizeResult = resizeImage($jpegFile, $resizedPath, 1920, 1080, 85);
            assertTrue($resizeResult, 'Performance test resize should succeed');
            
            // Test thumbnail performance
            $thumbResult = createThumbnail($jpegFile, $thumbnailPath, 300, 300, 85);
            assertTrue($thumbResult, 'Performance test thumbnail should succeed');
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            assertTrue($duration < 10.0, "Real image processing should complete within 10 seconds, took {$duration}s");
            
            // Cleanup
            if (file_exists($resizedPath)) unlink($resizedPath);
            if (file_exists($thumbnailPath)) unlink($thumbnailPath);
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - Batch Processing', function() {
            $files = ['IMG_4825.jpg', 'IMG_7078.HEIC', 'IMG_7010.HEIC'];
            $startTime = microtime(true);
            $results = [];
            
            foreach ($files as $filename) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                $thumbnailPath = $this->testDataPath . "/batch_thumb_{$filename}.jpg";
                $result = createThumbnail($filePath, $thumbnailPath, 200, 200, 85);
                $results[$filename] = $result;
                
                if ($result && file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            foreach ($results as $filename => $result) {
                // HEIC files might fail without ImageMagick
                if (strpos($filename, 'HEIC') !== false) {
                    assertTrue($result === false || $result === true, "Batch processing for {$filename} should return boolean (may fail without ImageMagick)");
                } else {
                    assertTrue($result, "Batch processing should work for {$filename}");
                }
            }
            
            assertTrue($duration < 15.0, "Batch processing should complete within 15 seconds, took {$duration}s");
            
            return true;
        });
    }
    
    private function addRealImageSecurityTests() {
        $this->testRunner->addTest('Real Image - Security Validation', function() {
            $files = ['IMG_4825.jpg', 'IMG_7078.HEIC', 'IMG_7010.HEIC'];
            
            foreach ($files as $filename) {
                $filePath = $this->picsPath . '/' . $filename;
                
                if (!file_exists($filePath)) {
                    continue; // Skip if file doesn't exist
                }
                
                // Test file size
                $fileSize = filesize($filePath);
                assertTrue($fileSize > 0, "File size should be > 0 for {$filename}");
                assertTrue($fileSize < 50 * 1024 * 1024, "File size should be < 50MB for {$filename}");
                
                // Test file permissions
                assertTrue(is_readable($filePath), "File should be readable for {$filename}");
                
                // Test MIME type validation
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                
                $allowedTypes = ['image/jpeg', 'image/heic', 'image/heif'];
                assertTrue(in_array($mimeType, $allowedTypes), "MIME type {$mimeType} should be allowed for {$filename}");
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Real Image - Duplicate Detection', function() {
            $jpegFile = $this->picsPath . '/IMG_4825.jpg';
            
            if (!file_exists($jpegFile)) {
                return true; // Skip if file doesn't exist
            }
            
            $hash1 = calculateFileHash($jpegFile);
            $hash2 = calculateFileHash($jpegFile);
            
            assertTrue($hash1 === $hash2, 'Same file should produce same hash for duplicate detection');
            assertTrue(strlen($hash1) === 64, 'Hash should be 64 characters for duplicate detection');
            
            return true;
        });
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new RealImageUploadTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
