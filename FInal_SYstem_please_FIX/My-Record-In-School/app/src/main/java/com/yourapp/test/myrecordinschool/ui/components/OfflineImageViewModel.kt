package com.yourapp.test.myrecordinschool.ui.components

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.yourapp.test.myrecordinschool.data.api.RetrofitClient
import com.yourapp.test.myrecordinschool.data.preferences.AppPreferences
import com.yourapp.test.myrecordinschool.roomdb.AppDatabase
import com.yourapp.test.myrecordinschool.roomdb.repository.ImageCacheRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

class OfflineImageViewModel(context: Context) : ViewModel() {

    private val appPreferences = AppPreferences(context)
    private val database = AppDatabase.getDatabase(context)
    private val studentApi = RetrofitClient.getStudentApi(appPreferences.getAppConfig().baseUrl)
    private val imageCacheRepository = ImageCacheRepository(
        context = context,
        studentDao = database.studentDao(),
        studentApi = studentApi
    )

    /**
     * Get cached image file for student
     */
    suspend fun getCachedImage(studentId: String): File? = withContext(Dispatchers.IO) {
        try {
            imageCacheRepository.getCachedImageFile(studentId)
        } catch (e: Exception) {
            null
        }
    }

    /**
     * Download and cache image for offline access
     */
    suspend fun downloadAndCacheImage(studentId: String, imageUrl: String): Boolean = withContext(Dispatchers.IO) {
        try {
            imageCacheRepository.cacheImageFromUrl(studentId, imageUrl)
        } catch (e: Exception) {
            false
        }
    }

    /**
     * Download student image from backend and cache it
     */
    fun downloadStudentImage(studentId: String) {
        viewModelScope.launch {
            try {
                imageCacheRepository.downloadAndCacheStudentImage(studentId)
            } catch (e: Exception) {
                // Handle error silently for background operation
            }
        }
    }

    /**
     * Check if student has cached image
     */
    suspend fun hasCachedImage(studentId: String): Boolean = withContext(Dispatchers.IO) {
        try {
            imageCacheRepository.hasCachedImage(studentId)
        } catch (e: Exception) {
            false
        }
    }

    /**
     * Invalidate cache for student (force refresh)
     */
    fun invalidateCache(studentId: String) {
        viewModelScope.launch {
            try {
                imageCacheRepository.invalidateCache(studentId)
            } catch (e: Exception) {
                // Handle error silently
            }
        }
    }
}