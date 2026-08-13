<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| CUSTOMER CART PAGE
|--------------------------------------------------------------------------
| File:
| /cart.php
|
| Uses:
| - includes/config.php
| - includes/session.php
| - includes/customer-header.php
|
| Cart:
| - cart
| - menu_items
| - restaurants
|
| Addresses:
| - customer_addresses
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0
) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function cart_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| PROJECT ROOT
|--------------------------------------------------------------------------
|
| This creates the correct path even when the project
| is installed inside:
|
| /humsafar-food-delivery-main/
|
*/

$projectRoot = rtrim(
    dirname($_SERVER['SCRIPT_NAME']),
    '/'
);

if ($projectRoot === '\\' || $projectRoot === '.') {
    $projectRoot = '';
}


/*
|--------------------------------------------------------------------------
| MANAGE ADDRESS URL
|--------------------------------------------------------------------------
*/

$manageAddressUrl =
    $projectRoot .
    '/customer/manage-addresses.php';


/*
|--------------------------------------------------------------------------
| IMAGE HELPERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Restaurant:
| assets/images/restaurants/
|
| Menu item:
| assets/images/menu/
|
| These paths match the existing restaurant.php
| implementation in the repository.
|--------------------------------------------------------------------------
*/

function cartRestaurantImage($image)
{
    $image = trim((string) $image);

    if ($image === '') {
        return '';
    }


    /*
    | Full URL / absolute path
    */

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }


    /*
    | Already contains assets path
    */

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }


    /*
    | Correct repository path
    */

    return
        'assets/images/restaurants/' .
        basename($image);
}


function cartMenuImage($image)
{
    $image = trim((string) $image);

    if ($image === '') {
        return '';
    }


    /*
    | Full URL / absolute path
    */

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }


    /*
    | Already contains assets path
    */

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }


    /*
    | IMPORTANT:
    | The repository's restaurant.php uses:
    |
    | assets/images/menu/
    |
    | NOT menu-items.
    */

    return
        'assets/images/menu/' .
        basename($image);
}


/*
|--------------------------------------------------------------------------
| GET CART ITEMS
|--------------------------------------------------------------------------
*/

$cartItems = [];


$cartSql = "
    SELECT
        c.id AS cart_id,
        c.menu_item_id,
        c.quantity,

        m.name AS item_name,
        m.description AS item_description,
        m.price AS item_price,
        m.image AS item_image,
        m.restaurant_id,

        r.name AS restaurant_name,
        r.image AS restaurant_image,
        r.address AS restaurant_address,
        r.delivery_time,
        r.delivery_fee,
        r.latitude AS restaurant_latitude,
        r.longitude AS restaurant_longitude

    FROM cart c

    INNER JOIN menu_items m
        ON c.menu_item_id = m.id

    INNER JOIN restaurants r
        ON m.restaurant_id = r.id

    WHERE c.user_id = ?

    ORDER BY c.id ASC
";


$cartStmt =
    $conn->prepare($cartSql);


if (!$cartStmt) {

    die(
        'Unable to load cart: ' .
        $conn->error
    );
}


$cartStmt->bind_param(
    'i',
    $userId
);

$cartStmt->execute();


$cartResult =
    $cartStmt->get_result();


while (
    $row =
    $cartResult->fetch_assoc()
) {

    $row['cart_id'] =
        (int) $row['cart_id'];

    $row['menu_item_id'] =
        (int) $row['menu_item_id'];

    $row['quantity'] =
        max(
            1,
            (int) $row['quantity']
        );

    $row['item_price'] =
        (float) $row['item_price'];

    $row['item_subtotal'] =
        $row['item_price'] *
        $row['quantity'];


    $cartItems[] =
        $row;
}


$cartStmt->close();


/*
|--------------------------------------------------------------------------
| BASIC VALUES
|--------------------------------------------------------------------------
*/

$isCartEmpty =
    empty($cartItems);

$totalItems = 0;

$subtotal = 0;


foreach (
    $cartItems
    as $item
) {

    $totalItems +=
        (int) $item['quantity'];

    $subtotal +=
        (float) $item['item_subtotal'];
}


/*
|--------------------------------------------------------------------------
| RESTAURANTS IN CART
|--------------------------------------------------------------------------
*/

$restaurantIds = [];


foreach (
    $cartItems
    as $item
) {

    $restaurantIds[] =
        (int) $item['restaurant_id'];
}


$restaurantIds =
    array_values(
        array_unique(
            $restaurantIds
        )
    );


$multipleRestaurants =
    count($restaurantIds) > 1;


/*
|--------------------------------------------------------------------------
| FIRST RESTAURANT
|--------------------------------------------------------------------------
*/

$restaurantId = 0;

$restaurantName = '';

$restaurantImage = '';

$restaurantAddress = '';

$deliveryTime = '';

$deliveryFee = 0;


