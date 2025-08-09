#!/bin/bash

echo "🚀 Starting Shortlink Generator Deployment..."

# Set proper permissions
echo "📁 Setting up permissions..."
mkdir -p data
chmod 755 data
chmod +x sync_stats.php

# Create necessary data files if they don't exist
if [ ! -f "data/shortlinks.json" ]; then
    echo "{}" > data/shortlinks.json
    chmod 644 data/shortlinks.json
fi

if [ ! -f "data/stats.json" ]; then
    echo "{}" > data/stats.json
    chmod 644 data/stats.json
fi

if [ ! -f "data/rate_limit.json" ]; then
    echo "{}" > data/rate_limit.json
    chmod 644 data/rate_limit.json
fi

# Setup cron job for stats synchronization
echo "⏰ Setting up cron job..."
CRON_JOB="*/5 * * * * php $(pwd)/sync_stats.php >/dev/null 2>&1"

# Add to crontab if not already present
(crontab -l 2>/dev/null | grep -q "sync_stats.php") || (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

echo "✅ Deployment completed successfully!"
echo ""
echo "📊 Monitor your application at: https://yoursite.com/monitor.php?key=GP666"
echo "🔑 Admin panel password: GP666"
echo "📚 Documentation: README.md"
echo ""
echo "🎉 Shortlink Generator is ready for high traffic!"
