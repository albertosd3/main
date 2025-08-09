<?php

define('DATA_FILE', __DIR__ . '/data/shortlinks.json');
define('STATS_FILE', __DIR__ . '/data/stats.json');
define('CACHE_FILE', __DIR__ . '/data/cache.json');
define('CACHE_DURATION', 300); // 5 minutes

/**
 * File locking wrapper for safe concurrent access
 */
function safeFileOperation(string $file, callable $operation, $mode = 'r+') {
    $handle = fopen($file, $mode);
    if (!$handle) {
        return false;
    }
    
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }
    
    $result = $operation($handle);
    
    flock($handle, LOCK_UN);
    fclose($handle);
    
    return $result;
}

/**
 * Cache management for better performance
 */
class SimpleCache {
    private static $cache = [];
    
    public static function get(string $key) {
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $item = self::$cache[$key];
        if ($item['expires'] < time()) {
            unset(self::$cache[$key]);
            return null;
        }
        
        return $item['data'];
    }
    
    public static function set(string $key, $data, int $ttl = 300) {
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
    }
    
    public static function delete(string $key) {
        unset(self::$cache[$key]);
    }
    
    public static function clear() {
        self::$cache = [];
    }
}

/**
 * Rate limiting per IP
 */
function checkRateLimit(string $ip, int $maxRequests = 100, int $timeWindow = 3600): bool {
    $rateLimitFile = __DIR__ . '/data/rate_limit.json';
    
    if (!file_exists($rateLimitFile)) {
        file_put_contents($rateLimitFile, json_encode([]));
    }
    
    return safeFileOperation($rateLimitFile, function($handle) use ($ip, $maxRequests, $timeWindow) {
        $content = stream_get_contents($handle);
        $limits = json_decode($content ?: '[]', true);
        
        $now = time();
        $windowStart = $now - $timeWindow;
        
        // Clean old entries
        foreach ($limits as $checkIp => $data) {
            if ($data['window_start'] < $windowStart) {
                unset($limits[$checkIp]);
            }
        }
        
        // Check current IP
        if (!isset($limits[$ip])) {
            $limits[$ip] = [
                'requests' => 1,
                'window_start' => $now
            ];
        } else {
            if ($limits[$ip]['window_start'] < $windowStart) {
                // Reset window
                $limits[$ip] = [
                    'requests' => 1,
                    'window_start' => $now
                ];
            } else {
                $limits[$ip]['requests']++;
            }
        }
        
        $allowed = $limits[$ip]['requests'] <= $maxRequests;
        
        // Save back
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($limits));
        
        return $allowed;
    }, 'c+');
}

/**
 * Ensure data directory exists
 */
