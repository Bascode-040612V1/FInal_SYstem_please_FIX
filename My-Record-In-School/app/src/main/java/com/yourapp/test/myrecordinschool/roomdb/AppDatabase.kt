package com.yourapp.test.myrecordinschool.roomdb

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase
import com.yourapp.test.myrecordinschool.roomdb.dao.AttendanceDao
import com.yourapp.test.myrecordinschool.roomdb.dao.ViolationDao
import com.yourapp.test.myrecordinschool.roomdb.entity.AttendanceEntity
import com.yourapp.test.myrecordinschool.roomdb.entity.ViolationEntity

@Database(
    entities = [ViolationEntity::class, AttendanceEntity::class],
    version = 2,  // Incremented for offline support fields
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun violationDao(): ViolationDao
    abstract fun attendanceDao(): AttendanceDao

    companion object {
        @Volatile
        private var INSTANCE: AppDatabase? = null

        // Migration from version 1 to 2 - adds offline support fields
        private val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(database: SupportSQLiteDatabase) {
                // Add offline support fields to violations table
                database.execSQL(
                    "ALTER TABLE violations ADD COLUMN last_sync_timestamp INTEGER NOT NULL DEFAULT ${System.currentTimeMillis()}"
                )
                database.execSQL(
                    "ALTER TABLE violations ADD COLUMN is_synced INTEGER NOT NULL DEFAULT 1"
                )
                database.execSQL(
                    "ALTER TABLE violations ADD COLUMN local_changes INTEGER NOT NULL DEFAULT 0"
                )
                
                // Add offline support fields to attendance table
                database.execSQL(
                    "ALTER TABLE attendance ADD COLUMN last_sync_timestamp INTEGER NOT NULL DEFAULT ${System.currentTimeMillis()}"
                )
                database.execSQL(
                    "ALTER TABLE attendance ADD COLUMN is_synced INTEGER NOT NULL DEFAULT 1"
                )
                database.execSQL(
                    "ALTER TABLE attendance ADD COLUMN local_changes INTEGER NOT NULL DEFAULT 0"
                )
                database.execSQL(
                    "ALTER TABLE attendance ADD COLUMN offline_created INTEGER NOT NULL DEFAULT 0"
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
