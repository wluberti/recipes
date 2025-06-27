<?php

require 'vendor/autoload.php';

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPass = $_ENV['DB_PASS'];

// Database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Unit conversion function (simplified for demonstration)
function convertUnit($quantity, $unit, $targetSystem) {
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

    $stmt = $pdo->prepare("SELECT id, url, name, servings FROM recipes WHERE id = ?");
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
            $displayUnitSystem = isset($_POST["unit_system"]) ? $_POST["unit_system"] : 'original';
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
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 5px; }
        .back-link { display: block; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($recipe): ?>
            <h1><?php echo htmlspecialchars($recipe['name'] ?? 'Unknown Recipe'); ?></h1>
            <p>Original Recipe: <a href="<?php echo htmlspecialchars($recipe['url']); ?>" target="_blank"><?php echo htmlspecialchars($recipe['url']); ?></a></p>
            <p>Original Servings: <?php echo htmlspecialchars($recipe['servings'] ?? ''); ?></p>

            <form method="post" class="controls">
                <label for="servings">Servings:</label>
                <input type="number" name="servings" id="servings" value="<?php echo htmlspecialchars($displayServings); ?>" min="1">
                <label for="unit_system">Units:</label>
                <select name="unit_system" id="unit_system">
                    <option value="original"<?php echo ($displayUnitSystem === 'original' ? ' selected' : ''); ?>>Original</option>
                    <option value="metric"<?php echo ($displayUnitSystem === 'metric' ? ' selected' : ''); ?>>Metric</option>
                    <option value="imperial"<?php echo ($displayUnitSystem === 'imperial' ? ' selected' : ''); ?>>Imperial</option>
                </select>
                <button type="submit">Adjust</button>
            </form>

            <h2>Ingredients:</h2>
            <ul>
                <?php
                $currentServings = $displayServings;
                foreach ($ingredients as $ingredient) {
                    $quantity = $ingredient['quantity'];
                    $unit = $ingredient['unit'];

                    // Adjust quantity based on servings
                    if ($currentServings !== $recipe['servings']) {
                        $quantity = ($quantity / $recipe['servings']) * $currentServings;
                    }

                    // Convert units if requested
                    if ($displayUnitSystem !== 'original') {
                        $converted = convertUnit($quantity, $ingredient['original_unit'], $displayUnitSystem);
                        $quantity = $converted['quantity'];
                        $unit = $converted['unit'];
                    }

                    echo "<li>" . htmlspecialchars(round($quantity, 2)) . " " . htmlspecialchars($unit) . " " . htmlspecialchars($ingredient['name']) . "</li>";
                }
                ?>
            </ul>
        <?php else: ?>
            <p>Recipe not found.</p>
        <?php endif; ?>

        <a href="index.php" class="back-link">Back to Recipe List</a>
    </div>
</body>
</html>