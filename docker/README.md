# Docker Configuration

This directory contains Docker-related configuration files for PC PhotoWall.

## Files

### apache-vhost.conf
Apache VirtualHost configuration for the web container. This file:
- Sets DocumentRoot to `/var/www/html`
- Enables `.htaccess` with `AllowOverride All`
- Adds security headers
- Configures mod_deflate for compression
- Sets up caching headers for static assets
- Denies access to sensitive directories

### mysql-data/
**Local MySQL database storage directory.** This directory:
- Contains all MariaDB database files
- Is mounted as volume in both production and development
- Persists data between container restarts
- Makes backups easy (just copy this directory)
- Is **ignored by Git** (except .gitkeep)
- Prevents accidental data loss from `docker volume rm`

**Important:** This is a local directory mount, NOT a Docker volume. Your database data is safely stored in your project directory and can be easily backed up, versioned, or migrated.

## Docker Setup Overview

### Production (docker-compose.yml + Dockerfile)
- **Multi-stage build** for optimized image size
- **Health checks** for automatic service recovery
- **Minimal runtime dependencies** (no build tools)
- **Volume mounts** only for uploads, data, and logs
- **Network isolation** with dedicated bridge network
- **Read-only .env** mount for security

### Development (docker-compose.dev.yml + Dockerfile.dev)
- **Xdebug** installed and configured
- **Full app directory** mounted for live code changes
- **phpMyAdmin** included on port 8081
- **Database port** exposed for external tools
- **Increased memory limit** (512M) for development
- **Error reporting** enabled

## Build Optimization

The production Dockerfile uses a two-stage build:

1. **Builder Stage**: Installs all build tools and PHP extensions, runs composer install
2. **Production Stage**: Copies only runtime libraries and compiled extensions from builder

This results in:
- ~30% smaller production images
- No build tools in production (security benefit)
- Faster subsequent builds due to layer caching
- Composer dependencies compiled once in builder stage

## Health Checks

Both web and database services have health checks configured:

- **Web**: Curls localhost every 30s (starts checking after 40s)
- **Database**: Uses MariaDB's built-in healthcheck.sh every 10s

This ensures:
- Automatic container restart on failure
- Proper startup order (web waits for healthy database)
- Monitoring via `docker-compose ps` shows health status

## Network Architecture

Services communicate via a dedicated `photowall-network` bridge network:
- Isolates PhotoWall services from other Docker containers
- Services can resolve each other by service name (e.g., `db`, `web`)
- Production doesn't expose database port (internal only)
- Development exposes database for phpMyAdmin and external tools

## Usage

See main project README.md and Makefile for usage commands:
- `make up` / `make dev-up` - Start containers
- `make down` / `make dev-down` - Stop containers
- `make logs` / `make dev-logs` - View logs
- `make restart` / `make dev-restart` - Restart services
