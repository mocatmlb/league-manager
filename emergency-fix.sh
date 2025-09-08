#!/bin/bash
# Emergency fix for production path issues
# Run this in the production root directory: /home2/moc835/public_html/district8travelleague.com/

echo "=== Emergency Production Fix ==="
echo "Fixing path issues in production files..."

# Check if we're in the right directory
if [ ! -f "index.php" ]; then
    echo "ERROR: index.php not found. Please run this script from the production root directory."
    echo "Expected location: /home2/moc835/public_html/district8travelleague.com/"
    exit 1
fi

# Check if includes directory exists
if [ ! -d "includes" ]; then
    echo "ERROR: includes directory not found. The deployment may be incomplete."
    echo "Please ensure the includes directory was copied during deployment."
    exit 1
fi

echo "✅ Found index.php and includes directory"

# Fix main files
echo "Fixing main files..."
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" index.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" schedule.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" standings.php

# Fix coaches files
echo "Fixing coaches files..."
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/login.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/logout.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/score-input.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/schedule-change.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/dashboard.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/contacts.php

echo "✅ All files fixed!"

# Test the fix
echo "Testing the fix..."
if curl -s -o /dev/null -w "%{http_code}" http://district8travelleague.com/ | grep -q "200"; then
    echo "✅ SUCCESS: Site is now accessible!"
    echo "Visit: http://district8travelleague.com/"
else
    echo "❌ Site still not accessible. Check error logs for additional issues."
fi

echo "=== Fix Complete ==="
