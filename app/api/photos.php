<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/geo.php';
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get event slug from query parameter
$eventSlug = $_GET['event_slug'] ?? '';
if (empty($eventSlug)) {
    sendErrorResponse('Ungültiger Event-Slug');
}

// Get event by slug
$event = getEventBySlug($eventSlug);
if (!$event || !$event['is_active']) {
    sendErrorResponse('Event nicht gefunden oder nicht aktiv');
}

$eventId = $event['id'];

// Get display configuration from event (will be set after event is loaded)
$displayCount = null;
$displayMode = DEFAULT_DISPLAY_MODE;

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Set display configuration from event, but allow GET parameters to override
    $displayMode = $_GET['display_mode'] ?? $event['display_mode'] ?? DEFAULT_DISPLAY_MODE;
    $displayCount = $_GET['display_count'] ?? $event['display_count'] ?? null;
    
    // Validate display mode
    if (!in_array($displayMode, ['random', 'newest', 'chronological'])) {
        $displayMode = DEFAULT_DISPLAY_MODE;
    }
    
    // Validate display count
    if ($displayCount !== null) {
        $displayCount = max(1, min(50, (int)$displayCount));
    }
    
    // Build query based on display mode
    $orderBy = '';
    switch ($displayMode) {
        case 'newest':
            $orderBy = 'ORDER BY uploaded_at DESC';
            break;
        case 'chronological':
            $orderBy = 'ORDER BY uploaded_at ASC';
            break;
        case 'random':
        default:
            $orderBy = 'ORDER BY RAND()';
            break;
    }
    
    // Get photos (only active ones) - load ALL photos for slideshow
    $sql = "SELECT id, filename, original_name, username, latitude, longitude, 
                   distance_meters, uploaded_at, file_size, mime_type, resized_filename
            FROM photos 
            WHERE event_id = ? AND is_active = 1
            {$orderBy}";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$eventId]);
    $photos = $stmt->fetchAll();

    // Get event-specific upload paths (event already loaded above)
    $uploadPaths = getEventUploadPaths($eventSlug);
    
    // Add full URLs to photos
    foreach ($photos as &$photo) {
        // Use resized version for display if available, otherwise fallback to original
        $displayFilename = !empty($photo['resized_filename']) ? $photo['resized_filename'] : $photo['filename'];
        $photo['url'] = $uploadPaths['photos_url'] . '/' . $displayFilename;
        $photo['original_url'] = $uploadPaths['photos_url'] . '/' . $photo['filename'];
        $photo['uploaded_at_formatted'] = date('d.m.Y H:i', strtotime($photo['uploaded_at']));
        $photo['file_size_formatted'] = formatBytes($photo['file_size']);
        $photo['distance_formatted'] = GeoUtils::formatDistance($photo['distance_meters']);
    }
    
    // Get total photo count for this event (only active ones)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM photos WHERE event_id = ? AND is_active = 1");
    $stmt->execute([$eventId]);
    $totalCount = $stmt->fetch()['total'];
    
    // Return response
    sendJSONResponse([
        'success' => true,
        'event' => [
            'id' => $event['id'],
            'name' => $event['name'],
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'radius_meters' => $event['radius_meters'],
            'show_username' => (bool)$event['show_username'],
            'show_date' => (bool)$event['show_date'],
            'overlay_opacity' => (float)$event['overlay_opacity'],
            'gps_validation_required' => (bool)$event['gps_validation_required'],
            'show_logo' => isset($_GET['show_logo']) ? (bool)$_GET['show_logo'] : (bool)$event['show_logo']
        ],
        'photos' => $photos,
        'pagination' => [
            'total' => $totalCount,
            'displayed' => count($photos),
            'mode' => $displayMode
        ],
        'display_config' => [
            'max_photos' => $displayCount,
            'unlimited' => $displayCount === null,
            'mode' => $displayMode,
            'interval' => $_GET['display_interval'] ?? $event['display_interval'],
            'slideshow_enabled' => true
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Photos API error: " . $e->getMessage());
    sendErrorResponse('Server-Fehler beim Laden der Fotos: ' . $e->getMessage(), 500);
}
?>
