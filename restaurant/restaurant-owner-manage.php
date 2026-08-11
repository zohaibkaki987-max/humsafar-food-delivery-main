<?php

/*
|--------------------------------------------------------------------------
| Humsafar Food Delivery
| Restaurant Owner - Manage Restaurant
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FIND LOGGED IN OWNER
========================================================= */

$owner = null;


/*
|--------------------------------------------------------------------------
| Try owner ID from session
|--------------------------------------------------------------------------
*/

$ownerId = 0;

$possibleIds = [
    $_SESSION['restaurant_owner_id'] ?? 0,
    $_SESSION['restaurant_user_id'] ?? 0,
    $_SESSION['owner_id'] ?? 0
];

foreach ($possibleIds as $id) {

    $id = (int)$id;

    if ($id <= 0) {
        continue;
    }

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

    if (!$stmt) {
        continue;
    }

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        $result = $stmt->get_result();

        if ($result) {
            $owner = $result->fetch_assoc();
        }
    }

    $stmt->close();

    if ($owner) {
        $ownerId = (int)$owner['id'];
        break;
    }
}


/*
|--------------------------------------------------------------------------
| If ID not found, try email
|--------------------------------------------------------------------------
*/

if (!$owner) {

    $ownerEmail =
        $_SESSION['restaurant_owner_email']
        ?? $_SESSION['email']
        ?? '';

    $ownerEmail = trim($ownerEmail);

    if ($ownerEmail !== '') {

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

            if ($stmt->execute()) {

                $result = $stmt->get_result();

                if ($result) {
                    $owner =
                        $result->fetch_assoc();
                }
            }

            $stmt->close();
        }

        if ($owner) {
            $ownerId =
                (int)$owner['id'];
        }
    }
}


/* =========================================================
   OWNER NOT FOUND
========================================================= */

if (!$owner) {

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
}


/* =========================================================
   OWNER INFORMATION
========================================================= */

$ownerName =
    trim(
        $owner['full_name'] ?? ''
    );

$ownerEmail =
    trim(
        $owner['email'] ?? ''
    );

$ownerPhone =
    trim(
        $owner['phone'] ?? ''
    );

$ownerRestaurantName =
    trim(
        $owner['restaurant_name'] ?? ''
    );

$ownerStatus =
    strtolower(
        trim(
            $owner['status'] ?? 'pending'
        )
    );


/*
|--------------------------------------------------------------------------
| Approved statuses
|--------------------------------------------------------------------------
*/

$isApproved = in_array(
    $ownerStatus,
    [
        'approved',
        'active'
    ],
    true
);


/* =========================================================
   VARIABLES
========================================================= */

$successMessage = '';
$errorMessage = '';

$restaurant = null;
$restaurantId = 0;


/* =========================================================
   RESTAURANT IMAGE DIRECTORY
========================================================= */

$imageDirectory =
    __DIR__ . '/../assets/images/restaurants/';

$imageUrl =
    '../assets/images/restaurants/';


if (!is_dir($imageDirectory)) {

    @mkdir(
        $imageDirectory,
        0755,
        true
    );
}


/* =========================================================
   CHECK owner_id COLUMN
========================================================= */

$hasOwnerId = false;

$columnResult =
    $conn->query(
        "SHOW COLUMNS FROM restaurants LIKE 'owner_id'"
    );

if (
    $columnResult &&
    $columnResult->num_rows > 0
) {
    $hasOwnerId = true;
}


/* =========================================================
   FIND RESTAURANT
========================================================= */

if ($isApproved) {

    /*
    |--------------------------------------------------------------------------
    | First priority: owner_id
    |--------------------------------------------------------------------------
    */

    if ($hasOwnerId) {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
                image,
                address,
                phone,
                rating,
                delivery_time,
                delivery_fee,
                status
            FROM restaurants
            WHERE owner_id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $ownerId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result) {

                    $restaurant =
                        $result->fetch_assoc();
                }
            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Fallback: restaurant name
    |--------------------------------------------------------------------------
    */

    if (
        !$restaurant &&
        $ownerRestaurantName !== ''
    ) {

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
                image,
                address,
                phone,
                rating,
                delivery_time,
                delivery_fee,
                status
            FROM restaurants
            WHERE name = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "s",
                $ownerRestaurantName
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result) {

                    $restaurant =
                        $result->fetch_assoc();
                }
            }

            $stmt->close();
        }
    }


    if ($restaurant) {

        $restaurantId =
            (int)$restaurant['id'];
    }
}


