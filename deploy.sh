#!/bin/bash

echo "🚀 Starting Shortlink Generator Deployment for Laravel Forge..."

# Ensure we're in the correct directory
cd /home/forge/$(basename "$PWD")

# Set proper permissions
echo "📁 Setting up permissions..."
mkdir -p data
chmod 755 data
chmod 755 .
chmod 644 *.php
chmod 644 .htaccess
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

# Fix ownership (important for Laravel Forge)
sudo chown -R forge:forge .
sudo chown -R www-data:www-data data/

echo "✅ Deployment completed successfully!"
echo ""
echo "🌐 Access your site at: https://$(basename "$PWD")"
echo "🔑 Admin panel password: GP666"
echo "� Monitor at: https://$(basename "$PWD")/monitor.php?key=GP666"
echo ""
echo "🎉 Shortlink Generator is ready!"
