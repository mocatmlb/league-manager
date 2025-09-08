# Git-Based Deployment Setup Guide

This guide walks you through setting up the Git-based deployment system for the District 8 Travel League MVP using A Small Orange shared hosting with cPanel Git Version Control.

## Prerequisites

- A Small Orange shared hosting account with cPanel access
- GitHub repository for the D8TL project
- Domain: `district8travelleague.com`
- Subdomain for staging: `staging.district8travelleague.com`

## Step 1: Create Subdomain for Staging

1. Log into cPanel
2. Navigate to **Subdomains**
3. Create subdomain: `staging`
4. Document root: `/public_html/staging`

## Step 2: Set Up Git Repositories in cPanel

### Production Repository Setup

1. Navigate to **cPanel → Files → Git Version Control**
2. Click **"Create"**
3. Configure:
   - **Clone a Repository**: Enabled
   - **Clone URL**: `https://github.com/your-org/d8tl.git`
   - **Repository Path**: `/public_html`
   - **Repository Name**: `D8TL Production`
4. Click **"Create"**
5. After creation, click **"Manage"** → **"Basic Information"**
6. Set **Checked-Out Branch** to `main`

### Staging Repository Setup

1. In **Git Version Control**, click **"Create"** again
2. Configure:
   - **Clone a Repository**: Enabled
   - **Clone URL**: `https://github.com/your-org/d8tl.git`
   - **Repository Path**: `/public_html/staging`
   - **Repository Name**: `D8TL Staging`
3. Click **"Create"**
4. After creation, click **"Manage"** → **"Basic Information"**
5. Set **Checked-Out Branch** to `staging`

## Step 3: Database Setup

### Create Databases

1. Navigate to **cPanel → MySQL Databases**
2. Create databases:
   - `d8tl_prod` (for production)
   - `d8tl_staging` (for staging)
3. Create database users and assign full privileges to respective databases

### Import Schema

1. Navigate to **cPanel → phpMyAdmin**
2. Select `d8tl_prod` database
3. Import `database/schema.sql`
4. Repeat for `d8tl_staging` database

## Step 4: Configure Environment Files

### Update Configuration Files

1. Edit `includes/config.prod.php`:
   - Update database credentials for production
   - Set secure passwords
   - Configure SMTP settings

2. Edit `includes/config.staging.php`:
   - Update database credentials for staging
   - Configure staging-specific settings

### Set File Permissions

Via cPanel File Manager or FTP:
```bash
# Configuration files
chmod 644 includes/config.*.php

# Directories
chmod 755 logs/
chmod 755 uploads/
chmod 755 backups/

# Make upload directories writable
chmod 777 uploads/
chmod 777 logs/
chmod 777 backups/

# Make scripts executable
chmod 755 scripts/*.php
chmod 755 database/migrate.php
```

## Step 5: Test Initial Deployment

### Deploy to Staging

1. In **Git Version Control**, find the staging repository
2. Click **"Manage"** → **"Pull or Deploy"**
3. Click **"Deploy HEAD Commit"**
4. Monitor deployment logs for any errors

### Verify Staging Deployment

1. Visit `https://staging.district8travelleague.com`
2. Check that the application loads
3. Verify database connectivity
4. Test basic functionality

### Deploy to Production

1. In **Git Version Control**, find the production repository
2. Click **"Manage"** → **"Pull or Deploy"**
3. Click **"Deploy HEAD Commit"**
4. Monitor deployment logs

### Verify Production Deployment

1. Visit `https://district8travelleague.com`
2. Complete post-deployment verification checklist

## Step 6: Set Up Git Workflow

### Local Development Setup

```bash
# Clone repository
git clone https://github.com/your-org/d8tl.git
cd d8tl

# Create and push development branches
git checkout -b develop
git push origin develop

git checkout -b staging
git push origin staging

# Set up local development environment
composer install
# Configure local database and settings
```

### Branching Strategy

- `main` → Production environment
- `staging` → Staging environment for testing
- `develop` → Active development branch
- `feature/*` → Feature development branches

## Step 7: Deployment Workflow

### For New Features

1. **Development**:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/new-feature
   # Make changes
   git commit -am "Add new feature"
   git push origin feature/new-feature
   ```

2. **Create Pull Request** to `develop` branch

3. **Deploy to Staging**:
   ```bash
   git checkout staging
   git pull origin staging
   git merge develop
   git push origin staging
   ```
   - This triggers automatic deployment to staging environment

4. **Test in Staging**:
   - Visit `https://staging.district8travelleague.com`
   - Complete QA testing
   - Verify all functionality

5. **Deploy to Production** (after testing approval):
   ```bash
   git checkout main
   git pull origin main
   git merge staging
   git push origin main
   ```

6. **Manual Deployment**:
   - In cPanel Git Version Control
   - Click "Pull or Deploy" for production repository
   - Monitor deployment and verify functionality

## Step 8: Monitoring and Maintenance

### Regular Tasks

1. **Monitor Deployment Logs**:
   - Check `/logs/deployment.log`
   - Review PHP error logs

2. **Database Backups**:
   - Automated backups before each production deployment
   - Manual backups before major changes

3. **Performance Monitoring**:
   - Monitor page load times
   - Check resource usage in cPanel

### Troubleshooting

1. **Deployment Fails**:
   - Check `.cpanel.yml` syntax
   - Verify file permissions
   - Review deployment logs in cPanel

2. **Database Issues**:
   - Verify credentials in config files
   - Check database connectivity
   - Review migration logs

3. **Permission Problems**:
   - Ensure proper file permissions
   - Check directory ownership
   - Verify writable directories

## Security Considerations

1. **Configuration Files**:
   - Never commit production passwords to Git
   - Use environment-specific config files
   - Protect config files via .htaccess

2. **Database Security**:
   - Use strong database passwords
   - Limit database user privileges
   - Regular security updates

3. **File Permissions**:
   - Maintain proper file permissions
   - Secure sensitive directories
   - Regular permission audits

## Support and Documentation

- **cPanel Git Documentation**: https://docs.cpanel.net/cpanel/files/git-version-control/
- **Project Documentation**: See `docs/tech.md` for detailed technical information
- **Troubleshooting**: Check deployment logs and error logs for issues

---

This setup provides a robust, automated deployment system that allows for safe testing in a staging environment before production deployment, while maintaining the simplicity and cost-effectiveness of shared hosting.
