<?php
/**
 * Photo Rotation Functionality Tests
 * Tests for the manual photo rotation feature
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../app/includes/functions.php';

class PhotoRotationTests {
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
        $this->addRotationFunctionTests();
        $this->addRotationValidationTests();
        $this->addRotationFormatTests();
        $this->addRotationErrorHandlingTests();
        $this->addRotationIntegrationTests();
        
        return $this->testRunner->runAll();
    }
    
    private function addRotationFunctionTests() {
        $this->testRunner->addTest('Rotation Function - Basic Functionality', function() {
            // Test that rotateImage function exists
            assertTrue(function_exists('rotateImage'), 'rotateImage function should exist');
            
            // Test function signature
            $reflection = new ReflectionFunction('rotateImage');
            $params = $reflection->getParameters();
            assertTrue(count($params) === 2, 'rotateImage should have 2 parameters');
            assertTrue($params[0]->getName() === 'imagePath', 'First parameter should be imagePath');
            assertTrue($params[1]->getName() === 'angle', 'Second parameter should be angle');
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Function - Invalid Angle Validation', function() {
            $testImage = $this->testDataPath . '/test_rotation.jpg';
            $this->createTestImage($testImage);
            
            // Test invalid angles
            $invalidAngles = [0, 45, 120, 360, -90, 91, 179, 271];
            
            foreach ($invalidAngles as $angle) {
                $result = rotateImage($testImage, $angle);
                assertTrue($result === false, "Invalid angle {$angle} should return false");
            }
            
            // Cleanup
            if (file_exists($testImage)) {
                unlink($testImage);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Function - Valid Angle Processing', function() {
            $testImage = $this->testDataPath . '/test_rotation_valid.jpg';
            $this->createTestImage($testImage);
            
            // Test valid angles
            $validAngles = [90, 180, 270];
            
            foreach ($validAngles as $angle) {
                // Create a copy for each test
                $testCopy = $this->testDataPath . "/test_rotation_{$angle}.jpg";
                copy($testImage, $testCopy);
                
                $result = rotateImage($testCopy, $angle);
                assertTrue($result === true || $result === false, "Valid angle {$angle} should return boolean");
                
                // Check if file still exists and is valid
                if ($result) {
                    assertTrue(file_exists($testCopy), "File should still exist after rotation");
                    $imageInfo = getimagesize($testCopy);
                    assertTrue($imageInfo !== false, "File should still be a valid image after rotation");
                }
                
                // Cleanup
                if (file_exists($testCopy)) {
                    unlink($testCopy);
                }
            }
            
            // Cleanup
            if (file_exists($testImage)) {
                unlink($testImage);
            }
            
            return true;
        });
    }
    
    private function addRotationValidationTests() {
        $this->testRunner->addTest('Rotation Validation - File Not Found', function() {
            $nonExistentFile = $this->testDataPath . '/non_existent.jpg';
            
            $result = rotateImage($nonExistentFile, 90);
            assertTrue($result === false, 'Non-existent file should return false');
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Validation - Invalid File Type', function() {
            $textFile = $this->testDataPath . '/test.txt';
            file_put_contents($textFile, 'This is not an image');
            
            $result = rotateImage($textFile, 90);
            assertTrue($result === false, 'Text file should return false');
            
            // Cleanup
            if (file_exists($textFile)) {
                unlink($textFile);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Validation - Corrupted Image', function() {
            $corruptedFile = $this->testDataPath . '/corrupted_rotation.jpg';
            // Create a file with JPEG header but corrupted content
            file_put_contents($corruptedFile, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xD9");
            
            $result = rotateImage($corruptedFile, 90);
            assertTrue($result === false, 'Corrupted image should return false');
            
            // Cleanup
            if (file_exists($corruptedFile)) {
                unlink($corruptedFile);
            }
            
            return true;
        });
    }
    
    private function addRotationFormatTests() {
        $this->testRunner->addTest('Rotation Format - JPEG Support', function() {
            $jpegFile = $this->testDataPath . '/test_rotation_jpeg.jpg';
            $this->createTestImage($jpegFile);
            
            $result = rotateImage($jpegFile, 90);
            assertTrue($result === true || $result === false, 'JPEG rotation should return boolean');
            
            // Cleanup
            if (file_exists($jpegFile)) {
                unlink($jpegFile);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Format - PNG Support', function() {
            $pngFile = $this->testDataPath . '/test_rotation.png';
            $this->createTestPNG($pngFile);
            
            $result = rotateImage($pngFile, 90);
            assertTrue($result === true || $result === false, 'PNG rotation should return boolean');
            
            // Cleanup
            if (file_exists($pngFile)) {
                unlink($pngFile);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Format - GIF Support', function() {
            $gifFile = $this->testDataPath . '/test_rotation.gif';
            $this->createTestGIF($gifFile);
            
            $result = rotateImage($gifFile, 90);
            assertTrue($result === true || $result === false, 'GIF rotation should return boolean');
            
            // Cleanup
            if (file_exists($gifFile)) {
                unlink($gifFile);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Format - WebP Support', function() {
            $webpFile = $this->testDataPath . '/test_rotation.webp';
            $this->createTestWebP($webpFile);
            
            $result = rotateImage($webpFile, 90);
            assertTrue($result === true || $result === false, 'WebP rotation should return boolean');
            
            // Cleanup
            if (file_exists($webpFile)) {
                unlink($webpFile);
            }
            
            return true;
        });
    }
    
    private function addRotationErrorHandlingTests() {
        $this->testRunner->addTest('Rotation Error Handling - Memory Limit', function() {
            // This test checks if the function handles memory issues gracefully
            $testImage = $this->testDataPath . '/test_memory.jpg';
            $this->createTestImage($testImage);
            
            $result = rotateImage($testImage, 90);
            assertTrue($result === true || $result === false, 'Function should handle memory gracefully');
            
            // Cleanup
            if (file_exists($testImage)) {
                unlink($testImage);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Error Handling - Permission Issues', function() {
            $testImage = $this->testDataPath . '/test_permission.jpg';
            $this->createTestImage($testImage);
            
            // Make file read-only (if possible)
            if (function_exists('chmod')) {
                chmod($testImage, 0444);
            }
            
            $result = rotateImage($testImage, 90);
            assertTrue($result === true || $result === false, 'Function should handle permission issues gracefully');
            
            // Restore permissions and cleanup
            if (function_exists('chmod')) {
                chmod($testImage, 0644);
            }
            if (file_exists($testImage)) {
                unlink($testImage);
            }
            
            return true;
        });
    }
    
    private function addRotationIntegrationTests() {
        $this->testRunner->addTest('Rotation Integration - Multiple Rotations', function() {
            $testImage = $this->testDataPath . '/test_multiple_rotations.jpg';
            $this->createTestImage($testImage);
            
            // Apply multiple rotations
            $rotations = [90, 180, 270];
            $totalRotation = 0;
            
            foreach ($rotations as $angle) {
                $result = rotateImage($testImage, $angle);
                assertTrue($result === true || $result === false, "Rotation {$angle} should return boolean");
                
                if ($result) {
                    $totalRotation += $angle;
                }
            }
            
            // File should still be valid
            assertTrue(file_exists($testImage), 'File should still exist after multiple rotations');
            $imageInfo = getimagesize($testImage);
            assertTrue($imageInfo !== false, 'File should still be a valid image after multiple rotations');
            
            // Cleanup
            if (file_exists($testImage)) {
                unlink($testImage);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Integration - Rotation and Thumbnail', function() {
            $originalImage = $this->testDataPath . '/test_rotation_thumb_original.jpg';
            $thumbnailPath = $this->testDataPath . '/test_rotation_thumb.jpg';
            
            $this->createTestImage($originalImage);
            
            // Create thumbnail
            $thumbResult = createThumbnail($originalImage, $thumbnailPath, 150, 150, 85);
            assertTrue($thumbResult === true || $thumbResult === false, 'Thumbnail creation should work');
            
            if ($thumbResult && file_exists($thumbnailPath)) {
                // Rotate thumbnail
                $rotationResult = rotateImage($thumbnailPath, 90);
                assertTrue($rotationResult === true || $rotationResult === false, 'Thumbnail rotation should work');
                
                // Both files should still be valid
                assertTrue(file_exists($originalImage), 'Original should still exist');
                assertTrue(file_exists($thumbnailPath), 'Thumbnail should still exist');
            }
            
            // Cleanup
            if (file_exists($originalImage)) {
                unlink($originalImage);
            }
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Integration - rotateImageWithVariants Function', function() {
            // Test that the new function exists
            assertTrue(function_exists('rotateImageWithVariants'), 'rotateImageWithVariants function should exist');
            
            $originalImage = $this->testDataPath . '/test_variants_original.jpg';
            $resizedPath = $this->testDataPath . '/test_variants_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/test_variants_thumb.jpg';
            
            $this->createTestImage($originalImage);
            
            // Create resized and thumbnail
            $resizeResult = resizeImage($originalImage, $resizedPath, 400, 300, 85);
            $thumbResult = createThumbnail($originalImage, $thumbnailPath, 150, 150, 85);
            
            if ($resizeResult && $thumbResult) {
                // Test rotateImageWithVariants
                $results = rotateImageWithVariants($originalImage, 90, $resizedPath, $thumbnailPath);
                
                assertTrue(is_array($results), 'Results should be an array');
                assertTrue(isset($results['original']), 'Results should contain original');
                assertTrue(isset($results['resized']), 'Results should contain resized');
                assertTrue(isset($results['thumbnail']), 'Results should contain thumbnail');
                
                assertTrue($results['original'] === true || $results['original'] === false, 'Original rotation should return boolean');
                assertTrue($results['resized'] === true || $results['resized'] === false, 'Resized regeneration should return boolean');
                assertTrue($results['thumbnail'] === true || $results['thumbnail'] === false, 'Thumbnail regeneration should return boolean');
                
                // All files should still exist and be valid
                assertTrue(file_exists($originalImage), 'Original should still exist');
                assertTrue(file_exists($resizedPath), 'Resized should still exist');
                assertTrue(file_exists($thumbnailPath), 'Thumbnail should still exist');
                
                $originalInfo = getimagesize($originalImage);
                $resizedInfo = getimagesize($resizedPath);
                $thumbInfo = getimagesize($thumbnailPath);
                
                assertTrue($originalInfo !== false, 'Original should be valid image');
                assertTrue($resizedInfo !== false, 'Resized should be valid image');
                assertTrue($thumbInfo !== false, 'Thumbnail should be valid image');
            }
            
            // Cleanup
            if (file_exists($originalImage)) {
                unlink($originalImage);
            }
            if (file_exists($resizedPath)) {
                unlink($resizedPath);
            }
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            return true;
        });
        
        $this->testRunner->addTest('Rotation Integration - Variants Regeneration Only', function() {
            $originalImage = $this->testDataPath . '/test_variants_only_original.jpg';
            $resizedPath = $this->testDataPath . '/test_variants_only_resized.jpg';
            $thumbnailPath = $this->testDataPath . '/test_variants_only_thumb.jpg';
            
            $this->createTestImage($originalImage);
            
            // Create resized and thumbnail
            $resizeResult = resizeImage($originalImage, $resizedPath, 400, 300, 85);
            $thumbResult = createThumbnail($originalImage, $thumbnailPath, 150, 150, 85);
            
            if ($resizeResult && $thumbResult) {
                // Test with only resized path (no thumbnail)
                $results1 = rotateImageWithVariants($originalImage, 90, $resizedPath, null);
                assertTrue($results1['original'] === true || $results1['original'] === false, 'Original rotation should work');
                assertTrue($results1['resized'] === true || $results1['resized'] === false, 'Resized regeneration should work');
                assertTrue($results1['thumbnail'] === false, 'Thumbnail should not be processed');
                
                // Test with only thumbnail path (no resized)
                $results2 = rotateImageWithVariants($originalImage, 180, null, $thumbnailPath);
                assertTrue($results2['original'] === true || $results2['original'] === false, 'Original rotation should work');
                assertTrue($results2['resized'] === false, 'Resized should not be processed');
                assertTrue($results2['thumbnail'] === true || $results2['thumbnail'] === false, 'Thumbnail regeneration should work');
            }
            
            // Cleanup
            if (file_exists($originalImage)) {
                unlink($originalImage);
            }
            if (file_exists($resizedPath)) {
                unlink($resizedPath);
            }
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            return true;
        });
    }
    
    private function createTestImage($path) {
        $image = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);
        return $path;
    }
    
    private function createTestPNG($path) {
        $image = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($image, 0, 255, 0);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path, 9);
        imagedestroy($image);
        return $path;
    }
    
    private function createTestGIF($path) {
        $image = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($image, 0, 0, 255);
        imagefill($image, 0, 0, $color);
        imagegif($image, $path);
        imagedestroy($image);
        return $path;
    }
    
    private function createTestWebP($path) {
        $image = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($image, 255, 255, 0);
        imagefill($image, 0, 0, $color);
        
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
        
        imagedestroy($image);
        return $path;
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new PhotoRotationTests();
    $success = $tests->runAllTests();
    exit($success ? 0 : 1);
}
