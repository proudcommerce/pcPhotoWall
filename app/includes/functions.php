<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ImageProcessor.php';
require_once __DIR__ . '/exceptions.php';

// Version Functions
function getCurrentVersion(): string {
    $changelogPath = __DIR__ . '/../../CHANGELOG.md';
    if (!file_exists($changelogPath)) {
        return '1.0.0';
    }
    
    $content = file_get_contents($changelogPath);
    if (preg_match('/## \[([0-9]+\.[0-9]+\.[0-9]+)\]/', $content, $matches)) {
        return $matches[1];
    }
    
    return '1.0.0';
}

// CSRF Token Functions
function generateCSRFToken(): string {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Event Hash Functions
function generateEventHash(): string {
    return md5(uniqid(rand(), true) . microtime(true));
}

function getEventByHash(string $hash): array|false {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_hash = ?");
        $stmt->execute([$hash]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting event by hash: " . $e->getMessage());
        return false;
    }
}

function getEventHashById(int $eventId): string|false {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT event_hash FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting event hash by ID: " . $e->getMessage());
        return false;
    }
}

// Event Slug Functions
function generateSlug(string $name): string {
    // Convert to lowercase and replace spaces/special chars with hyphens
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Ensure it's not empty
    if (empty($slug)) {
        $slug = 'event-' . time();
    }
    
    return $slug;
}

function isSlugUnique(string $slug, ?int $excludeId = null): bool {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($excludeId) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE event_slug = ?");
            $stmt->execute([$slug]);
        }
        
        return $stmt->fetchColumn() == 0;
    } catch (Exception $e) {
        error_log("Error checking slug uniqueness: " . $e->getMessage());
        return false;
    }
}

function generateUniqueSlug(string $name, ?int $excludeId = null): string {
    $baseSlug = generateSlug($name);
    $slug = $baseSlug;
    $counter = 1;
    
    while (!isSlugUnique($slug, $excludeId)) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

function getEventBySlug(string $slug): array|false {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting event by slug: " . $e->getMessage());
        return false;
    }
}

function validateSlug(string $slug): bool {
    // Reserved words that cannot be used as slugs
    $reservedWords = ['admin', 'api', 'uploads', 'assets', 'css', 'js', 'images', 'display', 'index', 'login', 'logout'];
    
    // Only allow letters, numbers, and hyphens, but not at start or end
    if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $slug) || strlen($slug) < 3 || strlen($slug) > 100) {
        return false;
    }
    
    // Check if slug is a reserved word
    if (in_array($slug, $reservedWords)) {
        return false;
    }
    
    return true;
}

// File Upload Functions
function validateFileUpload(array $file, ?int $maxSize = null): array {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Keine Datei hochgeladen';
        return $errors;
    }
    
    // Check file size if maxSize is provided
    if ($maxSize !== null && $file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1024 / 1024, 1);
        $fileSizeMB = round($file['size'] / 1024 / 1024, 1);
        $errors[] = "Datei zu groß: {$fileSizeMB}MB (Maximum: {$maxSizeMB}MB)";
    }
    
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Also check file extension for HEIC/HEIF files (MIME detection can be unreliable)
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isHeicFile = in_array($fileExtension, ['heic', 'heif']);
    
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES) && !$isHeicFile) {
        // Convert MIME types to file extensions for better user understanding
        $allowedExtensions = [];
        foreach (UPLOAD_ALLOWED_TYPES as $mime) {
            switch ($mime) {
                case 'image/jpeg': $allowedExtensions[] = 'JPG'; break;
                case 'image/png': $allowedExtensions[] = 'PNG'; break;
                case 'image/gif': $allowedExtensions[] = 'GIF'; break;
                case 'image/webp': $allowedExtensions[] = 'WebP'; break;
                case 'image/heic': $allowedExtensions[] = 'HEIC'; break;
                case 'image/heif': $allowedExtensions[] = 'HEIF'; break;
            }
        }
        $errors[] = 'Dateityp nicht erlaubt. Erlaubt: ' . implode(', ', $allowedExtensions);
    }
    
    // Check if it's actually an image
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        // Special check for HEIC/HEIF files (both MIME type and file extension)
        if (in_array($mimeType, ['image/heic', 'image/heif']) || $isHeicFile) {
            // HEIC files might not be recognized by getimagesize
            // We'll allow them and let the upload process handle conversion
        } else {
            $errors[] = 'Datei ist kein gültiges Bild';
        }
    }
    
    return $errors;
}

