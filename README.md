# HRMS — Enterprise Human Resource Management System

> A full-stack HRMS for managing the employee lifecycle, attendance, leave, payroll, recruitment, performance, and HR analytics.

**Stack:** PHP 8.2 · MySQL 8 · JavaScript · Tailwind CSS · REST API · JWT · Chart.js

---

## Overview

HRMS is a modular human-resource platform designed around real business workflows rather than isolated CRUD screens.

It brings core HR operations into one application:

- Employee management
- Attendance & shifts
- Leave & approvals
- Payroll
- Recruitment / ATS
- Performance management
- HR analytics & reports
- Employee self-service
- Notifications
- Role-based access control

---

## Product Areas

| Module | What it covers |
|---|---|
| **Core HR** | Employee profiles, departments, designations, organization structure, employment history, documents |
| **Attendance** | Check-in/out, geolocation support, shifts, overtime, holidays, attendance reports |
| **Leave** | Leave types, applications, approvals, balances, calendar, automatic balance deduction |
| **Payroll** | Salary structures, payroll runs, payslips, PF, ESI, TDS, professional tax, approvals |
| **Recruitment / ATS** | Jobs, candidates, applications, Kanban pipeline, interviews, feedback, stage tracking |
| **Performance** | OKRs/KPIs, goals, performance cycles, 360° reviews, progress tracking |
| **Analytics** | Headcount, attrition, attendance, leave, payroll, recruitment and workforce reports |
| **Employee Self-Service** | Profile, attendance, leave, payslips, goals, notifications |
| **Notifications** | In-app alerts, leave workflow notifications and unread tracking |

---

## Architecture

```text
Browser
   │
   ▼
HTML / Tailwind CSS / Vanilla JavaScript
   │
   │ Fetch API
   ▼
PHP REST API
   │
   ├── Authentication & JWT
   ├── RBAC / Authorization
   ├── Controllers
   ├── Validation
   └── Business Logic
   │
   ▼
MySQL 8
   └── 30+ relational tables
```

The backend follows a lightweight custom MVC-style structure with a REST API layer, authentication middleware, controllers, helpers, and a relational MySQL database.

---

## Security

- JWT authentication with token expiry
- Role-Based Access Control (RBAC)
- Account lockout after repeated failed logins
- Bcrypt password hashing
- PDO prepared statements
- XSS protection through output escaping
- CORS configuration
- Audit trail for critical operations

> **Production note:** The included configuration contains development/demo values. Replace secrets, credentials, database settings, and environment configuration before deployment.

---

## Tech Stack

**Frontend**  
HTML5 · Tailwind CSS · Vanilla JavaScript · Chart.js · Font Awesome

**Backend**  
PHP 8.2 · Custom MVC architecture · REST API

**Database**  
MySQL 8 · InnoDB · Foreign keys · Indexed relational schema

**Authentication**  
JWT · RBAC · Bcrypt

**Client communication**  
Fetch API · JSON

---

## Project Structure

```text
HRMs/
├── index.html
├── app.html
├── .htaccess
├── api/
│   ├── index.php
│   ├── config/
│   ├── helpers/
│   ├── middleware/
│   └── controllers/
├── database/
│   ├── schema.sql
│   ├── seed.sql
│   └── setup.php
└── assets/
    ├── css/
    └── js/
```

---

## Getting Started

### Requirements

- XAMPP or another Apache + PHP environment
- PHP 8.0+
- MySQL 8+
- Apache `mod_rewrite` enabled

### 1. Clone

```bash
git clone https://github.com/mohitkumar402/HRMS.git
```

Place the project inside your web server directory, for example:

```text
C:\xampp\htdocs\HRMs
```

### 2. Configure the database

Update the database credentials in:

```text
api/config/database.php
```

Then run the database setup script or import the provided SQL schema and seed files.

### 3. Configure the application

Review:

```text
api/config/config.php
```

Set a strong JWT secret and the correct application URL for your environment.

### 4. Launch

Open the project through your local Apache server:

```text
http://localhost/HRMs/
```

---

## Demo Roles

The seed data includes accounts for several application roles, including:

**Super Admin · HR Manager · HR Executive · Manager · Employee · Finance · Recruiter**

For security, change or remove all demo credentials before exposing the application to a real environment.

---

## API Surface

The REST API is organized around the major HR domains:

```text
/auth
/employees
/attendance
/leaves
/payroll
/recruitment
/performance
/reports
```

Examples include:

```text
POST /api/auth/login
GET  /api/auth/me
GET  /api/employees
POST /api/employees
POST /api/attendance/checkin
POST /api/attendance/checkout
POST /api/leaves
GET  /api/leaves/balance
GET  /api/payroll/runs
GET  /api/recruitment/jobs
GET  /api/performance/goals
GET  /api/reports/headcount
```

---

## Database

The application uses a normalized MySQL schema with 30+ tables covering employees, organizational structure, attendance, leave, payroll, recruitment, performance, notifications, and auditing.

Key database characteristics:

- Foreign-key relationships
- Indexed queries
- JSON fields where flexible data is useful
- Derived/generated values where appropriate
- Audit-oriented records
- Multi-company-ready structure

---

## Production Checklist

Before production deployment:

- [ ] Replace all demo credentials
- [ ] Generate a strong JWT secret
- [ ] Configure production database credentials
- [ ] Enable HTTPS
- [ ] Set production environment configuration
- [ ] Configure SMTP / email delivery
- [ ] Configure automated database backups
- [ ] Enable secure error logging
- [ ] Review CORS and Apache configuration
- [ ] Remove development-only setup access

---

## Why This Project

HRMS was built as an example of **end-to-end business application engineering**: authentication, authorization, relational data modeling, workflow logic, reporting, dashboards, and user-facing interfaces working together as one product.

The goal is not simply to demonstrate a UI — it is to model the workflows that an actual HR platform needs to support.

---

## Status

**Version:** 1.0  
**Type:** Full-stack enterprise application  
**Architecture:** PHP REST API + MySQL + browser-based frontend

---

<p align="center">
  <strong>Built with PHP, MySQL, JavaScript & Tailwind CSS</strong>
</p>
