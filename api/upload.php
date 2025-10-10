<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/geo.php';
require_once '../config/database.php';

// Start output buffering to prevent any output before JSON
ob_start();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Nur POST-Requests erlaubt', 405);
}

// Check if file was uploaded
if (!isset($_FILES['photo'])) {
    sendErrorResponse('Keine Datei in $_FILES gefunden');
}

if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Datei zu groß (Server-Limit)',
        UPLOAD_ERR_FORM_SIZE => 'Datei zu groß (HTML-Formular-Limit)',
        UPLOAD_ERR_PARTIAL => 'Upload unvollständig - bitte erneut versuchen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Server-Fehler: Temporärer Ordner fehlt',
        UPLOAD_ERR_CANT_WRITE => 'Server-Fehler: Schreibfehler',
        UPLOAD_ERR_EXTENSION => 'Upload durch Server-Extension gestoppt'
    ];
    $errorMsg = $errorMessages[$_FILES['photo']['error']] ?? 'Unbekannter Fehler: ' . $_FILES['photo']['error'];
    sendErrorResponse('Upload-Fehler: ' . $errorMsg);
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    sendErrorResponse('Ungültiger CSRF-Token', 403);
}

// Get event slug
$eventSlug = $_POST['event_slug'] ?? '';
if (empty($eventSlug)) {
    sendErrorResponse('Ungültiger Event-Slug');
}

// Get event by slug
$event = getEventBySlug($eventSlug);
if (!$event || !$event['is_active']) {
    sendErrorResponse('Event nicht gefunden oder inaktiv');
}

$eventId = $event['id'];

