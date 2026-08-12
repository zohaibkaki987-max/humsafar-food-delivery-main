<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Get Approved Restaurants
|--------------------------------------------------------------------------
*/

$restaurants = [];

$sql = "
    SELECT *
    FROM restaurants
    WHERE status = 1
    ORDER BY rating DESC, id DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}

/*
|--------------------------------------------------------------------------
| Restaurant Image
|--------------------------------------------------------------------------
*/

function restaurantImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    /*
     * If database already contains a complete URL
     */
    if (
        strpos($image, 'http://') === 0 ||
        strpos($image, 'https://') === 0
    ) {
        return $image;
    }

    /*
     * Existing Humsafar restaurant image directory
     */
    return 'assets/images/restaurants/' . ltrim($image, '/');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Restaurants - Humsafar</title>

    <!-- Existing project CSS -->
    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | Restaurants Page
        |--------------------------------------------------------------------------
        */

        .restaurants-page {
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
            padding: 35px 20px 60px;
        }

        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

        .restaurants-hero {
            position: relative;
            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #ed1748,
                    #f53b73
                );

            border-radius: 18px;

            padding: 35px 38px;

            margin-bottom: 25px;

            box-shadow:
                0 10px 28px
                rgba(237, 23, 72, .15);
        }

        .restaurants-hero::after {
            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            right: -50px;
            top: -70px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.08);
        }

        .restaurants-hero h1 {
            position: relative;
            z-index: 2;

            margin: 0 0 8px;

            color: #fff;

            font-size: 31px;
            font-weight: 800;
        }

        .restaurants-hero h1 i {
            margin-right: 8px;
        }

        .restaurants-hero p {
            position: relative;
            z-index: 2;

            margin: 0;

            color: rgba(255,255,255,.92);

            font-size: 14px;
        }

        /*
        |--------------------------------------------------------------------------
        | Toolbar
        |--------------------------------------------------------------------------
        */

        .restaurants-toolbar {
            background: #fff;

            border: 1px solid #eee;

            border-radius: 15px;

            padding: 16px;

            margin-bottom: 25px;

            display: grid;

            grid-template-columns:
                1fr
                1fr
                1.5fr;

            gap: 13px;

            box-shadow:
                0 6px 20px
                rgba(0,0,0,.045);
        }

        .restaurant-filter label {
            display: block;

            margin-bottom: 6px;

            color: #444;

            font-size: 12px;
            font-weight: 700;
        }

        .restaurant-filter select,
        .restaurant-filter input {

            width: 100%;

            height: 42px;

            border: 1px solid #ddd;

            border-radius: 9px;

            background: #fff;

            outline: none;

            padding: 0 12px;

            font-family: inherit;

            color: #333;

            font-size: 13px;
        }

        .restaurant-filter input {
            padding-left: 38px;
        }

        .restaurant-filter select:focus,
        .restaurant-filter input:focus {
            border-color: #ed1748;

            box-shadow:
                0 0 0 3px
                rgba(237,23,72,.08);
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;

            left: 13px;
            top: 50%;

            transform:
                translateY(-50%);

            color: #999;

            font-size: 13px;

            pointer-events: none;
        }

        /*
        |--------------------------------------------------------------------------
        | Restaurant Grid
        |--------------------------------------------------------------------------
        */

        .restaurants-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap: 22px;
        }

        /*
        |--------------------------------------------------------------------------
        | Card
        |--------------------------------------------------------------------------
        */

        .restaurant-card {

            background: #fff;

            border: 1px solid #eee;

            border-radius: 17px;

            overflow: hidden;

            display: flex;

            flex-direction: column;

            min-width: 0;

            box-shadow:
                0 6px 22px
                rgba(0,0,0,.055);

            transition:
                transform .25s ease,
                box-shadow .25s ease;
        }

        .restaurant-card:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 14px 32px
                rgba(0,0,0,.10);
        }

        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        .restaurant-card-image {

            width: 100%;

            height: 205px;

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff0f4,
                    #ffe1ea
                );
        }

        .restaurant-card-image img {

            width: 100%;
            height: 100%;

            object-fit: cover;

            display: block;

            transition:
                transform .35s ease;
        }

        .restaurant-card:hover
        .restaurant-card-image img {

            transform:
                scale(1.05);
        }

        .restaurant-image-placeholder {

            width: 100%;
            height: 100%;

            display: flex;

            align-items: center;
            justify-content: center;

            color: #ed1748;

            font-size: 52px;
        }

        /*
        |--------------------------------------------------------------------------
        | Approved Badge
        |--------------------------------------------------------------------------
        */

        .approved-badge {

            position: absolute;

            left: 12px;
            top: 12px;

            padding:
                6px 10px;

            border-radius: 20px;

            background:
                rgba(255,255,255,.95);

            color: #159447;

            font-size: 10px;

            font-weight: 700;

            box-shadow:
                0 4px 12px
                rgba(0,0,0,.10);
        }

        .approved-badge i {
            margin-right: 4px;
        }

        /*
        |--------------------------------------------------------------------------
        | Rating
        |--------------------------------------------------------------------------
        */

        .restaurant-rating {

            position: absolute;

            right: 12px;
            bottom: 12px;

            min-width: 48px;

            height: 30px;

            padding:
                0 9px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 4px;

            border-radius: 18px;

            background: #fff;

            color: #333;

            font-size: 12px;

            font-weight: 800;

            box-shadow:
                0 4px 13px
                rgba(0,0,0,.15);
        }

        .restaurant-rating i {
            color: #f6a623;
        }

        /*
        |--------------------------------------------------------------------------
        | Card Content
        |--------------------------------------------------------------------------
        */

        .restaurant-card-content {

            padding: 17px;

            display: flex;

            flex-direction: column;

            flex: 1;
        }

        .restaurant-card-title {

            margin: 0;

            color: #222;

            font-size: 19px;

            line-height: 1.3;

            font-weight: 800;

            overflow-wrap: anywhere;
        }

        .restaurant-card-description {

            margin:
                7px 0 15px;

            min-height: 38px;

            color: #777;

            font-size: 12.5px;

            line-height: 1.5;

            display:
                -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }

        /*
        |--------------------------------------------------------------------------
        | Details
        |--------------------------------------------------------------------------
        */

        .restaurant-details {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 7px;

            margin-bottom: 15px;
        }

        .restaurant-detail {

            min-height: 40px;

            padding:
                5px 4px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 4px;

            border:
                1px solid #eee;

            border-radius: 8px;

            background: #fafafa;

            color: #555;

            font-size: 10px;

            font-weight: 600;

            text-align: center;
        }

        .restaurant-detail i {
            color: #ed1748;

            font-size: 10px;
        }

        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */

        .restaurant-address {

            display: flex;

            align-items: flex-start;

            gap: 7px;

            margin-bottom: 15px;

            color: #777;

            font-size: 11px;

            line-height: 1.5;
        }

        .restaurant-address i {

            color: #ed1748;

            margin-top: 2px;

            flex-shrink: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Button
        |--------------------------------------------------------------------------
        */

        .restaurant-view-btn {

            width: 100%;

            min-height: 43px;

            margin-top: auto;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #ed1748,
                    #f53b73
                );

            color: #fff;

            font-size: 13px;

            font-weight: 700;

            transition:
                transform .2s ease,
                filter .2s ease;
        }

        .restaurant-view-btn:hover {

            color: #fff;

            transform:
                translateY(-1px);

            filter:
                brightness(.96);
        }

        /*
        |--------------------------------------------------------------------------
        | No Result
        |--------------------------------------------------------------------------
        */

        .restaurants-empty {

            grid-column: 1 / -1;

            padding: 65px 20px;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 17px;

            text-align: center;

            box-shadow:
                0 6px 20px
                rgba(0,0,0,.05);
        }

        .restaurants-empty i {

            display: block;

            margin-bottom: 15px;

            color: #ed1748;

            font-size: 50px;
        }

        .restaurants-empty h2 {

            margin: 0;

            color: #222;

            font-size: 22px;
        }

        .restaurants-empty p {

            margin:
                7px 0 0;

            color: #777;

            font-size: 13px;
        }

        .restaurant-card.hidden {
            display: none;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .restaurants-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

            .restaurants-toolbar {

                grid-template-columns:
                    1fr 1fr;
            }

            .restaurant-filter:last-child {

                grid-column:
                    1 / -1;
            }
        }

        @media (max-width: 700px) {

            .restaurants-page {

                padding:
                    25px 13px 45px;
            }

            .restaurants-hero {

                padding:
                    27px 22px;
            }

            .restaurants-hero h1 {

                font-size: 25px;
            }

            .restaurants-hero p {

                font-size: 12px;
            }

            .restaurants-toolbar {

                grid-template-columns:
                    1fr;
            }

            .restaurant-filter:last-child {

                grid-column:
                    auto;
            }

            .restaurants-grid {

                grid-template-columns:
                    1fr;

                gap: 18px;
            }

            .restaurant-card-image {

                height: 220px;
            }
        }

    </style>

