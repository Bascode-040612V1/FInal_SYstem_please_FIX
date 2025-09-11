package com.yourapp.test.myrecordinschool.ui.screen

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.livedata.observeAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.yourapp.test.myrecordinschool.data.model.Violation
import com.yourapp.test.myrecordinschool.data.model.ViolationCategories
import com.yourapp.test.myrecordinschool.ui.components.ProfileImage
import com.yourapp.test.myrecordinschool.ui.theme.*
import com.yourapp.test.myrecordinschool.viewmodel.ViolationViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ViolationDetailScreen(
    violation: Violation,
    onNavigateBack: () -> Unit,
    violationViewModel: ViolationViewModel = viewModel()
) {
    val violations by violationViewModel.violations.observeAsState(emptyList())
    
    LaunchedEffect(violation.id) {
        if (violation.acknowledged == 0) {
            violationViewModel.acknowledgeViolation(violation.id)
        }
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        Blue40,
                        Blue40.copy(alpha = 0.8f),
                        MaterialTheme.colorScheme.background
                    )
                )
            )
    ) {
        // Header
        TopAppBar(
            title = {
                Text(
                    text = "Violation Details",
                    color = MaterialTheme.colorScheme.onPrimary,
                    fontWeight = FontWeight.Bold
                )
            },
            navigationIcon = {
                IconButton(onClick = onNavigateBack) {
                    Icon(
                        imageVector = Icons.Filled.ArrowBack,
                        contentDescription = "Back",
                        tint = MaterialTheme.colorScheme.onPrimary
                    )
                }
            },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = Blue40
            )
        )
        
        // Main Content - Scrollable
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Student Data Card
            item {
                StudentDataCard(
                    violation = violation,
                    violations = violations,
                    onClose = onNavigateBack
                )
            }
        }
    }
}

@Composable
private fun StudentDataCard(
    violation: Violation,
    violations: List<Violation>,
    onClose: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(24.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp)
        ) {
            // 1. Profile Icon next to Student Name, with Student Number below
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Profile Icon - using offline-first ProfileImage
                ProfileImage(
                    studentId = violation.student_id,
                    imageUrl = null, // Will be loaded from backend automatically
                    modifier = Modifier,
                    size = 72.dp,
                    contentDescription = "${violation.student_name} Profile Picture"
                )
                
                // Name of student and Student Number below it
                Column {
                    Text(
                        text = violation.student_name,
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = violation.student_id,
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            // Line separator
            HorizontalDivider(
                thickness = 1.dp,
                color = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
            )
            
            // 2. Course and Year and Section (historical data when violation was recorded)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = violation.course,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = "${violation.year_level} - ${violation.section}",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface
                )
            }
            
            // 3. Violations Committed: (title)
            Text(
                text = "Violations Committed:",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            
            // Group violations by category for this student
            val studentViolations = violations.filter { it.student_id == violation.student_id }
            val violationsByCategory = studentViolations.groupBy { it.category }
            
            // Display only categories that have violations (subtitles)
            Column(
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                ViolationCategories.getAllCategories().forEach { category ->
                    val categoryViolations = violationsByCategory[category.name] ?: emptyList()
                    if (categoryViolations.isNotEmpty()) {
                        ViolationCategorySection(
                            categoryName = category.name,
                            violations = categoryViolations
                        )
                    }
                }
            }
            
            // 4. Penalty List: (title)
            Text(
                text = "Penalty List:",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            
            // Get unique penalties for this student
            val studentPenalties = studentViolations.map { it.penalty }.distinct()
            
            Column(
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                studentPenalties.forEach { penalty ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Circle,
                            contentDescription = null,
                            modifier = Modifier.size(8.dp),
                            tint = MaterialTheme.colorScheme.primary
                        )
                        Text(
                            text = penalty,
                            style = MaterialTheme.typography.titleMedium,
                            color = MaterialTheme.colorScheme.onSurface
                        )
                    }
                }
            }
            
            // 5. Close button
            Button(
                onClick = onClose,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = MaterialTheme.colorScheme.primary
                )
            ) {
                Text(
                    text = "Close",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@Composable
private fun ViolationCategorySection(
    categoryName: String,
    violations: List<Violation>
) {
    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Category subtitle
        Text(
            text = "${categoryName.replace("_", " ")}:",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.SemiBold,
            color = MaterialTheme.colorScheme.primary
        )
        
        // List of violations in this category
        Column(
            modifier = Modifier.padding(start = 16.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            violations.forEach { violation ->
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.Circle,
                        contentDescription = null,
                        modifier = Modifier.size(6.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Text(
                        text = "${violation.violation_description} (${violation.offense_count}${getOrdinalSuffix(violation.offense_count)} offense)",
                        style = MaterialTheme.typography.bodyLarge,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }
            }
        }
    }
}

private fun getOrdinalSuffix(number: Int): String {
    return when {
        number % 100 in 11..13 -> "th"
        number % 10 == 1 -> "st"
        number % 10 == 2 -> "nd"
        number % 10 == 3 -> "rd"
        else -> "th"
    }
}