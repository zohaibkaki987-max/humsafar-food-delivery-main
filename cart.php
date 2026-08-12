<?php
/**
 * ============================================================
 * HUMSAFAR FOOD DELIVERY
 * CUSTOMER CART PAGE
 * ============================================================
 *
 * Uses:
 *   - includes/config.php
 *   - includes/session.php
 *   - includes/customer-header.php
 *   - includes/footer.php
 *
 * Existing cart endpoints:
 *   - update_cart.php
 *   - remove_from_cart.php
 *
 * Existing checkout:
 *   - checkout.php
 */

require_once 'includes/config.php';
require_once 'includes/session.php';

/* ============================================================
   LOGIN CHECK
============================================================ */

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* ============================================================
   HELPER
============================================================ */

function cart_h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* ============================================================
   GET CART ITEMS
============================================================ */

$cartItems = [];

$cartStmt = $conn->prepare("
    SELECT
        c.id AS cart_id,
        c.menu_item_id,
        c.quantity,

        m.name,
        m.description,
        m.price,
        m.image,
        m.restaurant_id,

        r.name AS restaurant_name,
        r.image AS restaurant_image,
        r.address AS restaurant_address,
        r.delivery_time,
        r.delivery_fee

    FROM cart c

    INNER JOIN menu_items m
        ON c.menu_item_id = m.id

    INNER JOIN restaurants r
        ON m.restaurant_id = r.id

    WHERE c.user_id = ?

    ORDER BY c.id ASC
");

if (!$cartStmt) {
    die("Unable to load cart.");
}

$cartStmt->bind_param(
    "i",
    $user_id
);

$cartStmt->execute();

$cartResult = $cartStmt->get_result();

while ($row = $cartResult->fetch_assoc()) {

    $row['cart_id'] = (int)$row['cart_id'];
    $row['menu_item_id'] = (int)$row['menu_item_id'];
    $row['quantity'] = (int)$row['quantity'];
    $row['price'] = (float)$row['price'];

    $row['subtotal'] =
        $row['price'] * $row['quantity'];

    $cartItems[] = $row;
}

$cartStmt->close();

/* ============================================================
   BASIC CART VALUES
============================================================ */

$isCartEmpty = empty($cartItems);

$totalItems = 0;
$subtotal = 0;

foreach ($cartItems as $item) {

    $totalItems +=
        (int)$item['quantity'];

    $subtotal +=
        (float)$item['subtotal'];
}

/* ============================================================
   RESTAURANT CHECK
============================================================ */

$restaurantIds = [];

foreach ($cartItems as $item) {

    $restaurantIds[] =
        (int)$item['restaurant_id'];
}

$restaurantIds =
    array_values(
        array_unique($restaurantIds)
    );

$multipleRestaurants =
    count($restaurantIds) > 1;

/* ============================================================
   RESTAURANT INFORMATION
============================================================ */

$restaurantId = 0;
$restaurantName = '';
$restaurantImage = '';
$restaurantAddress = '';
$deliveryTime = '';
$deliveryFee = 0;

if (!$isCartEmpty) {

    $firstRestaurant =
        $cartItems[0];

    $restaurantId =
        (int)$firstRestaurant['restaurant_id'];

    $restaurantName =
        $firstRestaurant['restaurant_name'] ?? '';

    $restaurantImage =
        $firstRestaurant['restaurant_image'] ?? '';

    $restaurantAddress =
        $firstRestaurant['restaurant_address'] ?? '';

    $deliveryTime =
        $firstRestaurant['delivery_time'] ?? '';

    $deliveryFee =
        (float)(
            $firstRestaurant['delivery_fee']
            ?? 0
        );
}

/* ============================================================
   TOTAL
============================================================ */

$discount = 0;

$grandTotal =
    $subtotal +
    $deliveryFee -
    $discount;

/* ============================================================
   GET CUSTOMER ADDRESSES
============================================================ */

$addresses = [];

$addressStmt = $conn->prepare("
    SELECT
        id,
        address_type,
        label,
        full_name,
        phone,
        address,
        city,
        area,
        landmark,
        is_default

    FROM addresses

    WHERE user_id = ?

    ORDER BY
        is_default DESC,
        id DESC
");

if ($addressStmt) {

    $addressStmt->bind_param(
        "i",
        $user_id
    );

    $addressStmt->execute();

    $addressResult =
        $addressStmt->get_result();

    while (
        $addressRow =
        $addressResult->fetch_assoc()
    ) {

        $addresses[] =
            $addressRow;
    }

    $addressStmt->close();
}

/* ============================================================
   DEFAULT ADDRESS
============================================================ */

$selectedAddressId = 0;

foreach ($addresses as $address) {

    if (
        (int)$address['is_default'] === 1
    ) {

        $selectedAddressId =
            (int)$address['id'];

        break;
    }
}

if (
    $selectedAddressId <= 0 &&
    !empty($addresses)
) {

    $selectedAddressId =
        (int)$addresses[0]['id'];
}

/* ============================================================
   PAGE
============================================================ */

require_once 'includes/customer-header.php';

?>

<style>

/* ============================================================
   CART PAGE
============================================================ */

.humsafar-cart-page {

    min-height: calc(100vh - 120px);

    background:
        linear-gradient(
            180deg,
            #fff8fa 0%,
            #ffffff 340px
        );

    padding:
        34px 4%
        70px;
}

.humsafar-cart-wrapper {

    width: 100%;
    max-width: 1450px;

    margin: 0 auto;
}

/* ============================================================
   PAGE TITLE
============================================================ */

.cart-page-heading {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}

.cart-page-heading h1 {

    margin: 0;

    color: #222;

    font-size: 32px;

    font-weight: 900;

    letter-spacing: -.7px;
}

.cart-page-heading p {

    margin:
        7px 0 0;

    color: #777;

    font-size: 13px;
}

.continue-shopping {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #ed0038;

    text-decoration: none;

    font-size: 13px;

    font-weight: 800;

    transition: .2s ease;
}

.continue-shopping:hover {

    color: #c90030;

    transform:
        translateX(-2px);
}

/* ============================================================
   CART LAYOUT
============================================================ */

.cart-layout {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        390px;

    gap: 26px;

    align-items: start;
}

/* ============================================================
   MAIN CART CARD
============================================================ */

.cart-main-card {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 18px;

    box-shadow:
        0 8px 30px
        rgba(45, 20, 30, .06);

    overflow: hidden;
}

/* ============================================================
   RESTAURANT HEADER
============================================================ */

.restaurant-card {

    padding: 20px 22px;

    display: flex;

    align-items: center;

    gap: 14px;

    border-bottom:
        1px solid #eeeeee;

    background:
        linear-gradient(
            90deg,
            #fff7f9,
            #ffffff
        );
}

.restaurant-image {

    width: 58px;
    height: 58px;

    flex: 0 0 58px;

    border-radius: 14px;

    overflow: hidden;

    background: #fff1f5;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 21px;
}

.restaurant-image img {

    width: 100%;
    height: 100%;

    object-fit: cover;
}

.restaurant-info {

    min-width: 0;
}

.restaurant-info h2 {

    margin: 0;

    color: #222;

    font-size: 18px;

    font-weight: 900;
}

.restaurant-meta {

    margin-top: 7px;

    display: flex;

    flex-wrap: wrap;

    gap: 13px;

    color: #777;

    font-size: 11px;

    font-weight: 700;
}

.restaurant-meta span {

    display: inline-flex;

    align-items: center;

    gap: 5px;
}

.restaurant-meta i {

    color: #ed0038;
}

/* ============================================================
   MIXED RESTAURANT WARNING
============================================================ */

.restaurant-warning {

    margin: 18px 20px;

    padding: 14px 15px;

    display: flex;

    align-items: flex-start;

    gap: 11px;

    background: #fff7e8;

    border:
        1px solid #f4d39c;

    border-radius: 12px;

    color: #7a4c00;

    font-size: 12px;

    line-height: 1.5;
}

.restaurant-warning i {

    color: #e79b00;

    margin-top: 2px;
}

.restaurant-warning strong {

    display: block;

    margin-bottom: 2px;

    font-size: 13px;
}

/* ============================================================
   ITEMS HEADER
============================================================ */

.items-heading {

    padding:
        18px 22px
        12px;

    color: #333;

    font-size: 14px;

    font-weight: 900;
}

/* ============================================================
   CART ITEM
============================================================ */

.cart-item {

    margin:
        0 18px
        12px;

    padding: 16px;

    display: grid;

    grid-template-columns:
        88px
        minmax(0, 1fr)
        auto
        auto
        34px;

    align-items: center;

    gap: 15px;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    transition:
        .2s ease;
}

.cart-item:hover {

    border-color:
        #f0b5c5;

    box-shadow:
        0 5px 18px
        rgba(237,0,56,.06);
}

/* ============================================================
   ITEM IMAGE
============================================================ */

.item-image {

    width: 88px;
    height: 78px;

    border-radius: 11px;

    overflow: hidden;

    background: #fff1f5;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 22px;
}

.item-image img {

    width: 100%;
    height: 100%;

    object-fit: cover;
}

/* ============================================================
   ITEM DETAILS
============================================================ */

.item-details {

    min-width: 0;
}

.item-details h3 {

    margin: 0 0 5px;

    color: #222;

    font-size: 15px;

    font-weight: 900;
}

.item-description {

    margin: 0 0 7px;

    color: #888;

    font-size: 11px;

    line-height: 1.45;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;
}

.item-price {

    color: #ed0038;

    font-size: 13px;

    font-weight: 900;
}

/* ============================================================
   QUANTITY
============================================================ */

.quantity-box {

    height: 38px;

    display: inline-flex;

    align-items: center;

    border:
        1px solid #e6e6e6;

    border-radius: 10px;

    overflow: hidden;

    background: #ffffff;
}

.quantity-btn {

    width: 35px;
    height: 38px;

    border: 0;

    background: #fff5f7;

    color: #ed0038;

    cursor: pointer;

    font-size: 16px;

    font-weight: 900;

    transition: .2s ease;
}

.quantity-btn:hover {

    background: #ed0038;

    color: #ffffff;
}

.quantity-value {

    min-width: 34px;

    text-align: center;

    color: #333;

    font-size: 13px;

    font-weight: 900;
}

/* ============================================================
   ITEM TOTAL
============================================================ */

.item-total {

    min-width: 90px;

    text-align: right;

    color: #222;

    font-size: 14px;

    font-weight: 900;
}

/* ============================================================
   REMOVE
============================================================ */

.remove-item {

    width: 34px;
    height: 34px;

    border: 0;

    border-radius: 9px;

    background: #fff2f4;

    color: #ed0038;

    cursor: pointer;

    transition: .2s ease;
}

.remove-item:hover {

    background: #ed0038;

    color: #ffffff;
}

/* ============================================================
   ADD MORE
============================================================ */

.cart-footer-action {

    padding: 8px 22px 22px;
}

.add-more-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        10px 14px;

    border:
        1px solid #ed0038;

    border-radius: 9px;

    color: #ed0038;

    background: #ffffff;

    text-decoration: none;

    font-size: 12px;

    font-weight: 900;

    transition: .2s ease;
}

.add-more-btn:hover {

    color: #ffffff;

    background: #ed0038;
}

/* ============================================================
   SUMMARY
============================================================ */

.order-summary-card {

    position: sticky;

    top: 18px;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 18px;

    padding: 22px;

    box-shadow:
        0 8px 30px
        rgba(45, 20, 30, .07);
}

.summary-title {

    margin: 0 0 20px;

    color: #222;

    font-size: 19px;

    font-weight: 900;
}

.summary-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 13px;

    color: #666;

    font-size: 13px;
}

