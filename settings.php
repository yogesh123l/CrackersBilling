<?php
session_start();
include('config.php'); 


$userId = 1; 
$stmt = $conn->prepare("SELECT * FROM login WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newUsername = $_POST['username'];
    $newPassword = $_POST['password'];


    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $imageName = $_FILES['profile_image']['name'];
        $imageTmpName = $_FILES['profile_image']['tmp_name'];
        $imageFolder = 'image/' . $imageName;

   
        move_uploaded_file($imageTmpName, $imageFolder);
    } else {
        $imageName = $user['profile_image']; 
    }


    $stmt = $conn->prepare("UPDATE login SET username = ?, password = ?, profile_image = ? WHERE id = ?");
    $stmt->execute([$newUsername, $newPassword, $imageName, $userId]);


    header("Location: dashboard.php?success=1");
    exit;
}






$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'default.jpg';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-faf9d9675135708740834769d559d90807309968716651d9e756d33277107337" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Crackers Billing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./Assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="./Assets/css/font-awesome.css">
    <link rel="stylesheet" href="./Assets/css/all.min.css">
    <link rel="stylesheet" href="./Assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="./Assets/css/sweetalert2.css">
    <style>
   
form {
    display: flex;
    flex-direction: column;
}


form div {
    margin-bottom: 15px;
}

label {
    display: block;
    font-size: 16px;
    color: #333;
    margin-bottom: 5px;
}

input[type="text"],
input[type="password"],
input[type="file"] {
    width: 100%;
    padding: 10px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}


button {
    padding: 10px;
    font-size: 16px;
    color: #fff;
    background-color: #4CAF50;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

button:hover {
    background-color: #45a049;
}



@media (max-width: 768px) {
    .container {
        width: 90%;
        padding: 15px;
    }

    h1 {
        font-size: 24px;
    }

    input[type="text"],
    input[type="password"],
    input[type="file"] {
        font-size: 14px;
    }

    button {
        font-size: 14px;
    }
}


        body {
            padding: 20px;
        }
        .table img {
            width: 50px;
            height: auto;
        }
        @media(max-width: 768px) {
            .table {
                font-size: 14px;
            }
            .modal-dialog {
                max-width: 100%;
            }
        }
        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php include 'sidenav.php'; ?>
<?php include 'header.php'; ?>

      <h1>Settings</h1>
    <?php if (isset($_GET['success'])): ?>
        <p style="color: green;">Profile updated successfully!</p>
    <?php endif; ?>

    <form action="settings.php" method="POST" enctype="multipart/form-data">
        <div>
            <label for="username">Username:</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" name="password" value="<?php echo htmlspecialchars($user['password']); ?>" required>
        </div>
        <div>
            <label for="profile_image">Profile Image:</label>
            <input type="file" name="profile_image">
            <img src="image/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" width="100">
        </div>
        <button type="submit">SAVE UPDATE</button>
    </form>
</div>
<br>


  <a href="dashboard.php" i class="fa fa-arrow-left" aria-hidden="true" style="color:black;"></i> Back</a>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
