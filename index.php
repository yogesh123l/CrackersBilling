<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="./Assets/css/login.css">
    <title>Billing</title>
   
</head>
<body>
    <form id="loginForm" action="validate.php" method="post">
        <div class="login-box">
            <h1>Admin Login</h1>

            <div class="textbox">
                <i class="fa fa-user" aria-hidden="true"></i>
                <input type="text" placeholder="Username" name="username" id="username" required>
            </div>

            <div class="textbox">
                <i class="fa fa-lock" aria-hidden="true"></i>
                <input type="password" placeholder="Password" name="password" id="password" required>
          
            </div>

            <input class="button" type="submit" name="login" value="Sign In">
        </div>
    </form>

    <script src="./Assets/js/sweetalert2.all.min.js"></script>
    <script>
        

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('validate.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Welcome Admin...',
                        timer: 2000,
                        showConfirmButton: false,
                        position: window.innerWidth < 600 ? 'center' : 'center'  
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: data.message,
                        position: window.innerWidth < 600 ? 'center' : 'center'
                    });
                }
            })
            .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html>
