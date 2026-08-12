<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR CUSTOMER HEADER
|--------------------------------------------------------------------------
| File: includes/header.php
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}


/*
|--------------------------------------------------------------------------
| CUSTOMER LOGIN
|--------------------------------------------------------------------------
*/

$isLoggedIn =
    isset($_SESSION['user_id']) &&
    (int) $_SESSION['user_id'] > 0;

$userId = $isLoggedIn
    ? (int) $_SESSION['user_id']
    : 0;

$userName =
    $_SESSION['name']
    ?? $_SESSION['user_name']
    ?? 'Customer';

$userEmail =
    $_SESSION['email']
    ?? '';

$profileImage = '';



/*
|--------------------------------------------------------------------------
| CUSTOMER DATA
|--------------------------------------------------------------------------
*/

if (
    $userId > 0 &&
    isset($conn) &&
    $conn instanceof mysqli
) {

    $stmt = $conn->prepare("
        SELECT
            full_name,
            email,
            profile_image
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $userId
        );

        $stmt->execute();

        $userData =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($userData) {

            if (!empty($userData['full_name'])) {

                $userName =
                    $userData['full_name'];

                $_SESSION['name'] =
                    $userName;
            }

            if (!empty($userData['email'])) {

                $userEmail =
                    $userData['email'];

                $_SESSION['email'] =
                    $userEmail;
            }

            $profileImage =
                $userData['profile_image']
                ?? '';
        }
    }
}



/*
|--------------------------------------------------------------------------
| CART COUNT
|--------------------------------------------------------------------------
*/

$cartCount = 0;

if (
    $userId > 0 &&
    isset($conn) &&
    $conn instanceof mysqli
) {

    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                SUM(quantity),
                0
            ) AS total
        FROM cart
        WHERE user_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $userId
        );

        $stmt->execute();

        $cartData =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();

        $cartCount =
            (int) (
                $cartData['total']
                ?? 0
            );
    }
}



/*
|--------------------------------------------------------------------------
| CURRENT PAGE
|--------------------------------------------------------------------------
*/

$currentPage =
    basename(
        $_SERVER['PHP_SELF']
        ?? 'index.php'
    );



/*
|--------------------------------------------------------------------------
| ACTIVE NAV
|--------------------------------------------------------------------------
*/

function customerNavActive($pages)
{
    global $currentPage;

    if (!is_array($pages)) {
        $pages = [$pages];
    }

    return in_array(
        $currentPage,
        $pages,
        true
    )
        ? 'active'
        : '';
}



/*
|--------------------------------------------------------------------------
| ESCAPE
|--------------------------------------------------------------------------
*/

if (!function_exists('customer_h')) {

    function customer_h($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}



/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$customerInitials = 'C';

$nameParts =
    preg_split(
        '/\s+/',
        trim(
            (string) $userName
        )
    );

if (
    !empty($nameParts[0]) &&
    strtolower($nameParts[0]) !== 'customer'
) {

    $customerInitials =
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
        );

    if (!empty($nameParts[1])) {

        $customerInitials .=
            strtoupper(
                substr(
                    $nameParts[1],
                    0,
                    1
                )
            );
    }
}

?>


<!-- =========================================================
     FONT AWESOME
========================================================= -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   CUSTOMER HEADER
   MAIN BRAND COLOR = #ed0038
========================================================= */

.customer-header {

    position: sticky;

    top: 0;

    left: 0;

    width: 100%;

    z-index: 2000;

    background: #ffffff;

    border-bottom:
        1px solid #eeeeee;

    box-shadow:
        0 4px 18px
        rgba(70, 20, 35, .08);
}


.customer-header,
.customer-header * {

    box-sizing: border-box;
}



/* =========================================================
   TOP ROW
========================================================= */

.customer-header-top {

    width: 100%;

    max-width: 1500px;

    min-height: 78px;

    margin: 0 auto;

    padding:
        11px 4%;

    display: flex;

    align-items: center;

    gap: 18px;
}



/* =========================================================
   LOGO
========================================================= */

.customer-logo {

    display: inline-flex;

    align-items: center;

    gap: 10px;

    flex-shrink: 0;

    color: #ed0038;

    text-decoration: none;
}


