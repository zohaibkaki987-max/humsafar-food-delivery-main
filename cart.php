<?php

require_once 'includes/config.php';
require_once 'includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT
cart.id,
cart.quantity,
menu_items.id AS menu_id,
menu_items.restaurant_id,
menu_items.name,
menu_items.description,
menu_items.price,
menu_items.image,
restaurants.name AS restaurant_name,
restaurants.image AS restaurant_image,
restaurants.delivery_time,
restaurants.delivery_fee
FROM cart
JOIN menu_items ON cart.menu_item_id = menu_items.id
JOIN restaurants ON menu_items.restaurant_id = restaurants.id
WHERE cart.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$user_id);
$stmt->execute();

$result = $stmt->get_result();

$cartItems = [];
$totalItems = 0;
$subTotal = 0;
$deliveryFee = 0;
$restaurantID = 0;
$restaurantImage = "";  
$restaurantName = "";
$deliveryTime = "";

while($row = $result->fetch_assoc()){

    if($restaurantID==0){
        $restaurantImage = $row['restaurant_image'];
        $restaurantID = $row['restaurant_id'];
        $restaurantName = $row['restaurant_name'];
        $deliveryTime = $row['delivery_time'];
        $deliveryFee = $row['delivery_fee'];
    }

    $row['subtotal']=$row['price']*$row['quantity'];

    $subTotal += $row['subtotal'];

    $totalItems += $row['quantity'];

    $cartItems[]=$row;

}

$grandTotal = $subTotal + $deliveryFee;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Cart | Humsafar</title>

<link rel="stylesheet" href="css/cart.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body>

<?php include 'includes/header.php'; ?>
<div class="cart-container">

<div class="cart-content">

<div class="cart-items">

<div class="cart-header">

<h1>
<i class="fas fa-shopping-cart"></i>
My Cart
</h1>

<?php if($totalItems>0){ ?>

<p>

<strong><?php echo $totalItems; ?></strong>

item(s) from

<strong><?php echo htmlspecialchars($restaurantName); ?></strong>

</p>

<?php } else { ?>

<p>Your cart is empty.</p>

<?php } ?>

</div>

<?php if($totalItems>0){ ?>

<div class="cart-restaurant-info">

<div class="cart-restaurant-avatar">

<?php if(!empty($restaurantImage)){ ?>

    <img src="assets/images/restaurants/<?php echo htmlspecialchars($restaurantImage); ?>"
         alt="<?php echo htmlspecialchars($restaurantName); ?>"
         style="width:70px;height:70px;border-radius:50%;object-fit:cover;">

<?php } else { ?>

    <i class="fas fa-store"></i>

<?php } ?>

</div>
</div>

<div class="cart-restaurant-details">

<h3><?php echo htmlspecialchars($restaurantName); ?></h3>

<p>Delivery Time : <?php echo $deliveryTime; ?></p>

<p>Delivery Fee : Rs. <?php echo number_format($deliveryFee,2); ?></p>

</div>

</div>

<?php } ?>
<div class="cart-items-list">

<?php if($totalItems > 0){ ?>

<?php foreach($cartItems as $item){ ?>

<div class="cart-item">

    <!-- Item Image -->
    <div class="item-image">

        <?php if(!empty($item['image'])){ ?>

            <img src="assets/images/menu/<?php echo htmlspecialchars($item['image']); ?>"
                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                 style="width:80px;height:80px;object-fit:cover;border-radius:10px;">

        <?php }else{ ?>

            <i class="fas fa-utensils" style="font-size:35px;"></i>

        <?php } ?>

    </div>

    <!-- Item Details -->
    <div class="item-details">

        <h3><?php echo htmlspecialchars($item['name']); ?></h3>

        <p><?php echo htmlspecialchars($item['description']); ?></p>

        <strong>

            Rs. <?php echo number_format($item['price'],2); ?>

        </strong>

    </div>

    <!-- Quantity -->
    <div class="quantity-box">

        <a href="update_cart.php?action=minus&id=<?php echo $item['id']; ?>"
           class="quantity-btn">

            -

        </a>

        <span class="quantity">

            <?php echo $item['quantity']; ?>

        </span>

        <a href="update_cart.php?action=plus&id=<?php echo $item['id']; ?>"
           class="quantity-btn">

            +

        </a>

    </div>

    <!-- Subtotal -->

    <div class="item-total">

        Rs. <?php echo number_format($item['subtotal'],2); ?>

    </div>

    <!-- Remove -->

    <div class="item-remove">

        <a href="remove_from_cart.php?id=<?php echo $item['id']; ?>"

           onclick="return confirm('Remove this item from cart?');">

            <i class="fas fa-trash"></i>

        </a>

    </div>

</div>

<?php } ?>

</div>
<div class="add-more-section">

<a href="restaurant.php?id=<?php echo $restaurantID; ?>"

class="btn-secondary">

<i class="fas fa-plus"></i>

Add More Items

</a>

</div>

<?php }else{ ?>

<div class="empty-cart">

<i class="fas fa-shopping-cart"
style="font-size:80px;color:#bbb;"></i>

<h2>Your Cart is Empty</h2>

<p>

Looks like you haven't added anything yet.

</p>

<br>

<a href="restaurants.php"

class="btn-primary">

Browse Restaurants

</a>

</div>

<?php } ?>

</div>
            <!-- Order Summary -->

            <div class="order-summary">

                <div class="summary-card">

                    <h2>Order Summary</h2>

                    <div class="summary-row">

                        <span>Total Items</span>

                        <span><?php echo $totalItems; ?></span>

                    </div>

                    <div class="summary-row">

                        <span>Subtotal</span>

                        <span>Rs. <?php echo number_format($subTotal,2); ?></span>

                    </div>

                    <div class="summary-row">

                        <span>Delivery Fee</span>

                        <span>Rs. <?php echo number_format($deliveryFee,2); ?></span>

                    </div>

                    <hr>

                    <div class="summary-row total">

                        <strong>Total</strong>

                        <strong>

                            Rs. <?php echo number_format($grandTotal,2); ?>

                        </strong>

                    </div>

                    <?php if($totalItems>0){ ?>

                    <a href="checkout.php" class="btn-checkout">

                        <i class="fas fa-credit-card"></i>

                        Proceed to Checkout

                    </a>

                    <?php } ?>

                </div>

            </div>

        </div>

    </div>

<?php include 'includes/footer.php'; ?>

</body>

</html>