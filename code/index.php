<?php

require 'vendor/autoload.php';

use OpenAI\OpenAI;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];
$openaiApiKey = $_ENV['OPENAI_API_KEY'];
$debug = isset($_ENV['DEBUG']) && $_ENV['DEBUG'] === 'true';

// Database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// OpenAI client
$openAIClient = \OpenAI::client($openaiApiKey);


// Function to fetch and parse recipe from URL
function fetchAndParseRecipe($url, $debug) {
    try {
        $browser = new HttpBrowser(HttpClient::create());
        $crawler = $browser->request('GET', $url);
        $htmlContent = $crawler->html();

        $imageUrl = null;
        // Try to extract Open Graph image
        $crawler->filter('meta[property="og:image"]')->each(function (Crawler $node) use (&$imageUrl) {
            $imageUrl = $node->attr('content');
        });

        // If no Open Graph image, try to find a prominent image in the body
        if (!$imageUrl) {
            $crawler->filter('img')->each(function (Crawler $node) use (&$imageUrl) {
                // Basic heuristic: prefer larger images or images within main content
                $src = $node->attr('src');
                if ($src && strpos($src, 'http') === 0) { // Ensure it's an absolute URL
                    // You might add more sophisticated logic here, e.g., check image dimensions
                    $imageUrl = $src;
                    return false; // Stop after finding the first suitable image
                }
            });
        }

        // Try to extract JSON-LD recipe data
        $jsonLdData = [];
        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $node) use (&$jsonLdData) {
            $data = json_decode($node->text(), true);
            if ($data) {
                $jsonLdData[] = $data;
            }
        });

        foreach ($jsonLdData as $data) {
            if (isset($data['@graph'])) {
                foreach ($data['@graph'] as $graphItem) {
                    if (isset($graphItem['@type']) && $graphItem['@type'] === 'Recipe') {
                        $recipeName = $graphItem['name'] ?? null;
                        $servings = null;
                        if (isset($graphItem['recipeYield'])) {
                            if (is_array($graphItem['recipeYield'])) {
                                foreach ($graphItem['recipeYield'] as $yield) {
                                    if (is_string($yield) && preg_match('/^(\d+)/', $yield, $matches)) {
                                        $servings = (int)$matches[1];
                                        break;
                                    }
                                }
                            } else if (is_string($graphItem['recipeYield']) && preg_match('/^(\d+)/', $graphItem['recipeYield'], $matches)) {
                                $servings = (int)$matches[1];
                            }
                        }
                        $ingredients = $graphItem['recipeIngredient'] ?? [];

                        if ($recipeName && $servings && !empty($ingredients)) {
                            $recipeText = "Recipe Name: " . $recipeName . "\n";
                            $recipeText .= "Servings: " . $servings . "\n";
                            $recipeText .= "Ingredients:\n";
                            foreach ($ingredients as $ingredient) {
                                $recipeText .= "- " . $ingredient . "\n";
                            }
                            return ['recipeText' => $recipeText, 'imageUrl' => $imageUrl];
                        }
                    }
                }
            } else if (isset($data['@type']) && $data['@type'] === 'Recipe') {
                $recipeName = $data['name'] ?? null;
                $servings = null;
                if (isset($data['recipeYield'])) {
                    if (is_array($data['recipeYield'])) {
                        foreach ($data['recipeYield'] as $yield) {
                            if (is_string($yield) && preg_match('/^(\d+)/', $yield, $matches)) {
                                $servings = (int)$matches[1];
                                break;
                            }
                        }
                    } else if (is_string($data['recipeYield']) && preg_match('/^(\d+)/', $data['recipeYield'], $matches)) {
                        $servings = (int)$matches[1];
                    }
                }
                $ingredients = $data['recipeIngredient'] ?? [];

                if ($recipeName && $servings && !empty($ingredients)) {
                    $recipeText = "Recipe Name: " . $recipeName . "\n";
                    $recipeText .= "Servings: " . $servings . "\n";
                    $recipeText .= "Ingredients:\n";
                    foreach ($ingredients as $ingredient) {
                        $recipeText .= "- " . $ingredient . "\n";
                    }
                    return ['recipeText' => $recipeText, 'imageUrl' => $imageUrl];
                }
            }
        }

        // Fallback: Extract visible text content if no structured data is found
        return ['recipeText' => $crawler->filter('body')->text(), 'imageUrl' => $imageUrl];

    } catch (Exception $e) {
        if ($debug) error_log("Error fetching or parsing URL: " . $e->getMessage());
        return null;
    }
}

