# PC PhotoWall Test Suite

Diese Test-Suite bietet umfassende Tests für die PC PhotoWall-Anwendung. Sie ist darauf ausgelegt, alle kritischen Funktionen zu testen, ohne externe Abhängigkeiten zu benötigen.

## ⚠️ Voraussetzungen

**Wichtig:** Tests benötigen eine laufende Dev-Umgebung mit Datenbankzugriff.

```bash
# Dev-Umgebung starten (erforderlich für Tests)
make dev-up
```

Das Test-Script prüft automatisch, ob die Dev-Umgebung läuft und blockiert Tests, falls nicht. Nur `./run-tests.sh syntax` läuft ohne Dev-Umgebung.

## 📁 Test-Struktur

### Test-Dateien

- **`TestRunner.php`** - Leichtgewichtiges Test-Framework für kontinuierliches Testen
- **`ComprehensiveTests.php`** - Umfassende Tests aller PC PhotoWall-Funktionen
- **`SimpleIntegrationTests.php`** - Vereinfachte Integrationstests ohne Datenbank-Abhängigkeiten
- **`UploadFunctionalityTests.php`** - Spezifische Tests für Upload-Funktionalitäten
- **`RealImageUploadTests.php`** - Tests mit echten Bilddateien aus dem `pics/` Ordner
- **`ImageRotationAnalysisTests.php`** - Tests für Bildrotation und EXIF-Datenanalyse
- **`PhotoRotationTests.php`** - Tests für manuelle Foto-Rotation
- **`run-tests.sh`** - Bash-Script für Test-Ausführung mit verschiedenen Optionen

### Verzeichnisse

- **`data/`** - Temporäre Testdaten (wird automatisch erstellt)
- **`logs/`** - Test-Logs und Ergebnisse
- **`pics/`** - Echte Bilddateien für Real-Image-Tests
  - `IMG_4825.jpg` - JPEG-Testbild
  - `IMG_7010.HEIC` - HEIC-Testbild
  - `IMG_7078.HEIC` - HEIC-Testbild

## 🚀 Test-Ausführung

### Alle Tests ausführen
```bash
./run-tests.sh
# oder
./run-tests.sh test
# oder
./run-tests.sh all
```

### Spezifische Test-Suites
```bash
# Umfassende Tests (alle Funktionen)
./run-tests.sh comprehensive

# Upload-Funktionalität
./run-tests.sh upload

# Echte Bild-Upload-Tests
./run-tests.sh real-images

# Bildrotation-Analyse
./run-tests.sh rotation-analysis

# Foto-Rotation-Funktionalität
./run-tests.sh rotation

# Integrationstests
./run-tests.sh integration

# Event-Konfiguration-Tests
./run-tests.sh event-config

# Event-Management-Tests
./run-tests.sh event-mgmt

# Display-Konfiguration-Tests
./run-tests.sh display-config

# Schnelle Tests (nur umfassende Tests)
./run-tests.sh quick
```

### Weitere Optionen
```bash
# PHP-Syntax-Prüfung
./run-tests.sh syntax

# Watch-Modus (Tests bei Dateiänderungen)
./run-tests.sh watch

# Hilfe anzeigen
./run-tests.sh help
```

### Direkte PHP-Ausführung
```bash
# Einzelne Test-Datei ausführen
php ComprehensiveTests.php
php SimpleIntegrationTests.php
php UploadFunctionalityTests.php
php RealImageUploadTests.php
php ImageRotationAnalysisTests.php
php PhotoRotationTests.php
php EventConfigurationTests.php
php EventManagementTests.php
php DisplayConfigurationTests.php
```

## 🧪 Test-Kategorien