// Get username (optional)
$username = sanitizeInput($_POST['username'] ?? '');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND is_active = 1");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        sendErrorResponse('Event nicht gefunden oder nicht aktiv');
    }
    
    // Get GPS validation setting from event
    $gpsValidationRequired = (bool)$event['gps_validation_required'];
    
    // Get moderation setting from event
    $moderationRequired = (bool)$event['moderation_required'];
    
    // Validate file upload with event-specific max size
    $file = $_FILES['photo'];
    $maxUploadSize = $event['max_upload_size'] ?? 10485760; // Default to 10MB
    $errors = validateFileUpload($file, $maxUploadSize);
    
    if (!empty($errors)) {
        sendErrorResponse(implode(', ', $errors));
    }
    
    // Get event-specific upload paths
    $uploadPaths = getEventUploadPaths($event['event_slug']);
    ensureEventDirectories($event['event_slug']);
    
    // Generate unique filename
    $filename = generateUniqueFilename($file['name']);
    $uploadPath = $uploadPaths['photos_path'] . '/' . $filename;
    
    // Move uploaded file temporarily for processing
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        sendErrorResponse('Fehler beim Speichern der Datei');
    }
    
    // Calculate file hash for duplicate detection FIRST
    $fileHash = calculateFileHash($uploadPath);
    if (!$fileHash) {
        unlink($uploadPath); // Clean up
        sendErrorResponse('Fehler beim Berechnen des Datei-Hash');
    }
    
    // Check for duplicates FIRST - before any other processing
    if (isDuplicatePhoto($fileHash, $eventId)) {
        // Remove the uploaded file since it's a duplicate
        unlink($uploadPath);
        sendErrorResponse('Dieses Foto wurde bereits hochgeladen');
    }
    
    // GPS coordinates will be extracted after potential HEIC conversion
    $gpsCoords = null;
    $distance = null;
    
    // Convert HEIC to JPEG if needed
    $finalPath = $uploadPath;
    $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $isHeicFile = in_array($fileExtension, ['heic', 'heif']);
    
    if (in_array($mimeType, ['image/heic', 'image/heif']) || $isHeicFile) {
        $jpegFilename = pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $jpegPath = $uploadPaths['photos_path'] . '/' . $jpegFilename;
        
        // Try to convert HEIC to JPEG using ImageMagick PHP extension first
        if (extension_loaded('imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->readImage($uploadPath);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                $imagick->writeImage($jpegPath);
                $imagick->clear();
                $imagick->destroy();
                
                if (file_exists($jpegPath)) {
                    $finalPath = $jpegPath;
                    $filename = $jpegFilename;
                    $mimeType = 'image/jpeg';
                    // Remove original HEIC file
                    unlink($uploadPath);
                } else {
                    throw new Exception('ImageMagick conversion failed');
                }
            } catch (Exception $e) {
                error_log("ImageMagick conversion failed: " . $e->getMessage());
                // Fallback to command line conversion
                $command = "magick convert " . escapeshellarg($uploadPath) . " -quality 90 " . escapeshellarg($jpegPath) . " 2>&1";
                $output = [];
                $returnCode = 0;
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($jpegPath)) {
                    $finalPath = $jpegPath;
                    $filename = $jpegFilename;
                    $mimeType = 'image/jpeg';
                    unlink($uploadPath);
                } else {
                    throw new Exception('Both ImageMagick and command line conversion failed');
                }
            }
        } else {
            // Fallback to command line conversion if PHP extension not available
            $command = "magick convert " . escapeshellarg($uploadPath) . " -quality 90 " . escapeshellarg($jpegPath) . " 2>&1";
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($jpegPath)) {
                $finalPath = $jpegPath;
                $filename = $jpegFilename;
                $mimeType = 'image/jpeg';
                unlink($uploadPath);
            } else {
                // If conversion fails, create a placeholder JPEG
                $placeholderPath = $uploadPaths['photos_path'] . '/placeholder.jpg';
                if (!file_exists($placeholderPath)) {
                    $image = imagecreate(100, 100);
                    $bg = imagecolorallocate($image, 200, 200, 200);
                    $text = imagecolorallocate($image, 100, 100, 100);
                    imagestring($image, 3, 20, 40, 'HEIC', $text);
                    imagejpeg($image, $placeholderPath, 90);
                    imagedestroy($image);
                }
                copy($placeholderPath, $jpegPath);
                $finalPath = $jpegPath;
                $filename = $jpegFilename;
                $mimeType = 'image/jpeg';
                unlink($uploadPath);
            }
        }
    }
    
    // Extract GPS coordinates from final image (after potential HEIC conversion)
    $gpsCoords = GeoUtils::extractGPSCoordinates($finalPath);
    
    // GPS validation
    if ($gpsValidationRequired) {
        if (!$gpsCoords) {
            sendErrorResponse('Keine GPS-Koordinaten im Foto gefunden. Bitte stelle sicher, dass GPS aktiviert ist.');
        }
        
        // Validate coordinates
        if (!GeoUtils::validateCoordinates($gpsCoords['latitude'], $gpsCoords['longitude'])) {
            sendErrorResponse('Ungültige GPS-Koordinaten im Foto');
        }
        
        // Check if event has coordinates for validation
        if ($event['latitude'] !== null && $event['longitude'] !== null) {
            // Check if photo is within event radius
            $distance = GeoUtils::calculateDistance(
                $event['latitude'],
                $event['longitude'],
                $gpsCoords['latitude'],
                $gpsCoords['longitude']
            );
            
            if ($distance > $event['radius_meters']) {
                $formattedDistance = GeoUtils::formatDistance($distance);
                sendErrorResponse("Foto wurde außerhalb des Event-Radius aufgenommen. Entfernung: {$formattedDistance} (max. {$event['radius_meters']}m)");
            }
        }
    } else {
        // GPS validation is optional - calculate distance if GPS coordinates are available
        if ($gpsCoords && GeoUtils::validateCoordinates($gpsCoords['latitude'], $gpsCoords['longitude'])) {
            if ($event['latitude'] !== null && $event['longitude'] !== null) {
                $distance = GeoUtils::calculateDistance(
                    $event['latitude'],
                    $event['longitude'],
                    $gpsCoords['latitude'],
                    $gpsCoords['longitude']
                );
            }
        }
    }
    
    // Resize image for better performance
    $resizedPath = $uploadPaths['photos_path'] . '/resized_' . $filename;
    if (!resizeImage($finalPath, $resizedPath, 1920, 1080, 85)) {
        // If resize fails, keep original
        $resizedPath = $finalPath;
    }
    
    // Create thumbnail
    $thumbnailFilename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
    $thumbnailPath = $uploadPaths['thumbnails_path'] . '/' . $thumbnailFilename;
    $thumbnailCreated = createThumbnail($finalPath, $thumbnailPath, 300, 300, 85);
    
    // Get file info from final path (after potential conversion)
    $fileSize = filesize($finalPath);
    $mimeType = mime_content_type($finalPath);
    
    // Save to database
    $stmt = $conn->prepare("
        INSERT INTO photos (event_id, filename, original_name, username, latitude, longitude, distance_meters, file_size, mime_type, file_hash, thumbnail_filename, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Set is_active based on moderation setting
    $isActive = $moderationRequired ? 0 : 1;
    
    $stmt->execute([
        $eventId,
        $filename,
        $file['name'],
        $username ?: null,
        $gpsCoords ? $gpsCoords['latitude'] : null,
        $gpsCoords ? $gpsCoords['longitude'] : null,
        $distance ? round($distance, 2) : null,
        $fileSize,
        $mimeType,
        $fileHash,
        $thumbnailCreated ? $thumbnailFilename : null,
        $isActive
    ]);
    
    $photoId = $conn->lastInsertId();
    
    // Set username in session if provided
    if ($username) {
        setUserSession($username);
    }
    
    // Return success response
    $response = [
        'photo_id' => $photoId,
        'filename' => $filename,
        'moderation_required' => $moderationRequired,
        'is_active' => $isActive
    ];
    
    // Set appropriate message based on moderation status
    $message = 'Foto erfolgreich hochgeladen!';
    if ($moderationRequired) {
        $message = 'Foto erfolgreich hochgeladen! Es wird nach der Freigabe angezeigt.';
    }
    
    if ($distance !== null) {
        $response['distance'] = round($distance, 2);
        $response['formatted_distance'] = GeoUtils::formatDistance($distance);
    }
    
    if ($gpsCoords) {
        $response['location'] = [
            'latitude' => $gpsCoords['latitude'],
            'longitude' => $gpsCoords['longitude']
        ];
    }
    
    sendSuccessResponse($response, $message);
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    sendErrorResponse('Server-Fehler beim Upload: ' . $e->getMessage(), 500);
}
?>
