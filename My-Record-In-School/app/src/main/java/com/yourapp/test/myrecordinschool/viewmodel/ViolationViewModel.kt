package com.yourapp.test.myrecordinschool.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.asLiveData
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import com.yourapp.test.myrecordinschool.data.api.RetrofitClient
import com.yourapp.test.myrecordinschool.data.model.*
import com.yourapp.test.myrecordinschool.data.preferences.AppPreferences
import com.yourapp.test.myrecordinschool.data.sync.SyncManager
import com.yourapp.test.myrecordinschool.roomdb.AppDatabase
import com.yourapp.test.myrecordinschool.roomdb.entity.ViolationEntity
import com.yourapp.test.myrecordinschool.roomdb.repository.ViolationRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.launch

class ViolationViewModel(application: Application) : AndroidViewModel(application) {
    
    private val appPreferences = AppPreferences(application)
    private val violationDao = AppDatabase.getDatabase(application).violationDao()
    private val repository = ViolationRepository(violationDao)
    private val syncManager = SyncManager(application)
    
    // Data state for violations - Always show from local database (offline-first)
    private val _violationDataState = MutableStateFlow<DataState<List<ViolationEntity>>>(DataState.Loading)
    val violationDataState: StateFlow<DataState<List<ViolationEntity>>> = _violationDataState.asStateFlow()
    
