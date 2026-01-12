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