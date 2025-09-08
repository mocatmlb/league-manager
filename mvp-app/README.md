# District 8 Travel League - MVP Application

## Local Development Setup

This is the PHP-based MVP application for the District 8 Travel League management system.

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or PHP built-in server
- Composer (optional, for future dependencies)

### Quick Setup

1. **Database Setup**
   ```bash
   # Create the database and tables
   mysql -u root -p < database/schema.sql
   ```

2. **Configuration**
   - Edit `includes/config.php` to match your database settings
   - Update `DB_HOST`, `DB_USER`, `DB_PASS` as needed

3. **Web Server Setup**
   
   **Option A: PHP Built-in Server (Development)**
   ```bash
   cd mvp-app/public
   php -S localhost:8000
   ```
   
   **Option B: Apache/Nginx**
   - Point document root to `mvp-app/public/`
   - Ensure `.htaccess` is enabled (Apache)

4. **Access the Application**
   - Public site: `http://localhost:8000/`
   - Coaches login: `http://localhost:8000/coaches/login.php`
   - Admin login: `http://localhost:8000/admin/login.php`

### Default Credentials

- **Admin Login**: `admin` / `admin`
- **Coaches Password**: `coaches`

**⚠️ IMPORTANT: Change these passwords immediately after setup!**

### Directory Structure

```
mvp-app/
├── public/                 # Public web files
│   ├── index.php          # Home page
│   ├── schedule.php       # Schedule display
│   └── standings.php      # Standings display
├── coaches/               # Coach password-protected pages
│   ├── login.php         # Coach authentication
│   ├── schedule-change.php # Schedule change requests
│   ├── score-input.php   # Score submission
│   └── contacts.php      # Contact directory
├── admin/                 # Administrative console
│   ├── index.php         # Admin dashboard
│   ├── login.php         # Admin authentication
│   └── [management pages]
├── includes/              # Core PHP files
│   ├── bootstrap.php     # Application initialization
│   ├── config.php        # Configuration settings
│   ├── database.php      # Database connection class
│   ├── auth.php          # Authentication functions
│   └── functions.php     # Utility functions
├── assets/               # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/              # File uploads
│   └── documents/        # Public documents
└── database/             # Database files
    └── schema.sql        # Database schema
```

### Features Implemented

#### Public Features (No Authentication)
- ✅ Home page with today's games and upcoming games
- ✅ Schedule display (table format)
- ✅ Standings display (by division)
- ✅ Document access

#### Coach Features (Password Protected)
- 🚧 Schedule change request form
- 🚧 Score input form
- 🚧 Contact directory

#### Admin Features (Full Console)
- 🚧 Dashboard with metrics
- 🚧 Complete CRUD management for all entities
- 🚧 Email system configuration

### Development Status

- ✅ Database schema created
- ✅ Core PHP framework established
- ✅ Authentication system implemented
- ✅ Public home page created
- 🚧 Schedule and standings pages (in progress)
- 🚧 Coach interfaces (in progress)
- 🚧 Admin console (in progress)
- 🚧 Email notification system (planned)

### Next Steps

1. Complete public schedule and standings pages
2. Implement coach login and forms
3. Build admin dashboard and management interfaces
4. Add email notification system
5. Implement calendar view for schedules
6. Add data filtering and sorting

### Database Sample Data

The schema includes sample data:
- 1 Program: Junior Baseball
- 1 Season: 2024 Junior Baseball Spring
- 1 Division: American League
- 3 Sample locations
- Default admin user and settings
- Email templates

### Troubleshooting

**Database Connection Issues:**
- Verify MySQL is running
- Check database credentials in `config.php`
- Ensure database `d8tl_mvp` exists

**Permission Issues:**
- Ensure web server can read all files
- Check `uploads/` directory is writable
- Verify PHP has necessary extensions (PDO, MySQL)

**Session Issues:**
- Check PHP session configuration
- Ensure session directory is writable
- Verify cookies are enabled in browser

### Security Notes

- Default passwords MUST be changed in production
- Enable HTTPS in production
- Configure proper file permissions
- Review and update security settings in `config.php`
- Implement proper backup procedures

### Support

For development questions or issues, refer to the main project documentation in `/docs/MVP/mvp-requirements.md`.
