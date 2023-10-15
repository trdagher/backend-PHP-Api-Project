<?php
require_once('db.php');

require_once('db.php'); // Include the database connection file



// Allow requests from your frontend origin
header("Access-Control-Allow-Origin: *");

// Allow specific HTTP methods (e.g., POST)
header("Access-Control-Allow-Methods: GET, POST, DELETE, PUT");

// Allow specific HTTP headers, including 'Authorization'
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}
function validateKeyForAdmin($conn)
{
    // Check if the 'key' parameter is set in the URL
    if (isset($_GET['key'])) {
        $keyFromURL = $_GET['key'];

        // Query the database to check if the key exists and matches the admin key
        $query = "SELECT adminkey FROM admin";
        $result = $conn->query($query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $adminKey = $row['adminkey'];

            // Check if the key matches the admin key
            if ($keyFromURL === $adminKey) {
                // Key is valid, you can continue with the request
            } else {
                // Key is invalid, return a 401 Unauthorized response
                header('HTTP/1.1 401 Unauthorized');
                echo json_encode(array('status' => 401, 'error' => 'Invalid admin key'));
                exit();
            }
        } else {
            // Admin key not found in the database, return a 401 Unauthorized response
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(array('status' => 401, 'error' => 'Admin key not found'));
            exit();
        }
    } else {
        // 'key' parameter is missing in the URL, return a 401 Unauthorized response
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(array('status' => 401, 'error' => 'Key parameter is missing'));
        exit();
    }
}

