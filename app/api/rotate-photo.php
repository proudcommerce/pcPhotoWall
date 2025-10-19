<?php
session_start();
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    sendErrorResponse('Nicht autorisiert');
}

// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    sendErrorResponse('Ungültiger CSRF-Token');
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Nur POST-Requests erlaubt');
}

// Get photo ID and rotation angle
$photoId = (int)($_POST['photo_id'] ?? 0);
$angle = (int)($_POST['angle'] ?? 0);

if ($photoId <= 0) {
    sendErrorResponse('Ungültige Foto-ID');
}

if (!in_array($angle, [90, 180, 270])) {
    sendErrorResponse('Ungültiger Rotationswinkel. Nur 90°, 180° und 270° sind erlaubt.');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if photo exists and get event info
    $stmt = $conn->prepare("
        SELECT p.id, p.filename, p.thumbnail_filename, p.event_id, e.event_slug 
        FROM photos p 
        JOIN events e ON p.event_id = e.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    
    if (!$photo) {
        sendErrorResponse('Foto nicht gefunden');
    }
    
    // Get event-specific upload paths
    $uploadPaths = getEventUploadPaths($photo['event_slug']);
    
    // Define file paths
    $originalPath = $uploadPaths['photos_path'] . '/' . $photo['filename'];
    $resizedPath = $uploadPaths['photos_path'] . '/resized_' . $photo['filename'];
    $thumbnailPath = null;
    
    if ($photo['thumbnail_filename']) {
        $thumbnailPath = $uploadPaths['thumbnails_path'] . '/' . $photo['thumbnail_filename'];
    }
    
    // Check if original file exists
    if (!file_exists($originalPath)) {
        sendErrorResponse('Originaldatei nicht gefunden');
    }
    
    // Use the new function that rotates original and regenerates variants
    $rotationResults = rotateImageWithVariants($originalPath, $angle, $resizedPath, $thumbnailPath);
    
    // Check if at least the original was rotated successfully
    if (!$rotationResults['original']) {
        sendErrorResponse('Fehler beim Rotieren des Fotos');
    }
    
    // Log the rotation
    error_log("Photo rotated: ID=$photoId, angle=$angle, results=" . json_encode($rotationResults));
    
    // Create detailed success message
    $message = "Foto um {$angle}° gedreht";
    $details = [];
    
    if ($rotationResults['original']) {
        $details[] = "Original";
    }
    if ($rotationResults['resized']) {
        $details[] = "Resized";
    }
    if ($rotationResults['thumbnail']) {
        $details[] = "Thumbnail";
    }
    
    if (!empty($details)) {
        $message .= " (" . implode(', ', $details) . " neu generiert)";
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'photo_id' => $photoId,
        'angle' => $angle,
        'rotation_results' => $rotationResults,
        'regenerated_variants' => $details
    ]);
    
} catch (Exception $e) {
    error_log("Rotate photo error: " . $e->getMessage());
    sendErrorResponse('Fehler beim Rotieren des Fotos: ' . $e->getMessage());
}
?>