.summary-row strong {

    color: #222;

    font-weight: 900;
}

.summary-divider {

    height: 1px;

    background: #eeeeee;

    margin:
        17px 0;
}

.summary-total {

    display: flex;

    align-items: center;

    justify-content: space-between;

    color: #222;

    font-size: 17px;

    font-weight: 900;
}

.summary-total span:last-child {

    color: #ed0038;

    font-size: 20px;
}

/* ============================================================
   DELIVERY ADDRESS
============================================================ */

.summary-section {

    margin-top: 23px;
}

.section-title {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 10px;

    color: #333;

    font-size: 13px;

    font-weight: 900;
}

.section-title i {

    color: #ed0038;
}

.address-box {

    padding: 13px;

    background: #fff8fa;

    border:
        1px solid #f1c5d1;

    border-radius: 11px;
}

.address-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 7px;
}

.address-label {

    color: #ed0038;

    font-size: 11px;

    font-weight: 900;
}

.address-selected {

    padding:
        3px 7px;

    border-radius: 20px;

    background: #ed0038;

    color: #ffffff;

    font-size: 8px;

    font-weight: 900;

    text-transform: uppercase;
}

.address-text {

    margin: 0;

    color: #666;

    font-size: 11px;

    line-height: 1.5;
}

.change-address {

    margin-top: 10px;

    border: 0;

    background: transparent;

    color: #ed0038;

    cursor: pointer;

    font-size: 11px;

    font-weight: 900;

    padding: 0;
}