if (!$isCartEmpty) {

    $restaurantId =
        (int)
        $cartItems[0]['restaurant_id'];


    $restaurantName =
        $cartItems[0]['restaurant_name']
        ?? 'Restaurant';


    $restaurantImage =
        $cartItems[0]['restaurant_image']
        ?? '';


    $restaurantAddress =
        $cartItems[0]['restaurant_address']
        ?? '';


    $deliveryTime =
        trim(
            (string)
            (
                $cartItems[0]['delivery_time']
                ?? ''
            )
        );


    $deliveryFee =
        (float)
        (
            $cartItems[0]['delivery_fee']
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| MULTIPLE RESTAURANT DELIVERY FEE
|--------------------------------------------------------------------------
*/

if ($multipleRestaurants) {

    $deliveryFee = 0;
}


/*
|--------------------------------------------------------------------------
| DISCOUNT
|--------------------------------------------------------------------------
*/

$discount = 0;


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$grandTotal =
    $subtotal +
    $deliveryFee -
    $discount;


/*
|--------------------------------------------------------------------------
| CUSTOMER ADDRESSES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Same table used by manage-addresses.php
|--------------------------------------------------------------------------
*/

$addresses = [];


$addressSql = "
    SELECT
        id,
        user_id,
        address_title,
        address_line,
        city,
        area,
        phone,
        is_default,
        created_at,
        updated_at

    FROM customer_addresses

    WHERE user_id = ?

    ORDER BY
        is_default DESC,
        id DESC
";


$addressStmt =
    $conn->prepare(
        $addressSql
    );


if ($addressStmt) {

    $addressStmt->bind_param(
        'i',
        $userId
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


/*
|--------------------------------------------------------------------------
| SELECT ADDRESS
|--------------------------------------------------------------------------
*/

$selectedAddressId = 0;

$selectedAddress = null;


foreach (
    $addresses
    as $address
) {

    if (
        (int)
        $address['is_default'] === 1
    ) {

        $selectedAddressId =
            (int) $address['id'];

        $selectedAddress =
            $address;

        break;
    }
}


if (
    $selectedAddressId <= 0 &&
    !empty($addresses)
) {

    $selectedAddressId =
        (int)
        $addresses[0]['id'];

    $selectedAddress =
        $addresses[0];
}


/*
|--------------------------------------------------------------------------
| ADDRESS TEXT
|--------------------------------------------------------------------------
*/

$selectedAddressLabel =
    'No Address';

$selectedAddressText =
    'Please add a delivery address.';

$selectedAddressPhone =
    '';


if ($selectedAddress) {

    $selectedAddressLabel =
        $selectedAddress['address_title']
        ?: 'Address';


    $parts = [];


    if (
        !empty(
            $selectedAddress['address_line']
        )
    ) {

        $parts[] =
            $selectedAddress[
                'address_line'
            ];
    }


    if (
        !empty(
            $selectedAddress['area']
        )
    ) {

        $parts[] =
            $selectedAddress[
                'area'
            ];
    }


    if (
        !empty(
            $selectedAddress['city']
        )
    ) {

        $parts[] =
            $selectedAddress[
                'city'
            ];
    }


    $selectedAddressText =
        implode(
            ', ',
            $parts
        );


    $selectedAddressPhone =
        $selectedAddress['phone']
        ?? '';
}


/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
*/

require_once
    __DIR__ .
    '/includes/customer-header.php';

?>


<style>

/* =========================================================
   CART PAGE
========================================================= */

.cart-page {

    min-height:
        calc(100vh - 100px);

    padding:
        35px 4% 70px;

    background:
        linear-gradient(
            180deg,
            #fff7fa 0,
            #ffffff 350px
        );
}


.cart-wrapper {

    width: 100%;

    max-width: 1450px;

    margin: 0 auto;
}


/* =========================================================
   PAGE HEADING
========================================================= */

.cart-heading {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.cart-heading h1 {

    margin: 0;

    color: #ed0038;

    font-size: 32px;

    font-weight: 700;

    letter-spacing: -.4px;
}


.cart-heading p {

    margin:
        7px 0 0;

    color: #777;

    font-size: 15px;

    font-weight: 400;
}


.continue-shopping {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: #ed0038;

    font-size: 14px;

    font-weight: 500;

    transition: .2s ease;
}


.continue-shopping:hover {

    color: #c90030;
}


/* =========================================================
   MAIN GRID
========================================================= */

.cart-layout {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        390px;

    gap: 25px;

    align-items: start;
}


/* =========================================================
   CARD
========================================================= */

.cart-card {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 18px;

    box-shadow:
        0 8px 30px
        rgba(40,15,25,.06);

    overflow: hidden;
}


/* =========================================================
   RESTAURANT HEADER
========================================================= */

.restaurant-header {

    padding:
        20px 22px;

    display: flex;

    align-items: center;

    gap: 15px;

    border-bottom:
        1px solid #eeeeee;

    background:
        linear-gradient(
            90deg,
            #fff5f8,
            #ffffff
        );
}


.restaurant-image {

    width: 62px;

    height: 62px;

    flex-shrink: 0;

    border-radius: 13px;

    overflow: hidden;

    background: #fff0f4;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 23px;
}


.restaurant-image img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: cover;
}


.restaurant-info {

    min-width: 0;
}


.restaurant-info h2 {

    margin: 0;

    color: #222;

    font-size: 19px;

    font-weight: 600;
}


.restaurant-address {

    margin-top: 5px;

    color: #777;

    font-size: 13px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.restaurant-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 14px;

    margin-top: 7px;

    color: #777;

    font-size: 13px;

    font-weight: 400;
}


.restaurant-meta span {

    display: inline-flex;

    align-items: center;

    gap: 5px;
}


.restaurant-meta i {

    color: #ed0038;
}


/* =========================================================
   WARNING
========================================================= */

.restaurant-warning {

    margin:
        18px 20px;

    padding: 14px;

    display: flex;

    gap: 10px;

    background: #fff7e8;

    border:
        1px solid #f0d29d;

    border-radius: 11px;

    color: #765000;

    font-size: 13px;

    line-height: 1.55;
}


.restaurant-warning i {

    color: #df9700;

    margin-top: 2px;
}


.restaurant-warning strong {

    display: block;

    margin-bottom: 3px;

    font-weight: 600;
}


/* =========================================================
   ITEMS HEADING
========================================================= */

.items-heading {

    padding:
        19px 22px 12px;

    color: #333;

    font-size: 16px;

    font-weight: 500;
}


/* =========================================================
   CART ITEM
========================================================= */

.cart-item {

    margin:
        0 18px 12px;

    padding: 15px;

    display: grid;

    grid-template-columns:
        92px
        minmax(0,1fr)
        120px
        105px
        36px;

    align-items: center;

    gap: 15px;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    background: #ffffff;

    transition: .2s ease;
}


.cart-item:hover {

    border-color: #f0b6c6;

    box-shadow:
        0 5px 18px
        rgba(237,0,56,.06);
}


/* =========================================================
   ITEM IMAGE
========================================================= */

.item-image {

    width: 92px;

    height: 82px;

    border-radius: 11px;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            #fff0f4,
            #ffffff
        );

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ed0038;

    font-size: 23px;
}


.item-image img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: cover;
}


/* =========================================================
   ITEM DETAILS
========================================================= */

.item-details {

    min-width: 0;
}


.item-details h3 {

    margin:
        0 0 6px;

    color: #222;

    font-size: 17px;

    font-weight: 500;

    line-height: 1.3;
}


.item-description {

    margin: 0;

    color: #777;

    font-size: 13px;

    font-weight: 400;

    line-height: 1.5;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;
}


.item-price {

    margin-top: 7px;

    color: #ed0038;

    font-size: 14px;

    font-weight: 500;
}


/* =========================================================
   QUANTITY
========================================================= */

.quantity-area {

    text-align: center;
}


.quantity-label {

    margin-bottom: 6px;

    color: #888;

    font-size: 12px;

    font-weight: 400;
}


.quantity-form {

    margin: 0;
}


.quantity-box {

    display: inline-flex;

    align-items: center;

    border:
        1px solid #e3e3e3;

    border-radius: 8px;

    overflow: hidden;

    background: #fff;
}


.quantity-btn {

    width: 33px;

    height: 34px;

    border: 0;

    background: #fff;

    color: #ed0038;

    cursor: pointer;

    font-size: 16px;

    font-weight: 500;
}


.quantity-btn:hover {

    background: #fff1f5;
}


.quantity-number {

    min-width: 34px;

    text-align: center;

    color: #333;

    font-size: 13px;

    font-weight: 400;
}


/* =========================================================
   ITEM TOTAL
========================================================= */

.item-total {

    text-align: right;
}


.item-total-label {

    margin-bottom: 4px;

    color: #888;

    font-size: 12px;

    font-weight: 400;
}


.item-total-value {

    color: #222;

    font-size: 16px;

    font-weight: 500;
}


/* =========================================================
   REMOVE
========================================================= */

.remove-item {

    width: 36px;

    height: 36px;

    border: 0;

    border-radius: 8px;

    background: #fff1f4;

    color: #d52949;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    transition: .2s ease;
}


.remove-item:hover {

    background: #ed0038;

    color: #ffffff;
}


/* =========================================================
   ADD MORE
========================================================= */

.cart-footer {

    padding:
        8px 22px 22px;
}


.add-more {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        10px 15px;

    border:
        1px solid #ed0038;

    border-radius: 9px;

    color: #ed0038;

    background: #fff;

    font-size: 13px;

    font-weight: 500;

    text-decoration: none;
}


.add-more:hover {

    background: #ed0038;

    color: #fff;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-card {

    position: sticky;

    top: 18px;

    padding: 22px;

    background: #fff;

    border:
        1px solid #eeeeee;

    border-radius: 18px;

    box-shadow:
        0 8px 30px
        rgba(40,15,25,.07);
}


.summary-title {

    margin:
        0 0 20px;

    color: #222;

    font-size: 20px;

    font-weight: 600;
}


.summary-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 13px;

    color: #666;

    font-size: 14px;

    font-weight: 400;
}


.summary-row strong {

    color: #222;

    font-weight: 500;
}


.summary-divider {

    height: 1px;

    margin:
        17px 0;

    background: #eeeeee;
}


.summary-total {

    display: flex;

    align-items: center;

    justify-content: space-between;

    color: #222;

    font-size: 17px;

    font-weight: 600;
}


.summary-total span:last-child {

    color: #ed0038;

    font-size: 20px;

    font-weight: 600;
}


/* =========================================================
   SECTION TITLE
========================================================= */

.section-title {

    display: flex;

    align-items: center;

    gap: 8px;

    margin:
        22px 0 10px;

    color: #333;

    font-size: 14px;

    font-weight: 500;
}


.section-title i {

    color: #ed0038;
}


/* =========================================================
   ADDRESS
========================================================= */

.address-box {

    padding: 14px;

    background: #fff8fa;

    border:
        1px solid #f1c5d1;

    border-radius: 10px;
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

    font-size: 13px;

    font-weight: 500;
}


.address-selected {

    padding:
        4px 8px;

    border-radius: 20px;

    background: #ed0038;

    color: #fff;

    font-size: 9px;

    font-weight: 500;

    text-transform: uppercase;
}


.address-text {

    margin: 0;

    color: #666;

    font-size: 13px;

    line-height: 1.55;

    font-weight: 400;
}


.address-phone {

    margin-top: 7px;

    color: #777;

    font-size: 12px;
}


.address-phone i {

    color: #ed0038;
}


.change-address {

    margin-top: 11px;

    padding: 0;

    border: 0;

    background: transparent;

    color: #ed0038;

    cursor: pointer;

    font-size: 13px;

    font-weight: 500;
}


.change-address:hover {

    color: #c90030;

    text-decoration: underline;
}


.add-address-link {

    display: inline-block;

    margin-top: 8px;

    color: #ed0038;

    font-size: 13px;

    font-weight: 500;
}


/* =========================================================
   PROMO
========================================================= */

.promo-form {

    display: flex;

    gap: 8px;
}


.promo-input {

    width: 100%;

    height: 42px;

    padding:
        0 12px;

    border:
        1px solid #dddddd;

    border-radius: 8px;

    outline: none;

    color: #444;

    font-size: 13px;
}


.promo-input:focus {

    border-color: #ed0038;
}


.promo-button {

    height: 42px;

    padding:
        0 15px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #fff;

    cursor: pointer;

    font-size: 13px;

    font-weight: 500;
}


.promo-button:hover {

    background: #d90035;
}


/* =========================================================
   CHECKOUT
========================================================= */

.checkout-btn {

    width: 100%;

    min-height: 48px;

    margin-top: 22px;

    border: 0;

    border-radius: 10px;

    background:
        linear-gradient(
            90deg,
            #ed0038,
            #f52c67
        );

    color: #fff;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    font-size: 14px;

    font-weight: 500;
}


.checkout-btn:hover {

    background:
        linear-gradient(
            90deg,
            #d90035,
            #e7255e
        );
}


.checkout-btn.disabled,
.checkout-btn:disabled {

    opacity: .55;

    cursor: not-allowed;
}


.security-note {

    margin-top: 11px;

    text-align: center;

    color: #999;

    font-size: 11px;

    font-weight: 400;
}


.security-note i {

    color: #3a985d;

    margin-right: 4px;
}


/* =========================================================
   EMPTY CART
========================================================= */

.empty-cart {

    padding:
        75px 25px;

    text-align: center;
}


.empty-cart-icon {

    width: 85px;

    height: 85px;

    margin:
        0 auto 18px;

    border-radius: 50%;

    background: #fff0f4;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 34px;
}


.empty-cart h2 {

    margin:
        0 0 9px;

    color: #222;

    font-size: 25px;

    font-weight: 500;
}


.empty-cart p {

    max-width: 430px;

    margin:
        0 auto 23px;

    color: #777;

    font-size: 14px;

    line-height: 1.6;
}


.browse-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        11px 18px;

    border-radius: 9px;

    background: #ed0038;

    color: #fff;

    font-size: 13px;

    font-weight: 500;
}


