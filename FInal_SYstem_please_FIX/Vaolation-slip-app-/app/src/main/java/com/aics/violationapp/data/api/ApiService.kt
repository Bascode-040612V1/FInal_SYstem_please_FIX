package com.aics.violationapp.data.api

import com.aics.violationapp.data.model.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    
    @POST("auth/login.php")
    suspend fun login(@Body request: LoginRequest): Response<ApiResponse<User>>
    
    @POST("auth/register.php")
    suspend fun register(@Body request: RegisterRequest): Response<ApiResponse<User>>
    
    @GET("auth/get_rfid.php")
    suspend fun getRfidNumber(): Response<ApiResponse<String>>
    
    @GET("students/search.php")
    suspend fun searchStudent(@Query("student_id") studentId: String): Response<ApiResponse<Student>>
    
    @GET("violations/types.php")
    suspend fun getViolationTypes(): Response<ApiResponse<List<ViolationType>>>
    
    @POST("violations/submit.php")
    suspend fun submitViolation(@Body request: ViolationRequest): Response<ApiResponse<ViolationResponse>>
    
    @GET("violations/student.php")
    suspend fun getStudentViolations(@Query("student_id") studentId: String): Response<ApiResponse<List<Violation>>>
    
    @GET("violations/offense_counts.php")
    suspend fun getOffenseCounts(@Query("student_id") studentId: String): Response<ApiResponse<Map<String, Int>>>
    
    @GET("test/connection.php")
    suspend fun testConnection(): Response<ApiResponse<String>>
    
    @Multipart
    @POST("admin/upload_admin_image.php")
    suspend fun uploadAdminImage(
        @Part("admin_id") adminId: RequestBody,
        @Part image: MultipartBody.Part
    ): Response<ApiResponse<User>>
    
    @GET("admin/get_admin_profile.php")
    suspend fun getAdminProfile(
        @Query("admin_id") adminId: Int
    ): Response<ApiResponse<User>>
}
