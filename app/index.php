<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Initialize database and create tables
try {
    $database = new Database();
    $database->createTables();
} catch (Exception $e) {
    die('Datenbankfehler: ' . $e->getMessage());
}

// Get current event
$currentEvent = null;
if (isset($_GET['event_slug']) && !empty($_GET['event_slug'])) {
    $eventSlug = $_GET['event_slug'];

    // Validate slug format before database query
    if (validateSlug($eventSlug)) {
        $currentEvent = getEventBySlug($eventSlug);
        if (!$currentEvent || !$currentEvent['is_active']) {
            $currentEvent = null;
        }
    } else {
        // Invalid slug format - set to null
        $currentEvent = null;
    }
} elseif (isset($_GET['event_hash']) && !empty($_GET['event_hash'])) {
    // Fallback for old hash-based URLs
    $eventHash = $_GET['event_hash'];
    $currentEvent = getEventByHash($eventHash);
    if (!$currentEvent || !$currentEvent['is_active']) {
        $currentEvent = null;
    }
}

// Set $event for backward compatibility
$event = $currentEvent;

$username = getUserSession();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Source: https://github.com/proudcommerce/pcphotowall -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentEvent ? htmlspecialchars($currentEvent['name']) : APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2196F3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body>
    <div class="container">
        <header class="header">
        </header>


        <main class="main">
            <?php if (!$currentEvent): ?>
                <div class="no-event-selected">
                    <h2>Kein Event ausgewählt</h2>
                    <p>Bitte wähle ein Event über die URL aus.</p>
                    <a href="admin/" class="btn btn-primary">Admin-Bereich</a>
                </div>
            <?php else: ?>
                <!-- Event Logo (if available) -->
                <?php if ($currentEvent && $currentEvent['show_logo'] && !empty($currentEvent['logo_filename'])): ?>
                <div class="upload-logo">
                    <?php
                    $uploadPaths = getEventUploadPaths($currentEvent['event_slug']);
                    $logoUrl = $uploadPaths['logos_url'] . '/' . $currentEvent['logo_filename'];
                    ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>"
                         alt="<?php echo htmlspecialchars($currentEvent['name']); ?> Logo">
                </div>
                <?php endif; ?>

                <?php $uploadEnabled = ($currentEvent['upload_enabled'] ?? 1); ?>

                <div class="upload-section">
                    <!-- Username Input -->
                    <div class="user-info">
                        <div class="username-section">
                            <input type="text" id="username" name="username"
                                   value="<?php echo htmlspecialchars($username ?? ''); ?>"
                                   placeholder="Dein Name (optional)"
                                   <?php echo !$uploadEnabled ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <!-- File Selection Area -->
                    <div class="file-selection-area" id="fileSelectionArea">
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" id="fileInput" accept="image/*" style="display: none;" <?php echo !$uploadEnabled ? 'disabled' : ''; ?>>
                            <button type="button" class="btn btn-primary" id="selectFileBtn" <?php echo !$uploadEnabled ? 'disabled' : ''; ?>>
                                Foto auswählen
                            </button>
                            <?php if (!$uploadEnabled): ?>
                                <div class="upload-disabled-info">
                                    Upload derzeit deaktiviert
                                </div>
                            <?php endif; ?>
                            <?php if ($currentEvent && !empty($currentEvent['note'])): ?>
                            <div class="upload-divider"></div>
                            <div class="event-note-in-upload">
                                <?php echo $currentEvent['note']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- File Preview Area (hidden initially) -->
                <div class="file-preview-area" id="filePreviewArea" style="display: none;">
                    <div class="file-preview">
                        <div class="file-info">
                            <div class="file-details">
                                <div class="file-name" id="fileName"></div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;">
                                ❌ Abbrechen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="upload-progress" id="uploadProgress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p id="progressText">Upload läuft...</p>
            </div>

            <div class="upload-result" id="uploadResult" style="display: none;">
                <div class="result-content">
                    <div class="result-icon" id="resultIcon"></div>
                    <h3 id="resultTitle"></h3>
                    <p id="resultMessage"></p>
                    <button type="button" class="btn btn-primary" onclick="resetUpload()">
                        Weitere Fotos hochladen
                    </button>
                </div>
            </div>

                <!-- Event Links -->
                <?php if ($currentEvent && (($currentEvent['show_display_link'] ?? false) || ($currentEvent['show_gallery_link'] ?? false))): ?>
                <div class="event-links">
                    <div class="link-buttons">
                        <?php if ($currentEvent['show_display_link'] ?? false): ?>
                        <a href="display.php?event_slug=<?php echo urlencode($currentEvent['event_slug']); ?>"
                           class="btn btn-secondary" target="_blank">
                            Display anzeigen
                        </a>
                        <?php endif; ?>

                        <?php if ($currentEvent['show_gallery_link'] ?? false): ?>
                        <a href="gallery.php?event_slug=<?php echo urlencode($currentEvent['event_slug']); ?>"
                           class="btn btn-secondary" target="_blank">
                            Galerie anzeigen
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>

    <?php if ($currentEvent): ?>
    <script src="/assets/js/app.js"></script>
        <script>
            // Pass PHP variables to JavaScript
            window.appConfig = {
                eventSlug: '<?php echo $currentEvent['event_slug']; ?>',
                csrfToken: '<?php echo $csrfToken; ?>',
                uploadUrl: 'api/upload.php',
                moderationRequired: <?php echo $currentEvent['moderation_required'] ? 'true' : 'false'; ?>,
                uploadEnabled: <?php echo $uploadEnabled ? 'true' : 'false'; ?>
            };
        </script>
    <?php endif; ?>
    <br>
    <?php include 'includes/footer.php'; ?>
