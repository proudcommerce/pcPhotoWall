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

// Get photo ID and new status
$photoId = (int)($_POST['photo_id'] ?? 0);
$isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;

if ($photoId <= 0) {
    sendErrorResponse('Ungültige Foto-ID');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if photo exists and get event info
    $stmt = $conn->prepare("
        SELECT p.id, p.event_id, e.event_slug 
        FROM photos p 
        JOIN events e ON p.event_id = e.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    
    if (!$photo) {
        sendErrorResponse('Foto nicht gefunden');
    }
    
    // Update photo status (with additional logging)
    $updateStmt = $conn->prepare("UPDATE photos SET is_active = ? WHERE id = ?");
    $result = $updateStmt->execute([$isActive, $photoId]);
    $affectedRows = $updateStmt->rowCount();
    
    error_log("Toggle photo status: ID=$photoId, newStatus=$isActive, affectedRows=$affectedRows");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $isActive ? 'Foto aktiviert' : 'Foto deaktiviert',
        'photo_id' => $photoId,
        'is_active' => $isActive
    ]);
    
} catch (Exception $e) {
    error_log("Toggle photo status error: " . $e->getMessage());
    sendErrorResponse('Fehler beim Aktualisieren des Foto-Status');
}
?>
