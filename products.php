<?php
$servername = "localhost:3306";
$dbname = "crackersbilling";
$username = "root";
$password = "";


$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;


$search = isset($_POST['search']) ? $_POST['search'] : '';
$searchSql = !empty($search) ? " WHERE name LIKE '%$search%'" : '';


$sql = "SELECT * FROM products" . $searchSql . " ORDER BY id DESC LIMIT $start, $limit";
$result = $conn->query($sql);


$countSql = "SELECT COUNT(*) as total FROM products" . $searchSql;
$countResult = $conn->query($countSql);
$total = $countResult->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

$swalMessage = null;
$swalType = null;
$redirectNeeded = false;

if (isset($_POST['create'])) {
    $targetDir = "image/";
    $productsToCreate = [];
    $uploadedFiles = [];

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        $swalMessage = 'Failed to create the product image folder.';
        $swalType = 'error';
    } else {
        for ($index = 0; $index < 2; $index++) {
            $productsToCreate[] = [
                'name' => trim($_POST['name'][$index] ?? ''),
                'size' => trim($_POST['size'][$index] ?? ''),
                'quantity' => $_POST['quantity'][$index] ?? '',
                'amount' => $_POST['amount'][$index] ?? '',
            ];
        }

        $imageName = $_FILES['image']['name'] ?? '';
        $imageTmpName = $_FILES['image']['tmp_name'] ?? '';
        $imageError = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;

        foreach ($productsToCreate as $product) {
            if (
                $product['name'] === '' ||
                $product['size'] === '' ||
                !is_numeric($product['quantity']) ||
                !is_numeric($product['amount']) ||
                (int) $product['quantity'] < 0 ||
                (float) $product['amount'] < 0
            ) {
                $swalMessage = 'Complete all fields for both products.';
                $swalType = 'error';
                break;
            }
        }

        if ($swalMessage === null && $imageError !== UPLOAD_ERR_OK) {
            $swalMessage = 'Select one image to use for both products.';
            $swalType = 'error';
        }

        if ($swalMessage === null && strcasecmp($productsToCreate[0]['name'], $productsToCreate[1]['name']) === 0) {
            $swalMessage = 'The two products must have different names.';
            $swalType = 'error';
        }

        if ($swalMessage === null) {
            $duplicateStatement = $conn->prepare("SELECT id FROM products WHERE name = ?");
            foreach ($productsToCreate as $product) {
                $duplicateStatement->bind_param('s', $product['name']);
                $duplicateStatement->execute();
                $duplicateStatement->store_result();

                if ($duplicateStatement->num_rows > 0) {
                    $swalMessage = 'Product "' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . '" already exists.';
                    $swalType = 'error';
                    break;
                }
            }
            $duplicateStatement->close();
        }

        if ($swalMessage === null) {
            $extension = pathinfo(basename($imageName), PATHINFO_EXTENSION);
            $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
            $sharedImage = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
            $target = $targetDir . $sharedImage;

            if (move_uploaded_file($imageTmpName, $target)) {
                $uploadedFiles[] = $target;
                foreach ($productsToCreate as &$product) {
                    $product['image'] = $sharedImage;
                }
                unset($product);
            } else {
                $swalMessage = 'Failed to upload the shared product image.';
                $swalType = 'error';
            }
        }

        if ($swalMessage === null) {
            $conn->begin_transaction();
            $insertStatement = $conn->prepare(
                "INSERT INTO products (name, image, size, quantity, amount) VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($productsToCreate as $product) {
                $quantity = (int) $product['quantity'];
                $amount = (float) $product['amount'];
                $insertStatement->bind_param(
                    'sssid',
                    $product['name'],
                    $product['image'],
                    $product['size'],
                    $quantity,
                    $amount
                );
                if (!$insertStatement->execute()) {
                    $swalMessage = 'Error creating products: ' . $insertStatement->error;
                    $swalType = 'error';
                    break;
                }
            }

            if ($swalMessage === null) {
                $conn->commit();
                $swalMessage = 'Both products created successfully.';
                $swalType = 'success';
                $redirectNeeded = true;
            } else {
                $conn->rollback();
                foreach ($uploadedFiles as $uploadedFile) {
                    if (is_file($uploadedFile)) {
                        unlink($uploadedFile);
                    }
                }
            }

            $insertStatement->close();
        } elseif (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $uploadedFile) {
                if (is_file($uploadedFile)) {
                    unlink($uploadedFile);
                }
            }
        }
    }
}