/* =========================================================
   ADDRESS MODAL
========================================================= */

.address-modal {

    position: fixed;

    inset: 0;

    z-index: 9999;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        rgba(20,10,15,.55);
}


.address-modal.open {

    display: flex;
}


.address-modal-box {

    width: 100%;

    max-width: 560px;

    max-height: 88vh;

    overflow: hidden;

    display: flex;

    flex-direction: column;

    background: #fff;

    border-radius: 17px;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.22);
}


.modal-header {

    padding:
        18px 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom:
        1px solid #eeeeee;
}


.modal-header h2 {

    margin: 0;

    color: #222;

    font-size: 19px;

    font-weight: 600;
}


.modal-close {

    width: 35px;

    height: 35px;

    border: 0;

    border-radius: 8px;

    background: #fff1f4;

    color: #ed0038;

    cursor: pointer;
}


.modal-body {

    padding: 17px;

    overflow-y: auto;
}


.address-option {

    margin-bottom: 10px;

    padding: 14px;

    display: flex;

    align-items: flex-start;

    gap: 11px;

    border:
        1px solid #e5e5e5;

    border-radius: 11px;

    cursor: pointer;

    transition: .2s ease;
}


.address-option:hover {

    border-color: #ef9fb3;

    background: #fff9fb;
}


