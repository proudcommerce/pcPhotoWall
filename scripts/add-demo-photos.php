#!/usr/bin/env php
<?php
/**
 * Demo Photos Addition Script
 *
 * Adds sample photos to the demo event for fresh installations.
 * This creates placeholder images to showcase the application.
 */

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

function createPlaceholderImage($width, $height, $text, $bgColor) {
    $image = imagecreatetruecolor($width, $height);

    // Parse hex color
    $r = hexdec(substr($bgColor, 0, 2));
    $g = hexdec(substr($bgColor, 2, 2));
    $b = hexdec(substr($bgColor, 4, 2));

    $backgroundColor = imagecolorallocate($image, $r, $g, $b);
    $textColor = imagecolorallocate($image, 255, 255, 255);

    imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);

    // Add text
    $fontSize = 48;
    $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    // Fallback if font doesn't exist
    if (!file_exists($fontFile)) {
        $fontFile = null;
    }

    if ($fontFile) {
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        $x = ($width - $textWidth) / 2;
        $y = ($height + $textHeight) / 2;
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $text);
    } else {
        // Fallback to built-in font
        $x = ($width - strlen($text) * 10) / 2;
        $y = ($height - 20) / 2;
        imagestring($image, 5, $x, $y, $text, $textColor);
    }

    return $image;
}

function addDemoPhotos() {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Find demo event
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_slug = 'demo-event'");
        $stmt->execute();
        $event = $stmt->fetch();

        if (!$event) {
            echo "Demo event not found. Please create it first.\n";
            return false;
        }

        // Check if demo event already has photos
        $stmt = $conn->prepare("SELECT COUNT(*) FROM photos WHERE event_id = ?");
        $stmt->execute([$event['id']]);
        $photoCount = $stmt->fetchColumn();

        if ($photoCount > 0) {
            echo "Demo event already has {$photoCount} photos. Skipping.\n";
            return false;
        }

        echo "Adding demo photos to demo event...\n";

        $eventSlug = $event['event_slug'];
        $paths = getEventUploadPaths($eventSlug);

        // Ensure directories exist
        ensureEventDirectories($eventSlug);

        // Demo photo configurations
        $demoPhotos = [
            ['text' => 'Welcome!', 'color' => '3498db', 'username' => 'Demo User'],
            ['text' => 'Upload Photos', 'color' => '2ecc71', 'username' => 'PhotoWall'],
            ['text' => 'Share Moments', 'color' => 'e74c3c', 'username' => 'Admin'],
            ['text' => 'Create Events', 'color' => 'f39c12', 'username' => 'Demo User'],
            ['text' => 'Display Gallery', 'color' => '9b59b6', 'username' => 'PhotoWall'],
            ['text' => 'QR Codes', 'color' => '1abc9c', 'username' => 'Admin'],
        ];

        $uploadedCount = 0;

        foreach ($demoPhotos as $index => $photo) {
            // Create placeholder image
            $image = createPlaceholderImage(1920, 1080, $photo['text'], $photo['color']);

            $filename = 'demo_photo_' . ($index + 1) . '_' . time() . '.jpg';
            $photoPath = $paths['photos'] . '/' . $filename;
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $paths['thumbnails'] . '/' . $thumbnailFilename;

            // Save full-size image
            imagejpeg($image, $photoPath, 90);

            // Create thumbnail
            $thumbnail = imagescale($image, 400);
            imagejpeg($thumbnail, $thumbnailPath, 85);

            imagedestroy($image);
            imagedestroy($thumbnail);

            // Calculate file hash
            $fileHash = hash_file('sha256', $photoPath);
            $fileSize = filesize($photoPath);

            // Insert into database
            $sql = "INSERT INTO photos (
                event_id, filename, thumbnail_filename, original_name, username,
                latitude, longitude, distance_meters, file_size, mime_type,
                file_hash, is_active
            ) VALUES (
                :event_id, :filename, :thumbnail_filename, :original_name, :username,
                :latitude, :longitude, :distance_meters, :file_size, :mime_type,
                :file_hash, :is_active
            )";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'event_id' => $event['id'],
                'filename' => $filename,
                'thumbnail_filename' => $thumbnailFilename,
                'original_name' => $filename,
                'username' => $photo['username'],
                'latitude' => null,
                'longitude' => null,
                'distance_meters' => null,
                'file_size' => $fileSize,
                'mime_type' => 'image/jpeg',
                'file_hash' => $fileHash,
                'is_active' => 1
            ]);

            $uploadedCount++;
            echo "  ✓ Created demo photo {$uploadedCount}: {$photo['text']}\n";
        }

        echo "\n✓ Successfully added {$uploadedCount} demo photos to demo event!\n";
        echo "  View them at: http://localhost:4000/{$eventSlug}/gallery\n";

        return true;

    } catch (Exception $e) {
        echo "Error adding demo photos: " . $e->getMessage() . "\n";
        error_log("Demo photos creation error: " . $e->getMessage());
        return false;
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli') {
    echo "=== Picturewall Demo Photos Creation ===\n\n";
    addDemoPhotos();
    echo "\n";
}
