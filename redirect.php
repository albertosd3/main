<?php
require_once 'functions.php';

// Rate limiting untuk mencegah abuse
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, 200, 3600)) { // 200 requests per hour per IP
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit();
}

// Get the requested path
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = trim($requestPath, '/');

// Remove query parameters
$requestPath = explode('?', $requestPath)[0];

// If it's the root or common files, let them handle themselves
if (empty($requestPath) || 
    $requestPath === 'index.php' || 
    $requestPath === 'panel.php' || 
    $requestPath === 'functions.php' ||
    strpos($requestPath, 'data/') === 0) {
    return false; // Let the server handle these files normally
}

// Try to find the shortlink using optimized function
$shortlink = getShortlinkFast($requestPath);

if ($shortlink) {
    // Increment click count using optimized function
    incrementClickFast($requestPath);
    
    // Redirect to the target URL
    header('Location: ' . $shortlink['url'], true, 301);
    exit();
} else {
    // Shortlink not found - show 404 page
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Shortlink Tidak Ditemukan</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }
            
            .error-container {
                text-align: center;
                max-width: 500px;
                padding: 2rem;
            }
            
            .error-code {
                font-size: 8rem;
                font-weight: bold;
                margin-bottom: 1rem;
                opacity: 0.8;
            }
            
            .error-title {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            
            .error-message {
                font-size: 1.1rem;
                margin-bottom: 2rem;
                opacity: 0.9;
                line-height: 1.6;
            }
            
            .btn {
                display: inline-block;
                background: rgba(255,255,255,0.2);
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 5px;
                transition: background 0.3s;
            }
            
            .btn:hover {
                background: rgba(255,255,255,0.3);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-code">404</div>
            <h1 class="error-title">Shortlink Tidak Ditemukan</h1>
            <p class="error-message">
                Maaf, shortlink yang Anda cari tidak ditemukan atau mungkin sudah dihapus.
            </p>
            <a href="/" class="btn">Kembali ke Beranda</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>
