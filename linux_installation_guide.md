# InfraInventory on Linux: A Deployment White Paper

This document provides a comprehensive, step-by-step guide for deploying the InfraInventory suite on a new Linux server. The instructions are tailored for a Debian-based distribution such as Ubuntu 22.04 LTS.

## Part 1: Initial Server Setup & Security Hardening

This section covers the essential first steps to secure your new server before installing any applications.

### 1.1. Connect to Your Server

First, connect to your server as the `root` user via SSH.

```bash
ssh root@YOUR_SERVER_IP
```

### 1.2. System Update

Ensure all system packages are up-to-date.

```bash
apt update && apt upgrade -y
```

### 1.3. Create a Limited User Account

Running services as `root` is a security risk. We will create a new user and grant it administrative privileges.

```bash
# Replace 'deployer' with your desired username
adduser deployer

# Add the new user to the 'sudo' group to grant admin rights
usermod -aG sudo deployer
```

Now, log out of the `root` account and log back in as the new user.

```bash
exit
ssh deployer@YOUR_SERVER_IP
```

All subsequent commands should be run as this new `deployer` user.

### 1.4. Basic Firewall Configuration

We will use `ufw` (Uncomplicated Firewall) to restrict traffic to only the services we need.

```bash
# Allow SSH connections (so you don't get locked out)
sudo ufw allow OpenSSH

# Allow HTTP and HTTPS traffic for the web server
sudo ufw allow 'Apache Full'

# Enable the firewall
sudo ufw enable
```

When prompted, type `y` and press Enter to proceed.

---

## Part 2: Installing the LAMP Stack

Next, we will install Apache (web server), MariaDB (database), and PHP (scripting language).

### 2.1. Install Apache

```bash
sudo apt install apache2 -y
```

### 2.2. Install MariaDB

MariaDB is a community-developed, open-source fork of MySQL and serves as a robust database server.

```bash
sudo apt install mariadb-server -y
```

### 2.3. Secure MariaDB

Run the included security script to remove insecure defaults and lock down access.

```bash
sudo mysql_secure_installation
```

You will be asked a series of questions. For a new installation, answer them as follows:

*   **Enter current password for root (enter for none):** Press **Enter**.
*   **Switch to unix_socket authentication?** Press **n**.
*   **Set root password?** Press **Y**, then enter and confirm a strong password for the database `root` user.
*   **Remove anonymous users?** Press **Y**.
*   **Disallow root login remotely?** Press **Y**.
*   **Remove test database and access to it?** Press **Y**.
*   **Reload privilege tables now?** Press **Y**.

### 2.4. Install PHP and Required Modules

Install PHP along with the modules necessary for Apache and MariaDB integration, and for the application to function correctly.

```bash
sudo apt install php libapache2-mod-php php-mysql php-json php-mbstring php-xml php-cli -y
```

---

## Part 3: Deploying the InfraInventory Application

With the server stack in place, we can now deploy the application itself.

### 3.1. Create the Database and User

Log into the MariaDB shell using the root password you set earlier.

```bash
sudo mariadb -u root -p
```

Execute the following SQL commands to create the database and a dedicated user for the application. Replace `'YourSecurePassword'` with a strong, unique password.

```sql
CREATE DATABASE infra_inventory;
CREATE USER 'infra_user'@'localhost' IDENTIFIED BY 'YourSecurePassword';
GRANT ALL PRIVILEGES ON infra_inventory.* TO 'infra_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.2. Deploy Application Files

Navigate to the Apache web root and clone the project repository.

```bash
cd /var/www/html

# Remove the default Apache index page
sudo rm index.html

# Clone your project files here. If you have them in a git repo:
# sudo git clone https://github.com/aihkleol-ship-it/infra-inventory-suite.git .
# For this example, we'll assume you are copying files into /var/www/html/
# Ensure the final structure is /var/www/html/index.html, /var/www/html/api/, etc.
```

> **Note:** The above assumes you are deploying to the default web root. For production, creating a dedicated Virtual Host is recommended.

### 3.3. Set File Permissions

The web server needs permission to read the files. We'll set the ownership to the `www-data` user, which is what Apache runs as.

```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

### 3.4. Configure the Application

Edit the application's configuration file to use the database credentials you created.

```bash
sudo nano /var/www/html/infra-inventory-suite/infra-inventoryapi/api/config.php
```

Modify the database connection variables to match the user and password from step 3.1.

```php
// api/config.php
$host = 'localhost';
$db_name = 'infra_inventory';
$username = 'infra_user';
$password = 'YourSecurePassword'; // Use the password you created
```

Press `CTRL+X`, then `Y`, then `Enter` to save and exit `nano`.

---

## Part 4: Final Installation & Verification

### 4.1. Run the Web Installer

Open your web browser and navigate to the automated setup script using your server's IP address.

**http://YOUR_SERVER_IP/infra-inventory-suite/infra-inventoryapi/api/setup_database.php**

You should see a page with a "Connect & Install" button. Click it. Upon completion, a "✅ Installation Successful!" message will appear.

### 4.2. 🔒 Critical Security Step

Immediately after successful installation, **you must delete the setup script** to prevent unauthorized database resets.

```bash
sudo rm /var/www/html/infra-inventory-suite/infra-inventoryapi/api/setup_database.php
```

### 4.3. Log In

Navigate to your server's IP address in your browser.

**http://YOUR_SERVER_IP/**

Log in with the default administrator credentials:
*   **Username:** `admin`
*   **Password:** `password123`

Your InfraInventory instance is now successfully deployed and ready for use.