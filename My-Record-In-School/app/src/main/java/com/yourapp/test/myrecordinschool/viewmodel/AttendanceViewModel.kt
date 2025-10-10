package com.yourapp.test.myrecordinschool.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.asLiveData
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.switchMap
import com.yourapp.test.myrecordinschool.data.model.*
import com.yourapp.test.myrecordinschool.data.preferences.AppPreferences
import com.yourapp.test.myrecordinschool.data.sync.SyncManager
import com.yourapp.test.myrecordinschool.roomdb.AppDatabase
import com.yourapp.test.myrecordinschool.roomdb.entity.AttendanceEntity
import com.yourapp.test.myrecordinschool.roomdb.repository.AttendanceRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

/**
 * AttendanceViewModel with offline-first architecture
 * Provides attendance data immediately from local Room database
 * while syncing with backend in the background
 */
class AttendanceViewModel(application: Application) : AndroidViewModel(application) {
    
    private val appPreferences = AppPreferences(application)
    private val attendanceDao = AppDatabase.getDatabase(application).attendanceDao()
    private val repository = AttendanceRepository(attendanceDao)
    private val syncManager = SyncManager(application)
    
    // Data state for attendance - Always show from local database (offline-first)
    private val _attendanceDataState = MutableStateFlow<DataState<List<AttendanceEntity>>>(DataState.Loading)
    val attendanceDataState: StateFlow<DataState<List<AttendanceEntity>>> = _attendanceDataState.asStateFlow()
    
    // Local database attendance (primary source of truth)
    val attendanceFromDb: LiveData<List<AttendanceEntity>> = repository.getAttendanceByStudent(
        appPreferences.getStudentId() ?: ""
    ).asLiveData()
    
    // UI state
    private val _isRefreshing = MutableLiveData<Boolean>()
    val isRefreshing: LiveData<Boolean> = _isRefreshing
    
    // Offline indicator
    private val _isOfflineMode = MutableLiveData<Boolean>()
    val isOfflineMode: LiveData<Boolean> = _isOfflineMode
    
    // Last sync info
    private val _lastSyncTime = MutableLiveData<String>()
    val lastSyncTime: LiveData<String> = _lastSyncTime
    
    // Attendance statistics
    private val _attendanceStats = MutableLiveData<AttendanceStats>()
    val attendanceStats: LiveData<AttendanceStats> = _attendanceStats
    
    // Legacy support
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _errorMessage = MutableLiveData<String>()
    val errorMessage: LiveData<String> = _errorMessage
    
    // Sync status
    val syncStatus: StateFlow<SyncStatus> = syncManager.syncStatus
    val networkState: StateFlow<NetworkState> = syncManager.networkState
    
    // Monthly attendance data
    private val _currentMonth = MutableLiveData<String>()
    val currentMonth: LiveData<String> = _currentMonth
    
    //Calendar navigation properties for UI  
    private val _selectedMonth = MutableLiveData<Int>()
    val selectedMonth: LiveData<Int> = _selectedMonth
    
    private val _selectedYear = MutableLiveData<Int>()
    val selectedYear: LiveData<Int> = _selectedYear
    
    val currentMonthAttendance: LiveData<List<AttendanceEntity>> = 
        _currentMonth.switchMap { month ->
            appPreferences.getStudentId()?.let { studentId ->
                repository.getAttendanceByStudentAndMonth(studentId, month).asLiveData()
            } ?: MutableLiveData(emptyList())
        }

    init {
        // Set current month and initialize calendar navigation
        val calendar = java.util.Calendar.getInstance()
        val currentYearMonth = java.text.SimpleDateFormat("yyyy-MM", java.util.Locale.getDefault())
            .format(java.util.Date())
        _currentMonth.value = currentYearMonth
        _selectedMonth.value = calendar.get(java.util.Calendar.MONTH) + 1 // Calendar.MONTH is 0-based
        _selectedYear.value = calendar.get(java.util.Calendar.YEAR)
        
        // Load attendance from local database first (offline-first)
        loadAttendanceFromDatabase()
        
        // Update sync status
        updateSyncInfo()
        
        // Monitor network state
        viewModelScope.launch {
            networkState.collect { state ->
                _isOfflineMode.value = state != NetworkState.Available
                if (state == NetworkState.Available) {
                    // Auto-sync when network becomes available
                    syncAttendanceInBackground()
                }
            }
        }
        
        // Monitor sync status
        viewModelScope.launch {
            syncStatus.collect { status ->
                updateLastSyncTime(status.lastSyncTime)
                
                // Handle sync completion
                when (status.syncState) {
                    is SyncState.Success -> {
                        _errorMessage.value = ""
                        _isRefreshing.value = false
                    }
                    is SyncState.Error -> {
                        _errorMessage.value = status.syncState.message
                        _isRefreshing.value = false
                    }
                    is SyncState.Syncing -> {
                        _isRefreshing.value = true
                    }
                    else -> {
                        _isRefreshing.value = false
                    }
                }
            }
        }
        
        syncManager.startPeriodicSync()
    }
    
