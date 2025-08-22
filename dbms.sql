-- Drop tables if they exist (for easy re-creation)
DROP TABLE IF EXISTS job_progress;
DROP TABLE IF EXISTS bills;
DROP TABLE IF EXISTS delivery_slips;
DROP TABLE IF EXISTS quotations;
DROP TABLE IF EXISTS quotation_items;
DROP TABLE IF EXISTS delivery_slip_items;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS taxes;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS report_items;

-- Clients Table
CREATE TABLE clients (
    client_id INT PRIMARY KEY AUTO_INCREMENT,
    client_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone_number VARCHAR(20),
    address TEXT,
    city VARCHAR(255),
    state VARCHAR(255),
    zip_code VARCHAR(10),
    tax_id VARCHAR(20), -- For tax purposes
    notes TEXT
);

-- Items Table
CREATE TABLE items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    unit_price DECIMAL(10, 2) NOT NULL,
    unit_of_measure VARCHAR(50), -- e.g., "piece", "kg", "meter"
    stock_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 0,
    supplier VARCHAR(255),
    sku VARCHAR(50) UNIQUE, -- Stock Keeping Unit
    category VARCHAR(100),
    notes TEXT
);

-- Users Table (for access control)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- HASHED!  NEVER store plain text passwords
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    role VARCHAR(50) DEFAULT 'user', -- e.g., 'admin', 'manager', 'user'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Taxes Table
CREATE TABLE taxes (
    tax_id INT PRIMARY KEY AUTO_INCREMENT,
    tax_name VARCHAR(255) NOT NULL,
    tax_rate DECIMAL(5, 2) NOT NULL, -- e.g., 0.07 for 7%
    description TEXT
);

-- Quotations Table
CREATE TABLE quotations (
    quotation_id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT,
    quotation_date DATE NOT NULL,
    expiry_date DATE,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(5, 2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'pending', -- e.g., 'pending', 'accepted', 'rejected'
    notes TEXT,
    user_id INT, -- User who created the quotation
    FOREIGN KEY (client_id) REFERENCES clients(client_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Quotation Items (Many-to-Many relationship between Quotations and Items)
CREATE TABLE quotation_items (
    quotation_item_id INT PRIMARY KEY AUTO_INCREMENT,
    quotation_id INT,
    item_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_id INT, -- Apply tax to this item
    FOREIGN KEY (quotation_id) REFERENCES quotations(quotation_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (tax_id) REFERENCES taxes(tax_id)
);

-- Delivery Slips Table
CREATE TABLE delivery_slips (
    delivery_slip_id INT PRIMARY KEY AUTO_INCREMENT,
    quotation_id INT,
    delivery_date DATE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- e.g., 'pending', 'shipped', 'delivered'
    notes TEXT,
    FOREIGN KEY (quotation_id) REFERENCES quotations(quotation_id)
);

-- Delivery Slip Items (Many-to-Many relationship between Delivery Slips and Items)
CREATE TABLE delivery_slip_items (
    delivery_slip_item_id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_slip_id INT,
    item_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (delivery_slip_id) REFERENCES delivery_slips(delivery_slip_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id)
);

-- Bills Table
CREATE TABLE bills (
    bill_id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_slip_id INT,
    bill_date DATE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status VARCHAR(50) DEFAULT 'unpaid', -- e.g., 'unpaid', 'paid', 'partial'
    payment_date DATE,
    notes TEXT,
    FOREIGN KEY (delivery_slip_id) REFERENCES delivery_slips(delivery_slip_id)
);

-- Jobs Table
CREATE TABLE jobs (
    job_id INT PRIMARY KEY AUTO_INCREMENT,
    job_name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    status VARCHAR(50) DEFAULT 'open', -- e.g., 'open', 'in progress', 'completed', 'cancelled'
    client_id INT,
    notes TEXT,
    FOREIGN KEY (client_id) REFERENCES clients(client_id)
);

-- Job Progress Table
CREATE TABLE job_progress (
    job_progress_id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT,
    quotation_id INT,
    delivery_slip_id INT,
    bill_id INT,
    progress_date DATE NOT NULL,
    description TEXT,
    status VARCHAR(50),
    FOREIGN KEY (job_id) REFERENCES jobs(job_id),
    FOREIGN KEY (quotation_id) REFERENCES quotations(quotation_id),
    FOREIGN KEY (delivery_slip_id) REFERENCES delivery_slips(delivery_slip_id),
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id)
);

-- Reports Table
CREATE TABLE reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    report_name VARCHAR(255) NOT NULL,
    report_date DATE NOT NULL,
    description TEXT,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Report Items Table (linking reports to items/quotations/bills)
CREATE TABLE report_items (
    report_item_id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT,
    item_id INT,
    quotation_id INT,
    bill_id INT,
    quantity INT,
    unit_price DECIMAL(10, 2),
    total_amount DECIMAL(10, 2),
    FOREIGN KEY (report_id) REFERENCES reports(report_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (quotation_id) REFERENCES quotations(quotation_id),
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id)
);

-- No changes needed for login/logout/registration functions.