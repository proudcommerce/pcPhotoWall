<?php
/**
 * Simplified Integration Tests for PC PhotoWall Upload Process
 * Focuses on core functionality without database dependencies
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/includes/functions.php';

class SimpleIntegrationTests {
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
        $this->addImageProcessingTests();
        $this->addFileHandlingTests();
        $this->addErrorHandlingTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addImageProcessingTests() {
        $this->testRunner->addTest('Image Processing - JPEG Thumbnail', function() {
            $testImage = $this->createTestImage('jpeg');
            $thumbnailPath = $this->testDataPath . '/thumbnail.jpg';
            
            $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
            
            assertTrue($result, 'JPEG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'Thumbnail file should be created');
            
            $imageInfo = getimagesize($thumbnailPath);
            assertTrue($imageInfo !== false, 'Thumbnail should be valid image');
            assertTrue($imageInfo[0] <= 100, 'Thumbnail width should be <= 100px');
            assertTrue($imageInfo[1] <= 100, 'Thumbnail height should be <= 100px');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - PNG with Transparency', function() {
            $testImage = $this->createTestImageWithTransparency('png');
            $thumbnailPath = $this->testDataPath . '/thumbnail.png';
            
            $result = createThumbnail($testImage, $thumbnailPath, 100, 100, 85);
            
            assertTrue($result, 'PNG thumbnail creation should succeed');
            assertFileExists($thumbnailPath, 'PNG thumbnail file should be created');
            
            unlink($testImage);
            unlink($thumbnailPath);
            return true;
        });
        
        $this->testRunner->addTest('Image Processing - Multiple Formats', function() {
            $formats = ['jpeg', 'png', 'gif'];
            $results = [];
            
            foreach ($formats as $format) {
                $testImage = $this->createTestImage($format);
                $thumbnailPath = $this->testDataPath . "/thumbnail.{$format}";
                
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
        
        $this->testRunner->addTest('Image Processing - Large Image Resize', function() {
            $testImage = $this->createLargeTestImage('jpeg');
            $resizedPath = $this->testDataPath . '/resized.jpg';
            
            $result = resizeImage($testImage, $resizedPath, 800, 600, 85);
            
            assertTrue($result, 'Large image resize should succeed');
            assertFileExists($resizedPath, 'Resized file should exist');
            
            $imageInfo = getimagesize($resizedPath);
            assertTrue($imageInfo !== false, 'Resized image should be valid');
            assertTrue($imageInfo[0] <= 800, 'Resized width should be <= 800px');
            assertTrue($imageInfo[1] <= 600, 'Resized height should be <= 600px');
            
            unlink($testImage);
            unlink($resizedPath);
            return true;
        });
    }
    
    private function addFileHandlingTests() {
        $this->testRunner->addTest('File Handling - Hash Calculation', function() {
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
        
        $this->testRunner->addTest('File Handling - Unique Filename Generation', function() {
            $filename1 = generateUniqueFilename('test.jpg');
            $filename2 = generateUniqueFilename('test.jpg');
            
            assertNotEquals($filename1, $filename2, 'Generated filenames should be unique');
            assertContains('.jpg', $filename1, 'Extension should be preserved');
            assertContains('.jpg', $filename2, 'Extension should be preserved');
            
            return true;
        });
        
        $this->testRunner->addTest('File Handling - File Upload Validation', function() {
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
    }
    
    private function addErrorHandlingTests() {
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
        
        $this->testRunner->addTest('Error Handling - Non-existent File', function() {
            $nonExistentFile = '/non/existent/file.jpg';
            
            $fileHash = calculateFileHash($nonExistentFile);
            assertFalse($fileHash, 'Non-existent file should return false hash');
            
            $thumbnailCreated = createThumbnail($nonExistentFile, '/tmp/test.jpg', 100, 100, 85);
            assertFalse($thumbnailCreated, 'Thumbnail creation should fail for non-existent file');
            
            return true;
        });
        
        $this->testRunner->addTest('Error Handling - File Too Large', function() {
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
            case 'webp':
                imagewebp($image, $path, 90);
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
    
    private function createLargeTestImage($format) {
        $path = $this->testDataPath . '/large.' . $format;
        
        $image = imagecreate(2000, 1500);
        $bg = imagecolorallocate($image, 200, 200, 200);
        $text = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, 5, 800, 700, 'LARGE TEST', $text);
        
        switch ($format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, $path, 90);
                break;
            case 'png':
                imagepng($image, $path, 9);
                break;
        }
        
        imagedestroy($image);
        return $path;
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new SimpleIntegrationTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
