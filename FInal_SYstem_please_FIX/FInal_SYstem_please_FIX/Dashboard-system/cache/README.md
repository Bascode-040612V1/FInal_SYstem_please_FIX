# Cache Directory

This directory is used by the SimpleCache class in performance_config.php for file-based caching.

## Purpose:
- Stores cached data to improve system performance
- Reduces database queries and server load
- TTL-based expiration for automatic cleanup

## Cache Features:
- **MD5 Key Hashing**: Efficient cache key management
- **TTL Expiration**: Automatic cleanup of expired cache files
- **File-based Storage**: No external dependencies required
- **Default TTL**: 5 minutes (300 seconds)

## Files in this directory:
- Cache files are named using MD5 hashes of cache keys
- Files have `.cache` extension
- Files are automatically created and deleted by the system
- Manual cleanup: Delete files in this directory to clear cache

## Permissions:
- Directory must be writable by the web server (755 or 775)
- Cache files are created with appropriate permissions

## Note:
This directory was auto-created to fix the "Missing Cache Directory" issue.
The SimpleCache class will automatically manage files in this directory.