<?php
require_once 'includes/config.php';
require_once 'includes/session.php';

/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];


/* =====================================================
   CART DATA
===================================================== */

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

        JOIN menu_items
            ON cart.menu_item_id = menu_items.id

        JOIN restaurants
            ON menu_items.restaurant_id = restaurants.id

        WHERE cart.user_id = ?

        ORDER BY cart.id DESC";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Cart query error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();


/* =====================================================
   CART VARIABLES
===================================================== */

$cartItems = [];

$totalItems = 0;

$subTotal = 0;

$deliveryFee = 0;

$restaurantID = 0;

$restaurantName = "";

$restaurantImage = "";

$deliveryTime = "";


/* =====================================================
   FETCH CART ITEMS
===================================================== */

while ($row = $result->fetch_assoc()) {

    /* -----------------------------------------------
       RESTAURANT INFORMATION
    ----------------------------------------------- */

    if ($restaurantID === 0) {

        $restaurantID =
            (int) $row['restaurant_id'];

        $restaurantName =
            $row['restaurant_name'] ?? '';

        $restaurantImage =
            $row['restaurant_image'] ?? '';

        $deliveryTime =
            $row['delivery_time'] ?? '';

        $deliveryFee =
            (float) ($row['delivery_fee'] ?? 0);
    }


    /* -----------------------------------------------
       ITEM CALCULATIONS
    ----------------------------------------------- */

    $row['quantity'] =
        (int) $row['quantity'];

    $row['price'] =
        (float) $row['price'];

    $row['subtotal'] =
        $row['price'] * $row['quantity'];

    $totalItems +=
        $row['quantity'];

    $subTotal +=
        $row['subtotal'];

    $cartItems[] =
        $row;
}


/* =====================================================
   EMPTY CART
===================================================== */

$isCartEmpty =
    empty($cartItems);


/* =====================================================
   GRAND TOTAL
===================================================== */

$grandTotal =
    $subTotal + $deliveryFee;


/* =====================================================
   HEADER
===================================================== */

require_once 'includes/header.php';

?>


<!-- =====================================================
     CART PAGE
===================================================== -->