    override fun onCleared() {
        super.onCleared()
        syncManager.stopPeriodicSync()
    }
    
    private fun loadAttendanceFromDatabase() {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _attendanceDataState.value = DataState.Loading
                
                val studentId = appPreferences.getStudentId()
                if (studentId.isNullOrEmpty()) {
                    _attendanceDataState.value = DataState.Error("Student ID not found")
                    return@launch
                }
                
                // Load from local database (offline-first)
                repository.getAttendanceByStudent(studentId).collect { attendance ->
                    _attendanceDataState.value = DataState.Success(attendance)
                    updateAttendanceStats(attendance)
                }
                
                _isLoading.value = false
                
                // Try to sync in background if network is available
                if (networkState.value == NetworkState.Available) {
                    syncAttendanceInBackground()
                }
                
            } catch (e: Exception) {
                _attendanceDataState.value = DataState.Error("Failed to load attendance: ${e.message}")
                _isLoading.value = false
                android.util.Log.e("AttendanceViewModel", "Error loading attendance from database", e)
            }
        }
    }
    
    private suspend fun syncAttendanceInBackground() {
        try {
            syncManager.syncAttendance(forceRefresh = false)
            updateSyncInfo()
        } catch (e: Exception) {
            android.util.Log.w("AttendanceViewModel", "Background sync failed", e)
        }
    }
    
    private fun updateAttendanceStats(attendance: List<AttendanceEntity>) {
        val presentCount = attendance.count { it.status == "PRESENT" }
        val absentCount = attendance.count { it.status == "ABSENT" }
        val lateCount = attendance.count { it.status == "LATE" }
        
        val stats = AttendanceStats(
            totalDays = attendance.size,
            presentDays = presentCount,
            absentDays = absentCount,
            lateDays = lateCount,
            attendancePercentage = if (attendance.isNotEmpty()) (presentCount.toFloat() / attendance.size * 100) else 0f,
            recentAttendance = attendance.take(5),
            hasOfflineChanges = attendance.any { it.local_changes }
        )
        _attendanceStats.value = stats
    }
    
    private fun updateSyncInfo() {
        viewModelScope.launch {
            val lastSync = appPreferences.getLastAttendanceSync()
            updateLastSyncTime(lastSync)
        }
    }
    
    private fun updateLastSyncTime(timestamp: Long) {
        if (timestamp > 0) {
            val timeAgo = getTimeAgo(timestamp)
            _lastSyncTime.value = "Last synced: $timeAgo"
        } else {
            _lastSyncTime.value = "Never synced"
        }
    }
    
    private fun getTimeAgo(timestamp: Long): String {
        val now = System.currentTimeMillis()
        val diff = now - timestamp
        
        return when {
            diff < 60000 -> "Just now"
            diff < 3600000 -> "${diff / 60000}m ago"
            diff < 86400000 -> "${diff / 3600000}h ago"
            else -> "${diff / 86400000}d ago"
        }
    }
    
    fun loadAttendance() {
        viewModelScope.launch {
            _isLoading.value = true
            _attendanceDataState.value = DataState.Loading
            
            val success = syncManager.syncAttendance()
            
            if (success) {
                _attendanceDataState.value = DataState.Success(emptyList()) // Will be populated by Flow
            } else {
                val errorMsg = when (val syncState = syncManager.syncStatus.value.syncState) {
                    is SyncState.Error -> syncState.message
                    else -> "Failed to load attendance"
                }
                _attendanceDataState.value = DataState.Error(errorMsg)
                _errorMessage.value = errorMsg
            }
            
            _isLoading.value = false
        }
    }
    
    fun refreshAttendance() {
        viewModelScope.launch {
            _isLoading.value = true
            
            val success = syncManager.syncAttendance(forceRefresh = true)
            
            if (success) {
                _attendanceDataState.value = DataState.Success(emptyList())
                _errorMessage.value = ""
            } else {
                val errorMsg = when (val syncState = syncManager.syncStatus.value.syncState) {
                    is SyncState.Error -> syncState.message
                    else -> "Failed to refresh attendance"
                }
                _attendanceDataState.value = DataState.Error(errorMsg)
                _errorMessage.value = errorMsg
            }
            
            _isLoading.value = false
        }
    }
    
    fun setCurrentMonth(yearMonth: String) {
        _currentMonth.value = yearMonth
    }
    
    fun getCurrentMonthStats(): AttendanceStats? {
        val currentAttendance = currentMonthAttendance.value ?: return null
        val presentCount = currentAttendance.count { it.status == "PRESENT" }
        val absentCount = currentAttendance.count { it.status == "ABSENT" }
        val lateCount = currentAttendance.count { it.status == "LATE" }
        
        return AttendanceStats(
            totalDays = currentAttendance.size,
            presentDays = presentCount,
            absentDays = absentCount,
            lateDays = lateCount,
            attendancePercentage = if (currentAttendance.isNotEmpty()) (presentCount.toFloat() / currentAttendance.size * 100) else 0f,
            recentAttendance = currentAttendance.take(5),
            hasOfflineChanges = currentAttendance.any { it.local_changes }
        )
    }
    
    fun clearError() {
        _errorMessage.value = ""
        syncManager.resetSyncState()
    }
    
    fun retryOperation() {
        clearError()
        loadAttendance()
    }
    
    fun updateNetworkState(isAvailable: Boolean) {
        syncManager.updateNetworkState(isAvailable)
    }
    
    //fun navigateToPreviousMonth()
    fun navigateToPreviousMonth() {
        val currentMonth = _selectedMonth.value ?: 1
        val currentYear = _selectedYear.value ?: 2024
        
        if (currentMonth == 1) {
            _selectedMonth.value = 12
            _selectedYear.value = currentYear - 1
        } else {
            _selectedMonth.value = currentMonth - 1
        }
        updateCurrentMonthString()
    }
    
    //fun navigateToNextMonth()
    fun navigateToNextMonth() {
        val currentMonth = _selectedMonth.value ?: 1
        val currentYear = _selectedYear.value ?: 2024
        
        if (currentMonth == 12) {
            _selectedMonth.value = 1
            _selectedYear.value = currentYear + 1
        } else {
            _selectedMonth.value = currentMonth + 1
        }
        updateCurrentMonthString()
    }
    
    //private fun updateCurrentMonthString()
    private fun updateCurrentMonthString() {
        val month = _selectedMonth.value ?: 1
        val year = _selectedYear.value ?: 2024
        val formattedMonth = if (month < 10) "0$month" else "$month"
        _currentMonth.value = "$year-$formattedMonth"
    }
      
    //fun generateCalendarFromOfflineData()
    fun generateCalendarFromOfflineData(month: Int, year: Int) {
        viewModelScope.launch {
            try {
                val studentId = appPreferences.getStudentId() ?: return@launch
                
                // Get attendance data for the specific month from local database  
                val yearMonth = "${year}-${if (month < 10) "0$month" else "$month"}"
                val attendanceList = repository.getAttendanceByStudentAndMonthSuspend(studentId, yearMonth)
                
                // Generate calendar days for the month using local data
                val calendar = java.util.Calendar.getInstance()
                calendar.set(year, month - 1, 1) // month is 0-based in Calendar
                
                val daysInMonth = calendar.getActualMaximum(java.util.Calendar.DAY_OF_MONTH)
                val firstDayOfWeek = calendar.get(java.util.Calendar.DAY_OF_WEEK) - 1 // Make Sunday = 0
                
                val attendanceMap = attendanceList.associateBy { it.date }
                val calendarDays = mutableListOf<AttendanceCalendarDay>()
                
                // Add empty days before the first day of the month
                for (i in 0 until firstDayOfWeek) {
                    calendarDays.add(
                        AttendanceCalendarDay(
                            day = 0,
                            isCurrentMonth = false,
                            isToday = false,
                            attendance = null
                        )
                    )
                }
                
                // Add days of the month
                val today = java.util.Calendar.getInstance()
                for (day in 1..daysInMonth) {
                    val dateString = "$year-${if (month < 10) "0$month" else "$month"}-${if (day < 10) "0$day" else "$day"}"
                    val isToday = (today.get(java.util.Calendar.YEAR) == year && 
                                  today.get(java.util.Calendar.MONTH) == month - 1 && 
                                  today.get(java.util.Calendar.DAY_OF_MONTH) == day)
                    
                    calendarDays.add(
                        AttendanceCalendarDay(
                            day = day,
                            isCurrentMonth = true,
                            isToday = isToday,
                            attendance = attendanceMap[dateString]
                        )
                    )
                }
                
                // Create the attendance month object
                val attendanceMonth = AttendanceMonth(
                    year = year,
                    month = month,
                    days = calendarDays
                )
                
                // Update the current month data (this will trigger UI updates)
                _currentMonth.value = yearMonth
                
                android.util.Log.d("AttendanceViewModel", "Generated calendar for $yearMonth with ${attendanceList.size} attendance records")
                
            } catch (e: Exception) {
                android.util.Log.e("AttendanceViewModel", "Error generating calendar from offline data", e)
            }
        }
    }
    
    //fun populateSampleOfflineData()
    fun populateSampleOfflineData() {
        viewModelScope.launch {
            try {
                val studentId = appPreferences.getStudentId() ?: return@launch
                val calendar = java.util.Calendar.getInstance()
                val currentMonth = calendar.get(java.util.Calendar.MONTH) + 1
                val currentYear = calendar.get(java.util.Calendar.YEAR)
                
                // Generate sample attendance data for the current month
                val sampleAttendance = mutableListOf<AttendanceEntity>()
                for (day in 1..15) { // Add 15 days of sample data
                    val dateString = "$currentYear-${if (currentMonth < 10) "0$currentMonth" else "$currentMonth"}-${if (day < 10) "0$day" else "$day"}"
                    val status = when (day % 4) {
                        0 -> "ABSENT"
                        1 -> "LATE"
                        else -> "PRESENT"
                    }
                    
                    sampleAttendance.add(
                        AttendanceEntity(
                            id = day + 1000, // Use high ID to avoid conflicts
                            student_id = studentId,
                            student_name = "Sample Student",
                            student_number = "2024001",
                            date = dateString,
                            time_in = if (status != "ABSENT") "08:00:00" else null,
                            time_out = if (status != "ABSENT") "17:00:00" else null,
                            status = status,
                            attendance_type = "regular",
                            created_at = dateString,
                            last_sync_timestamp = System.currentTimeMillis(),
                            is_synced = false,
                            local_changes = true,
                            offline_created = true
                        )
                    )
                }
                
                // Save sample data to local database
                repository.saveAttendance(sampleAttendance)
                
                android.util.Log.d("AttendanceViewModel", "Sample offline attendance data populated: ${sampleAttendance.size} records")
                
            } catch (e: Exception) {
                android.util.Log.e("AttendanceViewModel", "Error populating sample data", e)
            }
        }
    }
}

/**
 * Data class representing attendance statistics for offline-first UI display
 */
data class AttendanceStats(
    val totalDays: Int = 0,
    val presentDays: Int = 0,
    val absentDays: Int = 0,
    val lateDays: Int = 0,
    val attendancePercentage: Float = 0f,
    val recentAttendance: List<AttendanceEntity> = emptyList(),
    val hasOfflineChanges: Boolean = false
) {
    fun hasAttendanceData(): Boolean = totalDays > 0
    
    fun getSummaryText(): String {
        return when {
            totalDays == 0 -> "No attendance records"
            else -> "$presentDays present, $absentDays absent, $lateDays late"
        }
    }
    
    fun getAttendanceGrade(): String {
        return when {
            attendancePercentage >= 95 -> "Excellent"
            attendancePercentage >= 85 -> "Good"
            attendancePercentage >= 75 -> "Fair"
            else -> "Needs Improvement"
        }
    }
}