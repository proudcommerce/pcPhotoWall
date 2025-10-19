<?php
/**
 * Custom Exception Classes for PC PhotoWall
 *
 * Provides structured exception handling with proper HTTP status codes
 * and error categories for better error reporting and debugging.
 */

/**
 * Base exception class for all PhotoWall exceptions
 */
class PhotoWallException extends Exception {
    protected int $httpStatusCode = 500;
    protected string $errorCategory = 'general';

    public function getHttpStatusCode(): int {
        return $this->httpStatusCode;
    }

    public function getErrorCategory(): string {
        return $this->errorCategory;
    }

    public function toArray(): array {
        return [
            'error' => $this->getMessage(),
            'category' => $this->errorCategory,
            'code' => $this->getCode()
        ];
    }
}

/**
 * Exception for validation errors (user input, file validation, etc.)
 */
class ValidationException extends PhotoWallException {
    protected int $httpStatusCode = 400;
    protected string $errorCategory = 'validation';
}

/**
 * Exception for database errors
 */
class DatabaseException extends PhotoWallException {
    protected int $httpStatusCode = 500;
    protected string $errorCategory = 'database';
}

/**
 * Exception for file system errors
 */
class FileSystemException extends PhotoWallException {
    protected int $httpStatusCode = 500;
    protected string $errorCategory = 'filesystem';
}

/**
 * Exception for image processing errors
 */
class ImageProcessingException extends PhotoWallException {
    protected int $httpStatusCode = 500;
    protected string $errorCategory = 'image_processing';
}

/**
 * Exception for GPS/geolocation errors
 */
class GeoLocationException extends PhotoWallException {
    protected int $httpStatusCode = 400;
    protected string $errorCategory = 'geolocation';
}

/**
 * Exception for authentication/authorization errors
 */
class AuthenticationException extends PhotoWallException {
    protected int $httpStatusCode = 403;
    protected string $errorCategory = 'authentication';
}

/**
 * Exception for not found errors (events, photos, etc.)
 */
class NotFoundException extends PhotoWallException {
    protected int $httpStatusCode = 404;
    protected string $errorCategory = 'not_found';
}

/**
 * Exception for duplicate entries
 */
class DuplicateException extends PhotoWallException {
    protected int $httpStatusCode = 409;
    protected string $errorCategory = 'duplicate';
}

/**
 * Global exception handler
 * Converts exceptions to proper JSON responses
 */
function handleException(Throwable $e): void {
    error_log("Unhandled exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    if ($e instanceof PhotoWallException) {
        sendJSONResponse($e->toArray(), $e->getHttpStatusCode());
    } else {
        // For unknown exceptions, return generic error
        sendJSONResponse([
            'error' => 'Ein unerwarteter Fehler ist aufgetreten',
            'category' => 'system'
        ], 500);
    }
}
?>
