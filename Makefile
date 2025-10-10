# Picturewall Project Makefile

.PHONY: help up down restart logs clean setup status dev-up dev-down dev-restart dev-logs dev-status

# Default target
help:
	@echo "Picturewall Project Commands:"
	@echo "  make up       - Start the project (docker-compose up -d)"
	@echo "  make down     - Stop the project (docker-compose down)"
	@echo "  make restart  - Restart the project"
	@echo "  make logs     - Show logs from all services"
	@echo "  make clean    - Stop and remove containers, networks, and volumes"
	@echo "  make setup    - Initial setup (copy .env.example to .env)"
	@echo "  make status   - Show status of containers"
	@echo ""
	@echo "Development Commands (with phpMyAdmin):"
	@echo "  make dev-up     - Start development environment with phpMyAdmin"
	@echo "  make dev-down   - Stop development environment"
	@echo "  make dev-restart - Restart development environment"
	@echo "  make dev-logs   - Show logs from development services"
	@echo "  make dev-status - Show status of development containers"
	@echo ""
	@echo "  make help     - Show this help message"

# Start the project
up:
	@echo "Starting Picturewall project..."
	docker-compose up -d
	@echo "Project started! Access at http://localhost:4000"

# Stop the project
down:
	@echo "Stopping Picturewall project..."
	docker-compose down
	@echo "Project stopped."

# Restart the project
restart: down up

# Show logs
logs:
	docker-compose logs -f

# Clean everything (containers, networks, volumes)
clean:
	@echo "Cleaning up Picturewall project..."
	docker-compose down -v --remove-orphans
	docker system prune -f
	@echo "Cleanup completed."

# Initial setup
setup:
	@if [ ! -f .env ]; then \
		echo "Creating .env file from .env.example..."; \
		cp .env.example .env; \
		echo ".env file created. Please review and adjust settings if needed."; \
	else \
		echo ".env file already exists."; \
	fi
	@echo "Setup completed."

# Show container status
status:
	@echo "Container status:"
	docker-compose ps

# Development environment commands (with phpMyAdmin)

# Start development environment
dev-up:
	@echo "Starting Picturewall development environment with phpMyAdmin..."
	docker-compose -f docker-compose.dev.yml up -d
	@echo "Development environment started!"
	@echo "Web application: http://localhost:4000"
	@echo "phpMyAdmin: http://localhost:8081"

# Stop development environment
dev-down:
	@echo "Stopping Picturewall development environment..."
	docker-compose -f docker-compose.dev.yml down
	@echo "Development environment stopped."

# Restart development environment
dev-restart: dev-down dev-up

# Show development logs
dev-logs:
	docker-compose -f docker-compose.dev.yml logs -f

# Show development container status
dev-status:
	@echo "Development container status:"
	docker-compose -f docker-compose.dev.yml ps
