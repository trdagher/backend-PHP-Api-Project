<?php

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







// GET Request: Fetch Data from the Database
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['key']) && isset($_GET['data'])) {
        $key = $_GET['key'];
        $data = intval($_GET['data']); // Convert 'data' to an integer

        validateKeyFromURL($conn);

        try {
            // Define the number of rows to fetch per request
            $rowsPerPage = 10;

            // Calculate the limit based on 'data' value
            $limit = $rowsPerPage;

            // Calculate the offset based on 'data' value
            $offset = ($data - 1) * $rowsPerPage;

            // Your SQL query to fetch data with pagination using 'LIMIT' and 'OFFSET'
            $query = "SELECT * FROM users ORDER BY id DESC LIMIT ?, ?";


            // Prepare the query using the existing $conn
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $offset, $limit);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);

                // Check if the result is empty
                if (empty($data)) {
                    header('HTTP/1.1 201 Created');
                    echo json_encode(array('status' => '201', 'message' => 'No matching records found'));
                } else {
                    // Return the data as a JSON response
                    // Calculate the number of pages based on the number of rows and rows per page (e.g., 10)
                    $totalRows = count($data);
                    $rowsPerPage = 10;
                    $totalPages = ceil($totalRows / $rowsPerPage);

                    // Prepare the response array
                    $response = array(
                        'status' => '200',
                        'message' => 'OK',
                        'data' => $data,
                        'pages' => $totalPages
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
        } catch (Exception $e) {
            // Handle database connection errors
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Database connection error'));
        }
    } else if (isset($_GET['key']) && isset($_GET['search']) && isset($_GET['limit'])) {
        $key = $_GET['key'];
        $searchValue = $_GET['search'];
        $limit = intval($_GET['limit']); // Convert 'limit' to an integer

        validateKeyFromURL($conn);

        try {
            // Calculate the offset based on 'limit' value
            $rowsPerPage = 10;
            $offset = ($limit - 1) * $rowsPerPage;

            // Use a prepared statement with parameter binding to prevent SQL injection
            $query = "SELECT * FROM users 
                  WHERE firstName LIKE ? OR lastName LIKE ? OR email LIKE ? 
                  ORDER BY id DESC LIMIT ?, ?";

            // Prepare the query using the existing $conn
            $stmt = $conn->prepare($query);

            // Add wildcard characters for partial matching
            $searchParam = '%' . $searchValue . '%';

            // Bind parameters
            $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $offset, $rowsPerPage);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);

                // Check if the result is empty
                if (empty($data)) {
                    header('HTTP/1.1 201 Created');
                    echo json_encode(array('status' => '201', 'message' => 'No matching records found'));
                } else {
                    // Return the data as a JSON response
                    // Calculate the number of pages based on the number of rows and rows per page (e.g., 10)
                    $totalRows = count($data);
                    $rowsPerPage = 10;
                    $totalPages = ceil($totalRows / $rowsPerPage);

                    // Prepare the response array
                    $response = array(
                        'status' => '200',
                        'message' => 'OK',
                        'data' => $data,
                        'pages' => $totalPages
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
        } catch (Exception $e) {
            // Handle database connection errors
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Database connection error'));
        }
    } else {
        // Handle the case where the 'key', 'data', 'search', or 'limit' parameter is missing
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Key, data, search, or limit is missing'));
    }
}


// DELETE Request: Delete Data from the Database
else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($_GET['key'])) {
        validateKeyForAdmin($conn);
        try {
            // Parse the JSON data sent in the request body, if any
            $data = json_decode(file_get_contents("php://input"), true);

            // Check if the JSON data contains an 'id' field
            if (isset($data['id'])) {
                // Extract the 'id' value
                $id = $data['id'];

                // Check if the record with the given ID exists
                $checkQuery = "SELECT id FROM users WHERE id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    // The record exists, proceed with deletion
                    $deleteQuery = "DELETE FROM users WHERE id = ?";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("i", $id);

                    if ($stmt->execute()) {
                        // Return a success response
                        header('HTTP/1.1 200 OK');
                        echo json_encode(array('status' => 200, 'message' => 'Record deleted successfully'));
                    } else {
                        // Handle database query errors
                        header('HTTP/1.1 500 Internal Server Error');
                        echo json_encode(array('error' => 'Database error'));
                    }
                } else {
                    // The record with the given ID does not exist
                    header('HTTP/1.1 404 Not Found');
                    echo json_encode(array('status' => 404, 'error' => 'Record not found'));
                }
            } else {
                // Handle missing 'id' field in JSON data
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 400, 'error' => 'Invalid request data'));
            }
        } catch (Exception $e) {
            // Handle exceptions
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Server error'));
        }
    } else {
        // Handle the case where the 'key' parameter is missing
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Key is missing'));
    }
}

