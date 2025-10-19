#!/usr/bin/env php
<?php
/**
 * Demo Event Creation Script
 *
 * Creates a demo event with sample configuration for fresh installations.
 * This script is automatically called during database initialization.
 */

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

function createDemoEvent() {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Check if any events exist
        $stmt = $conn->prepare("SELECT COUNT(*) FROM events");
        $stmt->execute();
        $eventCount = $stmt->fetchColumn();

        // Only create demo event if no events exist
        if ($eventCount > 0) {
            echo "Events already exist. Skipping demo event creation.\n";
            return false;
        }

        echo "Creating demo event...\n";

        // Generate unique identifiers
        $eventHash = generateEventHash();
        $eventSlug = 'demo-event';

        // Demo event configuration
        $demoData = [
            'name' => 'Demo Event',
            'event_slug' => $eventSlug,
            'event_hash' => $eventHash,
            'latitude' => null,
            'longitude' => null,
            'radius_meters' => 100,
            'display_mode' => 'random',
            'display_count' => 9,
            'display_interval' => 5,
            'layout_type' => 'grid',
            'grid_columns' => 3,
            'show_username' => true,
            'show_date' => true,
            'overlay_opacity' => 0.8,
            'gps_validation_required' => false,
            'moderation_required' => false,
            'note' => 'This is a demo event created during installation. You can edit or delete it from the admin interface.',
            'logo_filename' => null,
            'show_logo' => false,
            'show_qr_code' => true,
            'show_display_link' => true,
            'show_gallery_link' => true,
            'max_upload_size' => 10485760, // 10MB
            'is_active' => true
        ];

        // Insert demo event
        $sql = "INSERT INTO events (
            name, event_slug, event_hash, latitude, longitude, radius_meters,
            display_mode, display_count, display_interval, layout_type, grid_columns,
            show_username, show_date, overlay_opacity, gps_validation_required,
            moderation_required, note, logo_filename, show_logo, show_qr_code,
            show_display_link, show_gallery_link, max_upload_size, is_active
        ) VALUES (
            :name, :event_slug, :event_hash, :latitude, :longitude, :radius_meters,
            :display_mode, :display_count, :display_interval, :layout_type, :grid_columns,
            :show_username, :show_date, :overlay_opacity, :gps_validation_required,
            :moderation_required, :note, :logo_filename, :show_logo, :show_qr_code,
            :show_display_link, :show_gallery_link, :max_upload_size, :is_active
        )";

        $stmt = $conn->prepare($sql);
        $stmt->execute($demoData);

        $eventId = $conn->lastInsertId();

        // Create event directories
        $uploadsBase = __DIR__ . '/../app/uploads';
        $eventDir = $uploadsBase . '/' . $eventSlug;
        $photosDir = $eventDir . '/photos';
        $thumbnailsDir = $eventDir . '/thumbnails';
        $logoDir = $eventDir . '/logo';

        if (!file_exists($photosDir)) {
            mkdir($photosDir, 0755, true);
        }
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0755, true);
        }
        if (!file_exists($logoDir)) {
            mkdir($logoDir, 0755, true);
        }

        echo "✓ Demo event created successfully!\n";
        echo "  - Event ID: {$eventId}\n";
        echo "  - Event Slug: {$eventSlug}\n";
        echo "  - Event Hash: {$eventHash}\n";
        echo "  - Upload URL: http://localhost:4000/{$eventSlug}\n";
        echo "  - Display URL: http://localhost:4000/{$eventSlug}/display\n";
        echo "  - Gallery URL: http://localhost:4000/{$eventSlug}/gallery\n";
        echo "\nYou can manage this event in the admin interface at:\n";
        echo "  http://localhost:4000/admin/\n";

        return true;

    } catch (Exception $e) {
        echo "Error creating demo event: " . $e->getMessage() . "\n";
        error_log("Demo event creation error: " . $e->getMessage());
        return false;
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli') {
    echo "=== PC PhotoWall Demo Event Creation ===\n\n";
    createDemoEvent();
    echo "\n";
}