### 1. ComprehensiveTests.php
Testet alle Funktionen der Anwendung:
- **Version-Funktionen** - `getCurrentVersion()`
- **CSRF-Token** - `generateCSRFToken()`, `validateCSRFToken()`
- **Event-Hash** - `generateEventHash()`, `getEventByHash()`
- **Event-Slug** - `generateSlug()`, `validateSlug()`, `isSlugUnique()`
- **Datei-Upload** - `validateFileUpload()`, `generateUniqueFilename()`, `calculateFileHash()`
- **Bildverarbeitung** - `resizeImage()`, `createThumbnail()`, `autoRotateImage()`
- **Response-Funktionen** - `sendJSONResponse()`, `sendErrorResponse()`
- **Utility-Funktionen** - `sanitizeInput()`
- **Session-Funktionen** - `setUserSession()`, `getUserSession()`
- **Geo-Utils** - `calculateDistance()`, `isWithinRadius()`, `validateCoordinates()`
- **Datenbank** - `Database`-Klasse und Methoden

### 2. SimpleIntegrationTests.php
Fokus auf Kernfunktionalität ohne Datenbank-Abhängigkeiten:
- **Bildverarbeitung** - Thumbnail-Erstellung, Resize, verschiedene Formate
- **Datei-Handling** - Hash-Berechnung, eindeutige Dateinamen
- **Fehlerbehandlung** - Ungültige Dateien, nicht existierende Dateien

### 3. UploadFunctionalityTests.php
Spezifische Upload-Tests:
- **Datei-Validierung** - JPEG, PNG, HEIC, GIF, WebP
- **Bildverarbeitung** - Thumbnails, Resize, Rotation
- **Sicherheit** - Dateityp-Validierung, Größenbeschränkungen
- **Performance** - Große Dateien, Batch-Uploads
- **Format-Unterstützung** - Verschiedene Bildformate

### 4. RealImageUploadTests.php
Tests mit echten Bilddateien:
- **Echte Bild-Validierung** - Tests mit `pics/`-Dateien
- **Echte Bildverarbeitung** - Thumbnails und Resize mit echten Bildern
- **Format-spezifische Tests** - JPEG und HEIC-Verarbeitung
- **Performance-Tests** - Echte Dateigrößen und Verarbeitungszeiten

### 5. ImageRotationAnalysisTests.php
EXIF-Daten und Bildrotation:
- **EXIF-Analyse** - Orientierung aus Metadaten lesen
- **Rotation-Erkennung** - Automatische Rotation basierend auf EXIF
- **HEIC-Unterstützung** - Spezielle Tests für HEIC-Dateien

### 6. PhotoRotationTests.php
Manuelle Foto-Rotation:
- **Rotations-Funktionen** - `rotateImage()`-Funktionalität
- **Validierung** - Winkel-Validierung, Datei-Validierung
- **Format-Unterstützung** - Verschiedene Bildformate
- **Fehlerbehandlung** - Ungültige Parameter, Dateifehler

### 7. EventConfigurationTests.php
Event-Konfigurationsfeatures:
- **Display-Konfiguration** - `display_mode`, `display_count`, `display_interval`, `layout_type`, `grid_columns`
- **Overlay-Einstellungen** - `show_username`, `show_date`, `overlay_opacity`
- **Upload-Konfiguration** - `max_upload_size`, `gps_validation_required`, `moderation_required`
- **Anzeige-Optionen** - `show_logo`, `show_qr_code`, `show_display_link`, `show_gallery_link`, `is_active`
- **Event-Validierung** - Name, Radius, Slug, Notizen

### 8. EventManagementTests.php
Event-Management-Funktionalität:
- **Event-Erstellung** - Grunddaten, Slug-Generierung, Validierung
- **Event-Bearbeitung** - Konfigurations-Updates, Slug-Updates, GPS-Updates
- **Event-Status** - Aktiv/Inaktiv-Toggle, Status-Validierung
- **Event-Validierung** - Pflichtfelder, GPS-Validierung, Radius-Validierung
- **Event-Slug** - Eindeutigkeit, reservierte Wörter, Format-Validierung
- **Event-Verzeichnisse** - Pfad-Generierung, Verzeichnis-Erstellung, URL-Generierung

### 9. DisplayConfigurationTests.php
Display-spezifische Konfiguration:
- **Display-Modi** - Random, Newest, Chronological
- **Display-Anzahl** - Gültige Werte, Clamping, Single/Grid-Display
- **Display-Intervall** - Gültige Intervalle, Clamping, Performance
- **Layout-Typen** - Single, Grid
- **Grid-Konfiguration** - Spalten-Anzahl, Clamping, Responsive
- **Overlay-Konfiguration** - Username, Datum, Transparenz
- **Display-Optionen** - Logo, QR-Code, Links
- **URL-Parameter** - show_logo, display_count, display_mode, display_interval