.customer-logo-icon {

    width: 44px;

    height: 44px;

    border-radius: 11px;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;
}


.customer-logo-text {

    color: #ed0038;

    font-size: 29px;

    line-height: 1;

    font-weight: 900;

    letter-spacing: -.6px;
}


.customer-logo-sub {

    margin-top: 4px;

    color: #777777;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1.3px;
}



/* =========================================================
   LOCATION
========================================================= */

.customer-location {

    min-width: 155px;

    max-width: 195px;

    min-height: 44px;

    padding:
        7px 11px;

    display: flex;

    align-items: center;

    gap: 9px;

    background: #ffffff;

    border:
        1px solid #efb9ca;

    border-radius: 9px;

    color: #333333;

    text-decoration: none;

    transition: .2s ease;

}


.customer-location:hover {

    background: #fff1f5;

    border-color: #ed0038;
}


.customer-location-icon {

    width: 31px;

    height: 31px;

    border-radius: 8px;

    background: #fff1f5;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    font-size: 15px;
}


.customer-location-label {

    color: #888888;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;

    line-height: 1;
}


.customer-location-value {

    display: block;

    margin-top: 4px;

    color: #333333;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}



/* =========================================================
   SEARCH
========================================================= */

.customer-search {

    position: relative;

    flex: 1;

    max-width: 590px;

    margin: 0 auto;
}


.customer-search input {

    width: 100%;

    height: 46px;

    padding:
        0 50px 0 18px;

    background: #ffffff;

    border:
        1px solid #dddddd;

    border-radius: 25px;

    outline: none;

    color: #333333;

    font-size: 13px;

    transition: .2s ease;
}


.customer-search input::placeholder {

    color: #999999;
}


.customer-search input:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.08);
}


.customer-search button {

    position: absolute;

    top: 5px;

    right: 5px;

    width: 36px;

    height: 36px;

    border: 0;

    border-radius: 50%;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 14px;

}


.customer-search button:hover {

    background: #d90035;
}



/* =========================================================
   HEADER ACTIONS
========================================================= */

.customer-header-actions {

    display: flex;

    align-items: center;

    gap: 8px;

    flex-shrink: 0;
}


.customer-action {

    min-height: 41px;

    padding:
        0 13px;

    border:
        1px solid transparent;

    border-radius: 8px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    background: #ffffff;

    color: #333333;

    text-decoration: none;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;

    transition: .2s ease;
}


.customer-action i {

    font-size: 15px;
}


.customer-action:hover {

    background: #fff1f5;

    color: #ed0038;

    border-color: #f2bccd;
}



/* =========================================================
   CART - TOP ONLY
========================================================= */

.customer-cart {

    position: relative;

    width: 44px;

    height: 44px;

    padding: 0;

    border-radius: 50%;

    color: #ed0038;
}


.customer-cart:hover {

    background: #fff1f5;

    color: #ed0038;
}


.customer-cart i {

    font-size: 19px;
}


.customer-cart-count {

    position: absolute;

    top: -2px;

    right: -2px;

    min-width: 19px;

    height: 19px;

    padding:
        0 4px;

    border-radius: 20px;

    background: #ed0038;

    color: #ffffff;

    border:
        2px solid #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 8px;

    font-weight: 900;
}



/* =========================================================
   SIGN IN
========================================================= */

.customer-signin {

    background: #ed0038;

    color: #ffffff;

    border-color: #ed0038;

    padding:
        0 16px;
}


.customer-signin:hover {

    background: #d90035;

    color: #ffffff;

    border-color: #d90035;
}



/* =========================================================
   SIGN UP
========================================================= */

.customer-signup {

    background: #ffffff;

    color: #ed0038;

    border:
        1px solid #ed0038;

    padding:
        0 16px;
}


.customer-signup:hover {

    background: #fff1f5;

    color: #d90035;

    border-color: #d90035;
}



/* =========================================================
   CUSTOMER PROFILE
========================================================= */

.customer-user {

    position: relative;
}


.customer-user-button {

    min-height: 43px;

    padding:
        4px 10px 4px 5px;

    border:
        1px solid #e8e8e8;

    border-radius: 24px;

    background: #ffffff;

    display: inline-flex;

    align-items: center;

    gap: 8px;

    cursor: pointer;
}


