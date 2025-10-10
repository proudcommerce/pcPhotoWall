<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/geo.php';
require_once '../config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Get event slug
$eventSlug = $_GET['slug'] ?? '';
if (empty($eventSlug)) {
    header('Location: index.php');
    exit;
}

// Get event by slug
$event = getEventBySlug($eventSlug);
if (!$event) {
    header('Location: index.php');
    exit;
}

$eventId = $event['id'];

// Handle photo deletion
if (isset($_POST['delete_photo']) && isset($_POST['photo_id'])) {
    $photoId = (int)$_POST['photo_id'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get photo info before deletion
        $stmt = $conn->prepare("SELECT filename, thumbnail_filename FROM photos WHERE id = ? AND event_id = ?");
        $stmt->execute([$photoId, $eventId]);
        $photo = $stmt->fetch();
        
        if ($photo) {
            // Get event-specific upload paths
            $event = getEventBySlug($eventSlug);
            $uploadPaths = getEventUploadPaths($event['event_slug']);
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM photos WHERE id = ? AND event_id = ?");
            $stmt->execute([$photoId, $eventId]);
            
            // Delete files
            $originalPath = $uploadPaths['photos_path'] . '/' . $photo['filename'];
            $resizedPath = $uploadPaths['photos_path'] . '/resized_' . $photo['filename'];
            
            if (file_exists($originalPath)) {
                unlink($originalPath);
            }
            if (file_exists($resizedPath)) {
                unlink($resizedPath);
            }
            
            // Delete thumbnail if exists
            if ($photo['thumbnail_filename']) {
                $thumbnailPath = $uploadPaths['thumbnails_path'] . '/' . $photo['thumbnail_filename'];
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }
            
            $successMessage = 'Foto wurde erfolgreich gelöscht!';
        }
    } catch (Exception $e) {
        $errorMessage = 'Fehler beim Löschen des Fotos: ' . $e->getMessage();
    }
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get photos for this event
    $stmt = $conn->prepare("
        SELECT *, thumbnail_filename FROM photos 
        WHERE event_id = ? 
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$eventId]);
    $photos = $stmt->fetchAll();
    
    
    // Get event-specific upload paths
    $uploadPaths = getEventUploadPaths($event['event_slug']);
    
    // Add additional info to photos
    foreach ($photos as $key => $photo) {
        $photos[$key]['url'] = $uploadPaths['photos_url'] . '/' . $photo['filename'];
        // Use thumbnail if available, otherwise fallback to original
        if ($photo['thumbnail_filename']) {
            $photos[$key]['thumbnail_url'] = $uploadPaths['thumbnails_url'] . '/' . $photo['thumbnail_filename'];
        } else {
            $photos[$key]['thumbnail_url'] = $photos[$key]['url']; // Fallback to original
        }
        $photos[$key]['uploaded_at_formatted'] = date('d.m.Y H:i', strtotime($photo['uploaded_at']));
        $photos[$key]['file_size_formatted'] = formatBytes($photo['file_size']);
        if ($photo['distance_meters']) {
            $photos[$key]['distance_formatted'] = GeoUtils::formatDistance($photo['distance_meters']);
        }
    }
    
} catch (Exception $e) {
    $event = null;
    $photos = [];
    $errorMessage = 'Datenbankfehler: ' . $e->getMessage();
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $event ? htmlspecialchars($event['name']) : 'Event'; ?> Fotos</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><?php echo $event ? htmlspecialchars($event['name']) : 'Event'; ?> Fotos (<?php echo count($photos); ?>)</h1>
        </header>

        <main class="main">
            <?php if (isset($successMessage)): ?>
                <div class="success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if (isset($errorMessage)): ?>
                <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($event): ?>

                <?php if (empty($photos)): ?>
                    <div class="no-photos">
                        <h3>Keine Fotos vorhanden</h3>
                        <p>Es wurden noch keine Fotos für dieses Event hochgeladen.</p>
                        <a href="../index.php?event_id=<?php echo $eventId; ?>" class="btn btn-primary">Fotos hochladen</a>
                    </div>
                <?php else: ?>
                    <div class="photos-grid">
                        <?php foreach ($photos as $photo): ?>
                            <div class="photo-card">
                                <div class="photo-preview">
                                    <img src="<?php echo $photo['thumbnail_url']; ?>" alt="Foto" loading="lazy">
                                </div>
                                <div class="photo-info">
                                    <div class="photo-details">
                                        <p><strong>Datei:</strong> <?php echo htmlspecialchars($photo['original_name']); ?></p>
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($photo['username'] ?? ''); ?></p>
                                        <p><strong>Hochgeladen:</strong> <?php echo $photo['uploaded_at_formatted']; ?></p>
                                        <p><strong>Größe:</strong> <?php echo $photo['file_size_formatted']; ?></p>
                                        <p><strong>Status:</strong> 
                                            <span class="photo-status <?php echo $photo['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $photo['is_active'] ? 'Aktiv' : 'Deaktiviert'; ?>
                                            </span>
                                        </p>
                                        <?php if ($photo['latitude'] && $photo['longitude']): ?>
                                            <p><strong>GPS:</strong> <?php echo $photo['latitude']; ?>, <?php echo $photo['longitude']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="photo-actions">
                                        <button type="button" 
                                                class="btn btn-sm toggle-photo-status <?php echo $photo['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                data-photo-id="<?php echo $photo['id']; ?>"
                                                data-current-status="<?php echo $photo['is_active']; ?>">
                                            <?php echo $photo['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Foto wirklich löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                                            <button type="submit" name="delete_photo" class="btn btn-danger btn-sm">
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <style>

        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .photo-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .photo-preview {
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-info {
            padding: 1.5rem;
        }

        .photo-details p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .photo-actions {
            margin-top: 1rem;
            text-align: right;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }

        .photo-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .photo-status.active {
            background-color: #d4edda;
            color: #155724;
        }

        .photo-status.inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .toggle-photo-status {
            transition: all 0.2s ease;
        }

        .toggle-photo-status:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .no-photos {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-photos h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        @media (max-width: 768px) {
            .photos-grid {
                grid-template-columns: 1fr;
            }
            
            .event-details {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle photo status functionality
            const toggleButtons = document.querySelectorAll('.toggle-photo-status');
            
            console.log('Found toggle buttons:', toggleButtons.length);
            
            toggleButtons.forEach((button, index) => {
                console.log(`Button ${index}:`, {
                    photoId: button.getAttribute('data-photo-id'),
                    currentStatus: button.getAttribute('data-current-status'),
                    text: button.textContent
                });
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const photoId = this.getAttribute('data-photo-id');
                    const currentStatus = parseInt(this.getAttribute('data-current-status'));
                    const newStatus = currentStatus ? 0 : 1;
                    
                    console.log('Toggle clicked:', {
                        photoId: photoId,
                        currentStatus: currentStatus,
                        newStatus: newStatus
                    });
                    
                    // Disable button during request
                    this.disabled = true;
                    this.textContent = 'Wird aktualisiert...';
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('photo_id', photoId);
                    formData.append('is_active', newStatus);
                    formData.append('csrf_token', '<?php echo $csrfToken; ?>');
                    
                    fetch('/api/toggle-photo-status.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        return response.text().then(text => {
                            console.log('Response text:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                console.error('Response text:', text);
                                throw new Error('Invalid JSON response');
                            }
                        });
                    })
                    .then(data => {
                        console.log('Parsed data:', data);
                        if (data.success) {
                            console.log('Success: Updating UI for photo ID', photoId);
                            
                            // Update button appearance (only this specific button)
                            this.setAttribute('data-current-status', newStatus);
                            this.textContent = newStatus ? 'Deaktivieren' : 'Aktivieren';
                            this.className = this.className.replace(/btn-(warning|success)/, newStatus ? 'btn-warning' : 'btn-success');
                            
                            // Update status display (only in this specific photo card)
                            const photoCard = this.closest('.photo-card');
                            const statusSpan = photoCard.querySelector('.photo-status');
                            if (statusSpan) {
                                statusSpan.textContent = newStatus ? 'Aktiv' : 'Deaktiviert';
                                statusSpan.className = 'photo-status ' + (newStatus ? 'active' : 'inactive');
                            }
                            
                            // Show success message
                            showMessage(data.message + ' (ID: ' + photoId + ')', 'success');
                        } else {
                            showMessage(data.error || 'Fehler beim Aktualisieren', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Detailed error:', error);
                        showMessage('Fehler: ' + error.message, 'error');
                    })
                    .finally(() => {
                        // Re-enable button
                        this.disabled = false;
                    });
                });
            });
        });
        
        function showMessage(message, type) {
            // Create message element
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'success' ? 'success' : 'error';
            messageDiv.textContent = message;
            messageDiv.style.position = 'fixed';
            messageDiv.style.top = '20px';
            messageDiv.style.right = '20px';
            messageDiv.style.zIndex = '1000';
            messageDiv.style.padding = '1rem';
            messageDiv.style.borderRadius = '4px';
            messageDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            
            if (type === 'success') {
                messageDiv.style.backgroundColor = '#d4edda';
                messageDiv.style.color = '#155724';
                messageDiv.style.border = '1px solid #c3e6cb';
            } else {
                messageDiv.style.backgroundColor = '#f8d7da';
                messageDiv.style.color = '#721c24';
                messageDiv.style.border = '1px solid #f5c6cb';
            }
            
            document.body.appendChild(messageDiv);
            
            // Remove message after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 3000);
        }
    </script>

    <?php include '../includes/footer.php'; ?>
