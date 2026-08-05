<?php
require_once 'includes/config.php';
require_once 'includes/session.php';

if (!isset($_GET['id'])) {
    die("Restaurant not found.");
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM restaurants WHERE id=? AND status=1");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Restaurant not found.");
}

$restaurant = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant['name']); ?></title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container" style="max-width:1200px;margin:40px auto;">

    <img src="assets/images/restaurants/<?php echo $restaurant['image']; ?>"
     style="width:100%;
            height:400px;
            object-fit:cover;
            border-radius:15px;
            display:block;">

    <h1><?php echo htmlspecialchars($restaurant['name']); ?></h1>

    <p><?php echo htmlspecialchars($restaurant['description']); ?></p>

    <p>⭐ <strong><?php echo $restaurant['rating']; ?></strong></p>

    <p>📍 <?php echo htmlspecialchars($restaurant['address']); ?></p>

    <p>📞 <?php echo htmlspecialchars($restaurant['phone']); ?></p>

    <p>🕒 <?php echo htmlspecialchars($restaurant['delivery_time']); ?></p>

    <p>🚚 Delivery Fee: <strong>Rs. <?php echo $restaurant['delivery_fee']; ?></strong></p>

    <hr>

    <?php include 'includes/load_menu.php'; ?>

<h2>🍽 Menu</h2>

<div class="menu-list">

<?php while($item = $menu->fetch_assoc()) { ?>

<div class="menu-item" style="border:1px solid #ddd;padding:15px;margin-bottom:15px;border-radius:10px;">

    <h3><?php echo htmlspecialchars($item['name']); ?></h3>

    <p><?php echo htmlspecialchars($item['description']); ?></p>

    <h4>Rs. <?php echo $item['price']; ?></h4>

    <a href="add_to_cart.php?id=<?php echo $item['id']; ?>">
    <button>Add to Cart</button>
</a>

</div>

<?php } ?>

</div>

</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>