/* ============================================================
   PAYMENT
============================================================ */

.payment-options {

    display: grid;

    gap: 8px;
}

.payment-option {

    position: relative;

    padding: 11px;

    display: flex;

    align-items: center;

    gap: 9px;

    border:
        1px solid #e7e7e7;

    border-radius: 10px;

    cursor: pointer;

    transition: .2s ease;
}

.payment-option:hover {

    border-color: #ef9fb3;

    background: #fff9fb;
}

.payment-option.selected {

    border-color: #ed0038;

    background: #fff4f7;
}

.payment-option input {

    accent-color: #ed0038;
}

.payment-option label {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #444;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;
}

.payment-option label i {

    color: #ed0038;

    width: 18px;

    text-align: center;
}

/* ============================================================
   PROMO
============================================================ */

.promo-box {

    display: flex;

    gap: 7px;
}

.promo-box input {

    width: 100%;

    min-width: 0;

    height: 40px;

    padding:
        0 11px;

    border:
        1px solid #dddddd;

    border-radius: 9px;

    outline: none;

    font-size: 11px;
}

.promo-box input:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.07);
}

.promo-btn {

    flex: 0 0 auto;

    height: 40px;

    padding:
        0 13px;

    border: 0;

    border-radius: 9px;

    background: #ed0038;

    color: #ffffff;

    cursor: pointer;

    font-size: 11px;

    font-weight: 900;
}

.promo-btn:hover {

    background: #d90035;
}

/* ============================================================
   CHECKOUT BUTTON
============================================================ */

.checkout-btn {

    width: 100%;

    min-height: 49px;

    margin-top: 22px;

    border: 0;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #c90031
        );

    color: #ffffff;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    font-size: 13px;

    font-weight: 900;

    box-shadow:
        0 8px 20px
        rgba(237,0,56,.20);

    transition: .2s ease;
}

.checkout-btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 10px 25px
        rgba(237,0,56,.27);
}

.checkout-btn:disabled {

    background: #cccccc;

    box-shadow: none;

    cursor: not-allowed;

    transform: none;
}

/* ============================================================
   SECURITY
============================================================ */

.security-note {

    margin-top: 13px;

    text-align: center;

    color: #999;

    font-size: 9px;
}

.security-note i {

    color: #48a868;

    margin-right: 4px;
}

/* ============================================================
   EMPTY CART
============================================================ */

.empty-cart-box {

    padding:
        75px 25px;

    text-align: center;
}

.empty-cart-icon {

    width: 88px;
    height: 88px;

    margin:
        0 auto 20px;

    border-radius: 50%;

    background: #fff1f5;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 34px;
}

.empty-cart-box h2 {

    margin: 0 0 8px;

    color: #222;

    font-size: 24px;

    font-weight: 900;
}

.empty-cart-box p {

    margin: 0 auto 22px;

    max-width: 420px;

    color: #888;

    font-size: 13px;

    line-height: 1.6;
}

.browse-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        12px 19px;

    border-radius: 10px;

    background: #ed0038;

    color: #ffffff;

    text-decoration: none;

    font-size: 12px;

    font-weight: 900;

    transition: .2s ease;
}

.browse-btn:hover {

    background: #d90035;
}

/* ============================================================
   MODAL
============================================================ */

.address-modal {

    position: fixed;

    inset: 0;

    z-index: 5000;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(20,10,15,.58);

    backdrop-filter:
        blur(4px);
}

.address-modal.open {

    display: flex;
}

.address-modal-card {

    width: 100%;

    max-width: 560px;

    max-height: 88vh;

    overflow-y: auto;

    background: #ffffff;

    border-radius: 18px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.25);
}

