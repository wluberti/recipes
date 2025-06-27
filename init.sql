CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    servings INT,
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

-- Example data (optional, for testing)
-- INSERT INTO recipes (url, name, servings) VALUES ('https://example.com/recipe', 'Test Recipe', 4);
-- INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES (1, 'Flour', 250, 'grams');
-- INSERT INTO ingredients (recipe_id, name, quantity, unit) VALUES (1, 'Sugar', 100, 'grams');