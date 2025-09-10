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
    
    // Data state for violations
    private val _violationDataState = MutableStateFlow<DataState<List<ViolationEntity>>>(DataState.Loading)
    val violationDataState: StateFlow<DataState<List<ViolationEntity>>> = _violationDataState.asStateFlow()
    
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
    val violationsFromDb: LiveData<List<ViolationEntity>> = 
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
        loadViolationsOfflineFirst()
        syncManager.startPeriodicSync()
    }
    
    override fun onCleared() {
        super.onCleared()
        syncManager.stopPeriodicSync()
    }
    
    private fun loadViolationsOfflineFirst() {
        val studentId = appPreferences.getStudentId()
        if (studentId == null) {
            _violationDataState.value = DataState.Error("Student ID not found. Please log in again.")
            return
        }
        
        viewModelScope.launch {
            try {
                // CRITICAL FIX: Always show cached data first, regardless of network state
                val cachedCount = repository.getViolationCount(studentId)
                
                if (cachedCount > 0) {
                    // Show cached data immediately for better UX
                    _violationDataState.value = DataState.Cached(
                        data = emptyList(), // Will be populated by Flow
                        isStale = shouldRefreshData()
                    )
                    
                    android.util.Log.d("ViolationViewModel", "Loaded $cachedCount cached violations for offline-first experience")
                    
                    // Background sync without blocking UI
                    viewModelScope.launch {
                        try {
                            if (syncManager.networkState.value == NetworkState.Available) {
                                syncManager.syncViolations(forceRefresh = false)
                            }
                        } catch (e: Exception) {
                            // Silent background sync failure - user still has cached data
                            android.util.Log.w("ViolationViewModel", "Background sync failed but cached data available", e)
                        }
                    }
                } else {
                    // No cached data - must attempt initial sync
                    _violationDataState.value = DataState.Loading
                    
                    if (syncManager.networkState.value == NetworkState.Available) {
                        val success = syncManager.syncViolations(forceRefresh = true)
                        if (success) {
                            _violationDataState.value = DataState.Success(emptyList())
                        } else {
                            _violationDataState.value = DataState.Error("Unable to load violations. Please check your connection and try again.")
                        }
                    } else {
                        _violationDataState.value = DataState.Error("No cached data available. Please connect to the internet to load violations.")
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("ViolationViewModel", "Error in offline-first loading", e)
                _violationDataState.value = DataState.Error("Error loading violations: ${e.message}")
            }
        }
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
            
            try {
                if (syncManager.networkState.value == NetworkState.Available) {
                    val success = syncManager.syncViolations(forceRefresh = true)
                    
                    if (success) {
                        _violationDataState.value = DataState.Success(emptyList())
                        _errorMessage.value = ""
                        android.util.Log.d("ViolationViewModel", "Violations refreshed successfully")
                    } else {
                        // Keep showing cached data if available, but indicate sync failed
                        val studentId = appPreferences.getStudentId()
                        val cachedCount = studentId?.let { repository.getViolationCount(it) } ?: 0
                        
                        if (cachedCount > 0) {
                            _violationDataState.value = DataState.Cached(
                                data = emptyList(),
                                isStale = true
                            )
                            _errorMessage.value = "Sync failed but showing cached data. Please try again later."
                        } else {
                            _violationDataState.value = DataState.Error("Failed to refresh violations. Please check your connection.")
                            _errorMessage.value = "Failed to refresh violations"
                        }
                    }
                } else {
                    // Offline - show cached data if available
                    val studentId = appPreferences.getStudentId()
                    val cachedCount = studentId?.let { repository.getViolationCount(it) } ?: 0
                    
                    if (cachedCount > 0) {
                        _violationDataState.value = DataState.Cached(
                            data = emptyList(),
                            isStale = true
                        )
                        _errorMessage.value = "Offline mode - showing cached data"
                    } else {
                        _violationDataState.value = DataState.Error("No internet connection and no cached data available.")
                        _errorMessage.value = "No internet connection"
                    }
                }
            } catch (e: Exception) {
                android.util.Log.e("ViolationViewModel", "Error refreshing violations", e)
                _violationDataState.value = DataState.Error("Error refreshing violations: ${e.message}")
                _errorMessage.value = "Error refreshing violations"
            }
            
            _isLoading.value = false
        }
    }

    fun acknowledgeViolation(violationId: Int) {
        viewModelScope.launch {
            try {
                // CRITICAL FIX: Update local database immediately for offline-first experience
                repository.updateAcknowledgment(violationId, 1)
                android.util.Log.d("ViolationViewModel", "Violation $violationId acknowledged locally")
                
                // Attempt background sync without blocking UI
                viewModelScope.launch {
                    try {
                        if (syncManager.networkState.value == NetworkState.Available) {
                            val success = syncManager.syncAcknowledgment(violationId)
                            if (success) {
                                android.util.Log.d("ViolationViewModel", "Violation $violationId acknowledged on server")
                            } else {
                                android.util.Log.w("ViolationViewModel", "Failed to sync acknowledgment to server, but local update preserved")
                                // Note: We don't revert the local change as this would create poor UX
                                // The sync will be retried in the next sync cycle
                            }
                        } else {
                            android.util.Log.d("ViolationViewModel", "Offline - acknowledgment will sync when connection is restored")
                        }
                    } catch (e: Exception) {
                        android.util.Log.w("ViolationViewModel", "Background acknowledgment sync failed, will retry later", e)
                        // Don't show error to user as local operation succeeded
                    }
                }
            } catch (e: Exception) {
                _errorMessage.value = "Error acknowledging violation: ${e.message}"
                android.util.Log.e("ViolationViewModel", "Error acknowledging violation locally", e)
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