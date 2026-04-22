<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

// Admin authentication mit password_verify (bevorzugt ADMIN_PASSWORD_HASH).
if (!isAdminSessionValid()) {
    if (isset($_POST['admin_password'])) {
        $password = (string)$_POST['admin_password'];

        $hash = ADMIN_PASSWORD_HASH;
        if ($hash === '' && ADMIN_PASSWORD !== '') {
            // Fallback: kein Hash konfiguriert, vergleiche Plain-Text in konstanter Zeit.
            $valid = hash_equals(ADMIN_PASSWORD, $password);
        } else {
            $valid = $hash !== '' && password_verify($password, $hash);
        }

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_last_activity'] = time();
        } else {
            error_log('Admin login failed from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $error = 'Falsches Passwort';
        }
    }

    if (!isAdminSessionValid()) {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <!-- Source: https://github.com/proudcommerce/pcphotowall -->
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
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Ungültiger CSRF-Token.';
    } else {
    $eventId = (int)$_POST['event_id'];

    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Get event info (incl. slug + logo) before deletion
        $stmt = $conn->prepare("SELECT name, event_slug, logo_filename FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if ($event) {
            $eventSlug = $event['event_slug'];
            $uploadPaths = $eventSlug ? getEventUploadPaths($eventSlug) : null;

            // Get photos to delete files
            $stmt = $conn->prepare("SELECT filename, thumbnail_filename, resized_filename FROM photos WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $photos = $stmt->fetchAll();

            if ($uploadPaths) {
                foreach ($photos as $photo) {
                    foreach ([
                        $uploadPaths['photos_path'] . '/' . $photo['filename'],
                        $photo['resized_filename'] ? $uploadPaths['photos_path'] . '/' . $photo['resized_filename'] : null,
                        $photo['thumbnail_filename'] ? $uploadPaths['thumbnails_path'] . '/' . $photo['thumbnail_filename'] : null,
                    ] as $path) {
                        if ($path && is_file($path)) {
                            unlink($path);
                        }
                    }
                }

                // Logo und leere Event-Verzeichnisse entfernen
                if (!empty($event['logo_filename'])) {
                    $logoPath = $uploadPaths['logos_path'] . '/' . $event['logo_filename'];
                    if (is_file($logoPath)) {
                        unlink($logoPath);
                    }
                }
                foreach (['photos_path', 'thumbnails_path', 'logos_path'] as $pathKey) {
                    if (is_dir($uploadPaths[$pathKey])) {
                        @rmdir($uploadPaths[$pathKey]);
                    }
                }
                $eventRoot = dirname($uploadPaths['photos_path']);
                if (is_dir($eventRoot)) {
                    @rmdir($eventRoot);
                }
            }

            // Delete event (cascade will delete photos and display_config)
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);

            $successMessage = 'Event "' . $event['name'] . '" wurde erfolgreich gelöscht!';
        }
    } catch (Exception $e) {
        error_log('Delete event error: ' . $e->getMessage());
        $errorMessage = 'Fehler beim Löschen des Events.';
    }
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
    <!-- Source: https://github.com/proudcommerce/pcphotowall -->
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
