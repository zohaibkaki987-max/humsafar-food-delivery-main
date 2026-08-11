<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR RESTAURANT SIDEBAR
|--------------------------------------------------------------------------
| File:
| restaurant/restaurant-sidebar.php
|
| Other pages:
| <?php include __DIR__ . '/restaurant-sidebar.php'; ?>
|--------------------------------------------------------------------------
*/


/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CONNECTION
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {

    $dbFiles = [
        __DIR__ . '/../includes/db.php',
        __DIR__ . '/../includes/database.php',
        __DIR__ . '/../includes/config.php',
        __DIR__ . '/../config.php'
    ];

    foreach ($dbFiles as $dbFile) {

        if (file_exists($dbFile)) {
            require_once $dbFile;
            break;
        }

    }
}


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('restaurantSidebarSafe')) {

    function restaurantSidebarSafe($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/* =========================================================
   CURRENT PAGE
========================================================= */

$currentPage = basename(
    $_SERVER['PHP_SELF'] ?? ''
);


/* =========================================================
   OWNER DATA
========================================================= */

$owner = null;

$ownerId =
    $_SESSION['restaurant_owner_id']
    ?? $_SESSION['restaurant_user_id']
    ?? $_SESSION['owner_id']
    ?? null;

$ownerEmail =
    $_SESSION['restaurant_owner_email']
    ?? $_SESSION['email']
    ?? '';



/* =========================================================
   FIND OWNER BY ID
========================================================= */

if (
    !$owner &&
    $ownerId &&
    isset($conn) &&
    $conn instanceof mysqli
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            restaurant_name,
            status
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $ownerId = (int)$ownerId;

        $stmt->bind_param(
            "i",
            $ownerId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result) {
            $owner = $result->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   FIND OWNER BY EMAIL
========================================================= */

if (
    !$owner &&
    $ownerEmail !== '' &&
    isset($conn) &&
    $conn instanceof mysqli
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            email,
            phone,
            restaurant_name,
            status
        FROM restaurant_users
        WHERE email = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $ownerEmail
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result) {
            $owner = $result->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   OWNER INFORMATION
========================================================= */

$ownerName =
    $owner['full_name']
    ?? $_SESSION['restaurant_owner_name']
    ?? $_SESSION['name']
    ?? 'Restaurant Owner';


$restaurantName =
    $owner['restaurant_name']
    ?? $_SESSION['restaurant_name']
    ?? '';


$status = strtolower(
    trim(
        $owner['status']
        ?? $_SESSION['restaurant_owner_status']
        ?? 'pending'
    )
);


/* =========================================================
   APPROVED
========================================================= */

$isApproved = in_array(
    $status,
    [
        'approved',
        'active'
    ],
    true
);


/* =========================================================
   OWNER INITIAL
========================================================= */

$nameParts = preg_split(
    '/\s+/',
    trim($ownerName)
);

if (count($nameParts) >= 2) {

    $initial =
        strtoupper(
            substr($nameParts[0], 0, 1)
            .
            substr($nameParts[1], 0, 1)
        );

} else {

    $initial =
        strtoupper(
            substr($ownerName, 0, 1)
        );
}

if ($initial === '') {
    $initial = 'Z';
}


/* =========================================================
   ACTIVE PAGE
========================================================= */

function restaurantSidebarActive($pages)
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


/* =========================================================
   MENU PAGE
========================================================= */

$menuPage =
    'restaurant-owner-manage-menu.php';

if (!file_exists(__DIR__ . '/' . $menuPage)) {

    if (
        file_exists(
            __DIR__ . '/restaurant-owner-menu.php'
        )
    ) {

        $menuPage =
            'restaurant-owner-menu.php';
    }
}


/* =========================================================
   ORDERS PAGE
========================================================= */

$ordersPage =
    'restaurant-owner-manage-orders.php';

if (!file_exists(__DIR__ . '/' . $ordersPage)) {

    if (
        file_exists(
            __DIR__ . '/restaurant-owner-orders.php'
        )
    ) {

        $ordersPage =
            'restaurant-owner-orders.php';
    }
}

?>


<!-- ======================================================
     FONT AWESOME
====================================================== -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<!-- ======================================================
     SIDEBAR CSS
====================================================== -->

<style>

/* =========================================================
   SIDEBAR
========================================================= */

.humsafar-restaurant-sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 223px;
    height: 100vh;

    background: #f5003d;

    z-index: 99999;

    display: flex;

    flex-direction: column;

    color: #ffffff;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================================
   BRAND
========================================================= */

.hrs-brand {

    height: 94px;

    padding: 0 20px;

    display: flex;

    align-items: center;

    box-sizing: border-box;

    border-bottom:
        1px solid
        rgba(255,255,255,.18);

    flex-shrink: 0;

}


.hrs-logo-box {

    width: 43px;
    height: 43px;

    background: #ffffff;

    color: #f5003d;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

    flex-shrink: 0;

}


.hrs-logo-box i {

    color: #f5003d;

}


.hrs-brand-text {

    margin-left: 10px;

}


.hrs-brand-name {

    font-size: 20px;

    line-height: 21px;

    font-weight: 800;

}


.hrs-brand-subtitle {

    margin-top: 5px;

    font-size: 8px;

    line-height: 9px;

    font-weight: 700;

    letter-spacing: .8px;

}


/* =========================================================
   NAV
========================================================= */

.hrs-navigation {

    flex: 1;

    padding:
        17px 12px 10px;

    overflow-y: auto;

}


.hrs-section-title {

    padding:
        7px 11px 10px;

    color:
        rgba(255,255,255,.72);

    font-size: 8px;

    font-weight: 800;

    letter-spacing: 1.2px;

    text-transform: uppercase;

}


/* =========================================================
   NAV ITEM
========================================================= */

.hrs-nav-item {

    width: 100%;

    height: 41px;

    margin-bottom: 5px;

    padding:
        0 14px;

    border-radius: 9px;

    display: flex;

    align-items: center;

    box-sizing: border-box;

    text-decoration: none;

    color: #ffffff;

    font-size: 12px;

    font-weight: 600;

    transition:
        .18s ease;

}


.hrs-nav-item .main-icon {

    width: 25px;

    min-width: 25px;

    font-size: 13px;

    color: #ffffff;

}


.hrs-nav-item span {

    flex: 1;

}


/* =========================================================
   HOVER
========================================================= */

.hrs-nav-item:not(.active):hover {

    background:
        rgba(255,255,255,.12);

    color: #ffffff;

}


/* =========================================================
   ACTIVE
========================================================= */

.hrs-nav-item.active {

    background: #ffffff;

    color: #f5003d;

}


.hrs-nav-item.active .main-icon {

    color: #f5003d;

}


/* =========================================================
   LOCKED
========================================================= */

.hrs-nav-item.locked {

    opacity: .58;

    cursor: not-allowed;

}


.hrs-nav-item.locked:hover {

    background: transparent;

}


.hrs-nav-item .nav-lock {

    width: auto;

    min-width: auto;

    font-size: 9px;

    color:
        rgba(255,255,255,.75);

}


/* =========================================================
   SUPPORT
========================================================= */

.hrs-support-title {

    margin-top: 14px;

}


/* =========================================================
   ACCOUNT
========================================================= */

.hrs-account {

    padding:
        10px 12px;

    border-top:
        1px solid
        rgba(255,255,255,.18);

    flex-shrink: 0;

}


.hrs-account-card {

    min-height: 50px;

    width: 100%;

    padding:
        7px 9px;

    box-sizing: border-box;

    background:
        rgba(190,0,48,.55);

    border-radius: 10px;

    display: flex;

    align-items: center;

}


.hrs-avatar {

    width: 34px;
    height: 34px;

    border-radius: 50%;

    background: #ffc400;

    color: #111111;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 800;

    flex-shrink: 0;

}


.hrs-account-info {

    margin-left: 8px;

    min-width: 0;

}


.hrs-account-name {

    color: #ffffff;

    font-size: 9px;

    font-weight: 800;

    max-width: 125px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.hrs-account-status {

    margin-top: 3px;

    color:
        rgba(255,255,255,.8);

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;

}


/* =========================================================
   CONTENT
========================================================= */

.humsafar-restaurant-content {

    margin-left: 223px;

    min-height: 100vh;

}


/* =========================================================
   SCROLLBAR
========================================================= */

.hrs-navigation::-webkit-scrollbar {

    width: 3px;

}


.hrs-navigation::-webkit-scrollbar-track {

    background: transparent;

}


.hrs-navigation::-webkit-scrollbar-thumb {

    background:
        rgba(255,255,255,.25);

    border-radius: 10px;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 768px) {

    .humsafar-restaurant-sidebar {

        width: 72px;

    }


    .hrs-brand {

        padding: 0;

        justify-content: center;

    }


    .hrs-brand-text,
    .hrs-section-title,
    .hrs-nav-item span,
    .hrs-nav-item .nav-lock,
    .hrs-account-info {

        display: none;

    }


    .hrs-nav-item {

        padding: 0;

        justify-content: center;

    }


    .hrs-nav-item .main-icon {

        width: auto;

    }


    .hrs-account {

        padding: 8px;

    }


    .hrs-account-card {

        justify-content: center;

        padding: 7px;

    }


    .humsafar-restaurant-content {

        margin-left: 72px;

    }

}

</style>


<!-- ======================================================
     SIDEBAR
====================================================== -->

<aside class="humsafar-restaurant-sidebar">


    <!-- BRAND -->

    <div class="hrs-brand">

        <div class="hrs-logo-box">

            <i class="fa-solid fa-utensils"></i>

        </div>


        <div class="hrs-brand-text">

            <div class="hrs-brand-name">
                Humsafar
            </div>

            <div class="hrs-brand-subtitle">
                RESTAURANT PARTNER
            </div>

        </div>

    </div>


    <!-- NAVIGATION -->

    <nav class="hrs-navigation">


        <!-- MAIN MENU -->

        <div class="hrs-section-title">
            Main Menu
        </div>


        <!-- DASHBOARD -->

        <a
            href="restaurant-owner-dashboard.php"
            class="
                hrs-nav-item
                <?php
                echo restaurantSidebarActive(
                    'restaurant-owner-dashboard.php'
                );
                ?>
            "
        >

            <i
                class="fa-solid fa-chart-line main-icon"
            ></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- RESTAURANT -->

        <?php if ($isApproved): ?>

            <a
                href="restaurant-owner-manage.php"
                class="
                    hrs-nav-item
                    <?php
                    echo restaurantSidebarActive(
                        'restaurant-owner-manage.php'
                    );
                    ?>
                "
            >

                <i
                    class="fa-solid fa-store main-icon"
                ></i>

                <span>
                    Restaurant
                </span>

            </a>

        <?php else: ?>

            <div
                class="hrs-nav-item locked"
                title="Available after approval"
            >

                <i
                    class="fa-solid fa-store main-icon"
                ></i>

                <span>
                    Restaurant
                </span>

                <i
                    class="fa-solid fa-lock nav-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- MENU MANAGEMENT -->

        <?php if ($isApproved): ?>

            <a
                href="<?php echo restaurantSidebarSafe($menuPage); ?>"
                class="
                    hrs-nav-item
                    <?php
                    echo restaurantSidebarActive(
                        [
                            'restaurant-owner-manage-menu.php',
                            'restaurant-owner-menu.php'
                        ]
                    );
                    ?>
                "
            >

                <i
                    class="fa-solid fa-utensils main-icon"
                ></i>

                <span>
                    Menu Management
                </span>

            </a>

        <?php else: ?>

            <div
                class="hrs-nav-item locked"
                title="Available after approval"
            >

                <i
                    class="fa-solid fa-utensils main-icon"
                ></i>

                <span>
                    Menu Management
                </span>

                <i
                    class="fa-solid fa-lock nav-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- ORDERS -->

        <?php if ($isApproved): ?>

            <a
                href="<?php echo restaurantSidebarSafe($ordersPage); ?>"
                class="
                    hrs-nav-item
                    <?php
                    echo restaurantSidebarActive(
                        [
                            'restaurant-owner-manage-orders.php',
                            'restaurant-owner-orders.php'
                        ]
                    );
                    ?>
                "
            >

                <i
                    class="fa-solid fa-cart-shopping main-icon"
                ></i>

                <span>
                    Orders
                </span>

            </a>

        <?php else: ?>

            <div
                class="hrs-nav-item locked"
                title="Available after approval"
            >

                <i
                    class="fa-solid fa-cart-shopping main-icon"
                ></i>

                <span>
                    Orders
                </span>

                <i
                    class="fa-solid fa-lock nav-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- PROFILE -->

        <a
            href="restaurant-owner-profile.php"
            class="
                hrs-nav-item
                <?php
                echo restaurantSidebarActive(
                    'restaurant-owner-profile.php'
                );
                ?>
            "
        >

            <i
                class="fa-solid fa-user main-icon"
            ></i>

            <span>
                Profile
            </span>

        </a>


        <!-- SUPPORT -->

        <div
            class="
                hrs-section-title
                hrs-support-title
            "
        >
            Support
        </div>


<a
    href="restaurant-owner-support.php"
    class="
        hrs-nav-item
        <?php
        echo restaurantSidebarActive(
            'restaurant-owner-support.php'
        );
        ?>
    "
>

    <i class="fa-regular fa-circle-question main-icon"></i>

    <span>
        Support
    </span>

</a>


    </nav>


    <!-- OWNER ACCOUNT -->

    <div class="hrs-account">

        <div class="hrs-account-card">


            <div class="hrs-avatar">

                <?php
                echo restaurantSidebarSafe($initial);
                ?>

            </div>


            <div class="hrs-account-info">

                <div class="hrs-account-name">

                    <?php
                    echo restaurantSidebarSafe($ownerName);
                    ?>

                </div>


                <div class="hrs-account-status">

                    <?php

                    echo $isApproved
                        ? 'ACTIVE'
                        : strtoupper($status);

                    ?>

                </div>

            </div>


        </div>

    </div>


</aside>