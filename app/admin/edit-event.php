<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/geo.php';
require_once '../config/database.php';

// Check admin authentication (inkl. Session-Timeout)
if (!isAdminSessionValid()) {
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

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    } elseif (isset($_POST['delete_logo'])) {
        // Logo-Loeschen ist ein separater Flow — keine Event-Pflichtfelder validieren.
        try {
            $database = new Database();
            $conn = $database->getConnection();

            $stmt = $conn->prepare("SELECT logo_filename FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $currentLogo = $stmt->fetchColumn();

            if ($currentLogo) {
                $uploadPaths = getEventUploadPaths($event['event_slug']);
                $logoPath = $uploadPaths['logos_path'] . '/' . $currentLogo;
                if (is_file($logoPath)) {
                    @unlink($logoPath);
                }
                $stmt = $conn->prepare("UPDATE events SET logo_filename = NULL, show_logo = 0 WHERE id = ?");
                $stmt->execute([$eventId]);
            }

            header('Location: edit-event.php?slug=' . urlencode($eventSlug));
            exit;
        } catch (Exception $e) {
            error_log('Delete logo error: ' . $e->getMessage());
            $errors[] = 'Fehler beim Löschen des Logos.';
        }
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $latitude = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? (float)$_POST['latitude'] : null;
        $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? (float)$_POST['longitude'] : null;
        $radiusMeters = (int)($_POST['radius_meters'] ?? 100);
        $displayMode = $_POST['display_mode'] ?? 'random';
        $displayCount = (int)($_POST['display_count'] ?? 1);
        $displayInterval = (int)($_POST['display_interval'] ?? 5);
        $maxUploadSize = (int)($_POST['max_upload_size'] ?? 10485760);
        $showUsername = isset($_POST['show_username']) ? 1 : 0;
        $showDate = isset($_POST['show_date']) ? 1 : 0;
        $overlayOpacity = (float)($_POST['overlay_opacity'] ?? 0.8);
        $gpsValidationRequired = isset($_POST['gps_validation_required']) ? 1 : 0;
        $moderationRequired = isset($_POST['moderation_required']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $showLogo = isset($_POST['show_logo']) ? 1 : 0;
        $showQrCode = isset($_POST['show_qr_code']) ? 1 : 0;
        $showDisplayLink = isset($_POST['show_display_link']) ? 1 : 0;
        $showGalleryLink = isset($_POST['show_gallery_link']) ? 1 : 0;
        $uploadEnabled = isset($_POST['upload_enabled']) ? 1 : 0;
        $note = $_POST['note'] ?? '';
    
    // Slug cannot be changed after creation
    $slug = $event['event_slug'];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Event-Name ist erforderlich';
    }
    
    // Only validate GPS coordinates if GPS validation is required
    if ($gpsValidationRequired) {
        if ($latitude === null || $longitude === null || !GeoUtils::validateCoordinates($latitude, $longitude)) {
            $errors[] = 'Ungültige GPS-Koordinaten';
        }
    }
    
    if ($radiusMeters < 10 || $radiusMeters > 10000) {
        $errors[] = 'Radius muss zwischen 10 und 10000 Metern liegen';
    }
    
    if (!in_array($displayMode, ['random', 'newest', 'chronological'])) {
        $displayMode = 'random';
    }
    
    $displayInterval = max(3, min(60, $displayInterval));
    $overlayOpacity = max(0.1, min(1.0, $overlayOpacity));
    
    // Validate max_upload_size
    $allowedSizes = [1048576, 2097152, 5242880, 10485760, 20971520, 52428800]; // 1MB to 50MB
    if (!in_array($maxUploadSize, $allowedSizes)) {
        $maxUploadSize = 10485760; // Default to 10MB
    }
    
    // Validate display_count
    $allowedCounts = [1, 2, 3, 4, 6, 8, 10];
    if (!in_array($displayCount, $allowedCounts)) {
        $displayCount = 1; // Default to 1 if invalid
    }
    
    // Handle logo upload (mit voller MIME-/Size-/Bild-Validierung, max. 2 MB)
    $logoFilename = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoErrors = validateLogoUpload($_FILES['logo']);
        if (!empty($logoErrors)) {
            $errors = array_merge($errors, $logoErrors);
        } else {
            $uploadPaths = getEventUploadPaths($event['event_slug']);
            $uploadDir = $uploadPaths['logos_path'];
            ensureEventDirectories($event['event_slug']);

            $fileExtension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $logoFilename = uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . '/' . $logoFilename;

            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                $errors[] = 'Fehler beim Hochladen des Logos';
                $logoFilename = null;
            }
        }
    }

    if (empty($errors)) {
        $database = new Database();
        $conn = $database->getConnection();
        $transactionOpen = false;
        try {
            // Alten Logo-Namen immer holen (fuer Ersatz-Cleanup oder Fallback).
            $stmt = $conn->prepare("SELECT logo_filename FROM events WHERE id = ?");
            $stmt->execute([$eventId]);
            $previousLogo = $stmt->fetchColumn() ?: null;

            $conn->beginTransaction();
            $transactionOpen = true;

            $stmt = $conn->prepare("
                UPDATE events
                SET name = ?, latitude = ?, longitude = ?, radius_meters = ?,
                    display_mode = ?, display_count = ?, display_interval = ?,
                    max_upload_size = ?, show_username = ?, show_date = ?, overlay_opacity = ?,
                    gps_validation_required = ?, moderation_required = ?, note = ?, is_active = ?, logo_filename = ?,
                    show_logo = ?, show_qr_code = ?, show_display_link = ?, show_gallery_link = ?, upload_enabled = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $finalLogoFilename = $logoFilename !== null ? $logoFilename : $previousLogo;

            $stmt->execute([
                $name, $latitude, $longitude, $radiusMeters, $displayMode,
                $displayCount, $displayInterval, $maxUploadSize, $showUsername, $showDate,
                $overlayOpacity, $gpsValidationRequired, $moderationRequired, $note, $isActive,
                $finalLogoFilename, $showLogo, $showQrCode, $showDisplayLink, $showGalleryLink, $uploadEnabled, $eventId
            ]);

            $stmt = $conn->prepare("
                UPDATE display_config
                SET max_photos = ?, display_mode = ?, refresh_interval = ?
                WHERE event_id = ?
            ");

            $stmt->execute([
                $displayCount, $displayMode, $displayInterval, $eventId
            ]);

            $conn->commit();
            $transactionOpen = false;

            // Altes Logo erst NACH erfolgreichem Commit entfernen (Orphan-Schutz).
            if ($logoFilename !== null && $previousLogo !== null && $previousLogo !== $logoFilename) {
                $uploadPaths = getEventUploadPaths($event['event_slug']);
                $oldLogoPath = $uploadPaths['logos_path'] . '/' . $previousLogo;
                if (is_file($oldLogoPath)) {
                    @unlink($oldLogoPath);
                }
            }

            $success = 'Event erfolgreich aktualisiert!';
            $event = getEventBySlug($eventSlug);
            header("refresh:2;url=edit-event.php?slug=" . urlencode($eventSlug));

        } catch (Exception $e) {
            if ($transactionOpen && $conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Update event error: ' . $e->getMessage());
            $errors[] = 'Fehler beim Aktualisieren des Events.';
            // Orphan-Schutz: neu hochgeladenes Logo bei DB-Fehler entfernen.
            if ($logoFilename !== null) {
                $uploadPaths = getEventUploadPaths($event['event_slug']);
                $orphanPath = $uploadPaths['logos_path'] . '/' . $logoFilename;
                if (is_file($orphanPath)) {
                    @unlink($orphanPath);
                }
                $logoFilename = null;
            }
        }
    }
    }
}

// Event is already loaded above

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Source: https://github.com/proudcommerce/pcphotowall -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Event bearbeiten</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><?php echo htmlspecialchars($event['name']); ?> bearbeiten</h1>
            <div class="admin-actions">
                <a href="index.php" class="btn btn-secondary">Zurück</a>
                <a href="event-photos.php?slug=<?php echo $event['event_slug']; ?>" class="btn btn-primary">Fotos verwalten</a>
            </div>
        </header>

        <main class="main">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <?php foreach ($errors as $error): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($event): ?>
                <!-- Hidden form for logo deletion -->
                <form id="logoDeleteForm" method="POST" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="delete_logo" value="1">
                </form>
                
                <form method="POST" class="event-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-section">
                        <h2>Event-Details</h2>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php echo $event['is_active'] ? 'checked' : ''; ?>>
                                Event ist aktiv
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Event-Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($event['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="event_slug">URL-Slug</label>
                            <input type="text" id="event_slug" name="event_slug"
                                   value="<?php echo htmlspecialchars($event['event_slug'] ?? ''); ?>"
                                   readonly
                                   style="background-color: #f5f5f5; cursor: not-allowed;">
                            <small><strong>⚠️ Der Slug kann nach der Erstellung nicht mehr geändert werden.</strong></small>
                        </div>
                        
                        <?php if (!empty($event['logo_filename'])): ?>
                        <div class="form-group">
                            <label>Aktuelles Logo:</label>
                            <div style="margin: 10px 0;">
                                <?php 
                                $uploadPaths = getEventUploadPaths($event['event_slug']);
                                $logoUrl = $uploadPaths['logos_url'] . '/' . $event['logo_filename'];
                                ?>
                                <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                                     alt="Event Logo" style="max-height: 100px; max-width: 200px; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                <div style="margin-top: 10px;">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteLogo()">Logo löschen</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="logo">Logo ändern (optional)</label>
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <small>Erlaubte Formate: JPG, PNG, GIF, WebP (max. 2MB)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="note">Event-Notiz:</label>
                            <textarea id="note" name="note" rows="6"
                                      placeholder="Optionale Notiz, die beim Upload angezeigt wird. Nur Plain-Text, Zeilenumbrüche werden übernommen."><?php echo htmlspecialchars($event['note'] ?? ''); ?></textarea>
                            <small>
                                Diese Notiz wird als Plain-Text auf der Upload-Seite angezeigt. HTML wird nicht interpretiert.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="moderation_required" value="1" 
                                       <?php echo ($event['moderation_required'] ?? false) ? 'checked' : ''; ?>>
                                Bildmoderation erforderlich
                            </label>
                            <small>Fotos müssen vor der Anzeige freigegeben werden</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>Link-Einstellungen</h2>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_display_link" value="1" 
                                       <?php echo ($event['show_display_link'] ?? false) ? 'checked' : ''; ?>>
                                Display-Link auf Upload-Seite anzeigen
                            </label>
                            <small>Zeigt einen Link zur Display-Ansicht auf der Upload-Seite an</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_gallery_link" value="1" 
                                       <?php echo ($event['show_gallery_link'] ?? false) ? 'checked' : ''; ?>>
                                Gallery-Link auf Upload-Seite anzeigen
                            </label>
                            <small>Zeigt einen Link zur Galerie-Ansicht auf der Upload-Seite an</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>GPS-Validierung</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="latitude">Breitengrad (Latitude)</label>
                                <input type="number" id="latitude" name="latitude" step="any" 
                                       value="<?php echo $event['latitude']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="longitude">Längengrad (Longitude)</label>
                                <input type="number" id="longitude" name="longitude" step="any" 
                                       value="<?php echo $event['longitude']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="radius_meters">Upload-Radius (Meter) *</label>
                            <input type="number" id="radius_meters" name="radius_meters" min="10" max="10000" required 
                                   value="<?php echo $event['radius_meters']; ?>">
                            <small>Fotos müssen innerhalb dieses Radius aufgenommen werden</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="gps_validation_required" value="1" 
                                       <?php echo $event['gps_validation_required'] ? 'checked' : ''; ?>>
                                GPS-Validierung erforderlich
                            </label>
                            <small>Wenn aktiviert, müssen Fotos am Event-Standort aufgenommen werden</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>Display-Einstellungen</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="display_mode">Anzeige-Modus</label>
                                <select id="display_mode" name="display_mode">
                                    <option value="random" <?php echo $event['display_mode'] == 'random' ? 'selected' : ''; ?>>Zufällig</option>
                                    <option value="newest" <?php echo $event['display_mode'] == 'newest' ? 'selected' : ''; ?>>Neueste zuerst</option>
                                    <option value="chronological" <?php echo $event['display_mode'] == 'chronological' ? 'selected' : ''; ?>>Chronologisch</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="display_count">Anzahl Fotos</label>
                                <select id="display_count" name="display_count">
                                    <option value="1" <?php echo $event['display_count'] == 1 ? 'selected' : ''; ?>>1 Foto</option>
                                    <option value="2" <?php echo $event['display_count'] == 2 ? 'selected' : ''; ?>>2 Fotos</option>
                                    <option value="3" <?php echo $event['display_count'] == 3 ? 'selected' : ''; ?>>3 Fotos</option>
                                    <option value="4" <?php echo $event['display_count'] == 4 ? 'selected' : ''; ?>>4 Fotos</option>
                                    <option value="6" <?php echo $event['display_count'] == 6 ? 'selected' : ''; ?>>6 Fotos</option>
                                    <option value="8" <?php echo $event['display_count'] == 8 ? 'selected' : ''; ?>>8 Fotos</option>
                                    <option value="10" <?php echo $event['display_count'] == 10 ? 'selected' : ''; ?>>10 Fotos</option>
                                </select>
                                <small>Layout wird automatisch basierend auf der Anzahl optimiert</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_interval">Wechsel-Intervall (Sekunden)</label>
                            <input type="number" id="display_interval" name="display_interval" min="3" max="60" 
                                   value="<?php echo $event['display_interval']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_logo" value="1" 
                                       <?php echo $event['show_logo'] ? 'checked' : ''; ?>>
                                Logo im Display anzeigen
                            </label>
                            <small>Logo wird oben mittig im Display-Modus angezeigt</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_qr_code" value="1" 
                                       <?php echo ($event['show_qr_code'] ?? false) ? 'checked' : ''; ?>>
                                QR-Code im Display anzeigen
                            </label>
                            <small>QR-Code wird unten mittig im Display-Modus angezeigt und führt zur Upload-Seite</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>Upload-Einstellungen</h2>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="upload_enabled" value="1"
                                       <?php echo ($event['upload_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                Upload aktiviert
                            </label>
                            <small>Wenn deaktiviert, können keine Fotos hochgeladen werden (z.B. wenn das Event vorbei ist)</small>
                        </div>

                        <div class="form-group">
                            <label for="max_upload_size">Maximale Upload-Größe pro Bild (MB)</label>
                            <select id="max_upload_size" name="max_upload_size">
                                <option value="1048576" <?php echo ($event['max_upload_size'] ?? 10485760) == 1048576 ? 'selected' : ''; ?>>1 MB</option>
                                <option value="2097152" <?php echo ($event['max_upload_size'] ?? 10485760) == 2097152 ? 'selected' : ''; ?>>2 MB</option>
                                <option value="5242880" <?php echo ($event['max_upload_size'] ?? 10485760) == 5242880 ? 'selected' : ''; ?>>5 MB</option>
                                <option value="10485760" <?php echo ($event['max_upload_size'] ?? 10485760) == 10485760 ? 'selected' : ''; ?>>10 MB</option>
                                <option value="20971520" <?php echo ($event['max_upload_size'] ?? 10485760) == 20971520 ? 'selected' : ''; ?>>20 MB</option>
                                <option value="52428800" <?php echo ($event['max_upload_size'] ?? 10485760) == 52428800 ? 'selected' : ''; ?>>50 MB</option>
                            </select>
                            <small>Maximale Dateigröße für einzelne Bilder</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>Bild-Overlay-Einstellungen</h2>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_username" value="1" 
                                       <?php echo $event['show_username'] ? 'checked' : ''; ?>>
                                Username anzeigen
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="show_date" value="1" 
                                       <?php echo $event['show_date'] ? 'checked' : ''; ?>>
                                Datum anzeigen
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label for="overlay_opacity">Overlay-Transparenz (0.1 - 1.0)</label>
                            <input type="number" id="overlay_opacity" name="overlay_opacity" 
                                   min="0.1" max="1.0" step="0.1" 
                                   value="<?php echo $event['overlay_opacity'] ?? 0.8; ?>">
                            <small>0.1 = sehr transparent, 1.0 = undurchsichtig</small>
                        </div>
                    </div>
                    
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Event aktualisieren</button>
                        <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        // Debug form submission
        document.querySelector('.event-form').addEventListener('submit', function(e) {
            console.log('Form submitted');
            console.log('Form data:', new FormData(this));
        });
        
        // Logo deletion function
        function deleteLogo() {
            if (confirm('Logo wirklich löschen?')) {
                document.getElementById('logoDeleteForm').submit();
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>