.address-option.selected {

    border-color: #ed0038;

    background: #fff5f8;
}


.address-option input {

    margin-top: 4px;

    accent-color: #ed0038;
}


.address-option-content {

    flex: 1;

    min-width: 0;
}


.address-option-title {

    color: #333;

    font-size: 14px;

    font-weight: 500;
}


.address-option-text {

    margin-top: 5px;

    color: #777;

    font-size: 13px;

    line-height: 1.5;
}


.address-option-phone {

    margin-top: 6px;

    color: #777;

    font-size: 12px;
}


.address-option-phone i {

    color: #ed0038;
}


.no-address {

    padding:
        25px 15px;

    text-align: center;

    color: #777;

    font-size: 13px;

    line-height: 1.6;
}


.modal-footer {

    padding:
        14px 17px;

    display: flex;

    justify-content: flex-end;

    gap: 9px;

    border-top:
        1px solid #eeeeee;
}


.manage-address-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    min-height: 40px;

    padding:
        0 14px;

    border:
        1px solid #ed0038;

    border-radius: 8px;

    background: #fff;

    color: #ed0038;

    font-size: 12px;

    font-weight: 500;

    text-decoration: none;
}


.manage-address-btn:hover {

    background: #fff1f5;
}


.use-address-btn {

    min-height: 40px;

    padding:
        0 15px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #fff;

    cursor: pointer;

    font-size: 12px;

    font-weight: 500;
}


.use-address-btn:hover {

    background: #d90035;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .cart-layout {

        grid-template-columns:
            minmax(0,1fr)
            340px;
    }


    .cart-item {

        grid-template-columns:
            82px
            minmax(0,1fr)
            105px
            90px
            35px;
    }


    .item-image {

        width: 82px;

        height: 75px;
    }
}


@media (max-width: 850px) {

    .cart-layout {

        grid-template-columns: 1fr;
    }


    .summary-card {

        position: static;
    }


    .cart-heading {

        align-items: flex-start;

        flex-direction: column;
    }
}


@media (max-width: 650px) {

    .cart-page {

        padding:
            25px 3% 50px;
    }


    .cart-heading h1 {

        font-size: 28px;
    }


    .cart-heading p {

        font-size: 14px;
    }


    .cart-item {

        grid-template-columns:
            78px
            minmax(0,1fr)
            35px;

        gap: 11px;

        padding: 12px;
    }


    .item-image {

        width: 78px;

        height: 72px;
    }


    .quantity-area {

        grid-column: 2;

        text-align: left;
    }


    .item-total {

        grid-column: 2;

        text-align: left;
    }


    .remove-item {

        grid-column: 3;

        grid-row: 1;

        align-self: start;
    }


    .item-details h3 {

        font-size: 16px;
    }


    .item-description {

        font-size: 12px;
    }


    .item-price {

        font-size: 13px;
    }
}


