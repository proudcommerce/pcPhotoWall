# Picturewall

Ein Event-Foto-Sammlung und Display-System für Veranstaltungen mit GPS-Validierung und Live-Display.

## Features

- **Event-Management**: Erstelle und verwalte Events mit GPS-Koordinaten und Radius
- **Foto-Upload**: Drag & Drop Upload mit GPS-Validierung und automatischem Upload
- **Live-Display**: Automatische Foto-Anzeige mit konfigurierbaren Einstellungen
- **Galerie**: Event-spezifische Galerie mit Thumbnails
- **Bildmoderation**: Optional aktivierbare Moderation für Uploads
- **Admin-Interface**: Vollständige Verwaltung über Web-Interface
- **Responsive Design**: Mobile-optimiert für alle Geräte
- **Duplikat-Erkennung**: Automatische Erkennung basierend auf Datei-Hash
- **Sicherheit**: CSRF-Schutz und sichere Session-Verwaltung

## Technische Details

- **Backend**: PHP 8.4+ mit MySQL/MariaDB
- **Frontend**: Vanilla JavaScript, CSS3
- **Container**: Docker mit Apache und MariaDB
- **Entwicklung**: phpMyAdmin für Datenbankverwaltung
- **Upload**: Unterstützt JPG, PNG, GIF, WebP, HEIC, HEIF
- **GPS**: Automatische GPS-Koordinaten-Validierung
- **Bildverarbeitung**: ImageMagick für Thumbnails und Konvertierung

## Installation

### Voraussetzungen

- Docker und Docker Compose
- Make (optional, für einfache Befehle)

### Setup

1. **Repository klonen**
   ```bash
   git clone https://github.com/proudcommerce/event-picturewall.git
   cd picturewall
   ```

2. **Umgebungsvariablen konfigurieren**
   ```bash
   make setup
   # oder manuell:
   cp .env.example .env
   ```

3. **Projekt starten**
   ```bash
   # Produktionsumgebung
   make up
   # oder manuell:
   docker-compose up -d
   
   # Entwicklungsumgebung (mit phpMyAdmin)
   make dev-up
   # oder manuell:
   docker-compose -f docker-compose.dev.yml up -d
   ```

4. **Zugriff**
   - Hauptseite: http://localhost:4000
   - Admin-Bereich: http://localhost:4000/admin/
   - Display-Modus: http://localhost:4000/display.php
   - **phpMyAdmin** (nur Dev): http://localhost:8081

## Konfiguration

### Umgebungsvariablen (.env)

```env
# Datenbank
DB_HOST=db
DB_NAME=picturewall
DB_USER=picturewall
DB_PASS=picturewall
MYSQL_ROOT_PASSWORD=picturewall

# App
APP_NAME=Picturewall
APP_URL=http://localhost:4000
APP_ENV=development
ADMIN_PASSWORD=admin123

# Upload
UPLOAD_ALLOWED_TYPES=image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif
```

### Event-Konfiguration

Jedes Event kann individuell konfiguriert werden:

- **GPS-Validierung**: Erforderlich oder optional
- **Display-Modus**: Random, Newest, Chronological
- **Layout**: Single oder Grid
- **Anzeige-Intervall**: 3-60 Sekunden
- **Grid-Spalten**: 2-6 Spalten
- **Overlay-Einstellungen**: Username, Datum, Opacity
- **Logo**: Event-spezifisches Logo
- **Bildmoderation**: Aktivierung der Moderation für Uploads
- **Galerie-Links**: Optional anzeigen bei Upload
- **Display-Links**: Optional anzeigen bei Upload

#### URL-Parameter für Display-Konfiguration

Die Display-Seite unterstützt folgende URL-Parameter:

- `show_logo=0|1` - Event-Logo anzeigen (0=nein, 1=ja)
- `display_count=1|2|3|4|6|8|10|12` - Anzahl der gleichzeitig angezeigten Bilder
- `display_mode=random|newest|chronological` - Anzeige-Modus
- `display_interval=10` - Anzeige-Intervall in Sekunden

**Beispiel-URL:**
```
http://localhost:4000/[event-slug]/display?show_logo=0&display_count=1&display_mode=random&display_interval=10
```

## Verwendung

### Event erstellen

1. Admin-Bereich aufrufen: http://localhost:4000/admin/
2. "Neues Event erstellen" klicken
3. Event-Details eingeben:
   - Name und Beschreibung
   - GPS-Koordinaten (Latitude/Longitude)
   - Radius in Metern
   - Display-Einstellungen
4. Event aktivieren

### Fotos hochladen

1. Event-Seite aufrufen: http://localhost:4000/[event-slug]
2. Username eingeben (optional)
3. Foto per Drag & Drop oder Klick hochladen
4. GPS-Validierung erfolgt automatisch
5. Upload erfolgt automatisch nach Bildauswahl
6. Duplikate werden automatisch erkannt und verhindert

### Galerie anzeigen

1. Galerie-Seite aufrufen: http://localhost:4000/[event-slug]/gallery
2. Alle Event-Fotos werden als Thumbnails angezeigt
3. Event-Logo wird oben angezeigt (falls konfiguriert)

### Display-Modus