// Function to interpret recipe with OpenAI
function interpretRecipeWithOpenAI($recipeText, $openAIClient, $debug) {
    try {
        $response = $openAIClient->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant that extracts recipe information. Extract the recipe name, servings, a list of ingredients with quantity and unit, the cooking steps with timings, and the *actual* URL for an image of the dish from the provided text. For each ingredient, provide the quantity as a number (decimal if necessary) and the unit as a string (e.g., "grams", "cups", "teaspoons", "ml", "pieces"). If a unit is not explicitly stated, infer the most common unit for that ingredient. If a quantity is not specified, infer a reasonable amount (e.g., 1 for a pinch of salt). For each cooking step, provide a description and an estimated time in minutes. Format the output as a JSON object with the following keys: "name" (string), "servings" (integer), "ingredients" (an array of objects, each with "name" (string), "quantity" (float), "unit" (string)), "steps" (an array of objects, each with "description" (string) and "time" (integer)), and "image_url" (string). If no image URL is found, return an empty string for "image_url".'],
                ['role' => 'user', 'content' => $recipeText,],
            ],
        ])->choices[0]->message->content;

        if ($debug) error_log("Raw OpenAI response: " . $response);
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($debug) error_log("JSON decoding error: " . json_last_error_msg());
            if ($debug) error_log("Raw OpenAI response (after json_decode failure): " . $response);
            return null;
        }
        if ($debug) error_log("Decoded OpenAI response: " . print_r($decodedResponse, true));

        // Validate the structure of the decoded response
        if (!isset($decodedResponse['name']) || !isset($decodedResponse['servings']) || !isset($decodedResponse['ingredients']) || !is_array($decodedResponse['ingredients'])) {
            error_log("OpenAI response missing required keys or ingredients is not an array: " . print_r($decodedResponse, true));
            return null;
        }

        return $decodedResponse;

    } catch (Exception $e) {
        if ($debug) error_log("OpenAI API error: " . $e->getMessage());
        return null;
    }
}

