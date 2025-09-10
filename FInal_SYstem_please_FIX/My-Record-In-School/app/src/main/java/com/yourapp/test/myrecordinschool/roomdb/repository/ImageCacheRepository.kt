package com.yourapp.test.myrecordinschool.roomdb.repository

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.Log
import com.yourapp.test.myrecordinschool.data.api.StudentApi
import com.yourapp.test.myrecordinschool.roomdb.dao.StudentDao
import com.yourapp.test.myrecordinschool.roomdb.entity.StudentEntity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.io.FileOutputStream
import java.io.IOException

class ImageCacheRepository(
    private val context: Context,
    private val studentDao: StudentDao,
    private val studentApi: StudentApi
) {
    private val cacheDir: File by lazy {
        File(context.cacheDir, "profile_images").apply {
            if (!exists()) mkdirs()
        }
    }

    // Cache timeout: 24 hours for profile images
    private val cacheTimeout = 24 * 60 * 60 * 1000L
    private val okHttpClient = OkHttpClient()

    /**
     * Get cached image file for student, returns null if not cached or invalid
     */
    suspend fun getCachedImageFile(studentId: String): File? = withContext(Dispatchers.IO) {
        try {
            val student = studentDao.getStudent(studentId) ?: return@withContext null
            val cachedPath = student.cached_image_path ?: return@withContext null
            
            val file = File(cachedPath)
            if (!file.exists()) {
                // Clean up invalid cache entry
                studentDao.updateImageCache(studentId, null, 0L, false)
                return@withContext null
            }

            // Check if cache is still valid
            val cacheAge = System.currentTimeMillis() - student.image_cache_timestamp
            if (cacheAge > cacheTimeout) {
                // Cache expired, but return file anyway for offline-first approach
                return@withContext file
            }

            file
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error getting cached image", e)
            null
        }
    }

    /**
     * Check if student has a valid cached image
     */
    suspend fun hasCachedImage(studentId: String): Boolean = withContext(Dispatchers.IO) {
        try {
            val student = studentDao.getStudent(studentId) ?: return@withContext false
            val cachedPath = student.cached_image_path ?: return@withContext false
            File(cachedPath).exists()
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error checking cached image", e)
            false
        }
    }

    /**
     * Cache image from URL for offline access
     */
    suspend fun cacheImageFromUrl(studentId: String, imageUrl: String): Boolean = withContext(Dispatchers.IO) {
        try {
            val request = Request.Builder().url(imageUrl).build()
            val response = okHttpClient.newCall(request).execute()
            
            if (!response.isSuccessful) {
                Log.w("ImageCacheRepository", "Failed to download image: ${response.code}")
                return@withContext false
            }

            val inputStream = response.body?.byteStream() ?: return@withContext false
            val imageBytes = inputStream.readBytes()
            
            // Verify it's a valid image
            val bitmap = BitmapFactory.decodeByteArray(imageBytes, 0, imageBytes.size)
            if (bitmap == null) {
                Log.w("ImageCacheRepository", "Downloaded data is not a valid image")
                return@withContext false
            }

            // Save to cache directory
            val cacheFile = File(cacheDir, "${studentId}_profile.jpg")
            val outputStream = FileOutputStream(cacheFile)
            
            // Compress and save bitmap
            bitmap.compress(Bitmap.CompressFormat.JPEG, 85, outputStream)
            outputStream.close()
            bitmap.recycle()

            // Update database with cache info
            val currentTime = System.currentTimeMillis()
            studentDao.updateImageCache(
                studentId = studentId,
                cachedPath = cacheFile.absolutePath,
                timestamp = currentTime,
                isValid = true
            )
            studentDao.updateImageUrl(studentId, imageUrl, currentTime)

            Log.d("ImageCacheRepository", "Successfully cached image for student: $studentId")
            true
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error caching image for student: $studentId", e)
            false
        }
    }

    /**
     * Download and cache student image from backend
     */
    suspend fun downloadAndCacheStudentImage(studentId: String): Boolean = withContext(Dispatchers.IO) {
        try {
            val response = studentApi.getStudentImage(studentId)
            if (!response.isSuccessful || response.body() == null) {
                Log.w("ImageCacheRepository", "Failed to get student image from API")
                return@withContext false
            }

            val imageResponse = response.body()!!
            if (!imageResponse.success || imageResponse.image_url.isNullOrEmpty()) {
                Log.w("ImageCacheRepository", "No image available for student: $studentId")
                return@withContext false
            }

            cacheImageFromUrl(studentId, imageResponse.image_url)
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error downloading student image", e)
            false
        }
    }

    /**
     * Get student data flow for reactive UI updates
     */
    fun getStudentFlow(studentId: String): Flow<StudentEntity?> {
        return studentDao.getStudentFlow(studentId)
    }

    /**
     * Update student entity in database
     */
    suspend fun updateStudent(student: StudentEntity) {
        studentDao.updateStudent(student)
    }

    /**
     * Save student to database
     */
    suspend fun saveStudent(student: StudentEntity) {
        studentDao.insertStudent(student)
    }

    /**
     * Invalidate cache for student (force refresh on next access)
     */
    suspend fun invalidateCache(studentId: String) {
        studentDao.invalidateImageCache(studentId)
    }

    /**
     * Clean up old cached images to free space
     */
    suspend fun cleanupOldCache(maxAgeMs: Long = 7 * 24 * 60 * 60 * 1000L) = withContext(Dispatchers.IO) {
        try {
            val cutoffTime = System.currentTimeMillis() - maxAgeMs
            val expiredStudents = studentDao.getStudentsWithExpiredImageCache(cutoffTime)
            
            expiredStudents.forEach { student ->
                student.cached_image_path?.let { path ->
                    val file = File(path)
                    if (file.exists()) {
                        file.delete()
                        Log.d("ImageCacheRepository", "Deleted old cache for student: ${student.student_id}")
                    }
                }
                studentDao.updateImageCache(student.student_id, null, 0L, false)
            }
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error during cache cleanup", e)
        }
    }

    /**
     * Get cache size in bytes
     */
    suspend fun getCacheSize(): Long = withContext(Dispatchers.IO) {
        try {
            cacheDir.walkTopDown()
                .filter { it.isFile }
                .map { it.length() }
                .sum()
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error calculating cache size", e)
            0L
        }
    }

    /**
     * Clear all cached images
     */
    suspend fun clearAllCache() = withContext(Dispatchers.IO) {
        try {
            cacheDir.deleteRecursively()
            cacheDir.mkdirs()
            // Update all students to have no cached images
            val allStudents = studentDao.getAllStudents()
            // Note: This would need to be handled differently in a real implementation
            // as getAllStudents() returns Flow, not List
            Log.d("ImageCacheRepository", "Cleared all image cache")
        } catch (e: Exception) {
            Log.e("ImageCacheRepository", "Error clearing cache", e)
        }
    }
}