.address-modal-header {

    padding: 18px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom:
        1px solid #eeeeee;
}

.address-modal-header h3 {

    margin: 0;

    color: #222;

    font-size: 17px;

    font-weight: 900;
}

.close-address-modal {

    width: 34px;
    height: 34px;

    border: 0;

    border-radius: 50%;

    background: #fff1f5;

    color: #ed0038;

    cursor: pointer;

    font-size: 18px;
}

.address-list {

    padding: 16px;
}

.address-option {

    width: 100%;

    margin-bottom: 10px;

    padding: 14px;

    display: flex;

    align-items: flex-start;

    gap: 11px;

    border:
        1px solid #e7e7e7;

    border-radius: 12px;

    background: #ffffff;

    cursor: pointer;

    transition: .2s ease;
}

.address-option:hover {

    border-color: #ef9fb3;

    background: #fffafb;
}

.address-option.selected {

    border-color: #ed0038;

    background: #fff5f7;
}

.address-option input {

    margin-top: 3px;

    accent-color: #ed0038;
}

.address-option-content {

    flex: 1;
}

.address-option-title {

    margin: 0 0 5px;

    color: #333;

    font-size: 13px;

    font-weight: 900;
}

.address-option-text {

    margin: 0;

    color: #777;

    font-size: 11px;

    line-height: 1.5;
}

.address-modal-actions {

    padding:
        16px 20px;

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    border-top:
        1px solid #eeeeee;
}

.modal-cancel {

    min-height: 40px;

    padding:
        0 15px;

    border:
        1px solid #dddddd;

    border-radius: 9px;

    background: #ffffff;

    color: #666;

    cursor: pointer;

    font-size: 11px;

    font-weight: 800;
}

.modal-use {

    min-height: 40px;

    padding:
        0 17px;

    border: 0;

    border-radius: 9px;

    background: #ed0038;

    color: #ffffff;

    cursor: pointer;

    font-size: 11px;

    font-weight: 900;
}

/* ============================================================
   MESSAGE
============================================================ */

.cart-message {

    position: fixed;

    left: 50%;

    bottom: 25px;

    z-index: 6000;

    transform:
        translateX(-50%);

    max-width:
        min(90vw, 500px);

    padding:
        12px 18px;

    border-radius: 10px;

    color: #ffffff;

    font-size: 12px;

    font-weight: 800;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,.18);

    display: none;
}

.cart-message.success {

    background: #218c4b;
}

.cart-message.error {

    background: #d93045;
}

/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1050px) {

    .cart-layout {

        grid-template-columns:
            minmax(0, 1fr)
            340px;

        gap: 18px;
    }

    .cart-item {

        grid-template-columns:
            75px
            minmax(0, 1fr)
            auto;

        gap: 12px;
    }

    .item-image {

        width: 75px;
        height: 70px;
    }

    .item-total {

        grid-column: 2;

        text-align: left;
    }

    .remove-item {

        grid-column: 3;

        grid-row: 1;
    }
}

@media (max-width: 800px) {

    .humsafar-cart-page {

        padding:
            25px 3.5%
            50px;
    }

    .cart-layout {

        grid-template-columns: 1fr;
    }

    .order-summary-card {

        position: static;
    }

    .cart-page-heading {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;
    }
}

@media (max-width: 560px) {

    .cart-page-heading h1 {

        font-size: 26px;
    }

    .restaurant-card {

        padding: 16px;
    }

    .cart-item {

        grid-template-columns:
            68px
            minmax(0, 1fr)
            30px;

        padding: 12px;

        margin:
            0 10px
            10px;
    }

    .item-image {

        width: 68px;
        height: 64px;
    }

    .quantity-box {

        grid-column: 2;

        justify-self: start;
    }

    .item-total {

        grid-column: 2;

        margin-top: -2px;
    }

    .remove-item {

        grid-column: 3;

        grid-row: 1;
    }

    .item-details h3 {

        font-size: 13px;
    }

    .item-description {

        font-size: 10px;
    }

    .order-summary-card {

        padding: 18px;
    }
}

</style>

