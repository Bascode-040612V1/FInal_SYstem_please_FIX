package com.yourapp.test.myrecordinschool.ui.components

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.yourapp.test.myrecordinschool.roomdb.repository.ImageCacheRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import javax.inject.Inject

@HiltViewModel
class OfflineImageViewModel @Inject constructor(
    private val imageCacheRepository: ImageCacheRepository
) : ViewModel() {

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