# LeaveDesk 🏢

**Version:** 1.0  
**Developed by:** Sayyad Ali Haider Sherazi (CMIT Intern)  
**Company:** Nimble Web Solutions - July 2026  

---

## 📖 Project Overview
LeaveDesk is a lightweight, internal HR management application designed to streamline employee leave requests and daily attendance. Built to replace inefficient chat-based requests and manual Excel spreadsheets, LeaveDesk provides a centralized, automated workflow for small to medium-sized teams.

The system features a complete role-based architecture (Employee, Manager, HR Admin), ensuring secure access and automated routing of leave approvals, notifications, and balance tracking.

## ✨ Core Features
* **Role-Based Access Control:** Distinct dashboards and permissions for Employees, Managers, and HR.
* **Online Leave Management:** Employees can apply for various leave types (Annual, Sick, Casual) with real-time balance validation.
* **Manager Approval Workflow:** Streamlined approval/rejection queue with immediate database updates and bypass logic for leadership.
* **Automated Notifications:** Real-time email and in-app alerts powered by Laravel's notification system.
* **Dynamic Balances:** Automatic deduction and tracking of leave limits based on company policy.
* **Secure Architecture:** Explicit database queries and strict route protection to maintain data integrity.

## 🛠️ Technology Stack
LeaveDesk is engineered on a modern, reactive, and hosting-friendly stack:
* **Backend:** Laravel 12 (PHP 8.3)
* **Frontend:** Livewire 3 + Alpine.js
* **Styling:** Tailwind CSS
* **Database:** MySQL 8.0
* **Notifications:** Laravel Mail (Log/SMTP)

## 🚀 Local Setup & Installation

1. **Clone the repository and install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Install frontend dependencies and compile assets:**
   ```bash
   npm install
   npm run build
   ```

3. **Environment Setup:**
   Duplicate `.env.example` to `.env` and generate the application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Ensure your database credentials and `MAIL_MAILER` (set to `log` for local testing) are properly configured in the `.env` file.*

4. **Run Database Migrations:**
   ```bash
   php artisan migrate
   ```

5. **Start the local development server:**
   ```bash
   php artisan serve
   ```

---
*Architected and developed internally at Nimble Web Solutions.*