# PC PhotoWall Projekt Makefile

# Docker Compose Befehl automatisch erkennen (docker-compose vs docker compose)
DOCKER_COMPOSE := $(shell which docker-compose 2>/dev/null)
ifeq ($(DOCKER_COMPOSE),)
	DOCKER_COMPOSE := docker compose
endif

.PHONY: help clean setup prod-up prod-down prod-restart prod-logs prod-status dev-up dev-down dev-restart dev-logs dev-status test test-quick test-syntax backup backup-all restore restore-db list-backups

# Standard-Ziel
help:
	@echo "╔══════════════════════════════════════════════════╗"
	@echo "║           PC PhotoWall - Make Commands           ║"
	@echo "╚══════════════════════════════════════════════════╝"
	@echo ""
	@echo "Setup & Build:"
	@echo "  make clean              - Vollständiger Reset: Dev + Prod Container, Netzwerke, Volumes + Upload-Daten löschen"
	@echo "  make setup              - Initiales Setup (.env.example nach .env kopieren)"
	@echo ""
	@echo "Development (mit phpMyAdmin):"
	@echo "  make dev-up             - Entwicklungsumgebung mit phpMyAdmin starten"
	@echo "  make dev-down           - Entwicklungsumgebung stoppen"
	@echo "  make dev-restart        - Entwicklungsumgebung neustarten"
	@echo "  make dev-logs           - Logs der Entwicklungs-Services anzeigen"
	@echo "  make dev-status         - Status der Entwicklungs-Container anzeigen"
	@echo ""
	@echo "Production:"
	@echo "  make prod-up            - Produktionsumgebung starten"
	@echo "  make prod-down          - Produktionsumgebung stoppen"
	@echo "  make prod-restart       - Produktionsumgebung neustarten"
	@echo "  make prod-logs          - Logs aller Produktions-Services anzeigen"
	@echo "  make prod-status        - Status der Produktions-Container anzeigen"
	@echo ""
	@echo "Testing (benötigt laufende Dev-Umgebung):"
	@echo "  make test                    - Alle Tests ausführen (benötigt: make dev-up)"
	@echo "  make test-quick              - Schnelltests (benötigt: make dev-up)"
	@echo "  make test-syntax             - PHP-Syntax-Prüfung (keine Dev-Umgebung nötig)"
	@echo ""
	@echo "Backup & Restore:"
	@echo "  make backup [slug]           - Spezifisches Event sichern (Bilder + Datenbank)"
	@echo "  make backup-all              - Alle Events und komplette Datenbank sichern"
	@echo "  make restore [pfad]          - Aus Backup-Datei wiederherstellen (Bilder + DB)"
	@echo "  make restore-db [pfad]       - Nur Datenbank aus Backup wiederherstellen"
	@echo "  make list-backups            - Alle verfügbaren Backups auflisten"

# Produktions-Befehle

# Produktionsumgebung starten
prod-up:
	@echo "Starte PC PhotoWall Produktionsumgebung..."
	$(DOCKER_COMPOSE) up -d
	@echo "Produktionsumgebung gestartet! Erreichbar unter http://localhost:4000"

# Produktionsumgebung stoppen
prod-down:
	@echo "Stoppe PC PhotoWall Produktionsumgebung..."
	$(DOCKER_COMPOSE) down
	@echo "Produktionsumgebung gestoppt."

# Produktionsumgebung neustarten
prod-restart: prod-down prod-up

# Logs anzeigen
prod-logs:
	$(DOCKER_COMPOSE) logs -f

