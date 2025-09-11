package com.yourapp.test.myrecordinschool.data.api

import com.yourapp.test.myrecordinschool.data.model.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.*

interface StudentApi {
    @POST("auth/login.php")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>
    
    @POST("auth/register.php")
    suspend fun register(@Body request: RegisterRequest): Response<AuthResponse>
    
    @PUT("student/update.php")
    suspend fun updateStudent(@Body request: UpdateStudentRequest): Response<AuthResponse>
    
    @GET("student/{student_id}")
    suspend fun getStudent(@Path("student_id") studentId: String): Response<AuthResponse>
    
    // Image upload and retrieval endpoints
    @Multipart
    @POST("student/image.php")
    suspend fun uploadStudentImage(
        @Part("student_id") studentId: RequestBody,
        @Part image: MultipartBody.Part
    ): Response<ImageUploadResponse>
    
    @GET("student/image.php")
    suspend fun getStudentImage(@Query("student_id") studentId: String): Response<ImageUploadResponse>
}

interface ViolationApi {
    @GET("violations/index.php/{student_id}")
    suspend fun getStudentViolations(@Path("student_id") studentId: String): Response<ViolationResponse>
    
    @PUT("violations/acknowledge.php/{id}")
    suspend fun acknowledgeViolation(@Path("id") violationId: Int): Response<ViolationResponse>
    
    // Optimized endpoints for delta sync
    @GET("violations/index.php/{student_id}")
    suspend fun getStudentViolationsSince(
        @Path("student_id") studentId: String,
        @Query("since") timestamp: Long
    ): Response<ViolationResponse>
    
    @GET("violations/index.php/{student_id}")
    suspend fun getRecentViolations(
        @Path("student_id") studentId: String,
        @Query("limit") limit: Int = 20
    ): Response<ViolationResponse>
}

// Removed AttendanceApi interface - not needed for violations-only app

interface ConnectionApi {
    @GET("test_connection.php")
    suspend fun testConnection(): Response<ConnectionTestResponse>
}

interface RfidApi {
    @GET("rfid/get_latest.php")
    suspend fun getLatestRfid(): Response<RfidResponse>
}

data class RfidResponse(
    val success: Boolean,
    val message: String,
    val rfid_number: String? = null,
    val scanned_at: String? = null
)