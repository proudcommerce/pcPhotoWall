<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/geo.php';
require_once '../config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
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
    $showDisplayLink = isset($_POST['show_display_link']) ? 1 : 0;
    $showGalleryLink = isset($_POST['show_gallery_link']) ? 1 : 0;
    $note = $_POST['note'] ?? '';
    
    // Handle slug
    $slug = '';
    if (isset($_POST['event_slug']) && !empty($_POST['event_slug'])) {
        $slug = strtolower(trim($_POST['event_slug']));
        if (!validateSlug($slug)) {
            $errors[] = 'Ungültiger Slug. Nur Buchstaben, Zahlen und Bindestriche erlaubt (3-100 Zeichen). Reservierte Wörter: admin, api, uploads, assets, css, js, images, display, index, login, logout';
        } elseif (!isSlugUnique($slug)) {
            $errors[] = 'Slug bereits vergeben';
        }
    } else {
        $slug = generateUniqueSlug($name);
    }
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Event-Name ist erforderlich';
    }
    
    // Only validate GPS coordinates if GPS validation is required
    if ($gpsValidationRequired && !GeoUtils::validateCoordinates($latitude, $longitude)) {
        $errors[] = 'Ungültige GPS-Koordinaten';
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
    
    // Handle logo upload
    $logoFilename = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        // Use event-specific upload paths
        $uploadPaths = getEventUploadPaths($eventSlug);
        $uploadDir = $uploadPaths['logos_path'];
        
        // Ensure directory exists
        ensureEventDirectories($eventSlug);
        
        $fileExtension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $logoFilename = uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . '/' . $logoFilename;
            
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                $errors[] = 'Fehler beim Hochladen des Logos';
            }
        } else {
            $errors[] = 'Ungültiges Logo-Format. Erlaubt: JPG, PNG, GIF, SVG, WebP';
        }
    }
    
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Generate unique hash for the event
            $eventHash = generateEventHash();
            
            // Create event
            $stmt = $conn->prepare("
                INSERT INTO events (name, latitude, longitude, radius_meters, display_mode, display_count, display_interval, max_upload_size, show_username, show_date, overlay_opacity, gps_validation_required, moderation_required, note, is_active, logo_filename, show_logo, show_display_link, show_gallery_link, event_hash, event_slug) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $latitude, $longitude, $radiusMeters, $displayMode, 
                $displayCount, $displayInterval, $maxUploadSize, $showUsername, $showDate, 
                $overlayOpacity, $gpsValidationRequired, $moderationRequired, $note, $isActive, $logoFilename, $showLogo, $showDisplayLink, $showGalleryLink, $eventHash, $slug
            ]);
            
            $eventId = $conn->lastInsertId();
            
            // Create display config
            $stmt = $conn->prepare("
                INSERT INTO display_config (event_id, max_photos, display_mode, refresh_interval) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $eventId, $displayCount, $displayMode, $displayInterval
            ]);
            
            $success = 'Event erfolgreich erstellt!';
            
            // Redirect to event list after 2 seconds
            header("refresh:2;url=index.php");
            
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Erstellen des Events: ' . $e->getMessage();
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - neues Event anlegen</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>neues Event anlegen</h1>
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

            <form method="POST" class="event-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-section">
                    <h2>Event-Details</h2>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                            Event ist aktiv
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Event-Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="event_slug">URL-Slug (optional)</label>
                        <input type="text" id="event_slug" name="event_slug" 
                               value="<?php echo htmlspecialchars($_POST['event_slug'] ?? ''); ?>"
                               placeholder="z.B. devops-camp-2024"
                               pattern="[a-z0-9-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche. Nicht erlaubt: admin, api, uploads, assets, css, js, images, display, index, login, logout">
                        <small>Wird automatisch generiert wenn leer. Bestimmt die URL: picturewall/SLUG</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="logo">Event-Logo (optional)</label>
                        <input type="file" id="logo" name="logo" accept="image/*">
                        <small>Erlaubte Formate: JPG, PNG, GIF, SVG, WebP (max. 2MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="note">Event-Notiz (HTML erlaubt):</label>
                        <textarea id="note" name="note" rows="6" 
                                  placeholder="Optionale Notiz, die beim Upload angezeigt wird. HTML-Tags sind erlaubt.

Beispiel:
<strong>Wichtige Hinweise:</strong>
<ul>
  <li>Fotos müssen am Event-Standort aufgenommen werden</li>
  <li>Maximale Dateigröße: 10MB</li>
  <li>Erlaubte Formate: JPG, PNG, GIF, WebP</li>
</ul>

<em>Vielen Dank für Ihre Teilnahme!</em>"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                        <small>
                            <strong>HTML-Tags erlaubt:</strong> &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;h1&gt;-&lt;h6&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;p&gt;, &lt;a&gt;, &lt;blockquote&gt;, &lt;code&gt;<br>
                            Diese Notiz wird prominent auf der Upload-Seite angezeigt.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="moderation_required" value="1" 
                                   <?php echo isset($_POST['moderation_required']) ? 'checked' : ''; ?>>
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
                                   <?php echo isset($_POST['show_display_link']) ? 'checked' : ''; ?>>
                            Display-Link auf Upload-Seite anzeigen
                        </label>
                        <small>Zeigt einen Link zur Display-Ansicht auf der Upload-Seite an</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_gallery_link" value="1" 
                                   <?php echo isset($_POST['show_gallery_link']) ? 'checked' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>"
                                   placeholder="z.B. 52.520008">
                        </div>
                        
                        <div class="form-group">
                            <label for="longitude">Längengrad (Longitude)</label>
                            <input type="number" id="longitude" name="longitude" step="any" 
                                   value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>"
                                   placeholder="z.B. 13.404954">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="radius_meters">Upload-Radius (Meter) *</label>
                        <input type="number" id="radius_meters" name="radius_meters" min="10" max="10000" required 
                               value="<?php echo htmlspecialchars($_POST['radius_meters'] ?? '100'); ?>">
                        <small>Fotos müssen innerhalb dieses Radius aufgenommen werden</small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="gps_validation_required" value="1" 
                                   <?php echo isset($_POST['gps_validation_required']) ? 'checked' : ''; ?>>
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
                                <option value="random" <?php echo ($_POST['display_mode'] ?? '') == 'random' ? 'selected' : ''; ?>>Zufällig</option>
                                <option value="newest" <?php echo ($_POST['display_mode'] ?? '') == 'newest' ? 'selected' : ''; ?>>Neueste zuerst</option>
                                <option value="chronological" <?php echo ($_POST['display_mode'] ?? '') == 'chronological' ? 'selected' : ''; ?>>Chronologisch</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="display_count">Anzahl Fotos</label>
                            <select id="display_count" name="display_count">
                                <option value="1" <?php echo ($_POST['display_count'] ?? 1) == 1 ? 'selected' : ''; ?>>1 Foto</option>
                                <option value="2" <?php echo ($_POST['display_count'] ?? 1) == 2 ? 'selected' : ''; ?>>2 Fotos</option>
                                <option value="3" <?php echo ($_POST['display_count'] ?? 1) == 3 ? 'selected' : ''; ?>>3 Fotos</option>
                                <option value="4" <?php echo ($_POST['display_count'] ?? 1) == 4 ? 'selected' : ''; ?>>4 Fotos</option>
                                <option value="6" <?php echo ($_POST['display_count'] ?? 1) == 6 ? 'selected' : ''; ?>>6 Fotos</option>
                                <option value="8" <?php echo ($_POST['display_count'] ?? 1) == 8 ? 'selected' : ''; ?>>8 Fotos</option>
                                <option value="10" <?php echo ($_POST['display_count'] ?? 1) == 10 ? 'selected' : ''; ?>>10 Fotos</option>
                            </select>
                            <small>Layout wird automatisch basierend auf der Anzahl optimiert</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_interval">Wechsel-Intervall (Sekunden)</label>
                        <input type="number" id="display_interval" name="display_interval" min="3" max="60" 
                               value="<?php echo htmlspecialchars($_POST['display_interval'] ?? '5'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_logo" value="1" 
                                   <?php echo isset($_POST['show_logo']) ? 'checked' : ''; ?>>
                            Logo im Display anzeigen
                        </label>
                        <small>Logo wird oben mittig im Display-Modus angezeigt</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Upload-Einstellungen</h2>
                    
                    <div class="form-group">
                        <label for="max_upload_size">Maximale Upload-Größe pro Bild (MB)</label>
                        <select id="max_upload_size" name="max_upload_size">
                            <option value="1048576" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 1048576 ? 'selected' : ''; ?>>1 MB</option>
                            <option value="2097152" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 2097152 ? 'selected' : ''; ?>>2 MB</option>
                            <option value="5242880" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 5242880 ? 'selected' : ''; ?>>5 MB</option>
                            <option value="10485760" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 10485760 ? 'selected' : ''; ?>>10 MB</option>
                            <option value="20971520" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 20971520 ? 'selected' : ''; ?>>20 MB</option>
                            <option value="52428800" <?php echo ($_POST['max_upload_size'] ?? 10485760) == 52428800 ? 'selected' : ''; ?>>50 MB</option>
                        </select>
                        <small>Maximale Dateigröße für einzelne Bilder</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Bild-Overlay-Einstellungen</h2>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_username" value="1" 
                                   <?php echo isset($_POST['show_username']) ? 'checked' : ''; ?>>
                            Username anzeigen
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_date" value="1" 
                                   <?php echo isset($_POST['show_date']) ? 'checked' : ''; ?>>
                            Datum anzeigen
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="overlay_opacity">Overlay-Transparenz (0.1 - 1.0)</label>
                        <input type="number" id="overlay_opacity" name="overlay_opacity" 
                               min="0.1" max="1.0" step="0.1" 
                               value="<?php echo htmlspecialchars($_POST['overlay_opacity'] ?? '0.8'); ?>">
                        <small>0.1 = sehr transparent, 1.0 = undurchsichtig</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Event erstellen</button>
                    <a href="index.php" class="btn btn-secondary">Abbrechen</a>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        // Form validation and interaction scripts can be added here if needed
    </script>

    <?php include '../includes/footer.php'; ?>
