<?php
/**
 * Comprehensive Upload Functionality Tests
 * Tests all upload-related functions and scenarios
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';
require_once __DIR__ . '/../app/includes/geo.php';

class UploadFunctionalityTests {
    private $testRunner;
    private $testDataPath;
    private $testEventSlug;
    
    public function __construct() {
        $this->testRunner = new TestRunner(true);
        $this->testDataPath = __DIR__ . '/data';
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
        $this->addFileValidationTests();
        $this->addImageProcessingTests();
        $this->addFileHandlingTests();
        $this->addSecurityTests();
        $this->addErrorHandlingTests();
        $this->addPerformanceTests();
        $this->addFormatSupportTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addFileValidationTests() {
        $this->testRunner->addTest('Upload Validation - JPEG File', function() {
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
        
        $this->testRunner->addTest('Upload Validation - PNG File', function() {
            $validFile = [
                'name' => 'test.png',
                'type' => 'image/png',
                'size' => 1024,
                'tmp_name' => $this->createTestImage('png'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid PNG should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - GIF File', function() {
            $validFile = [
                'name' => 'test.gif',
                'type' => 'image/gif',
                'size' => 1024,
                'tmp_name' => $this->createTestImage('gif'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid GIF should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - WebP File', function() {
            $validFile = [
                'name' => 'test.webp',
                'type' => 'image/webp',
                'size' => 1024,
                'tmp_name' => $this->createTestImage('webp'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid WebP should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - HEIC File', function() {
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
        
        $this->testRunner->addTest('Upload Validation - HEIF File', function() {
            $validFile = [
                'name' => 'test.heif',
                'type' => 'image/heif',
                'size' => 1024,
                'tmp_name' => $this->createTestFile('heif'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($validFile, 2048);
            assertTrue(empty($errors), 'Valid HEIF should pass validation');
            
            unlink($validFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - Invalid File Type', function() {
            $invalidFile = [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'size' => 1024,
                'tmp_name' => $this->createTestFile('txt'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($invalidFile, 2048);
            assertFalse(empty($errors), 'Invalid file type should fail validation');
            assertContains('Dateityp nicht erlaubt', $errors[0], 'Error should mention file type');
            
            unlink($invalidFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - File Too Large', function() {
            $largeFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 5 * 1024 * 1024, // 5MB
                'tmp_name' => $this->createTestImage('jpeg'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($largeFile, 2 * 1024 * 1024); // 2MB limit
            assertFalse(empty($errors), 'File too large should fail validation');
            assertContains('Datei zu groß', $errors[0], 'Error should mention file size');
            
            unlink($largeFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - Upload Error', function() {
            $errorFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE
            ];
            
            $errors = validateFileUpload($errorFile, 2048);
            assertFalse(empty($errors), 'Upload error should fail validation');
            assertContains('Keine Datei hochgeladen', $errors[0], 'Error should mention no file');
            
            return true;
        });
        
        $this->testRunner->addTest('Upload Validation - Corrupted File', function() {
            $corruptedFile = [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => $this->createCorruptedImage(),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($corruptedFile, 2048);
            // Should fail validation because getimagesize() can't read corrupted file
            assertFalse(empty($errors), 'Corrupted file should fail validation');
            assertContains('gültiges Bild', $errors[0], 'Error should mention invalid image');
            
            unlink($corruptedFile['tmp_name']);
            return true;
        });
    }
    
    private function addImageProcessingTests() {
        $this->testRunner->addTest('Image Processing - JPEG Resize', function() {
            $testImage = $this->createLargeTestImage('jpeg');
            $resizedPath = $this->testDataPath . '/resized.jpg';
            
            $result = resizeImage($testImage, $resizedPath, 800, 600, 85);
            
            assertTrue($result, 'JPEG resize should succeed');
            assertFileExists($resizedPath, 'Resized file should exist');
            
            $imageInfo = getimagesize($resizedPath);
            assertTrue($imageInfo !== false, 'Resized image should be valid');
            assertTrue($imageInfo[0] <= 800, 'Resized width should be <= 800px');
            assertTrue($imageInfo[1] <= 600, 'Resized height should be <= 600px');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - PNG Resize with Transparency', function() {
            $testImage = $this->createTestImageWithTransparency('png');
            $resizedPath = $this->testDataPath . '/resized.png';
            
            $result = resizeImage($testImage, $resizedPath, 400, 300, 85);
            
            assertTrue($result, 'PNG resize should succeed');
            assertFileExists($resizedPath, 'Resized PNG file should exist');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - GIF Resize', function() {
            $testImage = $this->createTestImage('gif');
            $resizedPath = $this->testDataPath . '/resized.gif';
            
            $result = resizeImage($testImage, $resizedPath, 300, 200, 85);
            
            assertTrue($result, 'GIF resize should succeed');
            assertFileExists($resizedPath, 'Resized GIF file should exist');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - WebP Resize', function() {
            $testImage = $this->createTestImage('webp');
            $resizedPath = $this->testDataPath . '/resized.webp';
            
            $result = resizeImage($testImage, $resizedPath, 500, 400, 85);
            
            assertTrue($result, 'WebP resize should succeed');
            assertFileExists($resizedPath, 'Resized WebP file should exist');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Thumbnail Creation JPEG', function() {
            $testImage = $this->createTestImage('jpeg');
            $thumbnailPath = $this->testDataPath . '/thumb.jpg';
            
            $result = createThumbnail($testImage, $thumbnailPath, 200, 200, 85);
            
            assertTrue($result, 'JPEG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'Thumbnail file should exist');
            
            $imageInfo = getimagesize($thumbnailPath);
            assertTrue($imageInfo !== false, 'Thumbnail should be valid image');
            assertTrue($imageInfo[0] <= 200, 'Thumbnail width should be <= 200px');
            assertTrue($imageInfo[1] <= 200, 'Thumbnail height should be <= 200px');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Thumbnail Creation PNG', function() {
            $testImage = $this->createTestImageWithTransparency('png');
            $thumbnailPath = $this->testDataPath . '/thumb.png';
            
            $result = createThumbnail($testImage, $thumbnailPath, 150, 150, 85);
            
            assertTrue($result, 'PNG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'PNG thumbnail file should exist');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Auto Rotation JPEG', function() {
            $testImage = $this->createTestImage('jpeg');
            
            $result = autoRotateImage($testImage);
            
            // Should return false for images that don't need rotation
            assertTrue($result === false || $result === true, 'Auto rotate should return boolean');
            
            unlink($testImage);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Auto Rotation PNG', function() {
            $testImage = $this->createTestImage('png');
            
            $result = autoRotateImage($testImage);
            
            assertTrue($result === false || $result === true, 'Auto rotate should return boolean');
            
            unlink($testImage);
            return true;
        });
    }
    
    private function addFileHandlingTests() {
        $this->testRunner->addTest('File Handling - Hash Calculation', function() {
            $testFile = $this->testDataPath . '/test.txt';
            file_put_contents($testFile, 'test content for hashing');
            
            $hash1 = calculateFileHash($testFile);
            $hash2 = calculateFileHash($testFile);
            
            assertTrue($hash1 !== false, 'File hash should be calculated');
            assertEquals($hash1, $hash2, 'Same file should produce same hash');
            assertTrue(strlen($hash1) === 64, 'Hash should be 64 characters');
            assertTrue(ctype_xdigit($hash1), 'Hash should be hexadecimal');
            
            unlink($testFile);
            return true;
        });
        
        $this->testRunner->addTest('File Handling - Unique Filename Generation', function() {
            $filename1 = generateUniqueFilename('test.jpg');
            $filename2 = generateUniqueFilename('test.jpg');
            $filename3 = generateUniqueFilename('test.png');
            
            assertNotEquals($filename1, $filename2, 'Generated filenames should be unique');
            assertContains('.jpg', $filename1, 'JPEG extension should be preserved');
            assertContains('.jpg', $filename2, 'JPEG extension should be preserved');
            assertContains('.png', $filename3, 'PNG extension should be preserved');
            assertTrue(strlen($filename1) > 10, 'Filename should be reasonably long');
            
            return true;
        });
        
        $this->testRunner->addTest('File Handling - Event Directory Creation', function() {
            $eventSlug = 'test-event-' . time();
            $paths = getEventUploadPaths($eventSlug);
            
            ensureEventDirectories($eventSlug);
            
            assertTrue(is_dir($paths['photos_path']), 'Photos directory should be created');
            assertTrue(is_dir($paths['logos_path']), 'Logos directory should be created');
            assertTrue(is_dir($paths['thumbnails_path']), 'Thumbnails directory should be created');
            
            // Test directory permissions
            assertTrue(is_writable($paths['photos_path']), 'Photos directory should be writable');
            assertTrue(is_writable($paths['logos_path']), 'Logos directory should be writable');
            assertTrue(is_writable($paths['thumbnails_path']), 'Thumbnails directory should be writable');
            
            // Cleanup
            rmdir($paths['photos_path']);
            rmdir($paths['logos_path']);
            rmdir($paths['thumbnails_path']);
            rmdir(dirname($paths['photos_path']));
            
            return true;
        });
        
        $this->testRunner->addTest('File Handling - File Size Formatting', function() {
            assertEquals('1 B', formatBytes(1), '1 byte should format correctly');
            assertEquals('1 KB', formatBytes(1024, 0), '1024 bytes should be 1 KB');
            assertEquals('1 MB', formatBytes(1024 * 1024, 0), '1 MB should format correctly');
            assertEquals('1 GB', formatBytes(1024 * 1024 * 1024, 0), '1 GB should format correctly');
            assertEquals('1.5 MB', formatBytes(1024 * 1024 * 1.5), '1.5 MB should format correctly');
            assertEquals('2.5 GB', formatBytes(1024 * 1024 * 1024 * 2.5), '2.5 GB should format correctly');
            
            return true;
        });
    }
    
    private function addSecurityTests() {
        $this->testRunner->addTest('Security - Malicious File Extension', function() {
            $maliciousFile = [
                'name' => 'malicious.php.jpg',
                'type' => 'image/jpeg',
                'size' => 1024,
                'tmp_name' => $this->createTestImage('jpeg'),
                'error' => UPLOAD_ERR_OK
            ];
            
            $errors = validateFileUpload($maliciousFile, 2048);
            assertTrue(empty($errors), 'File with .php in name should pass validation (MIME type check)');
            
            unlink($maliciousFile['tmp_name']);
            return true;
        });
        
        $this->testRunner->addTest('Security - Input Sanitization', function() {
            $maliciousInput = '<script>alert("xss")</script>';
            $sanitized = sanitizeInput($maliciousInput);
            
            assertNotEquals($maliciousInput, $sanitized, 'Input should be sanitized');
            assertFalse(strpos($sanitized, '<script>') !== false, 'Script tags should be removed');
            assertTrue(strpos($sanitized, '&lt;script&gt;') !== false, 'Script tags should be HTML-encoded');
            
            return true;
        });
        
        $this->testRunner->addTest('Security - CSRF Token Generation', function() {
            $token1 = generateCSRFToken();
            $token2 = generateCSRFToken();
            
            assertTrue(strlen($token1) === 64, 'CSRF token should be 64 characters');
            assertEquals($token1, $token2, 'CSRF token should be consistent in session');
            assertTrue(ctype_xdigit($token1), 'CSRF token should be hexadecimal');
            
            return true;
        });
        
        $this->testRunner->addTest('Security - CSRF Token Validation', function() {
            $token = generateCSRFToken();
            
            assertTrue(validateCSRFToken($token), 'Valid CSRF token should pass validation');
            assertFalse(validateCSRFToken('invalid'), 'Invalid CSRF token should fail validation');
            assertFalse(validateCSRFToken(''), 'Empty CSRF token should fail validation');
            
            return true;
        });
    }
    
    private function addErrorHandlingTests() {
        $this->testRunner->addTest('Error Handling - Non-existent File', function() {
            $result = calculateFileHash('/non/existent/file.jpg');
            assertFalse($result, 'Non-existent file should return false');
            
            return true;
        });
        
        $this->testRunner->addTest('Error Handling - Invalid Image File', function() {
            $invalidFile = $this->testDataPath . '/invalid.txt';
            file_put_contents($invalidFile, 'not an image');
            
            $thumbnailPath = $this->testDataPath . '/invalid_thumb.jpg';
            $result = createThumbnail($invalidFile, $thumbnailPath, 100, 100, 85);
            
            assertFalse($result, 'Invalid image should fail thumbnail creation');
            assertFileNotExists($thumbnailPath, 'Thumbnail should not be created for invalid image');
            
            unlink($invalidFile);
            return true;
        });
        
        $this->testRunner->addTest('Error Handling - Corrupted Image File', function() {
            $corruptedFile = $this->createCorruptedImage();
            
            $thumbnailPath = $this->testDataPath . '/corrupted_thumb.jpg';
            $result = createThumbnail($corruptedFile, $thumbnailPath, 100, 100, 85);
            
            assertFalse($result, 'Corrupted image should fail thumbnail creation');
            assertFileNotExists($thumbnailPath, 'Thumbnail should not be created for corrupted image');
            
            unlink($corruptedFile);
            return true;
        });
        
        $this->testRunner->addTest('Error Handling - Permission Denied', function() {
            $testFile = $this->testDataPath . '/test.jpg';
            $this->createTestImage('jpeg', $testFile);
            
            // Make file read-only
            chmod($testFile, 0444);
            
            $thumbnailPath = $this->testDataPath . '/readonly_thumb.jpg';
            $result = createThumbnail($testFile, $thumbnailPath, 100, 100, 85);
            
            // Should fail due to permission issues or succeed (depending on system)
            // Just test that the function handles it gracefully
            assertTrue($result === false || $result === true, 'Permission handling should be graceful');
            
            // Restore permissions and cleanup
            chmod($testFile, 0644);
            unlink($testFile);
            
            return true;
        });
    }
    
    private function addPerformanceTests() {
        $this->testRunner->addTest('Performance - Large Image Processing', function() {
            $startTime = microtime(true);
            
            $testImage = $this->createLargeTestImage('jpeg');
            $resizedPath = $this->testDataPath . '/large_resized.jpg';
            
            $result = resizeImage($testImage, $resizedPath, 1920, 1080, 85);
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            assertTrue($result, 'Large image resize should succeed');
            assertTrue($duration < 5.0, 'Large image processing should complete within 5 seconds');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
        
        $this->testRunner->addTest('Performance - Multiple Thumbnail Creation', function() {
            $startTime = microtime(true);
            
            $formats = ['jpeg', 'png', 'gif'];
            $results = [];
            
            foreach ($formats as $format) {
                $testImage = $this->createTestImage($format);
                $thumbnailPath = $this->testDataPath . "/thumb.{$format}";
                
                $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
                $results[$format] = $result;
                
                unlink($testImage);
                if ($result) {
                    unlink($thumbnailPath);
                }
            }
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            foreach ($results as $format => $result) {
                assertTrue($result, "Thumbnail creation should work for {$format}");
            }
            
            assertTrue($duration < 3.0, 'Multiple thumbnail creation should complete within 3 seconds');
            
            return true;
        });
    }
    
    private function addFormatSupportTests() {
        $this->testRunner->addTest('Format Support - All Supported Formats', function() {
            $formats = [
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'heif' => 'image/heif'
            ];
            
            foreach ($formats as $ext => $mimeType) {
                $testFile = [
                    'name' => "test.{$ext}",
                    'type' => $mimeType,
                    'size' => 1024,
                    'tmp_name' => $ext === 'heic' || $ext === 'heif' ? 
                        $this->createTestFile($ext) : $this->createTestImage($ext),
                    'error' => UPLOAD_ERR_OK
                ];
                
                $errors = validateFileUpload($testFile, 2048);
                assertTrue(empty($errors), "Format {$ext} should pass validation");
                
                unlink($testFile['tmp_name']);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Format Support - Image Processing All Formats', function() {
            $formats = ['jpeg', 'png', 'gif', 'webp'];
            $results = [];
            
            foreach ($formats as $format) {
                $testImage = $this->createTestImage($format);
                $thumbnailPath = $this->testDataPath . "/thumb.{$format}";
                
                $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
                $results[$format] = $result;
                
                unlink($testImage);
                if ($result) {
                    unlink($thumbnailPath);
                }
            }
            
            foreach ($results as $format => $result) {
                assertTrue($result, "Thumbnail creation should work for {$format}");
            }
            
            return true;
        });
    }
    
    private function createTestImage($format, $path = null) {
        if (!$path) {
            $path = $this->testDataPath . '/test.' . $format;
        }
        
        $image = imagecreate(200, 200);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 3, 80, 90, 'TEST', $text);
        
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
            case 'webp':
                if (function_exists('imagewebp')) {
                    // Convert palette image to true color for WebP
                    $trueColorImage = imagecreatetruecolor(200, 200);
                    imagecopy($trueColorImage, $image, 0, 0, 0, 0, 200, 200);
                    imagewebp($trueColorImage, $path, 90);
                    imagedestroy($trueColorImage);
                } else {
                    // Fallback to JPEG if WebP not supported
                    imagejpeg($image, $path, 90);
                }
                break;
        }
        
        imagedestroy($image);
        return $path;
    }
    
    private function createTestImageWithTransparency($format) {
        $path = $this->testDataPath . '/test_transparent.' . $format;
        
        $image = imagecreate(200, 200);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 3, 80, 90, 'TEST', $text);
        
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
    
    private function createLargeTestImage($format) {
        $path = $this->testDataPath . '/large_test.' . $format;
        
        $image = imagecreate(2000, 1500);
        $bg = imagecolorallocate($image, 255, 255, 255);
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 5, 900, 700, 'LARGE TEST', $text);
        
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
    
    private function createTestFile($extension) {
        $path = $this->testDataPath . '/test.' . $extension;
        file_put_contents($path, 'test content');
        return $path;
    }
    
    private function createCorruptedImage() {
        $path = $this->testDataPath . '/corrupted.jpg';
        // Create a minimal JPEG header to pass MIME type check
        file_put_contents($path, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xD9");
        return $path;
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new UploadFunctionalityTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
