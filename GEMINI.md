# Gemini Customization

This file provides instructions and context for Gemini to better understand and interact with this project.

## General Guidelines

- **Project Purpose:** This project is a simple recipe translation application.
- **Preferred Language:** PHP for backend, HTML/CSS/JavaScript for frontend.
- **Database:** SQLite, managed via `init.sql` and `init_db.php`.
- **Development Environment:** Docker-based.

## Specific Instructions

- When asked to (re)run the application, use `docker compose up -d --force-recreate --remove-orphans`.
- When asked to stop the application, use `docker-compose down`.
- When making changes to the database, ensure `init.sql` is updated and the Docker containers are rebuilt.
