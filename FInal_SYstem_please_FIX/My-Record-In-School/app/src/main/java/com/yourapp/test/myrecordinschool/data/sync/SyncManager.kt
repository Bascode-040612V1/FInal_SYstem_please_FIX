package com.yourapp.test.myrecordinschool.data.sync

import android.app.Application
import android.util.Log
import com.yourapp.test.myrecordinschool.data.api.RetrofitClient
import com.yourapp.test.myrecordinschool.data.model.*
import com.yourapp.test.myrecordinschool.data.preferences.AppPreferences
import com.yourapp.test.myrecordinschool.roomdb.AppDatabase
import com.yourapp.test.myrecordinschool.roomdb.entity.AttendanceEntity
import com.yourapp.test.myrecordinschool.roomdb.entity.ViolationEntity
import com.yourapp.test.myrecordinschool.roomdb.repository.AttendanceRepository
import com.yourapp.test.myrecordinschool.roomdb.repository.ImageCacheRepository
import com.yourapp.test.myrecordinschool.roomdb.repository.ViolationRepository
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class SyncManager(private val application: Application) {
    
    private val appPreferences = AppPreferences(application)
    private val database = AppDatabase.getDatabase(application)
    private val violationRepository = ViolationRepository(database.violationDao())
    private val attendanceRepository = AttendanceRepository(database.attendanceDao())
    private val imageCacheRepository = ImageCacheRepository(
        context = application,
        studentDao = database.studentDao(),
        studentApi = RetrofitClient.getStudentApi(appPreferences.getAppConfig().baseUrl)
    )
    
    private val _syncStatus = MutableStateFlow(SyncStatus())
    val syncStatus: StateFlow<SyncStatus> = _syncStatus.asStateFlow()
    
    private val _networkState = MutableStateFlow<NetworkState>(NetworkState.Unknown)
    val networkState: StateFlow<NetworkState> = _networkState.asStateFlow()
    
    private var syncJob: Job? = null
    private var lastUserInteraction: Long = System.currentTimeMillis()
    private var isAppInForeground: Boolean = true
    
    companion object {
        private const val TAG = "SyncManager"
        private const val SYNC_INTERVAL_MS = 30 * 60 * 1000L // 30 minutes - optimized
        private const val ACTIVE_SYNC_INTERVAL_MS = 10 * 60 * 1000L // 10 minutes when app is active
        private const val RETRY_DELAY_MS = 30 * 1000L // 30 seconds
        private const val CACHE_TIMEOUT_MS = 10 * 60 * 1000L // 10 minutes
        private const val APP_USAGE_TIMEOUT_MS = 5 * 60 * 1000L // 5 minutes since last user interaction
    }
    
    suspend fun syncViolations(forceRefresh: Boolean = false): Boolean {
        val studentId = appPreferences.getStudentId() ?: return false
        
        return try {
            _syncStatus.value = _syncStatus.value.copy(syncState = SyncState.Syncing)
            
            // Smart caching - check if we need to sync
            if (!forceRefresh && violationRepository.isCacheValid(studentId)) {
                Log.d(TAG, "Using cached violations data - cache still valid")
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                return true
            }
            
            val config = appPreferences.getAppConfig()
            val api = RetrofitClient.getViolationApi(config.baseUrl)
            
            // Delta sync - only get data since last update
            val lastSyncTime = appPreferences.getLastViolationSync()
            val response = if (lastSyncTime > 0 && !forceRefresh) {
                Log.d(TAG, "Performing delta sync for violations since: $lastSyncTime")
                api.getStudentViolationsSince(studentId, lastSyncTime)
            } else {
                Log.d(TAG, "Performing full sync for violations")
                api.getStudentViolations(studentId)
            }
            
            if (response.isSuccessful && response.body()?.success == true) {
                val violations = response.body()?.violations ?: emptyList()
                
                Log.d(TAG, "Received ${violations.size} violations (delta sync: ${lastSyncTime > 0})")
                
                // Convert to entities with sync tracking
                val entities = violations.map { violation ->
                    ViolationEntity(
                        id = violation.id,
                        student_id = violation.student_id,
                        student_name = violation.student_name,
                        year_level = violation.year_level,
                        course = violation.course,
                        section = violation.section,
                        violation_type = violation.violation_type,
                        violation_description = violation.violation_description,
                        offense_count = violation.offense_count,
                        original_offense_count = violation.original_offense_count,
                        penalty = violation.penalty,
                        recorded_by = violation.recorded_by,
                        date_recorded = violation.date_recorded,
                        acknowledged = violation.acknowledged,
                        category = violation.category,
                        last_sync_timestamp = System.currentTimeMillis(),
                        is_synced = true,
                        local_changes = false
                    )
                }
                
                // Save to local database
                if (forceRefresh) {
                    violationRepository.clearViolationsForStudent(studentId)
                }
                violationRepository.saveViolations(entities)
                
                // Update sync timestamps
                appPreferences.setLastViolationSync(System.currentTimeMillis())
                violationRepository.updateSyncTimestamp(studentId, System.currentTimeMillis())
                
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                
                Log.d(TAG, "Violations synced successfully: ${entities.size} items")
                true
            } else {
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Error("Failed to sync violations: ${response.message()}")
                )
                false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing violations", e)
            _syncStatus.value = _syncStatus.value.copy(
                syncState = SyncState.Error("Network error: ${e.message}")
            )
            false
        }
    }
    
    suspend fun syncAttendance(month: Int? = null, year: Int? = null, forceRefresh: Boolean = false): Boolean {
        val studentId = appPreferences.getStudentId() ?: return false
        
        return try {
            _syncStatus.value = _syncStatus.value.copy(syncState = SyncState.Syncing)
            
            // Smart caching for attendance
            if (!forceRefresh && attendanceRepository.isCacheValid(studentId)) {
                Log.d(TAG, "Using cached attendance data - cache still valid")
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                return true
            }
            
            val config = appPreferences.getAppConfig()
            val api = RetrofitClient.getAttendanceApi(config.baseUrl)
            
            // Delta sync for attendance
            val lastSyncTime = appPreferences.getLastAttendanceSync()
            val response = if (lastSyncTime > 0 && !forceRefresh) {
                Log.d(TAG, "Performing delta sync for attendance since: $lastSyncTime")
                api.getStudentAttendanceSince(studentId, lastSyncTime, month, year)
            } else {
                Log.d(TAG, "Performing full sync for attendance")
                api.getStudentAttendance(studentId, month, year)
            }
            
            if (response.isSuccessful && response.body()?.success == true) {
                val attendance = response.body()?.attendance ?: emptyList()
                
                Log.d(TAG, "Received ${attendance.size} attendance records (delta sync: ${lastSyncTime > 0})")
                
                // Convert to entities
                val entities = attendance.map { att ->
                    AttendanceEntity(
                        id = att.id,
                        student_id = att.student_id,
                        student_name = att.student_name,
                        student_number = att.student_number,
                        date = att.date,
                        time_in = att.time_in,
                        time_out = att.time_out,
                        status = att.status,
                        attendance_type = att.attendance_type,
                        created_at = att.created_at
                    )
                }
                
                // Clear old data for the specific month if provided
                if (month != null && year != null && forceRefresh) {
                    val yearMonth = String.format("%04d-%02d", year, month)
                    attendanceRepository.clearAttendanceForMonth(studentId, yearMonth)
                } else if (forceRefresh) {
                    attendanceRepository.clearAttendanceForStudent(studentId)
                }
                
                attendanceRepository.saveAttendance(entities)
                
                // Update sync timestamp
                appPreferences.setLastAttendanceSync(System.currentTimeMillis())
                
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                
                Log.d(TAG, "Attendance synced successfully: ${entities.size} items")
                true
            } else {
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Error("Failed to sync attendance: ${response.message()}")
                )
                false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing attendance", e)
            _syncStatus.value = _syncStatus.value.copy(
                syncState = SyncState.Error("Network error: ${e.message}")
            )
            false
        }
    }
    
    suspend fun syncAcknowledgment(violationId: Int): Boolean {
        return try {
            if (_networkState.value != NetworkState.Available) {
                Log.d(TAG, "Offline - acknowledgment for violation $violationId will sync later")
                // Store pending acknowledgment for later sync
                storePendingAcknowledgment(violationId)
                return true // Return true for offline-first UX
            }
            
            val config = appPreferences.getAppConfig()
            val api = RetrofitClient.getViolationApi(config.baseUrl)
            val response = api.acknowledgeViolation(violationId)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Log.d(TAG, "Acknowledgment synced successfully for violation: $violationId")
                // Remove from pending acknowledgments
                removePendingAcknowledgment(violationId)
                true
            } else {
                Log.e(TAG, "Failed to sync acknowledgment: ${response.message()}")
                // Store for retry
                storePendingAcknowledgment(violationId)
                false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing acknowledgment", e)
            // Store for retry
            storePendingAcknowledgment(violationId)
            false
        }
    }
    
    private fun storePendingAcknowledgment(violationId: Int) {
        val pending = appPreferences.getPendingAcknowledgments().toMutableSet()
        pending.add(violationId)
        appPreferences.setPendingAcknowledgments(pending)
        Log.d(TAG, "Stored pending acknowledgment for violation: $violationId")
    }
    
    private fun removePendingAcknowledgment(violationId: Int) {
        val pending = appPreferences.getPendingAcknowledgments().toMutableSet()
        pending.remove(violationId)
        appPreferences.setPendingAcknowledgments(pending)
        Log.d(TAG, "Removed pending acknowledgment for violation: $violationId")
    }
    
    private suspend fun syncPendingAcknowledgments(): Boolean {
        val pendingAcknowledgments = appPreferences.getPendingAcknowledgments()
        
        if (pendingAcknowledgments.isEmpty()) {
            return true
        }
        
        Log.d(TAG, "Syncing ${pendingAcknowledgments.size} pending acknowledgments")
        var allSuccessful = true
        
        for (violationId in pendingAcknowledgments) {
            try {
                val config = appPreferences.getAppConfig()
                val api = RetrofitClient.getViolationApi(config.baseUrl)
                val response = api.acknowledgeViolation(violationId)
                
                if (response.isSuccessful && response.body()?.success == true) {
                    removePendingAcknowledgment(violationId)
                    Log.d(TAG, "Successfully synced pending acknowledgment for violation: $violationId")
                } else {
                    allSuccessful = false
                    Log.w(TAG, "Failed to sync pending acknowledgment for violation: $violationId")
                }
            } catch (e: Exception) {
                allSuccessful = false
                Log.w(TAG, "Error syncing pending acknowledgment for violation: $violationId", e)
            }
        }
        
        return allSuccessful
    }
    
    suspend fun performFullSync(forceRefresh: Boolean = false): Boolean {
        _syncStatus.value = _syncStatus.value.copy(syncState = SyncState.Syncing)
        
        // First sync pending acknowledgments if online
        var acknowledgmentsSynced = true
        if (_networkState.value == NetworkState.Available) {
            acknowledgmentsSynced = syncPendingAcknowledgments()
        }
        
        val violationsSynced = syncViolations(forceRefresh)
        val attendanceSynced = syncAttendance(forceRefresh = forceRefresh)
        
        val success = violationsSynced && attendanceSynced && acknowledgmentsSynced
        
        _syncStatus.value = _syncStatus.value.copy(
            syncState = if (success) SyncState.Success else SyncState.Error("Partial sync failure"),
            lastSyncTime = if (success) System.currentTimeMillis() else _syncStatus.value.lastSyncTime
        )
        
        return success
    }
    
    fun startPeriodicSync() {
        syncJob?.cancel()
        syncJob = CoroutineScope(Dispatchers.IO + SupervisorJob()).launch {
            while (isActive) {
                val syncInterval = getAdaptiveSyncInterval()
                delay(syncInterval)
                
                if (_networkState.value == NetworkState.Available && shouldPerformSync()) {
                    try {
                        // Smart sync - only sync if cache is stale and app is being used
                        performSmartSync()
                    } catch (e: Exception) {
                        Log.e(TAG, "Periodic sync failed", e)
                    }
                }
            }
        }
    }

    private suspend fun performSmartSync(): Boolean {
        val studentId = appPreferences.getStudentId() ?: return false
        
        val needsViolationSync = !violationRepository.isCacheValid(studentId)
        val needsAttendanceSync = !attendanceRepository.isCacheValid(studentId)
        
        var success = true
        
        if (needsViolationSync) {
            Log.d(TAG, "Cache stale, syncing violations...")
            success = syncViolationsPaginated(studentId) && success
        }
        
        if (needsAttendanceSync) {
            Log.d(TAG, "Cache stale, syncing attendance...")
            success = syncAttendancePaginated(studentId) && success
        }
        
        // Background sync of profile images (non-blocking)
        CoroutineScope(Dispatchers.IO).launch {
            try {
                syncStudentImages(studentId)
            } catch (e: Exception) {
                Log.w(TAG, "Image sync failed but not blocking other operations", e)
            }
        }
        
        // Perform cleanup if needed (non-blocking)
        if (success && appPreferences.shouldPerformCleanup()) {
            CoroutineScope(Dispatchers.IO).launch {
                performDataCleanup()
            }
        }
        
        if (!needsViolationSync && !needsAttendanceSync) {
            Log.d(TAG, "Cache still valid, skipping sync")
        }
        
        return success
    }
    
    // Paginated sync methods for better performance
    private suspend fun syncViolationsPaginated(studentId: String, pageSize: Int = 50): Boolean {
        return try {
            _syncStatus.value = _syncStatus.value.copy(syncState = SyncState.Syncing)
            
            val config = appPreferences.getAppConfig()
            val api = RetrofitClient.getViolationApi(config.baseUrl)
            
            // Get recent violations with limit to avoid large datasets
            val response = api.getRecentViolations(studentId, pageSize)
            
            if (response.isSuccessful && response.body()?.success == true) {
                val violations = response.body()?.violations ?: emptyList()
                
                Log.d(TAG, "Received ${violations.size} violations (paginated)")
                
                val entities = violations.map { violation ->
                    ViolationEntity(
                        id = violation.id,
                        student_id = violation.student_id,
                        student_name = violation.student_name,
                        year_level = violation.year_level,
                        course = violation.course,
                        section = violation.section,
                        violation_type = violation.violation_type,
                        violation_description = violation.violation_description,
                        offense_count = violation.offense_count,
                        original_offense_count = violation.original_offense_count,
                        penalty = violation.penalty,
                        recorded_by = violation.recorded_by,
                        date_recorded = violation.date_recorded,
                        acknowledged = violation.acknowledged,
                        category = violation.category,
                        last_sync_timestamp = System.currentTimeMillis(),
                        is_synced = true,
                        local_changes = false
                    )
                }
                
                violationRepository.saveViolations(entities)
                appPreferences.setLastViolationSync(System.currentTimeMillis())
                
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                
                Log.d(TAG, "Violations synced successfully (paginated): ${entities.size} items")
                true
            } else {
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Error("Failed to sync violations: ${response.message()}")
                )
                false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing violations (paginated)", e)
            _syncStatus.value = _syncStatus.value.copy(
                syncState = SyncState.Error("Network error: ${e.message}")
            )
            false
        }
    }
    
    private suspend fun syncAttendancePaginated(studentId: String, pageSize: Int = 100): Boolean {
        return try {
            _syncStatus.value = _syncStatus.value.copy(syncState = SyncState.Syncing)
            
            val config = appPreferences.getAppConfig()
            val api = RetrofitClient.getAttendanceApi(config.baseUrl)
            
            // Get recent attendance with limit
            val response = api.getRecentAttendance(studentId, pageSize)
            
            if (response.isSuccessful && response.body()?.success == true) {
                val attendance = response.body()?.attendance ?: emptyList()
                
                Log.d(TAG, "Received ${attendance.size} attendance records (paginated)")
                
                val entities = attendance.map { att ->
                    AttendanceEntity(
                        id = att.id,
                        student_id = att.student_id,
                        student_name = att.student_name,
                        student_number = att.student_number,
                        date = att.date,
                        time_in = att.time_in,
                        time_out = att.time_out,
                        status = att.status,
                        attendance_type = att.attendance_type,
                        created_at = att.created_at
                    )
                }
                
                attendanceRepository.saveAttendance(entities)
                appPreferences.setLastAttendanceSync(System.currentTimeMillis())
                
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Success,
                    lastSyncTime = System.currentTimeMillis()
                )
                
                Log.d(TAG, "Attendance synced successfully (paginated): ${entities.size} items")
                true
            } else {
                _syncStatus.value = _syncStatus.value.copy(
                    syncState = SyncState.Error("Failed to sync attendance: ${response.message()}")
                )
                false
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing attendance (paginated)", e)
            _syncStatus.value = _syncStatus.value.copy(
                syncState = SyncState.Error("Network error: ${e.message}")
            )
            false
        }
    }
    
    // Image synchronization methods
    private suspend fun syncStudentImages(studentId: String): Boolean {
        return try {
            Log.d(TAG, "Starting background image sync for student: $studentId")
            
            // Check if student already has a cached image that's still valid
            if (imageCacheRepository.hasCachedImage(studentId)) {
                Log.d(TAG, "Student $studentId already has cached image, skipping download")
                return true
            }
            
            // Download and cache student image from backend
            val success = imageCacheRepository.downloadAndCacheStudentImage(studentId)
            
            if (success) {
                Log.d(TAG, "Successfully synced image for student: $studentId")
            } else {
                Log.w(TAG, "Failed to sync image for student: $studentId")
            }
            
            success
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing student images", e)
            false
        }
    }
    
    /**
     * Sync profile images for multiple students (e.g., from violation records)
     */
    suspend fun syncProfileImagesFromViolations(): Boolean {
        return try {
            val studentId = appPreferences.getStudentId() ?: return false
            
            // Get unique student IDs from recent violations that might have profile images
            val recentViolations = violationRepository.getRecentViolations(studentId, 20)
            val studentIds = recentViolations.map { it.student_id }.distinct()
            
            Log.d(TAG, "Syncing profile images for ${studentIds.size} students from violation records")
            
            var successCount = 0
            for (id in studentIds) {
                try {
                    if (syncStudentImages(id)) {
                        successCount++
                    }
                    // Add small delay to avoid overwhelming the server
                    delay(100)
                } catch (e: Exception) {
                    Log.w(TAG, "Failed to sync image for student: $id", e)
                }
            }
            
            Log.d(TAG, "Image sync completed: $successCount/${studentIds.size} successful")
            successCount == studentIds.size
        } catch (e: Exception) {
            Log.e(TAG, "Error syncing profile images from violations", e)
            false
        }
    }
    
    /**
     * Clean up old cached images to free space
     */
    private suspend fun cleanupImageCache() {
        try {
            Log.d(TAG, "Starting image cache cleanup...")
            
            // Clean images older than 7 days
            imageCacheRepository.cleanupOldCache(7 * 24 * 60 * 60 * 1000L)
            
            val cacheSize = imageCacheRepository.getCacheSize()
            Log.d(TAG, "Image cache cleanup completed, current cache size: ${cacheSize / 1024}KB")
        } catch (e: Exception) {
            Log.e(TAG, "Error during image cache cleanup", e)
        }
    }
    
    /**
     * Force refresh of a specific student's profile image
     */
    suspend fun refreshStudentImage(studentId: String): Boolean {
        return try {
            Log.d(TAG, "Force refreshing image for student: $studentId")
            
            // Invalidate existing cache
            imageCacheRepository.invalidateCache(studentId)
            
            // Download fresh image
            val success = imageCacheRepository.downloadAndCacheStudentImage(studentId)
            
            if (success) {
                Log.d(TAG, "Successfully refreshed image for student: $studentId")
            } else {
                Log.w(TAG, "Failed to refresh image for student: $studentId")
            }
            
            success
        } catch (e: Exception) {
            Log.e(TAG, "Error refreshing student image", e)
            false
        }
    }

    // Data cleanup method for optimization
    suspend fun performDataCleanup(): Boolean {
        val studentId = appPreferences.getStudentId() ?: return false
        
        if (!appPreferences.isAutoCleanupEnabled() || !appPreferences.shouldPerformCleanup()) {
            return true // Skip cleanup if disabled or not needed
        }
        
        return try {
            Log.d(TAG, "Performing data cleanup...")
            
            // Cleanup old violations (keep last 90 days)
            violationRepository.cleanupOldViolations(studentId, 90)
            
            // Cleanup old attendance (keep last 180 days)
            attendanceRepository.cleanupOldAttendance(studentId, 180)
            
            // Cleanup old cached images
            cleanupImageCache()
            
            // Update last cleanup time
            appPreferences.setLastCleanupTime(System.currentTimeMillis())
            
            Log.d(TAG, "Data cleanup completed successfully")
            true
        } catch (e: Exception) {
            Log.e(TAG, "Error during data cleanup", e)
            false
        }
    }

    fun stopPeriodicSync() {
        syncJob?.cancel()
        syncJob = null
    }
    
    fun updateNetworkState(isAvailable: Boolean) {
        _networkState.value = if (isAvailable) NetworkState.Available else NetworkState.Unavailable
    }
    
    fun resetSyncState() {
        _syncStatus.value = SyncStatus()
    }
    
    // App usage tracking for optimized sync
    fun notifyUserInteraction() {
        lastUserInteraction = System.currentTimeMillis()
    }
    
    fun setAppForegroundState(isInForeground: Boolean) {
        isAppInForeground = isInForeground
        if (isInForeground) {
            lastUserInteraction = System.currentTimeMillis()
        }
    }
    
    private fun shouldPerformSync(): Boolean {
        val timeSinceLastInteraction = System.currentTimeMillis() - lastUserInteraction
        
        // Don't sync if app hasn't been used recently and is in background
        if (!isAppInForeground && timeSinceLastInteraction > APP_USAGE_TIMEOUT_MS) {
            Log.d(TAG, "Skipping sync - app not in active use")
            return false
        }
        
        return true
    }
    
    private fun getAdaptiveSyncInterval(): Long {
        val timeSinceLastInteraction = System.currentTimeMillis() - lastUserInteraction
        
        return if (isAppInForeground || timeSinceLastInteraction < APP_USAGE_TIMEOUT_MS) {
            ACTIVE_SYNC_INTERVAL_MS // More frequent when app is active
        } else {
            SYNC_INTERVAL_MS // Less frequent when app is idle
        }
    }
}