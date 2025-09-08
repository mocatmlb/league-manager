#!/bin/bash
# Quick fix for production path issues
# Run this script in the production root directory

echo "Fixing production paths..."

# Fix main files
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" index.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" schedule.php
sed -i "s|dirname(__DIR__) . '/includes/bootstrap\.php'|__DIR__ . '/includes/bootstrap.php'|g" standings.php

# Fix coaches files
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/login.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/logout.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/score-input.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/schedule-change.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/dashboard.php
sed -i "s|dirname(dirname(__DIR__)) . '/includes/bootstrap\.php'|dirname(__DIR__) . '/includes/bootstrap.php'|g" coaches/contacts.php

echo "Done! The production paths have been fixed."
echo "Test the application by visiting the main pages."
