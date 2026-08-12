```php
<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR RIDER SIDEBAR
|--------------------------------------------------------------------------
| This sidebar always checks the latest rider status from the database.
| Admin approval does NOT depend on an old session status.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

if (!isset($conn)) {

    require_once __DIR__ . '/../includes/config.php';

}


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('rider_e')) {

    function rider_e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPage = basename(
    $_SERVER['PHP_SELF']
);


/*
|--------------------------------------------------------------------------
| Rider ID
|--------------------------------------------------------------------------
*/

$riderId = isset($_SESSION['rider_id'])
    ? (int)$_SESSION['rider_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/

$riderName =
    $_SESSION['rider_name'] ?? 'Rider';

$riderStatus = 'pending';


/*
|--------------------------------------------------------------------------
| GET LATEST RIDER STATUS FROM DATABASE
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We do NOT depend only on $_SESSION['rider_status'].
| The admin may approve the rider after the session was created.
|
*/

if (
    $riderId > 0 &&
    isset($conn) &&
    $conn
) {

    $statusStmt = $conn->prepare("
        SELECT
            full_name,
            status
        FROM riders
        WHERE id = ?
        LIMIT 1
    ");


    if ($statusStmt) {

        $statusStmt->bind_param(
            'i',
            $riderId
        );


        $statusStmt->execute();


        $statusResult =
            $statusStmt->get_result();


        $statusRow =
            $statusResult->fetch_assoc();


        $statusStmt->close();


        if ($statusRow) {

            /*
            |--------------------------------------------------------------------------
            | Latest Name
            |--------------------------------------------------------------------------
            */

            if (
                !empty(
                    $statusRow['full_name']
                )
            ) {

                $riderName =
                    $statusRow['full_name'];

                $_SESSION['rider_name'] =
                    $riderName;

            }


            /*
            |--------------------------------------------------------------------------
            | Latest Status
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $statusRow['status']
                )
            ) {

                $riderStatus =
                    strtolower(
                        trim(
                            (string)$statusRow['status']
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | Update Session
                |--------------------------------------------------------------------------
                */

                $_SESSION['rider_status'] =
                    $riderStatus;

            }

        }

    }

} else {

    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    */

    $riderStatus =
        strtolower(
            trim(
                (string)(
                    $_SESSION['rider_status']
                    ?? 'pending'
                )
            )
        );

}


/*
|--------------------------------------------------------------------------
| APPROVAL / ACCESS CHECK
|--------------------------------------------------------------------------
|
| These statuses mean the rider can use delivery features.
|
*/

$isApproved = in_array(
    $riderStatus,
    [
        'approved',
        'active'
    ],
    true
);


/*
|--------------------------------------------------------------------------
| Active Page Helper
|--------------------------------------------------------------------------
*/

function riderNavActive($page)
{
    global $currentPage;

    return $currentPage === $page
        ? 'active'
        : '';
}


/*
|--------------------------------------------------------------------------
| Rider Initials
|--------------------------------------------------------------------------
*/

$nameParts = preg_split(
    '/\s+/',
    trim($riderName)
);


$initials = '';


if (
    !empty(
        $nameParts[0]
    )
) {

    $initials .= strtoupper(
        substr(
            $nameParts[0],
            0,
            1
        )
    );

}


if (
    !empty(
        $nameParts[1]
    )
) {

    $initials .= strtoupper(
        substr(
            $nameParts[1],
            0,
            1
        )
    );

}


if ($initials === '') {

    $initials = 'R';

}

?>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   HUMSAFAR RIDER SIDEBAR
========================================================= */

.rider-sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 223px;
    height: 100vh;

    background: #ed0038;

    color: #fff;

    display: flex;

    flex-direction: column;

    z-index: 9999;

    overflow: hidden;
}


/* =========================================================
   BRAND
========================================================= */

.rider-sidebar-brand {

    height: 96px;

    padding: 0 14px;

    display: flex;

    align-items: center;

    gap: 10px;

    border-bottom:
        1px solid
        rgba(255,255,255,.14);
}


.rider-sidebar-brand-icon {

    width: 44px;
    height: 44px;

    flex-shrink: 0;

    border-radius: 11px;

    background: #fff;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}


.rider-sidebar-brand-name {

    color: #fff;

    font-size: 22px;

    font-weight: 800;

    line-height: 1;
}


.rider-sidebar-brand-subtitle {

    margin-top: 5px;

    color:
        rgba(255,255,255,.8);

    font-size: 8px;

    font-weight: 800;

    letter-spacing: .55px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.rider-sidebar-nav {

    flex: 1;

    padding:
        18px 12px 8px;

    overflow-y: auto;
}


.rider-sidebar-section {

    margin:
        9px 11px 10px;

    color:
        rgba(255,255,255,.78);

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;
}


/* =========================================================
   NAV ITEM
========================================================= */

.rider-sidebar-item {

    width: 100%;

    min-height: 45px;

    margin-bottom: 4px;

    padding:
        0 13px;

    display: flex;

    align-items: center;

    gap: 12px;

    border-radius: 9px;

    color: #fff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 700;

    transition:
        all .18s ease;
}


.rider-sidebar-item i {

    width: 19px;

    flex-shrink: 0;

    text-align: center;

    color: #fff;

    font-size: 14px;
}


/* =========================================================
   HOVER
========================================================= */

.rider-sidebar-item:hover {

    background:
        rgba(255,255,255,.12);

    color: #fff;
}


/* =========================================================
   ACTIVE
========================================================= */

.rider-sidebar-item.active {

    background: #fff;

    color: #ed0038;
}


.rider-sidebar-item.active i {

    color: #ed0038;
}


/* =========================================================
   LOCKED
========================================================= */

.rider-sidebar-item.locked {

    opacity: .55;

    cursor: not-allowed;

    user-select: none;
}


.rider-sidebar-item.locked:hover {

    background:
        rgba(255,255,255,.05);
}


.rider-sidebar-lock {

    margin-left: auto;

    width: auto !important;

    font-size: 10px !important;
}


/* =========================================================
   BOTTOM PROFILE
========================================================= */

.rider-sidebar-bottom {

    padding:
        10px 11px 11px;

    border-top:
        1px solid
        rgba(255,255,255,.14);
}


.rider-sidebar-profile {

    min-height: 61px;

    padding:
        9px 10px;

    border-radius: 10px;

    background:
        rgba(190,0,45,.55);

    display: flex;

    align-items: center;

    gap: 9px;
}


.rider-sidebar-avatar {

    width: 37px;
    height: 37px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #ffd900;

    color: #111;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    font-weight: 900;
}


.rider-sidebar-profile-info {

    min-width: 0;
}


.rider-sidebar-profile-name {

    color: #fff;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.rider-sidebar-profile-status {

    margin-top: 4px;

    display: flex;

    align-items: center;

    gap: 4px;

    color:
        rgba(255,255,255,.75);

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;
}


.rider-status-dot {

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: #ffc400;
}


.rider-status-dot.approved,
.rider-status-dot.active {

    background: #40d477;
}


.rider-status-dot.rejected,
.rider-status-dot.blocked {

    background: #ffb0bd;
}


/* =========================================================
   MOBILE
========================================================= */

.rider-sidebar-menu {

    display: none;

    position: fixed;

    top: 15px;
    left: 15px;

    width: 40px;
    height: 40px;

    border: 0;

    border-radius: 8px;

    background: #ed0038;

    color: #fff;

    z-index: 10000;

    cursor: pointer;
}


@media (max-width: 900px) {

    .rider-sidebar {

        transform:
            translateX(-100%);

        transition:
            transform .25s ease;
    }


    .rider-sidebar.open {

        transform:
            translateX(0);
    }


    .rider-sidebar-menu {

        display: flex;

        align-items: center;

        justify-content: center;
    }

}


/* =========================================================
   SCROLLBAR
========================================================= */

.rider-sidebar-nav::-webkit-scrollbar {

    width: 3px;
}


.rider-sidebar-nav::-webkit-scrollbar-thumb {

    background:
        rgba(255,255,255,.3);

    border-radius: 10px;
}

</style>


<!-- =========================================================
     MOBILE BUTTON
========================================================= -->

<button
    type="button"
    class="rider-sidebar-menu"
    id="riderSidebarMenu"
>

    <i class="fas fa-bars"></i>

</button>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside
    class="rider-sidebar"
    id="riderSidebar"
>


    <!-- =====================================================
         BRAND
    ====================================================== -->

    <div class="rider-sidebar-brand">

        <div class="rider-sidebar-brand-icon">

            <i class="fas fa-motorcycle"></i>

        </div>


        <div>

            <div class="rider-sidebar-brand-name">
                Humsafar
            </div>

            <div class="rider-sidebar-brand-subtitle">
                RIDER PARTNER
            </div>

        </div>

    </div>


    <!-- =====================================================
         NAVIGATION
    ====================================================== -->

    <nav class="rider-sidebar-nav">


        <!-- MAIN MENU -->

        <div class="rider-sidebar-section">
            MAIN MENU
        </div>


        <!-- DASHBOARD -->

        <a
            href="rider-dashboard.php"
            class="rider-sidebar-item
            <?= riderNavActive(
                'rider-dashboard.php'
            ) ?>"
        >

            <i class="fas fa-chart-line"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- =================================================
             AVAILABLE ORDERS
        ================================================== -->

        <?php if ($isApproved): ?>

            <a
                href="rider-orders.php"
                class="rider-sidebar-item
                <?= riderNavActive(
                    'rider-orders.php'
                ) ?>"
            >

                <i class="fas fa-receipt"></i>

                <span>
                    Available Orders
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-sidebar-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-receipt"></i>

                <span>
                    Available Orders
                </span>

                <i
                    class="fas fa-lock rider-sidebar-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- =================================================
             MY DELIVERIES
        ================================================== -->

        <?php if ($isApproved): ?>

            <a
                href="rider-deliveries.php"
                class="rider-sidebar-item
                <?= riderNavActive(
                    'rider-deliveries.php'
                ) ?>"
            >

                <i class="fas fa-motorcycle"></i>

                <span>
                    My Deliveries
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-sidebar-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-motorcycle"></i>

                <span>
                    My Deliveries
                </span>

                <i
                    class="fas fa-lock rider-sidebar-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- =================================================
             EARNINGS
        ================================================== -->

        <?php if ($isApproved): ?>

            <a
                href="rider-earnings.php"
                class="rider-sidebar-item
                <?= riderNavActive(
                    'rider-earnings.php'
                ) ?>"
            >

                <i class="fas fa-wallet"></i>

                <span>
                    Earnings
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-sidebar-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-wallet"></i>

                <span>
                    Earnings
                </span>

                <i
                    class="fas fa-lock rider-sidebar-lock"
                ></i>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ACCOUNT
        ================================================== -->

        <div class="rider-sidebar-section">
            ACCOUNT
        </div>


        <!-- PROFILE -->

        <a
            href="rider-profile.php"
            class="rider-sidebar-item
            <?= riderNavActive(
                'rider-profile.php'
            ) ?>"
        >

            <i class="fas fa-user"></i>

            <span>
                Profile
            </span>

        </a>


        <!-- VEHICLE -->

        <a
            href="rider-vehicle.php"
            class="rider-sidebar-item
            <?= riderNavActive(
                'rider-vehicle.php'
            ) ?>"
        >

            <i class="fas fa-motorcycle"></i>

            <span>
                Vehicle
            </span>

        </a>


        <!-- =================================================
             SUPPORT
        ================================================== -->

        <div class="rider-sidebar-section">
            SUPPORT
        </div>


        <a
            href="rider-support.php"
            class="rider-sidebar-item
            <?= riderNavActive(
                'rider-support.php'
            ) ?>"
        >

            <i class="far fa-circle-question"></i>

            <span>
                Support
            </span>

        </a>


    </nav>


    <!-- =====================================================
         RIDER PROFILE
    ====================================================== -->

    <div class="rider-sidebar-bottom">

        <div class="rider-sidebar-profile">


            <div class="rider-sidebar-avatar">

                <?= rider_e(
                    $initials
                ) ?>

            </div>


            <div class="rider-sidebar-profile-info">

                <div class="rider-sidebar-profile-name">

                    <?= rider_e(
                        $riderName
                    ) ?>

                </div>


                <div class="rider-sidebar-profile-status">


                    <span
                        class="rider-status-dot
                        <?= rider_e(
                            $riderStatus
                        ) ?>"
                    ></span>


                    <?= rider_e(
                        $riderStatus
                    ) ?>


                </div>

            </div>


        </div>

    </div>


</aside>


<script>

/*
|--------------------------------------------------------------------------
| Rider Sidebar Mobile Toggle
|--------------------------------------------------------------------------
*/

const riderSidebar =
    document.getElementById(
        'riderSidebar'
    );


const riderSidebarMenu =
    document.getElementById(
        'riderSidebarMenu'
    );


if (
    riderSidebar &&
    riderSidebarMenu
) {

    riderSidebarMenu.addEventListener(
        'click',
        function () {

            riderSidebar.classList.toggle(
                'open'
            );

        }
    );

}

</script>
```
