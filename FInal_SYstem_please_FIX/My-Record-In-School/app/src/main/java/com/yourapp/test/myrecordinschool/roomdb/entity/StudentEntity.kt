package com.yourapp.test.myrecordinschool.roomdb.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

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