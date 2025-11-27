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