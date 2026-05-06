# BEL Exam Portal - XAMPP Multi-App Setup Guide

## ✅ Path Configuration - COMPLETED

Your application has been fixed to work with multiple applications in XAMPP.

### What Was Fixed

1. **Dynamic Folder Detection** ✅
   - The `base_url()` function in `includes/helpers.php` now automatically detects your application folder name
   - Works with any folder name (bel_exam_portal, bel-exam, myexam, etc.)
   - Eliminates hardcoded path assumptions

2. **All Include Paths** ✅
   - All PHP files use `__DIR__` for includes (relative to current file)
   - Example: `require_once __DIR__ . '/../includes/helpers.php';`
   - This works regardless of where the app folder is placed

3. **Asset Paths** ✅
   - CSS, JavaScript, images use `url()` function
   - Automatically generates correct paths based on application location
   - Example: `<link href="<?= url('assets/css/app.css') ?>">`

4. **API Calls** ✅
   - JavaScript fetch calls use PHP-generated URLs
   - Example: `const SAVE_URL = <?= json_encode(url('api/save-answer.php')) ?>;`

---

## 🚀 How to Access Your Application

### Method 1: Access from root
```
http://localhost/bel_exam_portal/
```

### Method 2: Add Virtual Host (Optional)
If you want clean URLs like `http://belexam.local/`:

1. Edit: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
2. Add:
```apache
<VirtualHost *:80>
    DocumentRoot "C:\xampp\htdocs\bel_exam_portal"
    ServerName belexam.local
    ServerAlias www.belexam.local
</VirtualHost>
```
3. Edit: `C:\Windows\System32\drivers\etc\hosts`
4. Add: `127.0.0.1    belexam.local`
5. Restart Apache

---

## 📋 Access Points

- **Student Login**: http://localhost/bel_exam_portal/
- **Admin Login**: http://localhost/bel_exam_portal/admin/login.php
- **Student Panel**: http://localhost/bel_exam_portal/student/login.php

---

## 🔧 Database Setup

1. Start MySQL in XAMPP Control Panel
2. Open phpMyAdmin: http://localhost/phpmyadmin
3. Create database named `bel_exam_portal`
4. Import schema: `schema.sql`

---

## 📁 Folder Structure

```
xampp/htdocs/
├── bel_exam_portal/          ← Your app (can be any name!)
│   ├── index.php
│   ├── admin/                ← Admin interface
│   ├── student/              ← Student interface
│   ├── api/                  ← API endpoints
│   ├── assets/               ← CSS, JS, images
│   ├── includes/             ← Shared PHP files
│   ├── uploads/              ← User photos
│   └── includes/config.php   ← Database credentials
│
└── [Other apps can go here too!]
    ├── another_app/
    ├── project2/
    └── legacy_site/
```

---

## ✨ Multi-App Setup Example

You can now run multiple applications:

```
xampp/htdocs/
├── bel_exam_portal/     → http://localhost/bel_exam_portal/
├── school_management/   → http://localhost/school_management/
├── inventory_system/    → http://localhost/inventory_system/
└── blog/                → http://localhost/blog/
```

Each app works independently without conflicting paths!

---

## 🔍 Troubleshooting

### 1. "Page Not Found" or blank page
- **Check**: Apache is running in XAMPP Control Panel
- **Fix**: Start Apache

### 2. "404 Not Found" on admin/student pages
- **Check**: URL path matches your folder name
- **Fix**: Use `http://localhost/bel_exam_portal/admin/login.php`

### 3. Database connection failed
- **Check**: MySQL is running
- **Fix**: Start MySQL in XAMPP, then import `schema.sql`

### 4. "Class not found" errors
- **Check**: All includes have `__DIR__` paths (they do!)
- **Fix**: Ensure no files were manually edited to use absolute paths

### 5. Blank login pages
- **Check**: Browser console (F12) for JavaScript errors
- **Fix**: Assets are loading correctly (CSS/JS visible in DevTools Network tab)

---

## ⚙️ Configuration Files

### Database: `includes/config.php`
```php
define('DB_HOST', '127.0.0.1');      // Change if needed
define('DB_NAME', 'bel_exam_portal'); // Same as your DB name
define('DB_USER', 'root');            // Default for XAMPP
define('DB_PASS', '');                // Default for XAMPP (empty)
```

### Uploads: `includes/config.php`
```php
define('PHOTO_DIR', __DIR__ . '/../uploads/photos');
define('MAX_PHOTO_SIZE', 1024 * 1024 * 2);  // 2 MB
```

---

## 🛡️ Security Notes

- Default admin: `admin@belkotdwar.in` / `Admin@123`
- Change these credentials immediately in production
- `.htaccess` prevents direct access to sensitive files
- Sessions use secure cookies

---

## ✅ Verification Checklist

- [x] All PHP includes use `__DIR__` paths
- [x] All URLs generated via `url()` function  
- [x] `base_url()` auto-detects folder name
- [x] Assets load correctly from any location
- [x] API endpoints work from JavaScript
- [x] Database connections configured

---

**Your application is now ready for multiple-app XAMPP setup!** 🎉

