<?php
/**
 * Simple monitoring script untuk mengecek status sistem
 * Akses: yoursite.com/monitor.php
 */

require_once 'functions.php';

// Simple authentication
if (!isset($_GET['key']) || $_GET['key'] !== 'GP666') {
    http_response_code(401);
    die('Unauthorized');
}

// Collect system information
$stats = [];

// File sizes
$mainFileSize = file_exists(DATA_FILE) ? filesize(DATA_FILE) : 0;
$statsFileSize = file_exists(STATS_FILE) ? filesize(STATS_FILE) : 0;
$rateLimitFileSize = file_exists(__DIR__ . '/data/rate_limit.json') ? filesize(__DIR__ . '/data/rate_limit.json') : 0;

// Memory usage
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);

// Get shortlinks stats
$shortlinks = getAllShortlinks();
$totalShortlinks = count($shortlinks);
$totalClicks = array_sum(array_column($shortlinks, 'clicks'));

// Calculate average clicks
$avgClicks = $totalShortlinks > 0 ? round($totalClicks / $totalShortlinks, 2) : 0;

// Check pending stats
$pendingStats = [];
if (file_exists(STATS_FILE)) {
    $pendingStats = json_decode(file_get_contents(STATS_FILE), true) ?? [];
}
$pendingClicks = array_sum($pendingStats);

// Performance check
$startTime = microtime(true);
getShortlinkFast('test-performance-check');
$lookupTime = round((microtime(true) - $startTime) * 1000, 2);

// Rate limit check
$rateLimitData = [];
if (file_exists(__DIR__ . '/data/rate_limit.json')) {
    $rateLimitData = json_decode(file_get_contents(__DIR__ . '/data/rate_limit.json'), true) ?? [];
}
$activeIPs = count($rateLimitData);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'system' => [
        'php_version' => PHP_VERSION,
        'memory_usage' => formatBytes($memoryUsage),
        'memory_peak' => formatBytes($memoryPeak),
        'memory_limit' => ini_get('memory_limit')
    ],
    'files' => [
        'main_file_size' => formatBytes($mainFileSize),
        'stats_file_size' => formatBytes($statsFileSize),
        'rate_limit_file_size' => formatBytes($rateLimitFileSize),
        'main_file_writable' => is_writable(dirname(DATA_FILE)),
        'main_file_exists' => file_exists(DATA_FILE)
    ],
    'shortlinks' => [
        'total_shortlinks' => $totalShortlinks,
        'total_clicks' => $totalClicks,
        'average_clicks' => $avgClicks,
        'pending_clicks' => $pendingClicks
    ],
    'performance' => [
        'lookup_time_ms' => $lookupTime,
        'active_ips' => $activeIPs
    ],
    'alerts' => checkAlerts($mainFileSize, $statsFileSize, $pendingClicks, $totalShortlinks)
], JSON_PRETTY_PRINT);

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function checkAlerts($mainFileSize, $statsFileSize, $pendingClicks, $totalShortlinks) {
    $alerts = [];
    
    // File size alerts
    if ($mainFileSize > 10 * 1024 * 1024) { // 10MB
        $alerts[] = 'Main file size is getting large (' . formatBytes($mainFileSize) . ')';
    }
    
    if ($statsFileSize > 1 * 1024 * 1024) { // 1MB
        $alerts[] = 'Stats file size is large (' . formatBytes($statsFileSize) . ') - increase sync frequency';
    }
    
    // Pending clicks alert
    if ($pendingClicks > 1000) {
        $alerts[] = 'High pending clicks (' . $pendingClicks . ') - check sync_stats.php cron job';
    }
    
    // Memory alert
    $memoryUsage = memory_get_usage(true);
    if ($memoryUsage > 50 * 1024 * 1024) { // 50MB
        $alerts[] = 'High memory usage (' . formatBytes($memoryUsage) . ')';
    }
    
    return $alerts;
}
?>