function generateUniqueFilename(string $originalName): string {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

function calculateFileHash(string $filePath): string|false {
    if (!file_exists($filePath)) {
        return false;
    }
    return hash_file('sha256', $filePath);
}

function isDuplicatePhoto(string $fileHash, int $eventId): bool {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Check for duplicates regardless of is_active status
        $stmt = $conn->prepare("SELECT COUNT(*) FROM photos WHERE file_hash = ? AND event_id = ?");
        $stmt->execute([$fileHash, $eventId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking for duplicate photo: " . $e->getMessage());
        return false;
    }
}

function getEventUploadPaths(string $eventSlug): array {
    $photosPath = DATA_PATH . '/' . $eventSlug . '/photos';
    $logosPath = DATA_PATH . '/' . $eventSlug . '/logos';
    $thumbnailsPath = DATA_PATH . '/' . $eventSlug . '/thumbnails';
    $photosUrl = APP_URL . '/data/' . $eventSlug . '/photos';
    $logosUrl = APP_URL . '/data/' . $eventSlug . '/logos';
    $thumbnailsUrl = APP_URL . '/data/' . $eventSlug . '/thumbnails';
    
    return [
        'photos_path' => $photosPath,
        'logos_path' => $logosPath,
        'thumbnails_path' => $thumbnailsPath,
        'photos_url' => $photosUrl,
        'logos_url' => $logosUrl,
        'thumbnails_url' => $thumbnailsUrl
    ];
}

function ensureEventDirectories(string $eventSlug): void {
    $paths = getEventUploadPaths($eventSlug);
    
    if (!is_dir($paths['photos_path'])) {
        mkdir($paths['photos_path'], 0755, true);
    }
    if (!is_dir($paths['logos_path'])) {
        mkdir($paths['logos_path'], 0755, true);
    }
    if (!is_dir($paths['thumbnails_path'])) {
        mkdir($paths['thumbnails_path'], 0755, true);
    }
}

function formatBytes(int|float $bytes, int $precision = 2): string {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes >= BYTES_PER_KB && $i < count($units) - 1; $i++) {
        $bytes /= BYTES_PER_KB;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

// Image Processing Functions
/**
 * Resize an image to fit within maximum dimensions
 * Wrapper function for backward compatibility - delegates to ImageProcessor
 *
 * @param string $sourcePath Source image path
 * @param string $destinationPath Destination path
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @param int $quality Image quality
 * @return bool Success status
 */
function resizeImage(string $sourcePath, string $destinationPath, int $maxWidth = IMAGE_MAX_WIDTH, int $maxHeight = IMAGE_MAX_HEIGHT, int $quality = IMAGE_QUALITY_MEDIUM): bool {
    try {
        return ImageProcessor::resize($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality);
    } catch (ImageProcessingException $e) {
        error_log("Image resize error: " . $e->getMessage());
        return false;
    }
}

function autoRotateImage(string $imagePath): bool {
    if (!function_exists('exif_read_data')) {
        return false;
    }
    
    $exif = @exif_read_data($imagePath);
    if (!$exif || !isset($exif['Orientation'])) {
        return false;
    }
    
    $orientation = $exif['Orientation'];
    if ($orientation == EXIF_ORIENTATION_NORMAL) {
        return false;
    }
    
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Check if MIME type is supported for rotation
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        return false;
    }
    
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($imagePath);
            break;
        default:
            return false;
    }
    
    if ($sourceImage === false) {
        return false;
    }
    
    $rotatedImage = false;

    switch ($orientation) {
        case 2:
            $rotatedImage = imageflip($sourceImage, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $rotatedImage = imagerotate($sourceImage, ROTATION_ANGLE_180, 0);
            break;
        case 4:
            $rotatedImage = imageflip($sourceImage, IMG_FLIP_VERTICAL);
            break;
        case 5:
            $rotatedImage = imagerotate($sourceImage, ROTATION_ANGLE_90, 0);
            $rotatedImage = imageflip($rotatedImage, IMG_FLIP_HORIZONTAL);
            break;
        case 6:
            $rotatedImage = imagerotate($sourceImage, -ROTATION_ANGLE_90, 0);
            break;
        case 7:
            $rotatedImage = imagerotate($sourceImage, ROTATION_ANGLE_90, 0);
            $rotatedImage = imageflip($rotatedImage, IMG_FLIP_VERTICAL);
            break;
        case 8:
            $rotatedImage = imagerotate($sourceImage, ROTATION_ANGLE_90, 0);
            break;
    }

    if ($rotatedImage === false) {
        imagedestroy($sourceImage);
        return false;
    }

    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($rotatedImage, $imagePath, IMAGE_QUALITY_HIGH);
            break;
        case 'image/png':
            $result = imagepng($rotatedImage, $imagePath, 9);
            break;
        case 'image/gif':
            $result = imagegif($rotatedImage, $imagePath);
            break;
        case 'image/webp':
            $result = imagewebp($rotatedImage, $imagePath, IMAGE_QUALITY_HIGH);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($rotatedImage);
    
    return $result;
}

/**
 * Rotate an image by a specific angle (90, 180, 270 degrees)
 * @param string $imagePath Path to the image file
 * @param int $angle Rotation angle (90, 180, 270)
 * @return bool Success status
 */
function rotateImage(string $imagePath, int $angle): bool {
    if (!in_array($angle, [ROTATION_ANGLE_90, ROTATION_ANGLE_180, ROTATION_ANGLE_270])) {
        return false;
    }
    
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Check if MIME type is supported for rotation
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        return false;
    }
    
    $sourceImage = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($imagePath);
            break;
        default:
            return false;
    }
    
    if ($sourceImage === false) {
        return false;
    }
    
    // Rotate the image
    $rotatedImage = imagerotate($sourceImage, $angle, 0);
    
    if ($rotatedImage === false) {
        imagedestroy($sourceImage);
        return false;
    }
    
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($rotatedImage, $imagePath, IMAGE_QUALITY_HIGH);
            break;
        case 'image/png':
            $result = imagepng($rotatedImage, $imagePath, 9);
            break;
        case 'image/gif':
            $result = imagegif($rotatedImage, $imagePath);
            break;
        case 'image/webp':
            $result = imagewebp($rotatedImage, $imagePath, IMAGE_QUALITY_HIGH);
            break;
    }

    imagedestroy($sourceImage);
    imagedestroy($rotatedImage);

    return $result;
}