@media (max-width: 480px) {

    .restaurant-header {

        padding: 16px;
    }


    .restaurant-image {

        width: 55px;

        height: 55px;
    }


    .restaurant-info h2 {

        font-size: 17px;
    }


    .restaurant-address {

        font-size: 12px;
    }


    .restaurant-meta {

        font-size: 12px;

        gap: 9px;
    }


    .summary-card {

        padding: 18px;
    }


    .modal-footer {

        flex-direction: column;
    }


    .manage-address-btn,
    .use-address-btn {

        width: 100%;
    }
}

</style>


<main class="cart-page">

    <div class="cart-wrapper">


        <!-- =====================================================
             PAGE HEADING
        ====================================================== -->

        <div class="cart-heading">

            <div>

                <h1>
                    Your Cart
                </h1>

                <p>

                    <?php if (!$isCartEmpty): ?>

                        <?php
                        echo (int)
                            $totalItems;
                        ?>

                        <?php
                        echo
                            $totalItems === 1
                                ? ' item'
                                : ' items';
                        ?>

                        in your cart

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

            <div class="cart-card">

                <div class="empty-cart">

                    <div
                        class="empty-cart-icon"
                    >

                        <i
                            class="
                            fas
                            fa-basket-shopping
                            "
                        ></i>

                    </div>


                    <h2>
                        Your Cart is Empty
                    </h2>


                    <p>

                        You haven't added any
                        food items yet.
                        Explore restaurants and
                        order your favourite food.

                    </p>


                    <a
                        href="restaurants.php"
                        class="browse-btn"
                    >

                        <i
                            class="fas fa-store"
                        ></i>

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
                     LEFT CART
                ================================================== -->

                <section
                    class="cart-card"
                >


                    <!-- RESTAURANT -->

                    <div
                        class="restaurant-header"
                    >

                        <div
                            class="restaurant-image"
                        >

                            <?php

                            $restaurantImg =
                                cartRestaurantImage(
                                    $restaurantImage
                                );

                            ?>


                            <?php if (
                                $restaurantImg !== ''
                            ): ?>

                                <img
                                    src="<?php
                                    echo cart_h(
                                        $restaurantImg
                                    );
                                    ?>"
                                    alt="<?php
                                    echo cart_h(
                                        $restaurantName
                                    );
                                    ?>"
                                    loading="lazy"
                                    onerror="
                                        this.style.display='none';
                                        this.parentElement
                                        .querySelector(
                                            '.restaurant-fallback'
                                        )
                                        .style.display='block';
                                    "
                                >


                                <i
                                    class="
                                    fas
                                    fa-store
                                    restaurant-fallback
                                    "
                                    style="
                                        display:none;
                                    "
                                ></i>


                            <?php else: ?>

                                <i
                                    class="
                                    fas
                                    fa-store
                                    "
                                ></i>

                            <?php endif; ?>

                        </div>


                        <div
                            class="restaurant-info"
                        >

                            <h2>

                                <?php
                                echo cart_h(
                                    $restaurantName
                                );
                                ?>

                            </h2>


                            <?php if (
                                $restaurantAddress !== ''
                            ): ?>

                                <div
                                    class="
                                    restaurant-address
                                    "
                                >

                                    <i
                                        class="
                                        fas
                                        fa-location-dot
                                        "
                                    ></i>

                                    <?php
                                    echo cart_h(
                                        $restaurantAddress
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                            <div
                                class="
                                restaurant-meta
                                "
                            >

                                <?php if (
                                    $deliveryTime !== ''
                                ): ?>

                                    <span>

                                        <i
                                            class="
                                            fas
                                            fa-clock
                                            "
                                        ></i>

                                        <?php
                                        echo cart_h(
                                            $deliveryTime
                                        );
                                        ?>

                                    </span>

                                <?php endif; ?>


                                <span>

                                    <i
                                        class="
                                        fas
                                        fa-motorcycle
                                        "
                                    ></i>

                                    Delivery:
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


                    <?php if (
                        $multipleRestaurants
                    ): ?>

                        <div
                            class="
                            restaurant-warning
                            "
                        >

                            <i
                                class="
                                fas
                                fa-triangle-exclamation
                                "
                            ></i>


                            <div>

                                <strong>
                                    Multiple restaurants
                                </strong>

                                Your cart contains
                                items from more than
                                one restaurant.
                                Please keep items
                                from one restaurant
                                before checkout.

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- ITEMS -->

                    <div
                        class="items-heading"
                    >
                        Cart Items
                    </div>


                    <?php foreach (
                        $cartItems
                        as $item
                    ): ?>


                        <?php

                        /*
                        | IMPORTANT:
                        | This now uses:
                        |
                        | assets/images/menu/
                        |
                        | exactly like restaurant.php.
                        */

                        $itemImg =
                            cartMenuImage(
                                $item['item_image']
                            );

                        ?>


                        <article
                            class="cart-item"
                        >


                            <!-- ITEM IMAGE -->

                            <div
                                class="item-image"
                            >

                                <?php if (
                                    $itemImg !== ''
                                ): ?>

                                    <img
                                        src="<?php
                                        echo cart_h(
                                            $itemImg
                                        );
                                        ?>"
                                        alt="<?php
                                        echo cart_h(
                                            $item['item_name']
                                        );
                                        ?>"
                                        loading="lazy"
                                        onerror="
                                            this.style.display='none';
                                            this.parentElement
                                            .querySelector(
                                                '.item-fallback'
                                            )
                                            .style.display='block';
                                        "
                                    >


                                    <i
                                        class="
                                        fas
                                        fa-utensils
                                        item-fallback
                                        "
                                        style="
                                            display:none;
                                        "
                                    ></i>


                                <?php else: ?>

                                    <i
                                        class="
                                        fas
                                        fa-utensils
                                        "
                                    ></i>

                                <?php endif; ?>

                            </div>


                            <!-- DETAILS -->

                            <div
                                class="item-details"
                            >

                                <h3>

                                    <?php
                                    echo cart_h(
                                        $item[
                                            'item_name'
                                        ]
                                    );
                                    ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $item[
                                            'item_description'
                                        ]
                                    )
                                ): ?>

                                    <p
                                        class="
                                        item-description
                                        "
                                    >

                                        <?php
                                        echo cart_h(
                                            $item[
                                                'item_description'
                                            ]
                                        );
                                        ?>

                                    </p>

                                <?php endif; ?>


                                <div
                                    class="item-price"
                                >

                                    Rs.
                                    <?php
                                    echo number_format(
                                        $item[
                                            'item_price'
                                        ],
                                        2
                                    );
                                    ?>

                                    each

                                </div>

                            </div>


                            <!-- QUANTITY -->

                            <div
                                class="quantity-area"
                            >

                                <div
                                    class="
                                    quantity-label
                                    "
                                >
                                    Quantity
                                </div>


                                <form
                                    method="POST"
                                    action="update_cart.php"
                                    class="
                                    quantity-form
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="cart_id"
                                        value="<?php
                                        echo (int)
                                            $item[
                                                'cart_id'
                                            ];
                                        ?>"
                                    >


                                    <div
                                        class="
                                        quantity-box
                                        "
                                    >

                                        <button
                                            type="submit"
                                            name="quantity"
                                            value="<?php
                                            echo max(
                                                1,
                                                (
                                                    (int)
                                                    $item[
                                                        'quantity'
                                                    ]
                                                    - 1
                                                )
                                            );
                                            ?>"
                                            class="
                                            quantity-btn
                                            "
                                            aria-label="
                                            Decrease quantity
                                            "
                                        >
                                            −
                                        </button>


                                        <span
                                            class="
                                            quantity-number
                                            "
                                        >

                                            <?php
                                            echo (int)
                                                $item[
                                                    'quantity'
                                                ];
                                            ?>

                                        </span>


                                        <button
                                            type="submit"
                                            name="quantity"
                                            value="<?php
                                            echo
                                                (
                                                    (int)
                                                    $item[
                                                        'quantity'
                                                    ]
                                                    + 1
                                                );
                                            ?>"
                                            class="
                                            quantity-btn
                                            "
                                            aria-label="
                                            Increase quantity
                                            "
                                        >
                                            +
                                        </button>

                                    </div>

                                </form>

                            </div>


                            <!-- TOTAL -->

                            <div
                                class="item-total"
                            >

                                <div
                                    class="
                                    item-total-label
                                    "
                                >
                                    Total
                                </div>


                                <div
                                    class="
                                    item-total-value
                                    "
                                >

                                    Rs.
                                    <?php
                                    echo number_format(
                                        $item[
                                            'item_subtotal'
                                        ],
                                        2
                                    );
                                    ?>

                                </div>

                            </div>


                            <!-- REMOVE -->

                            <form
                                method="POST"
                                action="remove_from_cart.php"
                            >

                                <input
                                    type="hidden"
                                    name="cart_id"
                                    value="<?php
                                    echo (int)
                                        $item[
                                            'cart_id'
                                        ];
                                    ?>"
                                >


                                <button
                                    type="submit"
                                    class="
                                    remove-item
                                    "
                                    title="Remove item"
                                    onclick="
                                        return confirm(
                                            'Remove this item from your cart?'
                                        );
                                    "
                                >

                                    <i
                                        class="
                                        fas
                                        fa-trash
                                        "
                                    ></i>

                                </button>

                            </form>


                        </article>


                    <?php endforeach; ?>


                    <!-- ADD MORE -->

                    <div
                        class="cart-footer"
                    >

                        <a
                            href="restaurants.php"
                            class="add-more"
                        >

                            <i
                                class="fas fa-plus"
                            ></i>

                            Add More Items

                        </a>

                    </div>


                </section>


                <!-- =================================================
                     RIGHT SUMMARY
                ================================================== -->

                <aside
                    class="summary-card"
                >


                    <h2
                        class="summary-title"
                    >
                        Order Summary
                    </h2>


                    <div
                        class="summary-row"
                    >

                        <span>
                            Items
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


                    <div
                        class="summary-row"
                    >

                        <span>
                            Delivery Fee
                        </span>

                        <strong>

                            Rs.
                            <?php
                            echo number_format(
                                $deliveryFee,
                                2
                            );
                            ?>

                        </strong>

                    </div>


                    <?php if (
                        $discount > 0
                    ): ?>

                        <div
                            class="summary-row"
                        >

                            <span>
                                Discount
                            </span>

                            <strong
                                style="
                                color:#218c4b;
                                "
                            >

                                - Rs.
                                <?php
                                echo number_format(
                                    $discount,
                                    2
                                );
                                ?>

                            </strong>

                        </div>

                    <?php endif; ?>


                    <div
                        class="summary-divider"
                    ></div>


                    <div
                        class="summary-total"
                    >

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
                         ADDRESS
                    ================================================== -->

                    <div
                        class="section-title"
                    >

                        <i
                            class="
                            fas
                            fa-location-dot
                            "
                        ></i>

                        Delivery Address

                    </div>


                    <div
                        class="address-box"
                    >

                        <div
                            class="address-top"
                        >

                            <span
                                class="address-label"
                                id="
                                selectedAddressLabel
                                "
                            >

                                <?php
                                echo cart_h(
                                    $selectedAddressLabel
                                );
                                ?>

                            </span>


                            <?php if (
                                $selectedAddress
                            ): ?>

                                <span
                                    class="
                                    address-selected
                                    "
                                >
                                    Selected
                                </span>

                            <?php endif; ?>

                        </div>


                        <p
                            class="address-text"
                            id="
                            selectedAddressText
                            "
                        >

                            <?php
                            echo cart_h(
                                $selectedAddressText
                            );
                            ?>

                        </p>


                        <?php if (
                            $selectedAddressPhone !== ''
                        ): ?>

                            <div
                                class="
                                address-phone
                                "
                                id="
                                selectedAddressPhone
                                "
                            >

                                <i
                                    class="
                                    fas
                                    fa-phone
                                    "
                                ></i>

                                <?php
                                echo cart_h(
                                    $selectedAddressPhone
                                );
                                ?>

                            </div>

                        <?php else: ?>

                            <div
                                class="
                                address-phone
                                "
                                id="
                                selectedAddressPhone
                                "
                            ></div>

                        <?php endif; ?>


                        <?php if (
                            $selectedAddress
                        ): ?>

                            <button
                                type="button"
                                class="
                                change-address
                                "
                                onclick="
                                    openAddressModal();
                                "
                            >

                                <i
                                    class="
                                    fas
                                    fa-pen
                                    "
                                ></i>

                                Change Address

                            </button>

                        <?php else: ?>

                            <a
                                href="<?php
                                echo cart_h(
                                    $manageAddressUrl
                                );
                                ?>"
                                class="
                                add-address-link
                                "
                            >

                                Add Delivery Address

                            </a>

                        <?php endif; ?>

                    </div>


                    <!-- =================================================
                         PROMO
                    ================================================== -->

                    <div
                        class="section-title"
                    >

                        <i
                            class="
                            fas
                            fa-ticket
                            "
                        ></i>

                        Promo Code

                    </div>


                    <div
                        class="promo-form"
                    >

                        <input
                            type="text"
                            id="promoCode"
                            class="promo-input"
                            placeholder="
                            Enter promo code
                            "
                            autocomplete="off"
                        >


                        <button
                            type="button"
                            class="
                            promo-button
                            "
                            onclick="
                                applyPromo();
                            "
                        >

                            Apply

                        </button>

                    </div>


                    <!-- =================================================
                         CHECKOUT
                    ================================================== -->

                    <?php

                    $checkoutDisabled =
                        $multipleRestaurants ||
                        empty($addresses);

                    ?>


                    <button
                        type="button"
                        id="checkoutButton"
                        class="
                        checkout-btn
                        <?php
                        echo
                            $checkoutDisabled
                            ? 'disabled'
                            : '';
                        ?>
                        "
                        <?php
                        echo
                            $checkoutDisabled
                            ? 'disabled'
                            : '';
                        ?>
                        onclick="
                            proceedToCheckout();
                        "
                    >

                        <i
                            class="
                            fas
                            fa-lock
                            "
                        ></i>

                        Proceed to Checkout

                    </button>


                    <div
                        class="security-note"
                    >

                        <i
                            class="
                            fas
                            fa-shield-halved
                            "
                        ></i>

                        Your order information is
                        securely handled.

                    </div>


                </aside>


            </div>

        <?php endif; ?>


    </div>

