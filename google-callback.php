<?php
require_once 'google-api-php-client--PHP8.1/vendor/autoload.php';
require_once 'config/database.php'; // needed for DB
session_start();

$client = new Google_Client();

$client->setClientId('1086248183931-k4h6028vqbll3nkel7uq2hepb909494t.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/svmehendhis-main/google-callback.php');

if (!isset($_GET['code'])) {
    header("Location: login.php");
    exit;
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token);

// Get Google user
$oauth = new Google_Service_Oauth2($client);
$user = $oauth->userinfo->get();


// 🔥 PUT YOUR DB CODE HERE
$conn = getDBConnection();

// Check if user exists
$email = $user->email;

$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $dbUser = $res->fetch_assoc();

    $_SESSION['user_id'] = $dbUser['id'];
    $_SESSION['username'] = $dbUser['username'];
    $_SESSION['user_role'] = $dbUser['role'];
} else {
    $username = $user->name;

    $stmt = $conn->prepare(
        "INSERT INTO users (email, username, role) VALUES (?, ?, 'user')"
    );
    $stmt->bind_param("ss", $email, $username);
    $stmt->execute();

    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = 'user';
}

closeDBConnection($conn);

// ✅ Redirect after session is set
header("Location: index.php");
exit;
