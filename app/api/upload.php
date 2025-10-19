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

    // Check if upload is enabled for this event
    $uploadEnabled = $event['upload_enabled'] ?? 1;
    if (!$uploadEnabled) {
        sendErrorResponse('Upload ist für dieses Event deaktiviert');
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
    
    // Get MIME type for processing
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadPath);
    finfo_close($finfo);
    
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
                
                // Auto-orient HEIC image based on EXIF data before conversion
                $imagick->autoOrient();
                
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
            $command = "magick convert " . escapeshellarg($uploadPath) . " -auto-orient -quality 90 " . escapeshellarg($jpegPath) . " 2>&1";
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($jpegPath)) {
                $finalPath = $jpegPath;
                $filename = $jpegFilename;
                $mimeType = 'image/jpeg';
                unlink($uploadPath);
            } else {
                // Clean up failed upload
                unlink($uploadPath);
                error_log("HEIC conversion failed: " . implode(", ", $output));
                sendErrorResponse('HEIC/HEIF-Konvertierung fehlgeschlagen. Bitte lade eine JPEG-Datei hoch oder verwende ein anderes Bildformat.');
            }
        }
    }
    
    // Auto-rotate image based on EXIF orientation data (only for supported formats)
    // HEIC/HEIF rotation is handled during conversion above
    if (!in_array($mimeType, ['image/heic', 'image/heif'])) {
        autoRotateImage($finalPath);
    }
    
    // Extract GPS coordinates from final image (after potential HEIC conversion and rotation)
    $gpsCoords = GeoUtils::extractGPSCoordinates($finalPath);
    
    // GPS validation
    $gpsValidationFailed = false;
    if ($gpsValidationRequired) {
        if (!$gpsCoords) {
            $gpsValidationFailed = true;
        } else {
            // Validate coordinates
            if (!GeoUtils::validateCoordinates($gpsCoords['latitude'], $gpsCoords['longitude'])) {
                $gpsValidationFailed = true;
            } else {
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
    $resizedFilename = 'resized_' . $filename;
    $resizedPath = $uploadPaths['photos_path'] . '/' . $resizedFilename;
    $resizeSuccess = resizeImage($finalPath, $resizedPath, 1920, 1080, 85);
    
    // Create thumbnail
    $thumbnailFilename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
    $thumbnailPath = $uploadPaths['thumbnails_path'] . '/' . $thumbnailFilename;
    $thumbnailCreated = createThumbnail($finalPath, $thumbnailPath, 300, 300, 85);
    
    // Get file info from final path (after potential conversion)
    $fileSize = filesize($finalPath);
    // MIME type is already set correctly during processing above
    
    // Save to database
    $stmt = $conn->prepare("
        INSERT INTO photos (event_id, filename, original_name, username, latitude, longitude, distance_meters, file_size, mime_type, file_hash, thumbnail_filename, resized_filename, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Set is_active based on moderation setting and GPS validation
    // If GPS validation failed, always require moderation regardless of event setting
    $isActive = ($moderationRequired || $gpsValidationFailed) ? 0 : 1;
    
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
        $resizeSuccess ? $resizedFilename : null,
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
    
    // Set appropriate message based on moderation status and GPS validation
    $message = 'Foto erfolgreich hochgeladen!';
    if ($gpsValidationFailed) {
        $message = 'Foto erfolgreich hochgeladen! Es wird nach der Freigabe angezeigt, da keine GPS-Koordinaten gefunden wurden.';
    } elseif ($moderationRequired) {
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