.customer-avatar {

    width: 34px;

    height: 34px;

    border-radius: 50%;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;

    flex-shrink: 0;

    font-size: 10px;

    font-weight: 900;
}


.customer-avatar img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}


.customer-user-name {

    max-width: 150px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

    color: #333333;

    font-size: 12px;

    font-weight: 800;
}


.customer-user-arrow {

    color: #999999;

    font-size: 9px;
}



/* =========================================================
   PROFILE DROPDOWN
========================================================= */

.customer-user-menu {

    position: absolute;

    top:
        calc(100% + 9px);

    right: 0;

    width: 245px;

    padding: 9px;

    background: #ffffff;

    border:
        1px solid #e8e8e8;

    border-radius: 12px;

    box-shadow:
        0 14px 40px
        rgba(50,20,30,.15);

    opacity: 0;

    visibility: hidden;

    transform:
        translateY(-7px);

    transition: .18s ease;
}


.customer-user.open
.customer-user-menu {

    opacity: 1;

    visibility: visible;

    transform:
        translateY(0);
}


.customer-user-menu-top {

    padding:
        12px;

    background: #fff1f5;

    border-radius: 9px;

    margin-bottom: 6px;
}


.customer-user-menu-name {

    color: #333333;

    font-size: 14px;

    font-weight: 800;
}