/**
 * Rotate an image and regenerate all variants (resized, thumbnail)
 * @param string $originalPath Path to the original image file
 * @param int $angle Rotation angle (90, 180, 270)
 * @param string|null $resizedPath Path to the resized image file (optional)
 * @param string|null $thumbnailPath Path to the thumbnail file (optional)
 * @return array Results of rotation operations
 */
function rotateImageWithVariants(string $originalPath, int $angle, ?string $resizedPath = null, ?string $thumbnailPath = null): array {
    $results = [
        'original' => false,
        'resized' => false,
        'thumbnail' => false
    ];
    
    // Rotate original image
    $results['original'] = rotateImage($originalPath, $angle);
    
    if (!$results['original']) {
        return $results;
    }
    
    // Regenerate resized image if path provided and file exists
    if ($resizedPath && file_exists($resizedPath)) {
        $results['resized'] = resizeImage($originalPath, $resizedPath, IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT, IMAGE_QUALITY_MEDIUM);
    }

    // Regenerate thumbnail if path provided and file exists
    if ($thumbnailPath && file_exists($thumbnailPath)) {
        $results['thumbnail'] = createThumbnail($originalPath, $thumbnailPath, THUMBNAIL_MAX_WIDTH, THUMBNAIL_MAX_HEIGHT, THUMBNAIL_QUALITY);
    }
    
    return $results;
}

/**
 * Create a thumbnail from an image
 * Wrapper function for backward compatibility - delegates to ImageProcessor
 *
 * @param string $sourcePath Source image path
 * @param string $destinationPath Destination path
 * @param int $maxWidth Maximum width
 * @param int $maxHeight Maximum height
 * @param int $quality Image quality
 * @return bool Success status
 */
