# Infra-System on Linux: A Deployment Guide

This document provides a comprehensive, step-by-step guide for deploying the Infra-System suite on a new Linux server. The instructions are tailored for a Debian-based distribution such as Ubuntu 22.04 LTS.

## 1. Prerequisites

### 1.1. Server Requirements
- A fresh server running Ubuntu 22.04 LTS.
- A user with `sudo` privileges.
- A static IP address for your server.

### 1.2. Initial Server Setup
Connect to your server via SSH and perform the initial setup.

**Update the system:**
```bash
sudo apt update && sudo apt upgrade -y
```

**Set up the firewall:**
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

## 2. LAMP Stack Installation
Install Apache, MariaDB, and PHP with the required modules.

```bash
sudo apt install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-curl php-json
```

Enable the Apache rewrite module:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## 3. Database Setup
Secure the MariaDB installation and create databases and users for the applications.

### 3.1. Secure MariaDB
Run the security script and follow the prompts to set a root password and secure your installation.
```bash
sudo mysql_secure_installation
```

### 3.2. Create Databases and Users
Log in to the MariaDB shell:
```bash
sudo mysql -u root -p
```

Execute the following SQL commands, replacing `YourSecurePassword` with strong, unique passwords.

**For `infra-inventory`:**
```sql
CREATE DATABASE infra_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'infra_user'@'localhost' IDENTIFIED BY 'YourSecurePassword1';
GRANT ALL PRIVILEGES ON infra_inventory.* TO 'infra_user'@'localhost';
```

**For `infra-gateway`:**
```sql
CREATE DATABASE infra_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gateway_user'@'localhost' IDENTIFIED BY 'YourSecurePassword2';
GRANT ALL PRIVILEGES ON infra_gateway.* TO 'gateway_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 4. Application Deployment

### 4.1. Clone the Repository
Clone the project into the web server's root directory.
```bash
sudo git clone https://github.com/aihkleol-ship-it/infra-inventory-suite.git /var/www/infra-system
```

### 4.2. Set File Permissions
Set the correct ownership and permissions for the application files.
```bash
sudo chown -R www-data:www-data /var/www/infra-system
sudo chmod -R 755 /var/www/infra-system
```

## 5. Configuration

### 5.1. Central Database Configuration
Create the central configuration file from the example.
```bash
sudo cp /var/www/infra-system/infra-system-config.php.example /var/www/infra-system/infra-system-config.php
```
Edit the file and set the database credentials. For this guide, we'll use the `root` user for simplicity, but it's recommended to use the specific users created earlier.
```bash
sudo nano /var/www/infra-system/infra-system-config.php
```
```php
<?php
$host     = 'localhost';
$username = 'root'; // Or 'infra_user' and 'gateway_user' respectively in each app's config
$password = 'YourRootDBPassword'; 
```

**Note:** Since both applications now share this file, you can either use a single powerful user like `root` or create separate config files for each application if you want to use the dedicated database users. The current setup assumes a single user for simplicity of the central config file.

## 6. Web Server Configuration
Create an Apache Virtual Host to serve the application.

```bash
sudo nano /etc/apache2/sites-available/infra-system.conf
```

Paste the following configuration, replacing `your_server_ip` with your server's IP address. This will serve the `infra-inventory` as the main site.

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName your_server_ip
    DocumentRoot /var/www/infra-system/infra-inventory

    <Directory /var/www/infra-system/infra-inventory>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Alias for the gateway admin panel
    Alias /gateway /var/www/infra-system/infra-gateway

    <Directory /var/www/infra-system/infra-gateway>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

Enable the new site and restart Apache:
```bash
sudo a2ensite infra-system.conf
sudo a2dissite 000-default.conf
sudo systemctl restart apache2
```

## 7. Run Web Installers
Run the setup scripts for both applications via your web browser.

*   **`infra-inventory`**: Navigate to `http://your_server_ip/api/setup_database.php`
*   **`infra-gateway`**: Navigate to `http://your_server_ip/gateway/api/setup.php`

You should see success messages for both.

## 8. Security Hardening

### 8.1. Remove Setup Scripts
This is a critical step to prevent unauthorized access and resets.
```bash
sudo rm /var/www/infra-system/infra-inventory/api/setup_database.php
sudo rm /var/www/infra-system/infra-gateway/api/setup.php
```

### 8.2. Change Default Passwords
Log in to both applications and change the default admin passwords immediately.
- `infra-inventory` default: `admin` / `password123`
- `infra-gateway` default: `admin` / `secret123`

## 9. Cron Jobs
Set up cron jobs for automated tasks. Open the crontab for the web server user:
```bash
sudo -u www-data crontab -e
```

Add the following lines to run the scripts daily at midnight:
```
# End-of-Support report for infra-inventory
0 0 * * * /usr/bin/php /var/www/infra-system/infra-inventory/api/cron_eos_report.php

# Zabbix synchronization for infra-inventory
0 1 * * * /usr/bin/php /var/www/infra-system/infra-inventory/api/cron_zabbix_sync.php
```

## 10. Post-Installation
Your Infra-System suite is now installed and ready to be configured.
- Access `infra-inventory` at `http://your_server_ip/`
- Access `infra-gateway` at `http://your_server_ip/gateway/`
- Configure your SMTP and Zabbix settings in the `infra-gateway` admin panel.
- Configure your `infra-inventory` settings, including the Gateway URL and API Key.

Your system is now ready for use.