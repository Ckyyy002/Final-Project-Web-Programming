# StudySync - Student Productivity & Social Hub
# User Guide & Documentation

## 🌟 Features

### 1. Task Management
- Create, edit, delete tasks
- Set priorities (Low, Medium, High, Urgent)
- Add deadlines and categories
- Mark tasks as complete (+50 XP)

### 2. Study Groups
- Create study groups
- Join public groups
- Invite friends to groups
- Set meeting schedules

### 3. Friend System
- Add friends by email
- Accept/reject friend requests
- See friends' progress
- Message friends (coming soon)

### 4. Gamification
- Earn XP for completing tasks
- Level up (every 1000 XP)
- See your progress on dashboard
- Compete with friends

## 👤 User Roles

### Student User
- Full access to all features
- Can create/join up to 5 groups
- Can add unlimited tasks
- XP and level progression

### Admin Features (Future)
- Manage all users
- View system statistics
- Moderate groups
- Reset passwords

## 📱 How to Use

### Getting Started
1. Register with email or Google
2. Complete your profile
3. Add your first task
4. Join a study group
5. Add friends

### Daily Use
1. Check dashboard for today's tasks
2. Update task status
3. Check group meetings
4. Message study partners
5. Track XP progress

## 🔧 Technical Information

### System Requirements
- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx
- 100MB storage
- SSL certificate (for production)

### File Structure

student_hub/
├── assets/ # CSS, JS, images
├── config/ # Configuration files
├── includes/ # Core PHP functions
├── pages/ # Main application pages
├── auth/ # Authentication
├── api/ # API endpoints
├── vendor/ # Third-party libraries
├── index.php # Landing page
└── .htaccess # URL rewriting

### Database Schema
See `database_schema.sql` for complete table structure.

## 🚀 Deployment Guide

### 1. Local Development
```bash
# Clone repository
git clone [repository-url]

# Import database
mysql -u root -p < database_schema.sql

# Configure
cp config/production.example.php config/production.php
# Edit config/production.php with your settings

# Test
php -S localhost:8000
