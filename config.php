<?php

$conn = null;

try {
    $servername = "sql313.infinityfree.com"; // From your phpMyAdmin server header
    $port = 3306;
    $dbname = "if0_43312751_crackers";       // Your full InfinityFree database name
    $username = "if0_43312751";              // Your InfinityFree control panel username
    $password = "Yogesh4949";     // Your InfinityFree account/panel password

    $conn = new PDO(
        "mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8mb4",
        $username, $password
    );
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed. Check that MySQL is running and the database is configured.");
}

?>