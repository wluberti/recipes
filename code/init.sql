CREATE TABLE IF NOT EXISTS recipes (
    id INTEGER PRIMARY KEY,
    url VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    servings INT NOT NULL,
    total_time INT,
    image_url VARCHAR(255),
    original_language VARCHAR(10) DEFAULT 'en',
    original_unit_system VARCHAR(10) DEFAULT 'metric',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS steps (
    id INTEGER PRIMARY KEY,
    recipe_id INT,
    language VARCHAR(10) DEFAULT 'en',
    description TEXT,
    time_in_minutes INT,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ingredients (
    id INTEGER PRIMARY KEY,
    recipe_id INT,
    language VARCHAR(10) DEFAULT 'en',
    name VARCHAR(255),
    quantity DECIMAL(10, 3),
    unit VARCHAR(50), -- e.g., "grams", "cups", "teaspoons"
    original_unit VARCHAR(50), -- Added for storing the original unit
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);