function createThumbnail(string $sourcePath, string $destinationPath, int $maxWidth = THUMBNAIL_MAX_WIDTH, int $maxHeight = THUMBNAIL_MAX_HEIGHT, int $quality = THUMBNAIL_QUALITY): bool {
    try {
        return ImageProcessor::createThumbnail($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality);
    } catch (ImageProcessingException $e) {
        error_log("Thumbnail creation error: " . $e->getMessage());
        return false;
    }
}

// Response Functions
function sendJSONResponse(array $data, int $statusCode = 200): never {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendErrorResponse(string $message, int $statusCode = 400): never {
    sendJSONResponse(['error' => $message], $statusCode);
}

function sendSuccessResponse(array|null $data = null, string $message = 'Success'): never {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJSONResponse($response);
}

// Utility Functions
function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


// QR Code Functions
function generateQRCode(string $data, int $size = QR_CODE_DEFAULT_SIZE): string|false {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Temporarily suppress deprecation warnings for QR code generation
        $oldErrorReporting = error_reporting();
        error_reporting($oldErrorReporting & ~E_DEPRECATED);
        
        $qrCode = \Endroid\QrCode\QrCode::create($data)
            ->setSize($size)
            ->setMargin(QR_CODE_MARGIN)
            ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
            ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));
        
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);
        
        // Restore original error reporting
        error_reporting($oldErrorReporting);
        
        return $result->getString();
    } catch (Exception $e) {
        error_log("QR Code generation error: " . $e->getMessage());
        return false;
    }
}

function generateQRCodeDataUri(string $data, int $size = QR_CODE_DEFAULT_SIZE): string|false {
    $qrCodeString = generateQRCode($data, $size);
    if ($qrCodeString === false) {
        return false;
    }
    
    return 'data:image/png;base64,' . base64_encode($qrCodeString);
}

// Session Functions
function setUserSession(?string $username = null): void {
    if ($username) {
        $_SESSION['username'] = $username;
    }
    $_SESSION['last_activity'] = time();
}

function getUserSession(): ?string {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return null;
    }
    return $_SESSION['username'] ?? null;
}

// Display Configuration Functions
function getDisplayConfiguration(array $event, array $getParams = []): array {
    // Load display configuration from event settings, but allow GET parameters to override
    $displayCount = $getParams['display_count'] ?? $event['display_count'] ?? DEFAULT_DISPLAY_COUNT;
    $displayMode = $getParams['display_mode'] ?? $event['display_mode'] ?? DEFAULT_DISPLAY_MODE;
    $displayInterval = $getParams['display_interval'] ?? $event['display_interval'] ?? DEFAULT_DISPLAY_INTERVAL;
    $layoutType = $event['layout_type'] ?? 'grid';
    $gridColumns = $event['grid_columns'] ?? DEFAULT_GRID_COLUMNS;

    // Override logo display with GET parameter if provided
    $showLogo = isset($getParams['show_logo']) ? (bool)$getParams['show_logo'] : ($event['show_logo'] ?? false);

    // Override QR code display with GET parameter if provided
    $showQrCode = isset($getParams['show_qr_code']) ? (bool)$getParams['show_qr_code'] : ($event['show_qr_code'] ?? false);

    // Validate and convert parameters to proper types
    $displayCount = max(1, min(50, (int)$displayCount));
    $displayInterval = max(3, min(60, (int)$displayInterval));
    $gridColumns = max(2, min(6, (int)$gridColumns));

    if (!in_array($displayMode, ['random', 'newest', 'chronological'])) {
        $displayMode = DEFAULT_DISPLAY_MODE;
    }

    if (!in_array($layoutType, ['single', 'grid'])) {
        $layoutType = 'grid';
    }

    return [
        'displayCount' => $displayCount,
        'displayMode' => $displayMode,
        'displayInterval' => $displayInterval,
        'layoutType' => $layoutType,
        'gridColumns' => $gridColumns,
        'showLogo' => $showLogo,
        'showQrCode' => $showQrCode
    ];
}

?>
