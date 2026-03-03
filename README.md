# 🏢 Enterprise HRMS
## Human Resource Management System — v1.0

A production-ready, full-stack Enterprise HRMS built with PHP 8 + MySQL + Modern JavaScript.

---

## 🚀 Quick Setup (5 Minutes)

### Prerequisites
- **XAMPP** with Apache + MySQL running
- PHP 8.0+

### Steps

**1. Place the project**
```
Copy folder → C:\xampp\htdocs\HRMs\
```

**2. Run Database Setup**
Open browser → `http://localhost/HRMs/database/setup.php`

**3. Login**
Open browser → `http://localhost/HRMs/`
- Email: `admin@hrms.com`
- Password: `Admin@123`

---

## 🔐 Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@hrms.com | Admin@123 |
| HR Manager | hr@hrms.com | Admin@123 |
| HR Executive | hr2@hrms.com | Admin@123 |
| Manager | manager@hrms.com | Admin@123 |
| Employee | john.doe@hrms.com | Admin@123 |
| Finance | finance@hrms.com | Admin@123 |
| Recruiter | recruiter@hrms.com | Admin@123 |

---

## 📋 Modules Implemented

### ✅ Core HR
- Employee master with full profiles
- Department & designation hierarchy
- Organization chart
- Employment history & lifecycle
- Document management

### ✅ Authentication & Security
- JWT-based authentication
- Role-Based Access Control (RBAC)
- Account lockout (5 failed attempts → 30 min lock)
- Complete audit trail
- Bcrypt password hashing

### ✅ Time & Attendance
- Check-in / Check-out with geolocation support
- Shift management & assignment
- Overtime tracking
- Monthly attendance reports
- Holiday calendar

### ✅ Leave Management
- 8 leave types (CL, SL, EL, ML, PL, etc.)
- Leave application workflow
- Manager approval/rejection
- Leave balance tracking
- Leave calendar view
- Auto-deduct on approval

### ✅ Payroll
- Salary structure management
- Monthly payroll runs
- Auto payslip generation (pro-rated)
- PF, ESI, TDS, Professional Tax
- Payroll approval workflow
- My Payslips for employees

### ✅ Recruitment & ATS
- Job posting management
- Candidate database
- Application pipeline (Kanban)
- Interview scheduling
- Feedback collection
- Stage progression tracking

### ✅ Performance Management
- OKR/KPI goal setting
- Goal progress tracking
- Performance cycles
- 360° review framework
- Performance analytics

### ✅ HR Analytics & Reports
- Headcount report
- Attrition analysis
- Attendance summary
- Leave utilization
- Payroll summary
- Diversity & inclusion
- Recruitment metrics
- Chart.js visualizations

### ✅ Employee Self-Service
- Personal profile view
- Check-in/Check-out
- Leave application
- Payslip access
- Goal tracking
- Notification center

### ✅ Notifications
- In-app notification system
- Leave request/approval alerts
- Real-time unread count

---

## 🛠 Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 (Custom MVC REST API) |
| Database | MySQL 8 (InnoDB, 30+ tables) |
| Frontend | HTML5 + Tailwind CSS + Vanilla JS |
| Charts | Chart.js 4 |
| Icons | Font Awesome 6 |
| Auth | JWT (HS256, no external library) |
| HTTP Client | Fetch API |

---

## 📁 Project Structure

```
HRMs/
├── index.html              # Login page
├── app.html                # Main SPA shell
├── .htaccess               # URL routing
├── api/
│   ├── index.php           # API router
│   ├── config/
│   │   ├── config.php      # App configuration
│   │   └── database.php    # DB connection (Singleton)
│   ├── helpers/
│   │   ├── JWT.php         # JWT implementation
│   │   └── Response.php    # API responses + Validator
│   ├── middleware/
│   │   └── Auth.php        # Authentication middleware
│   └── controllers/
│       ├── AuthController.php
│       ├── EmployeeController.php
│       ├── AttendanceController.php
│       ├── LeaveController.php
│       ├── PayrollController.php
│       ├── RecruitmentController.php
│       ├── PerformanceController.php
│       ├── DashboardController.php
│       └── ReportController.php
├── database/
│   ├── schema.sql          # Complete DB schema (30+ tables)
│   ├── seed.sql            # Sample data
│   └── setup.php           # One-click installer
└── assets/
    ├── css/app.css         # Global styles
    └── js/
        ├── utils.js        # Utilities, API client, helpers
        └── app.js          # Main application logic
```