// PUT Request: Update Data in the Database
// PUT Request: Update Data in the Database
else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (isset($_GET['key'])) {
        validateKeyForAdmin($conn);
        try {
            // Parse the JSON data sent in the request body, if any
            $data = json_decode(file_get_contents("php://input"), true);

            // Check if the JSON data contains the 'id' field
            if (isset($data['id'])) {
                // Extract the 'id' value
                $id = $data['id'];

                // Initialize an array to store the fields to update
                $updateFields = array();

                // Define an array of valid field names
                $validFields = array(
                    'firstName', 'lastName', 'manufacturerTrader', 'credibility',
                    'jobTitle', 'email', 'webPage', 'category', 'subCategory',
                    'street', 'city', 'stateProvince', 'zipPostalCode', 'country',
                    'businessPhone', 'homePhone', 'mobilePhone', 'faxNumber', 'latitude', 'longitude'
                );

                // Check and add valid fields to updateFields
                foreach ($validFields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = '{$data[$field]}'";
                    }
                }

                if (!empty($updateFields)) {
                    // Construct the SET clause for the SQL query
                    $setClause = implode(", ", $updateFields);

                    // Your SQL query to update data, e.g., UPDATE users SET firstName = ?, lastName = ? WHERE id = ?
                    $query = "UPDATE users SET $setClause WHERE id = ?";

                    // Prepare and execute the query using the existing $conn
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $id);

                    if ($stmt->execute()) {
                        // Return a success response
                        header('HTTP/1.1 200 OK');
                        echo json_encode(array('status' => 200, 'message' => 'Record updated successfully'));
                    } else {
                        // Handle database query errors
                        header('HTTP/1.1 500 Internal Server Error');
                        echo json_encode(array('status' => 500, 'error' => 'Database error: ' . $stmt->error));
                    }
                } else {
                    // Handle missing or invalid fields in JSON data
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(array('status' => 400, 'error' => 'No valid fields to update'));
                }
            } else {
                // Handle missing 'id' field in JSON data
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 400, 'error' => 'Invalid request data: Missing "id" field'));
            }
        } catch (Exception $e) {
            // Handle exceptions
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Server error: ' . $e->getMessage()));
        }
    } else {
        // Handle the case where the 'key' parameter is missing
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Key is missing'));
    }
}



// POST Request: Insert Data into the Database
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['key'])) {
        validateKeyFromURL($conn);
        try {
            // Parse the JSON data sent in the request body, if any
            $data = json_decode(file_get_contents("php://input"), true);

            // Define an array of the fields you want to insert
            $insertFields = array(
                'firstName', 'lastName', 'manufacturerTrader', 'credibility',
                'jobTitle', 'email', 'webPage', 'category', 'subCategory',
                'street', 'city', 'stateProvince', 'zipPostalCode', 'country',
                'businessPhone', 'homePhone', 'mobilePhone', 'faxNumber', 'latitude', 'longitude'
            );

            // Check if the required fields are present
            $requiredFields = array('firstName', 'lastName', 'email');
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(array('status' => 400, 'error' => "$field is required"));

                    exit();
                }
            }

            // Check if a record with the same first name, last name, and email already exists
            $checkQuery = "SELECT COUNT(*) FROM users WHERE firstName = ? AND lastName = ? AND email = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('sss', $data['firstName'], $data['lastName'], $data['email']);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('status' => 409, 'error' => 'Duplicate record found'));

                exit();
            }
            // Build the INSERT query
            $query = "INSERT INTO users (";
            $query .= implode(', ', $insertFields);
            $query .= ") VALUES (";
            $query .= rtrim(str_repeat(
                '?, ',
                count($insertFields)
            ), ', ');
            $query .= ")";



            // Prepare and execute the query using the existing $conn
            $stmt = $conn->prepare($query);

            // Initialize an array to store the references to the bind_params arguments
            $bindParams = array();

            foreach ($insertFields as $field) {
                if (isset($data[$field])) {
                    $bindParams[] = &$data[$field]; // Note the use of the '&' to pass by reference
                }
            }

            // Construct the argument string for bind_param
            $bindArgs = array(str_repeat('s', count($bindParams))); // Assuming all values are strings
            $bindArgs = array_merge($bindArgs, $bindParams);

            // Use call_user_func_array to bind the parameters dynamically
            call_user_func_array(array($stmt, 'bind_param'), $bindArgs);

            if ($stmt->execute()) {
                // Return a success response
                header('HTTP/1.1 200 OK');
                echo json_encode(array('status' => 200, 'message' => 'Record inserted successfully'));
            } else {
                // Handle database query errors
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(array('status' => 500, 'error' => 'Database error'));
            }
        } catch (Exception $e) {
            // Handle exceptions
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('status' => 500, 'error' => 'Server error: ' . $e->getMessage()));
        }
    } else {
        // Handle the case where the 'key' parameter is missing
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Key is missing'));
    }
}
