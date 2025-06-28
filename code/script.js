document.addEventListener('DOMContentLoaded', function() {
    const servingsInput = document.getElementById('servings');
    const unitButtons = document.querySelectorAll('.unit-button');
    const unitSystemInput = document.getElementById('unit_system');
    const ingredientsList = document.querySelector('#ingredients-list'); // Assuming you add an ID to your <ul> for ingredients

    // Store original ingredients data from PHP for calculations
    const originalIngredients = recipeData.ingredients;
    const originalServings = recipeData.servings;

    // Set initial unit system based on stored preference
    let currentUnitSystem = unitSystemInput.value; // Get current value from hidden input
    if (currentUnitSystem === 'original') { // If it's still 'original', set based on default to metric
        currentUnitSystem = 'metric';
        unitSystemInput.value = currentUnitSystem;
    }

    function updateIngredients() {
        const currentServings = parseFloat(servingsInput.value);
        const currentUnitSystem = unitSystemInput.value;
        const displayLanguage = displayLanguageInput.value;

        let updatedHtml = '';
        originalIngredients.forEach(ingredient => {
            let quantity = parseFloat(ingredient.quantity); // Ensure quantity is a number
            let unit = (displayLanguage === 'en') ? ingredient.unit_en : ingredient.unit_nl;
            let name = (displayLanguage === 'en') ? ingredient.name_en : ingredient.name_nl;

            // Adjust quantity based on servings
            if (currentServings !== originalServings) {
                quantity = (quantity / originalServings) * currentServings;
            }

            // Convert units if requested (replicate PHP logic in JS)
            if (currentUnitSystem !== 'original') {
                const converted = convertUnitJs(quantity, (displayLanguage === 'en') ? ingredient.unit_en : ingredient.unit_nl, currentUnitSystem);
                quantity = converted.quantity;
                unit = converted.unit;
            }

            updatedHtml += `<li>${quantity.toFixed(2)} ${unit} ${name}</li>`;
        });
        ingredientsList.innerHTML = updatedHtml;
    }

    function updateSteps() {
        const displayLanguage = displayLanguageInput.value;
        const stepsList = document.querySelector('#steps-list'); // Assuming you add an ID to your <ol> for steps
        const originalSteps = recipeData.steps; // Assuming steps are passed in recipeData

        let updatedHtml = '';
        if (originalSteps) {
            originalSteps.forEach(step => {
                let description = (displayLanguage === 'en') ? step.description_en : step.description_nl;
                updatedHtml += `<li>${description} (${step.time_in_minutes} minutes)</li>`;
            });
        }
        if (stepsList) {
            stepsList.innerHTML = updatedHtml;
        }
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
    servingsInput.addEventListener('input', function() {
        updateIngredients();
        updateSteps();
    });

    unitButtons.forEach(button => {
        button.addEventListener('click', function() {
            unitButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            unitSystemInput.value = this.dataset.unit;
            updateIngredients();
            updateSteps();
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
                unitSystemInput.value = 'metric'; // Fallback to metric if neither NL nor EN
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
            updateSteps();
            // Update recipe name display
            document.getElementById('recipe-name-nl').style.display = (displayLanguageInput.value === 'nl') ? 'inline' : 'none';
            document.getElementById('recipe-name-en').style.display = (displayLanguageInput.value === 'en') ? 'inline' : 'none';
        });
    });

    // Initial update on page load
    updateIngredients();
    updateSteps();

    // Initial display of recipe name based on default language
    document.getElementById('recipe-name-nl').style.display = (displayLanguageInput.value === 'nl') ? 'inline' : 'none';
    document.getElementById('recipe-name-en').style.display = (displayLanguageInput.value === 'en') ? 'inline' : 'none';
});