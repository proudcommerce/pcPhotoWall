# Changelog

## [1.9.1] - 2026-04-22

- Upload-Härtung: Extension-Whitelist zusätzlich zu MIME-Check, Script-Execution in `app/data/` per `.htaccess` (zwei Schichten) blockiert
- Gallery-XSS behoben: Inline-onclick durch `data-lightbox-*` Attribute + JS-Event-Binding ersetzt, alle Photo-Felder HTML-escaped
- Event-Löschung räumt `photos/`, `thumbnails/`, `logos/` inkl. Logo-Datei und leere Verzeichnisse vollständig auf
- GPS-Leak geschlossen: `api/photos.php` und `gallery.php` geben weder Foto- noch Event-Koordinaten an das Frontend aus
- Error-Disclosure entfernt: `$e->getMessage()` wird in keiner Client-Response mehr zurückgegeben
- Admin-Session-Timeout (`isAdminSessionValid()`) für alle Admin-Endpoints aktiv (create-event, edit-event, event-photos, rotate-photo, toggle-photo-status)
- HEIC-Upload: strengere MIME-Whitelist, neuer `validateLogoUpload()`-Helper (MIME + Extension + `getimagesize()` + 2 MB), ISO-BMFF `ftyp`-Brand-Check für `application/octet-stream`
- Upload-Cleanup zentralisiert: `$createdFiles` + `$registerFile` + `$cleanupFiles` + `$failAndCleanup` in `api/upload.php` — GPS-Radius-, HEIC-Konvertierungs- und DB-Fehler hinterlassen keine Orphans mehr
- Logo-Orphans beim Event-Update entfernt, Logo-Transaktionssicherheit und `delete_logo`-Flow korrigiert
- GPS-Prüfung akzeptiert 0-Koordinaten, kein TypeError bei `null`
- Thumbnails werden immer als echtes JPEG mit Alpha-Flatten geschrieben (`ImageProcessor::createThumbnail`)
- `app/manifest.json` im DocumentRoot ergänzt
- Neue Testsuiten: `SecurityUnitTests.php`, `SecurityIntegrationTests.php`, `SchemaIntegrityTests.php`
- Dokumentation aktualisiert: CLAUDE.md (SHA-256, `make restore-db`, `password_verify`/Session-Regeneration, neue Tests, Env-Vars), README.md (Version-Badge, `ADMIN_PASSWORD_HASH`, `DB_ROOT_PASS`)
- Codex-Review (6 Runden, 18 Findings behoben) unter `docs/codex-review.md` dokumentiert

## [1.9.0] - 2026-04-22

- CSRF-Validation in Admin-Endpoints (Event löschen, Foto löschen, Event erstellen) ergänzt
- Admin-Login auf password_hash / password_verify umgestellt (ADMIN_PASSWORD_HASH); Session-Regeneration nach Login
- Stored-XSS im Event-Notiz-Feld durch Plain-Text-Ausgabe behoben
- Session-Cookie-Flags (HttpOnly, Secure, SameSite) werden nicht mehr durch doppeltes session_start() umgangen
- SVG-Upload als Event-Logo entfernt (XSS-Vektor)
- Fehlermeldungen an unauthentifizierten Endpoints geben $e->getMessage() nicht mehr preis
- composer.json / composer.lock / vendor / includes / config über .htaccess geblockt
- Getrennte Env-Vars für DB-Root und App-User (DB_ROOT_PASS)
- .gitignore um Private-Keys und generische .env.* ergänzt

## [1.8.1] - 2025-10-21

- Docker Compose Erkennung verbessert
- Backup- und Restore-Scripte überarbeitet
- Problem Composer Installation behoben

## [1.8.0] - 2025-10-17

- Restore-Funktion nur Datenbank

## [1.7.0] - 2025-10-15

- Funktion zur Deaktivierung des Uploads
- Docker- und Datenbanksetup überarbeitet
- Backup- und Restore-Funktion integriert
- Demo-Event bei Erstinstallation hinzugefügt
- README aktualisiert

## [1.6.2] - 2025-10-13

- Galerie für Smartphones optimiert

## [1.6.1] - 2025-10-13

- Bild-Moderation wenn bei aktiver GPS-Prüfung keine Koordinaten gefunden werden

## [1.6.0] - 2025-10-11

- QR-Code für Display (optional)
- Bildrotation bei der Fotoverwaltung
- Umfassende Tests implementiert

## [1.5.1] - 2025-10-10

- GPS-Prüfung korrigiert
- Performance-Verbesserungen

## [1.5.0] - 2025-10-10

- Bildmoderation (optional)
- Thumbnails für Galerie und Verwaltung
- Display-Anzeige Smartphone optimiert
- Admin-Eventerstellung optimiert
- Display-Parameter überschreiben

## [1.4.0] - 2025-10-07

- Event-Einstellungen für Display/Gallery Links
- Gallerie/Display Links optional beim Upload
- Eventlogo in der Gallerie^

## [1.3.2] - 2025-10-06

- Imagick Installation angepasst

## [1.3.1] - 2025-09-29

- Uploadproblem behoben

## [1.3.0] - 2025-09-29

- Automatischer Upload nach Bildauswahl

## [1.2.0] - 2025-09-29

- Galerie-Seite für Event-Bilder

## [1.1.0] - 2025-09-28

- Duplikat-Erkennung basierend auf Datei-Hash
- Versionsnummer im Footer

## [1.0.0] - 2025-09-27

- Initiale Version des Picturewall-Systems
- Event-Management mit GPS-Validierung
- Foto-Upload mit HEIC/HEIF-Unterstützung
- Admin-Bereich für Event-Verwaltung
- Responsive Design für mobile Geräte
- CSRF-Schutz für alle Formulare
- Automatische Bildkonvertierung und -optimierung
