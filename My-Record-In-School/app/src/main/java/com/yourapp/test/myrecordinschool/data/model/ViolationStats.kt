package com.yourapp.test.myrecordinschool.data.model

import com.yourapp.test.myrecordinschool.roomdb.entity.ViolationEntity

/**
 * Data class representing violation statistics for offline-first UI display
 * Used in ViolationViewModel to track violation counts, sync status, and provide
 * analytics data even when offline
 */
data class ViolationStats(
    val totalViolations: Int = 0,
    val unacknowledgedCount: Int = 0,
    val categoryCounts: Map<String, Int> = emptyMap(),
    val recentViolations: List<ViolationEntity> = emptyList(),
    val hasNewViolations: Boolean = false,
    val lastUpdateTime: Long = 0L,
    val offlineChangesCount: Int = 0,
    val syncPendingCount: Int = 0
) {
    /**
     * Check if there are any violations to display
     */
    fun hasViolations(): Boolean = totalViolations > 0
    
    /**
     * Get the most frequent violation category
     */
    fun getMostFrequentCategory(): String? {
        return categoryCounts.maxByOrNull { it.value }?.key
    }
    
    /**
     * Check if user has urgent violations requiring attention
     */
    fun hasUrgentViolations(): Boolean = unacknowledgedCount > 0
    
    /**
     * Get summary text for UI display
     */
    fun getSummaryText(): String {
        return when {
            totalViolations == 0 -> "No violations recorded"
            unacknowledgedCount > 0 -> "$unacknowledgedCount unacknowledged of $totalViolations total"
            else -> "$totalViolations violations (all acknowledged)"
        }
    }
    
    /**
     * Get sync status summary
     */
    fun getSyncStatusText(): String {
        return when {
            syncPendingCount > 0 -> "$syncPendingCount changes pending sync"
            offlineChangesCount > 0 -> "$offlineChangesCount offline changes"
            else -> "All data synchronized"
        }
    }
}