package com.yourapp.test.myrecordinschool.roomdb.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "attendance")
data class AttendanceEntity(
    @PrimaryKey val id: Int, // from backend
    val student_id: String,
    val student_name: String,
    val student_number: String,
    val date: String,
    val time_in: String?,
    val time_out: String?,
    val status: String,
    val attendance_type: String,
    val created_at: String,
    // Offline support fields for sync tracking
    val last_sync_timestamp: Long = System.currentTimeMillis(),
    val is_synced: Boolean = true,
    val local_changes: Boolean = false,
    val offline_created: Boolean = false
)
