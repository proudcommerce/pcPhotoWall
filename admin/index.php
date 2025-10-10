<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Simple admin authentication (in production, use proper authentication)
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['admin_password'])) {
        $password = $_POST['admin_password'];
        // Simple password check (in production, use proper password hashing)
        if ($password === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = 'Falsches Passwort';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo APP_NAME; ?> Admin Login</title>
            <link rel="stylesheet" href="/assets/css/style.css">
        </head>
        <body>
            <div class="container">
                <div class="admin-login">
                    <h1>Admin Login</h1>
                    <form method="POST">
                        <div class="form-group">
                            <label for="admin_password">Passwort:</label>
                            <input type="password" id="admin_password" name="admin_password" required>
                        </div>
                        <?php if (isset($error)): ?>
                            <div class="error"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Anmelden</button>
                    </form>
                </div>
            </div>

            <?php include '../includes/footer.php'; ?>
        <?php
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: index.php');
    exit;
}

// Handle event deletion
if (isset($_POST['delete_event']) && isset($_POST['event_id'])) {
    $eventId = (int)$_POST['event_id'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get event info before deletion
        $stmt = $conn->prepare("SELECT name FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if ($event) {
            // Get photos to delete files
            $stmt = $conn->prepare("SELECT filename FROM photos WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $photos = $stmt->fetchAll();
            
            // Delete photos from database (cascade will handle this, but we need to delete files)
            foreach ($photos as $photo) {
                $originalPath = UPLOAD_PATH . '/' . $photo['filename'];
                $resizedPath = UPLOAD_PATH . '/resized_' . $photo['filename'];
                
                if (file_exists($originalPath)) {
                    unlink($originalPath);
                }
                if (file_exists($resizedPath)) {
                    unlink($resizedPath);
                }
            }
            
            // Delete event (cascade will delete photos and display_config)
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            
            $successMessage = 'Event "' . $event['name'] . '" wurde erfolgreich gelöscht!';
        }
    } catch (Exception $e) {
        $errorMessage = 'Fehler beim Löschen des Events: ' . $e->getMessage();
    }
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get all events
    $stmt = $conn->prepare("SELECT * FROM events ORDER BY created_at DESC");
    $stmt->execute();
    $events = $stmt->fetchAll();
    
    // Get photo statistics
    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.name,
            COUNT(p.id) as photo_count,
            MAX(p.uploaded_at) as last_upload
        FROM events e 
        LEFT JOIN photos p ON e.id = p.event_id 
        GROUP BY e.id, e.name
        ORDER BY e.created_at DESC
    ");
    $stmt->execute();
    $eventStats = $stmt->fetchAll();
    
    // Get total statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total_photos FROM photos");
    $stmt->execute();
    $totalPhotos = $stmt->fetch()['total_photos'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total_events FROM events");
    $stmt->execute();
    $totalEvents = $stmt->fetch()['total_events'];
    
} catch (Exception $e) {
    $events = [];
    $eventStats = [];
    $totalPhotos = 0;
    $totalEvents = 0;
}

// Check for success messages
$successMessage = $successMessage ?? '';
$errorMessage = $errorMessage ?? '';

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin">
    <div class="container">
        <header class="header">
            <h1><?php echo APP_NAME; ?> Admin</h1>
            <div class="admin-actions">
                <a href="create-event.php" class="btn btn-primary">Neues Event anlegen</a>
                <a href="?logout=1" class="btn btn-secondary">Abmelden</a>
            </div>
        </header>

        <main class="main">
            <?php if ($successMessage): ?>
                <div class="success">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-dashboard">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Gesamt Events</h3>
                        <div class="stat-number"><?php echo $totalEvents; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Gesamt Fotos</h3>
                        <div class="stat-number"><?php echo $totalPhotos; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Aktive Events</h3>
                        <div class="stat-number"><?php echo count(array_filter($events, fn($e) => $e['is_active'])); ?></div>
                    </div>
                </div>

                <div class="events-section">
                    <h2>Events verwalten</h2>
                    
                    <?php if (!empty($events)): ?>
                        <div class="events-list">
                            <?php foreach ($eventStats as $stat): ?>
                                <?php 
                                $event = array_filter($events, fn($e) => $e['id'] == $stat['id']);
                                $event = reset($event);
                                ?>
                                <div class="event-card admin">
                                    <div class="event-header">
                                        <h3><?php echo htmlspecialchars($event['name']); ?></h3>
                                        <div class="event-status">
                                            <?php if ($event['is_active']): ?>
                                                <span class="status active">Aktiv</span>
                                            <?php else: ?>
                                                <span class="status inactive">Inaktiv</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    
                                    <div class="event-actions">
                                        <a href="/<?php echo $event['event_slug']; ?>" 
                                           class="btn btn-secondary" target="_blank">Upload</a>
                                        <a href="/<?php echo $event['event_slug']; ?>/display" 
                                           class="btn btn-secondary" target="_blank">Display</a>
                                        <a href="/<?php echo $event['event_slug']; ?>/gallery" 
                                           class="btn btn-secondary" target="_blank">Galerie</a>
                                        <a href="event-photos.php?slug=<?php echo $event['event_slug']; ?>" 
                                           class="btn btn-secondary" target="_blank">Fotos</a>
                                        <a href="edit-event.php?slug=<?php echo $event['event_slug']; ?>" 
                                           class="btn btn-primary">Bearbeiten</a>

                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Event und alle Fotos wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="delete_event" class="btn btn-danger">
                                                Löschen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    
    <style>
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .event-actions form {
            margin-left: 0.5rem;
        }
        
        .event-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .event-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .event-actions form {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
    </style>

    <?php include '../includes/footer.php'; ?>