<main class="cart-container">


    <!-- =================================================
         CART CONTENT
    ================================================== -->

    <div class="cart-content">


        <!-- =================================================
             LEFT SIDE
        ================================================== -->

        <div class="cart-items">


            <!-- =================================================
                 CART HEADER
            ================================================== -->

            <div class="cart-header">

                <h1>
                    Your Cart
                </h1>


                <?php if (!$isCartEmpty) { ?>

                    <p>

                        <?php echo $totalItems; ?>

                        <?php
                        echo ($totalItems == 1)
                            ? 'item'
                            : 'items';
                        ?>

                        from

                        <?php
                        echo htmlspecialchars(
                            $restaurantName
                        );
                        ?>

                    </p>

                <?php } else { ?>

                    <p>
                        Your shopping cart is empty.
                    </p>

                <?php } ?>

            </div>


            <div class="cart-divider"></div>


            <?php if (!$isCartEmpty) { ?>


                <!-- =================================================
                     RESTAURANT INFORMATION
                ================================================== -->

                <div class="cart-restaurant-info">


                    <div class="cart-restaurant-avatar">

                        <?php
                        if (!empty($restaurantImage)) {
                        ?>

                            <img
                                src="assets/images/restaurants/<?php echo htmlspecialchars($restaurantImage); ?>"
                                alt="<?php echo htmlspecialchars($restaurantName); ?>"
                            >

                        <?php } else { ?>

                            <i class="fas fa-store"></i>

                        <?php } ?>

                    </div>


                    <div class="cart-restaurant-details">

                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $restaurantName
                            );
                            ?>

                        </h3>


                        <?php
                        if (!empty($deliveryTime)) {
                        ?>

                            <p class="delivery-time">

                                <i class="fas fa-clock"></i>

                                <?php
                                echo htmlspecialchars(
                                    $deliveryTime
                                );
                                ?>

                            </p>

                        <?php } ?>


                        <p>

                            <i class="fas fa-motorcycle"></i>

                            Delivery Fee:
                            Rs.

                            <?php
                            echo number_format(
                                $deliveryFee,
                                2
                            );
                            ?>

                        </p>

                    </div>

                </div>


                <!-- =================================================
                     CART ITEMS LIST
                ================================================== -->

                <div class="cart-items-list">


                    <?php
                    foreach ($cartItems as $item) {
                    ?>


                        <!-- =================================================
                             SINGLE CART ITEM
                        ================================================== -->

                        <div class="cart-item">


                            <!-- PRODUCT IMAGE -->

                            <div class="item-image">

                                <?php
                                if (!empty($item['image'])) {
                                ?>

                                    <img
                                        src="assets/images/menu/<?php echo htmlspecialchars($item['image']); ?>"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    >

                                <?php } else { ?>

                                    <i class="fas fa-utensils"></i>

                                <?php } ?>

                            </div>


                            <!-- PRODUCT DETAILS -->

                            <div class="item-details">

                                <h4>

                                    <?php
                                    echo htmlspecialchars(
                                        $item['name']
                                    );
                                    ?>

                                </h4>


                                <?php
                                if (!empty($item['description'])) {
                                ?>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $item['description']
                                        );
                                        ?>

                                    </p>

                                <?php } ?>


                                <span class="item-price">

                                    Rs.

                                    <?php
                                    echo number_format(
                                        $item['price'],
                                        2
                                    );
                                    ?>

                                </span>

                            </div>


                            <!-- QUANTITY -->

                            <div class="item-quantity">


                                <button
                                    type="button"
                                    class="quantity-btn"
                                    onclick="updateQuantity(
                                        <?php echo (int) $item['id']; ?>,
                                        <?php echo (int) $item['quantity'] - 1; ?>
                                    )"

                                    <?php
                                    if ($item['quantity'] <= 1) {
                                        echo 'disabled';
                                    }
                                    ?>
                                >
                                    −
                                </button>


                                <span class="quantity">

                                    <?php
                                    echo (int) $item['quantity'];
                                    ?>

                                </span>


                                <button
                                    type="button"
                                    class="quantity-btn"
                                    onclick="updateQuantity(
                                        <?php echo (int) $item['id']; ?>,
                                        <?php echo (int) $item['quantity'] + 1; ?>
                                    )"
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


                            <!-- REMOVE ITEM -->

                            <button
                                type="button"
                                class="item-remove"

                                onclick="removeCartItem(
                                    <?php echo (int) $item['id']; ?>
                                )"

                                title="Remove item"
                            >

                                <i class="fas fa-trash"></i>

                            </button>


                        </div>


                    <?php
                    }
                    ?>


                </div>


                <!-- =================================================
                     ADD MORE ITEMS
                ================================================== -->

                <div class="add-more-section">

                    <a
                        href="restaurants.php"
                        class="btn-secondary"
                    >

                        <i class="fas fa-plus"></i>

                        Add More Items

                    </a>

                </div>


            <?php } else { ?>


                <!-- =================================================
                     EMPTY CART
                ================================================== -->

                <div class="empty-cart">

                    <i class="fas fa-shopping-cart"></i>

                    <h2>
                        Your Cart is Empty
                    </h2>

                    <p>
                        Add some delicious food to your cart.
                    </p>

                    <a href="restaurants.php">

                        Browse Restaurants

                    </a>

                </div>


            <?php } ?>


        </div>


        <!-- =================================================
             RIGHT SIDE - ORDER SUMMARY
        ================================================== -->

        <aside class="order-summary">


            <div class="summary-card">


                <h3>
                    Order Summary
                </h3>


                <!-- SUBTOTAL -->

                <div class="summary-row">

                    <span>
                        Subtotal
                    </span>

                    <span>

                        Rs.

                        <?php
                        echo number_format(
                            $subTotal,
                            2
                        );
                        ?>

                    </span>

                </div>


                <!-- DELIVERY FEE -->

                <div class="summary-row">

                    <span>
                        Delivery Fee
                    </span>


                    <?php
                    if ($deliveryFee > 0) {
                    ?>

                        <span>

                            Rs.

                            <?php
                            echo number_format(
                                $deliveryFee,
                                2
                            );
                            ?>

                        </span>

                    <?php } else { ?>

                        <span class="free">
                            FREE
                        </span>

                    <?php } ?>

                </div>


                <div class="summary-divider"></div>


                <!-- TOTAL -->

                <div class="summary-row total">

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

                <div class="delivery-address">


                    <h4>

                        <i class="fas fa-map-marker-alt"></i>

                        Delivery Address

                    </h4>


                    <div class="address-card small">


                        <div class="address-header">

                            <span
                                class="address-label"
                                id="selected-address-label"
                            >
                                Home
                            </span>


                            <span class="default-badge">
                                Selected
                            </span>

                        </div>


                        <p id="selected-address">

                            Your saved delivery address

                        </p>


                        <button
                            type="button"
                            class="btn-change-address"
                            onclick="openAddressModal()"
                        >

                            <i class="fas fa-edit"></i>

                            Change Address

                        </button>


                    </div>

                </div>


                <!-- =================================================
                     PAYMENT METHOD
                ================================================== -->

                <div class="payment-method">


                    <h4>

                        <i class="fas fa-credit-card"></i>

                        Payment Method

                    </h4>


                    <!-- CASH ON DELIVERY -->

                    <div
                        class="payment-option selected"
                        onclick="selectPayment(this)"
                    >

                        <input
                            type="radio"
                            name="payment_method"
                            value="cod"
                            id="payment_cod"
                            checked
                        >


                        <label for="payment_cod">

                            <i class="fas fa-money-bill-wave"></i>

                            <span>
                                Cash on Delivery
                            </span>

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


                        <label for="payment_card">

                            <i class="fas fa-credit-card"></i>

                            <span>
                                Debit / Credit Card
                            </span>

                        </label>

                    </div>


                    <!-- DIGITAL WALLET -->

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


                        <label for="payment_online">

                            <i class="fas fa-wallet"></i>

                            <span>
                                Digital Wallet
                            </span>

                        </label>

                    </div>


                </div>


                <!-- =================================================
                     PROMO CODE
                ================================================== -->

                <div class="voucher-section">


                    <h4>

                        <i class="fas fa-ticket-alt"></i>

                        Promo Code

                    </h4>


                    <div class="voucher-input">


                        <input
                            type="text"
                            id="promo_code"
                            name="promo_code"
                            placeholder="Enter promo code"
                            autocomplete="off"
                        >


                        <button
                            type="button"
                            class="btn-apply"
                            onclick="applyPromoCode()"
                        >
                            Apply
                        </button>


                    </div>


                </div>


                <!-- =================================================
                     CHECKOUT
                ================================================== -->

                <?php if (!$isCartEmpty) { ?>

                    <button
                        type="button"
                        class="btn-checkout"
                        onclick="proceedToCheckout()"
                    >

                        <i class="fas fa-lock"></i>

                        Proceed to Checkout

                    </button>

                <?php } else { ?>

                    <button
                        type="button"
                        class="btn-checkout"
                        disabled
                    >

                        <i class="fas fa-lock"></i>

                        Proceed to Checkout

                    </button>

                <?php } ?>


                <!-- SECURITY -->

                <div class="security-note">

                    <i class="fas fa-shield-alt"></i>

                    Your payment information is secure.

                </div>


            </div>


        </aside>


    </div>


    <!-- =================================================
         ADDRESS SELECTION MODAL
    ================================================== -->

    <div
        class="modal"
        id="addressModal"
        aria-hidden="true"
    >


        <div class="modal-content">


            <!-- MODAL HEADER -->

            <div class="modal-header">

                <h3>
                    Select Delivery Address
                </h3>


                <span
                    class="close-modal"
                    onclick="closeAddressModal()"
                    title="Close"
                >
                    &times;
                </span>

            </div>


            <!-- =================================================
                 ADDRESS LIST
            ================================================== -->

            <div class="addresses-list-modal">


                <!-- =================================================
                     HOME ADDRESS
                ================================================== -->

                <div
                    class="address-option selected"
                    id="home-address-option"
                    data-address="Home Address"
                    onclick="selectAddress(this)"
                >


                    <div>

                        <input
                            type="radio"
                            name="delivery_address"
                            value="home"
                            checked
                        >

                    </div>


                    <div class="address-details">

                        <h4>

                            <i class="fas fa-house"></i>

                            Home

                        </h4>


                        <p id="home-address-display">
                            Add your home address
                        </p>


                        <div
                            class="address-form"
                            id="home-address-form"
                            style="display:none;"
                            onclick="event.stopPropagation();"
                        >

                            <textarea
                                id="home-address-input"
                                rows="3"
                                placeholder="Enter your complete home address"
                            ></textarea>


                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="saveAddressForm('home')"
                            >
                                Save Home Address
                            </button>

                        </div>

                    </div>


                </div>


                <!-- =================================================
                     WORK ADDRESS
                ================================================== -->

                <div
                    class="address-option"
                    id="work-address-option"
                    data-address=""
                    onclick="selectAddress(this)"
                >


                    <div>

                        <input
                            type="radio"
                            name="delivery_address"
                            value="work"
                        >

                    </div>


                    <div class="address-details">

                        <h4>

                            <i class="fas fa-briefcase"></i>

                            Work

                        </h4>


                        <p id="work-address-display">
                            Add your work address
                        </p>


                        <div
                            class="address-form"
                            id="work-address-form"
                            style="display:none;"
                            onclick="event.stopPropagation();"
                        >

                            <textarea
                                id="work-address-input"
                                rows="3"
                                placeholder="Enter your complete work address"
                            ></textarea>


                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="saveAddressForm('work')"
                            >
                                Save Work Address
                            </button>

                        </div>


                    </div>


                </div>


                <!-- =================================================
                     NEW ADDRESS
                ================================================== -->

                <div
                    class="address-option"
                    id="new-address-option"
                    data-address=""
                    onclick="selectAddress(this)"
                >


                    <div>

                        <input
                            type="radio"
                            name="delivery_address"
                            value="new"
                        >

                    </div>


                    <div class="address-details">

                        <h4>

                            <i class="fas fa-plus-circle"></i>

                            <span id="new-address-title">
                                Add New Address
                            </span>

                        </h4>


                        <p id="new-address-display">
                            Add another delivery address
                        </p>


                        <div
                            class="address-form"
                            id="new-address-form"
                            style="display:none;"
                            onclick="event.stopPropagation();"
                        >

                            <textarea
                                id="new-address-input"
                                rows="3"
                                placeholder="Enter your complete new address"
                            ></textarea>


                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="saveAddressForm('new')"
                            >
                                Save New Address
                            </button>

                        </div>


                    </div>


                </div>


            </div>


            <!-- =================================================
                 MODAL ACTIONS
            ================================================== -->

            <div class="modal-actions">


                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="saveSelectedAddress()"
                >

                    <i class="fas fa-check"></i>

                    Use This Address

                </button>


                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeAddressModal()"
                >

                    Cancel

                </button>


                <a
                    href="addresses.php"
                    class="btn-link"
                >

                    <i class="fas fa-address-book"></i>

                    Manage Addresses

                </a>


            </div>


        </div>

    </div>


    <!-- =================================================
         SUCCESS MESSAGE
    ================================================== -->

    <div
        id="cart-success-message"
        class="success-message"
        style="display:none;"
    ></div>


    <!-- =================================================
         ERROR MESSAGE
    ================================================== -->

    <div
        id="cart-error-message"
        class="error-message"
        style="display:none;"
    ></div>


