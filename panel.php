<?php
session_start();

// Check if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

require_once 'functions.php';

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    $custom_alias = trim($_POST['custom_alias'] ?? '');
    $use_custom = isset($_POST['use_custom']) && $_POST['use_custom'] === '1';
    
    if (empty($url)) {
        $error = 'URL tidak boleh kosong!';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'URL tidak valid!';
    } else {
        if ($use_custom && !empty($custom_alias)) {
            // Validate custom alias
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $custom_alias)) {
                $error = 'Alias hanya boleh mengandung huruf, angka, underscore, dan dash!';
            } elseif (aliasExists($custom_alias)) {
                $error = 'Alias sudah digunakan!';
            } else {
                $shortcode = $custom_alias;
            }
        } else {
            // Generate random alias
            $shortcode = generateRandomAlias();
        }
        
        if (empty($error)) {
            $result = createShortlink($url, $shortcode);
            if ($result) {
                $message = 'Shortlink berhasil dibuat!';
                $generated_url = getCurrentDomain() . '/' . $shortcode;
            } else {
                $error = 'Gagal membuat shortlink!';
            }
        }
    }
}

// Get all shortlinks
$shortlinks = getAllShortlinks();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlink Generator - Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 1.5rem;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="url"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="url"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 0.5rem;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .result-box {
            background: #e8f5e8;
            border: 2px solid #4caf50;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .result-url {
            font-family: monospace;
            font-size: 1.1rem;
            color: #2e7d32;
            word-break: break-all;
            margin-bottom: 0.5rem;
        }
        
        .copy-btn {
            background: #4caf50;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .shortlink {
            color: #667eea;
            text-decoration: none;
        }
        
        .shortlink:hover {
            text-decoration: underline;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>🔗 Shortlink Generator</h1>
            </div>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($shortlinks) ?></div>
                <div class="stat-label">Total Shortlinks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= array_sum(array_column($shortlinks, 'clicks')) ?></div>
                <div class="stat-label">Total Clicks</div>
            </div>
        </div>
        
        <div class="card">
            <h2>Buat Shortlink Baru</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php if (isset($generated_url)): ?>
                    <div class="result-box">
                        <div class="result-url" id="generated-url"><?= htmlspecialchars($generated_url) ?></div>
                        <button class="copy-btn" onclick="copyToClipboard('generated-url')">Copy URL</button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="url">URL Target</label>
                    <input type="url" id="url" name="url" required placeholder="https://example.com" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="use_custom" name="use_custom" value="1" onchange="toggleCustomAlias()" <?= isset($_POST['use_custom']) ? 'checked' : '' ?>>
                    <label for="use_custom">Gunakan alias custom</label>
                </div>
                
                <div class="form-group" id="custom-alias-group" style="display: none;">
                    <label for="custom_alias">Alias Custom</label>
                    <input type="text" id="custom_alias" name="custom_alias" placeholder="alias-custom" value="<?= htmlspecialchars($_POST['custom_alias'] ?? '') ?>">
                    <small style="color: #666;">Hanya huruf, angka, underscore (_), dan dash (-)</small>
                </div>
                
                <button type="submit" class="btn">Buat Shortlink</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Daftar Shortlinks</h2>
            
            <?php if (empty($shortlinks)): ?>
                <p style="color: #666; text-align: center; padding: 2rem;">Belum ada shortlink yang dibuat.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shortlink</th>
                            <th>URL Target</th>
                            <th>Clicks</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shortlinks as $alias => $data): ?>
                            <tr>
                                <td>
                                    <a href="<?= getCurrentDomain() . '/' . htmlspecialchars($alias) ?>" target="_blank" class="shortlink">
                                        <?= htmlspecialchars($alias) ?>
                                    </a>
                                </td>
                                <td style="word-break: break-all;"><?= htmlspecialchars($data['url']) ?></td>
                                <td><?= $data['clicks'] ?></td>
                                <td><?= date('d/m/Y H:i', $data['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleCustomAlias() {
            const checkbox = document.getElementById('use_custom');
            const group = document.getElementById('custom-alias-group');
            
            if (checkbox.checked) {
                group.style.display = 'block';
                document.getElementById('custom_alias').required = true;
            } else {
                group.style.display = 'none';
                document.getElementById('custom_alias').required = false;
            }
        }
        
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(function() {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.style.background = '#2e7d32';
                
                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.style.background = '#4caf50';
                }, 2000);
            });
        }
        
        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            toggleCustomAlias();
        });
    </script>
</body>
</html>
