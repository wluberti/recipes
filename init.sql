CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    servings INT NOT NULL,
    steps JSON,
    image_url VARCHAR(255),
    original_language VARCHAR(10) DEFAULT 'en',
    original_unit_system VARCHAR(10) DEFAULT 'metric',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT,
    name VARCHAR(255),
    quantity DECIMAL(10, 3),
    unit VARCHAR(50), -- e.g., "grams", "cups", "teaspoons"
    original_unit VARCHAR(50), -- Added for storing the original unit
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);
