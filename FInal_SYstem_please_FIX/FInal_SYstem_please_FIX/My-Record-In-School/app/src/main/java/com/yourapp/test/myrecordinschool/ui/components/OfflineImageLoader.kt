package com.yourapp.test.myrecordinschool.ui.components

import android.graphics.BitmapFactory
import android.util.Log
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Person
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.vector.rememberVectorPainter
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import coil.request.ImageRequest
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

/**
 * Offline-first image loader that prioritizes cached images for optimal offline experience
 */
@Composable
fun OfflineImageLoader(
    studentId: String,
    imageUrl: String? = null,
    modifier: Modifier = Modifier,
    size: Dp = 48.dp,
    contentDescription: String? = null,
    showLoadingIndicator: Boolean = true,
    fallbackIcon: @Composable () -> Unit = {
        Icon(
            imageVector = Icons.Default.Person,
            contentDescription = contentDescription,
            modifier = Modifier.size(size * 0.6f),
            tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
        )
    }
) {
    val context = LocalContext.current
    val viewModel: OfflineImageViewModel = viewModel { OfflineImageViewModel(context) }
    
    var imageState by remember { mutableStateOf<ImageState>(ImageState.Loading) }
    var cachedImageBitmap by remember { mutableStateOf<androidx.compose.ui.graphics.ImageBitmap?>(null) }

    // Load cached image on composition
    LaunchedEffect(studentId, imageUrl) {
        try {
            val cachedFile = viewModel.getCachedImage(studentId)
            if (cachedFile != null && cachedFile.exists()) {
                // Load cached image
                val bitmap = withContext(Dispatchers.IO) {
                    try {
                        BitmapFactory.decodeFile(cachedFile.absolutePath)
                    } catch (e: Exception) {
                        Log.w("OfflineImageLoader", "Failed to decode cached image: ${cachedFile.absolutePath}", e)
                        null
                    }
                }
                if (bitmap != null) {
                    cachedImageBitmap = bitmap.asImageBitmap()
                    imageState = ImageState.CachedSuccess
                } else {
                    imageState = ImageState.Error
                }
            } else {
                // No cached image, try to download if URL provided
                if (!imageUrl.isNullOrEmpty()) {
                    imageState = ImageState.Loading
                    val success = viewModel.downloadAndCacheImage(studentId, imageUrl)
                    if (success) {
                        // Reload cached image after download
                        val newCachedFile = viewModel.getCachedImage(studentId)
                        if (newCachedFile?.exists() == true) {
                            val bitmap = withContext(Dispatchers.IO) {
                                try {
                                    BitmapFactory.decodeFile(newCachedFile.absolutePath)
                                } catch (e: Exception) {
                                    Log.w("OfflineImageLoader", "Failed to decode newly cached image: ${newCachedFile.absolutePath}", e)
                                    null
                                }
                            }
                            if (bitmap != null) {
                                cachedImageBitmap = bitmap.asImageBitmap()
                                imageState = ImageState.Success
                            }
                        }
                    } else {
                        imageState = ImageState.Error
                    }
                } else {
                    imageState = ImageState.Error
                }
            }
        } catch (e: Exception) {
            Log.e("OfflineImageLoader", "Error loading image for student: $studentId", e)
            imageState = ImageState.Error
        }
    }

    Box(
        modifier = modifier
            .size(size)
            .clip(CircleShape)
            .background(MaterialTheme.colorScheme.surfaceVariant),
        contentAlignment = Alignment.Center
    ) {
        when (imageState) {
            is ImageState.Loading -> {
                if (showLoadingIndicator) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(size * 0.4f),
                        strokeWidth = 2.dp
                    )
                } else {
                    fallbackIcon()
                }
            }
            
            is ImageState.CachedSuccess, is ImageState.Success -> {
                cachedImageBitmap?.let { bitmap ->
                    Image(
                        bitmap = bitmap,
                        contentDescription = contentDescription,
                        modifier = Modifier.fillMaxSize(),
                        contentScale = ContentScale.Crop
                    )
                } ?: run {
                    // Fallback to network image if cached bitmap failed to load
                    if (!imageUrl.isNullOrEmpty()) {
                        AsyncImage(
                            model = ImageRequest.Builder(context)
                                .data(imageUrl)
                                .crossfade(true)
                                .build(),
                            contentDescription = contentDescription,
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop,
                            error = rememberVectorPainter(Icons.Default.Person)
                        )
                    } else {
                        fallbackIcon()
                    }
                }
            }
            
            is ImageState.Error -> {
                // Try to load from network as last resort if URL is available
                if (!imageUrl.isNullOrEmpty()) {
                    AsyncImage(
                        model = ImageRequest.Builder(context)
                            .data(imageUrl)
                            .crossfade(true)
                            .build(),
                        contentDescription = contentDescription,
                        modifier = Modifier.fillMaxSize(),
                        contentScale = ContentScale.Crop,
                        error = rememberVectorPainter(Icons.Default.Person)
                    )
                } else {
                    fallbackIcon()
                }
            }
        }
        
        // Show cache indicator for debugging (remove in production)
        if (imageState is ImageState.CachedSuccess) {
            Box(
                modifier = Modifier
                    .align(Alignment.BottomEnd)
                    .size(8.dp)
                    .clip(CircleShape)
                    .background(MaterialTheme.colorScheme.primary)
            )
        }
    }
}

/**
 * States for image loading
 */
sealed class ImageState {
    object Loading : ImageState()
    object Success : ImageState()
    object CachedSuccess : ImageState()
    object Error : ImageState()
}

/**
 * Simplified version for basic profile image display
 */
@Composable
fun ProfileImage(
    studentId: String,
    imageUrl: String? = null,
    modifier: Modifier = Modifier,
    size: Dp = 48.dp,
    contentDescription: String? = "Profile Image"
) {
    OfflineImageLoader(
        studentId = studentId,
        imageUrl = imageUrl,
        modifier = modifier,
        size = size,
        contentDescription = contentDescription,
        showLoadingIndicator = false
    )
}