1. Display-Seite aufrufen: http://localhost:4000/[event-slug]/display
2. Fotos werden automatisch im konfigurierten Intervall angezeigt
3. Vollbild-Modus für Präsentationen
4. Smartphone-optimierte Anzeige

## Makefile-Befehle

### Produktionsumgebung
```bash
make help      # Hilfe anzeigen
make up        # Projekt starten
make down      # Projekt stoppen
make restart   # Projekt neu starten
make logs      # Logs anzeigen
make clean     # Alles löschen (Container, Volumes)
make setup     # Initiales Setup
make status    # Container-Status anzeigen
```

### Entwicklungsumgebung (mit phpMyAdmin)
```bash
make dev-up     # Entwicklungsumgebung starten
make dev-down   # Entwicklungsumgebung stoppen
make dev-restart # Entwicklungsumgebung neu starten
make dev-logs   # Logs der Entwicklungsumgebung anzeigen
make dev-status # Status der Dev-Container anzeigen
```

## Projektstruktur

```
picturewall/
├── admin/                    # Admin-Interface
├── api/                      # API-Endpunkte
├── assets/                   # CSS, JS, Bilder
├── config/                   # Konfigurationsdateien
├── data/                     # Upload-Verzeichnis
├── includes/                 # PHP-Funktionen
├── uploads/                  # Event-spezifische Uploads
├── docker-compose.yml        # Docker-Konfiguration (Produktion)
├── docker-compose.dev.yml    # Docker-Konfiguration (Entwicklung)
├── Dockerfile                # Docker-Image-Definition
├── Makefile                  # Automatisierte Befehle
├── composer.json             # PHP-Abhängigkeiten
├── CHANGELOG.md              # Versionshistorie
└── README.md                 # Diese Datei
```

## API-Endpunkte

- `GET /api/photos.php` - Fotos für Event abrufen (mit Thumbnails)
- `POST /api/upload.php` - Foto hochladen (mit Duplikat-Erkennung)
- `POST /api/toggle-photo-status.php` - Foto-Status ändern (Moderation)
- `GET /api/event-config.php` - Event-Konfiguration abrufen
- `GET /api/csrf-token.php` - CSRF-Token abrufen

## Sicherheit

- CSRF-Token-Schutz für alle POST-Requests
- Sichere Session-Verwaltung
- Datei-Typ-Validierung
- GPS-Koordinaten-Validierung
- SQL-Injection-Schutz durch Prepared Statements
- Duplikat-Erkennung basierend auf Datei-Hash
- Optional aktivierbare Bildmoderation

## Entwicklung

### Lokale Entwicklung

```bash
# Entwicklungsumgebung mit phpMyAdmin starten
make dev-up

# Logs verfolgen
make dev-logs

# Änderungen testen
# Code in /var/www/html wird automatisch gemountet

# Datenbankverwaltung
# phpMyAdmin: http://localhost:8081
# Login mit DB_USER/DB_PASS aus .env
```

### Datenbankverwaltung

Für die Entwicklung steht phpMyAdmin zur Verfügung:
- **URL**: http://localhost:8081 (nur in Dev-Umgebung)
- **Login**: Verwenden Sie die DB_USER/DB_PASS aus der .env-Datei
- **Root-Zugang**: DB_PASS als Root-Passwort

### Debugging

- PHP-Fehler werden in `logs/php_errors.log` gespeichert
- Docker-Logs: `make logs` (Produktion) oder `make dev-logs` (Entwicklung)
- Browser-Entwicklertools für Frontend-Debugging
- Datenbank-Debugging über phpMyAdmin (nur Dev)
- Versionsnummer wird im Footer angezeigt

## Troubleshooting

### Häufige Probleme

1. **Port bereits belegt**
   ```bash
   # Port in docker-compose.yml oder docker-compose.dev.yml ändern
   ports:
     - "4001:80"  # Statt 4000
   ```

2. **Datenbank-Verbindungsfehler**
   ```bash
   # Container neu starten
   make restart        # Produktion
   make dev-restart    # Entwicklung
   ```

3. **phpMyAdmin nicht erreichbar**
   ```bash
   # Prüfen ob Dev-Umgebung läuft
   make dev-status
   
   # Falls Port 8081 belegt, in docker-compose.dev.yml ändern
   ports:
     - "8082:80"  # Statt 8081
   ```

4. **Upload-Fehler**
   - PHP-Upload-Limits in docker-compose.yml prüfen
   - Dateiberechtigungen für `data/` Verzeichnis prüfen
   - ImageMagick-Installation prüfen (für Thumbnails)

5. **GPS-Validierung funktioniert nicht**
   - HTTPS erforderlich für GPS-Zugriff
   - Browser-Berechtigungen prüfen

6. **Thumbnails werden nicht erstellt**
   - ImageMagick-Erweiterung prüfen: `docker exec -it [container] php -m | grep imagick`
   - Berechtigungen für Upload-Verzeichnis prüfen

7. **Duplikat-Erkennung funktioniert nicht**
   - Datenbank-Tabelle `photos` auf `file_hash` Spalte prüfen
   - Hash-Berechnung in PHP prüfen

## Lizenz

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

(c) Proud Commerce GmbH 2025