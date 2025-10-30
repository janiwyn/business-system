CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    supplier_id INT NOT NULL,
    `branch-id` INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    product INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    `spent-by` INT NOT NULL,
    description TEXT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (`branch-id`) REFERENCES branch(id) ON DELETE CASCADE,
    FOREIGN KEY (product) REFERENCES supplier_products(id) ON DELETE CASCADE,
    FOREIGN KEY (`spent-by`) REFERENCES users(id) ON DELETE CASCADE
);
