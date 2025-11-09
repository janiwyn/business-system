-- Table to store tills
CREATE TABLE IF NOT EXISTS tills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creation_date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    FOREIGN KEY (branch_id) REFERENCES branch(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table to store sales for each till
CREATE TABLE IF NOT EXISTS till_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    till_id INT NOT NULL,
    sale_date DATE NOT NULL,
    total_sales DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (till_id) REFERENCES tills(id) ON DELETE CASCADE
);