<main class="humsafar-cart-page">

    <div class="humsafar-cart-wrapper">

        <!-- ====================================================
             PAGE HEADING
        ===================================================== -->

        <div class="cart-page-heading">

            <div>

                <h1>
                    Your Cart
                </h1>

                <p>

                    <?php if (!$isCartEmpty): ?>

                        <?php echo $totalItems; ?>

                        <?php
                        echo
                            $totalItems == 1
                            ? ' item'
                            : ' items';
                        ?>

                        ready for checkout

                    <?php else: ?>

                        Your cart is currently empty.

                    <?php endif; ?>

                </p>

            </div>

            <a
                href="restaurants.php"
                class="continue-shopping"
            >

                <i class="fas fa-arrow-left"></i>

                Continue Shopping

            </a>

        </div>


        <?php if ($isCartEmpty): ?>

            <!-- =================================================
                 EMPTY CART
            ================================================== -->

            <div class="cart-main-card">

                <div class="empty-cart-box">

                    <div class="empty-cart-icon">

                        <i class="fas fa-basket-shopping"></i>

                    </div>

                    <h2>
                        Your Cart is Empty
                    </h2>

                    <p>
                        Looks like you haven't added
                        anything to your cart yet.
                        Explore restaurants and order
                        your favourite food.
                    </p>

                    <a
                        href="restaurants.php"
                        class="browse-btn"
                    >

                        <i class="fas fa-store"></i>

                        Browse Restaurants

                    </a>

                </div>

            </div>


        <?php else: ?>

            <!-- =================================================
                 CART LAYOUT
            ================================================== -->

            <div class="cart-layout">

                <!-- =================================================
                     LEFT
                ================================================== -->

                <section class="cart-main-card">

                    <!-- RESTAURANT -->

                    <div class="restaurant-card">

                        <div class="restaurant-image">

                            <?php if (!empty($restaurantImage)): ?>

                                <img
                                    src="assets/images/restaurants/<?php echo cart_h($restaurantImage); ?>"
                                    alt="<?php echo cart_h($restaurantName); ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <i class="fas fa-store"></i>

                            <?php endif; ?>

                        </div>


                        <div class="restaurant-info">

                            <h2>
                                <?php
                                echo cart_h(
                                    $restaurantName
                                );
                                ?>
                            </h2>

                            <div class="restaurant-meta">

                                <?php if (!empty($deliveryTime)): ?>

                                    <span>

                                        <i class="fas fa-clock"></i>

                                        <?php
                                        echo cart_h(
                                            $deliveryTime
                                        );
                                        ?>

                                    </span>

                                <?php endif; ?>


                                <span>

                                    <i class="fas fa-motorcycle"></i>

                                    Delivery Fee:
                                    Rs.
                                    <?php
                                    echo number_format(
                                        $deliveryFee,
                                        0
                                    );
                                    ?>

                                </span>

                            </div>

                        </div>

                    </div>


                    <?php if ($multipleRestaurants): ?>

                        <!-- =========================================
                             MULTIPLE RESTAURANT WARNING
                        ========================================== -->

                        <div class="restaurant-warning">

                            <i class="fas fa-triangle-exclamation"></i>

                            <div>

                                <strong>
                                    Multiple restaurants in cart
                                </strong>

                                Your current checkout system
                                supports one restaurant per order.
                                Please remove items from other
                                restaurants before continuing.

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- ITEMS HEADING -->

                    <div class="items-heading">

                        Your Items

                    </div>


                    <!-- =================================================
                         ITEMS
                    ================================================== -->

                    <?php foreach ($cartItems as $item): ?>

                        <article class="cart-item">

                            <!-- ITEM IMAGE -->

                            <div class="item-image">

                                <?php if (!empty($item['image'])): ?>

                                    <img
                                        src="assets/images/menu/<?php echo cart_h($item['image']); ?>"
                                        alt="<?php echo cart_h($item['name']); ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <i class="fas fa-utensils"></i>

                                <?php endif; ?>

                            </div>


                            <!-- ITEM DETAILS -->

                            <div class="item-details">

                                <h3>

                                    <?php
                                    echo cart_h(
                                        $item['name']
                                    );
                                    ?>

                                </h3>


                                <?php if (!empty($item['description'])): ?>

                                    <p class="item-description">

                                        <?php
                                        echo cart_h(
                                            $item['description']
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>


                                <div class="item-price">

                                    Rs.
                                    <?php
                                    echo number_format(
                                        $item['price'],
                                        2
                                    );
                                    ?>

                                    <span
                                        style="
                                            color:#999;
                                            font-size:10px;
                                            font-weight:600;
                                        "
                                    >
                                        each
                                    </span>

                                </div>

                            </div>


                            <!-- QUANTITY -->

                            <div class="quantity-box">

                                <button
                                    type="button"
                                    class="quantity-btn"
                                    onclick="updateQuantity(
                                        <?php echo (int)$item['cart_id']; ?>,
                                        <?php echo max(
                                            1,
                                            (int)$item['quantity'] - 1
                                        ); ?>
                                    )"
                                    aria-label="Decrease quantity"
                                    <?php
                                    if (
                                        (int)$item['quantity'] <= 1
                                    ) {
                                        echo 'disabled style="opacity:.45;cursor:not-allowed;"';
                                    }
                                    ?>
                                >
                                    −
                                </button>


                                <span class="quantity-value">

                                    <?php
                                    echo (int)$item['quantity'];
                                    ?>

                                </span>


                                <button
                                    type="button"
                                    class="quantity-btn"
                                    onclick="updateQuantity(
                                        <?php echo (int)$item['cart_id']; ?>,
                                        <?php echo (int)$item['quantity'] + 1; ?>
                                    )"
                                    aria-label="Increase quantity"
                                >
                                    +
                                </button>

                            </div>


                            <!-- ITEM TOTAL -->

                            <div class="item-total">

                                Rs.
                                <?php
                                echo number_format(
                                    $item['subtotal'],
                                    2
                                );
                                ?>

                            </div>


                            <!-- REMOVE -->

                            <button
                                type="button"
                                class="remove-item"
                                onclick="removeCartItem(
                                    <?php echo (int)$item['cart_id']; ?>
                                )"
                                title="Remove item"
                                aria-label="Remove item"
                            >

                                <i class="fas fa-trash"></i>

                            </button>

                        </article>

                    <?php endforeach; ?>


                    <!-- ADD MORE -->

                    <div class="cart-footer-action">

                        <a
                            href="restaurants.php"
                            class="add-more-btn"
                        >

                            <i class="fas fa-plus"></i>

                            Add More Items

                        </a>

                    </div>

                </section>


                <!-- =================================================
                     RIGHT SUMMARY
                ================================================== -->

                <aside class="order-summary-card">

                    <h2 class="summary-title">
                        Order Summary
                    </h2>


                    <!-- SUBTOTAL -->

                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>

                            Rs.
                            <?php
                            echo number_format(
                                $subtotal,
                                2
                            );
                            ?>

                        </strong>

                    </div>


                    <!-- DELIVERY -->

                    <div class="summary-row">

                        <span>

                            Delivery Fee

                        </span>

                        <strong>

                            <?php if ($deliveryFee > 0): ?>

                                Rs.
                                <?php
                                echo number_format(
                                    $deliveryFee,
                                    2
                                );
                                ?>

                            <?php else: ?>

                                FREE

                            <?php endif; ?>

                        </strong>

                    </div>


                    <!-- DISCOUNT -->

                    <?php if ($discount > 0): ?>

                        <div class="summary-row">

                            <span>
                                Discount
                            </span>

                            <strong
                                style="color:#218c4b;"
                            >

                                − Rs.
                                <?php
                                echo number_format(
                                    $discount,
                                    2
                                );
                                ?>

                            </strong>

                        </div>

                    <?php endif; ?>


                    <div class="summary-divider"></div>


                    <!-- TOTAL -->

                    <div class="summary-total">

                        <span>
                            Total
                        </span>

                        <span>

                            Rs.
                            <?php
                            echo number_format(
                                $grandTotal,
                                2
                            );
                            ?>

                        </span>

                    </div>


                    <!-- =================================================
                         DELIVERY ADDRESS
                    ================================================== -->

                    <div class="summary-section">

                        <div class="section-title">

                            <i class="fas fa-location-dot"></i>

                            Delivery Address

                        </div>


                        <div class="address-box">

                            <?php

                            $selectedAddress = null;

                            foreach (
                                $addresses
                                as $address
                            ) {

                                if (
                                    (int)$address['id']
                                    ===
                                    $selectedAddressId
                                ) {

                                    $selectedAddress =
                                        $address;

                                    break;
                                }
                            }

                            ?>

                            <div class="address-top">

                                <span
                                    class="address-label"
                                    id="selectedAddressLabel"
                                >

                                    <?php
                                    if (
                                        $selectedAddress
                                    ) {

                                        echo cart_h(
                                            !empty(
                                                $selectedAddress['label']
                                            )
                                            ? $selectedAddress['label']
                                            : ucfirst(
                                                $selectedAddress[
                                                    'address_type'
                                                ]
                                            )
                                        );

                                    } else {

                                        echo 'No Address';

                                    }
                                    ?>

                                </span>


                                <?php if ($selectedAddress): ?>

                                    <span
                                        class="address-selected"
                                    >
                                        Selected
                                    </span>

                                <?php endif; ?>

                            </div>


                            <p
                                class="address-text"
                                id="selectedAddressText"
                            >

                                <?php if ($selectedAddress): ?>

                                    <?php
                                    echo cart_h(
                                        $selectedAddress['address']
                                    );
                                    ?>

                                    <?php if (
                                        !empty(
                                            $selectedAddress['area']
                                        )
                                    ): ?>

                                        ,
                                        <?php
                                        echo cart_h(
                                            $selectedAddress['area']
                                        );
                                        ?>

                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $selectedAddress['city']
                                        )
                                    ): ?>

                                        ,
                                        <?php
                                        echo cart_h(
                                            $selectedAddress['city']
                                        );
                                        ?>

                                    <?php endif; ?>

                                <?php else: ?>

                                    Please select a delivery address.

                                <?php endif; ?>

                            </p>


                            <button
                                type="button"
                                class="change-address"
                                onclick="openAddressModal()"
                            >

                                <i class="fas fa-pen"></i>

                                Change Address

                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                         PAYMENT
                    ================================================== -->

                    <div class="summary-section">

                        <div class="section-title">

                            <i class="fas fa-credit-card"></i>

                            Payment Method

                        </div>


                        <div class="payment-options">

                            <!-- COD -->

                            <div
                                class="payment-option selected"
                                onclick="selectPayment(this)"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="cash_on_delivery"
                                    id="payment_cod"
                                    checked
                                >

                                <label
                                    for="payment_cod"
                                >

                                    <i
                                        class="fas fa-money-bill-wave"
                                    ></i>

                                    Cash on Delivery

                                </label>

                            </div>


                            <!-- CARD -->

                            <div
                                class="payment-option"
                                onclick="selectPayment(this)"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="card"
                                    id="payment_card"
                                >

                                <label
                                    for="payment_card"
                                >

                                    <i
                                        class="fas fa-credit-card"
                                    ></i>

                                    Debit / Credit Card

                                </label>

                            </div>


                            <!-- ONLINE -->

                            <div
                                class="payment-option"
                                onclick="selectPayment(this)"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="online"
                                    id="payment_online"
                                >

                                <label
                                    for="payment_online"
                                >

                                    <i
                                        class="fas fa-wallet"
                                    ></i>

                                    Digital Wallet

                                </label>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         PROMO
                    ================================================== -->

                    <div class="summary-section">

                        <div class="section-title">

                            <i class="fas fa-ticket"></i>

                            Promo Code

                        </div>


                        <div class="promo-box">

                            <input
                                type="text"
                                id="promo_code"
                                placeholder="Enter promo code"
                                autocomplete="off"
                            >

                            <button
                                type="button"
                                class="promo-btn"
                                onclick="applyPromoCode()"
                            >
                                Apply
                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                         CHECKOUT
                    ================================================== -->

                    <button
                        type="button"
                        class="checkout-btn"
                        onclick="proceedToCheckout()"
                        <?php
                        if ($multipleRestaurants) {
                            echo 'disabled';
                        }
                        ?>
                    >

                        <i class="fas fa-lock"></i>

                        <?php
                        echo $multipleRestaurants
                            ? 'Fix Cart First'
                            : 'Proceed to Checkout';
                        ?>

                    </button>


                    <div class="security-note">

                        <i class="fas fa-shield-halved"></i>

                        Secure checkout with Humsafar

                    </div>

                </aside>

            </div>

        <?php endif; ?>

    </div>