</main>


<script>

/* =====================================================
   CART PAGE JAVASCRIPT
===================================================== */


/* =====================================================
   UPDATE QUANTITY
===================================================== */

/* =====================================================
   UPDATE QUANTITY
===================================================== */

function updateQuantity(cartId, quantity) {

    quantity = parseInt(quantity);

    if (quantity < 1) {
        return;
    }


    /* ================================================
       SEND UPDATE REQUEST
    ================================================= */

    fetch('update_cart.php', {

        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body:
            'cart_id=' +
            encodeURIComponent(cartId) +
            '&quantity=' +
            encodeURIComponent(quantity)

    })


    /* ================================================
       CHECK RESPONSE
    ================================================= */

    .then(function(response) {

        if (!response.ok) {
            throw new Error(
                'Server returned an error.'
            );
        }

        return response.text();

    })


    /* ================================================
       UPDATE PAGE
    ================================================= */

    .then(function() {

        /*
         * Add timestamp so browser does not
         * show the old cached cart page.
         */

        window.location.href =
            'cart.php?' + new Date().getTime();

    })


    /* ================================================
       ERROR
    ================================================= */

    .catch(function(error) {

        console.error(
            'Update quantity error:',
            error
        );

        showCartError(
            'Something went wrong. Please try again.'
        );

    });

}


