<?php

require 'vendor/autoload.php';

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];
$debug = isset($_ENV['DEBUG']) && $_ENV['DEBUG'] === 'true';

// Database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
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

    $stmt = $pdo->prepare("SELECT id, url, name, servings, steps, image_url, original_language, original_unit_system FROM recipes WHERE id = ?");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recipe) {
        $stmtIngredients = $pdo->prepare("SELECT name, quantity, unit, original_unit FROM ingredients WHERE recipe_id = ?");
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
    <title><?php echo htmlspecialchars($recipe['name'] ?? 'Recipe'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .controls { display: flex; align-items: center; margin-top: 10px; margin-bottom: 20px; }
        .controls label { margin-right: 10px; }
        .controls input, .controls select { margin-right: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .unit-buttons { display: flex; gap: 5px; margin-left: 10px; }
        .unit-button { background-color: #e9ecef; color: #333; border: 1px solid #ddd; }
        .unit-button.active { background-color: #007bff; color: white; border-color: #007bff; }
        .language-buttons { display: flex; gap: 5px; margin-bottom: 10px; }
        .language-button { padding: 8px 15px; background-color: #e9ecef; color: #333; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
        .language-button.active { background-color: #28a745; color: white; border-color: #28a745; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 5px; }
        .back-link { display: block; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($recipe): ?>
            <a href="index.php" class="back-link">Back to Recipe List</a>
            <h1><?php echo htmlspecialchars($recipe['name'] ?? 'Unknown Recipe'); ?></h1>
            <p>Original Recipe: <a href="<?php echo htmlspecialchars($recipe['url']); ?>" target="_blank"><?php echo htmlspecialchars($recipe['url']); ?></a></p>

            <?php if (!empty($recipe['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" style="max-width: 100%; height: auto;">
            <?php endif; ?>

            <form method="post" class="controls" id="recipe-controls-form">
                <label for="servings">Servings:</label>
                <input type="number" name="servings" id="servings" value="<?php echo htmlspecialchars($displayServings); ?>" min="1">
                <div class="unit-buttons">
                    <input type="hidden" name="unit_system" id="unit_system" value="<?php echo htmlspecialchars($displayUnitSystem); ?>">
                    <button type="button" class="unit-button <?php echo ($displayUnitSystem === 'original' ? 'active' : ''); ?>" data-unit="original">Original</button>
                    <button type="button" class="unit-button <?php echo ($displayUnitSystem === 'metric' ? 'active' : ''); ?>" data-unit="metric">Metric</button>
                    <button type="button" class="unit-button <?php echo ($displayUnitSystem === 'imperial' ? 'active' : ''); ?>" data-unit="imperial">Imperial</button>
                </div>
            </form>

            <h2 id="ingredients-list-heading">Ingredients:</h2>
            <ul id="ingredients-list">
            </ul>

            <?php if (!empty($recipe['steps'])): ?>
                <h2>Cooking Steps</h2>
                <ol>
                    <?php foreach (json_decode($recipe['steps'], true) as $step): ?>
                        <li><?php echo htmlspecialchars($step['description']); ?> (<?php echo htmlspecialchars($step['time']); ?> minutes)</li>
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
        document.addEventListener('DOMContentLoaded', function() {
            const servingsInput = document.getElementById('servings');
            const unitButtons = document.querySelectorAll('.unit-button');
            const unitSystemInput = document.getElementById('unit_system');
            const ingredientsList = document.querySelector('#ingredients-list'); // Assuming you add an ID to your <ul> for ingredients

            // Store original ingredients data from PHP for calculations
            const originalIngredients = <?php echo json_encode($ingredients); ?>;
            const originalServings = <?php echo json_encode($recipe['servings']); ?>;
            const originalLanguage = <?php echo json_encode($recipe['original_language']); ?>;
            const originalUnitSystem = <?php echo json_encode($recipe['original_unit_system']); ?>;

            // Set initial unit system based on original language or stored preference
            let currentUnitSystem = unitSystemInput.value; // Get current value from hidden input
            if (currentUnitSystem === 'original') { // If it's still 'original', set based on language
                if (originalLanguage === 'nl') {
                    currentUnitSystem = 'metric';
                } else {
                    currentUnitSystem = 'imperial';
                }
                unitSystemInput.value = currentUnitSystem;
            }

            function updateIngredients() {
                const currentServings = parseFloat(servingsInput.value);
                const currentUnitSystem = unitSystemInput.value;

                let updatedHtml = '';
                originalIngredients.forEach(ingredient => {
                    let quantity = parseFloat(ingredient.quantity); // Ensure quantity is a number
                    let unit = ingredient.unit;

                    // Adjust quantity based on servings
                    if (currentServings !== originalServings) {
                        quantity = (quantity / originalServings) * currentServings;
                    }

                    // Convert units if requested (replicate PHP logic in JS)
                    if (currentUnitSystem !== 'original') {
                        const converted = convertUnitJs(quantity, ingredient.original_unit, currentUnitSystem);
                        quantity = converted.quantity;
                        unit = converted.unit;
                    }

                    updatedHtml += `<li>${quantity.toFixed(2)} ${unit} ${ingredient.name}</li>`;
                });
                ingredientsList.innerHTML = updatedHtml;
            }

            // JavaScript equivalent of PHP's convertUnit function
            function convertUnitJs(quantity, unit, targetSystem) {
                let convertedQuantity = quantity;
                let convertedUnit = unit;

                if (targetSystem === 'imperial') {
                    switch (unit.toLowerCase()) {
                        case 'grams':
                            convertedQuantity = quantity / 28.35; // grams to ounces
                            convertedUnit = 'ounces';
                            break;
                        case 'ml':
                            convertedQuantity = quantity / 29.5735; // ml to fluid ounces
                            convertedUnit = 'fl oz';
                            break;
                        case 'liters':
                            convertedQuantity = quantity * 4.22675; // liters to cups
                            convertedUnit = 'cups';
                            break;
                        // Add more metric to imperial conversions as needed
                    }
                } else if (targetSystem === 'metric') {
                    switch (unit.toLowerCase()) {
                        case 'ounces':
                            convertedQuantity = quantity * 28.35; // ounces to grams
                            convertedUnit = 'grams';
                            break;
                        case 'fl oz':
                            convertedQuantity = quantity * 29.5735; // fluid ounces to ml
                            convertedUnit = 'ml';
                            break;
                        case 'cups':
                            convertedQuantity = quantity / 4.22675; // cups to liters
                            convertedUnit = 'liters';
                            break;
                        // Add more imperial to metric conversions as needed
                    }
                }
                return { quantity: convertedQuantity, unit: convertedUnit };
            }

            // Event Listeners
            servingsInput.addEventListener('input', updateIngredients);

            unitButtons.forEach(button => {
                button.addEventListener('click', function() {
                    unitButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    unitSystemInput.value = this.dataset.unit;
                    updateIngredients();
                });
            });

            const languageButtons = document.querySelectorAll('.language-button');
            const displayLanguageInput = document.getElementById('display_language');

            languageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    languageButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    displayLanguageInput.value = this.dataset.lang;

                    // Set unit system based on selected language
                    if (this.dataset.lang === 'nl') {
                        unitSystemInput.value = 'metric';
                    } else if (this.dataset.lang === 'en') {
                        unitSystemInput.value = 'imperial';
                    } else {
                        unitSystemInput.value = originalUnitSystem; // Fallback to original if neither NL nor EN
                    }
                    // Update active unit button
                    unitButtons.forEach(btn => {
                        if (btn.dataset.unit === unitSystemInput.value) {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                    updateIngredients();
                });
            });

            // Initial update on page load
            updateIngredients();
        });
    </script>
</body>
</html>