</main>


<!-- ============================================================
     ADDRESS MODAL
============================================================ -->

<div
    class="address-modal"
    id="addressModal"
    aria-hidden="true"
>

    <div class="address-modal-card">

        <div class="address-modal-header">

            <h3>
                Select Delivery Address
            </h3>

            <button
                type="button"
                class="close-address-modal"
                onclick="closeAddressModal()"
                aria-label="Close"
            >
                ×
            </button>

        </div>


        <div class="address-list">

            <?php if (!empty($addresses)): ?>

                <?php foreach (
                    $addresses
                    as $address
                ): ?>

                    <?php

                    $addressLabel =
                        !empty(
                            $address['label']
                        )
                        ? $address['label']
                        : ucfirst(
                            $address['address_type']
                        );

                    $fullAddress =
                        $address['address'];

                    if (
                        !empty(
                            $address['area']
                        )
                    ) {

                        $fullAddress .=
                            ', ' .
                            $address['area'];
                    }

                    if (
                        !empty(
                            $address['city']
                        )
                    ) {

                        $fullAddress .=
                            ', ' .
                            $address['city'];
                    }

                    ?>

                    <div
                        class="address-option <?php
                        echo (
                            (int)$address['id']
                            ===
                            $selectedAddressId
                        )
                            ? 'selected'
                            : '';
                        ?>"
                        data-address-id="<?php
                        echo (int)$address['id'];
                        ?>"
                        data-address-label="<?php
                        echo cart_h($addressLabel);
                        ?>"
                        data-address-text="<?php
                        echo cart_h($fullAddress);
                        ?>"
                        onclick="selectAddress(this)"
                    >

                        <input
                            type="radio"
                            name="delivery_address"
                            value="<?php
                            echo (int)$address['id'];
                            ?>"
                            <?php
                            if (
                                (int)$address['id']
                                ===
                                $selectedAddressId
                            ) {
                                echo 'checked';
                            }
                            ?>
                        >


                        <div class="address-option-content">

                            <h4
                                class="address-option-title"
                            >

                                <i
                                    class="fas
                                    <?php
                                    if (
                                        $address['address_type']
                                        === 'home'
                                    ) {
                                        echo 'fa-house';
                                    } elseif (
                                        $address['address_type']
                                        === 'work'
                                    ) {
                                        echo 'fa-briefcase';
                                    } else {
                                        echo 'fa-location-dot';
                                    }
                                    ?>"
                                ></i>

                                <?php
                                echo cart_h(
                                    $addressLabel
                                );
                                ?>

                            </h4>


                            <p
                                class="address-option-text"
                            >

                                <?php
                                echo cart_h(
                                    $fullAddress
                                );
                                ?>

                                <?php if (
                                    !empty(
                                        $address['landmark']
                                    )
                                ): ?>

                                    <br>

                                    Landmark:
                                    <?php
                                    echo cart_h(
                                        $address['landmark']
                                    );
                                    ?>

                                <?php endif; ?>

                            </p>

                        </div>

                    </div>

                <?php endforeach; ?>


            <?php else: ?>

                <div
                    style="
                        text-align:center;
                        padding:35px 15px;
                        color:#777;
                        font-size:12px;
                    "
                >

                    <i
                        class="fas fa-location-dot"
                        style="
                            color:#ed0038;
                            font-size:28px;
                            margin-bottom:12px;
                        "
                    ></i>

                    <div
                        style="
                            font-weight:800;
                            color:#333;
                            margin-bottom:5px;
                        "
                    >
                        No saved address
                    </div>

                    <div>
                        Please add a delivery address
                        before checkout.
                    </div>

                    <br>

                    <a
                        href="my-account.php#addresses"
                        style="
                            color:#ed0038;
                            font-weight:800;
                            text-decoration:none;
                        "
                    >
                        Manage Addresses
                    </a>

                </div>

            <?php endif; ?>

        </div>


        <div class="address-modal-actions">

            <button
                type="button"
                class="modal-cancel"
                onclick="closeAddressModal()"
            >
                Cancel
            </button>


            <button
                type="button"
                class="modal-use"
                onclick="useSelectedAddress()"
            >

                <i class="fas fa-check"></i>

                Use This Address

            </button>

        </div>

    </div>

