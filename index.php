<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR CUSTOMER HOME
|--------------------------------------------------------------------------
| File:
| C:\xampp\htdocs\humsafar-food-delivery-main\index.php
|
| Customer header:
| C:\xampp\htdocs\humsafar-food-delivery-main\includes\customer-header.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   LOGIN
========================================================= */

$isLoggedIn =
    isset($_SESSION['user_id']) &&
    (int)$_SESSION['user_id'] > 0;

$userId =
    $isLoggedIn
        ? (int)$_SESSION['user_id']
        : 0;


/* =========================================================
   HELPER
========================================================= */

function customer_home_h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   IMAGE HELPERS
========================================================= */

function customerCategoryImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }

    return
        'assets/images/categories/' .
        basename($image);
}


function customerRestaurantImage($image)
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    if (
        preg_match(
            '/^(https?:\/\/|data:|\/)/i',
            $image
        )
    ) {
        return $image;
    }

    if (
        strpos($image, 'assets/') === 0
    ) {
        return $image;
    }

    return
        'assets/images/restaurants/' .
        basename($image);
}


/* =========================================================
   SELECTED CATEGORY
========================================================= */

$selectedCategoryId = 0;

if (
    isset($_GET['category_id']) &&
    is_numeric($_GET['category_id'])
) {
    $selectedCategoryId =
        (int)$_GET['category_id'];
}


/* =========================================================
   ADMIN MARKUP
========================================================= */

$markupPercent = 0.00;

$settingsTable =
    $conn->query(
        "SHOW TABLES LIKE 'app_settings'"
    );

if (
    $settingsTable &&
    $settingsTable->num_rows > 0
) {

    $stmt = $conn->prepare("
        SELECT setting_value
        FROM app_settings
        WHERE setting_key = 'platform_markup_percent'
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->execute();

        $row =
            $stmt
                ->get_result()
                ->fetch_assoc();

        $stmt->close();

        if ($row) {

            $markupPercent =
                max(
                    0,
                    (float)$row['setting_value']
                );
        }
    }
}


/* =========================================================
   CATEGORIES
========================================================= */

$categories = [];

$result =
    $conn->query("
        SELECT
            id,
            name,
            image
        FROM categories
        WHERE status = 1
        ORDER BY id ASC
    ");

if ($result) {

    while (
        $row = $result->fetch_assoc()
    ) {
        $categories[] = $row;
    }
}


/* =========================================================
   SELECTED CATEGORY NAME
========================================================= */

$selectedCategoryName = '';

if ($selectedCategoryId > 0) {

    $stmt = $conn->prepare("
        SELECT name
        FROM categories
        WHERE id = ?
          AND status = 1
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $selectedCategoryId
        );

        $stmt->execute();

        $row =
            $stmt
                ->get_result()
                ->fetch_assoc();

        $stmt->close();

        if ($row) {

            $selectedCategoryName =
                $row['name'];

        } else {

            $selectedCategoryId = 0;
        }
    }
}


/* =========================================================
   APPROVED RESTAURANTS
========================================================= */

$restaurants = [];

if ($selectedCategoryId > 0) {

    $stmt = $conn->prepare("
        SELECT DISTINCT
            r.id,
            r.name,
            r.description,
            r.image,
            r.address,
            r.phone,
            r.rating,
            r.delivery_time,
            r.delivery_fee
        FROM restaurants r
        INNER JOIN menu_items mi
            ON mi.restaurant_id = r.id
        WHERE r.status = 1
          AND mi.status = 1
          AND mi.category_id = ?
        ORDER BY
            r.rating DESC,
            r.id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $selectedCategoryId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row = $result->fetch_assoc()
        ) {
            $restaurants[] = $row;
        }

        $stmt->close();
    }

} else {

    $result =
        $conn->query("
            SELECT
                id,
                name,
                description,
                image,
                address,
                phone,
                rating,
                delivery_time,
                delivery_fee
            FROM restaurants
            WHERE status = 1
            ORDER BY
                rating DESC,
                id DESC
        ");

    if ($result) {

        while (
            $row = $result->fetch_assoc()
        ) {
            $restaurants[] = $row;
        }
    }
}


/* =========================================================
   MENU ITEMS
========================================================= */

$restaurantItems = [];

foreach (
    $restaurants
    as $restaurant
) {

    $restaurantId =
        (int)$restaurant['id'];

    $restaurantItems[$restaurantId] =
        [];


    if ($selectedCategoryId > 0) {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
                price,
                image,
                category,
                category_id
            FROM menu_items
            WHERE restaurant_id = ?
              AND status = 1
              AND category_id = ?
            ORDER BY id ASC
            LIMIT 6
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $restaurantId,
                $selectedCategoryId
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            while (
                $row = $result->fetch_assoc()
            ) {

                $restaurantItems[$restaurantId][] =
                    $row;
            }

            $stmt->close();
        }

    } else {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
                price,
                image,
                category,
                category_id
            FROM menu_items
            WHERE restaurant_id = ?
              AND status = 1
            ORDER BY id ASC
            LIMIT 4
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $restaurantId
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            while (
                $row = $result->fetch_assoc()
            ) {

                $restaurantItems[$restaurantId][] =
                    $row;
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   PRICE CALCULATION
========================================================= */

function customerFinalPrice(
    $price,
    $markupPercent
) {

    $price =
        (float)$price;

    return
        $price +
        (
            $price *
            ((float)$markupPercent / 100)
        );
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

<meta
    name="description"
    content="Humsafar Food Delivery - Find approved restaurants and order your favorite food."
>

<title>
    Humsafar - Food Delivery
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    background: #ffffff;
    color: #222222;
    font-family:
        "Segoe UI",
        Arial,
        Helvetica,
        sans-serif;
    overflow-x: hidden;
}

a {
    text-decoration: none;
}


/* =========================================================
   HERO
========================================================= */

.home-hero {

    position: relative;

    min-height: 560px;

    padding:
        80px 6%;

    display: flex;

    align-items: center;

    overflow: hidden;

    background:
        linear-gradient(
            90deg,
            rgba(170,0,38,.91),
            rgba(237,0,56,.64),
            rgba(237,0,56,.28)
        ),
        url("https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1900&q=85")
        center/cover
        no-repeat;
}

.home-hero::after {

    content: "";

    position: absolute;

    inset: 0;

    background:
        linear-gradient(
            180deg,
            rgba(0,0,0,.05),
            rgba(0,0,0,.14)
        );

    pointer-events: none;
}

.hero-inner {

    position: relative;

    z-index: 2;

    width: 100%;

    max-width: 1300px;

    margin: 0 auto;
}

.hero-content {

    max-width: 760px;

    color: #ffffff;
}

.hero-badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        9px 14px;

    border:
        1px solid rgba(255,255,255,.24);

    border-radius: 25px;

    background:
        rgba(255,255,255,.12);

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 19px;
}

.hero-content h1 {

    margin:
        0 0 16px;

    font-size:
        clamp(40px, 6vw, 68px);

    line-height: 1.02;

    font-weight: 900;

    letter-spacing: -1.8px;
}

.hero-content h1 span {
    color: #ffd34d;
}

.hero-content p {

    max-width: 620px;

    margin:
        0 0 27px;

    color:
        rgba(255,255,255,.92);

    font-size: 16px;

    line-height: 1.7;
}


/* =========================================================
   HERO SEARCH
========================================================= */

.hero-search-box {

    width: 100%;

    max-width: 720px;

    background: #ffffff;

    border-radius: 14px;

    padding: 8px;

    display: flex;

    align-items: center;

    gap: 8px;

    box-shadow:
        0 18px 40px
        rgba(70,0,20,.20);
}

.hero-search-icon {

    width: 45px;

    height: 45px;

    flex-shrink: 0;

    border-radius: 10px;

    background: #fff1f5;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}

.hero-search-input {

    flex: 1;

    min-width: 0;

    height: 45px;

    border: 0;

    outline: none;

    padding:
        0 8px;

    color: #333333;

    font-size: 13px;
}

.hero-search-input::placeholder {
    color: #999999;
}

.hero-search-button {

    height: 45px;

    padding:
        0 23px;

    border: 0;

    border-radius: 10px;

    background: #ed0038;

    color: #ffffff;

    cursor: pointer;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;
}

.hero-search-button:hover {
    background: #d90035;
}


/* =========================================================
   HERO QUICK LINKS
========================================================= */

.hero-quick {

    margin-top: 18px;

    display: flex;

    flex-wrap: wrap;

    gap: 9px;
}

.hero-quick a {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        8px 12px;

    border-radius: 20px;

    background:
        rgba(255,255,255,.11);

    border:
        1px solid rgba(255,255,255,.18);

    color: #ffffff;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   GENERAL SECTION
========================================================= */

.home-section {

    width: 100%;

    max-width: 1400px;

    margin: 0 auto;

    padding:
        52px 5%;
}

.section-header {

    margin-bottom: 24px;

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;
}

.section-kicker {

    display: block;

    margin-bottom: 6px;

    color: #ed0038;

    font-size: 10px;

    font-weight: 900;

    text-transform: uppercase;

    letter-spacing: 1.6px;
}

.section-header h2 {

    margin: 0;

    color: #222222;

    font-size: 30px;

    line-height: 1.1;

    font-weight: 900;
}

.section-header p {

    margin:
        7px 0 0;

    color: #888888;

    font-size: 12px;
}

.section-link {

    color: #ed0038;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;
}


/* =========================================================
   CATEGORIES
========================================================= */

.category-grid {

    display: grid;

    grid-template-columns:
        repeat(6, 1fr);

    gap: 13px;
}

.category-card {

    min-height: 132px;

    padding:
        15px 10px;

    border:
        1px solid #eeeeee;

    border-radius: 13px;

    background: #ffffff;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    box-shadow:
        0 7px 22px
        rgba(0,0,0,.04);

    transition:
        transform .18s ease,
        border-color .18s ease,
        box-shadow .18s ease;
}

.category-card:hover {

    transform:
        translateY(-3px);

    border-color:
        #efb4c6;

    box-shadow:
        0 12px 28px
        rgba(237,0,56,.08);
}

.category-card.active {

    border-color:
        #ed0038;

    background:
        #fff6f8;

    box-shadow:
        0 8px 25px
        rgba(237,0,56,.12);
}

.category-image {

    width: 62px;

    height: 62px;

    border-radius: 50%;

    background: #fff1f5;

    overflow: hidden;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;
}

.category-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}

.category-image i {

    color: #ed0038;

    font-size: 24px;
}

.category-card strong {

    color: #333333;

    font-size: 12px;

    font-weight: 800;
}

.category-card span {

    margin-top: 4px;

    color: #999999;

    font-size: 9px;
}


/* =========================================================
   RESTAURANT AREA
========================================================= */

.restaurants-area {

    background: #fafafa;

    border-top:
        1px solid #f1f1f1;

    border-bottom:
        1px solid #f1f1f1;
}

.restaurant-heading {

    margin-bottom: 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.restaurant-heading h2 {

    margin: 0;

    font-size: 28px;

    font-weight: 900;
}

.restaurant-heading p {

    margin:
        6px 0 0;

    color: #888888;

    font-size: 11px;
}

.restaurant-filter-pill {

    padding:
        8px 12px;

    border-radius: 20px;

    background: #fff1f5;

    color: #ed0038;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}


/* =========================================================
   RESTAURANT ROW
========================================================= */

.restaurant-row {

    width: 100%;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 15px;

    margin-bottom: 17px;

    overflow: hidden;

    box-shadow:
        0 6px 20px
        rgba(0,0,0,.035);
}

.restaurant-top {

    padding:
        16px 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom:
        1px solid #eeeeee;
}

.restaurant-info {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 0;
}

.restaurant-logo {

    width: 58px;

    height: 58px;

    border-radius: 11px;

    background: #fff1f5;

    overflow: hidden;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;
}

.restaurant-logo img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}

.restaurant-logo i {

    color: #ed0038;

    font-size: 22px;
}

.restaurant-name {

    color: #222222;

    font-size: 16px;

    font-weight: 900;
}

.restaurant-description {

    margin-top: 4px;

    color: #888888;

    font-size: 10px;

    max-width: 580px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.restaurant-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 7px;
}

.restaurant-meta span {

    display: inline-flex;

    align-items: center;

    gap: 4px;

    color: #777777;

    font-size: 9px;
}

.restaurant-meta i {

    color: #ed0038;
}


/* =========================================================
   RESTAURANT BUTTON
========================================================= */

.restaurant-view {

    flex-shrink: 0;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 13px;

    border-radius: 8px;

    background: #ed0038;

    color: #ffffff;

    font-size: 10px;

    font-weight: 800;
}

.restaurant-view:hover {

    background: #d90035;
}


/* =========================================================
   MENU ITEMS
========================================================= */

.menu-items-row {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 12px;

    padding:
        15px 17px 17px;
}

.menu-card {

    min-width: 0;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 11px;

    overflow: hidden;

    transition:
        transform .18s ease,
        box-shadow .18s ease;
}

.menu-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 22px
        rgba(0,0,0,.07);
}

.menu-image {

    height: 125px;

    background: #fff1f5;

    overflow: hidden;

    display: flex;

    align-items: center;

    justify-content: center;
}

.menu-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;
}

.menu-image i {

    color: #ed0038;

    font-size: 25px;
}

.menu-body {

    padding: 11px;
}

.menu-name {

    color: #222222;

    font-size: 12px;

    font-weight: 800;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

.menu-description {

    min-height: 28px;

    margin-top: 5px;

    color: #929292;

    font-size: 9px;

    line-height: 1.45;

    display:
        -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;
}

.menu-price-row {

    margin-top: 10px;

    padding-top: 9px;

    border-top:
        1px solid #eeeeee;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 8px;
}

.menu-price {

    color: #ed0038;

    font-size: 14px;

    font-weight: 900;
}

.menu-old-price {

    display: block;

    margin-top: 2px;

    color: #999999;

    font-size: 8px;

    text-decoration: line-through;
}

.menu-add {

    width: 30px;

    height: 30px;

    border-radius: 50%;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 12px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-restaurants {

    padding:
        65px 20px;

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 15px;

    text-align: center;
}

.empty-icon {

    width: 65px;

    height: 65px;

    margin:
        0 auto 14px;

    border-radius: 50%;

    background: #fff1f5;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;
}

.empty-restaurants h3 {

    margin:
        0 0 7px;

    font-size: 17px;
}

.empty-restaurants p {

    margin: 0;

    color: #888888;

    font-size: 10px;
}


/* =========================================================
   WHY
========================================================= */

.why-section {

    background: #fff8fa;

    border-top:
        1px solid #f5e2e8;

    border-bottom:
        1px solid #f5e2e8;
}

.why-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}

.why-card {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 14px;

    padding: 24px;
}

.why-icon {

    width: 45px;

    height: 45px;

    border-radius: 11px;

    background: #fff1f5;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

    margin-bottom: 14px;
}

.why-card h3 {

    margin:
        0 0 7px;

    font-size: 14px;
}

.why-card p {

    margin: 0;

    color: #888888;

    font-size: 10px;

    line-height: 1.6;
}


/* =========================================================
   CTA
========================================================= */

.cta-section {

    padding:
        0 5% 65px;
}

.cta-box {

    max-width: 1400px;

    margin: 0 auto;

    min-height: 190px;

    padding:
        30px 36px;

    border-radius: 18px;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f14c77
        );

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 30px;
}

.cta-box h2 {

    margin:
        0 0 8px;

    font-size: 28px;

    font-weight: 900;
}

.cta-box p {

    max-width: 600px;

    margin: 0;

    color:
        rgba(255,255,255,.87);

    font-size: 12px;

    line-height: 1.6;
}

.cta-button {

    flex-shrink: 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    min-height: 45px;

    padding:
        0 21px;

    border-radius: 9px;

    background: #ffd34d;

    color: #222222;

    font-size: 11px;

    font-weight: 900;
}


/* =========================================================
   FOOTER
========================================================= */

.home-footer {

    background: #1f1f23;

    color: #ffffff;

    padding:
        52px 5% 25px;
}

.footer-grid {

    max-width: 1400px;

    margin: 0 auto;

    display: grid;

    grid-template-columns:
        1.3fr
        1fr
        1fr
        1fr;

    gap: 30px;

    padding-bottom: 35px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);
}

.footer-brand h3 {

    margin:
        0 0 9px;

    font-size: 23px;
}

.footer-brand p {

    max-width: 290px;

    margin: 0;

    color: #ababab;

    font-size: 10px;

    line-height: 1.7;
}

.footer-column h4 {

    margin:
        0 0 12px;

    font-size: 12px;
}

.footer-column a {

    display: block;

    margin-bottom: 8px;

    color: #a9a9a9;

    font-size: 10px;
}

.footer-column a:hover {

    color: #ff6d95;
}

.social-links {

    display: flex;

    gap: 8px;

    margin-top: 13px;
}

.social-links a {

    width: 31px;

    height: 31px;

    margin: 0;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ffffff;
}

.social-links a:hover {

    background: #ed0038;
}

.footer-bottom {

    max-width: 1400px;

    margin: 0 auto;

    padding-top: 21px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    color: #777777;

    font-size: 9px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width:1100px) {

    .category-grid {
        grid-template-columns:
            repeat(4,1fr);
    }

    .menu-items-row {
        grid-template-columns:
            repeat(3,1fr);
    }

    .why-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .footer-grid {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width:850px) {

    .category-grid {
        grid-template-columns:
            repeat(3,1fr);
    }

    .restaurant-top {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .restaurant-view {

        width: 100%;

        justify-content:
            center;
    }
}

@media (max-width:700px) {

    .home-hero {

        min-height:
            520px;

        padding:
            55px 5%;
    }

    .hero-content h1 {

        font-size:
            clamp(
                36px,
                11vw,
                50px
            );
    }

    .hero-content p {
        font-size: 14px;
    }

    .hero-search-box {
        flex-wrap: wrap;
    }

    .hero-search-input {

        width:
            calc(100% - 60px);

        flex: auto;
    }

    .hero-search-button {
        width: 100%;
    }

    .section-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .restaurant-heading {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .menu-items-row {

        grid-template-columns:
            repeat(2,1fr);
    }

    .why-grid {
        grid-template-columns: 1fr;
    }

    .cta-box {

        flex-direction:
            column;

        align-items:
            flex-start;

        padding:
            27px;
    }
}

@media (max-width:560px) {

    .home-section {
        padding:
            43px 4%;
    }

    .category-grid {

        grid-template-columns:
            repeat(3,1fr);

        gap: 9px;
    }

    .category-card {

        min-height:
            125px;

        padding:
            11px 7px;
    }

    .category-image {

        width: 52px;

        height: 52px;
    }

    .category-card strong {
        font-size: 10px;
    }

    .category-card span {
        font-size: 8px;
    }

    .restaurant-name {
        font-size: 15px;
    }

    .menu-items-row {

        grid-template-columns:
            1fr 1fr;

        padding:
            11px;
    }

    .menu-image {
        height: 105px;
    }

    .menu-name {
        font-size: 11px;
    }

    .menu-price {
        font-size: 13px;
    }

    .footer-grid {
        grid-template-columns: 1fr;
    }

    .footer-bottom {

        align-items:
            flex-start;

        flex-direction:
            column;
    }
}

@media (max-width:400px) {

    .category-grid {
        grid-template-columns:
            repeat(2,1fr);
    }

    .menu-items-row {
        grid-template-columns: 1fr;
    }

    .menu-image {
        height: 145px;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     CUSTOMER HEADER
========================================================= -->

<?php

include __DIR__ . '/includes/customer-header.php';

?>


<!-- =========================================================
     HERO
========================================================= -->

<section class="home-hero">

    <div class="hero-inner">

        <div class="hero-content">


            <div class="hero-badge">

                <i class="fas fa-bolt"></i>

                Fast &amp; Fresh Food Delivery

            </div>


            <h1>

                Your Favorite Food,

                <span>
                    Delivered.
                </span>

            </h1>


            <p>

                Discover delicious food from approved
                restaurants around you and get it delivered
                fresh to your doorstep.

            </p>


            <form
                action="restaurants.php"
                method="GET"
                class="hero-search-box"
            >

                <div
                    class="hero-search-icon"
                >

                    <i
                        class="fas fa-location-dot"
                    ></i>

                </div>


                <input
                    type="text"
                    name="search"
                    class="hero-search-input"
                    placeholder="Search restaurants or food..."
                    autocomplete="off"
                >


                <button
                    type="submit"
                    class="hero-search-button"
                >

                    <i class="fas fa-search"></i>

                    Find Food

                </button>

            </form>


            <div class="hero-quick">


                <a href="#categories">

                    <i
                        class="fas fa-utensils"
                    ></i>

                    Browse Categories

                </a>


                <a href="#restaurants">

                    <i
                        class="fas fa-store"
                    ></i>

                    Restaurants

                </a>


                <?php if ($isLoggedIn): ?>

                    <a href="my_orders.php">

                        <i
                            class="fas fa-receipt"
                        ></i>

                        My Orders

                    </a>

                <?php else: ?>

                    <a href="register.php">

                        <i
                            class="fas fa-user-plus"
                        ></i>

                        Create Account

                    </a>

                <?php endif; ?>


            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     CATEGORIES
========================================================= -->

<section
    class="home-section"
    id="categories"
>

    <div class="section-header">

        <div>

            <span
                class="section-kicker"
            >
                Browse Categories
            </span>


            <h2>
                What are you craving?
            </h2>


            <p>
                Choose a category and see restaurants
                serving that food.
            </p>

        </div>


        <?php if (
            $selectedCategoryId > 0
        ): ?>

            <a
                href="index.php#categories"
                class="section-link"
            >

                Show All

                <i
                    class="fas fa-arrow-right"
                ></i>

            </a>

        <?php endif; ?>

    </div>


    <div class="category-grid">


        <?php foreach (
            $categories
            as $category
        ): ?>


            <?php

            $categoryId =
                (int)$category['id'];

            $categoryIsActive =
                $categoryId ===
                $selectedCategoryId;

            $categoryImage =
                customerCategoryImage(
                    $category['image']
                    ?? ''
                );

            ?>


            <a
                href="index.php?category_id=<?= $categoryId ?>#restaurants"
                class="category-card
                <?= $categoryIsActive
                    ? 'active'
                    : '' ?>"
            >


                <div class="category-image">


                    <?php if (
                        $categoryImage !== ''
                    ): ?>

                        <img
                            src="<?= customer_home_h(
                                $categoryImage
                            ) ?>"
                            alt="<?= customer_home_h(
                                $category['name']
                            ) ?>"
                            onerror="this.style.display='none';"
                        >

                    <?php else: ?>

                        <i
                            class="fas fa-utensils"
                        ></i>

                    <?php endif; ?>


                </div>


                <strong>

                    <?= customer_home_h(
                        $category['name']
                    ) ?>

                </strong>


                <span>

                    <?=
                        $categoryIsActive
                            ? 'Selected'
                            : 'View restaurants'
                    ?>

                </span>


            </a>


        <?php endforeach; ?>


    </div>

</section>



<!-- =========================================================
     RESTAURANTS
========================================================= -->

<section
    class="restaurants-area"
    id="restaurants"
>

    <div class="home-section">


        <div class="restaurant-heading">


            <div>


                <?php if (
                    $selectedCategoryId > 0
                ): ?>


                    <h2>

                        <?= customer_home_h(
                            $selectedCategoryName
                        ) ?>

                        Restaurants

                    </h2>


                    <p>

                        Approved restaurants serving
                        <?= customer_home_h(
                            strtolower(
                                $selectedCategoryName
                            )
                        ) ?>.

                    </p>


                <?php else: ?>


                    <h2>
                        Restaurants
                    </h2>


                    <p>
                        Restaurants approved by
                        Humsafar administration.
                    </p>


                <?php endif; ?>


            </div>


            <?php if (
                $selectedCategoryId > 0
            ): ?>


                <div
                    class="restaurant-filter-pill"
                >

                    <i
                        class="fas fa-filter"
                    ></i>

                    <?= customer_home_h(
                        $selectedCategoryName
                    ) ?>

                </div>


            <?php endif; ?>


        </div>



        <?php if (
            !empty($restaurants)
        ): ?>


            <?php foreach (
                $restaurants
                as $restaurant
            ): ?>


                <?php

                $restaurantId =
                    (int)$restaurant['id'];

                $items =
                    $restaurantItems[
                        $restaurantId
                    ] ?? [];

                $restaurantImage =
                    customerRestaurantImage(
                        $restaurant['image']
                        ?? ''
                    );

                ?>


                <article
                    class="restaurant-row"
                >


                    <div class="restaurant-top">


                        <div class="restaurant-info">


                            <div
                                class="restaurant-logo"
                            >


                                <?php if (
                                    $restaurantImage !== ''
                                ): ?>

                                    <img
                                        src="<?= customer_home_h(
                                            $restaurantImage
                                        ) ?>"
                                        alt="<?= customer_home_h(
                                            $restaurant['name']
                                        ) ?>"
                                        onerror="this.style.display='none';"
                                    >

                                <?php else: ?>

                                    <i
                                        class="fas fa-store"
                                    ></i>

                                <?php endif; ?>


                            </div>


                            <div>


                                <div
                                    class="restaurant-name"
                                >

                                    <?= customer_home_h(
                                        $restaurant['name']
                                    ) ?>

                                </div>


                                <div
                                    class="restaurant-description"
                                >

                                    <?= customer_home_h(
                                        $restaurant['description']
                                        ??
                                        'Fresh food and great service.'
                                    ) ?>

                                </div>


                                <div
                                    class="restaurant-meta"
                                >


                                    <span>

                                        <i
                                            class="fas fa-star"
                                        ></i>

                                        <?= number_format(
                                            (float)(
                                                $restaurant['rating']
                                                ?? 0
                                            ),
                                            1
                                        ) ?>

                                    </span>


                                    <?php if (
                                        !empty(
                                            $restaurant['delivery_time']
                                        )
                                    ): ?>

                                        <span>

                                            <i
                                                class="fas fa-clock"
                                            ></i>

                                            <?= customer_home_h(
                                                $restaurant[
                                                    'delivery_time'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                    <?php if (
                                        !empty(
                                            $restaurant['address']
                                        )
                                    ): ?>

                                        <span>

                                            <i
                                                class="fas fa-location-dot"
                                            ></i>

                                            <?= customer_home_h(
                                                $restaurant[
                                                    'address'
                                                ]
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                </div>

                            </div>


                        </div>


                        <a
                            href="restaurant.php?id=<?= $restaurantId ?>"
                            class="restaurant-view"
                        >

                            View Restaurant

                            <i
                                class="fas fa-arrow-right"
                            ></i>

                        </a>


                    </div>


                    <?php if (
                        !empty($items)
                    ): ?>


                        <div
                            class="menu-items-row"
                        >


                            <?php foreach (
                                $items
                                as $item
                            ): ?>


                                <?php

                                $originalPrice =
                                    (float)$item['price'];

                                $customerPrice =
                                    customerFinalPrice(
                                        $originalPrice,
                                        $markupPercent
                                    );

                                $itemImage =
                                    customerRestaurantImage(
                                        $item['image']
                                        ?? ''
                                    );

                                ?>


                                <div
                                    class="menu-card"
                                >


                                    <div
                                        class="menu-image"
                                    >


                                        <?php if (
                                            $itemImage !== ''
                                        ): ?>

                                            <img
                                                src="<?= customer_home_h(
                                                    $itemImage
                                                ) ?>"
                                                alt="<?= customer_home_h(
                                                    $item['name']
                                                ) ?>"
                                                onerror="this.style.display='none';"
                                            >

                                        <?php else: ?>

                                            <i
                                                class="fas fa-utensils"
                                            ></i>

                                        <?php endif; ?>


                                    </div>


                                    <div
                                        class="menu-body"
                                    >


                                        <div
                                            class="menu-name"
                                            title="<?= customer_home_h(
                                                $item['name']
                                            ) ?>"
                                        >

                                            <?= customer_home_h(
                                                $item['name']
                                            ) ?>

                                        </div>


                                        <div
                                            class="menu-description"
                                        >

                                            <?= customer_home_h(
                                                $item['description']
                                                ??
                                                'Delicious food from this restaurant.'
                                            ) ?>

                                        </div>


                                        <div
                                            class="menu-price-row"
                                        >


                                            <div>


                                                <div
                                                    class="menu-price"
                                                >

                                                    Rs.

                                                    <?= number_format(
                                                        $customerPrice,
                                                        0
                                                    ) ?>

                                                </div>


                                                <?php if (
                                                    $markupPercent > 0
                                                ): ?>

                                                    <span
                                                        class="menu-old-price"
                                                    >

                                                        Rs.

                                                        <?= number_format(
                                                            $originalPrice,
                                                            0
                                                        ) ?>

                                                    </span>

                                                <?php endif; ?>


                                            </div>


                                            <a
                                                href="restaurant.php?id=<?= $restaurantId ?>"
                                                class="menu-add"
                                                title="View Restaurant"
                                            >

                                                <i
                                                    class="fas fa-plus"
                                                ></i>

                                            </a>


                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div
                            style="
                                padding:18px;
                                color:#888;
                                font-size:10px;
                            "
                        >

                            No active menu items available.

                        </div>


                    <?php endif; ?>


                </article>


            <?php endforeach; ?>


        <?php else: ?>


            <div
                class="empty-restaurants"
            >


                <div
                    class="empty-icon"
                >

                    <i
                        class="fas fa-store-slash"
                    ></i>

                </div>


                <?php if (
                    $selectedCategoryId > 0
                ): ?>


                    <h3>
                        No Restaurant Found
                    </h3>


                    <p>

                        Currently no approved restaurant
                        is serving

                        <?= customer_home_h(
                            strtolower(
                                $selectedCategoryName
                            )
                        ) ?>.

                    </p>


                <?php else: ?>


                    <h3>
                        No Approved Restaurants
                    </h3>


                    <p>
                        There are currently no approved
                        restaurants available.
                    </p>


                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>

</section>



<!-- =========================================================
     WHY HUMSAFAR
========================================================= -->

<section
    class="why-section"
>

    <div
        class="home-section"
    >


        <div
            class="section-header"
        >

            <div>

                <span
                    class="section-kicker"
                >
                    Why Humsafar
                </span>


                <h2>
                    Made for hungry people
                </h2>


                <p>
                    Everything you need for a simple
                    food ordering experience.
                </p>

            </div>

        </div>


        <div
            class="why-grid"
        >


            <div
                class="why-card"
            >

                <div
                    class="why-icon"
                >

                    <i
                        class="fas fa-bolt"
                    ></i>

                </div>


                <h3>
                    Fast Delivery
                </h3>


                <p>
                    Get your favorite meals delivered
                    from restaurants around you.
                </p>

            </div>


            <div
                class="why-card"
            >

                <div
                    class="why-icon"
                >

                    <i
                        class="fas fa-store"
                    ></i>

                </div>


                <h3>
                    Restaurants
                </h3>


                <p>
                    Browse restaurants approved by
                    Humsafar administration.
                </p>

            </div>


            <div
                class="why-card"
            >

                <div
                    class="why-icon"
                >

                    <i
                        class="fas fa-mobile-screen-button"
                    ></i>

                </div>


                <h3>
                    Easy Ordering
                </h3>


                <p>
                    Browse food, select a restaurant,
                    order and track everything easily.
                </p>

            </div>


        </div>

    </div>

</section>



<!-- =========================================================
     CTA
========================================================= -->

<section
    class="cta-section"
>

    <div
        class="cta-box"
    >


        <div>

            <h2>
                Ready to order?
            </h2>


            <p>
                Explore approved restaurants and find
                something delicious today.
            </p>

        </div>


        <a
            href="#categories"
            class="cta-button"
        >

            <i
                class="fas fa-utensils"
            ></i>

            Browse Food

        </a>


    </div>

</section>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer
    class="home-footer"
>


    <div
        class="footer-grid"
    >


        <div
            class="footer-brand"
        >

            <h3>
                Humsafar
            </h3>


            <p>
                Your everyday food delivery partner.
                Discover delicious meals from approved
                local restaurants.
            </p>


            <div
                class="social-links"
            >

                <a
                    href="#"
                    aria-label="Facebook"
                >

                    <i
                        class="fab fa-facebook-f"
                    ></i>

                </a>


                <a
                    href="#"
                    aria-label="Instagram"
                >

                    <i
                        class="fab fa-instagram"
                    ></i>

                </a>


                <a
                    href="#"
                    aria-label="YouTube"
                >

                    <i
                        class="fab fa-youtube"
                    ></i>

                </a>

            </div>

        </div>


        <div
            class="footer-column"
        >

            <h4>
                Humsafar
            </h4>


            <a href="#">
                About Us
            </a>


            <a href="#">
                Blog
            </a>


            <a href="#">
                Careers
            </a>

        </div>


        <div
            class="footer-column"
        >

            <h4>
                For Customers
            </h4>


            <a href="#categories">
                Categories
            </a>


            <a href="#restaurants">
                Restaurants
            </a>


            <a href="deals.php">
                Deals
            </a>


            <a href="my_orders.php">
                My Orders
            </a>

        </div>


        <div
            class="footer-column"
        >

            <h4>
                Support
            </h4>


            <a href="#">
                Help &amp; Support
            </a>


            <a href="#">
                Contact Us
            </a>


            <a href="#">
                Privacy Policy
            </a>


            <a href="#">
                Terms &amp; Conditions
            </a>

        </div>


    </div>


    <div
        class="footer-bottom"
    >

        <span>

            © <?= date('Y') ?>
            Humsafar Food Delivery.
            All rights reserved.

        </span>


        <span>
            Made for better food delivery.
        </span>

    </div>


</footer>


</body>

</html>