function validateKeyFromURL($conn)
{
    // Check if the 'key' parameter is set in the URL
    if (isset($_GET['key'])) {
        $keyFromURL = $_GET['key'];

        // Query the database to check if the key exists and is still valid
        $query = "SELECT expiration_timestamp FROM login WHERE api_key = '$keyFromURL'";
        $result = $conn->query($query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $expirationTimestamp = $row['expiration_timestamp'];

            // Check if the key is still valid
            if ($expirationTimestamp >= time()) {
                // Key is valid, you can continue with the request
            } else {
                // Key has expired, return a 401 Unauthorized response
                header('HTTP/1.1 401 Unauthorized');
                exit();
            }
        } else {
            // Key not found in the database, return a 401 Unauthorized response
            header('HTTP/1.1 401 Unauthorized');
            exit();
        }
    } else {
        // 'key' parameter is missing in the URL, return a 401 Unauthorized response
        header('HTTP/1.1 401 Unauthorized');
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['key']) && isset($_GET['data'])) {
        $key = $_GET['key'];
        $data = $_GET['data']; // Keep 'data' as a string to specify the table name

       ValidateKeyFromURL($conn);

        try {
            // Check if the requested table exists
            $allowedTables = array('company', 'category', 'credibility', 'jobTitle', 'manufacturer'); // Add all allowed table names here
            if (in_array($data, $allowedTables)) {
                // Your SQL query to fetch all data from the specified table
                $query = "SELECT * FROM $data";

                // Prepare the query using the existing $conn
                $stmt = $conn->prepare($query);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result) {
                    $data = $result->fetch_all(MYSQLI_ASSOC);

                    // Check if the result is empty
                    if (empty($data)) {
                        header('HTTP/1.1 201 Created');
                        echo json_encode(array('status' => '201', 'message' => 'No matching records found'));
                    } else {
                        // Prepare the response array
                        $response = array(
                            'status' => '200',
                            'message' => 'OK',
                            'data' => $data
                        );

                        // Return the data as a JSON response with status 200
                        header('HTTP/1.1 200 OK');
                        header('Content-Type: application/json');
                        echo json_encode($response);
                    }
                } else {
                    // Handle database query errors
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(array('status' => 500, 'error' => 'Database error'));
                }
            } elseif ($data === 'sub_category' && isset($_GET['category'])) {
                // Check if 'data' is 'sub_category' and 'category' is set
                $category = $_GET['category']; // Get the category name

                // Your SQL query to fetch sub-categories for the specified category
                $query = "SELECT * FROM `sub_category` WHERE category_id = (SELECT id FROM category WHERE category_name = ? LIMIT 1)";

                // Prepare the query using the existing $conn
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $category);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result) {
                    $data = $result->fetch_all(MYSQLI_ASSOC);

                    // Check if the result is empty
                    if (empty($data)) {
                        header('HTTP/1.1 201 Created');
                        echo json_encode(array('status' => '201', 'message' => 'No matching records found for sub-categories'));
                    } else {
                        // Prepare the response array
                        $response = array(
                            'status' => '200',
                            'message' => 'OK',
                            'data' => $data
                        );

                        // Return the data as a JSON response with status 200
                        header('HTTP/1.1 200 OK');
                        header('Content-Type: application/json');
                        echo json_encode($response);
                    }
                } else {
                    // Handle database query errors
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(array('status' => 500, 'error' => 'Database error for sub-categories'));
                }
            } else {
                // Handle the case where the requested table or conditions are not allowed
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 400, 'error' => 'Invalid table or condition'));
            }
        } catch (Exception $e) {
            // Handle database connection errors
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Database connection error'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($_GET['key']) && isset($_GET['data']) && isset($_GET['id'])) {
        $key = $_GET['key'];
        $data = $_GET['data']; // Keep 'data' as a string to specify the table name
        $id = $_GET['id'];     // Get the 'id' provided

        validateKeyForAdmin($conn);

        try {
            // Check if the requested table exists
            $allowedTables = array('company', 'category', 'credibility', 'jobTitle', 'manufacturer', 'sub_category'); // Add all allowed table names here
            if (in_array($data, $allowedTables)) {
                // Your SQL query to delete a record from the specified table
                $query = "DELETE FROM $data WHERE id = ?";

                // Prepare the query using the existing $conn
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $id);
                $stmt->execute();

                // Check if any rows were affected by the delete operation
                $rowsAffected = $stmt->affected_rows;

                if ($rowsAffected > 0) {
                    // Record(s) deleted successfully
                    header('HTTP/1.1 200 OK');
                    echo json_encode(array('status' => '200', 'message' => 'Record(s) deleted successfully'));
                } else {
                    // No matching record found for the provided 'id'
                    header('HTTP/1.1 404 Not Found');
                    echo json_encode(array('status' => '404', 'error' => 'No matching record found'));
                }
            } else {
                // Handle the case where the requested table is not allowed
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 400, 'error' => 'Invalid table name'));
            }
        } catch (Exception $e) {
            // Handle database connection errors or other exceptions
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Database error'));
        }
    } else {
        // Handle missing 'key,' 'data,' or 'id' parameters
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Missing parameters'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (isset($_GET['key']) && isset($_GET['data']) && isset($_GET['id'])) {
        $key = $_GET['key'];
        $data = $_GET['data']; // Keep 'data' as a string to specify the table name
        $id = $_GET['id'];     // Get the 'id' provided

        validateKeyForAdmin($conn);

        // Define an array of table names and their corresponding column names for updates
        $tableColumnMap = array(
            'company' => 'company_name',
            'category' => 'category_name',
            'credibility' => 'credibility_name',
            'jobTitle' => 'jobTitle_name',
            'manufacturer' => 'manufacturer_name',
            'sub_category' => 'sub_category_name'
        );

        try {
            // Check if the requested table exists and is in the allowed tables
            if (array_key_exists($data, $tableColumnMap)) {
                // Determine the column name for the specified table
                $columnName = $tableColumnMap[$data];

                // Parse the request body as JSON
                $requestBody = file_get_contents('php://input');
                $requestData = json_decode($requestBody, true);

                if ($requestData !== null) {
                    // Check if the table is 'sub_category' and if so, add the 'category_id' column
                    if ($data === 'sub_category') {
                        // Add an extra check for 'category_id'
                        if (isset($requestData['category_id'])) {
                            // Build the SQL query to update the specified table
                            $query = "UPDATE $data SET $columnName = ?, category_id = ? WHERE id = ?";
                            
                            // Bind parameters and execute the query
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("sii", $requestData[$columnName], $requestData['category_id'], $id);
                            $stmt->execute();
                        } else {
                            // Missing 'category_id' in the request body
                            header('HTTP/1.1 400 Bad Request');
                            echo json_encode(array('status' => 400, 'error' => 'Missing category_id in the request body'));
                        }
                    } else {
                        // Build the SQL query to update the specified table without 'category_id'
                        $query = "UPDATE $data SET $columnName = ? WHERE id = ?";
                        
                        // Bind parameters and execute the query
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("si", $requestData[$columnName], $id);
                        $stmt->execute();
                    }

                    // Check if any rows were affected by the update operation
                    $rowsAffected = $stmt->affected_rows;

                    if ($rowsAffected > 0) {
                        // Record(s) updated successfully
                        header('HTTP/1.1 200 OK');
                        echo json_encode(array('status' => '200', 'message' => 'Record(s) updated successfully'));
                    } else {
                        // No matching record found for the provided 'id'
                        header('HTTP/1.1 404 Not Found');
                        echo json_encode(array('status' => '404', 'error' => 'No matching record found'));
                    }
                } else {
                    // Invalid JSON data in the request body
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(array('status' => 400, 'error' => 'Invalid JSON data in the request body'));
                }
            } else {
                // Handle the case where the requested table is not allowed
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 400, 'error' => 'Invalid table name'));
            }
        } catch (Exception $e) {
            // Handle database connection errors or other exceptions
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Database error'));
        }
    } else {
        // Handle missing 'key,' 'data,' or 'id' parameters
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Missing parameters'));
    }
}
