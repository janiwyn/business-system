# Remote Ordering System - Installation Guide

## Step 1: Database Setup

1. Open phpMyAdmin
2. Select your database
3. Import the SQL schema:
   - Navigate to `sql/remote_orders_schema.sql`
   - Execute the SQL file

## Step 2: Configuration

1. Ensure `config/config.php` has the correct settings
2. Verify database connection in `includes/db.php`

## Step 3: File Structure

Ensure these directories exist:
