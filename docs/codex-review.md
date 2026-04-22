---
status: passed
date: 2026-04-22
reviewer: codex
rounds: 6/6
---

# Codex-Review — pcPhotoWall (gesamte Codebase)

## Scope

Iterativer Codex-Review der gesamten Codebase unter `app/` nach einem manuellen Security-Commit (9812897, „fix CSRF, XSS, auth, and info-disclosure issues; bump 1.9.0"). Codex wurde agentisch über `codex exec` aufgerufen, Ergebnisse in 6 Runden bis `APPROVED` verifiziert.

**Abgedeckte Bereiche:** `app/admin/`, `app/api/`, `app/config/`, `app/includes/`, `app/.htaccess`, `app/data/.htaccess`, `app/gallery.php`, `app/display.php`, `app/index.php`, `.gitignore`, `.env.example`, `docker-compose*.yml`.

## Rundenverlauf

| Runde | Verdict  | Findings (H/M/N) | Kommentar |
|-------|----------|-------------------|-----------|
| 1     | REVISE   | 2 / 4 / 2         | PHP-Polyglot-Upload, Gallery-XSS, Event-Löschpfade, GPS-Leak, Error-Disclosure, Session-Timeout, TypeError, manifest.json |
| 2     | REVISE   | 0 / 4 / 1         | gallery.js-GPS, HEIC-MIME, Logo-Validation, .gitignore, Thumbnail-Endung |
| 3     | REVISE   | 0 / 2 / 2         | HEIC ISO-BMFF, Upload-Cleanup, event-config-Contract, UI-Text SVG |
| 4     | REVISE   | 0 / 1 / 1         | GPS-TypeError mit null, Logo-Orphans |
| 5     | REVISE   | 0 / 1 / 2         | Logo-Transaktionssicherheit, 0-Koordinaten, delete_logo-Flow |
| 6     | **APPROVED** | 0 / 0 / 0     | Alle Runde-5-Fixes verifiziert |

Insgesamt **18 Findings** über 5 Review-Runden behoben.

## Vorgenommene Korrekturen

### Runde 1
1. **Upload Extension-Whitelist + Script-Block in `data/`** — `validateFileUpload` prüft Endung zusätzlich zur MIME; `app/.htaccess` blockt `data/**/*.php|phtml|phar|phps|pl|py|cgi|sh`; zweite Schutzschicht via `app/data/.htaccess` mit `php_flag engine off`
2. **Gallery-XSS** — Inline `onclick` ersetzt durch `data-lightbox-*` Attributes + JS-Event-Binding; alle Photo-Felder per `htmlspecialchars(ENT_QUOTES, UTF-8)`
3. **Event-Löschung** — nutzt jetzt `getEventUploadPaths($event_slug)` und räumt `photos/`, `thumbnails/`, `logos/` + Logo-Datei + leere Directories auf
4. **GPS-Leak** — `app/api/photos.php` liefert weder `latitude`/`longitude` im `photos[]`-Array noch `event.latitude`/`event.longitude`
5. **Error-Disclosure** — `$e->getMessage()` aus allen Client-Responses entfernt (`app/index.php:12`, `app/api/photos.php:123`, `app/api/event-config.php:41`, `app/api/upload.php:330`, `app/gallery.php:100`, `app/admin/event-photos.php:114`)
6. **Admin-Session-Timeout** — neue `isAdminSessionValid()` mit `admin_last_activity`-Ablauf, in allen Admin-Endpoints (`create-event`, `edit-event`, `event-photos`, `api/rotate-photo`, `api/toggle-photo-status`) eingesetzt
7. **`sendSuccessResponse`-Argumente** in `event-config.php` getauscht
8. **`manifest.json`** im DocumentRoot angelegt

### Runde 2
9. **Gallery-HTML GPS-Leak** — `app/gallery.php` SELECT ohne `latitude`/`longitude` → nicht mehr in `window.galleryConfig.photos`
10. **HEIC-MIME-Whitelist strenger** + `validateLogoUpload()`-Helper (MIME + Extension + `getimagesize()` + 2 MB) in `functions.php`
11. **Upload-Temp-Cleanup** in `api/upload.php` für alle HEIC-Fehlerpfade
12. **`.gitignore`** `/app/data/*` + `!/app/data/.htaccess`
13. **Thumbnail JPEG** — `ImageProcessor::createThumbnail` schreibt jetzt immer echtes JPEG mit Alpha-Flatten, nicht mehr Quell-MIME

### Runde 3
14. **HEIC ISO-BMFF `ftyp`-Brand-Check** — neue `isIsoBmffHeic()` akzeptiert `application/octet-stream` nur bei korrektem Container-Header (`heic`/`heix`/`hevc`/`hevx`/`mif1`/`msf1`/`heis`/`hevs`)
15. **Upload-Cleanup zentral** — `$createdFiles` + `$registerFile` + `$cleanupFiles` + `$failAndCleanup` in `api/upload.php`, deckt GPS-Radius-Fehler, HEIC-Konvertierungsfehler und DB-Exception ab. Nach erfolgreichem DB-Insert wird Tracking geleert.
16. **`event-config.php` Frontend-Contract** — zurück zu `sendJSONResponse` mit `event` top-level, passend zu `app/assets/js/app.js:126`
17. **Logo-UI** — SVG-Hinweis aus `create-event.php` und `edit-event.php` entfernt

### Runde 4
18. **GPS-Null-Check** vor `GeoUtils::validateCoordinates()` in beiden Admin-Formularen — kein TypeError bei leeren Koordinaten-Feldern
19. **Logo-Orphans im `catch`** — create-event und edit-event entfernen das frisch verschobene Logo bei DB-Exception

### Runde 5
20. **DB-Transaktionen** für `events` + `display_config` in create-event und edit-event mit `beginTransaction`/`commit`/`rollBack` — altes Logo wird erst nach `commit()` entfernt
21. **`empty()` → `isset() && !== ''`** für `$_POST['latitude']`/`['longitude']` — Äquator/Nullmeridian werden korrekt gespeichert
22. **`delete_logo`-Flow** — eigener POST-Pfad vor der Event-Update-Validierung, mit `header('Location: ...'); exit;`

## Finale Findings

**Keine.** Runde 6 lieferte `VERDICT: APPROVED`.

## Kurzfazit

Die Codebase wurde in 6 Runden von 12 manuell identifizierten auf insgesamt 18 behobene Findings gebracht. Nach Runde 6 bestätigt Codex, dass keine blockierenden Security- oder Bug-Lücken verbleiben, alle PHP-Dateien syntaktisch sauber sind und die Codebase für einen Release-Cut ausreichend ist.

**Empfehlung für Deployment:** `ADMIN_PASSWORD_HASH` und separates `DB_ROOT_PASS` in `.env` setzen, `app/data/.htaccess` wurde in Runde 1 angelegt und ist jetzt auch git-versionierbar.
