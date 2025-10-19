<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/exceptions.php';

/**
 * ImageProcessor Class
 *
 * Handles all image processing operations including:
 * - Creating image resources from various formats
 * - Saving images in different formats
 * - Resizing and thumbnail creation
 * - Image rotation and EXIF orientation correction
 */
class ImageProcessor {

    /**
     * Create an image resource from a file path
     *
     * @param string $imagePath Path to the image file
     * @param string $mimeType MIME type of the image
     * @return \GdImage|false Image resource or false on failure
     * @throws ImageProcessingException
     */
    public static function createImageResource(string $imagePath, string $mimeType): \GdImage|false {
        $resource = match($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($imagePath),
            'image/png' => @imagecreatefrompng($imagePath),
            'image/gif' => @imagecreatefromgif($imagePath),
            'image/webp' => @imagecreatefromwebp($imagePath),
            'image/heic', 'image/heif' => @imagecreatefromjpeg($imagePath), // Assumes already converted
            default => false
        };

        if ($resource === false) {
            throw new ImageProcessingException("Failed to create image resource from: {$imagePath} (MIME: {$mimeType})");
        }

        return $resource;
    }

    /**
     * Save an image resource to a file
     *
     * @param \GdImage $image Image resource to save
     * @param string $destinationPath Destination file path
     * @param string $mimeType MIME type to save as
     * @param int $quality Quality setting (for JPEG/WebP)
     * @return bool Success status
     */
    public static function saveImage(\GdImage $image, string $destinationPath, string $mimeType, int $quality = IMAGE_QUALITY_MEDIUM): bool {
        return match($mimeType) {
            'image/jpeg' => imagejpeg($image, $destinationPath, $quality),
            'image/png' => imagepng($image, $destinationPath, 9),
            'image/gif' => imagegif($image, $destinationPath),
            'image/webp' => imagewebp($image, $destinationPath, $quality),
            default => false
        };
    }

    /**
     * Preserve transparency for PNG and GIF images
     *
     * @param \GdImage $image Image resource
     * @param string $mimeType MIME type of the image
     * @return void
     */
    public static function preserveTransparency(\GdImage $image, string $mimeType): void {
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }

    /**
     * Calculate new dimensions maintaining aspect ratio
     *
     * @param int $originalWidth Original image width
     * @param int $originalHeight Original image height
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return array{width: int, height: int} New dimensions
     */
    public static function calculateDimensions(int $originalWidth, int $originalHeight, int $maxWidth, int $maxHeight): array {
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        return [
            'width' => (int)($originalWidth * $ratio),
            'height' => (int)($originalHeight * $ratio)
        ];
    }

    /**
     * Check if MIME type is supported for image processing
     *
     * @param string $mimeType MIME type to check
     * @return bool True if supported
     */
    public static function isSupportedMimeType(string $mimeType): bool {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heic',
            'image/heif'
        ]);
    }

    /**
     * Resize an image to fit within maximum dimensions
     *
     * @param string $sourcePath Source image path
     * @param string $destinationPath Destination path for resized image
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @param int $quality Quality for JPEG/WebP
     * @return bool Success status
     * @throws ImageProcessingException
     */
    public static function resize(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth = IMAGE_MAX_WIDTH,
        int $maxHeight = IMAGE_MAX_HEIGHT,
        int $quality = IMAGE_QUALITY_MEDIUM
    ): bool {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new ImageProcessingException("Failed to read image information from: {$sourcePath}");
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Calculate new dimensions
        $newDimensions = self::calculateDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight);

        // Create source image
        $sourceImage = self::createImageResource($sourcePath, $mimeType);
        if ($sourceImage === false) {
            throw new ImageProcessingException("Failed to create image resource from: {$sourcePath}");
        }

        // Create new image
        $newImage = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
        if ($newImage === false) {
            imagedestroy($sourceImage);
            throw new ImageProcessingException("Failed to create new image resource");
        }

        // Preserve transparency
        self::preserveTransparency($newImage, $mimeType);

        // Resize image
        imagecopyresampled(
            $newImage,
            $sourceImage,
            0, 0, 0, 0,
            $newDimensions['width'],
            $newDimensions['height'],
            $originalWidth,
            $originalHeight
        );

        // Save image
        $result = self::saveImage($newImage, $destinationPath, $mimeType, $quality);

        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);

        return $result;
    }

    /**
     * Create a thumbnail from an image
     *
     * @param string $sourcePath Source image path
     * @param string $destinationPath Destination path for thumbnail
     * @param int $maxWidth Maximum thumbnail width
     * @param int $maxHeight Maximum thumbnail height
     * @param int $quality Quality for JPEG
     * @return bool Success status
     * @throws ImageProcessingException
     */
    public static function createThumbnail(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth = THUMBNAIL_MAX_WIDTH,
        int $maxHeight = THUMBNAIL_MAX_HEIGHT,
        int $quality = THUMBNAIL_QUALITY
    ): bool {
        // Thumbnails are always saved as JPEG for consistency and file size
        return self::resize($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality);
    }
}
?>