function ensureDataDirectory(): void {
    $dataDir = dirname(DATA_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
}

/**
 * Get current domain
 */
function getCurrentDomain(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Generate random alias
 */
function generateRandomAlias(int $length = 6): string {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $alias = '';
    
    for ($i = 0; $i < $length; $i++) {
        $alias .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    // Check if alias already exists, if so generate new one
    if (aliasExists($alias)) {
        return generateRandomAlias($length);
    }
    
    return $alias;
}

/**
 * Check if alias exists (optimized)
 */
function aliasExists(string $alias): bool {
    return getShortlinkFast($alias) !== null;
}

/**
 * Get all shortlinks
 */
function getAllShortlinks(): array {
    ensureDataDirectory();
    
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    
    $content = file_get_contents(DATA_FILE);
    if ($content === false) {
        return [];
    }
    
    $data = json_decode($content, true);
    return $data ?? [];
}

/**
 * Save shortlinks data with file locking
 */
function saveShortlinks(array $shortlinks): bool {
    ensureDataDirectory();
    
    return safeFileOperation(DATA_FILE, function($handle) use ($shortlinks) {
        $json = json_encode($shortlinks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $json);
        
        return true;
    }, 'c+');
}

/**
 * Create new shortlink
 */
function createShortlink(string $url, string $alias): bool {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    // Validate alias
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $alias)) {
        return false;
    }
    
    $shortlinks = getAllShortlinks();
    
    // Check if alias already exists
    if (isset($shortlinks[$alias])) {
        return false;
    }
    
    // Add new shortlink
    $shortlinks[$alias] = [
        'url' => $url,
        'clicks' => 0,
        'created_at' => time(),
        'last_accessed' => null
    ];
    
    // Clear cache for this alias
    SimpleCache::delete("shortlink_{$alias}");
    
    return saveShortlinks($shortlinks);
}

/**
 * Get shortlink data by alias (optimized)
 */
function getShortlink(string $alias): ?array {
    return getShortlinkFast($alias);
}

/**
 * Increment click count (optimized)
 */
function incrementClick(string $alias): bool {
    return incrementClickFast($alias);
}

/**
 * Get shortlink statistics
 */
function getStatistics(): array {
    $shortlinks = getAllShortlinks();
    
    $totalShortlinks = count($shortlinks);
    $totalClicks = array_sum(array_column($shortlinks, 'clicks'));
    
    // Get top 5 most clicked
    $topClicked = $shortlinks;
    uasort($topClicked, function($a, $b) {
        return $b['clicks'] <=> $a['clicks'];
    });
    $topClicked = array_slice($topClicked, 0, 5, true);
    
    // Get recent shortlinks
    $recentShortlinks = $shortlinks;
    uasort($recentShortlinks, function($a, $b) {
        return $b['created_at'] <=> $a['created_at'];
    });
    $recentShortlinks = array_slice($recentShortlinks, 0, 5, true);
    
    return [
        'total_shortlinks' => $totalShortlinks,
        'total_clicks' => $totalClicks,
        'top_clicked' => $topClicked,
        'recent_shortlinks' => $recentShortlinks
    ];
}

/**
 * Delete shortlink
 */
function deleteShortlink(string $alias): bool {
    $shortlinks = getAllShortlinks();
    
    if (!isset($shortlinks[$alias])) {
        return false;
    }
    
    unset($shortlinks[$alias]);
    SimpleCache::delete("shortlink_{$alias}");
    
    return saveShortlinks($shortlinks);
}

/**
 * Update shortlink URL
 */
function updateShortlink(string $alias, string $newUrl): bool {
    if (!filter_var($newUrl, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $shortlinks = getAllShortlinks();
    
    if (!isset($shortlinks[$alias])) {
        return false;
    }
    
    $shortlinks[$alias]['url'] = $newUrl;
    SimpleCache::delete("shortlink_{$alias}");
    
    return saveShortlinks($shortlinks);
}

/**
 * Get shortlinks with pagination
 */
function getShortlinksWithPagination(int $page = 1, int $perPage = 10): array {
    $shortlinks = getAllShortlinks();
    
    // Sort by creation date (newest first)
    uasort($shortlinks, function($a, $b) {
        return $b['created_at'] <=> $a['created_at'];
    });
    
    $total = count($shortlinks);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    
    $paginatedShortlinks = array_slice($shortlinks, $offset, $perPage, true);
    
    return [
        'shortlinks' => $paginatedShortlinks,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $total,
        'per_page' => $perPage
    ];
}

/**
 * Search shortlinks
 */
function searchShortlinks(string $query): array {
    $shortlinks = getAllShortlinks();
    $results = [];
    
    $query = strtolower($query);
    
    foreach ($shortlinks as $alias => $data) {
        if (
            strpos(strtolower($alias), $query) !== false ||
            strpos(strtolower($data['url']), $query) !== false
        ) {
            $results[$alias] = $data;
        }
    }
    
    return $results;
}

/**
 * Validate and sanitize alias
 */
function validateAlias(string $alias): string {
    // Remove any characters that are not alphanumeric, underscore, or dash
    $alias = preg_replace('/[^a-zA-Z0-9_-]/', '', $alias);
    
    // Ensure it's not empty and not too long
    if (empty($alias) || strlen($alias) > 50) {
        throw new InvalidArgumentException('Invalid alias');
    }
    
    return $alias;
}

/**
 * Export shortlinks data
 */
function exportShortlinks(): string {
    $shortlinks = getAllShortlinks();
    
    $export = [];
    foreach ($shortlinks as $alias => $data) {
        $export[] = [
            'alias' => $alias,
            'url' => $data['url'],
            'clicks' => $data['clicks'],
            'created_at' => date('Y-m-d H:i:s', $data['created_at']),
            'last_accessed' => $data['last_accessed'] ? date('Y-m-d H:i:s', $data['last_accessed']) : null
        ];
    }
    
    return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Clean old shortlinks (optional maintenance function)
 */
function cleanOldShortlinks(int $daysOld = 365): int {
    $shortlinks = getAllShortlinks();
    $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
    $deletedCount = 0;
    
    foreach ($shortlinks as $alias => $data) {
        if ($data['created_at'] < $cutoffTime && $data['clicks'] == 0) {
            unset($shortlinks[$alias]);
            SimpleCache::delete("shortlink_{$alias}");
            $deletedCount++;
        }
    }
    
    if ($deletedCount > 0) {
        saveShortlinks($shortlinks);
    }
    
    return $deletedCount;
}

/**
 * Optimized function to get single shortlink (for redirects)
 */
function getShortlinkFast(string $alias): ?array {
    // Try cache first
    $cacheKey = "shortlink_{$alias}";
    $cached = SimpleCache::get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    // Read from file with minimal parsing
    if (!file_exists(DATA_FILE)) {
        return null;
    }
    
    $content = file_get_contents(DATA_FILE);
    if ($content === false) {
        return null;
    }
    
    // Parse JSON and find specific alias
    $data = json_decode($content, true);
    if (!$data || !isset($data[$alias])) {
        return null;
    }
    
    $shortlink = $data[$alias];
    
    // Cache the result
    SimpleCache::set($cacheKey, $shortlink, 600); // 10 minutes cache
    
    return $shortlink;
}

/**
 * Optimized click tracking with batching
 */
function incrementClickFast(string $alias): bool {
    // Use separate stats file for click tracking
    ensureDataDirectory();
    
    if (!file_exists(STATS_FILE)) {
        file_put_contents(STATS_FILE, json_encode([]));
    }
    
    return safeFileOperation(STATS_FILE, function($handle) use ($alias) {
        $content = stream_get_contents($handle);
        $stats = json_decode($content ?: '[]', true);
        
        if (!isset($stats[$alias])) {
            $stats[$alias] = 0;
        }
        $stats[$alias]++;
        
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($stats));
        
        return true;
    }, 'c+');
}

/**
 * Batch update main shortlinks file with click stats
 */
function syncClickStats(): bool {
    if (!file_exists(STATS_FILE) || !file_exists(DATA_FILE)) {
        return false;
    }
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    if (!$stats) {
        return false;
    }
    
    return safeFileOperation(DATA_FILE, function($handle) use ($stats) {
        $content = stream_get_contents($handle);
        $shortlinks = json_decode($content ?: '[]', true);
        
        foreach ($stats as $alias => $clicks) {
            if (isset($shortlinks[$alias])) {
                $shortlinks[$alias]['clicks'] = ($shortlinks[$alias]['clicks'] ?? 0) + $clicks;
                $shortlinks[$alias]['last_accessed'] = time();
            }
        }
        
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($shortlinks, JSON_PRETTY_PRINT));
        
        return true;
    }, 'c+');
}

/**
 * Clear processed stats
 */
function clearProcessedStats(): bool {
    if (file_exists(STATS_FILE)) {
        return file_put_contents(STATS_FILE, json_encode([])) !== false;
    }
    return true;
}
