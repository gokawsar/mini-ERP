# 🚀 Mini-ERP System

[![GitHub last commit](https://img.shields.io/github/last-commit/your-username/mini-erp)](https://github.com/your-username/mini-erp/commits/main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## ✨ Overview

The **Mini-ERP System** is for small and medium-sized businesses (SMBs) include client management, inventory tracking, web-based ERP, and an invoicing system that has been carefully planned.  It is a database-driven application that centralizes financial billing, sales quotes, delivery slips, and important businesscking.  Its main goals are to improve data accuracy, expedite daily tasks, and offer informative reporting so that decisions can be made with knowledge.

This project was developed as a database lab project, focusing on robust schema design, secure backend logic, and a user-friendly frontend.

## 🌟 Features

<img width="3840" height="1737" alt="er_diagram_con" src="https://github.com/user-attachments/assets/205ba32c-f3d8-4025-91c4-1c709f03eb0d" />

* **Integrated Client Management**: Comprehensive profiles for all clients, linked to sales and job processes.
* **Dynamic Inventory Control**: Manage items with unit prices, stock quantities, and reorder levels, preventing stockouts.
* **Full Sales Workflow (Quote-to-Cash)**: Seamless flow from:
    * **Quotations**: Generate detailed sales offers with multiple items, quantities, unit prices, discounts, and real-time tax calculations.
    * **Delivery Slips**: Record item deliveries based on accepted quotations.
    * **Bills/Invoicing**: Generate final invoices from delivery slips and track payment statuses.
* **Project/Job Tracking**: Manage specific jobs for clients with status updates.
* **Comprehensive Reporting Suite**: Access various pre-defined reports, including:
    * Total Sales and Clients
    * Quotation Conversion Rate
    * Pending Bills by Client
    * Low Stock Alerts
    * Job Status Summaries
* **Secure User Authentication**: Session-based login/logout with password hashing.
* **Intuitive User Interface**: A clean, consistent, and responsive design with a minimizable sidebar for enhanced navigation and screen real estate.
* **Data Integrity**: Enforced by a normalized database schema with robust primary and foreign key constraints.

## 🛠️ Technologies Used

* **Backend**: PHP (for server-side logic and templating)
* **Database**: MySQL
* **Frontend**: HTML5, CSS3 (inline styling for consistency), JavaScript (for dynamic interactions, real-time calculations, and sidebar toggle)
* **Web Server**: Apache HTTP Server (typically via XAMPP/WAMP)
* **Database Connectivity**: PHP's MySQLi extension (using prepared statements for security)

## 📊 Database Schema

The Mini-ERP system is built upon a normalized relational database schema designed for efficiency and integrity. Below is a conceptual overview of the main entities and their relationships.

![er_diagram_i](https://github.com/user-attachments/assets/a9e0cc49-40f2-4e3e-8a1e-54de1f402e3e)


## 🚀 Installation & Setup

To get a local copy up and running, follow these simple steps.

### Prerequisites

* **Web Server**: Apache (e.g., XAMPP, WAMP, MAMP)
* **Database**: MySQL
* **PHP**: Version 7.4+ (with MySQLi extension enabled)

### Steps

1.  **Clone the Repository**
    ```bash
    git clone [https://github.com/gokawsar/mini-ERP.git](https://github.com/gokawsar/mini-ERP.git)
    cd mini-erp
    ```
2.  **Database Setup**
    * Open your MySQL client (e.g., phpMyAdmin, MySQL Workbench).
    * Create a new database named `dbms`.
    * Import the provided SQL schema (`dbms.sql`). You can usually do this by running the SQL script.
    
3.  **Place Files on Web Server**
    * Move the `mini-erp` project folder into your web server's document root (e.g., `htdocs` for XAMPP, `www` for WAMP).
    * Update database connection details in relevant PHP files (e.g., `config.php` if you create one, or directly in `sidebar.php` and other modules where `$conn` is initialized) if your MySQL host, username, or password are not `localhost`, `root`, and `''` respectively.
      
4.  **Access the Application**
    * Open your web browser and navigate to `http://localhost/mini-erp/users/login.php` to access the login page.
    * You can create a new account via the registration form.

## 💡 Usage

After logging in, you can navigate through the sidebar to:
* **Dashboard**: Get an overview of key metrics.
* **Clients**: Add, view, edit, or import client information.
* **Items**: Manage your product/service inventory.
* **Quotations**: Create new sales quotations, view their details, update, or delete them.
* **Reports**: Generate various business reports based on sales, inventory, and client data.

The sidebar is minimizable, providing more screen space when needed.

## ⛔ Limitations

As a lab-based project, the Mini-ERP has certain limitations:
* **Basic RBAC**: Limited role-based access control beyond basic user authentication.
* **No Integrations**: Lacks integration with external payment gateways, accounting software, or CRM tools.
* **Manual Inventory Deduction**: Stock quantities are not automatically deducted upon delivery or billing.
* **Limited Custom Reporting**: Users cannot build highly custom reports with dynamic filtering.
* **No Audit Logs**: Comprehensive tracking of data changes is not implemented.
* **Inline CSS**: Styling primarily uses inline CSS, which can be less maintainable for large applications.

## 📈 Future Enhancements

Potential future developments to extend the Mini-ERP's capabilities:
* **Advanced RBAC**: Implement granular permissions for different user roles.
* **Automated Inventory**: Introduce triggers or backend logic for automatic stock updates.
* **Payment Gateway Integration**: Enable online payment processing.
* **Dynamic Reporting Interface**: Provide tools for users to build custom reports with visualizations.
* **Notifications**: Add an internal system for alerts (e.g., low stock, overdue bills).
* **RESTful API**: Develop an API for external system integration.
* **Frontend Framework**: Migrate to a CSS framework (e.g., Tailwind CSS, Bootstrap) and external stylesheets for better maintainability and modern design practices.

## 🤝 Contributing

Contributions are welcome! If you have suggestions for improvements or find any issues, please feel free to:
1.  Fork the repository.
2.  Create your feature branch (`git checkout -b feature/AmazingFeature`).
3.  Commit your changes (`git commit -m 'Add some AmazingFeature'`).
4.  Push to the branch (`git push origin feature/AmazingFeature`).
5.  Open a Pull Request.

## 📄 License

Distributed under the MIT License. See `LICENSE` for more information.

---

Project Link: [https://github.com/gokawsar/mini-ERP](https://github.com/gokawsar/mini-ERP)
