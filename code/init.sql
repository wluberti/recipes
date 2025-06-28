CREATE TABLE IF NOT EXISTS recipes (
    id INTEGER PRIMARY KEY,
    url VARCHAR(255) NOT NULL UNIQUE,
    name_nl VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    servings INT NOT NULL,
    total_time INT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS steps (
    id INTEGER PRIMARY KEY,
    recipe_id INT,
    description_nl TEXT,
    description_en TEXT,
    time_in_minutes INT,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ingredients (
    id INTEGER PRIMARY KEY,
    recipe_id INT,
    language VARCHAR(10) DEFAULT 'en',
    name_nl VARCHAR(255),
    name_en VARCHAR(255),
    quantity DECIMAL(10, 3),
    unit_nl VARCHAR(50), -- e.g., "gram", "ml", "eetlepel", "theelepel"
    unit_en VARCHAR(50), -- e.g., "grams", "Fl. oz.", "cups", "teaspoons"
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);