.customer-user-menu-email {

    margin-top: 5px;

    color: #888888;

    font-size: 10px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.customer-menu-link {

    min-height: 44px;

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        9px 11px;

    border-radius: 8px;

    text-decoration: none;

    color: #444444;

    font-size: 13px;

    font-weight: 700;
}


.customer-menu-link i {

    width: 20px;

    text-align: center;

    color: #ed0038;

    font-size: 14px;
}


.customer-menu-link:hover {

    background: #fff1f5;

    color: #ed0038;
}


.customer-menu-link.logout {

    color: #cf0033;
}



/* =========================================================
   LOWER NAVIGATION
   CART IS NOT HERE
========================================================= */

.customer-nav {

    width: 100%;

    background: #ed0038;
}


.customer-nav-inner {

    width: 100%;

    max-width: 1500px;

    min-height: 49px;

    margin: 0 auto;

    padding:
        0 4%;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.customer-nav-list {

    display: flex;

    align-items: center;

    list-style: none;

    padding: 0;

    margin: 0;
}


.customer-nav-item {

    position: relative;
}


.customer-nav-link {

    min-height: 49px;

    padding:
        0 18px;

    display: flex;

    align-items: center;

    gap: 8px;

    color: #ffffff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 800;

    transition: .2s ease;
}


.customer-nav-link i {

    font-size: 14px;
}


.customer-nav-link:hover {

    background:
        rgba(255,255,255,.12);

    color: #ffffff;
}


.customer-nav-link.active {

    background:
        rgba(255,255,255,.14);

    color: #ffffff;
}


.customer-nav-link.active::after {

    content: "";

    position: absolute;

    left: 18px;

    right: 18px;

    bottom: 0;

    height: 3px;

    background: #ffffff;

    border-radius:
        3px 3px 0 0;
}



/* =========================================================
   BECOME RIDER
========================================================= */

.customer-nav-promo {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        8px 14px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid
        rgba(255,255,255,.18);

    border-radius: 20px;

    color: #ffffff;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;
}


.customer-nav-promo i {

    font-size: 14px;
}


.customer-nav-promo:hover {

    background:
        rgba(255,255,255,.22);

    color: #ffffff;
}



/* =========================================================
   MOBILE MENU BUTTON
========================================================= */

.customer-mobile-toggle {

    display: none;

    width: 41px;

    height: 41px;

    border: 0;

    border-radius: 9px;

    background: #fff1f5;

    color: #ed0038;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-size: 17px;

    flex-shrink: 0;
}



/* =========================================================
   TABLET
========================================================= */

@media (max-width: 1180px) {

    .customer-location {

        display: none;
    }


    .customer-action span {

        display: none;
    }


    .customer-action {

        width: 43px;

        min-height: 43px;

        padding: 0;
    }


    .customer-signin,
    .customer-signup {

        width: auto;

        padding:
            0 13px;
    }


    .customer-signin span,
    .customer-signup span {

        display: inline;
    }


    .customer-search {

        max-width: none;
    }
}



/* =========================================================
   TABLET / MOBILE
========================================================= */

@media (max-width: 900px) {

    .customer-header-top {

        min-height: 70px;

        padding:
            9px 4%;

        flex-wrap: wrap;

        gap: 10px;
    }


    .customer-search {

        order: 5;

        flex-basis: 100%;

        width: 100%;

        max-width: none;
    }


    .customer-mobile-toggle {

        display: inline-flex;
    }


    .customer-nav {

        display: none;
    }


    .customer-nav.mobile-open {

        display: block;
    }


    .customer-nav-inner {

        padding:
            7px 4% 11px;

        flex-direction: column;

        align-items: stretch;
    }


    .customer-nav-list {

        flex-direction: column;

        align-items: stretch;
    }


    .customer-nav-link {

        min-height: 46px;

        padding:
            0 12px;

        font-size: 14px;
    }


    .customer-nav-link.active::after {

        left: 12px;

        right: auto;

        width: 32px;
    }


    .customer-nav-promo {

        margin-top: 8px;

        min-height: 40px;

        justify-content: center;

        font-size: 12px;
    }
}



/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 560px) {

    .customer-header-top {

        padding:
            9px 3.5%;

        gap: 8px;
    }


    .customer-logo-sub {

        display: none;
    }


    .customer-logo-text {

        font-size: 24px;
    }


    .customer-logo-icon {

        width: 37px;

        height: 37px;

        font-size: 16px;
    }


    .customer-search input {

        height: 44px;

        font-size: 13px;
    }


    .customer-signin,
    .customer-signup {

        display: none;
    }


    .customer-user-button {

        border: 0;

        background: transparent;

        padding-right: 0;
    }


    .customer-user-name,
    .customer-user-arrow {

        display: none;
    }


    .customer-cart {

        width: 40px;

        height: 40px;
    }


    .customer-mobile-toggle {

        width: 39px;

        height: 39px;
    }


    .customer-user-menu {

        width:
            min(92vw, 280px);

        right:
            -4px;
    }


    .customer-user-menu-name {

        font-size: 14px;
    }


    .customer-menu-link {

        min-height: 45px;

        font-size: 13px;
    }
}



/* =========================================================
   SMALL PHONES
========================================================= */

@media (max-width: 400px) {

    .customer-logo-text {

        font-size: 21px;
    }


    .customer-logo-icon {

        width: 34px;

        height: 34px;
    }


    .customer-header-actions {

        gap: 3px;
    }


    .customer-cart {

        width: 37px;

        height: 37px;
    }


    .customer-mobile-toggle {

        width: 36px;

        height: 36px;
    }


    .customer-search input {

        font-size: 12px;
    }
}

</style>



<!-- =========================================================
     CUSTOMER HEADER
========================================================= -->

<header
    class="customer-header"
    id="customerHeader"
>


    <!-- =====================================================
         TOP ROW
    ====================================================== -->

    <div
        class="customer-header-top"
    >


        <!-- LOGO -->

        <a
            href="index.php"
            class="customer-logo"
            aria-label="Humsafar Home"
        >

            <div
                class="customer-logo-icon"
            >

                <i
                    class="fas fa-utensils"
                ></i>

            </div>


            <div>

                <div
                    class="customer-logo-text"
                >
                    Humsafar
                </div>


                <div
                    class="customer-logo-sub"
                >
                    FOOD DELIVERY
                </div>

            </div>

        </a>



        <!-- LOCATION -->

        <a
            href="my-account.php#addresses"
            class="customer-location"
            title="Manage delivery address"
        >

            <div
                class="customer-location-icon"
            >

                <i
                    class="fas fa-location-dot"
                ></i>

            </div>


            <div>

                <div
                    class="customer-location-label"
                >
                    Deliver to
                </div>


                <span
                    class="customer-location-value"
                >
                    Choose your address
                </span>

            </div>

        </a>



        <!-- SEARCH -->

        <form
            action="restaurants.php"
            method="GET"
            class="customer-search"
            role="search"
        >

            <input
                type="search"
                name="search"
                placeholder="Search restaurants or food..."
                aria-label="Search restaurants or food"
                autocomplete="off"
            >


            <button
                type="submit"
                aria-label="Search"
            >

                <i
                    class="fas fa-search"
                ></i>

            </button>

        </form>



        <!-- MOBILE MENU -->

        <button
            type="button"
            class="customer-mobile-toggle"
            id="customerMobileToggle"
            aria-label="Open Menu"
            aria-expanded="false"
        >

            <i
                class="fas fa-bars"
            ></i>

        </button>



        <!-- RIGHT ACTIONS -->

        <div
            class="customer-header-actions"
        >


            <?php if ($isLoggedIn): ?>


                <!-- MY ORDERS -->

                <a
                    href="my_orders.php"
                    class="customer-action"
                    title="My Orders"
                >

                    <i
                        class="fas fa-receipt"
                    ></i>

                    <span>
                        Orders
                    </span>

                </a>



                <!-- CART
                     ONLY TOP BAR
                -->

                <a
                    href="cart.php"
                    class="customer-action customer-cart"
                    title="Shopping Cart"
                    aria-label="Shopping Cart"
                >

                    <i
                        class="fas fa-shopping-cart"
                    ></i>


                    <?php if (
                        $cartCount > 0
                    ): ?>

                        <span
                            class="customer-cart-count"
                        >

                            <?= (int) $cartCount ?>

                        </span>

                    <?php endif; ?>

                </a>



                <!-- CUSTOMER PROFILE -->

                <div
                    class="customer-user"
                    id="customerUser"
                >

                    <button
                        type="button"
                        class="customer-user-button"
                        id="customerUserButton"
                        aria-expanded="false"
                    >

                        <div
                            class="customer-avatar"
                        >

                            <?php if (
                                !empty(
                                    $profileImage
                                )
                            ): ?>

                                <img
                                    src="uploads/profiles/<?= customer_h($profileImage) ?>"
                                    alt="<?= customer_h($userName) ?>"
                                >

                            <?php else: ?>

                                <?= customer_h(
                                    $customerInitials
                                ) ?>

                            <?php endif; ?>

                        </div>


                        <span
                            class="customer-user-name"
                        >

                            <?= customer_h(
                                $userName
                            ) ?>

                        </span>


                        <span
                            class="customer-user-arrow"
                        >

                            <i
                                class="fas fa-chevron-down"
                            ></i>

                        </span>

                    </button>



                    <!-- PROFILE DROPDOWN -->

                    <div
                        class="customer-user-menu"
                    >


                        <div
                            class="customer-user-menu-top"
                        >

                            <div
                                class="customer-user-menu-name"
                            >

                                <?= customer_h(
                                    $userName
                                ) ?>

                            </div>


                            <div
                                class="customer-user-menu-email"
                            >

                                <?= customer_h(
                                    $userEmail !== ''
                                        ? $userEmail
                                        : 'Customer Account'
                                ) ?>

                            </div>

                        </div>



                        <!-- MY ACCOUNT -->

                        <a
                            href="my-account.php"
                            class="customer-menu-link"
                        >

                            <i
                                class="fas fa-user"
                            ></i>

                            My Account

                        </a>



                        <!-- MY ORDERS -->

                        <a
                            href="my_orders.php"
                            class="customer-menu-link"
                        >

                            <i
                                class="fas fa-receipt"
                            ></i>

                            My Orders

                        </a>



                        <!-- MY CART -->

                        <a
                            href="cart.php"
                            class="customer-menu-link"
                        >

                            <i
                                class="fas fa-cart-shopping"
                            ></i>

                            My Cart

                        </a>



                        <!-- LOGOUT -->

                        <a
                            href="logout.php"
                            class="customer-menu-link logout"
                        >

                            <i
                                class="fas fa-right-from-bracket"
                            ></i>

                            Logout

                        </a>


                    </div>

                </div>


            <?php else: ?>


                <!-- SIGN IN -->

                <a
                    href="login.php"
                    class="customer-action customer-signin"
                >

                    <i
                        class="fas fa-right-to-bracket"
                    ></i>

                    <span>
                        Sign In
                    </span>

                </a>



                <!-- SIGN UP -->

                <a
                    href="register.php"
                    class="customer-action customer-signup"
                >

                    <i
                        class="fas fa-user-plus"
                    ></i>

                    <span>
                        Sign Up
                    </span>

                </a>


            <?php endif; ?>


        </div>

    </div>



    <!-- =====================================================
         LOWER NAVIGATION
         CART IS NOT HERE
    ====================================================== -->

    <nav
        class="customer-nav"
        id="customerNav"
    >

        <div
            class="customer-nav-inner"
        >


            <ul
                class="customer-nav-list"
            >


                <!-- HOME -->

                <li
                    class="customer-nav-item"
                >

                    <a
                        href="index.php"
                        class="customer-nav-link
                        <?= customerNavActive(
                            'index.php'
                        ) ?>"
                    >

                        <i
                            class="fas fa-house"
                        ></i>

                        Home

                    </a>

                </li>



                <!-- RESTAURANTS -->

                <li
                    class="customer-nav-item"
                >

                    <a
                        href="restaurants.php"
                        class="customer-nav-link
                        <?= customerNavActive(
                            'restaurants.php'
                        ) ?>"
                    >

                        <i
                            class="fas fa-store"
                        ></i>

                        Restaurants

                    </a>

                </li>



                <!-- DEALS -->

                <li
                    class="customer-nav-item"
                >

                    <a
                        href="deals.php"
                        class="customer-nav-link
                        <?= customerNavActive(
                            'deals.php'
                        ) ?>"
                    >

                        <i
                            class="fas fa-tags"
                        ></i>

                        Deals

                    </a>

                </li>



                <?php if ($isLoggedIn): ?>


                    <!-- MY ORDERS -->

                    <li
                        class="customer-nav-item"
                    >

                        <a
                            href="my_orders.php"
                            class="customer-nav-link
                            <?= customerNavActive(
                                [
                                    'my_orders.php',
                                    'order_success.php'
                                ]
                            ) ?>"
                        >

                            <i
                                class="fas fa-bag-shopping"
                            ></i>

                            My Orders

                        </a>

                    </li>



                    <!-- MY ACCOUNT -->

                    <li
                        class="customer-nav-item"
                    >

                        <a
                            href="my-account.php"
                            class="customer-nav-link
                            <?= customerNavActive(
                                'my-account.php'
                            ) ?>"
                        >

                            <i
                                class="fas fa-user"
                            ></i>

                            My Account

                        </a>

                    </li>


                <?php endif; ?>


            </ul>




        </div>

    </nav>


</header>



<script>

/*
|--------------------------------------------------------------------------
| MOBILE MENU
|--------------------------------------------------------------------------
*/

(function () {

    const mobileButton =
        document.getElementById(
            'customerMobileToggle'
        );


    const customerNav =
        document.getElementById(
            'customerNav'
        );


    if (
        mobileButton &&
        customerNav
    ) {

        mobileButton.addEventListener(
            'click',
            function () {

                const opened =
                    customerNav.classList.toggle(
                        'mobile-open'
                    );


                mobileButton.setAttribute(
                    'aria-expanded',
                    opened
                        ? 'true'
                        : 'false'
                );


                const icon =
                    mobileButton.querySelector(
                        'i'
                    );


                if (icon) {

                    icon.classList.toggle(
                        'fa-bars',
                        !opened
                    );


                    icon.classList.toggle(
                        'fa-xmark',
                        opened
                    );

                }

            }
        );

    }



    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PROFILE DROPDOWN
    |--------------------------------------------------------------------------
    */

    const customerUser =
        document.getElementById(
            'customerUser'
        );


    const customerUserButton =
        document.getElementById(
            'customerUserButton'
        );


    if (
        customerUser &&
        customerUserButton
    ) {

        customerUserButton.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

                customerUser.classList.toggle(
                    'open'
                );

            }
        );


        document.addEventListener(
            'click',
            function () {

                customerUser.classList.remove(
                    'open'
                );

            }
        );

    }

})();

</script>