/* =========================================================
   CURRENT VALUES
========================================================= */

$currentName =
    $restaurant['name']
    ?? $ownerRestaurantName;

$currentDescription =
    $restaurant['description']
    ?? '';

$currentImage =
    $restaurant['image']
    ?? '';

$currentAddress =
    $restaurant['address']
    ?? '';

$currentPhone =
    $restaurant['phone']
    ?? $ownerPhone;


/* =========================================================
   CREATE / UPDATE RESTAURANT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_restaurant'])
) {

    if (!$isApproved) {

        $errorMessage =
            'Your account must be approved before managing your restaurant.';

    } else {

        $newName =
            trim(
                $_POST['restaurant_name']
                ?? ''
            );

        $newDescription =
            trim(
                $_POST['description']
                ?? ''
            );

        $newAddress =
            trim(
                $_POST['address']
                ?? ''
            );

        $newPhone =
            trim(
                $_POST['phone']
                ?? ''
            );


        /* -----------------------------------------
           VALIDATION
        ----------------------------------------- */

        if ($newName === '') {

            $errorMessage =
                'Please enter restaurant name.';

        } elseif ($newAddress === '') {

            $errorMessage =
                'Please enter restaurant address.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Image
            |--------------------------------------------------------------------------
            */

            $imageName =
                $currentImage;

            $newImageUploaded = false;


            if (
                isset(
                    $_FILES['restaurant_image']
                ) &&
                $_FILES['restaurant_image']['error']
                !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $_FILES['restaurant_image']['error']
                    !== UPLOAD_ERR_OK
                ) {

                    $errorMessage =
                        'Restaurant image upload failed.';

                } elseif (
                    $_FILES['restaurant_image']['size']
                    > 5 * 1024 * 1024
                ) {

                    $errorMessage =
                        'Image size must be less than 5 MB.';

                } else {

                    $extension =
                        strtolower(
                            pathinfo(
                                $_FILES['restaurant_image']['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                    $allowed =
                        [
                            'jpg',
                            'jpeg',
                            'png',
                            'webp'
                        ];


                    if (
                        !in_array(
                            $extension,
                            $allowed,
                            true
                        )
                    ) {

                        $errorMessage =
                            'Only JPG, JPEG, PNG and WEBP images are allowed.';

                    } else {

                        $imageInfo =
                            @getimagesize(
                                $_FILES['restaurant_image']['tmp_name']
                            );

                        if ($imageInfo === false) {

                            $errorMessage =
                                'Selected file is not a valid image.';

                        } else {

                            $imageName =
                                'restaurant_' .
                                $ownerId .
                                '_' .
                                date('YmdHis') .
                                '_' .
                                mt_rand(
                                    1000,
                                    999999
                                ) .
                                '.' .
                                $extension;


                            $destination =
                                $imageDirectory .
                                $imageName;


                            if (
                                move_uploaded_file(
                                    $_FILES['restaurant_image']['tmp_name'],
                                    $destination
                                )
                            ) {

                                $newImageUploaded =
                                    true;

                            } else {

                                $errorMessage =
                                    'Unable to save restaurant image.';
                            }
                        }
                    }
                }
            }


            /* -----------------------------------------
               DATABASE
            ----------------------------------------- */

            if ($errorMessage === '') {

                /*
                |--------------------------------------------------------------------------
                | CREATE
                |--------------------------------------------------------------------------
                */

                if (!$restaurant) {

                    if ($hasOwnerId) {

                        $stmt = $conn->prepare("
                            INSERT INTO restaurants
                            (
                                owner_id,
                                name,
                                description,
                                image,
                                address,
                                phone,
                                rating,
                                delivery_time,
                                delivery_fee,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                0,
                                '',
                                0,
                                1
                            )
                        ");

                        if (!$stmt) {

                            $errorMessage =
                                'Database error: ' .
                                $conn->error;

                        } else {

                            $stmt->bind_param(
                                "isssss",
                                $ownerId,
                                $newName,
                                $newDescription,
                                $imageName,
                                $newAddress,
                                $newPhone
                            );


                            if (
                                $stmt->execute()
                            ) {

                                $restaurantId =
                                    (int)$stmt->insert_id;

                                $successMessage =
                                    'Restaurant created successfully.';

                            } else {

                                $errorMessage =
                                    'Unable to create restaurant: ' .
                                    $stmt->error;
                            }

                            $stmt->close();
                        }

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Fallback for existing database
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $conn->prepare("
                            INSERT INTO restaurants
                            (
                                name,
                                description,
                                image,
                                address,
                                phone,
                                rating,
                                delivery_time,
                                delivery_fee,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                0,
                                '',
                                0,
                                1
                            )
                        ");

                        if (!$stmt) {

                            $errorMessage =
                                'Database error: ' .
                                $conn->error;

                        } else {

                            $stmt->bind_param(
                                "sssss",
                                $newName,
                                $newDescription,
                                $imageName,
                                $newAddress,
                                $newPhone
                            );


                            if (
                                $stmt->execute()
                            ) {

                                $restaurantId =
                                    (int)$stmt->insert_id;

                                $successMessage =
                                    'Restaurant created successfully.';

                            } else {

                                $errorMessage =
                                    'Unable to create restaurant: ' .
                                    $stmt->error;
                            }

                            $stmt->close();
                        }
                    }


                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                } else {

                    $stmt = $conn->prepare("
                        UPDATE restaurants
                        SET
                            name = ?,
                            description = ?,
                            image = ?,
                            address = ?,
                            phone = ?
                        WHERE id = ?
                        LIMIT 1
                    ");


                    if (!$stmt) {

                        $errorMessage =
                            'Database error: ' .
                            $conn->error;

                    } else {

                        $stmt->bind_param(
                            "sssssi",
                            $newName,
                            $newDescription,
                            $imageName,
                            $newAddress,
                            $newPhone,
                            $restaurantId
                        );


                        if (
                            $stmt->execute()
                        ) {

                            $successMessage =
                                'Restaurant information updated successfully.';

                        } else {

                            $errorMessage =
                                'Unable to update restaurant: ' .
                                $stmt->error;
                        }

                        $stmt->close();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Sync restaurant_users
                |--------------------------------------------------------------------------
                */

                if (
                    $errorMessage === ''
                ) {

                    $stmt =
                        $conn->prepare("
                            UPDATE restaurant_users
                            SET
                                restaurant_name = ?,
                                phone = ?
                            WHERE id = ?
                            LIMIT 1
                        ");

                    if ($stmt) {

                        $stmt->bind_param(
                            "ssi",
                            $newName,
                            $newPhone,
                            $ownerId
                        );

                        $stmt->execute();

                        $stmt->close();
                    }


                    $_SESSION[
                        'restaurant_name'
                    ] = $newName;


                    $_SESSION[
                        'restaurant_owner_restaurant_name'
                    ] = $newName;


                    /*
                    |--------------------------------------------------------------------------
                    | Delete old image
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $newImageUploaded &&
                        $currentImage !== '' &&
                        $currentImage !== $imageName
                    ) {

                        $oldImage =
                            $imageDirectory .
                            basename(
                                $currentImage
                            );

                        if (
                            is_file($oldImage)
                        ) {

                            @unlink(
                                $oldImage
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Refresh values
                    |--------------------------------------------------------------------------
                    */

                    $currentName =
                        $newName;

                    $currentDescription =
                        $newDescription;

                    $currentImage =
                        $imageName;

                    $currentAddress =
                        $newAddress;

                    $currentPhone =
                        $newPhone;
                }
            }
        }
    }
}


/* =========================================================
   CREATE DEAL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_deal'])
) {

    if (!$isApproved) {

        $errorMessage =
            'Your account must be approved first.';

    } elseif ($restaurantId <= 0) {

        $errorMessage =
            'Please create your restaurant first.';

    } else {

        $dealName =
            trim(
                $_POST['deal_name']
                ?? ''
            );

        $dealDescription =
            trim(
                $_POST['deal_description']
                ?? ''
            );

        $dealPrice =
            $_POST['deal_price']
            ?? '';


        if ($dealName === '') {

            $errorMessage =
                'Please enter deal name.';

        } elseif (
            !is_numeric($dealPrice)
        ) {

            $errorMessage =
                'Please enter a valid deal price.';

        } else {

            $dealImage = '';


            /*
            |--------------------------------------------------------------------------
            | Deal Image
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $_FILES['deal_image']
                ) &&
                $_FILES['deal_image']['error']
                !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $_FILES['deal_image']['error']
                    === UPLOAD_ERR_OK
                ) {

                    $extension =
                        strtolower(
                            pathinfo(
                                $_FILES['deal_image']['name'],
                                PATHINFO_EXTENSION
                            )
                        );

                    $allowed =
                        [
                            'jpg',
                            'jpeg',
                            'png',
                            'webp'
                        ];


                    if (
                        in_array(
                            $extension,
                            $allowed,
                            true
                        )
                    ) {

                        $dealImage =
                            'deal_' .
                            $ownerId .
                            '_' .
                            date('YmdHis') .
                            '_' .
                            mt_rand(
                                1000,
                                999999
                            ) .
                            '.' .
                            $extension;


                        move_uploaded_file(
                            $_FILES['deal_image']['tmp_name'],
                            $imageDirectory .
                            $dealImage
                        );
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Insert deal into menu_items
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO menu_items
                (
                    restaurant_id,
                    name,
                    description,
                    price,
                    image,
                    category,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Deal',
                    1
                )
            ");


            if (!$stmt) {

                $errorMessage =
                    'Database error: ' .
                    $conn->error;

            } else {

                $price =
                    (float)$dealPrice;

                $stmt->bind_param(
                    "issds",
                    $restaurantId,
                    $dealName,
                    $dealDescription,
                    $price,
                    $dealImage
                );


                if (
                    $stmt->execute()
                ) {

                    $successMessage =
                        'Deal created successfully.';

                } else {

                    $errorMessage =
                        'Unable to create deal: ' .
                        $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   RELOAD RESTAURANT
========================================================= */

if (
    $restaurantId > 0
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            image,
            address,
            phone,
            rating
        FROM restaurants
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $restaurantId
        );

        if ($stmt->execute()) {

            $result =
                $stmt->get_result();

            if ($result) {

                $restaurant =
                    $result->fetch_assoc();
            }
        }

        $stmt->close();
    }
}


if ($restaurant) {

    $currentName =
        $restaurant['name'] ?? '';

    $currentDescription =
        $restaurant['description'] ?? '';

    $currentImage =
        $restaurant['image'] ?? '';

    $currentAddress =
        $restaurant['address'] ?? '';

    $currentPhone =
        $restaurant['phone'] ?? '';
}


/* =========================================================
   AVATAR
========================================================= */

$initial =
    strtoupper(
        substr(
            $ownerName !== ''
                ? $ownerName
                : 'R',
            0,
            1
        )
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Manage Restaurant | Humsafar
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

body {
    margin: 0;
    background: #f5f6fa;
    color: #191919;
    font-family:
        "Segoe UI",
        Arial,
        sans-serif;
}

a {
    text-decoration: none;
}


/* =========================================================
   LAYOUT
========================================================= */

.app {
    min-height: 100vh;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 230px;
    background:
        linear-gradient(
            180deg,
            #ed0038,
            #f20b48
        );
    color: #fff;
    z-index: 100;
}


/* =========================================================
   BRAND
========================================================= */

.brand {
    height: 88px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 22px;
    border-bottom:
        1px solid rgba(255,255,255,.15);
}

.brand-icon {
    width: 41px;
    height: 41px;
    border-radius: 11px;
    background: #fff;
    color: #ed0038;
    display: flex;
    align-items: center;
    justify-content: center;
}

.brand strong {
    font-size: 20px;
}

.brand small {
    display: block;
    margin-top: 4px;
    font-size: 8px;
    letter-spacing: 1.2px;
    opacity: .7;
}


/* =========================================================
   NAV
========================================================= */

.nav-title {
    padding:
        25px 22px 10px;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,.55);
}

.nav {
    padding: 0 11px;
}

.nav a {
    height: 44px;
    margin-bottom: 5px;
    padding: 0 14px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    gap: 13px;
    color: rgba(255,255,255,.88);
    font-size: 12px;
    font-weight: 700;
}

.nav a:hover {
    background:
        rgba(255,255,255,.11);
}

.nav a.active {
    background: #fff;
    color: #ed0038;
}

.nav a i:first-child {
    width: 17px;
    text-align: center;
}

.nav .lock {
    margin-left: auto;
    font-size: 9px;
}


/* =========================================================
   SIDEBAR OWNER
========================================================= */

.sidebar-owner {
    position: absolute;
    left: 12px;
    right: 12px;
    bottom: 15px;
    padding: 9px;
    border-radius: 11px;
    background:
        rgba(0,0,0,.12);
    display: flex;
    align-items: center;
    gap: 8px;
}

.avatar {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #ffc800;
    color: #222;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 12px;
}

.sidebar-owner-info {
    min-width: 0;
    flex: 1;
}

.sidebar-owner-info strong {
    display: block;
    font-size: 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-owner-info span {
    display: block;
    margin-top: 3px;
    font-size: 8px;
    color: rgba(255,255,255,.6);
}


/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 230px;
    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    height: 65px;
    padding: 0 28px;
    background: #fff;
    border-bottom: 1px solid #e8e8eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar small {
    display: block;
    font-size: 7px;
    color: #a0a2a9;
    letter-spacing: 1.5px;
    font-weight: 900;
}

.topbar strong {
    display: block;
    margin-top: 4px;
    font-size: 15px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 13px;
}

.bell {
    width: 35px;
    height: 35px;
    border: 1px solid #e4e5e8;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #555;
    font-size: 12px;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-user .avatar {
    width: 31px;
    height: 31px;
    font-size: 10px;
}

.top-user-text strong {
    font-size: 10px;
}

.top-user-text span {
    display: block;
    margin-top: 2px;
    color: #999;
    font-size: 7px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 32px 28px 55px;
    max-width: 1400px;
}

.heading {
    margin-bottom: 22px;
}

.heading .eyebrow {
    color: #ed0038;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 1.5px;
}

.heading h1 {
    margin: 7px 0 5px;
    font-size: 28px;
}

.heading p {
    margin: 0;
    color: #888b93;
    font-size: 12px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}

.alert.success {
    background: #eaf8ef;
    border: 1px solid #c9ead4;
    color: #17743d;
}

.alert.error {
    background: #fff0f3;
    border: 1px solid #ffd0d9;
    color: #b4233c;
}


/* =========================================================
   LOCK
========================================================= */

.lock-card {
    padding: 55px 25px;
    background: #fff;
    border: 1px solid #e8e9ed;
    border-radius: 18px;
    text-align: center;
}

.lock-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #fff0f4;
    color: #ed0038;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}

.lock-card h2 {
    margin: 0 0 8px;
    font-size: 21px;
}

.lock-card p {
    max-width: 570px;
    margin: auto;
    color: #858890;
    font-size: 12px;
    line-height: 1.7;
}

.pending {
    display: inline-flex;
    margin-top: 18px;
    padding: 8px 14px;
    border-radius: 20px;
    background: #fff6df;
    color: #966300;
    font-size: 9px;
    font-weight: 900;
}


/* =========================================================
   RESTAURANT PREVIEW
========================================================= */

.preview {
    position: relative;
    height: 190px;
    margin-bottom: 22px;
    overflow: hidden;
    border-radius: 18px;
    background:
        linear-gradient(
            135deg,
            #ed0038,
            #fa578b
        );
}

.preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preview-overlay {
    position: absolute;
    inset: 0;
    padding: 28px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    color: #fff;
    background:
        linear-gradient(
            90deg,
            rgba(0,0,0,.62),
            rgba(0,0,0,.08)
        );
}

.preview-badge {
    width: fit-content;
    padding: 6px 10px;
    border-radius: 20px;
    background: rgba(255,255,255,.17);
    font-size: 8px;
    font-weight: 900;
}

.preview h2 {
    margin: 10px 0 5px;
    font-size: 25px;
}

.preview p {
    margin: 0;
    max-width: 600px;
    font-size: 10px;
    opacity: .9;
}


/* =========================================================
   CARD
========================================================= */

.card {
    background: #fff;
    border: 1px solid #e7e8ec;
    border-radius: 18px;
    overflow: hidden;
    margin-bottom: 22px;
}

.card-header {
    padding: 20px 22px;
    border-bottom: 1px solid #eeeeef;
}

.card-header h2 {
    margin: 0 0 4px;
    font-size: 17px;
}

.card-header p {
    margin: 0;
    color: #91949c;
    font-size: 10px;
}

.card-body {
    padding: 23px 22px;
}


/* =========================================================
   FORM
========================================================= */

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.full {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 800;
    color: #44464d;
}

.input,
.textarea,
.file {
    width: 100%;
    border: 1px solid #dedfe4;
    border-radius: 9px;
    outline: none;
    background: #fff;
    font-family: inherit;
    font-size: 12px;
}

.input {
    height: 44px;
    padding: 0 13px;
}

.textarea {
    min-height: 105px;
    padding: 12px 13px;
    resize: vertical;
}

.file {
    padding: 11px;
}

.input:focus,
.textarea:focus,
.file:focus {
    border-color: #ed0038;
    box-shadow:
        0 0 0 3px rgba(237,0,56,.07);
}


/* =========================================================
   IMAGE
========================================================= */

.image-area {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 17px;
    align-items: center;
}

.image-preview {
    width: 150px;
    height: 110px;
    overflow: hidden;
    border-radius: 11px;
    background: #fff1f5;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ed0038;
    font-size: 29px;
}

.image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.help {
    margin-top: 7px;
    color: #989ba3;
    font-size: 9px;
    line-height: 1.5;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {
    margin-top: 23px;
    padding-top: 20px;
    border-top: 1px solid #eeeeef;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    min-height: 42px;
    padding: 0 20px;
    border: 0;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    font-family: inherit;
    font-size: 11px;
    font-weight: 900;
}

.btn-light {
    background: #f3f4f6;
    color: #555;
}

.btn-primary {
    color: #fff;
    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f34c82
        );
    box-shadow:
        0 7px 18px rgba(237,0,56,.18);
}


/* =========================================================
   INFORMATION CARDS
========================================================= */

.info-grid {
    display: grid;
    grid-template-columns:
        repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 22px;
}

.info {
    padding: 17px;
    border: 1px solid #e7e8ec;
    background: #fff;
    border-radius: 13px;
}

.info i {
    color: #ed0038;
    font-size: 15px;
}

.info strong {
    display: block;
    margin-top: 8px;
    font-size: 11px;
}

.info span {
    display: block;
    margin-top: 5px;
    color: #92959d;
    font-size: 9px;
    line-height: 1.5;
}


/* =========================================================
   DEAL
========================================================= */

.deal-grid {
    display: grid;
    grid-template-columns:
        1.5fr 1fr 1fr;
    gap: 15px;
}

.deal-full {
    grid-column: 1 / -1;
}

.deal-note {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 9px;
    background: #fff8e9;
    color: #8c6200;
    font-size: 9px;
    line-height: 1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {
        width: 70px;
    }

    .brand {
        justify-content: center;
        padding: 0;
    }

    .brand-text,
    .nav-title,
    .nav a span,
    .sidebar-owner-info {
        display: none;
    }

    .nav a {
        justify-content: center;
        padding: 0;
    }

    .main {
        margin-left: 70px;
    }

    .deal-grid {
        grid-template-columns: 1fr 1fr;
    }
}


@media (max-width: 700px) {

    .content {
        padding: 24px 14px 40px;
    }

    .form-grid,
    .deal-grid,
    .info-grid {
        grid-template-columns: 1fr;
    }

    .full,
    .deal-full {
        grid-column: auto;
    }

    .image-area {
        grid-template-columns: 1fr;
    }

    .image-preview {
        width: 100%;
        height: 170px;
    }

    .actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .top-user-text {
        display: none;
    }
}

</style>

</head>


<body>


<div class="app">


<?php include __DIR__ . '/restaurant-sidebar.php'; ?>

<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <header class="topbar">

        <div>

            <small>
                RESTAURANT PARTNER PORTAL
            </small>

            <strong>
                Manage Restaurant
            </strong>

        </div>


        <div class="topbar-right">

            <div class="bell">
                <i class="far fa-bell"></i>
            </div>


            <div class="top-user">

                <div class="avatar">
                    <?php
                    echo e($initial);
                    ?>
                </div>

                <div class="top-user-text">

                    <strong>
                        <?php
                        echo e($ownerName);
                        ?>
                    </strong>

                    <span>
                        Restaurant Owner
                    </span>

                </div>

            </div>

        </div>

    </header>


    <section class="content">


        <!-- =================================================
             HEADING
        ================================================== -->

        <div class="heading">

            <div class="eyebrow">
                RESTAURANT MANAGEMENT
            </div>

            <h1>
                Manage Restaurant
            </h1>

            <p>
                Manage your restaurant profile and promotional deals.
            </p>

        </div>


        <!-- =================================================
             ALERT
        ================================================== -->

        <?php if ($successMessage !== '') { ?>

            <div class="alert success">

                <i class="fas fa-circle-check"></i>

                <?php
                echo e(
                    $successMessage
                );
                ?>

            </div>

        <?php } ?>


        <?php if ($errorMessage !== '') { ?>

            <div class="alert error">

                <i class="fas fa-circle-exclamation"></i>

                <?php
                echo e(
                    $errorMessage
                );
                ?>

            </div>

        <?php } ?>


        <!-- =================================================
             PENDING
        ================================================== -->

        <?php if (!$isApproved) { ?>


            <div class="lock-card">

                <div class="lock-icon">

                    <i class="fas fa-lock"></i>

                </div>


                <h2>
                    Restaurant Management Locked
                </h2>


                <p>

                    Your restaurant owner account is currently
                    waiting for admin approval. After approval,
                    you will be able to create and manage your
                    restaurant profile and deals.

                </p>


                <div class="pending">

                    <i class="fas fa-clock"></i>

                    &nbsp;

                    <?php
                    echo e(
                        strtoupper(
                            $ownerStatus
                        )
                    );
                    ?>

                </div>

            </div>


        <?php } else { ?>


            <!-- =================================================
                 PREVIEW
            ================================================== -->

            <?php if ($restaurant) { ?>

                <div class="preview">

                    <?php if ($currentImage !== '') { ?>

                        <img
                            src="<?php
                            echo e(
                                $imageUrl .
                                $currentImage
                            );
                            ?>"
                            alt="Restaurant"
                        >

                    <?php } ?>


                    <div class="preview-overlay">

                        <div class="preview-badge">

                            <i class="fas fa-store"></i>

                            &nbsp;

                            YOUR RESTAURANT

                        </div>


                        <h2>

                            <?php
                            echo e(
                                $currentName
                            );
                            ?>

                        </h2>


                        <p>

                            <?php
                            echo e(
                                $currentDescription !== ''
                                    ? $currentDescription
                                    : 'Your restaurant profile on Humsafar.'
                            );
                            ?>

                        </p>

                    </div>

                </div>

            <?php } ?>


            <!-- =================================================
                 RESTAURANT FORM
            ================================================== -->

            <div class="card">


                <div class="card-header">

                    <h2>

                        <?php
                        echo $restaurant
                            ? 'Restaurant Information'
                            : 'Create Your Restaurant';
                        ?>

                    </h2>


                    <p>

                        <?php
                        echo $restaurant
                            ? 'Update the information customers see on your restaurant profile.'
                            : 'Create your restaurant profile on Humsafar.';
                        ?>

                    </p>

                </div>


                <div class="card-body">


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <div class="form-grid">


                            <!-- NAME -->

                            <div class="form-group">

                                <label>
                                    Restaurant Name *
                                </label>

                                <input
                                    type="text"
                                    name="restaurant_name"
                                    class="input"
                                    value="<?php
                                    echo e(
                                        $currentName
                                    );
                                    ?>"
                                    placeholder="Enter restaurant name"
                                    required
                                >

                                <div class="help">
                                    This name will be shown to customers.
                                </div>

                            </div>


                            <!-- PHONE -->

                            <div class="form-group">

                                <label>
                                    Phone Number *
                                </label>

                                <input
                                    type="text"
                                    name="phone"
                                    class="input"
                                    value="<?php
                                    echo e(
                                        $currentPhone
                                    );
                                    ?>"
                                    placeholder="03XXXXXXXXX"
                                    required
                                >

                            </div>


                            <!-- ADDRESS -->

                            <div class="form-group full">

                                <label>
                                    Restaurant Address *
                                </label>

                                <input
                                    type="text"
                                    name="address"
                                    class="input"
                                    value="<?php
                                    echo e(
                                        $currentAddress
                                    );
                                    ?>"
                                    placeholder="Enter complete restaurant address"
                                    required
                                >

                            </div>


                            <!-- DESCRIPTION -->

                            <div class="form-group full">

                                <label>
                                    Restaurant Description
                                </label>

                                <textarea
                                    name="description"
                                    class="textarea"
                                    placeholder="Tell customers about your restaurant..."
                                ><?php
                                echo e(
                                    $currentDescription
                                );
                                ?></textarea>

                            </div>


                            <!-- IMAGE -->

                            <div class="form-group full">

                                <label>
                                    Restaurant Image
                                </label>


                                <div class="image-area">


                                    <div class="image-preview">

                                        <?php if (
                                            $currentImage !== ''
                                        ) { ?>

                                            <img
                                                src="<?php
                                                echo e(
                                                    $imageUrl .
                                                    $currentImage
                                                );
                                                ?>"
                                                alt="Restaurant Image"
                                            >

                                        <?php } else { ?>

                                            <i
                                                class="fas fa-store"
                                            ></i>

                                        <?php } ?>

                                    </div>


                                    <div>

                                        <input
                                            type="file"
                                            name="restaurant_image"
                                            class="file"
                                            accept=".jpg,.jpeg,.png,.webp"
                                        >


                                        <div class="help">

                                            JPG, JPEG, PNG or WEBP.
                                            Maximum 5 MB.

                                        </div>


                                        <div class="help">

                                            This same image will be displayed
                                            to Humsafar customers.

                                        </div>

                                    </div>


                                </div>

                            </div>


                        </div>


                        <div class="actions">


                            <a
                                href="restaurant-owner-dashboard.php"
                                class="btn btn-light"
                            >

                                <i class="fas fa-arrow-left"></i>

                                Dashboard

                            </a>


                            <button
                                type="submit"
                                name="save_restaurant"
                                value="1"
                                class="btn btn-primary"
                            >

                                <i class="fas fa-save"></i>

                                <?php
                                echo $restaurant
                                    ? 'Save Changes'
                                    : 'Create Restaurant';
                                ?>

                            </button>


                        </div>


                    </form>

                </div>

            </div>


            <!-- =================================================
                 OWNER PERMISSIONS
            ================================================== -->

            <div class="info-grid">


                <div class="info">

                    <i class="fas fa-store"></i>

                    <strong>
                        Restaurant Profile
                    </strong>

                    <span>
                        Manage your restaurant name,
                        image, description and contact details.
                    </span>

                </div>


                <div class="info">

                    <i class="fas fa-tags"></i>

                    <strong>
                        Deals
                    </strong>

                    <span>
                        Create promotional deals for your
                        customers.
                    </span>

                </div>


                <div class="info">

                    <i class="fas fa-shield-halved"></i>

                    <strong>
                        Admin Controlled
                    </strong>

                    <span>
                        Delivery fee, restaurant timing and
                        availability are controlled by Humsafar Admin.
                    </span>

                </div>


            </div>


            <!-- =================================================
                 DEAL FORM
            ================================================== -->

            <?php if ($restaurant) { ?>


                <div class="card">


                    <div class="card-header">

                        <h2>
                            Create Promotional Deal
                        </h2>

                        <p>
                            Add a special deal to your restaurant.
                        </p>

                    </div>


                    <div class="card-body">


                        <form
                            method="POST"
                            enctype="multipart/form-data"
                        >


                            <div class="deal-grid">


                                <div class="form-group">

                                    <label>
                                        Deal Name *
                                    </label>

                                    <input
                                        type="text"
                                        name="deal_name"
                                        class="input"
                                        placeholder="Family Deal"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>
                                        Deal Price (Rs.) *
                                    </label>

                                    <input
                                        type="number"
                                        name="deal_price"
                                        class="input"
                                        min="1"
                                        step="1"
                                        placeholder="999"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>
                                        Deal Image
                                    </label>

                                    <input
                                        type="file"
                                        name="deal_image"
                                        class="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >

                                </div>


                                <div class="form-group deal-full">

                                    <label>
                                        Deal Description
                                    </label>

                                    <textarea
                                        name="deal_description"
                                        class="textarea"
                                        placeholder="What is included in this deal?"
                                    ></textarea>

                                </div>


                            </div>


                            <div class="deal-note">

                                <i
                                    class="fas fa-circle-info"
                                ></i>

                                Your deal will be saved under the
                                <strong>Deal</strong> category and can
                                appear on the Humsafar Deals section.

                            </div>


                            <div class="actions">

                                <button
                                    type="submit"
                                    name="create_deal"
                                    value="1"
                                    class="btn btn-primary"
                                >

                                    <i class="fas fa-tag"></i>

                                    Create Deal

                                </button>

                            </div>


                        </form>

                    </div>

                </div>


            <?php } ?>


        <?php } ?>


    </section>


</main>

</div>


<script>

/* =========================================================
   RESTAURANT IMAGE PREVIEW
========================================================= */

const imageInput =
    document.querySelector(
        'input[name="restaurant_image"]'
    );

const imagePreview =
    document.querySelector(
        '.image-preview'
    );


if (
    imageInput &&
    imagePreview
) {

    imageInput.addEventListener(
        'change',
        function () {

            const file =
                this.files &&
                this.files[0];

            if (!file) {
                return;
            }

            if (
                !file.type.startsWith(
                    'image/'
                )
            ) {
                return;
            }

            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    imagePreview.innerHTML =
                        '<img src="' +
                        event.target.result +
                        '" alt="Preview">';

                };


            reader.readAsDataURL(file);

        }
    );
}

</script>


</body>

</html>