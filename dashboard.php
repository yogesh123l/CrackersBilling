<?php
require_once __DIR__ . '/config.php';

$totalProducts = (int) $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalInvoices = (int) $conn->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$latestProducts = $conn->query(
    "SELECT id, name, image, size, amount FROM products ORDER BY id DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
$sales = $conn->query(
    "SELECT
        COALESCE(SUM(total_amount), 0) AS lifetime_sales,
        COALESCE(SUM(CASE WHEN invoice_date = CURRENT_DATE() THEN total_amount ELSE 0 END), 0) AS today_sales,
        COALESCE(SUM(CASE WHEN invoice_date = CURRENT_DATE() - INTERVAL 1 DAY THEN total_amount ELSE 0 END), 0) AS yesterday_sales,
        COALESCE(SUM(CASE WHEN YEAR(invoice_date) = YEAR(CURRENT_DATE()) AND MONTH(invoice_date) = MONTH(CURRENT_DATE()) THEN total_amount ELSE 0 END), 0) AS monthly_sales
     FROM invoices"
)->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT profile_image FROM login WHERE id = ?");
$stmt->execute([1]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 'logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/favion.jpg" type="image/jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-faf9d9675135708740834769d559d90807309968716651d9e756d33277107337" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Crackers Billing</title>
 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>


<style>
    .dashboard-body {
        background: #f4f6f9;
        color: #202638;
        font-family: Arial, sans-serif;
    }

    .dashboard-body header {
        min-height: 78px;
        padding: 14px 28px;
        background: #fff;
    }

    .dashboard-body header h1 {
        color: #202638;
        font-size: 24px;
    }

    .dashboard-page {
        max-width: 1440px;
        margin: 0 auto;
        padding: 24px 28px 36px;
    }

    .dashboard-intro,
    .section-heading {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
    }

    .dashboard-intro {
        margin-bottom: 20px;
    }

    .dashboard-eyebrow {
        margin: 0 0 5px;
        color: #667085;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .dashboard-intro h2,
    .section-heading h2 {
        margin: 0;
        color: #202638;
        font-size: 21px;
        font-weight: 700;
    }

    .dashboard-date,
    .section-heading p {
        margin: 0;
        color: #667085;
        font-size: 13px;
    }

    .dashboard-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 30px;
    }

    .metric-card {
        min-width: 0;
        padding: 17px 18px;
        border: 1px solid #e1e5eb;
        border-left: 4px solid var(--metric-color);
        border-radius: 6px;
        background: #fff;
    }

    .metric-label {
        margin: 0 0 9px;
        color: #5d6677;
        font-size: 14px;
        font-weight: 600;
    }

    .metric-value {
        display: block;
        color: #202638;
        font-size: 25px;
        font-weight: 700;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .metric-detail {
        display: block;
        margin-top: 6px;
        color: #7b8493;
        font-size: 12px;
    }

    .products-section {
        overflow: hidden;
        border: 1px solid #e1e5eb;
        border-radius: 6px;
        background: #fff;
    }

    .section-heading {
        padding: 18px 20px;
        border-bottom: 1px solid #e8ebf0;
    }

    .products-table-wrap {
        overflow-x: auto;
    }

    .products-table {
        width: 100%;
        min-width: 560px;
        margin: 0;
        border-collapse: collapse;
        background: #fff;
        font-size: 14px;
    }

    .products-table th,
    .products-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #e8ebf0;
        text-align: left;
        vertical-align: middle;
    }

    .products-table th {
        background: #212545;
        color: #fff;
        font-weight: 600;
    }

    .products-table tbody tr:hover {
        background: #f8fafc;
    }

    .products-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .products-table img {
        width: 72px;
        max-width: 100%;
        height: 58px;
        border-radius: 4px;
        object-fit: contain;
    }

    .dashboard-actions {
        padding: 14px 20px;
        border-top: 1px solid #e8ebf0;
    }

    .dashboard-actions .btn-link {
        display: inline-block;
        margin: 0;
        padding: 9px 14px;
        border-radius: 4px;
        background: #167d8d;
        color: #fff;
        font-size: 14px;
        text-decoration: none;
    }

    .dashboard-actions .btn-link:hover {
        background: #116675;
        color: #fff;
    }

    .dashboard-body .footer_part {
        padding: 0 20px 20px;
        color: #667085;
        font-size: 12px;
    }

    @media (max-width: 991.98px) {
        .dashboard-body header {
            min-height: 72px;
            padding: 10px 16px 10px 58px;
            flex-direction: row;
            align-items: center;
        }

        .dashboard-body header h1 {
            margin: 0;
            font-size: 21px;
        }

        .dashboard-body .profile-icons {
            width: auto;
        }

        .dashboard-body .profile-img {
            width: 42px;
            height: 42px;
        }

        .dashboard-body #sidebarToggler {
            top: 18px;
            left: 12px;
            display: flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
        }

        .dashboard-page {
            padding: 20px 18px 30px;
        }

        .dashboard-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .dashboard-page {
            padding: 16px 12px 26px;
        }

        .dashboard-intro {
            align-items: start;
        }

        .dashboard-intro h2,
        .section-heading h2 {
            font-size: 18px;
        }

        .dashboard-date {
            max-width: 112px;
            text-align: right;
        }

        .dashboard-metrics {
            gap: 9px;
            margin-bottom: 22px;
        }

        .metric-card {
            padding: 12px 11px;
        }

        .metric-label {
            font-size: 12px;
        }

        .metric-value {
            font-size: 20px;
        }

        .metric-detail {
            font-size: 11px;
        }

        .section-heading {
            align-items: start;
            padding: 14px;
        }

        .section-heading p {
            max-width: 130px;
            text-align: right;
        }

        .products-table {
            min-width: 520px;
            font-size: 13px;
        }

        .products-table th,
        .products-table td {
            padding: 10px;
        }

        .dashboard-actions {
            padding: 12px 14px;
        }
    }

    @media (max-width: 360px) {
        .dashboard-metrics {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body class="dashboard-body">
<?php include 'sidenav.php'; ?>
<?php include 'header.php'; ?>
<main class="dashboard-page">
    <div class="dashboard-intro">
        <div>
            <p class="dashboard-eyebrow">Overview</p>
            <h2>Sales at a glance</h2>
        </div>
        <p class="dashboard-date"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <section class="dashboard-metrics" aria-label="Business summary">
        <article class="metric-card" style="--metric-color: #167d8d">
            <p class="metric-label">Total Products</p>
            <span class="metric-value"><?php echo number_format($totalProducts); ?></span>
            <span class="metric-detail">Items in inventory</span>
        </article>
        <article class="metric-card" style="--metric-color: #5264a8">
            <p class="metric-label">Total Bills</p>
            <span class="metric-value"><?php echo number_format($totalInvoices); ?></span>
            <span class="metric-detail">Invoices created</span>
        </article>
        <article class="metric-card" style="--metric-color: #24865a">
            <p class="metric-label">Lifetime Sales</p>
            <span class="metric-value">₹<?php echo number_format((float) $sales['lifetime_sales'], 2); ?></span>
            <span class="metric-detail">All recorded invoices</span>
        </article>
        <article class="metric-card" style="--metric-color: #d38a1f">
            <p class="metric-label">Today's Sales</p>
            <span class="metric-value">₹<?php echo number_format((float) $sales['today_sales'], 2); ?></span>
            <span class="metric-detail"><?php echo date('F j'); ?></span>
        </article>
        <article class="metric-card" style="--metric-color: #3182a0">
            <p class="metric-label">Yesterday's Sales</p>
            <span class="metric-value">₹<?php echo number_format((float) $sales['yesterday_sales'], 2); ?></span>
            <span class="metric-detail"><?php echo date('F j', strtotime('-1 day')); ?></span>
        </article>
        <article class="metric-card" style="--metric-color: #c45b45">
            <p class="metric-label">This Month</p>
            <span class="metric-value">₹<?php echo number_format((float) $sales['monthly_sales'], 2); ?></span>
            <span class="metric-detail"><?php echo date('F Y'); ?></span>
        </article>
    </section>

    <section class="products-section" aria-labelledby="products-heading">
        <div class="section-heading">
            <div>
                <p class="dashboard-eyebrow">Inventory</p>
                <h2 id="products-heading">Recently added products</h2>
            </div>
            <p>Latest 5 products</p>
        </div>
        <div class="products-table-wrap">
<table class="products-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Image</th>
            <th>Size</th>
            <th>Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php
       
        if (!empty($latestProducts)) {
            foreach ($latestProducts as $product) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($product['id']) . "</td>"; 
                echo "<td>" . htmlspecialchars($product['name']) . "</td>"; 
                echo "<td>";
                
   
                $imagePath = 'image/' . htmlspecialchars($product['image']);
                
               
                if (file_exists($imagePath)) {
                    echo "<img src='$imagePath' alt='Product Image' width='100'>";
                } else {
                    echo "No Image Available";
                }
                
                echo "</td>";
                echo "<td>" . htmlspecialchars($product['size']) . "</td>";
                echo "<td>" . htmlspecialchars($product['amount']) . "</td>"; 
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No latest products found.</td></tr>";
        }
        
           
        ?>
    </tbody>
</table>
</div>
        <div class="dashboard-actions">
            <a href="products.php" class="btn-link">View all products</a>
        </div>
    </section>
</main>

<div class="footer_part">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="footer_iner text-center ">
                    <span class="mr-1"><script>document.write(new Date().getFullYear())</script>©</span> <a href="http://shwasthik.com/" class="" target="_blank">Shwasthik Technologies</a>
                </div>
            </div>
        </div>
    </div>
</div>
</section>


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


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



</body>
</html>


