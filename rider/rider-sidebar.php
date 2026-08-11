<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$riderName = $_SESSION['rider_name'] ?? 'Rider';

$riderStatus = strtolower(
    $_SESSION['rider_status'] ?? 'pending'
);

$currentPage = basename($_SERVER['PHP_SELF']);

$isApproved = ($riderStatus === 'approved');

function riderActive($page)
{
    global $currentPage;

    return $currentPage === $page
        ? 'active'
        : '';
}

?>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   RIDER SIDEBAR
========================================================= */

.rider-sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 223px;
    height: 100vh;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    flex-direction: column;

    z-index: 9999;

    overflow: hidden;
}


/* =========================================================
   BRAND
========================================================= */

.rider-brand {

    height: 96px;

    padding: 0 20px;

    display: flex;

    align-items: center;

    gap: 11px;

    border-bottom:
        1px solid
        rgba(255,255,255,.13);
}


.rider-brand-icon {

    width: 44px;
    height: 44px;

    border-radius: 11px;

    background: #ffffff;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;

    flex-shrink: 0;
}


.rider-brand-text {

    color: #ffffff;

    font-size: 21px;

    font-weight: 800;

    line-height: 1;
}


.rider-brand-subtitle {

    margin-top: 5px;

    color:
        rgba(255,255,255,.82);

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .5px;

    text-transform: uppercase;
}


/* =========================================================
   NAVIGATION
========================================================= */

.rider-navigation {

    flex: 1;

    padding:
        18px 12px 10px;

    overflow-y: auto;
}


.rider-section-title {

    margin:
        7px 11px 10px;

    color:
        rgba(255,255,255,.72);

    font-size: 8px;

    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;
}


/* =========================================================
   NAV ITEM
========================================================= */

.rider-nav-item {

    width: 100%;

    min-height: 42px;

    margin-bottom: 3px;

    padding:
        0 13px;

    border-radius: 9px;

    display: flex;

    align-items: center;

    gap: 13px;

    color: #ffffff;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    transition:
        background .2s ease,
        color .2s ease;
}


.rider-nav-item i {

    width: 17px;

    text-align: center;

    color: #ffffff;

    font-size: 12px;
}


/* =========================================================
   HOVER
========================================================= */

.rider-nav-item:hover {

    background:
        rgba(255,255,255,.12);

    color: #ffffff;
}


/* =========================================================
   ACTIVE
========================================================= */

.rider-nav-item.active {

    background: #ffffff;

    color: #ed0038;

    box-shadow:
        0 4px 12px
        rgba(0,0,0,.06);
}


.rider-nav-item.active i {

    color: #ed0038;
}


/* =========================================================
   LOCKED
========================================================= */

.rider-nav-item.locked {

    opacity: .55;

    cursor: not-allowed;
}


.rider-nav-item.locked:hover {

    background:
        rgba(255,255,255,.05);
}


.rider-lock {

    margin-left: auto;

    font-size: 9px !important;
}


/* =========================================================
   BADGE
========================================================= */

.rider-badge {

    margin-left: auto;

    min-width: 18px;

    height: 18px;

    padding: 0 5px;

    border-radius: 20px;

    background: #ffffff;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 7px;

    font-weight: 800;
}


/* =========================================================
   BOTTOM RIDER PROFILE
========================================================= */

.rider-bottom {

    padding:
        10px 11px 12px;

    border-top:
        1px solid
        rgba(255,255,255,.13);
}


/* =========================================================
   PROFILE CARD
========================================================= */

