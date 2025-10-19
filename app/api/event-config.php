<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Get event slug
$eventSlug = $_GET['event_slug'] ?? '';

if (empty($eventSlug)) {
    sendErrorResponse('Event-Slug erforderlich');
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get event configuration
    $stmt = $conn->prepare("
        SELECT id, name, event_slug, max_upload_size, display_mode, display_count, 
               display_interval, show_username, show_date, overlay_opacity, 
               gps_validation_required, note, is_active, show_logo, show_qr_code,
               show_display_link, show_gallery_link
        FROM events 
        WHERE event_slug = ? AND is_active = 1
    ");
    $stmt->execute([$eventSlug]);
    $event = $stmt->fetch();
    
    if (!$event) {
        sendErrorResponse('Event nicht gefunden oder nicht aktiv');
    }
    
    sendSuccessResponse('Event-Konfiguration geladen', [
        'event' => $event
    ]);
    
} catch (Exception $e) {
    sendErrorResponse('Fehler beim Laden der Event-Konfiguration: ' . $e->getMessage());
}
?>
