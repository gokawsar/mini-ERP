<?php
// sidebar.php
// This file contains the HTML structure and styling for the application's sidebar.
?>

<nav aria-label="Main Sidebar" class="sidebar" style="width:220px; background:#2c3e50; color:#ecf0f1; height:100vh; position:fixed; top:0; left:0; z-index:1000; box-shadow: 2px 0 5px rgba(0,0,0,0.1);">
    <div style="padding: 20px 16px; border-bottom: 1px solid #34495e; text-align: center;">
        <h2 style="margin: 0; font-size: 1.5em; color: #ecf0f1;">Mini-ERP</h2>
    </div>
    <ul style="list-style:none; padding:0; margin:0;">
        <li style="border-bottom: 1px solid #34495e;">
            <a href="/dbms/index.php" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Dashboard
            </a>
        </li>
        <li style="border-bottom: 1px solid #34495e;">
            <a href="/dbms/clients/index.php" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Clients
            </a>
        </li>
        <li style="border-bottom: 1px solid #34495e;">
            <a href="/dbms/items/index.php" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Items
            </a>
        </li>
        <li style="border-bottom: 1px solid #34495e;">
            <a href="/dbms/quotations/index.php" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Quotations
            </a>
        </li>
        <li style="border-bottom: 1px solid #34495e;">
            <a href="/dbms/reports/index.php" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Reports
            </a>
        </li>
        <li style="border-top: 1px solid #34495e; margin-top: auto; ">
            <a href="/dbms/users/login.php?logout=1" style="color:#ecf0f1; text-decoration:none; padding:16px; display:block; transition: background-color 0.3s ease;">
                Logout
            </a>
        </li>
    </ul>
</nav>

<style>
.sidebar {
    font-family: 'Arial', sans-serif;
    display: flex; 
    flex-direction: column; 
}

.sidebar ul {
    flex-grow: 1; 
    display: flex; 
    flex-direction: column; 
}

.sidebar ul li:last-of-type {
    margin-top: auto; 
}

.sidebar a:focus, .sidebar a:hover {
    background: #34495e; 
    outline: none; 
}
</style>
