# Offline Profile Image Caching Implementation

## Overview
This implementation provides offline-first profile image caching for the My-Record-In-School Android app, ensuring that profile images are viewable even when offline, similar to how violations and attendance data work offline.

## Key Features

### 🔄 Offline-First Architecture
- **Cached Images Display**: Profile images are cached locally and displayed immediately when available
- **Background Sync**: Images are downloaded and cached automatically in the background
- **Fallback Mechanism**: Shows default profile icon when no cached image is available
- **Smart Cache Management**: Automatic cache expiration and cleanup to manage storage space

### 🏗️ Architecture Components

#### 1. Database Layer
- **StudentEntity**: Room entity with image caching metadata
- **StudentDao**: Database operations for student and image cache management
- **Database Migration**: Seamless upgrade from version 1 to 2 with new image fields

#### 2. Repository Layer
- **ImageCacheRepository**: Manages local image storage and backend synchronization
- **Hybrid Caching**: Combines local file storage with database metadata tracking

#### 3. UI Components
- **OfflineImageLoader**: Composable that prioritizes cached images over network requests
- **ProfileImage**: Simplified component for basic profile image display
- **Cache Indicator**: Visual indicator showing when image is loaded from cache

#### 4. Sync Management
- **Enhanced SyncManager**: Background image synchronization integrated with existing sync system
- **Non-blocking Operations**: Image sync doesn't interfere with critical data sync operations

## Implementation Details

### Database Schema (StudentEntity)
```kotlin
@Entity(tableName = "students")
data class StudentEntity(
    @PrimaryKey val student_id: String,
    val name: String,
    val year: String,
    val course: String,
    val section: String,
    val password: String,
    val created_at: String,
    val updated_at: String,
    
    // Image caching fields
    val image_url: String? = null,
    val cached_image_path: String? = null,
    val image_last_modified: Long? = null,
    val image_cache_timestamp: Long = System.currentTimeMillis(),
    val image_cache_valid: Boolean = false,
    
    // Sync tracking fields
    val last_sync_timestamp: Long = System.currentTimeMillis(),
    val is_synced: Boolean = true,
    val local_changes: Boolean = false
)
```

### Offline-First Image Loading Flow
1. **Check Local Cache**: First attempts to load image from local cache
2. **Display Cached Image**: Shows cached image immediately if available
3. **Background Download**: Downloads fresh image in background if cache is stale
4. **Update Cache**: Updates local cache with new image
5. **Fallback Display**: Shows default icon if no image is available

### Cache Management
- **Cache Timeout**: 24 hours for profile images (configurable)
- **Automatic Cleanup**: Removes images older than 7 days
- **Storage Optimization**: JPEG compression at 85% quality
- **File Validation**: Verifies downloaded data is valid image format

## Usage Examples

### Basic Profile Image Display
```kotlin
@Composable
fun StudentProfile(studentId: String) {
    ProfileImage(
        studentId = studentId,
        imageUrl = null, // Will load from backend automatically
        size = 72.dp,
        contentDescription = "Student Profile Picture"
    )
}
```

### Advanced Image Loading with Custom Fallback
```kotlin
@Composable
fun CustomProfileDisplay(studentId: String, imageUrl: String?) {
    OfflineImageLoader(
        studentId = studentId,
        imageUrl = imageUrl,
        size = 100.dp,
        showLoadingIndicator = true,
        fallbackIcon = {
            Icon(
                imageVector = Icons.Default.AccountCircle,
                contentDescription = "Default Profile",
                modifier = Modifier.size(60.dp)
            )
        }
    )
}
```

## Integration Points

### 1. ViolationDetailScreen
- Shows student profile image in violation details
- Automatically downloads and caches images for students in violation records

### 2. Settings02Screen
- Displays current user's profile image with upload functionality
- Combines offline-first loading with manual upload capability

### 3. Background Sync (SyncManager)
- Automatically downloads profile images during data sync operations
- Non-blocking image sync that doesn't affect critical data operations

## Configuration

### Cache Settings
```kotlin
// ImageCacheRepository configuration
private val cacheTimeout = 24 * 60 * 60 * 1000L // 24 hours
private val maxCacheAge = 7 * 24 * 60 * 60 * 1000L // 7 days for cleanup
private val imageQuality = 85 // JPEG compression quality
```

### Sync Behavior
```kotlin
// SyncManager image sync integration
private suspend fun performSmartSync(): Boolean {
    // ... existing sync logic ...
    
    // Background sync of profile images (non-blocking)
    CoroutineScope(Dispatchers.IO).launch {
        try {
            syncStudentImages(studentId)
        } catch (e: Exception) {
            Log.w(TAG, "Image sync failed but not blocking other operations", e)
        }
    }
}
```

## Testing

### Integration Tests
- **Offline Image Caching**: Tests successful caching and retrieval
- **Cache Expiration**: Validates cache timeout behavior
- **Database Migration**: Ensures seamless schema upgrade
- **Invalidation**: Tests cache invalidation functionality

### Test Coverage
- Unit tests for ImageCacheRepository
- Integration tests for offline-first behavior
- UI tests for ProfileImage component
- Database migration validation

## Benefits

### 🚀 Performance
- **Instant Loading**: Cached images load immediately
- **Reduced Network Usage**: Only downloads images when necessary
- **Background Operations**: Non-blocking image sync

### 📱 User Experience
- **Offline Availability**: Profile images work without internet
- **Smooth UI**: No loading spinners for cached images
- **Consistent Design**: Seamless integration with existing UI patterns

### 🔧 Maintainability
- **Modular Design**: Reusable components for any profile image display
- **Centralized Management**: Single repository for all image caching operations
- **Clear Architecture**: Follows existing app patterns and conventions

## Migration Guide

### From Previous Version
1. **Database Upgrade**: Automatic migration from version 1 to 2
2. **No Breaking Changes**: Existing functionality remains unchanged
3. **Progressive Enhancement**: Images load from network initially, then cached for offline use

### Configuration Updates
- No configuration changes required
- Existing BaseUrl and API endpoints are reused
- Cache directory is automatically created in app's cache folder

## Future Enhancements

### Planned Features
- **Bulk Image Download**: Download all profile images for offline use
- **Image Compression Options**: Configurable quality settings
- **Cache Size Limits**: Automatic cleanup based on storage usage
- **Image Upload Integration**: Direct upload from ProfileImage component

### Performance Optimizations
- **Lazy Loading**: Load images only when visible
- **Memory Management**: Better bitmap recycling
- **Network Optimization**: Conditional downloads based on image modification dates

This implementation provides a robust, offline-first profile image caching system that enhances the user experience while maintaining the app's existing architecture and design patterns.