</div>


<!-- ============================================================
     MESSAGES
============================================================ -->

<div
    id="cartSuccess"
    class="cart-message success"
></div>

<div
    id="cartError"
    class="cart-message error"
></div>


<script>

/* ============================================================
   UPDATE QUANTITY
============================================================ */

function updateQuantity(cartId, quantity)
{
    quantity = parseInt(quantity);

    if (
        isNaN(quantity) ||
        quantity < 1
    ) {
        return;
    }

    fetch(
        'update_cart.php',
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded'
            },

            body:
                'cart_id=' +
                encodeURIComponent(cartId) +
                '&quantity=' +
                encodeURIComponent(quantity)
        }
    )
    .then(function(response) {

        if (!response.ok) {
            throw new Error(
                'Unable to update cart.'
            );
        }

        return response.text();

    })
    .then(function() {

        window.location.href =
            'cart.php?updated=' +
            Date.now();

    })
    .catch(function(error) {

        console.error(error);

        showCartError(
            'Unable to update quantity. Please try again.'
        );

    });
}


/* ============================================================
   REMOVE ITEM
============================================================ */

function removeCartItem(cartId)
{
    if (
        !confirm(
            'Are you sure you want to remove this item?'
        )
    ) {
        return;
    }

    window.location.href =
        'remove_from_cart.php?id=' +
        encodeURIComponent(cartId);
}


/* ============================================================
   PAYMENT
============================================================ */

