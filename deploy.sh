#!/bin/bash

# District 8 Travel League - Manual Deployment Script
# This script can be used as a backup if .cpanel.yml deployment fails

echo "=== D8TL Deployment Script ==="
echo "Branch: $CPANEL_DEPLOYMENT_BRANCH"
echo "Current directory: $(pwd)"
echo "User: $USER"
echo "Date: $(date)"
echo

# Determine target directory based on branch
if [ "$CPANEL_DEPLOYMENT_BRANCH" = "staging" ]; then
    TARGETDIR="/home/moc835/public_html/staging.district8travelleague.com"
    CONFIGFILE="includes/config.staging.php"
    echo "Deploying to STAGING environment: $TARGETDIR"
elif [ "$CPANEL_DEPLOYMENT_BRANCH" = "main" ]; then
    TARGETDIR="/home/moc835/public_html/district8travelleague.com"
    CONFIGFILE="includes/config.prod.php"
    echo "Deploying to PRODUCTION environment: $TARGETDIR"
else
    TARGETDIR="/home/moc835/public_html/saved-files-do-not-delete/test"
    CONFIGFILE="includes/config.staging.php"
    echo "Deploying to TEST environment: $TARGETDIR"
fi

echo "Target directory: $TARGETDIR"
echo "Config file: $CONFIGFILE"
echo

# Create target directories
echo "Creating directories..."
mkdir -p $TARGETDIR/includes
mkdir -p $TARGETDIR/logs
mkdir -p $TARGETDIR/uploads
mkdir -p $TARGETDIR/backups  
mkdir -p $TARGETDIR/templates
mkdir -p $TARGETDIR/scripts

# Copy files with error checking
echo "Copying files..."

if [ -d "includes" ]; then
    cp -R includes/* $TARGETDIR/includes/
    echo "✓ Copied includes directory"
else
    echo "✗ includes directory not found"
fi

if [ -d "public" ]; then
    cp -R public/* $TARGETDIR/
    echo "✓ Copied public files"
else
    echo "✗ public directory not found"
fi

if [ -d "templates" ]; then
    cp -R templates/* $TARGETDIR/templates/
    echo "✓ Copied templates directory"
else
    echo "✗ templates directory not found"
fi

if [ -d "scripts" ]; then
    cp -R scripts/* $TARGETDIR/scripts/
    echo "✓ Copied scripts directory"
else
    echo "✗ scripts directory not found"
fi

# Copy environment-specific configuration
if [ -f "$CONFIGFILE" ]; then
    cp $CONFIGFILE $TARGETDIR/includes/config.php
    echo "✓ Copied configuration file: $CONFIGFILE"
else
    echo "✗ Configuration file not found: $CONFIGFILE"
fi

# Set permissions
echo "Setting permissions..."
chmod 755 $TARGETDIR/logs $TARGETDIR/uploads $TARGETDIR/backups $TARGETDIR/scripts
chmod 644 $TARGETDIR/includes/*.php

# Create .htaccess protection files
echo "Order deny,allow" > $TARGETDIR/includes/.htaccess
echo "Deny from all" >> $TARGETDIR/includes/.htaccess

echo "Order deny,allow" > $TARGETDIR/templates/.htaccess
echo "Deny from all" >> $TARGETDIR/templates/.htaccess

# Create deployment log
echo "$(date): Deployed $CPANEL_DEPLOYMENT_BRANCH branch successfully via deploy.sh" >> $TARGETDIR/logs/deployment.log
echo "$(date): Files deployed to: $TARGETDIR" >> $TARGETDIR/logs/deployment.log
echo "$(date): Configuration: $CONFIGFILE" >> $TARGETDIR/logs/deployment.log

echo
echo "=== Deployment Summary ==="
echo "Branch: $CPANEL_DEPLOYMENT_BRANCH"
echo "Target: $TARGETDIR"
echo "Config: $CONFIGFILE"
echo "Status: COMPLETED"
echo "Log: $TARGETDIR/logs/deployment.log"
echo

# List deployed files for verification
echo "Deployed files:"
ls -la $TARGETDIR/ | head -10
echo
echo "Deployment completed successfully!"