</main>


<!-- =========================================================
     ADDRESS MODAL
========================================================== -->

<div
    class="address-modal"
    id="addressModal"
    aria-hidden="true"
>


    <div
        class="address-modal-box"
    >


        <div
            class="modal-header"
        >

            <h2>
                Select Delivery Address
            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="
                    closeAddressModal();
                "
                aria-label="Close"
            >

                <i
                    class="
                    fas
                    fa-xmark
                    "
                ></i>

            </button>

        </div>


        <div
            class="modal-body"
        >


            <?php if (
                empty($addresses)
            ): ?>


                <div
                    class="no-address"
                >

                    You don't have any saved
                    delivery addresses.

                    <br>


                    <a
                        href="<?php
                        echo cart_h(
                            $manageAddressUrl
                        );
                        ?>"
                        class="
                        add-address-link
                        "
                    >

                        Add New Address

                    </a>

                </div>


            <?php else: ?>


                <?php foreach (
                    $addresses
                    as $address
                ): ?>


                    <?php

                    $optionParts = [];


                    if (
                        !empty(
                            $address[
                                'address_line'
                            ]
                        )
                    ) {

                        $optionParts[] =
                            $address[
                                'address_line'
                            ];
                    }


                    if (
                        !empty(
                            $address['area']
                        )
                    ) {

                        $optionParts[] =
                            $address['area'];
                    }


                    if (
                        !empty(
                            $address['city']
                        )
                    ) {

                        $optionParts[] =
                            $address['city'];
                    }


                    $optionText =
                        implode(
                            ', ',
                            $optionParts
                        );


                    $isSelected =
                        (
                            (int)
                            $address['id']
                            ===
                            $selectedAddressId
                        );


                    ?>


                    <div
                        class="
                        address-option
                        <?php
                        echo
                            $isSelected
                            ? 'selected'
                            : '';
                        ?>
                        "
                        data-address-id="<?php
                        echo (int)
                            $address['id'];
                        ?>"
                        data-address-label="<?php
                        echo cart_h(
                            $address[
                                'address_title'
                            ]
                            ?: 'Address'
                        );
                        ?>"
                        data-address-text="<?php
                        echo cart_h(
                            $optionText
                        );
                        ?>"
                        data-address-phone="<?php
                        echo cart_h(
                            $address[
                                'phone'
                            ]
                            ?? ''
                        );
                        ?>"
                        onclick="
                            selectAddress(this);
                        "
                    >


                        <input
                            type="radio"
                            name="selected_address"
                            <?php
                            echo
                                $isSelected
                                ? 'checked'
                                : '';
                            ?>
                        >


                        <div
                            class="
                            address-option-content
                            "
                        >

                            <div
                                class="
                                address-option-title
                                "
                            >

                                <?php
                                echo cart_h(
                                    $address[
                                        'address_title'
                                    ]
                                    ?: 'Address'
                                );
                                ?>

                            </div>


                            <div
                                class="
                                address-option-text
                                "
                            >

                                <?php
                                echo cart_h(
                                    $optionText
                                );
                                ?>

                            </div>


                            <?php if (
                                !empty(
                                    $address[
                                        'phone'
                                    ]
                                )
                            ): ?>

                                <div
                                    class="
                                    address-option-phone
                                    "
                                >

                                    <i
                                        class="
                                        fas
                                        fa-phone
                                        "
                                    ></i>

                                    <?php
                                    echo cart_h(
                                        $address[
                                            'phone'
                                        ]
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


        <div
            class="modal-footer"
        >


            <!-- IMPORTANT:
                 This is now generated from
                 the actual project root.
            -->

            <a
                href="<?php
                echo cart_h(
                    $manageAddressUrl
                );
                ?>"
                class="
                manage-address-btn
                "
            >

                <i
                    class="
                    fas
                    fa-location-dot
                    "
                ></i>

                Manage Addresses

            </a>


            <button
                type="button"
                class="
                use-address-btn
                "
                onclick="
                    useSelectedAddress();
                "
            >

                Use This Address

            </button>


        </div>


    </div>

</div>


<script>

/* =========================================================
   SELECTED ADDRESS
========================================================= */

var selectedAddressId =
    <?php
    echo (int)
        $selectedAddressId;
    ?>;


/* =========================================================
   OPEN MODAL
========================================================= */

function openAddressModal()
{

    var modal =
        document.getElementById(
            'addressModal'
        );


    if (!modal) {
        return;
    }


    modal.classList.add(
        'open'
    );


    modal.setAttribute(
        'aria-hidden',
        'false'
    );


    document.body.style.overflow =
        'hidden';
}


/* =========================================================
   CLOSE MODAL
========================================================= */

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


    document.body.style.overflow =
        '';
}


