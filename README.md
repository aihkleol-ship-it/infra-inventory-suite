# Infra-System

Infra-System is a comprehensive suite of tools designed for managing IT infrastructure. It provides a centralized inventory system, along with supporting services for notifications and real-time updates.

## System Architecture

The Infra-System suite is composed of three main components:

### 1. Infra-Inventory

This is the core of the suite, providing a web-based IT asset management system. It allows users to track and manage hardware assets, including their physical location, rack position, and status.

**Key Features:**
*   **Asset Management:** Full CRUD (Create, Read, Update, Delete) functionality for network devices, servers, and other hardware.
*   **Rack Visualization:** An interactive, drag-and-drop interface to visualize and manage rack layouts.
*   **Data Management:** Features for CSV import/export, database backup, and restoration.
*   **User Roles:** Simple role-based access control (e.g., 'viewer' role).
*   **Reporting:** Automated End-of-Support (EoS) reports.

### 2. Infra-Gateway

A centralized and independent API-driven email gateway. Other applications within the suite can use this service to send emails and notifications. It requires API key authentication and logs all transactions.

### 3. Infra-Websocket (Under Development)

This component is planned for introducing real-time features into the ecosystem. The primary goal is to provide live updates and notifications to the Infra-Inventory front-end, enhancing the user experience. The source directory for this component is currently empty.

## Technology Stack

*   **Backend:** PHP
*   **Database:** MariaDB (MySQL)
*   **Web Server:** Apache
*   **Frontend:** HTML, vanilla JavaScript, and CSS, with a JavaScript library for drag-and-drop functionality.

## Database Schema

The Infra-System suite uses two separate databases: `infra_inventory` and `infra_gateway`.

### `infra_inventory` Database

This database stores all the data for the main inventory management application.

*   **`inventory`**: The core table containing all hardware assets and their details (hostname, IP, serial number, location, status, etc.).
*   **`users`**: Stores user accounts and roles (`admin`, `editor`, `viewer`) for accessing the inventory system.
*   **`system_settings`**: A key-value store for system-wide settings, such as the `infra-gateway` URL and API key.
*   **`device_types`**, **`brands`**, **`models`**: Lookup tables for categorizing devices. Models are linked to brands and can have End-of-Support (EoS) dates.
*   **`audit_logs`**: Records all actions performed by users within the system for accountability.

### `infra_gateway` Database

This database manages the operation of the centralized email and API gateway.

*   **`gateway_settings`**: A key-value store for all gateway-related configurations, including SMTP server details, Zabbix API credentials, and cached Zabbix auth tokens.
*   **`gateway_clients`**: Manages API clients and their unique keys, which are required to use the gateway's services.
*   **`gateway_logs`**: A detailed log of every transaction that passes through the gateway, including email sending status and errors.
*   **`gateway_users`**: Stores user accounts for the gateway's separate admin panel.