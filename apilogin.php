<?php
require_once('db.php'); // Include the database connection file

// Allow requests from any origin
header("Access-Control-Allow-Origin: *");

// Allow specific HTTP methods (e.g., POST)
header("Access-Control-Allow-Methods: POST");

// Allow specific HTTP headers (e.g., Content-Type)
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}



// Function to generate a random API key with expiration timestamp
function generateApiKeyWithExpiration($expirationHours,$length = 32)
{
    // Define characters to use in the API key
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    $apiKey = '';
    $expirationTimestamp = time() + ($expirationHours * 3600); // Set expiration time

    // Generate a random character for each position in the key
    for ($i = 0; $i < $length; $i++) {
        $apiKey .= $characters[rand(0, strlen($characters) - 1)];
    }

    return ['key' => $apiKey, 'expiration' => $expirationTimestamp];
}

// Generate a new API key with a 24-hour expiration
// $newApiKeyData = generateApiKeyWithExpiration();

// echo "Generated API Key: " . $newApiKeyData['key'] . "\n";
// echo "Expiration Timestamp: " . date('Y-m-d H:i:s', $newApiKeyData['expiration']) . "\n";

$response = array();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if the JSON data contains the expected keys
    if (isset($data['username']) && isset($data['password'])) {
        // Get the username and password from the JSON data
        $username = $data['username'];
        $password = $data['password'];

        // Perform the user credential check
        $query = "SELECT * FROM login WHERE username = '$username' AND password = '$password'";
        $result = $conn->query($query);

        if ($result->num_rows > 0) {
            // User credentials are valid

            // Generate a JWT token (API key) with an expiration time
            $expirationHours = 720; // 1 month 720hrs, you can adjust this as needed
            $apiKeyData = generateApiKeyWithExpiration($expirationHours);
            $apiKey = $apiKeyData['key'];
            $timestamp = $apiKeyData['expiration'];


            // Store the API key and expiration timestamp in your database
            $insertQuery = "UPDATE login SET api_key = '$apiKey', expiration_timestamp = '$timestamp' WHERE username = '$username' AND password = '$password'";
            $insertResult = $conn->query($insertQuery);

            if ($insertResult) {
                // Include the API key in the response body
                $response = array('success' => true, 'message' => 'Login successful', 'token' => $apiKey, 'status' => 200);


                // Set a cookie with the API key (if needed)
                setcookie('jwt_token', $apiKey, $timestamp, '/', '127.0.0.1', false, true);
            } else {
                // Failed to store the API key in the database
                $response = array('success' => false, 'message' => 'Failed to store API key', 'status' => 500);

            }
        } else {
            // User credentials are invalid
            $response = array('success' => false, 'message' => 'Login failed', 'status' => 401);

        }
    } else {
        // JSON data does not contain the expected keys
        $response = array('success' => false, 'message' => 'Invalid JSON data', 'status' => 400);

    }
} else {
    // Invalid request method
    header('HTTP/1.1 405 Method Not Allowed');
    echo "Method not allowed";
}

// Return the JSON response
echo json_encode($response);
