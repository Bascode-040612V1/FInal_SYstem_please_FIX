package com.yourapp.test.myrecordinschool.roomdb.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import com.yourapp.test.myrecordinschool.roomdb.entity.StudentEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface StudentDao {

    @Query("SELECT * FROM students WHERE student_id = :studentId")
    suspend fun getStudent(studentId: String): StudentEntity?

    @Query("SELECT * FROM students WHERE student_id = :studentId")
    fun getStudentFlow(studentId: String): Flow<StudentEntity?>

    @Query("SELECT * FROM students")
    fun getAllStudents(): Flow<List<StudentEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertStudent(student: StudentEntity)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertStudents(students: List<StudentEntity>)

    @Update
    suspend fun updateStudent(student: StudentEntity)

    @Query("DELETE FROM students WHERE student_id = :studentId")
    suspend fun deleteStudent(studentId: String)

    @Query("DELETE FROM students")
    suspend fun deleteAllStudents()

    // Image cache specific queries
    @Query("UPDATE students SET cached_image_path = :cachedPath, image_cache_timestamp = :timestamp, image_cache_valid = :isValid WHERE student_id = :studentId")
    suspend fun updateImageCache(studentId: String, cachedPath: String?, timestamp: Long, isValid: Boolean)

    @Query("UPDATE students SET image_url = :imageUrl, image_last_modified = :lastModified WHERE student_id = :studentId")
    suspend fun updateImageUrl(studentId: String, imageUrl: String?, lastModified: Long?)

    @Query("SELECT cached_image_path FROM students WHERE student_id = :studentId")
    suspend fun getCachedImagePath(studentId: String): String?

    @Query("SELECT image_cache_valid FROM students WHERE student_id = :studentId")
    suspend fun isImageCacheValid(studentId: String): Boolean?

    @Query("SELECT image_cache_timestamp FROM students WHERE student_id = :studentId")
    suspend fun getImageCacheTimestamp(studentId: String): Long?

    @Query("SELECT * FROM students WHERE image_cache_valid = 0 OR image_cache_timestamp < :cutoffTime")
    suspend fun getStudentsWithExpiredImageCache(cutoffTime: Long): List<StudentEntity>

    @Query("UPDATE students SET image_cache_valid = 0 WHERE student_id = :studentId")
    suspend fun invalidateImageCache(studentId: String)

    // Sync tracking
    @Query("UPDATE students SET last_sync_timestamp = :timestamp, is_synced = :isSynced WHERE student_id = :studentId")
    suspend fun updateSyncStatus(studentId: String, timestamp: Long, isSynced: Boolean)

    @Query("SELECT * FROM students WHERE local_changes = 1")
    suspend fun getStudentsWithLocalChanges(): List<StudentEntity>

    @Query("UPDATE students SET local_changes = :hasChanges WHERE student_id = :studentId")
    suspend fun updateLocalChanges(studentId: String, hasChanges: Boolean)
}