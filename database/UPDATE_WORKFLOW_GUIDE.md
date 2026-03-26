# 🔄 Database Update Management System

## 📋 **How It Works**

When you make database changes locally, I'll help you generate proper SQL update scripts that you can run on your hosting environment to keep both databases synchronized.

## 🛠️ **Available Tools**

### **1. Update Tracking File**
- **`hosting_updates.sql`** - Logs all changes after initial deployment
- Each update is versioned and documented
- Ready to copy/paste into hosting SQL interface

### **2. Update Generator Script**
- **`update_generator.php`** - Automated SQL generation
- Creates proper UPDATE scripts for hosting
- Handles tables, columns, and data changes

## 📝 **Workflow Process**

### **When You Make Local Changes:**

1. **Tell me what you changed** (e.g., "Added new column to members table")
2. **I generate the SQL script** for hosting deployment  
3. **Copy the SQL** from `hosting_updates.sql`
4. **Run on your hosting** database via phpMyAdmin/cPanel
5. **Both databases stay synchronized**

## 🔧 **Common Change Types I Can Help With:**

### **Table Structure Changes:**
- ✅ Add new tables
- ✅ Add new columns  
- ✅ Modify column types
- ✅ Add indexes
- ✅ Create foreign keys

### **Data Changes:**
- ✅ Insert new records
- ✅ Update existing data
- ✅ Add new system settings
- ✅ Bulk data imports

### **System Updates:**
- ✅ Add new services
- ✅ Create new departments
- ✅ Update user permissions
- ✅ Modify configurations

## 📊 **Example Usage**

```sql
-- UPDATE VERSION: 1.0.1
-- DATE: 2025-11-27
-- DESCRIPTION: Added middle name field for members

ALTER TABLE members ADD COLUMN middle_name VARCHAR(50) AFTER name;

-- VERIFICATION:
-- DESCRIBE members; (should show new middle_name column)
```

## ✅ Latest Data Fixes (2026-03-15)

Use the latest blocks in [database/db_updates.sql](database/db_updates.sql):

1. `[2026-03-15] Status-column compatibility hardening`
2. `[2026-03-15] Unified people foundation (modular monolith)`

What the unified people block adds:
- Creates `people` as a single source-of-truth identity table
- Creates `person_lifecycle_events` for journey timeline tracking
- Adds nullable `person_id` links to `visitors`, `new_converts`, and `members`
- Backfills `person_id` using email/phone/name matching
- Seeds lifecycle events (`visitor_checked_in`, `became_new_convert`, `became_member`)

Recommended run order:
1. Backup database
2. Run the full `database/db_updates.sql`
3. Verify summary with:
   - `SELECT COUNT(*) FROM people;`
   - `SELECT COUNT(*) FROM person_lifecycle_events;`
   - `SELECT COUNT(*) FROM visitors WHERE person_id IS NOT NULL;`
   - `SELECT COUNT(*) FROM new_converts WHERE person_id IS NOT NULL;`
   - `SELECT COUNT(*) FROM members WHERE person_id IS NOT NULL;`

## 🎯 **What This Prevents:**
- ❌ Database inconsistencies between local/hosting
- ❌ Lost data when updating hosting
- ❌ Broken functionality due to missing changes
- ❌ Manual SQL writing errors

---

**💡 Ready to help! Just tell me what database changes you need to make, and I'll generate the hosting update script for you.**