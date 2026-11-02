
<html>

<style>
.sidebar {
    background-color: #212545;
    height: 100vh;
    height: 100dvh;
    width: 200px;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    transition: transform 0.25s ease;
    z-index: 1000;
}

.sidebar .logo {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar .logo a img {
    height: 130px;
    width: 120px;
    object-fit: contain;
}

#sidebar_menu {
    padding: 0;
    margin: 20px 0 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

#sidebar_menu li {
    list-style-type: none;
    padding: 0;
}

#sidebar_menu li a {
    text-decoration: none;
    color: #ecf0f1;
    display: flex;
    align-items: center;
    min-height: 48px;
    padding: 8px 14px;
}

#sidebar_menu li a:hover,
#sidebar_menu li a:focus-visible {
    background-color: #343a5d;
}

#sidebar_menu li a img {
    flex: 0 0 30px;
    margin-right: 12px;
    height: 30px;
    width: 30px;
    object-fit: contain;
}

.sidebar_close_icon {
    display: none;
    border: 0;
    padding: 8px;
    background: transparent;
    color: white;
    cursor: pointer;
    font-size: 20px;
}

#sidebarToggler {
    display: none;
    position: fixed;
    top: 18px;
    left: 12px;
    background-color: #212545;
    color: white;
    border: 0;
    padding: 8px;
    cursor: pointer;
    z-index: 1400;
    border-radius: 50%;
}

.sidebar-backdrop {
    display: none;
}

@media (max-width: 991.98px) {
    #sidebarToggler {
        display: flex;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
    }

    .sidebar {
        width: min(200px, 82vw);
        transform: translateX(-100%);
        z-index: 1300;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar .logo {
        padding: 14px 12px;
    }

    .sidebar .logo a img {
        width: 92px;
        height: 92px;
    }

    .sidebar_close_icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
    }

    #sidebar_menu {
        gap: 6px;
        margin-top: 12px;
    }

    .sidebar-backdrop:not([hidden]) {
        display: block;
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
        background: rgba(12, 16, 32, 0.52);
        z-index: 1200;
    }

    body.sidebar-open {
        overflow: hidden;
    }

    body.sidebar-open #sidebarToggler {
        visibility: hidden;
    }

    body.modal-open #sidebarToggler {
        z-index: 1030;
    }
}

body {
    margin-left: 250px;
    transition: all 0.3s ease;
}

@media (max-width: 991.98px) {
    body {
        margin-left: 0;
    }
}

    </style>
<nav class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="logo d-flex justify-content-between">
        <a href="dashboard.php" aria-label="Home">
            <img src="Assets/images/logo1.png" alt="">
        </a>
        <button type="button" class="sidebar_close_icon" id="sidebarCloseIcon" aria-label="Close navigation">
            <i class="fa-solid fa-x" aria-hidden="true"></i>
        </button>
    </div>
    <ul id="sidebar_menu">
        <li>
            <a href="dashboard.php">
                <img src="Assets/images/home1.png" alt="Home Icon" style="height: 30px; width: 30px;">
                <span>Home</span>
            </a>
        </li>
        <li>
            <a href="products.php">
                <img src="Assets/images/product1.png" alt="Products Icon" style="height: 30px; width: 30px;">
                <span>Products</span>
            </a>
        </li>
        <li>
            <a href="billing.php">
                <img src="Assets/images/bill1.png" alt="Billing Icon" style="height: 30px; width: 30px;">
                <span>Billing</span>
            </a>
        </li>
        <li>
            <a href="summary.php">
                <img src="Assets/images/summary1.png" alt="Summary Icon" style="height: 30px; width: 30px;">
                <span>Summary</span>
            </a>
        </li>
    </ul>
</nav>

<button type="button" id="sidebarToggler" aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
    <i class="fas fa-bars" aria-hidden="true"></i>
</button>
<button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Close navigation" hidden></button>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggler = document.getElementById('sidebarToggler');
    const sidebarCloseIcon = document.getElementById('sidebarCloseIcon');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function setSidebarOpen(isOpen) {
        sidebar.classList.toggle('active', isOpen);
        document.body.classList.toggle('sidebar-open', isOpen);
        sidebarBackdrop.hidden = !isOpen;
        sidebarToggler.setAttribute('aria-expanded', String(isOpen));
        sidebarToggler.setAttribute('aria-label', isOpen ? 'Navigation open' : 'Open navigation');
    }

    sidebarToggler.addEventListener('click', function () {
        setSidebarOpen(!sidebar.classList.contains('active'));
    });

    sidebarCloseIcon.addEventListener('click', function () {
        setSidebarOpen(false);
    });

    sidebarBackdrop.addEventListener('click', function () {
        setSidebarOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });
});

    </script>