/* =========================================================
   CLOSE WHEN CLICK OUTSIDE
========================================================= */

var addressModal =
    document.getElementById(
        'addressModal'
    );


if (addressModal) {

    addressModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                this
            ) {

                closeAddressModal();

            }

        }
    );

}


/* =========================================================
   SELECT ADDRESS
========================================================= */

function selectAddress(element)
{

    document
        .querySelectorAll(
            '.address-option'
        )
        .forEach(
            function(option) {

                option.classList.remove(
                    'selected'
                );


                var radio =
                    option.querySelector(
                        'input[type="radio"]'
                    );


                if (radio) {

                    radio.checked =
                        false;

                }

            }
        );


    element.classList.add(
        'selected'
    );


    var radio =
        element.querySelector(
            'input[type="radio"]'
        );


    if (radio) {

        radio.checked =
            true;

    }


    selectedAddressId =
        parseInt(
            element.getAttribute(
                'data-address-id'
            ),
            10
        );
}


/* =========================================================
   USE SELECTED ADDRESS
========================================================= */

function useSelectedAddress()
{

    var selected =
        document.querySelector(
            '.address-option.selected'
        );


    if (!selected) {

        alert(
            'Please select a delivery address.'
        );

        return;
    }


    selectedAddressId =
        parseInt(
            selected.getAttribute(
                'data-address-id'
            ),
            10
        );


    var label =
        selected.getAttribute(
            'data-address-label'
        );


    var text =
        selected.getAttribute(
            'data-address-text'
        );


    var phone =
        selected.getAttribute(
            'data-address-phone'
        );


    var labelElement =
        document.getElementById(
            'selectedAddressLabel'
        );


    var textElement =
        document.getElementById(
            'selectedAddressText'
        );


    var phoneElement =
        document.getElementById(
            'selectedAddressPhone'
        );


    if (labelElement) {

        labelElement.textContent =
            label ||
            'Address';
    }


    if (textElement) {

        textElement.textContent =
            text ||
            '';
    }


    if (phoneElement) {

        if (phone) {

            phoneElement.innerHTML =
                '<i class="fas fa-phone"></i> ' +
                escapeHtml(phone);

        } else {

            phoneElement.innerHTML =
                '';

        }

    }


    closeAddressModal();
}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value)
{

    var div =
        document.createElement(
            'div'
        );


    div.textContent =
        value;


    return div.innerHTML;
}


