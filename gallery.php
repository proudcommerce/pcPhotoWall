<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/geo.php';
require_once 'config/database.php';

// Get event slug from query parameter
$eventSlug = $_GET['event_slug'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get event details
    $event = null;
    if (!empty($eventSlug)) {
        $event = getEventBySlug($eventSlug);
        if (!$event || !$event['is_active']) {
            $event = null;
        }
    } elseif (isset($_GET['event_hash']) && !empty($_GET['event_hash'])) {
        // Fallback for old hash-based URLs
        $eventHash = $_GET['event_hash'];
        $event = getEventByHash($eventHash);
        if (!$event || !$event['is_active']) {
            $event = null;
        }
    }
    
    // If no specific event, get the latest active event
    if (!$event) {
        $stmt = $conn->prepare("SELECT * FROM events WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $event = $stmt->fetch();
    }
    
    if (!$event) {
        $event = [
            'id' => 0,
            'name' => 'Kein Event',
            'event_slug' => 'no-event',
            'latitude' => 0,
            'longitude' => 0,
            'radius_meters' => 100,
            'show_username' => 1,
            'show_date' => 1,
            'overlay_opacity' => 0.8,
            'logo_filename' => null,
            'show_logo' => 0
        ];
    }
    
    $eventId = $event['id'];
    
    // Get photos for this event (only active ones)
    $stmt = $conn->prepare("
        SELECT id, filename, original_name, username, latitude, longitude, 
               distance_meters, uploaded_at, file_size, mime_type, thumbnail_filename
        FROM photos 
        WHERE event_id = ? AND is_active = 1
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$eventId]);
    $photos = $stmt->fetchAll();
    
    // Get event-specific upload paths
    $uploadPaths = getEventUploadPaths($event['event_slug']);
    
    // Add full URLs and formatted data to photos
    foreach ($photos as &$photo) {
        $photo['url'] = $uploadPaths['photos_url'] . '/' . $photo['filename'];
        // Use thumbnail if available, otherwise fallback to original
        if ($photo['thumbnail_filename']) {
            $photo['thumbnail_url'] = $uploadPaths['thumbnails_url'] . '/' . $photo['thumbnail_filename'];
        } else {
            $photo['thumbnail_url'] = $photo['url']; // Fallback to original
        }
        $photo['uploaded_at_formatted'] = date('d.m.Y H:i', strtotime($photo['uploaded_at']));
        $photo['file_size_formatted'] = formatBytes($photo['file_size']);
        if ($photo['distance_meters']) {
            $photo['distance_formatted'] = GeoUtils::formatDistance($photo['distance_meters']);
        }
    }
    
} catch (Exception $e) {
    $event = [
        'id' => 0,
        'name' => 'Fehler',
        'event_slug' => 'error',
        'latitude' => 0,
        'longitude' => 0,
        'radius_meters' => 100,
        'show_username' => 1,
        'show_date' => 1,
        'overlay_opacity' => 0.8
    ];
    $photos = [];
    $errorMessage = 'Datenbankfehler: ' . $e->getMessage();
}

$username = getUserSession();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['name']); ?> Galerie | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/gallery.css?v=<?php echo time(); ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <div class="container">
        <header class="header">
            <?php if ($event['show_logo'] && !empty($event['logo_filename'])): ?>
                <div class="gallery-logo">
                    <?php 
                    $uploadPaths = getEventUploadPaths($event['event_slug']);
                    $logoUrl = $uploadPaths['logos_url'] . '/' . $event['logo_filename'];
                    ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                         alt="<?php echo htmlspecialchars($event['name']); ?> Logo"
                         class="event-logo">
                </div>
            <?php else: ?>
                <h1><?php echo htmlspecialchars($event['name']); ?> Galerie</h1>
            <?php endif; ?>
        </header>

        <main class="main">
            <?php if (isset($errorMessage)): ?>
                <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <?php if (empty($photos)): ?>
                <div class="no-photos">
                    <div class="no-photos-icon">📷</div>
                    <h2>Keine Fotos verfügbar</h2>
                    <p>Es wurden noch keine Fotos für dieses Event hochgeladen.</p>
                    <a href="index.php?event_slug=<?php echo urlencode($event['event_slug']); ?>" class="btn btn-primary">
                        Fotos hochladen
                    </a>
                </div>
            <?php else: ?>
                <!-- Gallery controls temporarily hidden -->
                <!--
                <div class="gallery-controls">
                    <div class="gallery-stats">
                        <span class="photo-count"><?php echo count($photos); ?> Fotos</span>
                    </div>
                    <div class="gallery-filters">
                        <select id="sortBy" class="filter-select">
                            <option value="newest">Neueste zuerst</option>
                            <option value="oldest">Älteste zuerst</option>
                            <option value="username">Nach Username</option>
                            <option value="random">Zufällig</option>
                        </select>
                        <input type="text" id="searchInput" placeholder="Nach Username suchen..." class="search-input">
                    </div>
                </div>
                -->

                <div class="gallery-grid" id="galleryGrid">
                    <?php foreach ($photos as $photo): ?>
                        <div class="gallery-item" 
                             data-username="<?php echo htmlspecialchars($photo['username'] ?? ''); ?>"
                             data-date="<?php echo $photo['uploaded_at']; ?>"
                             data-original-name="<?php echo htmlspecialchars($photo['original_name'] ?? ''); ?>">
                            <div class="gallery-item-image">
                                <img src="<?php echo $photo['thumbnail_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($photo['original_name'] ?? ''); ?>"
                                     loading="lazy"
                                     onclick="openLightbox('<?php echo $photo['url']; ?>', '<?php echo htmlspecialchars($photo['original_name'] ?? ''); ?>', '<?php echo htmlspecialchars($photo['username'] ?? ''); ?>', '<?php echo $photo['uploaded_at_formatted']; ?>')">
                                <div class="gallery-item-overlay">
                                    <div class="gallery-item-info">
                                        <?php if ($event['show_username'] && $photo['username']): ?>
                                            <span class="username"><?php echo htmlspecialchars($photo['username'] ?? ''); ?></span>
                                        <?php endif; ?>
                                        <?php if ($event['show_date']): ?>
                                            <span class="date"><?php echo $photo['uploaded_at_formatted']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
            <button class="lightbox-nav lightbox-prev" onclick="previousPhoto()" title="Vorheriges Foto (←)">‹</button>
            <button class="lightbox-nav lightbox-next" onclick="nextPhoto()" title="Nächstes Foto (→)">›</button>
            <img id="lightboxImage" src="" alt="">
        </div>
    </div>

    <script src="assets/js/gallery.js?v=<?php echo time(); ?>"></script>
    <script>
        // Pass PHP variables to JavaScript
        window.galleryConfig = {
            eventSlug: '<?php echo $event['event_slug']; ?>',
            showUsername: <?php echo $event['show_username'] ? 'true' : 'false'; ?>,
            showDate: <?php echo $event['show_date'] ? 'true' : 'false'; ?>,
            photos: <?php echo json_encode($photos); ?>
        };
    </script>

    <?php include 'includes/footer.php'; ?>
