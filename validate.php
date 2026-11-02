<?php
include_once('config.php');


if (!function_exists('test_input')) {
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

$response = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = test_input($_POST["username"]);
    $password = test_input($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM login WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    
    if ($user && $user['password'] == $password) {
        session_start();
        $_SESSION['username'] = $username; 

        $response['success'] = true;
    } else {
        $response['success'] = false;
        $response['message'] = 'Invalid username or password!';
    }


    echo json_encode($response);
}
?>