</head>

<body>


<?php
/*
|--------------------------------------------------------------------------
| EXISTING CUSTOMER HEADER
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do NOT create another header here.
| The existing customer-header.php handles the customer navbar/header.
|
*/

require_once __DIR__ . '/includes/customer-header.php';
?>


<!-- =========================================================
     RESTAURANTS PAGE
========================================================= -->

<main class="restaurants-page">


    <!-- PAGE HERO -->

    <section class="restaurants-hero">

        <h1>

            <i class="fas fa-store"></i>

            Restaurants

        </h1>

        <p>
            Discover approved restaurants and order your
            favorite food from Humsafar.
        </p>

    </section>


    <!-- =====================================================
         FILTERS
    ====================================================== -->

    <section class="restaurants-toolbar">


        <!-- SORT -->

        <div class="restaurant-filter">

            <label for="restaurant-sort">
                Sort By
            </label>

            <select id="restaurant-sort">

                <option value="rating">
                    Highest Rated
                </option>

                <option value="name">
                    Name
                </option>

                <option value="delivery">
                    Fastest Delivery
                </option>

            </select>

        </div>


        <!-- DELIVERY -->

        <div class="restaurant-filter">

            <label for="delivery-filter">
                Delivery
            </label>

            <select id="delivery-filter">

                <option value="all">
                    All Restaurants
                </option>

                <option value="free">
                    Free Delivery
                </option>

                <option value="paid">
                    Paid Delivery
                </option>

            </select>

        </div>


        <!-- SEARCH -->

        <div class="restaurant-filter">

            <label for="restaurant-search">
                Search Restaurant
            </label>

            <div class="search-wrapper">

                <i class="fas fa-search"></i>

                <input
                    type="text"
                    id="restaurant-search"
                    placeholder="Search restaurant..."
                    autocomplete="off"
                >

            </div>

        </div>


    </section>


    <!-- =====================================================
         RESTAURANT CARDS
    ====================================================== -->

    <section
        class="restaurants-grid"
        id="restaurants-grid"
    >


        <?php if (!empty($restaurants)) { ?>


            <?php foreach ($restaurants as $restaurant) { ?>


                <?php

                $restaurantId =
                    (int)(
                        $restaurant['id']
                        ?? 0
                    );

                $restaurantName =
                    trim(
                        (string)(
                            $restaurant['name']
                            ?? 'Restaurant'
                        )
                    );

                $description =
                    trim(
                        (string)(
                            $restaurant['description']
                            ?? ''
                        )
                    );

                $rating =
                    (float)(
                        $restaurant['rating']
                        ?? 0
                    );

                $deliveryTime =
                    trim(
                        (string)(
                            $restaurant['delivery_time']
                            ?? ''
                        )
                    );

                $deliveryFee =
                    (float)(
                        $restaurant['delivery_fee']
                        ?? 0
                    );

                $address =
                    trim(
                        (string)(
                            $restaurant['address']
                            ?? ''
                        )
                    );

                $image =
                    restaurantImage(
                        $restaurant['image']
                        ?? ''
                    );

                ?>


                <article
                    class="restaurant-card"

                    data-name="<?php
                        echo h(
                            strtolower(
                                $restaurantName
                            )
                        );
                    ?>"

                    data-rating="<?php
                        echo $rating;
                    ?>"

                    data-delivery="<?php

                        preg_match(
                            '/\d+/',
                            $deliveryTime,
                            $timeMatch
                        );

                        echo
                            !empty($timeMatch)
                                ? (int)$timeMatch[0]
                                : 999;

                    ?>"

                    data-delivery-fee="<?php
                        echo $deliveryFee;
                    ?>"
                >


                    <!-- IMAGE -->

                    <div class="restaurant-card-image">


                        <?php if ($image !== '') { ?>


                            <img
                                src="<?php
                                    echo h($image);
                                ?>"

                                alt="<?php
                                    echo h(
                                        $restaurantName
                                    );
                                ?>"

                                loading="lazy"

                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="restaurant-image-placeholder"
                                style="display:none;"
                            >

                                <i class="fas fa-store"></i>

                            </div>


                        <?php } else { ?>


                            <div class="restaurant-image-placeholder">

                                <i class="fas fa-store"></i>

                            </div>


                        <?php } ?>


                        <!-- APPROVED -->

                        <div class="approved-badge">

                            <i class="fas fa-check-circle"></i>

                            Approved

                        </div>


                        <!-- RATING -->

                        <div class="restaurant-rating">

                            <i class="fas fa-star"></i>

                            <?php
                            echo number_format(
                                $rating,
                                1
                            );
                            ?>

                        </div>


                    </div>


                    <!-- CONTENT -->

                    <div class="restaurant-card-content">


                        <!-- NAME -->

                        <h2 class="restaurant-card-title">

                            <?php
                            echo h(
                                $restaurantName
                            );
                            ?>

                        </h2>


                        <!-- DESCRIPTION -->

                        <p class="restaurant-card-description">

                            <?php

                            if ($description !== '') {

                                echo h(
                                    $description
                                );

                            } else {

                                echo
                                    'Delicious food and great service.';

                            }

                            ?>

                        </p>


                        <!-- DETAILS -->

                        <div class="restaurant-details">


                            <!-- RATING -->

                            <div class="restaurant-detail">

                                <i class="fas fa-star"></i>

                                <span>

                                    <?php
                                    echo number_format(
                                        $rating,
                                        1
                                    );
                                    ?>

                                </span>

                            </div>


                            <!-- TIME -->

                            <div class="restaurant-detail">

                                <i class="fas fa-clock"></i>

                                <span>

                                    <?php

                                    if (
                                        $deliveryTime !== ''
                                    ) {

                                        echo h(
                                            $deliveryTime
                                        );

                                    } else {

                                        echo 'N/A';

                                    }

                                    ?>

                                </span>

                            </div>


                            <!-- DELIVERY -->

                            <div class="restaurant-detail">

                                <i class="fas fa-motorcycle"></i>

                                <span>

                                    <?php

                                    if (
                                        $deliveryFee <= 0
                                    ) {

                                        echo 'Free';

                                    } else {

                                        echo
                                            'Rs. ' .
                                            number_format(
                                                $deliveryFee,
                                                0
                                            );

                                    }

                                    ?>

                                </span>

                            </div>


                        </div>


                        <!-- ADDRESS -->

                        <?php if ($address !== '') { ?>

                            <div class="restaurant-address">

                                <i class="fas fa-location-dot"></i>

                                <span>

                                    <?php
                                    echo h(
                                        $address
                                    );
                                    ?>

                                </span>

                            </div>

                        <?php } ?>


                        <!-- BUTTON -->

                        <a
                            href="restaurant.php?id=<?php
                                echo $restaurantId;
                            ?>"
                            class="restaurant-view-btn"
                        >

                            View Restaurant

                            <i class="fas fa-arrow-right"></i>

                        </a>


                    </div>


                </article>


            <?php } ?>


            <!-- FILTER EMPTY -->

            <div
                id="restaurants-no-filter-results"
                class="restaurants-empty"
                style="display:none;"
            >

                <i class="fas fa-search"></i>

                <h2>
                    No Restaurant Found
                </h2>

                <p>
                    Try another restaurant name or change the filter.
                </p>

            </div>


        <?php } else { ?>


            <!-- NO APPROVED RESTAURANTS -->

            <div class="restaurants-empty">

                <i class="fas fa-store-slash"></i>

                <h2>
                    No Restaurants Available
                </h2>

                <p>
                    There are currently no approved restaurants available.
                </p>

            </div>


        <?php } ?>


    </section>


