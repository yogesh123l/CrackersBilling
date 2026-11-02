
<html>
    
<style>

header {
    background-color: none;
    color: Black;
    padding: 15px 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    position: relative;
}

header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}


.profile-icons {
    display: flex;
    align-items: center;
    position: relative;
}

.profile-dropdown {
    position: relative;
    display: inline-block;
}

.profile-img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid white;
}


.dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    background-color: #fff;
    min-width: 150px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    z-index: 1;
    border-radius: 5px;
    overflow: hidden;
    color: black;
    text-align: left;
}

.dropdown-content p {
    margin: 0;
    padding: 12px;
    background-color: #34495e;
    color: white;
    font-size: 14px;
    font-weight: bold;
}

.dropdown-content a {
    padding: 10px 15px;
    display: block;
    text-decoration: none;
    color: black;
    font-size: 14px;
    border-top: 1px solid #eaeaea;
}

.dropdown-content a:hover {
    background-color: #f1f1f1;
}


.profile-dropdown:hover .dropdown-content {
    display: block;
}


@media (max-width: 768px) {
    header {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px;
    }

    header h1 {
        margin-bottom: 10px;
        font-size: 20px;
    }

    .profile-icons {
        width: 100%;
        justify-content: flex-end;
    }

    .dropdown-content {
        right: 0;
    }
}

    </style>
<header class="d-flex justify-content-between align-items-center">
    <h1>Welcome Admin!</h1>
    <div class="profile-icons">
        <div class="profile-dropdown">

            <img src="image/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-img">

            <div class="dropdown-content">
                <p>Welcome Admin!</p>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="javascript:void(0);" id="logoutButton"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<script>
document.getElementById('logoutButton').addEventListener('click', function () {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of the session!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});
</script>
<html>