.rider-profile-card {

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


.rider-avatar {

    width: 37px;
    height: 37px;

    border-radius: 50%;

    background: #ffd900;

    color: #171717;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;

    font-weight: 900;

    flex-shrink: 0;
}


.rider-profile-info {

    min-width: 0;

    flex: 1;
}


.rider-profile-name {

    color: #ffffff;

    font-size: 8px;

    font-weight: 800;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.rider-profile-status {

    margin-top: 4px;

    display: flex;

    align-items: center;

    gap: 4px;

    color:
        rgba(255,255,255,.75);

    font-size: 7px;

    font-weight: 700;

    text-transform: uppercase;
}


.status-dot {

    width: 5px;
    height: 5px;

    border-radius: 50%;

    background: #ffc400;
}


.status-dot.approved {

    background: #43d17b;
}


.status-dot.rejected {

    background: #ffb0bd;
}


/* =========================================================
   MOBILE
========================================================= */

.rider-menu-button {

    display: none;

    position: fixed;

    top: 15px;
    left: 15px;

    width: 40px;
    height: 40px;

    border: none;

    border-radius: 8px;

    background: #ed0038;

    color: #ffffff;

    z-index: 10001;

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


    .rider-menu-button {

        display: flex;

        align-items: center;

        justify-content: center;
    }

}

</style>


<!-- =========================================================
     MOBILE BUTTON
========================================================= -->

<button
    type="button"
    class="rider-menu-button"
    id="riderMenuButton"
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

    <div class="rider-brand">

        <div class="rider-brand-icon">

            <i class="fas fa-motorcycle"></i>

        </div>


        <div>

            <div class="rider-brand-text">
                Humsafar
            </div>

            <div class="rider-brand-subtitle">
                RIDER PARTNER
            </div>

        </div>

    </div>



    <!-- =====================================================
         NAVIGATION
    ====================================================== -->

    <nav class="rider-navigation">


        <div class="rider-section-title">
            MAIN MENU
        </div>


        <!-- DASHBOARD -->

        <a
            href="rider-dashboard.php"
            class="rider-nav-item <?= riderActive('rider-dashboard.php') ?>"
        >

            <i class="fas fa-chart-line"></i>

            <span>
                Dashboard
            </span>

        </a>


        <!-- AVAILABLE ORDERS -->

        <?php if ($isApproved): ?>

            <a
                href="rider-orders.php"
                class="rider-nav-item <?= riderActive('rider-orders.php') ?>"
            >

                <i class="fas fa-receipt"></i>

                <span>
                    Available Orders
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-nav-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-receipt"></i>

                <span>
                    Available Orders
                </span>

                <i class="fas fa-lock rider-lock"></i>

            </div>

        <?php endif; ?>


        <!-- MY DELIVERIES -->

        <?php if ($isApproved): ?>

            <a
                href="rider-deliveries.php"
                class="rider-nav-item <?= riderActive('rider-deliveries.php') ?>"
            >

                <i class="fas fa-motorcycle"></i>

                <span>
                    My Deliveries
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-nav-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-motorcycle"></i>

                <span>
                    My Deliveries
                </span>

                <i class="fas fa-lock rider-lock"></i>

            </div>

        <?php endif; ?>


        <!-- EARNINGS -->

        <?php if ($isApproved): ?>

            <a
                href="rider-earnings.php"
                class="rider-nav-item <?= riderActive('rider-earnings.php') ?>"
            >

                <i class="fas fa-wallet"></i>

                <span>
                    Earnings
                </span>

            </a>

        <?php else: ?>

            <div
                class="rider-nav-item locked"
                title="Available after admin approval"
            >

                <i class="fas fa-wallet"></i>

                <span>
                    Earnings
                </span>

                <i class="fas fa-lock rider-lock"></i>

            </div>

        <?php endif; ?>



        <!-- =================================================
             ACCOUNT
        ================================================== -->

        <div class="rider-section-title">

            ACCOUNT

        </div>


        <!-- PROFILE -->

        <a
            href="rider-profile.php"
            class="rider-nav-item <?= riderActive('rider-profile.php') ?>"
        >

            <i class="fas fa-user"></i>

            <span>
                Profile
            </span>

        </a>


        <!-- VEHICLE -->

        <a
            href="rider-vehicle.php"
            class="rider-nav-item <?= riderActive('rider-vehicle.php') ?>"
        >

            <i class="fas fa-motorcycle"></i>

            <span>
                Vehicle
            </span>

        </a>



        <!-- =================================================
             SUPPORT
        ================================================== -->

        <div class="rider-section-title">

            SUPPORT

        </div>


        <a
            href="rider-support.php"
            class="rider-nav-item <?= riderActive('rider-support.php') ?>"
        >

            <i class="far fa-circle-question"></i>

            <span>
                Support
            </span>

        </a>


    </nav>



    <!-- =====================================================
         BOTTOM PROFILE
    ====================================================== -->

    <div class="rider-bottom">

        <div class="rider-profile-card">


            <div class="rider-avatar">

                <?php

                $initials = '';

                $nameParts =
                    preg_split(
                        '/\s+/',
                        trim($riderName)
                    );

                if (
                    isset($nameParts[0]) &&
                    $nameParts[0] !== ''
                ) {

                    $initials .=
                        strtoupper(
                            substr(
                                $nameParts[0],
                                0,
                                1
                            )
                        );
                }

                if (
                    isset($nameParts[1]) &&
                    $nameParts[1] !== ''
                ) {

                    $initials .=
                        strtoupper(
                            substr(
                                $nameParts[1],
                                0,
                                1
                            )
                        );
                }

                echo e(
                    $initials ?: 'R'
                );

                ?>

            </div>


            <div class="rider-profile-info">

                <div class="rider-profile-name">

                    <?= e($riderName) ?>

                </div>


                <div class="rider-profile-status">

                    <span
                        class="status-dot <?= e($riderStatus) ?>"
                    ></span>

                    <?= e($riderStatus) ?>

                </div>

            </div>


        </div>

    </div>


</aside>



<script>

const riderSidebar =
    document.getElementById(
        'riderSidebar'
    );

const riderMenuButton =
    document.getElementById(
        'riderMenuButton'
    );


if (
    riderSidebar &&
    riderMenuButton
) {

    riderMenuButton.addEventListener(
        'click',
        function () {

            riderSidebar.classList.toggle(
                'open'
            );

        }
    );

}

</script>