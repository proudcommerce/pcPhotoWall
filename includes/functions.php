<?php
require_once __DIR__ . '/../config/config.php';

// Version Functions
function getCurrentVersion() {
    $changelogPath = __DIR__ . '/../CHANGELOG.md';
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
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Event Hash Functions
function generateEventHash() {
    return md5(uniqid(rand(), true) . microtime(true));
}

function getEventByHash($hash) {
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

function getEventHashById($eventId) {
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
function generateSlug($name) {
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

function isSlugUnique($slug, $excludeId = null) {
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

function generateUniqueSlug($name, $excludeId = null) {
    $baseSlug = generateSlug($name);
    $slug = $baseSlug;
    $counter = 1;
    
    while (!isSlugUnique($slug, $excludeId)) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

function getEventBySlug($slug) {
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

function validateSlug($slug) {
    // Reserved words that cannot be used as slugs
    $reservedWords = ['admin', 'api', 'uploads', 'assets', 'css', 'js', 'images', 'display', 'index', 'login', 'logout'];
    
    // Only allow letters, numbers, and hyphens
    if (!preg_match('/^[a-z0-9-]+$/', $slug) || strlen($slug) < 3 || strlen($slug) > 100) {
        return false;
    }
    
    // Check if slug is a reserved word
    if (in_array($slug, $reservedWords)) {
        return false;
    }
    
    return true;
}

// File Upload Functions
function validateFileUpload($file, $maxSize = null) {
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
    
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
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

function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

function calculateFileHash($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    return hash_file('sha256', $filePath);
}

function isDuplicatePhoto($fileHash, $eventId) {
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

function getEventUploadPaths($eventSlug) {
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

function ensureEventDirectories($eventSlug) {
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

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Image Processing Functions
function resizeImage($sourcePath, $destinationPath, $maxWidth = 1920, $maxHeight = 1080, $quality = 85) {
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // Create image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        case 'image/heic':
        case 'image/heif':
            // For HEIC files, we'll convert to JPEG first
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        default:
            return false;
    }
    
    if ($sourceImage === false) {
        return false;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Save image
    $result = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($newImage, $destinationPath, $quality);
            break;
        case 'image/png':
            $result = imagepng($newImage, $destinationPath, 9);
            break;
        case 'image/gif':
            $result = imagegif($newImage, $destinationPath);
            break;
        case 'image/webp':
            $result = imagewebp($newImage, $destinationPath, $quality);
            break;
    }
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

function createThumbnail($sourcePath, $destinationPath, $maxWidth = 300, $maxHeight = 300, $quality = 85) {
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];
    $mimeType = $imageInfo['mime'];
    
    // Calculate new dimensions maintaining aspect ratio
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);
    
    // Create image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        case 'image/heic':
        case 'image/heif':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        default:
            return false;
    }
    
    if ($sourceImage === false) {
        return false;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Save thumbnail as JPEG for consistency and smaller file size
    $result = imagejpeg($newImage, $destinationPath, $quality);
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

// Response Functions
function sendJSONResponse($data, $statusCode = 200) {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendErrorResponse($message, $statusCode = 400) {
    sendJSONResponse(['error' => $message], $statusCode);
}

function sendSuccessResponse($data = null, $message = 'Success') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJSONResponse($response);
}

// Utility Functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


// Session Functions
function setUserSession($username = null) {
    if ($username) {
        $_SESSION['username'] = $username;
    }
    $_SESSION['last_activity'] = time();
}

function getUserSession() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        return null;
    }
    return $_SESSION['username'] ?? null;
}

?>
