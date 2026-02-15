<?php
require_once 'google-api-php-client--PHP8.1/vendor/autoload.php';
session_start();

$client = new Google_Client();

$client->setClientId('1086248183931-k4h6028vqbll3nkel7uq2hepb909494t.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('https://svmehendi.in/google-callback.php');

$client->addScope("email");
$client->addScope("profile");

// Redirect to Google
header('Location: ' . $client->createAuthUrl());
exit;
