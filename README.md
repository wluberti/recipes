# Recipe Processor

This project is a web application that allows users to extract, store, and display food recipes from various websites. It leverages OpenAI for intelligent recipe analysis and provides features for adjusting serving sizes and converting ingredient units.

## Features

-   **Recipe Extraction:** Input a URL of a recipe website, and the application will extract key details like recipe name, servings, and ingredients.
-   **AI-Powered Analysis:** Utilizes OpenAI to interpret and structure the extracted recipe data.
-   **Database Storage:** Stores processed recipes and their ingredients in a MariaDB database.
-   **Adjustable Servings:** Dynamically adjust ingredient quantities based on desired serving sizes.
-   **Unit Conversion:** Convert ingredient measurements between imperial and metric units.
-   **Dedicated Recipe Pages:** Each saved recipe has its own page with a link back to the original source.
-   **Dockerized Environment:** The entire application runs locally within Docker containers for easy setup and portability.

## Technologies Used

-   **Backend:** PHP (with Symfony components for web scraping and Dotenv for environment management)
-   **Frontend:** HTML, CSS (basic styling)
-   **Database:** MariaDB
-   **Web Server:** Nginx
-   **Containerization:** Docker, Docker Compose
-   **AI Integration:** OpenAI API (GPT-3.5 Turbo)

## Setup Instructions

To get the application up and running, follow these steps:

1.  **Clone the repository:**
    ```bash
    git clone <repository_url>
    cd recipe
    ```

2.  **Configure Environment Variables:**
    Copy the `.env.example` file to `.env` and fill in your database credentials and OpenAI API key.
    ```bash
    cp .env.example .env
    ```
    Open `.env` in your text editor and update the following:
    ```
    DB_HOST=db
    DB_NAME=recipe_db
    DB_USER=recipe_user
    DB_PASS=recipe_password
    OPENAI_API_KEY=your_openai_api_key_here
    ```
    **Important:** Replace `your_openai_api_key_here` with your actual OpenAI API key. Ensure your OpenAI account has sufficient quota and permissions for `gpt-3.5-turbo` model requests.

    ℹ ⚡ Slow response times detected. Automatically switching from gemini-2.5-pro to gemini-2.5-flash for faster responses for the remainder of
  this session.
  ⚡ To avoid this you can utilize a Gemini API Key. See: https://goo.gle/gemini-cli-docs-auth#gemini-api-key
  ⚡ You can switch authentication methods by typing /auth

3.  **Build and Run Docker Containers:**
    Navigate to the project root directory (where `docker-compose.yaml` is located) and run:
    ```bash
    docker-compose up --build -d
    ```
    This command will:
    -   Build the PHP image (installing necessary extensions like `pdo_mysql`).
    -   Start the Nginx, PHP, MariaDB, and Adminer containers.
    -   Initialize the database schema using `init.sql`.

4.  **Install PHP Dependencies:**
    Once the containers are running, install the PHP Composer dependencies inside the `php` container:
    ```bash
    docker-compose exec -w /code php composer update
    ```

## Usage

1.  **Access the Application:**
    Open your web browser and go to `http://localhost:8080`.

2.  **Process a Recipe:**
    Enter the URL of a recipe website into the input field and click "Process Recipe". The application will fetch, analyze, and save the recipe to the database.

3.  **View Saved Recipes:**
    The main page will list all saved recipes. Click on a recipe's name to view its dedicated page.

4.  **Adjust Servings and Units:**
    On the individual recipe page, you can adjust the number of servings and switch between metric and imperial units for ingredients.

## Troubleshooting

-   **"Could not find driver" error:** Ensure the `pdo_mysql` extension is correctly installed in your PHP container. Rebuild your Docker containers (`docker-compose up --build -d`).
-   **"OpenAI API error: You exceeded your current quota" or "Insufficient permissions":** Check your OpenAI account dashboard for billing details, usage limits, and API key permissions.
-   **"Missing required parameter: 'messages[1].role'":** This indicates an issue with the OpenAI API call structure. Ensure the `role` is correctly specified for all messages.
-   **"Error interpreting recipe with OpenAI" or empty recipe data:** This often means the web scraping failed to get useful content, or OpenAI couldn't extract the information. Check the `docker-compose logs php` for more details, especially the "Recipe Text sent to OpenAI" and "Raw OpenAI response" messages.
-   **Recipes not saving or displaying correctly:** Check `docker-compose logs php` for database errors (e.g., "Duplicate entry"). Ensure the `name` and `servings` fields are populated in the database.
-   **Form submissions not working (no POST logs):** Verify your Nginx configuration (`default.conf`) and ensure it's correctly routing PHP requests to the `php-fpm` service. Restart Nginx if you make changes.

If you encounter persistent issues, please provide the full output of `docker-compose logs php` and a description of the steps you took.