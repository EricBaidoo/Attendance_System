# 🚀 Hosting Deployment Guide for Bridge Ministries International Attendance System

## 📋 **Pre-Deployment Checklist**

### **1. Database Setup on Hosting**
1. **Create Database:**
   - Log into your hosting control panel (cPanel/Plesk)
   - Create a new MySQL database
   - Note: Database name, username, password, host

2. **Import Database Structure:**
   - Use phpMyAdmin or MySQL import tool
   - Import: `database/complete_hosting_setup.sql`
   - This creates all tables and default data

### **2. File Upload**
1. **Upload Project Files:**
   ```
   Upload entire project folder to public_html/ or www/
   ```

2. **Update Database Configuration:**
   - Edit `config/database.php`
   - Use the template in `config/database_hosting.php`
   - Update with your hosting database details

### **3. Folder Structure on Hosting**
```
public_html/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   └── database.php (update this!)
├── includes/
├── pages/
├── database/
├── login.php
├── index.php
└── other files...
```

## 🗄️ **Database Import Instructions**

### **Option 1: phpMyAdmin (Recommended)**
1. Open phpMyAdmin in hosting control panel
2. Select your database
3. Click "Import" tab
4. Choose file: `complete_hosting_setup.sql`
5. Click "Go" to import

### **Option 2: MySQL Command Line**
```sql
mysql -u username -p database_name < complete_hosting_setup.sql
```

### **Option 3: Hosting File Manager**
1. Upload SQL file via file manager
2. Use hosting's MySQL import tool
3. Import the uploaded SQL file

## ⚙️ **Configuration Updates**

### **Update `config/database.php`:**
```php
$host = 'localhost';                    // Your hosting DB host
$dbname = 'yourusername_attendance';    // Your database name
$username = 'yourusername_dbuser';      // Your DB username
$password = 'your_secure_password';     // Your DB password
```

### **Common Hosting Database Formats:**
- **Shared Hosting:** `username_dbname`
- **Cloud Hosting:** Custom names allowed
- **VPS/Dedicated:** Full control over naming

## 🔐 **Security Setup**

### **1. Change Default Admin Password**
- Login: `admin` / `admin123`
- Change password immediately after first login

### **2. Update File Permissions**
```
Folders: 755
PHP Files: 644
Config Files: 600 (if possible)
```

### **3. Hide Database Config**
- Place config files outside public_html if possible
- Or use .htaccess to protect config folder

## 🧪 **Testing Your Deployment**

### **1. Basic Tests:**
1. Visit: `yourdomain.com/login.php`
2. Login with: `admin` / `admin123`
3. Check dashboard loads correctly
4. Test adding a member
5. Test visitor management

### **2. Database Connection Test:**
```php
// Create test.php file temporarily
<?php
include 'config/database.php';
echo "Database connected successfully!";
?>
```

## 🏗️ **Hosting Provider Specific Notes**

### **cPanel Hosting:**
- Database format: `username_dbname`
- Use phpMyAdmin for import
- File Manager or FTP for uploads

### **Cloudflare/AWS/Digital Ocean:**
- Full database control
- SSH access available
- Custom domain configuration

### **Shared Hosting (GoDaddy/Bluehost):**
- Limited database naming
- Use provided import tools
- May have file size limits

## ⚡ **Performance Optimization**

### **1. Enable PHP Extensions:**
- PDO MySQL
- JSON
- OpenSSL
- Fileinfo

### **2. PHP Configuration:**
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

## 🆘 **Troubleshooting Common Issues**

### **Database Connection Failed:**
1. Check database credentials
2. Verify database exists
3. Confirm user has permissions
4. Test with simple connection script

### **404 Errors:**
1. Check file paths in includes
2. Verify .htaccess rules
3. Confirm all files uploaded

### **Permission Errors:**
1. Set folder permissions to 755
2. Set file permissions to 644
3. Check config file access

## 🔧 **Post-Deployment Tasks**

### **1. Initial Setup:**
- [ ] Change admin password
- [ ] Add church departments
- [ ] Import member list
- [ ] Configure service schedules

### **2. Backup Setup:**
- [ ] Setup automatic database backups
- [ ] Configure file backups
- [ ] Test restore procedures

### **3. Monitoring:**
- [ ] Setup error logging
- [ ] Monitor performance
- [ ] Track user activity

## 📞 **Support & Maintenance**

After deployment, ensure:
1. Regular database backups
2. PHP/MySQL updates
3. Security monitoring
4. Performance optimization

---

## 🎉 **Ready to Deploy!**

Your Bridge Ministries International Attendance System is now ready for hosting deployment. Follow this guide step by step, and you'll have a fully functional church management system online!

**Default Login:** `admin` / `admin123` (change immediately!)