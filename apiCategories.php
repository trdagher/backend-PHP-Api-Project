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
    if (isset($_GET['key'])) {
        validateKeyFromURL($conn);

        try {
            // Define the number of rows to fetch per request
            $rowsPerPage = 10;

            if (isset($_GET['page']) && is_numeric($_GET['page'])) {
                $page = max(1, intval($_GET['page']));
            } else {
                $page = 1;
            }

            // Calculate the limit based on the page and rowsPerPage
            $limit = $rowsPerPage;
            $offset = ($page - 1) * $rowsPerPage;

            // Your SQL query to fetch data with pagination using 'LIMIT' and 'OFFSET'
            $query = "SELECT category_name, GROUP_CONCAT(sub_category) as sub_category FROM category GROUP BY category_name ORDER BY category_name ASC LIMIT ?, ?";

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
                    echo json_encode(array(
                        'status' => '201', 'message' => 'No matching records found'
                    ));
                } else {
                    // Convert sub_category from a comma-separated string to an array
                    foreach ($data as &$record) {
                        $subCategories = explode(',', $record['sub_category']);
                        $record['sub_category'] = array_values(array_filter($subCategories, function ($sub) {
                            return trim($sub) !== '';
                        }));
                        if (empty($record['sub_category'])) {
                            $record['sub_category'] = [];
                        }
                    }

                    // Calculate the number of pages based on the number of rows and rows per page (e.g., 10)
                    $totalRows = count($data);
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
        // Handle the case where the 'key' parameter is missing
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Key is missing'));
    }
}




if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (isset($_GET['key']) && isset($_GET['data']) && isset($_GET['id'])) {
        $key = $_GET['key'];
        $data = $_GET['data']; // Keep 'data' as a string to specify the table name
        $id = $_GET['id'];     // Get the 'id' provided

        ValidateKeyFromURL($conn);

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

        ValidateKeyFromURL($conn);

        // Define an array of table names and their corresponding column names for updates
        $tableColumnMap = array(
            'company' => 'company_name',
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
    } else  if (isset($_GET['key'])) {
        $key = $_GET['key'];
        ValidateKeyFromURL($conn);

        // Check if 'category_name' and 'sub_category' are in the request body
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);

        if (isset($requestData['category_name']) && isset($requestData['sub_category']) && isset($requestData['id'])) {
            // Handle 'category' table updates using data from the request body
            $category_name = $requestData['category_name'];

            // Convert 'sub_category' array to a comma-separated string
            $sub_category = implode(', ', $requestData['sub_category']);

            $id = $requestData['id'];

            try {
                // Build the SQL query to update the 'category' table
                $query = "UPDATE category SET category_name = ?, sub_category = ? WHERE id = ?";

                // Bind parameters and execute the query
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $category_name, $sub_category, $id);
                $stmt->execute();

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
            } catch (Exception $e) {
                // Handle database connection errors or other exceptions
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(array('status' => 500, 'error' => 'Database error'));
            }
        } else {
            // Handle the case where 'category_name,' 'sub_category,' or 'id' is missing in the request body
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(array('status' => 400, 'error' => 'Missing category_name, sub_category, or id parameters'));
        }
    } else {
        // Handle missing 'key' parameter
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(array('status' => 400, 'error' => 'Missing key parameter'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['key'])) {
        validateKeyFromURL($conn); // Assuming validateKeyFromURL is defined

        try {
            // Parse the JSON data sent in the request body, if any
            $data = json_decode(file_get_contents("php://input"), true);

            // Define an array of the fields you want to insert
            $insertFields = array('category_name', 'sub_category');

            // Check if the required fields are present
            $requiredFields = array('category_name', 'sub_category');
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(array('status' => 400, 'error' => "$field is required"));
                    exit();
                }
            }

            // Convert the "sub_category" array to a comma-separated string
            if (isset($data['sub_category']) && is_array($data['sub_category'])) {
                $data['sub_category'] = implode(', ', $data['sub_category']);
            }

            // Check if a record with the same category_name and sub_category already exists
            $checkQuery = "SELECT COUNT(*) FROM category WHERE category_name = ? AND sub_category = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('ss', $data['category_name'], $data['sub_category']);
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
            $query = "INSERT INTO category (";
            $query .= implode(', ', $insertFields);
            $query .= ") VALUES (";
            $query .= rtrim(str_repeat('?, ', count($insertFields)), ', ');
            $query .= ")";

            // Prepare and execute the query using the existing $conn
            $stmt = $conn->prepare($query);

            // Initialize an array to store the references to the bind_params arguments
            $bindParams = array();

            foreach ($insertFields as $field) {
                if (isset($data[$field])) {
                    $bindParams[] = &$data[$field];
                }
            }

            // Construct the argument string for bind_param
            $bindArgs = array(str_repeat('s', count($bindParams)));
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