</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const grid =
            document.getElementById(
                'restaurants-grid'
            );


        if (!grid) {
            return;
        }


        const cards =
            Array.from(
                grid.querySelectorAll(
                    '.restaurant-card'
                )
            );


        const searchInput =
            document.getElementById(
                'restaurant-search'
            );


        const deliveryFilter =
            document.getElementById(
                'delivery-filter'
            );


        const sortSelect =
            document.getElementById(
                'restaurant-sort'
            );


        const emptyResult =
            document.getElementById(
                'restaurants-no-filter-results'
            );


        /*
        |--------------------------------------------------------------------------
        | Apply Filters
        |--------------------------------------------------------------------------
        */

        function applyFilters() {


            const search =
                searchInput
                    ? searchInput.value
                        .trim()
                        .toLowerCase()
                    : '';


            const delivery =
                deliveryFilter
                    ? deliveryFilter.value
                    : 'all';


            let visible = 0;


            cards.forEach(
                function (card) {


                    const name =
                        (
                            card.dataset.name
                            || ''
                        ).toLowerCase();


                    const fee =
                        parseFloat(
                            card.dataset.deliveryFee
                            || 0
                        );


                    const matchesSearch =
                        name.includes(
                            search
                        );


                    let matchesDelivery =
                        true;


                    if (
                        delivery === 'free'
                    ) {

                        matchesDelivery =
                            fee <= 0;

                    }


                    if (
                        delivery === 'paid'
                    ) {

                        matchesDelivery =
                            fee > 0;

                    }


                    if (
                        matchesSearch
                        &&
                        matchesDelivery
                    ) {

                        card.classList.remove(
                            'hidden'
                        );

                        visible++;

                    } else {

                        card.classList.add(
                            'hidden'
                        );

                    }

                }
            );


            sortRestaurants();


            if (emptyResult) {

                emptyResult.style.display =
                    visible === 0
                        ? 'block'
                        : 'none';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        function sortRestaurants() {


            if (!sortSelect) {
                return;
            }


            const value =
                sortSelect.value;


            cards.sort(
                function (a, b) {


                    if (
                        value === 'rating'
                    ) {

                        return (
                            parseFloat(
                                b.dataset.rating || 0
                            )
                            -
                            parseFloat(
                                a.dataset.rating || 0
                            )
                        );

                    }


                    if (
                        value === 'delivery'
                    ) {

                        return (
                            parseInt(
                                a.dataset.delivery || 999
                            )
                            -
                            parseInt(
                                b.dataset.delivery || 999
                            )
                        );

                    }


                    if (
                        value === 'name'
                    ) {

                        return (
                            (
                                a.dataset.name || ''
                            ).localeCompare(
                                b.dataset.name || ''
                            )
                        );

                    }


                    return 0;

                }
            );


            cards.forEach(
                function (card) {

                    grid.appendChild(
                        card
                    );

                }
            );


            if (emptyResult) {

                grid.appendChild(
                    emptyResult
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Events
        |--------------------------------------------------------------------------
        */

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                applyFilters
            );

        }


        if (deliveryFilter) {

            deliveryFilter.addEventListener(
                'change',
                applyFilters
            );

        }


        if (sortSelect) {

            sortSelect.addEventListener(
                'change',
                function () {

                    sortRestaurants();

                    applyFilters();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Initial
        |--------------------------------------------------------------------------
        */

        applyFilters();

    }
);

</script>


</body>
</html>