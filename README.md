# PHP_Covert_Bytes_Into_KB_MB_And_GB

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php">
  <img src="https://img.shields.io/badge/XAMPP-Local%20Server-orange?style=for-the-badge&logo=apache">
  <img src="https://img.shields.io/badge/Utility-Bytes%20Converter-success?style=for-the-badge">
</p>

---

##  Overview

This documentation explains how to **convert bytes into KB, MB, and GB using pure PHP**  
and run the project using **XAMPP (htdocs)** in the browser.

 No framework required  
 Works with core PHP  
 Beginner friendly  
 Can be reused as helper function  

---

##  Features

- Convert bytes to **B, KB, MB, GB**
- Uses **1024-based conversion**
- Clean and reusable PHP function
- Runs directly in browser
- No Laravel / framework dependency

---

##  Folder Structure

```
php-bytes-converter/
└── index.php
```

---

##  Step 1 — Install XAMPP

1. Download XAMPP from:
   ```
   https://www.apachefriends.org
   ```
2. Install XAMPP
3. Open **XAMPP Control Panel**
4. Start **Apache**

⚠ Apache must be running before continuing.

---

##  Step 2 — Go to htdocs Directory

Default XAMPP path:

```
C:\xampp\htdocs
```

All PHP projects must be created inside **htdocs**.

---

##  Step 3 — Create Project Folder

Inside `htdocs`, create a folder:

```
php-bytes-converter
```

Final path:

```
C:\xampp\htdocs\php-bytes-converter
```

---

##  Step 4 — Create index.php File

Inside the project folder, create a file:

```
index.php
```

Final structure:

```
php-bytes-converter/
└── index.php
```

---

##  Step 5 — Add PHP Code

Open `index.php` and add the following code:

```php
<?php

/**
 * Convert bytes to KB, MB, or GB
 */
function formatBytes($bytes, $precision = 2)
{
    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;

    if ($bytes < $kilobyte) {
        return $bytes . ' B';
    } elseif ($bytes < $megabyte) {
        return round($bytes / $kilobyte, $precision) . ' KB';
    } elseif ($bytes < $gigabyte) {
        return round($bytes / $megabyte, $precision) . ' MB';
    } else {
        return round($bytes / $gigabyte, $precision) . ' GB';
    }
}

/* Example usage */
$bytes = 567890123;

echo formatBytes($bytes);
```

Save the file.

---

##  Step 6 — Run Project in Browser

Open your browser and visit:

```
http://localhost/php-bytes-converter
```

---

##  Output

```
541.58 MB
```
<img width="388" height="91" alt="Screenshot 2025-12-16 124603" src="https://github.com/user-attachments/assets/370cd798-a68c-4a6a-9c90-7f5ad5073140" />

---

##  Conversion Logic

| Unit | Formula |
|-----|--------|
| KB | bytes ÷ 1024 |
| MB | bytes ÷ (1024 × 1024) |
| GB | bytes ÷ (1024 × 1024 × 1024) |

---

##  Example Values

| Bytes | Result |
|------|--------|
| 500 | 500 B |
| 2048 | 2 KB |
| 1048576 | 1 MB |
| 1073741824 | 1 GB |

---
.

