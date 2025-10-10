package com.yourapp.test.myrecordinschool.data.model

import com.yourapp.test.myrecordinschool.roomdb.entity.AttendanceEntity

data class Attendance(
    val id: Int = 0,
    val student_id: String = "",
    val student_name: String = "",
    val student_number: String = "",
    val date: String = "",
    val time_in: String? = null,
    val time_out: String? = null,
    val status: String = "ABSENT", // PRESENT, ABSENT, LATE, EARLY, VERY_LATE
    val attendance_type: String = "regular",
    val created_at: String = ""
)

data class AttendanceResponse(
    val success: Boolean,
    val message: String,
    val attendance: List<Attendance> = emptyList()
)

// Enhanced for offline-first functionality - can work with both API and Room data
data class AttendanceCalendarDay(
    val day: Int,
    val isCurrentMonth: Boolean,
    val attendance: AttendanceEntity? = null, // Changed to work with Room entity
    val isToday: Boolean = false
)

data class AttendanceMonth(
    val month: Int,
    val year: Int,
    val days: List<AttendanceCalendarDay>
)

// Extension function to convert AttendanceEntity to Attendance for compatibility
fun AttendanceEntity.toAttendance(): Attendance {
    return Attendance(
        id = this.id,
        student_id = this.student_id,
        student_name = this.student_name,
        student_number = this.student_number,
        date = this.date,
        time_in = this.time_in,
        time_out = this.time_out,
        status = this.status,
        attendance_type = this.attendance_type,
        created_at = this.created_at
    )
}