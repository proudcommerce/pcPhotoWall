<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Get event slug from query parameter
$eventSlug = $_GET['event_slug'] ?? '';

// Display configuration will be loaded from event settings only

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get event details
    $event = null;
    if (!empty($eventSlug)) {
        // Validate slug format before database query
        if (validateSlug($eventSlug)) {
            $event = getEventBySlug($eventSlug);
            if (!$event || !$event['is_active']) {
                $event = null;
            }
        } else {
            // Invalid slug format - set to null
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
            'latitude' => 0,
            'longitude' => 0,
            'radius_meters' => 100,
            'display_mode' => DEFAULT_DISPLAY_MODE,
            'display_count' => DEFAULT_DISPLAY_COUNT,
            'display_interval' => DEFAULT_DISPLAY_INTERVAL,
            'layout_type' => 'grid',
            'grid_columns' => DEFAULT_GRID_COLUMNS,
            'show_username' => 1,
            'show_date' => 1,
            'overlay_opacity' => 0.8,
            'logo_filename' => null,
            'show_logo' => 0
        ];
    }

    // Get display configuration using helper function
    $config = getDisplayConfiguration($event, $_GET);
    extract($config); // Extracts: $displayCount, $displayMode, $displayInterval, $layoutType, $gridColumns, $showLogo, $showQrCode
    
} catch (Exception $e) {
    $event = [
        'id' => 0,
        'name' => 'Fehler',
        'latitude' => 0,
        'longitude' => 0,
        'radius_meters' => 100,
        'display_mode' => DEFAULT_DISPLAY_MODE,
        'display_count' => DEFAULT_DISPLAY_COUNT,
        'display_interval' => DEFAULT_DISPLAY_INTERVAL,
        'layout_type' => 'grid',
        'grid_columns' => DEFAULT_GRID_COLUMNS,
        'show_username' => 1,
        'show_date' => 1,
        'overlay_opacity' => 0.8
    ];

    // Get display configuration using helper function
    $config = getDisplayConfiguration($event, $_GET);
    extract($config); // Extracts: $displayCount, $displayMode, $displayInterval, $layoutType, $gridColumns, $showLogo, $showQrCode
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Source: https://github.com/proudcommerce/pcphotowall -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Display</title>
    <link rel="stylesheet" href="/assets/css/display.css">
    <meta name="theme-color" content="#000000">
    <style>
        :root {
            --display-count: <?php echo $displayCount; ?>;
            --display-interval: <?php echo $displayInterval; ?>s;
            --grid-columns: <?php echo $gridColumns; ?>;
            --layout-type: <?php echo $layoutType; ?>;
        }
    </style>
</head>
<body class="display-mode">
    <div class="display-container">
        <?php if ($showLogo && !empty($event['logo_filename'])): ?>
        <div class="display-logo">
            <?php 
            $uploadPaths = getEventUploadPaths($event['event_slug']);
            $logoUrl = $uploadPaths['logos_url'] . '/' . $event['logo_filename'];
            ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                 alt="<?php echo htmlspecialchars($event['name']); ?> Logo">
        </div>
        <?php endif; ?>
        
        <div class="photo-container" id="photoContainer">
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Lade Fotos...</p>
            </div>
            
            <div class="no-photos" id="noPhotos" style="display: none;">
                <div class="no-photos-icon">📷</div>
                <h2>Keine Fotos verfügbar</h2>
                <p>Es wurden noch keine Fotos für dieses Event hochgeladen.</p>
            </div>
            
            <div class="photos-grid" id="photosGrid" style="display: none;">
                <!-- Photos will be loaded here via JavaScript -->
            </div>
        </div>
        
        <?php if ($showQrCode): ?>
        <div class="display-qr-code">
            <?php 
            // Generate upload URL with clean slug-based path
            $uploadUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/' . $event['event_slug'];
            $qrCodeDataUri = generateQRCodeDataUri($uploadUrl, 150);
            if ($qrCodeDataUri): ?>
                <img src="<?php echo $qrCodeDataUri; ?>" alt="QR-Code für Upload" class="qr-code-image">
                <div class="qr-code-label">Foto hochladen</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
    
    
    <script src="/assets/js/display.js"></script>
    <script>
        // Pass PHP variables to JavaScript
        window.displayConfig = {
            eventSlug: '<?php echo $event['event_slug']; ?>',
            displayCount: <?php echo $displayCount; ?>,
            displayMode: '<?php echo $displayMode; ?>',
            displayInterval: <?php echo $displayInterval; ?>,
            showLogo: <?php echo $showLogo ? 'true' : 'false'; ?>,
            showQrCode: <?php echo $showQrCode ? 'true' : 'false'; ?>,
            photosUrl: '/api/photos.php',
            eventConfig: {
                showUsername: <?php echo $event['show_username'] ? 'true' : 'false'; ?>,
                showDate: <?php echo $event['show_date'] ? 'true' : 'false'; ?>,
                overlayOpacity: <?php echo $event['overlay_opacity'] ?? 0.8; ?>
            }
        };
    </script>
</body>
</html>