if (isset($_POST['edit'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $size = $_POST['size'];
    $quantity = $_POST['quantity'];
    $amount = $_POST['amount'];
    $image = $_FILES['image']['name'];
    $target = "image/" . basename($image);

    $checkDuplicateSql = "SELECT * FROM products WHERE name = '$name' AND id != $id";
    $checkDuplicateResult = $conn->query($checkDuplicateSql);

    if ($checkDuplicateResult->num_rows > 0) {
        $swalMessage = 'Another product with this name already exists.';
        $swalType = 'error';
    } else {
        if (!empty($image)) {
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
            $sql = "UPDATE products SET name='$name', image='$image', size='$size', quantity='$quantity', amount='$amount' WHERE id=$id";
        } else {
            $sql = "UPDATE products SET name='$name', size='$size', quantity='$quantity', amount='$amount' WHERE id=$id";
        }

        if ($conn->query($sql) === TRUE) {
            $swalMessage = 'Product updated successfully';
            $swalType = 'success';
            $redirectNeeded = true;
        } else {
            $swalMessage = 'Error updating product: ' . $conn->error;
            $swalType = 'error';
        }
    }
}


if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $sql = "DELETE FROM products WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        $swalMessage = 'Product has been deleted.';
        $swalType = 'success';
        $redirectNeeded = true;
    } else {
        $swalMessage = 'Error deleting product: ' . $conn->error;
        $swalType = 'error';
    }
}


$stmt = $conn->prepare("SELECT profile_image FROM login WHERE id = ?");
$userId = 1;
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($profileImage);
$stmt->fetch();
$stmt->close();

$profileImage = !empty($profileImage) ? $profileImage : 'default.jpg';