// Function to save recipe to database
function saveRecipeToDatabase($pdo, $recipeData, $url, $debug) {
    try {
        $pdo->beginTransaction();

        // Download image and save locally
        $localImagePath = null;
        if (!empty($recipeData['image_url']) && filter_var($recipeData['image_url'], FILTER_VALIDATE_URL)) {
            $imageUrl = $recipeData['image_url'];
            $imageContents = @file_get_contents($imageUrl);
            if ($imageContents !== false) {
                $imageName = basename(parse_url($imageUrl, PHP_URL_PATH));
                if (empty($imageName) || !preg_match('/\.(jpg|jpeg|png|gif)$/i', $imageName)) {
                    $imageName = uniqid() . '.jpg'; // Fallback to a unique name if no valid extension
                }
                $localImagePath = 'images/' . $imageName;
                file_put_contents(__DIR__ . '/' . $localImagePath, $imageContents);
            } else {
                if ($debug) error_log("Failed to download image from: " . $imageUrl);
            }
        }

        // Check if recipe already exists
        $stmt = $pdo->prepare("SELECT id FROM recipes WHERE url = ?");
        $stmt->execute([$url]);
        $existingRecipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingRecipe) {
            // Update existing recipe
            $recipeId = $existingRecipe['id'];
            if ($debug) error_log("Updating recipe ID: " . $recipeId . ", Name: " . $recipeData['name'] . ", Servings: " . $recipeData['servings']);
            $stmt = $pdo->prepare("UPDATE recipes SET name = ?, servings = ?, steps = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$recipeData['name'], $recipeData['servings'], json_encode($recipeData['steps']), $localImagePath, $recipeId]);

            // Delete old ingredients and insert new ones
            $stmt = $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?");
            $stmt->execute([$recipeId]);
        } else {
            // Insert new recipe
            if ($debug) error_log("Inserting new recipe. Name: " . $recipeData['name'] . ", Servings: " . $recipeData['servings']);
            $stmt = $pdo->prepare("INSERT INTO recipes (url, name, servings, steps, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$url, $recipeData['name'], $recipeData['servings'], json_encode($recipeData['steps']), $localImagePath]);
            $recipeId = $pdo->lastInsertId();
        }

        // Insert ingredients
        $stmt = $pdo->prepare("INSERT INTO ingredients (recipe_id, name, quantity, unit, original_unit) VALUES (?, ?, ?, ?, ?)");
        foreach ($recipeData['ingredients'] as $ingredient) {
            $stmt->execute([$recipeId, $ingredient['name'], $ingredient['quantity'], $ingredient['unit'], $ingredient['unit']]);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

// Handle form submission for recipe processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["recipe_url"])) {
    if ($debug) error_log("POST request received for recipe URL: " . $_POST["recipe_url"]);
    $recipeUrl = $_POST["recipe_url"];

    // Validate the URL
    if (filter_var($recipeUrl, FILTER_VALIDATE_URL)) {
        $parsedRecipe = fetchAndParseRecipe($recipeUrl, $debug);
        if ($parsedRecipe) {
            $recipeText = $parsedRecipe['recipeText'];
            $extractedImageUrl = $parsedRecipe['imageUrl'];
            if ($debug) error_log("Recipe Text sent to OpenAI: " . $recipeText);
            $recipeData = interpretRecipeWithOpenAI($recipeText, $openAIClient, $debug);

            if ($recipeData) {
                // Override OpenAI's image_url with the directly extracted one if available
                if (!empty($extractedImageUrl)) {
                    $recipeData['image_url'] = $extractedImageUrl;
                }

                if (saveRecipeToDatabase($pdo, $recipeData, $recipeUrl, $debug)) {
                    echo "<p>Recipe processed and saved successfully!</p>";
                } else {
                    echo "<p>Error saving recipe to database.</p>";
                }
            } else {
                echo "<p>Error interpreting recipe with OpenAI.</p>";
            }
        }
        else {
            echo "<p>Error fetching recipe from URL.</p>";
        }
    } else {
        echo "<p>Invalid URL.</p>\n";
    }
}

// Handle form submission for recipe display/conversion
$displayServings = null;
$displayUnitSystem = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["recipe_id"])) {
    $displayServings = isset($_POST["servings"]) ? (int)$_POST["servings"] : null;
    $displayUnitSystem = isset($_POST["unit_system"]) ? $_POST["unit_system"] : null;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Recipe Processor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        form { margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; }
        form label { margin-right: 10px; }
        input[type="url"], input[type="number"], select { flex-grow: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-right: 10px; margin-bottom: 10px; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px; }
        button:hover { background-color: #0056b3; }
        .recipe-list { margin-top: 30px; }
        .recipe-item { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 10px; }
        .recipe-item h3 { margin-top: 0; color: #0056b3; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 5px; }
        .controls { display: flex; align-items: center; margin-top: 10px; }
        .controls label { margin-right: 5px; }
        .controls input, .controls select { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Recipe Processor</h1>
        <form method="post">
            <label for="recipe_url">Recipe URL:</label>
            <input type="url" name="recipe_url" id="recipe_url" required>
            <button type="submit">Process Recipe</button>
        </form>

        <hr>

        <h2>Saved Recipes</h2>
        <div class="recipe-list">
            <?php
            $stmt = $pdo->query("SELECT id, name, servings FROM recipes ORDER BY created_at DESC");
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Recipes from DB: " . print_r($recipes, true));

            if (count($recipes) > 0) {
                foreach ($recipes as $recipe) {
                    echo "<div class='recipe-item'>";
                    echo "<h3><a href=\"recipe.php?id=" . htmlspecialchars($recipe['id']) . "\">" . htmlspecialchars($recipe['name'] ?? 'Unknown Recipe') . "</a></h3>";
                    echo "</div>";
                }
            } else {
                echo "<p>No recipes saved yet.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>
