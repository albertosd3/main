# URGENT FIX - Files Not Deployed

## 🚨 Problem: index.php tidak ada di server

### ✅ Solusi 1: Push All Files ke Repository

```bash
# Di local machine (Windows PowerShell)
cd "c:\Users\Administrator\Downloads\main"

# Check status
git status

# Add ALL files
git add .
git add index.php
git add panel.php
git add functions.php
git add redirect.php
git add .htaccess

# Commit
git commit -m "Add all PHP files and fix deployment"

# Push ke GitHub
git push origin main
```

### ✅ Solusi 2: Manual Upload via Laravel Forge

1. **Go to Laravel Forge → Sites → Files**
2. **Upload files manually:**
   - index.php
   - panel.php  
   - functions.php
   - redirect.php
   - .htaccess
   - composer.json

### ✅ Solusi 3: Create index.php via SSH

```bash
# SSH ke server
ssh forge@your-server

# Go to site directory
cd /home/forge/default

# Check what files exist
ls -la

# Create index.php if missing
cat > index.php << 'EOF'
<?php
session_start();

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: panel.php');
    exit();
}

// Handle login
if ($_POST['password'] ?? false) {
    if ($_POST['password'] === 'GP666') {
        $_SESSION['logged_in'] = true;
        header('Location: panel.php');
        exit();
    } else {
        $error = 'Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlink Generator - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 2rem; }
        .logo h1 { color: #333; font-size: 2rem; margin-bottom: 0.5rem; }
        .logo p { color: #666; font-size: 0.9rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500; }
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🔗 Shortlink</h1>
            <p>Generator Panel</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Masukkan password">
            </div>
            
            <button type="submit" class="btn">Masuk Panel</button>
        </form>
        
        <div style="text-align: center; margin-top: 2rem; color: #666; font-size: 0.8rem;">
            <p>&copy; 2025 Shortlink Generator</p>
        </div>
    </div>
</body>
</html>
EOF

# Set permissions
chmod 644 index.php

echo "✅ index.php created successfully!"
```

## 🎯 Quick Test

Setelah salah satu solusi di atas, test:
```
http://107.155.112.162
```

Harusnya muncul halaman login dengan password `GP666`!