$conn->close();
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="Assets/images/logo1.png" type="image/jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-faf9d9675135708740834769d559d90807309968716651d9e756d33277107337" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Crackers Billing</title>
 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <style>
        .products-body {
            background: #f4f6f9;
            color: #202638;
            font-family: Arial, sans-serif;
        }

        .products-body header {
            min-height: 78px;
            padding: 14px 28px;
            background: #fff;
        }

        .products-body header h1 {
            margin: 0;
            color: #202638;
            font-size: 24px;
        }

        .products-page.container {
            width: calc(100% - 48px);
            max-width: 1320px;
            padding: 28px 0 36px;
        }

        .products-page-heading {
            margin-bottom: 22px;
        }

        .products-eyebrow {
            margin: 0 0 5px;
            color: #167d8d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .products-page-heading h1 {
            margin: 0;
            color: #202638;
            font-size: 28px;
            font-weight: 700;
        }

        .products-page-heading p:last-child {
            margin: 6px 0 0;
            color: #667085;
            font-size: 14px;
        }

        .products-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .product-search-form {
            display: flex;
            flex: 1;
            gap: 9px;
            max-width: 680px;
            margin: 0;
        }

        .product-search-form .form-control {
            min-width: 0;
            height: 42px;
            border-color: #d8dee7;
        }

        .product-search-form .btn,
        .add-product-button {
            min-height: 42px;
            white-space: nowrap;
        }

        .product-search-form .btn {
            background: #212545;
            border-color: #212545;
        }

        .product-search-form .btn:hover {
            background: #30385f;
            border-color: #30385f;
        }

        .add-product-button {
            padding-right: 16px;
            padding-left: 16px;
            background: #167d8d;
            border-color: #167d8d;
        }

        .add-product-button:hover {
            background: #116675;
            border-color: #116675;
        }

        .products-table-wrap {
            overflow-x: auto;
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            background: #fff;
        }

        .products-table {
            min-width: 760px;
            margin: 0;
            background: #fff;
            font-size: 14px;
        }

        .products-table thead th {
            padding: 13px 14px;
            border-top: 0;
            border-bottom: 0;
            background: #212545;
            color: #fff;
            font-weight: 600;
            white-space: nowrap;
        }

        .products-table tbody td {
            padding: 10px 14px;
            vertical-align: middle;
        }

        .products-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .products-table img {
            width: 58px;
            height: 52px;
            border-radius: 4px;
            object-fit: contain;
        }

        .products-table td:last-child {
            white-space: nowrap;
        }

        .products-table td:last-child a {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            text-decoration: none;
        }

        .products-table td:last-child a:hover {
            background: #eef1f6;
        }

        .products-page .pagination {
            margin: 18px 0;
        }

        .products-page .no-print > .fa-print {
            display: inline-flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background: #fff;
        }

        .products-body #sidebarToggler {
            border: 0;
        }

        .products-body .modal-content {
            overflow: hidden;
        }

        .create-product-dialog {
            width: calc(100% - 32px);
            max-width: 900px;
        }

        .create-product-card {
            height: 100%;
            padding: 20px;
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(33, 37, 69, 0.06);
        }

        .create-product-card h6 {
            margin-bottom: 16px;
            color: #212545;
            font-weight: 700;
        }

        .shared-product-image {
            padding: 16px 18px;
            border: 1px dashed #b9c3d1;
            border-radius: 6px;
            background: #f9fafc;
        }

        .shared-product-image label {
            margin-bottom: 8px;
            color: #202638;
            font-weight: 600;
        }

        .products-body .modal-body {
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .products-body .footer_part {
            padding: 0 20px 20px;
            color: #667085;
            font-size: 12px;
        }

        @media (max-width: 991.98px) {
            .products-body header {
                min-height: 72px;
                padding: 10px 16px 10px 58px;
                flex-direction: row;
                align-items: center;
            }

            .products-body header h1 {
                font-size: 21px;
            }

            .products-body .profile-icons {
                width: auto;
            }

            .products-body .profile-img {
                width: 42px;
                height: 42px;
            }

            .products-body #sidebarToggler {
                top: 18px;
                left: 12px;
                display: flex;
                width: 36px;
                height: 36px;
                align-items: center;
                justify-content: center;
            }

            .products-page.container {
                width: calc(100% - 36px);
                padding-top: 22px;
            }
        }

        @media (max-width: 575.98px) {
            .products-page.container {
                width: calc(100% - 24px);
                padding-top: 18px;
            }

            .products-page-heading {
                margin-bottom: 16px;
            }

            .products-page-heading h1 {
                font-size: 24px;
            }

            .products-toolbar {
                align-items: stretch;
                flex-direction: column;
                gap: 10px;
            }

            .product-search-form {
                max-width: none;
            }

            .product-search-form .btn {
                padding-right: 12px;
                padding-left: 12px;
            }

            .add-product-button {
                align-self: flex-start;
            }

            .products-table {
                min-width: 700px;
            }

            .modal-dialog {
                width: calc(100% - 24px);
                max-width: 480px;
                margin: 12px auto;
            }

            .products-body .modal-content {
                max-height: calc(100vh - 24px);
                max-height: calc(100dvh - 24px);
                border-radius: 8px;
            }

            .products-body .modal-header {
                flex: 0 0 auto;
                padding: 12px 16px;
            }

            .products-body .modal-title {
                font-size: 18px;
            }

            .products-body .modal-body {
                padding: 12px 16px 16px;
            }

            .products-body .modal-body .form-group {
                margin-bottom: 12px;
            }

            .products-body .modal-body label {
                margin-bottom: 4px;
                font-size: 14px;
            }

            .products-body .modal-body .form-control {
                min-height: 42px;
                font-size: 16px;
            }

            .products-body .modal-body input[type="file"] {
                height: auto;
                padding: 6px;
            }

            .products-body .modal-body .btn {
                min-height: 44px;
            }

            .create-product-dialog {
                width: calc(100% - 24px);
                margin: 12px auto;
            }

            .create-product-card {
                padding: 14px;
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-area,
            .print-area * {
                visibility: visible;
            }

            .print-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .products-table-wrap {
                overflow: visible;
                border: 0;
            }
        }
    </style>
</head>
<body class="products-body">
<?php include 'sidenav.php'; ?>
<?php include 'header.php'; ?>
    <main class="container products-page">
        <div class="products-page-heading">
            <p class="products-eyebrow">Inventory</p>
            <h1>Products</h1>
            <p>Search and manage your product catalog.</p>
        </div>

        <div class="products-toolbar no-print">
            <form method="POST" class="product-search-form">
                <label class="sr-only" for="productSearch">Search products</label>
                <input id="productSearch" type="search" name="search" class="form-control" placeholder="Search by product name" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search" aria-hidden="true"></i> Search</button>
            </form>
            <button type="button" class="btn btn-success add-product-button" data-toggle="modal" data-target="#createModal"><i class="fas fa-plus" aria-hidden="true"></i> Add product</button>
        </div>

        <div class="products-table-wrap">
        <table class="table table-striped products-table print-area">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Image</th>
                    <th>Size</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "
        <tr>
            <td>{$row['id']}</td>
            <td>{$row['name']}</td>
            <td><img src='image/{$row['image']}' alt='Product Image'></td>
            <td>{$row['size']}</td>
            <td>{$row['quantity']}</td>
            <td>\${$row['amount']}</td>
            <td>
                <a href='#' class='text-warning' 
                   data-toggle='modal' 
                   data-target='#editModal{$row['id']}' 
                   onclick='confirmEdit(event, {$row['id']})'>
                   <i class='fas fa-edit'></i> <!-- Edit Icon -->
                </a>
                
                <a href='#' class='text-danger' 
                   onclick='confirmDelete(event, {$row['id']})'>
                   <i class='fas fa-trash-alt'></i> <!-- Delete Icon -->
                </a>
            </td>
        </tr>

        <!-- Edit Modal -->
        <div class='modal fade' id='editModal{$row['id']}' tabindex='-1'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title'>Edit Product</h5>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                    </div>
                    <div class='modal-body'>
                        <form method='POST' enctype='multipart/form-data'>
                            <input type='hidden' name='id' value='{$row['id']}'>
                            <div class='form-group'>
                                <label>Product Name</label>
                                <input type='text' name='name' class='form-control' value='{$row['name']}' required>
                            </div>
                            <div class='form-group'>
                                <label>Product Size</label>
                                <input type='text' name='size' class='form-control' value='{$row['size']}' required>
                            </div>
                            <div class='form-group'>
                                <label>Quantity</label>
                                <input type='number' name='quantity' class='form-control' value='{$row['quantity']}' required>
                            </div>
                            <div class='form-group'>
                                <label>Amount</label>
                                <input type='number' step='0.01' name='amount' class='form-control' value='{$row['amount']}' required>
                            </div>
                            <div class='form-group'>
                                <label>Product Image</label>
                                <input type='file' name='image' class='form-control'>
                            </div>
                            <button type='submit' name='edit' class='btn btn-primary'>Update Product</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class='modal fade' id='deleteModal{$row['id']}' tabindex='-1'>
            <div class='modal-dialog'>
                <div class='modal-content'>
                    <div class='modal-header'>
                        <h5 class='modal-title'>Delete Product</h5>
                        <button type='button' class='close' data-dismiss='modal'>&times;</button>
                    </div>
                    <div class='modal-body'>
                        <p>Are you sure you want to delete this product?</p>
                    </div>
                    <div class='modal-footer'>
                        <form method='POST'>
                            <input type='hidden' name='id' value='{$row['id']}'>
                            <button type='submit' name='delete' class='btn btn-danger'>Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>";
    }
} else {
    echo "<tr><td colspan='7'>No products found</td></tr>";
}
?>

            </tbody>
        </table>
        </div>

  
        <nav aria-label="Page navigation">
            <ul class="pagination no-print">
                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1); } ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php if($page == $i){ echo 'active'; } ?>">
                        <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php if($page >= $totalPages){ echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($page >= $totalPages){ echo '#'; } else { echo "?page=".($page + 1); } ?>">Next</a>
                </li>
            </ul>
        </nav>

        <div class="mb-3 no-print">
        <!-- <button id="printBtn" class="btn btn-info">Print</button> -->
        <i onclick="window.print()" class="fa fa-print" style="font-size:34px;color:dark;cursor:pointer;"></i>
            <i  id="pdfBtn" class="btn btn-danger fa-regular fa-file-pdf"></i>
        
        </div>
    </div>

    
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog create-product-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Two Products</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <?php for ($productIndex = 0; $productIndex < 2; $productIndex++): ?>
                                <div class="col-md-6 mb-3">
                                    <section class="create-product-card">
                                        <h6>Product <?= $productIndex + 1 ?></h6>
                                        <div class="form-group">
                                            <label for="productName<?= $productIndex ?>">Product Name</label>
                                            <input id="productName<?= $productIndex ?>" type="text" name="name[]" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="productSize<?= $productIndex ?>">Product Size</label>
                                            <input id="productSize<?= $productIndex ?>" type="text" name="size[]" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="productQuantity<?= $productIndex ?>">Quantity</label>
                                            <input id="productQuantity<?= $productIndex ?>" type="number" min="0" name="quantity[]" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="productAmount<?= $productIndex ?>">Amount</label>
                                            <input id="productAmount<?= $productIndex ?>" type="number" min="0" step="0.01" name="amount[]" class="form-control" required>
                                        </div>
                                    </section>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div class="shared-product-image mb-3">
                            <label for="sharedProductImage">Image shared by both products</label>
                            <input id="sharedProductImage" type="file" name="image" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" name="create" class="btn btn-primary">Add Both Products</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <div class="footer_part no-print">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="footer_iner text-center ">
                    <span class="mr-1"><script>document.write(new Date().getFullYear())</script>©</span> <a href="http://shwasthik.com/" class="" target="_blank">Shwasthik Technologies</a>.
                </div>
            </div>
        </div>
    </div>
</div>

    <script>


    function confirmEdit(event, productId) {
        event.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "Do you want to edit this product?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, edit it!',
            cancelButtonText: 'No, cancel!'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#editModal' + productId).modal('show');
            }
        });
    }

    function confirmDelete(event, productId) {
        event.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "Do you want to delete this product?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type='hidden' name='id' value='${productId}'>
                                  <input type='hidden' name='delete' value='true'>`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }





        // document.getElementById('printBtn').addEventListener('click', function() {
        //     window.print();
        // });


        document.getElementById('pdfBtn').addEventListener('click', function() {
    
    fetch('getAllProducts.php')
        .then(response => response.json())
        .then(data => {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.text("All Product List", 10, 10);
            let y = 20;

            data.forEach(product => {
                doc.text(`ID: ${product.id} | Name: ${product.name} | Size: ${product.size} | Quantity: ${product.quantity} | Amount: $${product.amount}`, 10, y);
                y += 10;
            });

            doc.save("all_products.pdf");
        })
        .catch(error => {
            console.error('Error fetching products:', error);
        });
});

    </script>

<script>
<?php if (!empty($swalMessage)): ?>
    Swal.fire({
        title: '<?= $swalType === 'success' ? 'Success!' : 'Error!' ?>',
        text: '<?= $swalMessage ?>',
        icon: '<?= $swalType ?>'
    }).then(() => {
        <?php if ($redirectNeeded): ?>
            window.location.href = window.location.href.split('?')[0]; 
        <?php endif; ?>
    });
<?php endif; ?>
</script>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</body>
</html>