## 🔧 Test-Framework

### TestRunner-Klasse
- Leichtgewichtiges PHP-Test-Framework
- Keine externen Abhängigkeiten
- Unterstützt verschiedene Assertion-Funktionen
- Detaillierte Ausgabe mit Zeitmessung

### Assertion-Funktionen
- `assertTrue($condition, $message)` - Bedingung muss wahr sein
- `assertFalse($condition, $message)` - Bedingung muss falsch sein
- `assertEquals($expected, $actual, $message)` - Werte müssen gleich sein
- `assertNotEquals($expected, $actual, $message)` - Werte müssen ungleich sein
- `assertContains($needle, $haystack, $message)` - String muss enthalten sein
- `assertFileExists($file, $message)` - Datei muss existieren
- `assertFileNotExists($file, $message)` - Datei darf nicht existieren

## 📊 Test-Ergebnisse

### Ausgabe-Format
```
🧪 Running PC PhotoWall Tests...

✅ Version Functions - getCurrentVersion (2.45ms)
✅ CSRF Token - generateCSRFToken (1.23ms)
❌ File Upload - validateFileUpload Invalid Type: Dateityp nicht erlaubt
💥 Image Processing - resizeImage: Exception - Invalid image file

📊 Test Results: 45 passed, 3 failed
❌ Some tests failed!
```

### Log-Dateien
- Test-Ergebnisse werden in `../logs/test-results.log` gespeichert
- Detaillierte Fehlermeldungen und Stack-Traces
- Zeitstempel für jeden Test-Lauf

## 🛠️ Entwicklung

### Neue Tests hinzufügen
1. Test-Datei bearbeiten (z.B. `ComprehensiveTests.php`)
2. Neue Test-Methode in entsprechender Kategorie hinzufügen
3. `$this->testRunner->addTest()` verwenden
4. Assertion-Funktionen für Validierung nutzen

### Test-Daten
- Temporäre Dateien werden in `data/` erstellt
- Automatische Bereinigung nach Tests
- Echte Test-Bilder in `pics/` verwenden

### Watch-Modus
- Automatische Test-Ausführung bei Dateiänderungen
- Erfordert `fswatch` (macOS: `brew install fswatch`)
- Überwacht alle `.php`-Dateien im Projekt

## 🔍 Troubleshooting

### Häufige Probleme
1. **EXIF-Extension fehlt** - `exif_read_data()` nicht verfügbar
2. **GD-Extension fehlt** - Bildverarbeitung nicht möglich
3. **Berechtigungen** - Schreibrechte für `data/` und `logs/` Ordner
4. **Speicher-Limit** - Große Bilder können Speicher-Limit überschreiten

### Debug-Modus
- `TestRunner(true)` für detaillierte Ausgabe
- Einzelne Tests mit `php -d display_errors=1 TestFile.php`
- Log-Dateien in `logs/` überprüfen

## 📈 Performance

### Test-Optimierung
- Parallele Test-Ausführung möglich
- Schnelle Tests mit `./run-tests.sh quick`
- Watch-Modus für kontinuierliche Entwicklung
- Speicher-optimierte Bildverarbeitung

### Monitoring
- Test-Dauer wird gemessen und angezeigt
- Speicherverbrauch kann überwacht werden
- Log-Dateien für Performance-Analyse

## 🔒 Sicherheit

### Test-Sicherheit
- Isolierte Test-Umgebung
- Automatische Bereinigung von Test-Dateien
- Keine echten Datenbank-Operationen
- Sichere Datei-Validierung

### Validierung
- XSS-Schutz-Tests
- Datei-Typ-Validierung
- Größenbeschränkungen
- CSRF-Token-Validierung

---

**Hinweis:** Diese Test-Suite ist darauf ausgelegt, ohne externe Abhängigkeiten zu funktionieren und alle kritischen Funktionen der PC PhotoWall-Anwendung zu testen.
