<?php
use OpenAI\OpenAI;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$openaiApiKey = $_ENV['OPENAI_API_KEY'];
$openAIClient = \OpenAI::client($openaiApiKey);
$response = $openAIClient->models()->list();

foreach ($response->data as $result) {
    printf("Owner: {$result->ownedBy} - Model: {$result->id}<br>");
}
