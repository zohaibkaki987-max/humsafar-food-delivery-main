<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/customer-header.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {
    function h($value)
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
| Get Approved / Active Deals
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The query tries to keep the deal customer-side only when:
| - deal is approved
| - deal is active
|
*/

$deals = [];

$sql = "
    SELECT
        d.*,
        r.name AS restaurant_name,
        r.image AS restaurant_image
    FROM deals d
    INNER JOIN restaurants r
        ON r.id = d.restaurant_id
    WHERE
        r.status = 1
        AND d.status = 1
    ORDER BY d.id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $deals[] = $row;

    }

}


/*
|--------------------------------------------------------------------------
| Image Helper
|--------------------------------------------------------------------------
*/

function dealImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (
        strpos($image, 'http://') === 0 ||
        strpos($image, 'https://') === 0
    ) {
        return $image;
    }

    /*
     * Deal image
     */
    return 'assets/images/deals/' .
        ltrim($image, '/');
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

    <title>Deals - Humsafar</title>


    <!-- Existing Project CSS -->

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
        | PAGE
        |--------------------------------------------------------------------------
        */

        .deals-page {

            width: 100%;

            max-width: 1250px;

            margin: 0 auto;

            padding:
                35px 20px 60px;
        }


        /*
        |--------------------------------------------------------------------------
        | HERO
        |--------------------------------------------------------------------------
        */

        .deals-hero {

            position: relative;

            overflow: hidden;

            padding:
                35px 38px;

            margin-bottom: 25px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #ed1748,
                    #f53b73
                );

            box-shadow:
                0 10px 28px
                rgba(237,23,72,.15);
        }


        .deals-hero::after {

            content: "";

            position: absolute;

            width: 190px;
            height: 190px;

            right: -60px;
            top: -80px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.08);
        }


        .deals-hero h1 {

            position: relative;

            z-index: 2;

            margin: 0 0 8px;

            color: #fff;

            font-size: 31px;

            font-weight: 800;
        }


        .deals-hero h1 i {

            margin-right: 8px;
        }


        .deals-hero p {

            position: relative;

            z-index: 2;

            margin: 0;

            color:
                rgba(255,255,255,.92);

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | DEAL FILTER
        |--------------------------------------------------------------------------
        */

        .deals-toolbar {

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 13px;

            margin-bottom: 25px;

            padding: 16px;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 15px;

            box-shadow:
                0 6px 20px
                rgba(0,0,0,.045);
        }


        .deal-filter label {

            display: block;

            margin-bottom: 6px;

            color: #444;

            font-size: 12px;

            font-weight: 700;
        }


        .deal-filter input,
        .deal-filter select {

            width: 100%;

            height: 42px;

            padding:
                0 12px;

            border:
                1px solid #ddd;

            border-radius: 9px;

            background: #fff;

            color: #333;

            font-family: inherit;

            font-size: 13px;

            outline: none;
        }


        .deal-filter input:focus,
        .deal-filter select:focus {

            border-color: #ed1748;

            box-shadow:
                0 0 0 3px
                rgba(237,23,72,.08);
        }


        /*
        |--------------------------------------------------------------------------
        | GRID
        |--------------------------------------------------------------------------
        */

        .deals-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0,1fr)
                );

            gap: 22px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .deal-card {

            overflow: hidden;

            display: flex;

            flex-direction: column;

            min-width: 0;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 17px;

            box-shadow:
                0 7px 23px
                rgba(0,0,0,.055);

            transition:
                transform .25s ease,
                box-shadow .25s ease;
        }


        .deal-card:hover {

            transform:
                translateY(-5px);

            box-shadow:
                0 15px 32px
                rgba(0,0,0,.10);
        }


        /*
        |--------------------------------------------------------------------------
        | IMAGE
        |--------------------------------------------------------------------------
        */

        .deal-image {

            position: relative;

            width: 100%;

            height: 205px;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff0f4,
                    #ffe1ea
                );
        }


        .deal-image img {

            width: 100%;

            height: 100%;

            display: block;

            object-fit: cover;

            transition:
                transform .35s ease;
        }


        .deal-card:hover
        .deal-image img {

            transform:
                scale(1.05);
        }


        .deal-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ed1748;

            font-size: 55px;
        }


        /*
        |--------------------------------------------------------------------------
        | DEAL BADGE
        |--------------------------------------------------------------------------
        */

        .deal-badge {

            position: absolute;

            left: 12px;

            top: 12px;

            padding:
                7px 11px;

            border-radius: 20px;

            background:
                #ed1748;

            color: #fff;

            font-size: 10px;

            font-weight: 800;

            box-shadow:
                0 4px 12px
                rgba(0,0,0,.15);
        }


        .deal-badge i {

            margin-right: 4px;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        .deal-content {

            flex: 1;

            display: flex;

            flex-direction: column;

            padding: 17px;
        }


        .deal-title {

            margin: 0;

            color: #222;

            font-size: 19px;

            line-height: 1.3;

            font-weight: 800;
        }


        .deal-restaurant {

            display: flex;

            align-items: center;

            gap: 6px;

            margin-top: 7px;

            color: #ed1748;

            font-size: 12px;

            font-weight: 700;
        }


        .deal-description {

            margin:
                9px 0 15px;

            color: #777;

            font-size: 12.5px;

            line-height: 1.5;

            min-height: 38px;

            display:
                -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        /*
        |--------------------------------------------------------------------------
        | PRICE BOX
        |--------------------------------------------------------------------------
        */

        .deal-price-box {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            padding:
                12px;

            margin-bottom: 13px;

            border-radius: 11px;

            background:
                #fff5f7;

            border:
                1px solid #ffe1e8;
        }


        .deal-old-price {

            display: block;

            color: #999;

            font-size: 11px;

            text-decoration:
                line-through;
        }


        .deal-final-price {

            display: block;

            color: #ed1748;

            font-size: 22px;

            font-weight: 900;
        }


        .deal-markup {

            text-align: right;

            color: #777;

            font-size: 10px;

            line-height: 1.4;
        }


        .deal-markup strong {

            display: block;

            color: #159447;

            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | DETAILS
        |--------------------------------------------------------------------------
        */

        .deal-details {

            display: grid;

            grid-template-columns:
                repeat(2,1fr);

            gap: 7px;

            margin-bottom: 15px;
        }


        .deal-detail {

            min-height: 39px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 5px;

            padding:
                5px;

            border:
                1px solid #eee;

            border-radius: 8px;

            background: #fafafa;

            color: #555;

            font-size: 10px;

            font-weight: 600;

            text-align: center;
        }


        .deal-detail i {

            color: #ed1748;

            font-size: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | BUTTON
        |--------------------------------------------------------------------------
        */

        .deal-button {

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


        .deal-button:hover {

            color: #fff;

            transform:
                translateY(-1px);

            filter:
                brightness(.96);
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY
        |--------------------------------------------------------------------------
        */

        .deals-empty {

            grid-column:
                1 / -1;

            padding:
                65px 20px;

            background: #fff;

            border:
                1px solid #eee;

            border-radius: 17px;

            text-align: center;

            box-shadow:
                0 6px 20px
                rgba(0,0,0,.05);
        }


        .deals-empty i {

            display: block;

            margin-bottom: 15px;

            color: #ed1748;

            font-size: 50px;
        }


        .deals-empty h2 {

            margin: 0;

            color: #222;

            font-size: 22px;
        }


        .deals-empty p {

            margin:
                7px 0 0;

            color: #777;

            font-size: 13px;
        }


        .deal-card.hidden {

            display: none;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1000px) {

            .deals-grid {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );
            }

        }


        @media (max-width: 700px) {

            .deals-page {

                padding:
                    25px 13px 45px;
            }


            .deals-hero {

                padding:
                    27px 22px;
            }


            .deals-hero h1 {

                font-size: 25px;
            }


            .deals-hero p {

                font-size: 12px;
            }


            .deals-toolbar {

                grid-template-columns:
                    1fr;
            }


            .deals-grid {

                grid-template-columns:
                    1fr;

                gap: 18px;
            }


            .deal-image {

                height: 220px;
            }

            
        }

    </style>

</head>


<body>





<!-- =========================================================
     MAIN
========================================================= -->

<main class="deals-page">


    <!-- HERO -->

    <section class="deals-hero">

        <h1>

            <i class="fas fa-tags"></i>

            Today's Deals

        </h1>

        <p>

            Grab amazing deals from our approved restaurants
            at special prices.

        </p>

    </section>


    <!-- =====================================================
         FILTERS
    ====================================================== -->

    <section class="deals-toolbar">


        <!-- SEARCH -->

        <div class="deal-filter">

            <label for="deal-search">
                Search Deal
            </label>

            <input
                type="text"
                id="deal-search"
                placeholder="Search deals or restaurants..."
                autocomplete="off"
            >

        </div>


        <!-- SORT -->

        <div class="deal-filter">

            <label for="deal-sort">
                Sort By
            </label>

            <select id="deal-sort">

                <option value="latest">
                    Latest Deals
                </option>

                <option value="price-low">
                    Price: Low to High
                </option>

                <option value="price-high">
                    Price: High to Low
                </option>

                <option value="discount">
                    Highest Discount
                </option>

            </select>

        </div>


    </section>


    <!-- =====================================================
         DEALS
    ====================================================== -->

    <section
        class="deals-grid"
        id="deals-grid"
    >


        <?php if (!empty($deals)) { ?>


            <?php foreach ($deals as $deal) { ?>


                <?php

                /*
                |--------------------------------------------------------------------------
                | Deal Values
                |--------------------------------------------------------------------------
                */

                $dealId =
                    (int)(
                        $deal['id']
                        ?? 0
                    );


                $restaurantId =
                    (int)(
                        $deal['restaurant_id']
                        ?? 0
                    );


                $dealName =
                    trim(
                        (string)(
                            $deal['name']
                            ?? $deal['title']
                            ?? 'Special Deal'
                        )
                    );


                $description =
                    trim(
                        (string)(
                            $deal['description']
                            ?? ''
                        )
                    );


                $restaurantName =
                    trim(
                        (string)(
                            $deal['restaurant_name']
                            ?? 'Restaurant'
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | Owner Price
                |--------------------------------------------------------------------------
                */

                $ownerPrice =
                    (float)(
                        $deal['price']
                        ?? $deal['deal_price']
                        ?? $deal['original_price']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Admin Markup
                |--------------------------------------------------------------------------
                |
                | Supports percentage OR fixed markup.
                |
                */

                $markupPercent =
                    (float)(
                        $deal['admin_markup_percent']
                        ?? 0
                    );


                $markupAmount =
                    (float)(
                        $deal['admin_markup']
                        ?? $deal['markup']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Calculate Admin Markup
                |--------------------------------------------------------------------------
                */

                if (
                    $markupPercent > 0
                ) {

                    $adminMarkupValue =
                        $ownerPrice *
                        ($markupPercent / 100);

                } else {

                    $adminMarkupValue =
                        $markupAmount;
                }


                /*
                |--------------------------------------------------------------------------
                | Final Customer Price
                |--------------------------------------------------------------------------
                */

                $finalPrice =
                    $ownerPrice +
                    $adminMarkupValue;


                /*
                |--------------------------------------------------------------------------
                | Discount
                |--------------------------------------------------------------------------
                */

                $discount =
                    (float)(
                        $deal['discount']
                        ?? $deal['discount_percent']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Image
                |--------------------------------------------------------------------------
                */

                $image =
                    dealImage(
                        $deal['image']
                        ?? ''
                    );


                /*
                |--------------------------------------------------------------------------
                | Valid Until
                |--------------------------------------------------------------------------
                */

                $validUntil =
                    trim(
                        (string)(
                            $deal['valid_until']
                            ?? $deal['end_date']
                            ?? ''
                        )
                    );


                ?>


                <!-- DEAL CARD -->

                <article
                    class="deal-card"

                    data-name="<?php

                        echo h(
                            strtolower(
                                $dealName
                                . ' '
                                . $restaurantName
                            )
                        );

                    ?>"

                    data-price="<?php
                        echo $finalPrice;
                    ?>"

                    data-discount="<?php
                        echo $discount;
                    ?>"

                    data-id="<?php
                        echo $dealId;
                    ?>"
                >


                    <!-- IMAGE -->

                    <div class="deal-image">


                        <?php if ($image !== '') { ?>


                            <img
                                src="<?php
                                    echo h($image);
                                ?>"

                                alt="<?php
                                    echo h($dealName);
                                ?>"

                                loading="lazy"

                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="deal-placeholder"
                                style="display:none;"
                            >

                                <i class="fas fa-tags"></i>

                            </div>


                        <?php } else { ?>


                            <div class="deal-placeholder">

                                <i class="fas fa-tags"></i>

                            </div>


                        <?php } ?>


                        <!-- DEAL BADGE -->

                        <div class="deal-badge">

                            <i class="fas fa-fire"></i>

                            Special Deal

                        </div>


                    </div>


                    <!-- CONTENT -->

                    <div class="deal-content">


                        <!-- DEAL NAME -->

                        <h2 class="deal-title">

                            <?php
                            echo h($dealName);
                            ?>

                        </h2>


                        <!-- RESTAURANT -->

                        <div class="deal-restaurant">

                            <i class="fas fa-store"></i>

                            <?php
                            echo h(
                                $restaurantName
                            );
                            ?>

                        </div>


                        <!-- DESCRIPTION -->

                        <p class="deal-description">

                            <?php

                            if ($description !== '') {

                                echo h(
                                    $description
                                );

                            } else {

                                echo
                                    'Enjoy this special restaurant deal on Humsafar.';

                            }

                            ?>

                        </p>


                        <!-- =================================================
                             PRICE
                        ================================================== -->

                        <div class="deal-price-box">


                            <div>

                                <?php if ($ownerPrice > 0) { ?>

                                    <span class="deal-old-price">

                                        Restaurant Price:
                                        Rs.
                                        <?php
                                        echo number_format(
                                            $ownerPrice,
                                            0
                                        );
                                        ?>

                                    </span>

                                <?php } ?>


                                <span class="deal-final-price">

                                    Rs.
                                    <?php
                                    echo number_format(
                                        $finalPrice,
                                        0
                                    );
                                    ?>

                                </span>

                            </div>


                            <!-- MARKUP INFO -->

                            <div class="deal-markup">

                                <?php if ($adminMarkupValue > 0) { ?>

                                    <strong>

                                        <i class="fas fa-check-circle"></i>

                                        Humsafar Price

                                    </strong>

                                    Includes service markup

                                <?php } else { ?>

                                    <strong>

                                        Special Price

                                    </strong>

                                <?php } ?>

                            </div>


                        </div>


                        <!-- DETAILS -->

                        <div class="deal-details">


                            <!-- DISCOUNT -->

                            <div class="deal-detail">

                                <i class="fas fa-percent"></i>

                                <span>

                                    <?php

                                    if ($discount > 0) {

                                        echo
                                            number_format(
                                                $discount,
                                                0
                                            )
                                            . '% OFF';

                                    } else {

                                        echo 'Special Offer';

                                    }

                                    ?>

                                </span>

                            </div>


                            <!-- VALIDITY -->

                            <div class="deal-detail">

                                <i class="fas fa-calendar"></i>

                                <span>

                                    <?php

                                    if (
                                        $validUntil !== ''
                                    ) {

                                        echo h(
                                            $validUntil
                                        );

                                    } else {

                                        echo
                                            'Limited Time';

                                    }

                                    ?>

                                </span>

                            </div>


                        </div>


                        <!-- VIEW DEAL -->

                        <a
                            href="deal.php?id=<?php
                                echo $dealId;
                            ?>"
                            class="deal-button"
                        >

                            View Deal

                            <i class="fas fa-arrow-right"></i>

                        </a>


                    </div>


                </article>


            <?php } ?>


            <!-- FILTER EMPTY -->

            <div
                id="deals-no-results"
                class="deals-empty"
                style="display:none;"
            >

                <i class="fas fa-search"></i>

                <h2>
                    No Deals Found
                </h2>

                <p>
                    Try searching for another deal or restaurant.
                </p>

            </div>


        <?php } else { ?>


            <!-- NO DEALS -->

            <div class="deals-empty">

                <i class="fas fa-tags"></i>

                <h2>
                    No Deals Available
                </h2>

                <p>
                    There are currently no approved deals available.
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
                'deals-grid'
            );


        if (!grid) {
            return;
        }


        const cards =
            Array.from(
                grid.querySelectorAll(
                    '.deal-card'
                )
            );


        const search =
            document.getElementById(
                'deal-search'
            );


        const sort =
            document.getElementById(
                'deal-sort'
            );


        const noResults =
            document.getElementById(
                'deals-no-results'
            );


        /*
        |--------------------------------------------------------------------------
        | FILTER
        |--------------------------------------------------------------------------
        */

        function filterDeals() {


            const searchValue =
                search
                    ? search.value
                        .trim()
                        .toLowerCase()
                    : '';


            let visible = 0;


            cards.forEach(
                function (card) {


                    const name =
                        (
                            card.dataset.name
                            || ''
                        ).toLowerCase();


                    if (
                        name.includes(
                            searchValue
                        )
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


            sortDeals();


            if (noResults) {

                noResults.style.display =
                    visible === 0
                        ? 'block'
                        : 'none';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | SORT
        |--------------------------------------------------------------------------
        */

        function sortDeals() {


            if (!sort) {
                return;
            }


            const value =
                sort.value;


            cards.sort(
                function (a, b) {


                    if (
                        value === 'price-low'
                    ) {

                        return (
                            parseFloat(
                                a.dataset.price || 0
                            )
                            -
                            parseFloat(
                                b.dataset.price || 0
                            )
                        );

                    }


                    if (
                        value === 'price-high'
                    ) {

                        return (
                            parseFloat(
                                b.dataset.price || 0
                            )
                            -
                            parseFloat(
                                a.dataset.price || 0
                            )
                        );

                    }


                    if (
                        value === 'discount'
                    ) {

                        return (
                            parseFloat(
                                b.dataset.discount || 0
                            )
                            -
                            parseFloat(
                                a.dataset.discount || 0
                            )
                        );

                    }


                    /*
                     * Latest
                     */

                    return (
                        parseInt(
                            b.dataset.id || 0
                        )
                        -
                        parseInt(
                            a.dataset.id || 0
                        )
                    );

                }
            );


            cards.forEach(
                function (card) {

                    grid.appendChild(
                        card
                    );

                }
            );


            if (noResults) {

                grid.appendChild(
                    noResults
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | EVENTS
        |--------------------------------------------------------------------------
        */

        if (search) {

            search.addEventListener(
                'input',
                filterDeals
            );

        }


        if (sort) {

            sort.addEventListener(
                'change',
                function () {

                    sortDeals();

                    filterDeals();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | INITIAL
        |--------------------------------------------------------------------------
        */

        filterDeals();

    }
);

</script>


</body>
</html>