/* =========================================================
   PROMO
========================================================= */

function applyPromo()
{

    var input =
        document.getElementById(
            'promoCode'
        );


    if (!input) {
        return;
    }


    var code =
        input.value
            .trim()
            .toUpperCase();


    if (code === '') {

        alert(
            'Please enter a promo code.'
        );

        return;
    }


    if (
        code === 'WELCOME10'
    ) {

        alert(
            'Promo code accepted.'
        );

    } else {

        alert(
            'Invalid promo code.'
        );

    }
}


/* =========================================================
   CHECKOUT
========================================================= */

function proceedToCheckout()
{

    <?php if (
        $multipleRestaurants
    ): ?>

        alert(
            'Please keep items from one restaurant in the cart before checkout.'
        );

        return;

    <?php endif; ?>


    <?php if (
        empty($addresses)
    ): ?>

        alert(
            'Please add a delivery address first.'
        );


        window.location.href =
            <?php
            echo json_encode(
                $manageAddressUrl
            );
            ?>;


        return;

    <?php endif; ?>


    if (
        !selectedAddressId ||
        selectedAddressId <= 0
    ) {

        alert(
            'Please select a valid delivery address.'
        );


        openAddressModal();


        return;
    }


    window.location.href =
        'checkout.php?address_id=' +
        encodeURIComponent(
            selectedAddressId
        );

}

</script>


<?php

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
|
| The existing customer header/footer structure
| is preserved. If your customer-header.php already
| includes the footer, don't add another footer here.
|--------------------------------------------------------------------------
*/

if (
    file_exists(
        __DIR__ .
        '/includes/footer.php'
    )
) {

    require_once
        __DIR__ .
        '/includes/footer.php';

}

?>