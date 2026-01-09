# Infra Inventory Suite

A modern, lightweight, and responsive web application for managing IT infrastructure assets. Keep track of your servers, network devices, and more with an intuitive interface, visual rack layouts, and robust administrative features.

![InfraInventory Screenshot](https://via.placeholder.com/800x400.png?text=InfraInventory+Dashboard)

## ✨ Key Features

*   **Comprehensive Inventory Management**: Track devices by hostname, IP, serial number, model, location, and status.
*   **Interactive Rack View**: Visualize your server racks and manage device placement with an intuitive drag-and-drop interface.
*   **User & Role Management**: Secure the system with multi-level user access (Admin, Editor, Viewer).
*   **Encrypted Backups**: Create secure, password-protected (AES-256-CBC) backups of your entire database.
*   **Data Import/Export**: Easily bulk-import your inventory from a CSV file and export your data when needed.
*   **Audit Logging**: Keep a detailed log of all actions performed within the system for accountability and security.
*   **Email Alerting**: Integrate with the `InfraGateway` microservice to send system alerts and test messages.
*   **Modern Tech Stack**: Built with a reliable PHP backend, a dynamic React frontend, and styled with Tailwind CSS.
*   **Responsive Design**: Access and manage your inventory from any device, with automatic light/dark mode support.

## 🚀 Quick Start (Windows / Laragon)

This guide will get you up and running using the recommended Laragon WAMP stack on Windows.

### 1. Environment Setup

1.  Download and install **Laragon (Full version)**.
2.  Launch Laragon and click **"Start All"** to run Apache and MySQL.

### 2. Deploy Files

1.  Navigate to Laragon's web root directory (usually `C:\laragon\www`).
2.  Create a new folder named `infra-inventory`.
3.  Copy the project files (`index.html`, `api/` folder, etc.) into this new directory. Your file structure should look like this:
    ```
    C:\laragon\www\
    └── infra-inventory\
        ├── api\
        ├── index.html
        └── ...
    ```

### 3. Configure Database

1.  Open the `infra-inventory/api/config.php` file in a text editor.
2.  Verify the database credentials. For a default Laragon installation, you shouldn't need to change anything.
    ```php
    $host = 'localhost';
    $db_name = 'infra_inventory';
    $username = 'root';
    $password = ''; // Default is an empty password
    ```

### 4. Initialize the Database

1.  Open your web browser and navigate to the automated installation script:
    **http://localhost/infra-inventory/api/setup_database.php**
2.  Confirm the pre-filled settings and click the **"Connect & Install"** button.
3.  You should see a "✅ Installation Successful!" message.

> **Security Note**: After successful installation, it is highly recommended to delete or rename the `setup_database.php` file to prevent accidental resets.

### 5. Log In

1.  Navigate to the application's main page:
    **http://localhost/infra-inventory/**
2.  Log in with the default administrator credentials:
    *   **Username:** `admin`
    *   **Password:** `password123`

## 📦 Version History

### Version 2.0 - Rack View & Interactive Diagram
*   **Interactive Rack View:** Visualize racks and drag-and-drop devices to update their position.
*   **Bug Fixes:** Resolved issues with CDN links for the drag-and-drop library and improved device save logic.

### Version 1.0 - Rack and Position Fields
*   **Enhanced Tracking:** Added `rack` and `rack_position` fields to the database and API for more precise device location.
*   **UI Updates:** The main table and device forms were updated to include the new rack and position fields.
*   **CSV Import:** The CSV import format was updated to include the new fields.

## 🔧 Tech Stack

*   **Backend**: PHP 7.4+
*   **Database**: MySQL / MariaDB
*   **Frontend**: React, Tailwind CSS
*   **Web Server**: Apache (or Nginx)

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the issues page.

## 📄 License

This project is licensed under the MIT License. See the `LICENSE` file for details.