/* =====================================================
   REMOVE CART ITEM

   Uses the actual file:
   remove_from_cart.php?id=...
===================================================== */

function removeCartItem(cartId) {

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


/* =====================================================
   PAYMENT SELECTION
===================================================== */

function selectPayment(element) {

    var radio =
        element.querySelector(
            'input[type="radio"]'
        );


    if (radio) {

        radio.checked = true;

    }


    var options =
        document.querySelectorAll(
            '.payment-option'
        );


    options.forEach(function(option) {

        option.classList.remove(
            'selected'
        );

    });


    element.classList.add(
        'selected'
    );

}


/* =====================================================
   OPEN ADDRESS MODAL
===================================================== */

function openAddressModal() {

    var modal =
        document.getElementById(
            'addressModal'
        );


    if (!modal) {
        return;
    }


    modal.style.display = 'block';

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

}


/* =====================================================
   CLOSE ADDRESS MODAL
===================================================== */

function closeAddressModal() {

    var modal =
        document.getElementById(
            'addressModal'
        );


    if (!modal) {
        return;
    }


    modal.style.display = 'none';

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

}


/* =====================================================
   HIDE ALL ADDRESS FORMS
===================================================== */

function hideAddressForms() {

    var forms =
        document.querySelectorAll(
            '.address-form'
        );


    forms.forEach(function(form) {

        form.style.display =
            'none';

    });

}


/* =====================================================
   SELECT ADDRESS
===================================================== */

function selectAddress(element) {

    var options =
        document.querySelectorAll(
            '.address-option'
        );


    options.forEach(function(option) {

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


    var selectedRadio =
        element.querySelector(
            'input[type="radio"]'
        );


    if (selectedRadio) {

        selectedRadio.checked =
            true;

    }


    hideAddressForms();


    /*
     * Show form when Home,
     * Work or New Address is selected.
     */

    if (
        element.id ===
        'home-address-option'
    ) {

        document.getElementById(
            'home-address-form'
        ).style.display = 'block';

    }


    if (
        element.id ===
        'work-address-option'
    ) {

        document.getElementById(
            'work-address-form'
        ).style.display = 'block';

    }


    if (
        element.id ===
        'new-address-option'
    ) {

        document.getElementById(
            'new-address-form'
        ).style.display = 'block';

    }

}


/* =====================================================
   SAVE ADDRESS FORM
===================================================== */

function saveAddressForm(type) {

    var input =
        document.getElementById(
            type +
            '-address-input'
        );


    var option =
        document.getElementById(
            type +
            '-address-option'
        );


    var display =
        document.getElementById(
            type +
            '-address-display'
        );


    if (
        !input ||
        !option ||
        !display
    ) {

        return;

    }


    var address =
        input.value.trim();


    if (address === '') {

        showCartError(
            'Please enter your complete address.'
        );

        input.focus();

        return;

    }


    /*
     * Save address in browser.
     */

    localStorage.setItem(
        'humsafar_' +
        type +
        '_address',
        address
    );


    /*
     * Update address option.
     */

    option.setAttribute(
        'data-address',
        address
    );


    display.textContent =
        address;


    /*
     * Change title for New Address
     * after address has been saved.
     */

    if (type === 'new') {

        document.getElementById(
            'new-address-title'
        ).textContent =
            'New Address';

    }


    /*
     * Keep this address selected.
     */

    selectAddress(option);


    /*
     * Hide form after saving.
     */

    hideAddressForms();


    showCartSuccess(
        'Address saved successfully.'
    );

}


/* =====================================================
   SAVE SELECTED ADDRESS
===================================================== */

function saveSelectedAddress() {

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


    var addressText =
        selected.getAttribute(
            'data-address'
        );


    /*
     * If address has not been entered,
     * open its form instead.
     */

    if (
        !addressText ||
        addressText.trim() === ''
    ) {

        if (
            selected.id ===
            'home-address-option'
        ) {

            document.getElementById(
                'home-address-form'
            ).style.display =
                'block';

        }


        if (
            selected.id ===
            'work-address-option'
        ) {

            document.getElementById(
                'work-address-form'
            ).style.display =
                'block';

        }


        if (
            selected.id ===
            'new-address-option'
        ) {

            document.getElementById(
                'new-address-form'
            ).style.display =
                'block';

        }


        showCartError(
            'Please enter your address first.'
        );

        return;

    }


    /*
     * Update main address text.
     */

    var addressElement =
        document.getElementById(
            'selected-address'
        );


    if (addressElement) {

        addressElement.textContent =
            addressText;

    }


    /*
     * Update main address label.
     */

    var label =
        document.getElementById(
            'selected-address-label'
        );


    if (label) {

        if (
            selected.id ===
            'home-address-option'
        ) {

            label.textContent =
                'Home';

        } else if (
            selected.id ===
            'work-address-option'
        ) {

            label.textContent =
                'Work';

        } else {

            label.textContent =
                'New Address';

        }

    }


    /*
     * Save selected type.
     */

    localStorage.setItem(
        'humsafar_selected_address_type',
        selected.id
    );


    closeAddressModal();

}


/* =====================================================
   LOAD SAVED ADDRESSES
===================================================== */

function loadSavedAddresses() {

    var types = [
        'home',
        'work',
        'new'
    ];


    types.forEach(function(type) {

        var saved =
            localStorage.getItem(
                'humsafar_' +
                type +
                '_address'
            );


        if (!saved) {
            return;
        }


        var option =
            document.getElementById(
                type +
                '-address-option'
            );


        var display =
            document.getElementById(
                type +
                '-address-display'
            );


        if (option) {

            option.setAttribute(
                'data-address',
                saved
            );

        }


        if (display) {

            display.textContent =
                saved;

        }

    });


    /*
     * New Address title
     */

    var newAddress =
        localStorage.getItem(
            'humsafar_new_address'
        );


    if (newAddress) {

        var title =
            document.getElementById(
                'new-address-title'
            );


        if (title) {

            title.textContent =
                'New Address';

        }

    }


    /*
     * Load selected address
     */

    var selectedId =
        localStorage.getItem(
            'humsafar_selected_address_type'
        );


    if (!selectedId) {
        return;
    }


    var selected =
        document.getElementById(
            selectedId
        );


    if (!selected) {
        return;
    }


    var address =
        selected.getAttribute(
            'data-address'
        );


    if (!address) {
        return;
    }


    /*
     * Main address
     */

    var mainAddress =
        document.getElementById(
            'selected-address'
        );


    if (mainAddress) {

        mainAddress.textContent =
            address;

    }


    /*
     * Main label
     */

    var mainLabel =
        document.getElementById(
            'selected-address-label'
        );


    if (mainLabel) {

        if (
            selectedId ===
            'home-address-option'
        ) {

            mainLabel.textContent =
                'Home';

        } else if (
            selectedId ===
            'work-address-option'
        ) {

            mainLabel.textContent =
                'Work';

        } else {

            mainLabel.textContent =
                'New Address';

        }

    }


    /*
     * Select correct radio.
     */

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


    selected.classList.add(
        'selected'
    );


    var selectedRadio =
        selected.querySelector(
            'input[type="radio"]'
        );


    if (selectedRadio) {

        selectedRadio.checked =
            true;

    }

}


/* =====================================================
   PROMO CODE
===================================================== */

function applyPromoCode() {

    var input =
        document.getElementById(
            'promo_code'
        );


    if (!input) {
        return;
    }


    var code =
        input.value.trim();


    if (code === '') {

        showCartError(
            'Please enter a promo code.'
        );

        return;

    }


    if (
        code.toUpperCase() ===
        'WELCOME10'
    ) {

        showCartSuccess(
            'Promo code applied successfully.'
        );

    } else {

        showCartError(
            'Invalid promo code.'
        );

    }

}


/* =====================================================
   PROCEED TO CHECKOUT
===================================================== */

function proceedToCheckout() {

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

        return;

    }


    var address =
        selectedAddress.getAttribute(
            'data-address'
        );


    if (
        !address ||
        address.trim() === ''
    ) {

        showCartError(
            'Please enter a valid delivery address.'
        );

        openAddressModal();

        return;

    }


    var payment =
        encodeURIComponent(
            selectedPayment.value
        );


    address =
        encodeURIComponent(
            address
        );


    window.location.href =
        'checkout.php?payment=' +
        payment +
        '&address=' +
        address;

}


/* =====================================================
   SUCCESS MESSAGE
===================================================== */

function showCartSuccess(message) {

    var box =
        document.getElementById(
            'cart-success-message'
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


/* =====================================================
   ERROR MESSAGE
===================================================== */

function showCartError(message) {

    var box =
        document.getElementById(
            'cart-error-message'
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


/* =====================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
===================================================== */

window.addEventListener(
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


/* =====================================================
   ESC KEY
===================================================== */

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            closeAddressModal();

        }

    }
);


/* =====================================================
   INITIALIZE CART PAGE
===================================================== */

document.addEventListener(
    'DOMContentLoaded',
    function() {


        /* PAYMENT */

        var checkedPayment =
            document.querySelector(
                '.payment-option input[type="radio"]:checked'
            );


        if (checkedPayment) {

            var parent =
                checkedPayment.closest(
                    '.payment-option'
                );


            if (parent) {

                parent.classList.add(
                    'selected'
                );

            }

        }


        /* LOAD ADDRESSES */

        loadSavedAddresses();

    }
);

</script>


<?php

/* =====================================================
   FOOTER
===================================================== */

require_once 'includes/footer.php';

?>