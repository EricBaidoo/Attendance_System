# 📁 Bridge Ministries International - Church Management System

## 🏗️ **Project Structure**

```
ATTENDANCE SYSTEM/
├── 📁 assets/                     # Frontend resources
│   ├── css/                       # Stylesheets
│   ├── js/                        # JavaScript files
│   └── img/                       # Images
│
├── 📁 config/                     # Configuration files
│   └── database.php               # Database connection
│
├── 📁 database/                   # Database management
│   ├── 📁 archive/                # Old/deprecated SQL files
│   ├── hosting_deployment_clean.sql  # Main hosting deployment
│   ├── hosting_updates.sql       # Track changes for hosting
│   ├── update_generator.php      # Generate update scripts
│   ├── UPDATE_WORKFLOW_GUIDE.md  # Database update process
│   ├── cleanup_unused_tables.sql # Database cleanup script
│   ├── enhance_visitors.php      # Visitor system enhancements
│   ├── migrate_new_converts.php  # Convert migration
│   └── update_database.php       # Database update utilities
│
├── 📁 docs/                       # Documentation
│   ├── HOSTING_DEPLOYMENT_GUIDE.md  # Hosting setup guide
│   └── hosting_verification_checklist.md  # Post-deploy checklist
│
├── 📁 exports/                    # Data exports & imports
│   ├── 📁 mysql_workbench_exports/  # Clean table exports
│   └── *.csv                     # CSV data files
│
├── 📁 includes/                   # Shared components
│   ├── header.php                # Common header
│   └── footer.php                # Common footer
│
├── 📁 pages/                      # Application pages
│   ├── 📁 admin/                  # Admin functions
│   ├── 📁 attendance/             # Attendance management
│   ├── 📁 members/                # Member management
│   ├── 📁 reports/                # Reports & analytics
│   ├── 📁 services/               # Service management
│   └── 📁 visitors/               # Visitor management
│
├── index.php                     # Main dashboard
├── login.php                     # Authentication
├── logout.php                    # Session termination
└── README.md                     # This file
```

## 🎯 **Key Features**

### **👥 Member Management**
- Complete member database (213 members)
- Department organization
- Baptism tracking
- Contact management

### **🚪 Visitor System**
- Visitor registration
- Follow-up tracking
- Conversion workflow

### **🔄 New Converts**
- Convert management
- Baptism preparation
- Member conversion

### **⛪ Service Management**
- Service scheduling
- Session tracking
- Attendance ready

### **📊 Reports & Analytics**
- Member statistics
- Attendance reports
- Visitor analytics

## 🗄️ **Database Structure**

**9 Core Tables:**
- `members` (213 records) - Church members
- `visitors` (3 records) - Church visitors
- `new_converts` (3 records) - Convert tracking
- `services` (8 records) - Service templates
- `departments` (3 records) - Member departments
- `users` (2 records) - System administrators
- `service_sessions` (4 records) - Session management
- `system_settings` (8 records) - Configuration
- `attendance` (0 records) - Ready for tracking

## 🚀 **Deployment Status**

- ✅ **Local Development** - Fully functional
- ✅ **Hosting Deployed** - Live and operational
- ✅ **Database Synchronized** - Update system in place
- ✅ **Clean Structure** - Organized and professional

## 🔒 **Production Go-Live Checklist**

Before internet-facing deployment, confirm all items below:

1. Secrets via environment variables only
	- Set `BULKSMSGH_API_KEY`, `TWILIO_AUTH_TOKEN`, DB credentials, and session secrets outside source code.
	- Do not keep real API keys in tracked files.
2. Disable maintenance/test tools
	- Keep `APP_ENABLE_MAINTENANCE_TOOLS` unset in production.
	- Ensure root utility scripts are not publicly accessible.
3. Error display and logs
	- `display_errors` must be off in production.
	- Log errors to files with restricted permissions.
4. HTTPS and secure cookies
	- Force HTTPS.
	- Use secure, httponly, samesite session cookie settings.
5. DB and least privilege
	- Use a dedicated DB user with minimum required permissions.
	- Disable remote root DB access.
6. Backups and rollback
	- Daily DB backup configured.
	- Restore drill tested before launch.
7. Smoke tests
	- Verify login, attendance, visitors, finance, communication SMS queue/send, reports export.

### One-Command Preflight (After Deploy)

Run this from the project root:

```bash
php tools/production_preflight.php
```

### Optional Nginx Deny Snippet

If you deploy on Nginx (instead of Apache), add:

```nginx
location ~ ^/(password_reset|check_sms_config|check_tither_phone|check_tither_phones|run_db_updates|test_bulksms|test_phone_norm)\.php$ {
	deny all;
	return 403;
}
```

## 🔧 **Development Workflow**

1. **Local Changes** - Develop and test locally
2. **Generate Updates** - Use `update_generator.php`
3. **Deploy Changes** - Copy SQL to hosting
4. **Verify** - Test functionality

## 📞 **Support**

**Bridge Ministries International**  
*Church Management System v1.0*

---
*Last Updated: November 27, 2025*