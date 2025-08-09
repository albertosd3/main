<?php
/**
 * Cron job untuk sinkronisasi click statistics
 * Jalankan setiap 5-10 menit untuk performa optimal
 * 
 * Tambahkan ke crontab:
 * */5 * * * * php /path/to/your/project/sync_stats.php
 */

require_once __DIR__ . '/functions.php';

echo "Starting stats synchronization...\n";

// Sync click stats dari file terpisah ke main file
if (syncClickStats()) {
    echo "Stats synchronized successfully\n";
    
    // Clear processed stats
    if (clearProcessedStats()) {
        echo "Processed stats cleared\n";
    } else {
        echo "Warning: Could not clear processed stats\n";
    }
} else {
    echo "No stats to sync or sync failed\n";
}

// Optional: Clean old shortlinks (uncomment if needed)
// $deletedCount = cleanOldShortlinks(365); // Delete unused links older than 1 year
// if ($deletedCount > 0) {
//     echo "Cleaned {$deletedCount} old shortlinks\n";
// }

echo "Stats synchronization completed\n";
?>
