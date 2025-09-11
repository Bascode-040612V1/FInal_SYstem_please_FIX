package com.yourapp.test.myrecordinschool.data.model

data class Student(
    val id: Int = 0,
    val student_id: String = "",
    val name: String = "",
    val password: String = "",
    val year: String = "",
    val course: String = "",
    val section: String = "",
    val created_at: String = "",
    val updated_at: String = "",
    val image: String = "assets/default-profile.png"
)

data class LoginRequest(
    val student_id: String,
    val password: String
)

data class RegisterRequest(
    val student_id: String,
    val name: String,
    val password: String,
    val year: String,
    val course: String,
    val section: String,
    val rfid: String = ""
)

data class UpdateStudentRequest(
    val student_id: String,
    val year: String,
    val course: String,
    val section: String
)

data class AuthResponse(
    val success: Boolean,
    val message: String,
    val student: Student? = null
)

data class ImageUploadResponse(
    val success: Boolean,
    val message: String,
    val image_path: String? = null,
    val image_url: String? = null,
    val filename: String? = null
)