    // Local database violations (primary source of truth)
    val violationsFromDb: LiveData<List<ViolationEntity>> = repository.getViolationsByStudent(
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
    
    // Violation statistics
    private val _violationStats = MutableLiveData<ViolationStats>()
    val violationStats: LiveData<ViolationStats> = _violationStats
    
    // Legacy LiveData for backwards compatibility
    private val _violations = MutableLiveData<List<Violation>>()
    val violations: LiveData<List<Violation>> = _violations
    
    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading
    
    private val _errorMessage = MutableLiveData<String>()
    val errorMessage: LiveData<String> = _errorMessage
    
    private val _selectedViolation = MutableLiveData<Violation?>()
    val selectedViolation: LiveData<Violation?> = _selectedViolation

    // Observable violations from database (offline-first)
    val violationsFromDbCompat: LiveData<List<ViolationEntity>> = 
        appPreferences.getStudentId()?.let { studentId ->
            repository.getViolationsByStudent(studentId).asLiveData()
        } ?: MutableLiveData(emptyList())
    
    // Sync status
    val syncStatus: StateFlow<SyncStatus> = syncManager.syncStatus
    val networkState: StateFlow<NetworkState> = syncManager.networkState
    
    // Unacknowledged violations count
    val unacknowledgedCount: LiveData<Int> = 
        appPreferences.getStudentId()?.let { studentId ->
            repository.getUnacknowledgedViolations(studentId)
                .map { it.size }
                .asLiveData()
        } ?: MutableLiveData(0)

    init {
        // Load violations from local database first (offline-first)
        loadViolationsFromDatabase()
        
        // Update sync status
        updateSyncInfo()
        
        // Monitor network state
        viewModelScope.launch {
            networkState.collect { state ->
                _isOfflineMode.value = state != NetworkState.Available
                if (state == NetworkState.Available) {
                    // Auto-sync when network becomes available
                    syncViolationsInBackground()
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
    
    private fun loadViolationsFromDatabase() {
        viewModelScope.launch {
            try {
                _isLoading.value = true
                _violationDataState.value = DataState.Loading
                
                val studentId = appPreferences.getStudentId()
                if (studentId.isNullOrEmpty()) {
                    _violationDataState.value = DataState.Error("Student ID not found")
                    return@launch
                }
                
                // Load from local database (offline-first)
                repository.getViolationsByStudent(studentId).collect { violations ->
                    _violationDataState.value = DataState.Success(violations)
                    updateViolationStats(violations)
                    
                    // Convert to API format for compatibility
                    val apiViolations = violations.map { entity ->
                        Violation(
                            id = entity.id,
                            student_id = entity.student_id,
                            student_name = entity.student_name,
                            year_level = entity.year_level,
                            course = entity.course,
                            section = entity.section,
                            violation_type = entity.violation_type,
                            violation_description = entity.violation_description,
                            offense_count = entity.offense_count,
                            original_offense_count = entity.original_offense_count,
                            penalty = entity.penalty,
                            recorded_by = entity.recorded_by,
                            date_recorded = entity.date_recorded,
                            acknowledged = entity.acknowledged,
                            category = entity.category
                        )
                    }
                    _violations.value = apiViolations
                }
                
                _isLoading.value = false
                
                // Try to sync in background if network is available
                if (networkState.value == NetworkState.Available) {
                    syncViolationsInBackground()
                }
                
            } catch (e: Exception) {
                _violationDataState.value = DataState.Error("Failed to load violations: ${e.message}")
                _isLoading.value = false
                android.util.Log.e("ViolationViewModel", "Error loading violations from database", e)
            }
        }
    }
    
    private suspend fun syncViolationsInBackground() {
        try {
            syncManager.syncViolations(forceRefresh = false)
            updateSyncInfo()
        } catch (e: Exception) {
            android.util.Log.w("ViolationViewModel", "Background sync failed", e)
        }
    }
    
    private fun updateViolationStats(violations: List<ViolationEntity>) {
        val stats = ViolationStats(
            totalViolations = violations.size,
            unacknowledgedCount = violations.count { it.acknowledged == 0 },
            categoryCounts = violations.groupingBy { it.category }.eachCount(),
            recentViolations = violations.take(5),
            hasNewViolations = violations.any { 
                it.acknowledged == 0 && 
                System.currentTimeMillis() - it.last_sync_timestamp < 86400000 // 24 hours
            }
        )
        _violationStats.value = stats
    }
    
    private fun updateSyncInfo() {
        viewModelScope.launch {
            val lastSync = appPreferences.getLastViolationSync()
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

    private fun loadViolationsOfflineFirst() {
        loadViolationsFromDatabase()
    }
    
    private fun shouldRefreshData(): Boolean {
        val lastSyncTime = syncManager.syncStatus.value.lastSyncTime
        val currentTime = System.currentTimeMillis()
        val fiveMinutes = 5 * 60 * 1000L
        return (currentTime - lastSyncTime) > fiveMinutes
    }

    fun loadViolations() {
        viewModelScope.launch {
            _isLoading.value = true
            _violationDataState.value = DataState.Loading
            
            val success = syncManager.syncViolations()
            
            if (success) {
                _violationDataState.value = DataState.Success(emptyList()) // Will be populated by Flow
            } else {
                val errorMsg = when (val syncState = syncManager.syncStatus.value.syncState) {
                    is SyncState.Error -> syncState.message
                    else -> "Failed to load violations"
                }
                _violationDataState.value = DataState.Error(errorMsg)
                _errorMessage.value = errorMsg
            }
            
            _isLoading.value = false
        }
    }
    
    fun refreshViolations() {
        viewModelScope.launch {
            _isLoading.value = true
            
            val success = syncManager.syncViolations(forceRefresh = true)
            
            if (success) {
                _violationDataState.value = DataState.Success(emptyList())
                _errorMessage.value = ""
            } else {
                val errorMsg = when (val syncState = syncManager.syncStatus.value.syncState) {
                    is SyncState.Error -> syncState.message
                    else -> "Failed to refresh violations"
                }
                _violationDataState.value = DataState.Error(errorMsg)
                _errorMessage.value = errorMsg
            }
            
            _isLoading.value = false
        }
    }

    fun acknowledgeViolation(violationId: Int) {
        viewModelScope.launch {
            try {
                // Update local database immediately for better UX
                repository.updateAcknowledgment(violationId, 1)
                
                // Sync with backend
                val success = syncManager.syncAcknowledgment(violationId)
                
                if (!success) {
                    // Revert local change if sync failed
                    repository.updateAcknowledgment(violationId, 0)
                    _errorMessage.value = "Failed to acknowledge violation. Please try again."
                }
            } catch (e: Exception) {
                _errorMessage.value = "Error acknowledging violation: ${e.message}"
                android.util.Log.e("ViolationViewModel", "Error acknowledging violation", e)
            }
        }
    }
    
    fun selectViolation(violation: Violation) {
        _selectedViolation.value = violation
    }
    
    fun clearSelectedViolation() {
        _selectedViolation.value = null
    }
    
    fun getViolationsByCategory(category: String): List<Violation> {
        return _violations.value?.filter { it.category == category } ?: emptyList()
    }
    
    fun getViolationCounts(): Map<String, Int> {
        val violations = _violations.value ?: return emptyMap()
        return violations.groupingBy { it.category }.eachCount()
    }
    
    fun clearError() {
        _errorMessage.value = ""
        syncManager.resetSyncState()
    }
    
    fun retryOperation() {
        clearError()
        loadViolations()
    }
    
    fun updateNetworkState(isAvailable: Boolean) {
        syncManager.updateNetworkState(isAvailable)
    }
    
    // Helper function to convert ViolationEntity to Violation for compatibility
    fun convertToViolation(entity: ViolationEntity): Violation {
        return Violation(
            id = entity.id,
            student_id = entity.student_id,
            student_name = entity.student_name,
            year_level = entity.year_level,
            course = entity.course,
            section = entity.section,
            violation_type = entity.violation_type,
            violation_description = entity.violation_description,
            offense_count = entity.offense_count,
            original_offense_count = entity.original_offense_count,
            penalty = entity.penalty,
            recorded_by = entity.recorded_by,
            date_recorded = entity.date_recorded,
            acknowledged = entity.acknowledged,
            category = entity.category
        )
    }
    
    fun debugAppState() {
        val studentId = appPreferences.getStudentId()
        val config = appPreferences.getAppConfig()
        val isLoggedIn = appPreferences.isLoggedIn()
        
        android.util.Log.d("ViolationViewModel", "Debug App State:")
        android.util.Log.d("ViolationViewModel", "  - Student ID: $studentId")
        android.util.Log.d("ViolationViewModel", "  - Is Logged In: $isLoggedIn")
        android.util.Log.d("ViolationViewModel", "  - Base URL: ${config.baseUrl}")
        android.util.Log.d("ViolationViewModel", "  - IP Address: ${config.ipAddress}")
        android.util.Log.d("ViolationViewModel", "  - Port: ${config.port}")
    }
    
    fun testConnectivity() {
        viewModelScope.launch {
            try {
                val config = appPreferences.getAppConfig()
                val studentId = appPreferences.getStudentId()
                
                android.util.Log.d("ViolationViewModel", "=== CONNECTIVITY TEST ===")
                android.util.Log.d("ViolationViewModel", "Base URL: ${config.baseUrl}")
                android.util.Log.d("ViolationViewModel", "Student ID: $studentId")
                android.util.Log.d("ViolationViewModel", "Full endpoint: ${config.baseUrl}violations/$studentId")
                
                if (studentId != null) {
                    val api = RetrofitClient.getViolationApi(config.baseUrl)
                    val response = api.getStudentViolations(studentId)
                    
                    android.util.Log.d("ViolationViewModel", "Response Status: ${response.code()}")
                    android.util.Log.d("ViolationViewModel", "Response Success: ${response.isSuccessful}")
                    android.util.Log.d("ViolationViewModel", "Response Body: ${response.body()}")
                    android.util.Log.d("ViolationViewModel", "Response Error: ${response.errorBody()?.string()}")
                    
                    if (response.isSuccessful) {
                        val body = response.body()
                        android.util.Log.d("ViolationViewModel", "Backend Success Flag: ${body?.success}")
                        android.util.Log.d("ViolationViewModel", "Backend Message: ${body?.message}")
                        android.util.Log.d("ViolationViewModel", "Violations Count: ${body?.violations?.size}")
                    }
                } else {
                    android.util.Log.e("ViolationViewModel", "Cannot test connectivity - no student ID")
                }
                android.util.Log.d("ViolationViewModel", "=== END CONNECTIVITY TEST ===")
            } catch (e: Exception) {
                android.util.Log.e("ViolationViewModel", "Connectivity test failed", e)
            }
        }
    }
}