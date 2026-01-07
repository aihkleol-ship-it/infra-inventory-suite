# Changelog

## Version 2.0 - Rack View & Interactive Diagram

This update introduces a new interactive Rack View, allowing users to visualize and manage their rack layouts via drag-and-drop.

### ✨ New Features

*   **Interactive Rack View:**
    *   A new "Rack View" page has been added to the main menu.
    *   This view provides a visual representation of all racks, grouped by location.
    *   Devices are displayed in their respective positions within the racks.
    *   **Drag and Drop:** You can now move devices between slots *within the same rack* by dragging and dropping them. The device's `rack_position` is updated automatically.

### 🐛 Bug Fixes & Improvements

*   **Rack View:** Fixed a critical issue where the Rack View would not load due to incorrect or blocked CDN links for the drag-and-drop library.
*   The `handleSaveDevice` function in the frontend has been improved to be more robust for both creating and updating devices.
*   The database migration script is now non-destructive and safe to run multiple times.

---

## Version 1.0 - Rack and Position Fields

This update introduces the concept of "Racks" and "Rack Positions" to the inventory system, allowing for more precise location tracking of devices.

### <mark>Manual Steps Required</mark>

**IMPORTANT:** If you are upgrading from a previous version, you must perform the following action to apply the database changes:

*   **Update Database Schema:**
    *   A new migration script has been created to safely update your database.
    *   Open your web browser and navigate to `http://<your_server_address>/infra-inventory/api/migrate_v6_1.php`.
    *   This script will add the new `rack` and `rack_position` columns to your `inventory` table without deleting any data.

---

### Detailed Changes

#### Backend

1.  **Database Migration (`/infra-inventory/api/migrate_v6_1.php`)**
    *   A new migration script has been created to add the `rack` and `rack_position` columns to the `inventory` table. This is a non-destructive operation.

2.  **Database Setup (`/infra-inventory/api/setup_database.php`)**
    *   The main database setup script has been updated to include the new `rack` and `rack_position` columns for new installations.

3.  **Inventory API (`/infra-inventory/api/inventory.php`)**
    *   The API now includes `rack` and `rack_position` in all relevant responses (GET requests).
    *   The API now supports creating (POST) and updating (PUT) inventory items with `rack` and `rack_position` values.

4.  **CSV Import (`/infra-inventory/api/import.php`)**
    *   The CSV import logic has been updated to recognize the new format.
    *   The expected CSV header is now: `Hostname,IP,Serial,Type,Brand,Model,Location,Rack,RackPosition,Status`.
    *   `SubLocation` has been removed from the import process.

#### Frontend

1.  **Main Inventory View (`/infra-inventory/index.html`)**
    *   The main inventory table has been updated:
        *   The "Firmware" column has been removed to save space.
        *   The "Sub-Location" column has been replaced with two new columns: "Rack" and "Position".
    *   Sorting has been enabled for the new "Rack" and "Position" columns.

2.  **Add/Edit Device Form (`/infra-inventory/index.html`)**
    *   The "Sub-Location" text field has been replaced with two separate fields: "Rack" and "Position".

3.  **CSV Import Template (`/infra-inventory/index.html`)**
    *   The downloadable CSV template from the "Import" modal now reflects the new header format: `Hostname,IP,Serial,Type,Brand,Model,Location,Rack,RackPosition,Status`.