---

## 🔌 API Reference

### Authentication
```
POST /api/auth/login          Login
POST /api/auth/logout         Logout
GET  /api/auth/me             Current user info
POST /api/auth/refresh        Refresh token
POST /api/auth/change-password
```

### Employees
```
GET  /api/employees           List (paginated, filterable)
POST /api/employees           Create employee
GET  /api/employees/{id}      Employee detail
PUT  /api/employees/{id}      Update
DEL  /api/employees/{id}      Terminate
GET  /api/employees/stats     Statistics
```

### Attendance
```
POST /api/attendance/checkin
POST /api/attendance/checkout
GET  /api/attendance          List
GET  /api/attendance/today    Today summary
GET  /api/attendance/my-status
GET  /api/attendance/report
```

### Leave
```
GET  /api/leaves              List requests
POST /api/leaves              Apply for leave
PUT  /api/leaves/{id}/approve Approve/Reject
PUT  /api/leaves/{id}/cancel  Cancel
GET  /api/leaves/balance      Leave balances
GET  /api/leaves/types        Leave types
GET  /api/leaves/calendar     Calendar view
```

### Payroll
```
GET  /api/payroll/runs            All runs
POST /api/payroll/runs            Create run
GET  /api/payroll/runs/{id}       Run detail
PUT  /api/payroll/runs/{id}/approve
GET  /api/payroll/payslip/{id}    Payslip detail
GET  /api/payroll/my-payslips
GET  /api/payroll/salary-structures
POST /api/payroll/salary-structures
GET  /api/payroll/analytics
```

### Recruitment
```
GET  /api/recruitment/jobs
POST /api/recruitment/jobs
PUT  /api/recruitment/jobs/{id}
GET  /api/recruitment/jobs/{id}/applications
PUT  /api/recruitment/applications/{id}/stage
POST /api/recruitment/applications/{id}/interview
PUT  /api/recruitment/interviews/{id}/feedback
POST /api/recruitment/candidates
GET  /api/recruitment/stats
```

### Performance
```
GET  /api/performance/cycles
POST /api/performance/cycles
GET  /api/performance/goals
POST /api/performance/goals
PUT  /api/performance/goals/{id}
GET  /api/performance/reviews
POST /api/performance/reviews
PUT  /api/performance/reviews/{id}/submit
GET  /api/performance/stats
```

### Reports
```
GET /api/reports/headcount
GET /api/reports/attrition
GET /api/reports/attendance-summary
GET /api/reports/leave-summary
GET /api/reports/payroll-summary
GET /api/reports/diversity
GET /api/reports/recruitment
```

---

## ⚙️ Configuration

Edit `api/config/config.php`:
```php
// Database (update if MySQL has a password)
// Edit api/config/database.php:
private string $password = 'your_mysql_password';

// JWT Secret (change in production!)
define('JWT_SECRET', 'your_secret_key_here');

// App URL
define('APP_URL', 'http://localhost/HRMs');
```

---

## 🔒 Security Features

- JWT authentication with expiry
- Account lockout after failed attempts
- RBAC with granular permissions
- Complete audit trail (all actions logged)
- Bcrypt password hashing (cost=12)
- SQL injection prevention (PDO prepared statements)
- XSS prevention (output escaping)
- CORS headers configured

---

## 📈 Database Schema Highlights

- **30+ normalized tables** with foreign keys
- JSON columns for flexible data (skills, components)
- Generated columns for derived values
- Optimized indexes for performance
- Audit trail on all critical operations
- Multi-company ready structure

---

## 🐛 Troubleshooting

**Blank page / 404 after setup**
- Ensure mod_rewrite is enabled in Apache
- Check `.htaccess` is in root folder
- Verify `AllowOverride All` in Apache config

**Database connection failed**
- Ensure XAMPP MySQL is running (green in control panel)
- Default MySQL user: `root` with empty password
- Edit `api/config/database.php` if different

**Login fails with correct credentials**
- Run setup.php again to verify seed data
- Check browser console for API errors

---

## 🚀 Production Checklist

- [ ] Change `JWT_SECRET` in config.php
- [ ] Set `APP_ENV = 'production'`
- [ ] Set MySQL password
- [ ] Enable HTTPS
- [ ] Configure SMTP for email notifications
- [ ] Set up automated backups
- [ ] Configure error logging

---

*Built with ❤️ using PHP 8 + MySQL + Tailwind CSS*
#   H R M S  
 