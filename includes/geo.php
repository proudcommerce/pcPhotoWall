<?php
require_once __DIR__ . '/../config/config.php';

class GeoUtils {
    
    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @return float Distance in meters
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Earth's radius in meters
        
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
        
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Check if coordinates are within specified radius
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @param int $radiusMeters Radius in meters
     * @return bool True if within radius
     */
    public static function isWithinRadius($lat1, $lon1, $lat2, $lon2, $radiusMeters) {
        $distance = self::calculateDistance($lat1, $lon1, $lat2, $lon2);
        return $distance <= $radiusMeters;
    }
    
    /**
     * Extract GPS coordinates from EXIF data
     * 
     * @param string $filePath Path to image file
     * @return array|false Array with 'latitude' and 'longitude' or false if not found
     */
    public static function extractGPSCoordinates($filePath) {
        if (!function_exists('exif_read_data')) {
            return false;
        }
        
        $exif = @exif_read_data($filePath);
        
        if (!$exif) {
            return false;
        }
        
        // Check if GPS data is in GPS section or directly in EXIF
        $gps = isset($exif['GPS']) ? $exif['GPS'] : $exif;
        
        if (!isset($gps['GPSLatitude']) || !isset($gps['GPSLongitude']) ||
            !isset($gps['GPSLatitudeRef']) || !isset($gps['GPSLongitudeRef'])) {
            return false;
        }
        
        $lat = self::getGPSCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
        $lon = self::getGPSCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
        
        if ($lat === false || $lon === false) {
            return false;
        }
        
        return [
            'latitude' => $lat,
            'longitude' => $lon
        ];
    }
    
    /**
     * Convert GPS coordinate from EXIF format to decimal degrees
     * 
     * @param array $coordinate GPS coordinate array from EXIF
     * @param string $ref Reference (N, S, E, W)
     * @return float|false Decimal coordinate or false on error
     */
    private static function getGPSCoordinate($coordinate, $ref) {
        if (!is_array($coordinate) || count($coordinate) !== 3) {
            return false;
        }
        
        $degrees = self::parseGPSCoordinatePart($coordinate[0]);
        $minutes = self::parseGPSCoordinatePart($coordinate[1]);
        $seconds = self::parseGPSCoordinatePart($coordinate[2]);
        
        if ($degrees === false || $minutes === false || $seconds === false) {
            return false;
        }
        
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        
        // Apply reference (N/E = positive, S/W = negative)
        if (strtoupper($ref) === 'S' || strtoupper($ref) === 'W') {
            $decimal = -$decimal;
        }
        
        return $decimal;
    }
    
    /**
     * Parse GPS coordinate part (degrees, minutes, or seconds)
     * 
     * @param string $part Coordinate part as string
     * @return float|false Parsed value or false on error
     */
    private static function parseGPSCoordinatePart($part) {
        if (!is_string($part)) {
            return false;
        }
        
        // Handle fractions like "123/1" or "123/456"
        if (strpos($part, '/') !== false) {
            $parts = explode('/', $part);
            if (count($parts) !== 2) {
                return false;
            }
            
            $numerator = (float)$parts[0];
            $denominator = (float)$parts[1];
            
            if ($denominator == 0) {
                return false;
            }
            
            return $numerator / $denominator;
        }
        
        return (float)$part;
    }
    
    /**
     * Validate GPS coordinates
     * 
     * @param float $latitude
     * @param float $longitude
     * @return bool True if valid coordinates
     */
    public static function validateCoordinates($latitude, $longitude) {
        return $latitude >= -90 && $latitude <= 90 && 
               $longitude >= -180 && $longitude <= 180;
    }
    
    /**
     * Format distance for display
     * 
     * @param float $distanceMeters Distance in meters
     * @return string Formatted distance string
     */
    public static function formatDistance($distanceMeters) {
        if ($distanceMeters === null || $distanceMeters === '') {
            return '0 m';
        }
        if ($distanceMeters < 1000) {
            return round($distanceMeters) . ' m';
        } else {
            return round($distanceMeters / 1000, 1) . ' km';
        }
    }
}
?>