function selectPayment(element)
{
    var radio =
        element.querySelector(
            'input[type="radio"]'
        );

    if (radio) {
        radio.checked = true;
    }

    document
        .querySelectorAll(
            '.payment-option'
        )
        .forEach(function(option) {

            option.classList.remove(
                'selected'
            );

        });

    element.classList.add(
        'selected'
    );
}


/* ============================================================
   ADDRESS MODAL
============================================================ */

var selectedAddressId =
    <?php
    echo (int)$selectedAddressId;
    ?>;


function openAddressModal()
{
    var modal =
        document.getElementById(
            'addressModal'
        );

    if (!modal) {
        return;
    }

    modal.classList.add('open');

    modal.setAttribute(
        'aria-hidden',
        'false'
    );
}


function closeAddressModal()
{
    var modal =
        document.getElementById(
            'addressModal'
        );

    if (!modal) {
        return;
    }

    modal.classList.remove(
        'open'
    );

    modal.setAttribute(
        'aria-hidden',
        'true'
    );
}


/* ============================================================
   SELECT ADDRESS
============================================================ */

function selectAddress(element)
{
    document
        .querySelectorAll(
            '.address-option'
        )
        .forEach(function(option) {

            option.classList.remove(
                'selected'
            );

            var radio =
                option.querySelector(
                    'input[type="radio"]'
                );

            if (radio) {
                radio.checked = false;
            }

        });


    element.classList.add(
        'selected'
    );


    var radio =
        element.querySelector(
            'input[type="radio"]'
        );

    if (radio) {
        radio.checked = true;
    }


    selectedAddressId =
        parseInt(
            element.getAttribute(
                'data-address-id'
            )
        );
}


/* ============================================================
   USE ADDRESS
============================================================ */

function useSelectedAddress()
{
    var selected =
        document.querySelector(
            '.address-option.selected'
        );

    if (!selected) {

        showCartError(
            'Please select a delivery address.'
        );

        return;
    }


    selectedAddressId =
        parseInt(
            selected.getAttribute(
                'data-address-id'
            )
        );


    var label =
        selected.getAttribute(
            'data-address-label'
        );


    var addressText =
        selected.getAttribute(
            'data-address-text'
        );


    var labelElement =
        document.getElementById(
            'selectedAddressLabel'
        );

    var textElement =
        document.getElementById(
            'selectedAddressText'
        );


    if (labelElement) {

        labelElement.textContent =
            label || 'Address';
    }


    if (textElement) {

        textElement.textContent =
            addressText || '';
    }


    closeAddressModal();
}


/* ============================================================
   PROMO
============================================================ */

function applyPromoCode()
{
    var input =
        document.getElementById(
            'promo_code'
        );

    if (!input) {
        return;
    }


    var code =
        input.value
            .trim()
            .toUpperCase();


    if (code === '') {

        showCartError(
            'Please enter a promo code.'
        );

        return;
    }


    /*
     * Existing project currently
     * uses WELCOME10 as demo promo.
     *
     * Real promo calculation should
     * later be connected to database.
     */

    if (code === 'WELCOME10') {

        showCartSuccess(
            'Promo code accepted.'
        );

    } else {

        showCartError(
            'Invalid promo code.'
        );

    }
}


/* ============================================================
   PROCEED TO CHECKOUT
============================================================ */

function proceedToCheckout()
{
    <?php if ($multipleRestaurants): ?>

        showCartError(
            'Please keep items from one restaurant in the cart.'
        );

        return;

    <?php endif; ?>


    var selectedPayment =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (!selectedPayment) {

        showCartError(
            'Please select a payment method.'
        );

        return;
    }


    var selectedAddress =
        document.querySelector(
            '.address-option.selected'
        );


    if (!selectedAddress) {

        showCartError(
            'Please select a delivery address.'
        );

        openAddressModal();

        return;
    }


    var addressId =
        parseInt(
            selectedAddress.getAttribute(
                'data-address-id'
            )
        );


    if (
        isNaN(addressId) ||
        addressId <= 0
    ) {

        showCartError(
            'Please select a valid delivery address.'
        );

        return;
    }


    var payment =
        encodeURIComponent(
            selectedPayment.value
        );


    /*
     * Important:
     *
     * checkout.php expects:
     *
     * cash_on_delivery
     * card
     * online
     *
     * So we send the correct value.
     */

    window.location.href =
        'checkout.php?payment=' +
        payment +
        '&address_id=' +
        encodeURIComponent(
            addressId
        );
}


/* ============================================================
   SUCCESS MESSAGE
============================================================ */

function showCartSuccess(message)
{
    var box =
        document.getElementById(
            'cartSuccess'
        );

    if (!box) {
        return;
    }

    box.textContent =
        message;

    box.style.display =
        'block';


    setTimeout(function() {

        box.style.display =
            'none';

    }, 3000);
}


/* ============================================================
   ERROR MESSAGE
============================================================ */

function showCartError(message)
{
    var box =
        document.getElementById(
            'cartError'
        );

    if (!box) {
        return;
    }

    box.textContent =
        message;

    box.style.display =
        'block';


    setTimeout(function() {

        box.style.display =
            'none';

    }, 3500);
}


/* ============================================================
   OUTSIDE MODAL CLICK
============================================================ */

document.addEventListener(
    'click',
    function(event) {

        var modal =
            document.getElementById(
                'addressModal'
            );

        if (
            modal &&
            event.target === modal
        ) {

            closeAddressModal();

        }

    }
);


/* ============================================================
   ESC
============================================================ */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeAddressModal();

        }

    }
);

</script>


<?php

require_once 'includes/footer.php';

?>