# Vollständiger Reset: Dev + Prod + Daten löschen
clean:
	@if [ "$(FORCE)" != "yes" ]; then \
		echo "⚠️  WARNUNG: Vollständiger Reset (Dev + Prod)!"; \
		echo "   Dies wird:"; \
		echo "   - Alle Dev- und Prod-Container stoppen und Volumes entfernen"; \
		echo "   - ALLE hochgeladenen Fotos und Daten löschen"; \
		echo "   - Docker-System bereinigen"; \
		echo ""; \
		echo "   Ergebnis: Neuinstallation mit Demo-Event beim nächsten Start"; \
		echo ""; \
		read -p "Sind Sie sicher? Geben Sie 'yes' ein zum Fortfahren: " confirm; \
		if [ "$$confirm" != "yes" ]; then \
			echo "Abgebrochen."; \
			exit 1; \
		fi; \
		echo ""; \
	fi
	@echo "1/4 Stoppe und entferne Produktionsumgebung..."
	@$(DOCKER_COMPOSE) down -v --remove-orphans 2>/dev/null || true
	@echo "2/4 Stoppe und entferne Entwicklungsumgebung..."
	@$(DOCKER_COMPOSE) -f docker-compose.dev.yml down -v --remove-orphans 2>/dev/null || true
	@echo "3/4 Entferne hochgeladene Daten und Logs..."
	@rm -rf app/data/*/ 2>/dev/null || true
	@rm -rf app/uploads/*/ 2>/dev/null || true
	@rm -rf app/logs/*.log 2>/dev/null || true
	@echo "4/4 Bereinige Docker-System..."
	@docker system prune -f > /dev/null 2>&1
	@echo ""
	@echo "✓ Vollständiger Reset abgeschlossen!"
	@echo "  Führen Sie 'make dev-up' oder 'make up' aus, um neu zu starten."

# Initiales Setup
setup:
	@if [ ! -f .env ]; then \
		echo "Erstelle .env-Datei aus .env.example..."; \
		cp .env.example .env; \
		echo ".env-Datei erstellt. Bitte Einstellungen prüfen und bei Bedarf anpassen."; \
	else \
		echo ".env-Datei existiert bereits."; \
	fi
	@echo "Setup abgeschlossen."

# Container-Status anzeigen
prod-status:
	@echo "Container-Status:"
	$(DOCKER_COMPOSE) ps

# Entwicklungs-Befehle (mit phpMyAdmin)

# Entwicklungsumgebung starten
dev-up:
	@echo "Starte PC PhotoWall Entwicklungsumgebung mit phpMyAdmin..."
	$(DOCKER_COMPOSE) -f docker-compose.dev.yml up -d
	@echo "Entwicklungsumgebung gestartet!"
	@echo "Webanwendung: http://localhost:4000"
	@echo "phpMyAdmin: http://localhost:8081"

# Entwicklungsumgebung stoppen
dev-down:
	@echo "Stoppe PC PhotoWall Entwicklungsumgebung..."
	$(DOCKER_COMPOSE) -f docker-compose.dev.yml down
	@echo "Entwicklungsumgebung gestoppt."

# Entwicklungsumgebung neustarten
dev-restart: dev-down dev-up

# Entwicklungs-Logs anzeigen
dev-logs:
	$(DOCKER_COMPOSE) -f docker-compose.dev.yml logs -f

# Entwicklungs-Container-Status anzeigen
dev-status:
	@echo "Entwicklungs-Container-Status:"
	@$(DOCKER_COMPOSE) -f docker-compose.dev.yml ps

# Test-Befehle

# Alle Tests ausführen
test:
	@echo "Führe alle PC PhotoWall-Tests aus..."
	./tests/run-tests.sh test

# Schnelltests ausführen (ohne Integration/Real Images)
test-quick:
	@echo "Führe Schnelltests aus..."
	./tests/run-tests.sh quick

# PHP-Syntax-Prüfung ausführen
test-syntax:
	@echo "Führe PHP-Syntax-Prüfung aus..."
	./tests/run-tests.sh syntax

# Backup & Wiederherstellungs-Befehle

# Spezifisches Event sichern
backup:
	@EVENT_SLUG="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$EVENT_SLUG" ] && [ -z "$(EVENT)" ]; then \
		echo "Fehler: Event-Slug erforderlich"; \
		echo "Verwendung: make backup [event-slug]"; \
		echo "       oder: make backup EVENT=event-slug"; \
		exit 1; \
	fi; \
	EVENT_SLUG="$${EVENT_SLUG:-$(EVENT)}"; \
	echo "Erstelle Backup für Event: $$EVENT_SLUG"; \
	./scripts/backup-event.sh "$$EVENT_SLUG"

# Alle Events sichern
backup-all:
	@echo "Erstelle vollständiges Backup aller Events..."
	./scripts/backup-event.sh --all

# Aus Backup wiederherstellen (vollständig: Bilder + DB)
restore:
	@BACKUP_FILE="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$BACKUP_FILE" ] && [ -z "$(FILE)" ]; then \
		echo "Fehler: Backup-Dateipfad erforderlich"; \
		echo "Verwendung: make restore [pfad/zur/backup.tar.gz]"; \
		echo "       oder: make restore FILE=pfad/zur/backup.tar.gz"; \
		exit 1; \
	fi; \
	BACKUP_FILE="$${BACKUP_FILE:-$(FILE)}"; \
	echo "Stelle aus Backup wieder her: $$BACKUP_FILE"; \
	./scripts/restore-event.sh "$$BACKUP_FILE"

# Nur Datenbank aus Backup wiederherstellen
restore-db:
	@BACKUP_FILE="$(filter-out $@,$(MAKECMDGOALS))"; \
	if [ -z "$$BACKUP_FILE" ] && [ -z "$(FILE)" ]; then \
		echo "Fehler: Backup-Dateipfad erforderlich"; \
		echo "Verwendung: make restore-db [pfad/zur/backup.tar.gz]"; \
		echo "       oder: make restore-db FILE=pfad/zur/backup.tar.gz"; \
		exit 1; \
	fi; \
	BACKUP_FILE="$${BACKUP_FILE:-$(FILE)}"; \
	echo "Stelle nur Datenbank wieder her: $$BACKUP_FILE"; \
	./scripts/restore-db-only.sh "$$BACKUP_FILE"

# Alle Backups auflisten
list-backups:
	@echo "Verfügbare Backups:"
	@if [ -d backups ] && [ -n "$$(ls -A backups 2>/dev/null)" ]; then \
		ls -lh backups/*.tar.gz 2>/dev/null | awk '{print "  " $$9 " (" $$5 ") - " $$6 " " $$7 " " $$8}' || echo "  Keine Backups gefunden"; \
	else \
		echo "  Kein Backup-Verzeichnis oder keine Backups gefunden"; \
	fi

# Catch-all-Regel, um "No rule to make target"-Fehler für positionelle Argumente zu vermeiden
%:
	@:
