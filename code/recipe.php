<?php

require 'vendor/autoload.php';

$dbFile = $_ENV['DB_FILE'];
$debug = isset($_ENV['DEBUG']) && $_ENV['DEBUG'] === 'true';

// Database connection
try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Handle recipe deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_recipe_id"])) {
    $recipeIdToDelete = (int)$_POST["delete_recipe_id"];

    try {
        $pdo->beginTransaction();

        // Get image URL to delete local file
        $stmt = $pdo->prepare("SELECT image_url FROM recipes WHERE id = ?");
        $stmt->execute([$recipeIdToDelete]);
        $recipeToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recipeToDelete && !empty($recipeToDelete['image_url'])) {
            $localImagePath = __DIR__ . '/' . $recipeToDelete['image_url'];
            if (file_exists($localImagePath)) {
                unlink($localImagePath);
                if ($debug) error_log("Deleted local image: " . $localImagePath);
            }
        }

        // Delete ingredients first
        $stmt = $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?");
        $stmt->execute([$recipeIdToDelete]);

        // Delete the recipe
        $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
        $stmt->execute([$recipeIdToDelete]);

        $pdo->commit();
        header("Location: index.php"); // Redirect to home page after deletion
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($debug) error_log("Error deleting recipe: " . $e->getMessage());
        // Optionally, display an error message to the user
    }
}

// Unit conversion function (simplified for demonstration)
function convertUnit($quantity, $unit, $targetSystem, $debug) {
    // This is a very basic conversion and needs to be expanded for real-world use
    // For example, converting cups to ml, grams to ounces, etc.
    // A more robust solution would involve a comprehensive unit conversion library

    $convertedQuantity = $quantity;
    $convertedUnit = $unit;

    if ($targetSystem === 'imperial') {
        switch (strtolower($unit)) {
            case 'grams':
                $convertedQuantity = $quantity / 28.35; // grams to ounces
                $convertedUnit = 'ounces';
                break;
            case 'ml':
                $convertedQuantity = $quantity / 29.5735; // ml to fluid ounces
                $convertedUnit = 'fl oz';
                break;
            case 'liters':
                $convertedQuantity = $quantity * 4.22675; // liters to cups
                $convertedUnit = 'cups';
                break;
            // Add more metric to imperial conversions
        }
    } elseif ($targetSystem === 'metric') {
        switch (strtolower($unit)) {
            case 'ounces':
                $convertedQuantity = $quantity * 28.35; // ounces to grams
                $convertedUnit = 'grams';
                break;
            case 'fl oz':
                $convertedQuantity = $quantity * 29.5735; // fluid ounces to ml
                $convertedUnit = 'ml';
                break;
            case 'cups':
                $convertedQuantity = $quantity / 4.22675; // cups to liters
                $convertedUnit = 'liters';
                break;
            // Add more imperial to metric conversions
        }
    }

    return ['quantity' => round($convertedQuantity, 2), 'unit' => $convertedUnit];
}

$recipe = null;
$ingredients = [];
$displayServings = null;
$displayUnitSystem = null;

if (isset($_GET['id'])) {
    $recipeId = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT id, url, name_nl, name_en, servings, total_time, image_url FROM recipes WHERE id = ?");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recipe) {
        $displayLanguage = isset($_POST["display_language"]) ? $_POST["display_language"] : 'nl'; // Default to Dutch

        $stmtSteps = $pdo->prepare("SELECT description_nl, description_en, time_in_minutes FROM steps WHERE recipe_id = ?");
        $stmtSteps->execute([$recipeId]);
        $steps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);

        $stmtIngredients = $pdo->prepare("SELECT name_nl, name_en, quantity, unit_nl, unit_en FROM ingredients WHERE recipe_id = ?");
        $stmtIngredients->execute([$recipeId]);
        $ingredients = $stmtIngredients->fetchAll(PDO::FETCH_ASSOC);

        $displayServings = $recipe['servings'];
        $displayUnitSystem = 'original';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $displayServings = isset($_POST["servings"]) ? (int)$_POST["servings"] : $recipe['servings'];
            $displayUnitSystem = isset($_POST["unit_system"]) ? $_POST["unit_system"] : 'metric';
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($recipe['name_nl'] ?? 'Recipe'); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if ($recipe): ?>
            <a href="index.php" class="back-link">Back to Recipe List</a>
            <h1>
                <span id="recipe-name-nl"><?php echo htmlspecialchars($recipe['name_nl'] ?? 'Unknown Recipe'); ?></span>
                <span id="recipe-name-en" style="display:none;"><?php echo htmlspecialchars($recipe['name_en'] ?? 'Unknown Recipe'); ?></span>
            </h1>

            <p>Original Recipe: <a href="<?php echo htmlspecialchars($recipe['url']); ?>" target="_blank"><?php echo htmlspecialchars($recipe['url']); ?></a></p>

            <?php if (!empty($recipe['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" alt="<?php echo htmlspecialchars($recipe['name_nl']); ?>" style="max-width: 100%; height: auto;">
            <?php endif; ?>

            <form method="post" class="controls" id="recipe-controls-form">
                <label for="servings">Servings:</label>
                <input type="number" name="servings" id="servings" value="<?php echo htmlspecialchars($displayServings); ?>" min="1">
                <div class="unit-buttons">
                    <input type="hidden" name="unit_system" id="unit_system" value="<?php echo htmlspecialchars($displayUnitSystem); ?>">
                    <button type="button" class="unit-button <?php echo ($displayUnitSystem === 'metric' ? 'active' : ''); ?>" data-unit="metric">Metric</button>
                    <button type="button" class="unit-button <?php echo ($displayUnitSystem === 'imperial' ? 'active' : ''); ?>" data-unit="imperial">Imperial</button>
                </div>
            </form>

            <form method="post" class="controls" id="language-controls-form">
                <input type="hidden" name="display_language" id="display_language" value="<?php echo htmlspecialchars($displayLanguage); ?>">
                <div class="language-buttons">
                    <button type="button" class="language-button <?php echo ($displayLanguage === 'nl' ? 'active' : ''); ?>" data-lang="nl">Dutch</button>
                    <button type="button" class="language-button <?php echo ($displayLanguage === 'en' ? 'active' : ''); ?>" data-lang="en">English</button>
                </div>
            </form>

            <h2 id="ingredients-list-heading">Ingredients:</h2>
            <ul id="ingredients-list">
                <?php foreach ($ingredients as $ingredient): ?>
                    <li>
                        <span class="ingredient-nl">
                            <?php echo htmlspecialchars($ingredient['name_nl']); ?> (<?php echo htmlspecialchars($ingredient['quantity']); ?> <?php echo htmlspecialchars($ingredient['unit_nl']); ?>)
                        </span>
                        <span class="ingredient-en" style="display:none;">
                            <?php echo htmlspecialchars($ingredient['name_en']); ?> (<?php echo htmlspecialchars($ingredient['quantity']); ?> <?php echo htmlspecialchars($ingredient['unit_en']); ?>)
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if (!empty($steps)): ?>
                <h2>Cooking Steps</h2>
                <ol id="steps-list">
                    <?php foreach ($steps as $step): ?>
                        <li>
                            <span class="step-nl">
                                <?php echo htmlspecialchars($step['description_nl']); ?> (<?php echo htmlspecialchars($step['time_in_minutes']); ?> minutes)
                            </span>
                            <span class="step-en" style="display:none;">
                                <?php echo htmlspecialchars($step['description_en']); ?> (<?php echo htmlspecialchars($step['time_in_minutes']); ?> minutes)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

        <?php else: ?>
            <p>Recipe not found.</p>
        <?php endif; ?>

        <form method="post" style="margin-top: 20px;">
            <input type="hidden" name="delete_recipe_id" value="<?php echo htmlspecialchars($recipe['id']); ?>">
            <button type="submit" style="background-color: #dc3545;">Delete Recipe</button>
        </form>

        <a href="index.php" class="back-link">Back to Recipe List</a>
    </div>
    <script>
        const recipeData = {
            ingredients: <?php echo json_encode($ingredients); ?>,
            steps: <?php echo json_encode($steps); ?>,
            servings: <?php echo json_encode($recipe['servings'] ?? 1); ?>
        };
    </script>
    <script src="script.js"></script>
</body>
</html>