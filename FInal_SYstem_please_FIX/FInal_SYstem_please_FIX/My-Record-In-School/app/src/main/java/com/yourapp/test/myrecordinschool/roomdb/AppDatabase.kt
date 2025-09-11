package com.yourapp.test.myrecordinschool.roomdb

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase
import com.yourapp.test.myrecordinschool.roomdb.dao.StudentDao
import com.yourapp.test.myrecordinschool.roomdb.dao.ViolationDao
// Removed AttendanceDao import - not needed for violations-only app
import com.yourapp.test.myrecordinschool.roomdb.entity.StudentEntity
import com.yourapp.test.myrecordinschool.roomdb.entity.ViolationEntity
// Removed AttendanceEntity import - not needed for violations-only app

@Database(
    entities = [ViolationEntity::class, StudentEntity::class], // Removed AttendanceEntity
    version = 2,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun violationDao(): ViolationDao
    // Removed attendanceDao() - not needed for violations-only app
    abstract fun studentDao(): StudentDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        // Migration from version 1 to 2 (adding students table)
        val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(database: SupportSQLiteDatabase) {
                database.execSQL(
                    """
                    CREATE TABLE IF NOT EXISTS `students` (
                        `student_id` TEXT NOT NULL,
                        `name` TEXT NOT NULL,
                        `year` TEXT NOT NULL,
                        `course` TEXT NOT NULL,
                        `section` TEXT NOT NULL,
                        `password` TEXT NOT NULL,
                        `created_at` TEXT NOT NULL,
                        `updated_at` TEXT NOT NULL,
                        `image_url` TEXT,
                        `cached_image_path` TEXT,
                        `image_last_modified` INTEGER,
                        `image_cache_timestamp` INTEGER NOT NULL,
                        `image_cache_valid` INTEGER NOT NULL,
                        `last_sync_timestamp` INTEGER NOT NULL,
                        `is_synced` INTEGER NOT NULL,
                        `local_changes` INTEGER NOT NULL,
                        PRIMARY KEY(`student_id`)
                    )
                    """.trimIndent()
                )
            }
        }

        fun getDatabase(context: Context): AppDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    AppDatabase::class.java,
                    "school_db"   // ✅ database file name
                )
                .addMigrations(MIGRATION_1_2)
                .build()
                INSTANCE = instance
                instance
            }
        }
    }
}
