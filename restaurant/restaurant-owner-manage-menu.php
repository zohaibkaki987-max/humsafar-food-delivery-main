<?php

/*
|--------------------------------------------------------------------------
| Humsafar Food Delivery
| Restaurant Owner - Menu Management
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
   OWNER
========================================================= */

$owner = null;
$ownerId = 0;


/*
|--------------------------------------------------------------------------
| Find owner by session ID
|--------------------------------------------------------------------------
*/

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
| Fallback by email
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
                    $owner = $result->fetch_assoc();
                }
            }

            $stmt->close();
        }

        if ($owner) {
            $ownerId = (int)$owner['id'];
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
   OWNER DATA
========================================================= */

$ownerName =
    trim(
        $owner['full_name'] ?? ''
    );

$ownerEmail =
    trim(
        $owner['email'] ?? ''
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
   IMAGE DIRECTORY
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
   CHECK RESTAURANTS OWNER_ID COLUMN
========================================================= */

$hasOwnerId = false;

$columnResult = $conn->query(
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
    | First: owner_id
    |--------------------------------------------------------------------------
    */

    if ($hasOwnerId) {

        $stmt = $conn->prepare("
            SELECT *
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

                $result = $stmt->get_result();

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
    | Fallback by restaurant name
    |--------------------------------------------------------------------------
    */

    if (
        !$restaurant &&
        $ownerRestaurantName !== ''
    ) {

        $stmt = $conn->prepare("
            SELECT *
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

                $result = $stmt->get_result();

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
   ADMIN PERCENTAGE
========================================================= */

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| We look for admin percentage in restaurants table.
|
| Recommended column:
|
| admin_percentage
|
|--------------------------------------------------------------------------
*/

$adminPercentage = 0;

$hasAdminPercentage = false;

$percentageColumn =
    $conn->query(
        "SHOW COLUMNS FROM restaurants LIKE 'admin_percentage'"
    );

if (
    $percentageColumn &&
    $percentageColumn->num_rows > 0
) {

    $hasAdminPercentage = true;
}


if (
    $restaurant &&
    $hasAdminPercentage
) {

    $adminPercentage =
        (float)(
            $restaurant['admin_percentage']
            ?? 0
        );
}


/* =========================================================
   RESTAURANT REQUIRED
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    if (!$isApproved) {

        $errorMessage =
            'Your account must be approved before managing menu items.';

    } elseif (!$restaurant) {

        $errorMessage =
            'Restaurant record not found. Please create your restaurant first.';
    }
}


/* =========================================================
   CREATE MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_item']) &&
    $isApproved &&
    $restaurant
) {

    $itemName =
        trim(
            $_POST['item_name']
            ?? ''
        );

    $description =
        trim(
            $_POST['description']
            ?? ''
        );

    $category =
        trim(
            $_POST['category']
            ?? ''
        );

    $ownerPrice =
        $_POST['owner_price']
        ?? '';

    $available =
        isset($_POST['available'])
            ? 1
            : 0;


    /* -----------------------------------------
       VALIDATION
    ----------------------------------------- */

    if ($itemName === '') {

        $errorMessage =
            'Please enter item name.';

    } elseif ($category === '') {

        $errorMessage =
            'Please select a category.';

    } elseif (
        $ownerPrice === '' ||
        !is_numeric($ownerPrice) ||
        (float)$ownerPrice <= 0
    ) {

        $errorMessage =
            'Please enter a valid owner price.';

    } else {

        $ownerPrice =
            (float)$ownerPrice;


        /* -----------------------------------------
           IMAGE
        ----------------------------------------- */

        $itemImage = '';

        if (
            isset($_FILES['item_image']) &&
            $_FILES['item_image']['error']
            !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES['item_image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $errorMessage =
                    'Item image upload failed.';

            } elseif (
                $_FILES['item_image']['size']
                > 5 * 1024 * 1024
            ) {

                $errorMessage =
                    'Item image must be less than 5 MB.';

            } else {

                $extension =
                    strtolower(
                        pathinfo(
                            $_FILES['item_image']['name'],
                            PATHINFO_EXTENSION
                        )
                    );

                $allowed = [
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
                            $_FILES['item_image']['tmp_name']
                        );

                    if ($imageInfo === false) {

                        $errorMessage =
                            'Selected file is not a valid image.';

                    } else {

                        $itemImage =
                            'item_' .
                            $restaurantId .
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
                            $itemImage;


                        if (
                            !move_uploaded_file(
                                $_FILES['item_image']['tmp_name'],
                                $destination
                            )
                        ) {

                            $errorMessage =
                                'Unable to save item image.';
                        }
                    }
                }
            }
        }


        /* -----------------------------------------
           INSERT
        ----------------------------------------- */

        if ($errorMessage === '') {

            /*
            |--------------------------------------------------------------------------
            | Check menu_items columns
            |--------------------------------------------------------------------------
            */

            $menuColumns = [];

            $columnsResult =
                $conn->query(
                    "SHOW COLUMNS FROM menu_items"
                );

            if ($columnsResult) {

                while (
                    $column =
                    $columnsResult->fetch_assoc()
                ) {

                    $menuColumns[] =
                        $column['Field'];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Normal schema
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    'restaurant_id',
                    $menuColumns,
                    true
                ) &&
                in_array(
                    'name',
                    $menuColumns,
                    true
                ) &&
                in_array(
                    'price',
                    $menuColumns,
                    true
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | Insert basic fields first
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
                        ?,
                        ?
                    )
                ");


                if (!$stmt) {

                    $errorMessage =
                        'Database error: ' .
                        $conn->error;

                } else {

                    $stmt->bind_param(
                        "issdssi",
                        $restaurantId,
                        $itemName,
                        $description,
                        $ownerPrice,
                        $itemImage,
                        $category,
                        $available
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $successMessage =
                            'Menu item added successfully.';

                    } else {

                        $errorMessage =
                            'Unable to add menu item: ' .
                            $stmt->error;
                    }

                    $stmt->close();
                }

            } else {

                $errorMessage =
                    'Your menu_items table does not have the required columns.';
            }
        }
    }
}


/* =========================================================
   EDIT MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['edit_item']) &&
    $isApproved &&
    $restaurant
) {

    $itemId =
        (int)(
            $_POST['item_id']
            ?? 0
        );

    $itemName =
        trim(
            $_POST['item_name']
            ?? ''
        );

    $description =
        trim(
            $_POST['description']
            ?? ''
        );

    $category =
        trim(
            $_POST['category']
            ?? ''
        );

    $ownerPrice =
        $_POST['owner_price']
        ?? '';

    $available =
        isset($_POST['available'])
            ? 1
            : 0;


    if ($itemId <= 0) {

        $errorMessage =
            'Invalid menu item.';

    } elseif ($itemName === '') {

        $errorMessage =
            'Please enter item name.';

    } elseif ($category === '') {

        $errorMessage =
            'Please select category.';

    } elseif (
        !is_numeric($ownerPrice) ||
        (float)$ownerPrice <= 0
    ) {

        $errorMessage =
            'Please enter a valid owner price.';

    } else {

        $ownerPrice =
            (float)$ownerPrice;


        /*
        |--------------------------------------------------------------------------
        | Get old image
        |--------------------------------------------------------------------------
        */

        $oldImage = '';

        $stmt =
            $conn->prepare("
                SELECT image
                FROM menu_items
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $itemId,
                $restaurantId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result) {

                    $oldItem =
                        $result->fetch_assoc();

                    if ($oldItem) {

                        $oldImage =
                            $oldItem['image']
                            ?? '';
                    }
                }
            }

            $stmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | New image
        |--------------------------------------------------------------------------
        */

        $itemImage =
            $oldImage;

        $newImageUploaded =
            false;


        if (
            isset($_FILES['item_image']) &&
            $_FILES['item_image']['error']
            !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES['item_image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $errorMessage =
                    'Item image upload failed.';

            } elseif (
                $_FILES['item_image']['size']
                > 5 * 1024 * 1024
            ) {

                $errorMessage =
                    'Item image must be less than 5 MB.';

            } else {

                $extension =
                    strtolower(
                        pathinfo(
                            $_FILES['item_image']['name'],
                            PATHINFO_EXTENSION
                        )
                    );

                $allowed = [
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
                        'Invalid image format.';

                } else {

                    $imageInfo =
                        @getimagesize(
                            $_FILES['item_image']['tmp_name']
                        );

                    if ($imageInfo === false) {

                        $errorMessage =
                            'Selected file is not a valid image.';

                    } else {

                        $itemImage =
                            'item_' .
                            $restaurantId .
                            '_' .
                            date('YmdHis') .
                            '_' .
                            mt_rand(
                                1000,
                                999999
                            ) .
                            '.' .
                            $extension;


                        if (
                            move_uploaded_file(
                                $_FILES['item_image']['tmp_name'],
                                $imageDirectory .
                                $itemImage
                            )
                        ) {

                            $newImageUploaded =
                                true;

                        } else {

                            $errorMessage =
                                'Unable to save new image.';
                        }
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if ($errorMessage === '') {

            $stmt = $conn->prepare("
                UPDATE menu_items
                SET
                    name = ?,
                    description = ?,
                    price = ?,
                    image = ?,
                    category = ?,
                    status = ?
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");


            if (!$stmt) {

                $errorMessage =
                    'Database error: ' .
                    $conn->error;

            } else {

                $stmt->bind_param(
                    "ssdssiii",
                    $itemName,
                    $description,
                    $ownerPrice,
                    $itemImage,
                    $category,
                    $available,
                    $itemId,
                    $restaurantId
                );


                if (
                    $stmt->execute()
                ) {

                    $successMessage =
                        'Menu item updated successfully.';


                    /*
                    |--------------------------------------------------------------------------
                    | Remove old image
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $newImageUploaded &&
                        $oldImage !== '' &&
                        $oldImage !== $itemImage
                    ) {

                        $oldImagePath =
                            $imageDirectory .
                            basename(
                                $oldImage
                            );

                        if (
                            is_file(
                                $oldImagePath
                            )
                        ) {

                            @unlink(
                                $oldImagePath
                            );
                        }
                    }

                } else {

                    $errorMessage =
                        'Unable to update menu item: ' .
                        $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}


/* =========================================================
   DELETE MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_item']) &&
    $isApproved &&
    $restaurant
) {

    $itemId =
        (int)(
            $_POST['item_id']
            ?? 0
        );


    if ($itemId > 0) {

        /*
        |--------------------------------------------------------------------------
        | Get image
        |--------------------------------------------------------------------------
        */

        $oldImage = '';

        $stmt =
            $conn->prepare("
                SELECT image
                FROM menu_items
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $itemId,
                $restaurantId
            );

            if ($stmt->execute()) {

                $result =
                    $stmt->get_result();

                if ($result) {

                    $row =
                        $result->fetch_assoc();

                    if ($row) {
                        $oldImage =
                            $row['image']
                            ?? '';
                    }
                }
            }

            $stmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare("
                DELETE FROM menu_items
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $itemId,
                $restaurantId
            );

            if (
                $stmt->execute()
            ) {

                $successMessage =
                    'Menu item deleted successfully.';


                if (
                    $oldImage !== ''
                ) {

                    $oldImagePath =
                        $imageDirectory .
                        basename(
                            $oldImage
                        );

                    if (
                        is_file(
                            $oldImagePath
                        )
                    ) {

                        @unlink(
                            $oldImagePath
                        );
                    }
                }

            } else {

                $errorMessage =
                    'Unable to delete menu item.';
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   TOGGLE AVAILABILITY
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_item']) &&
    $isApproved &&
    $restaurant
) {

    $itemId =
        (int)(
            $_POST['item_id']
            ?? 0
        );


    $newStatus =
        (int)(
            $_POST['new_status']
            ?? 0
        );

    $newStatus =
        $newStatus
            ? 1
            : 0;


    if ($itemId > 0) {

        $stmt =
            $conn->prepare("
                UPDATE menu_items
                SET status = ?
                WHERE id = ?
                AND restaurant_id = ?
                LIMIT 1
            ");

        if ($stmt) {

            $stmt->bind_param(
                "iii",
                $newStatus,
                $itemId,
                $restaurantId
            );

            if (
                $stmt->execute()
            ) {

                $successMessage =
                    $newStatus
                    ? 'Item is now available.'
                    : 'Item is now unavailable.';

            } else {

                $errorMessage =
                    'Unable to change item availability.';
            }

            $stmt->close();
        }
    }
}


/* =========================================================
   GET MENU ITEMS
========================================================= */

$menuItems = [];

if (
    $isApproved &&
    $restaurantId > 0
) {

    $stmt =
        $conn->prepare("
            SELECT
                id,
                name,
                description,
                price,
                image,
                category,
                status
            FROM menu_items
            WHERE restaurant_id = ?
            ORDER BY id DESC
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

                while (
                    $row =
                    $result->fetch_assoc()
                ) {

                    $menuItems[] =
                        $row;
                }
            }
        }

        $stmt->close();
    }
}


/* =========================================================
   CATEGORY LIST
========================================================= */

$categories = [
    'Fast Food',
    'Pizza',
    'Burgers',
    'BBQ',
    'Biryani',
    'Rice',
    'Chinese',
    'Desi',
    'Drinks',
    'Desserts',
    'Deals',
    'Other'
];


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
    Menu Management | Humsafar
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
   SIDEBAR
========================================================= */

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

.lock {
    margin-left: auto;
    font-size: 9px;
}

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
    max-width: 1450px;
}

.heading {
    margin-bottom: 22px;
}

.eyebrow {
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
   PRICE INFORMATION
========================================================= */

.price-info {
    display: grid;
    grid-template-columns:
        1fr 1fr 1fr;
    gap: 12px;
    margin-bottom: 22px;
}

.price-box {
    padding: 17px;
    background: #fff;
    border: 1px solid #e7e8ec;
    border-radius: 13px;
}

.price-box i {
    color: #ed0038;
    font-size: 15px;
}

.price-box small {
    display: block;
    margin-top: 8px;
    color: #92959d;
    font-size: 8px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.price-box strong {
    display: block;
    margin-top: 4px;
    font-size: 17px;
}

.price-box p {
    margin: 5px 0 0;
    color: #9a9ca4;
    font-size: 9px;
    line-height: 1.5;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
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
        repeat(2, minmax(0,1fr));
    gap: 17px;
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
.select,
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

.input,
.select {
    height: 44px;
    padding: 0 13px;
}

.textarea {
    min-height: 90px;
    padding: 12px 13px;
    resize: vertical;
}

.file {
    padding: 11px;
}

.input:focus,
.select:focus,
.textarea:focus,
.file:focus {
    border-color: #ed0038;
    box-shadow:
        0 0 0 3px rgba(237,0,56,.07);
}

.help {
    margin-top: 7px;
    color: #989ba3;
    font-size: 9px;
    line-height: 1.5;
}


/* =========================================================
   CHECKBOX
========================================================= */

.checkbox-row {
    display: flex;
    align-items: center;
    gap: 9px;
    padding-top: 28px;
}

.checkbox-row input {
    width: 17px;
    height: 17px;
    accent-color: #ed0038;
}

.checkbox-row label {
    margin: 0;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {
    margin-top: 22px;
    padding-top: 19px;
    border-top: 1px solid #eeeeef;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.btn {
    min-height: 42px;
    padding: 0 18px;
    border: 0;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    font-family: inherit;
    font-size: 10px;
    font-weight: 900;
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

.btn-light {
    background: #f3f4f6;
    color: #555;
}

.btn-danger {
    background: #fff0f2;
    color: #d42343;
}

.btn-small {
    min-height: 34px;
    padding: 0 12px;
    font-size: 9px;
}


/* =========================================================
   MENU HEADER
========================================================= */

.menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.item-count {
    padding: 7px 11px;
    border-radius: 20px;
    background: #fff0f4;
    color: #ed0038;
    font-size: 9px;
    font-weight: 900;
}


/* =========================================================
   MENU GRID
========================================================= */

.menu-grid {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0,1fr));
    gap: 17px;
}

.menu-item {
    overflow: hidden;
    border: 1px solid #e8e8eb;
    border-radius: 14px;
    background: #fff;
    transition: .2s ease;
}

.menu-item:hover {
    transform: translateY(-2px);
    box-shadow:
        0 10px 30px rgba(20,20,20,.07);
}

.item-image {
    position: relative;
    height: 170px;
    background: #fff0f4;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ed0038;
    font-size: 35px;
}

.availability {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 8px;
    font-weight: 900;
    background: #fff;
}

.available {
    color: #187641;
}

.unavailable {
    color: #a13b3b;
}

.item-body {
    padding: 15px;
}

.item-category {
    color: #ed0038;
    font-size: 8px;
    font-weight: 900;
    letter-spacing: .6px;
    text-transform: uppercase;
}

.item-name {
    margin: 6px 0 5px;
    font-size: 15px;
    font-weight: 900;
}

.item-description {
    height: 32px;
    overflow: hidden;
    color: #8d9098;
    font-size: 9px;
    line-height: 1.55;
}

.prices {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #eeeeef;
}

.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 7px;
}

.price-row span {
    color: #999ca4;
    font-size: 8px;
    font-weight: 700;
}

.price-row strong {
    font-size: 11px;
}

.owner-price {
    color: #555;
}

.customer-price {
    color: #ed0038;
    font-size: 16px !important;
}

.item-actions {
    margin-top: 13px;
    display: flex;
    gap: 7px;
}

.item-actions form {
    flex: 1;
}

.item-actions .btn {
    width: 100%;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    padding: 60px 20px;
    text-align: center;
}

.empty-icon {
    width: 70px;
    height: 70px;
    margin: auto;
    border-radius: 50%;
    background: #fff0f4;
    color: #ed0038;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.empty h3 {
    margin: 17px 0 7px;
    font-size: 17px;
}

.empty p {
    margin: 0;
    color: #92959d;
    font-size: 10px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {
    position: fixed;
    inset: 0;
    z-index: 500;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background:
        rgba(0,0,0,.55);
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 650px;
    max-height: 90vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 17px;
}

.modal-header {
    padding: 19px 22px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h2 {
    margin: 0;
    font-size: 17px;
}

.close {
    width: 33px;
    height: 33px;
    border: 0;
    border-radius: 8px;
    background: #f3f4f6;
    cursor: pointer;
}

.modal-body {
    padding: 22px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .menu-grid {
        grid-template-columns:
            repeat(2, minmax(0,1fr));
    }

    .price-info {
        grid-template-columns:
            1fr 1fr;
    }
}


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
}


@media (max-width: 700px) {

    .content {
        padding: 23px 14px 40px;
    }

    .form-grid,
    .menu-grid,
    .price-info {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }

    .card-header {
        align-items: flex-start;
        flex-direction: column;
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


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-icon">
            <i class="fas fa-utensils"></i>
        </div>

        <div class="brand-text">

            <strong>
                Humsafar
            </strong>

            <small>
                RESTAURANT PARTNER
            </small>

        </div>

    </div>


    <div class="nav-title">
        MAIN MENU
    </div>


    <nav class="nav">


        <a href="restaurant-owner-dashboard.php">

            <i class="fas fa-chart-line"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="restaurant-owner-manage.php">

            <i class="fas fa-store"></i>

            <span>
                Restaurant
            </span>

        </a>


        <a
            href="restaurant-owner-manage-menu.php"
            class="active"
        >

            <i class="fas fa-utensils"></i>

            <span>
                Menu Management
            </span>

        </a>


        <a href="restaurant-owner-manage-orders.php">

            <i class="fas fa-cart-shopping"></i>

            <span>
                Orders
            </span>

        </a>


        <a href="restaurant-owner-profile.php">

            <i class="fas fa-user"></i>

            <span>
                Profile
            </span>

        </a>


    </nav>


    <div class="nav-title">
        SUPPORT
    </div>


    <nav class="nav">

        <a href="#">

            <i class="far fa-circle-question"></i>

            <span>
                Support
            </span>

        </a>

    </nav>


    <div class="sidebar-owner">

        <div class="avatar">

            <?php
            echo e($initial);
            ?>

        </div>


        <div class="sidebar-owner-info">

            <strong>
                <?php
                echo e($ownerName);
                ?>
            </strong>

            <span>
                <?php
                echo e(
                    strtoupper(
                        $ownerStatus
                    )
                );
                ?>
            </span>

        </div>

    </div>


</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">


<header class="topbar">


    <div>

        <small>
            RESTAURANT PARTNER PORTAL
        </small>

        <strong>
            Menu Management
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
            MENU MANAGEMENT
        </div>

        <h1>
            Manage Your Menu
        </h1>

        <p>
            Add, edit and manage the items customers see on Humsafar.
        </p>

    </div>


    <!-- =================================================
         ALERTS
    ================================================== -->

    <?php if ($successMessage !== '') { ?>

        <div class="alert success">

            <i class="fas fa-circle-check"></i>

            &nbsp;

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

            &nbsp;

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
                Menu Management Locked
            </h2>


            <p>

                Your restaurant owner account is currently
                waiting for admin approval. Once your account
                is approved, you will be able to add and manage
                your restaurant menu.

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


    <?php } elseif (!$restaurant) { ?>


        <!-- =================================================
             RESTAURANT NOT CREATED
        ================================================== -->

        <div class="lock-card">

            <div class="lock-icon">

                <i class="fas fa-store"></i>

            </div>


            <h2>
                Create Your Restaurant First
            </h2>


            <p>

                Your owner account has been approved, but your
                restaurant profile has not been created yet.
                Create your restaurant before adding menu items.

            </p>


            <div style="margin-top:20px;">

                <a
                    href="restaurant-owner-manage.php"
                    class="btn btn-primary"
                >

                    <i class="fas fa-store"></i>

                    Manage Restaurant

                </a>

            </div>

        </div>


    <?php } else { ?>


        <!-- =================================================
             PRICE SYSTEM
        ================================================== -->

        <div class="price-info">


            <div class="price-box">

                <i class="fas fa-tag"></i>

                <small>
                    Your Price
                </small>

                <strong>
                    Owner Set Price
                </strong>

                <p>
                    You decide the base price of every menu item.
                </p>

            </div>


            <div class="price-box">

                <i class="fas fa-percent"></i>

                <small>
                    Admin Percentage
                </small>

                <strong>

                    <?php
                    echo number_format(
                        $adminPercentage,
                        2
                    );
                    ?>%

                </strong>

                <p>
                    Set by Humsafar Admin for your restaurant.
                </p>

            </div>


            <div class="price-box">

                <i class="fas fa-store"></i>

                <small>
                    Customer Price
                </small>

                <strong>
                    Automatically Calculated
                </strong>

                <p>
                    Customer sees your price + admin percentage.
                </p>

            </div>


        </div>


        <!-- =================================================
             ADD ITEM
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>
                        Add New Menu Item
                    </h2>

                    <p>
                        Add a food item that customers can order.
                    </p>

                </div>


                <span class="item-count">

                    <i class="fas fa-percent"></i>

                    Admin:
                    <?php
                    echo number_format(
                        $adminPercentage,
                        2
                    );
                    ?>%

                </span>

            </div>


            <div class="card-body">


                <form
                    method="POST"
                    enctype="multipart/form-data"
                >


                    <div class="form-grid">


                        <!-- ITEM NAME -->

                        <div class="form-group">

                            <label>
                                Item Name *
                            </label>

                            <input
                                type="text"
                                name="item_name"
                                class="input"
                                placeholder="Chicken Biryani"
                                required
                            >

                        </div>


                        <!-- CATEGORY -->

                        <div class="form-group">

                            <label>
                                Category *
                            </label>

                            <select
                                name="category"
                                class="select"
                                required
                            >

                                <option value="">
                                    Select Category
                                </option>

                                <?php foreach (
                                    $categories
                                    as $category
                                ) { ?>

                                    <option
                                        value="<?php
                                        echo e(
                                            $category
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            $category
                                        );
                                        ?>

                                    </option>

                                <?php } ?>

                            </select>

                        </div>


                        <!-- OWNER PRICE -->

                        <div class="form-group">

                            <label>
                                Your Price (Rs.) *
                            </label>

                            <input
                                type="number"
                                name="owner_price"
                                class="input"
                                min="1"
                                step="0.01"
                                placeholder="500"
                                required
                                id="newOwnerPrice"
                            >

                            <div class="help">

                                This is your base price.
                                Admin percentage will be added automatically.

                            </div>

                        </div>


                        <!-- LIVE CUSTOMER PRICE -->

                        <div class="form-group">

                            <label>
                                Customer Price
                            </label>

                            <input
                                type="text"
                                class="input"
                                id="newCustomerPrice"
                                value="Rs. 0"
                                readonly
                            >

                            <div class="help">

                                This is the estimated price customers will see.

                            </div>

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">

                            <label>
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="textarea"
                                placeholder="Describe this item..."
                            ></textarea>

                        </div>


                        <!-- IMAGE -->

                        <div class="form-group">

                            <label>
                                Item Image
                            </label>

                            <input
                                type="file"
                                name="item_image"
                                class="file"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <div class="help">
                                Maximum 5 MB.
                            </div>

                        </div>


                        <!-- AVAILABLE -->

                        <div class="checkbox-row">

                            <input
                                type="checkbox"
                                name="available"
                                id="available"
                                value="1"
                                checked
                            >

                            <label for="available">

                                Item is available

                            </label>

                        </div>


                    </div>


                    <div class="actions">

                        <button
                            type="submit"
                            name="add_item"
                            value="1"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-plus"></i>

                            Add Menu Item

                        </button>

                    </div>


                </form>

            </div>

        </div>


        <!-- =================================================
             MENU ITEMS
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>
                        Your Menu Items
                    </h2>

                    <p>
                        These items will appear on your restaurant
                        page for customers.
                    </p>

                </div>


                <span class="item-count">

                    <?php
                    echo count(
                        $menuItems
                    );
                    ?>

                    Items

                </span>

            </div>


            <div class="card-body">


                <?php if (
                    empty($menuItems)
                ) { ?>


                    <div class="empty">

                        <div class="empty-icon">

                            <i class="fas fa-utensils"></i>

                        </div>


                        <h3>
                            No Menu Items Yet
                        </h3>


                        <p>
                            Add your first menu item using the form above.
                        </p>

                    </div>


                <?php } else { ?>


                    <div class="menu-grid">


                        <?php foreach (
                            $menuItems
                            as $item
                        ) {


                            $itemId =
                                (int)(
                                    $item['id']
                                    ?? 0
                                );

                            $itemName =
                                $item['name']
                                ?? '';

                            $itemDescription =
                                $item['description']
                                ?? '';

                            $itemCategory =
                                $item['category']
                                ?? 'Other';

                            $itemImage =
                                $item['image']
                                ?? '';

                            $itemStatus =
                                (int)(
                                    $item['status']
                                    ?? 0
                                );

                            $ownerItemPrice =
                                (float)(
                                    $item['price']
                                    ?? 0
                                );


                            /*
                            |--------------------------------------------------------------------------
                            | CUSTOMER PRICE
                            |--------------------------------------------------------------------------
                            */

                            $customerPrice =
                                $ownerItemPrice +
                                (
                                    $ownerItemPrice *
                                    $adminPercentage /
                                    100
                                );

                        ?>


                            <div class="menu-item">


                                <div class="item-image">


                                    <?php if (
                                        $itemImage !== ''
                                    ) { ?>

                                        <img
                                            src="<?php
                                            echo e(
                                                $imageUrl .
                                                $itemImage
                                            );
                                            ?>"
                                            alt="<?php
                                            echo e(
                                                $itemName
                                            );
                                            ?>"
                                        >

                                    <?php } else { ?>

                                        <div class="no-image">

                                            <i
                                                class="fas fa-utensils"
                                            ></i>

                                        </div>

                                    <?php } ?>


                                    <span
                                        class="
                                            availability
                                            <?php
                                            echo $itemStatus
                                                ? 'available'
                                                : 'unavailable';
                                            ?>
                                        "
                                    >

                                        <i
                                            class="
                                                fas
                                                <?php
                                                echo $itemStatus
                                                    ? 'fa-circle-check'
                                                    : 'fa-circle-xmark';
                                                ?>
                                            "
                                        ></i>

                                        &nbsp;

                                        <?php
                                        echo $itemStatus
                                            ? 'Available'
                                            : 'Unavailable';
                                        ?>

                                    </span>


                                </div>


                                <div class="item-body">


                                    <div class="item-category">

                                        <?php
                                        echo e(
                                            $itemCategory
                                        );
                                        ?>

                                    </div>


                                    <div class="item-name">

                                        <?php
                                        echo e(
                                            $itemName
                                        );
                                        ?>

                                    </div>


                                    <div class="item-description">

                                        <?php
                                        echo e(
                                            $itemDescription
                                        );
                                        ?>

                                    </div>


                                    <div class="prices">


                                        <div class="price-row">

                                            <span>
                                                Your Price
                                            </span>

                                            <strong
                                                class="owner-price"
                                            >

                                                Rs.
                                                <?php
                                                echo number_format(
                                                    $ownerItemPrice,
                                                    2
                                                );
                                                ?>

                                            </strong>

                                        </div>


                                        <div class="price-row">

                                            <span>

                                                Customer Price
                                                (+<?php
                                                echo number_format(
                                                    $adminPercentage,
                                                    2
                                                );
                                                ?>%)

                                            </span>

                                            <strong
                                                class="customer-price"
                                            >

                                                Rs.
                                                <?php
                                                echo number_format(
                                                    $customerPrice,
                                                    2
                                                );
                                                ?>

                                            </strong>

                                        </div>


                                    </div>


                                    <!-- ACTIONS -->

                                    <div class="item-actions">


                                        <button
                                            type="button"
                                            class="btn btn-light btn-small"
                                            onclick="openEditModal(
                                                <?php
                                                echo (int)$itemId;
                                                ?>,
                                                <?php
                                                echo htmlspecialchars(
                                                    json_encode(
                                                        $itemName
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>,
                                                <?php
                                                echo htmlspecialchars(
                                                    json_encode(
                                                        $itemDescription
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>,
                                                <?php
                                                echo htmlspecialchars(
                                                    json_encode(
                                                        $itemCategory
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>,
                                                <?php
                                                echo $ownerItemPrice;
                                                ?>,
                                                <?php
                                                echo $itemStatus;
                                                ?>
                                            )"
                                        >

                                            <i
                                                class="fas fa-pen"
                                            ></i>

                                            Edit

                                        </button>


                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this item?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="item_id"
                                                value="<?php
                                                echo $itemId;
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_item"
                                                value="1"
                                                class="btn btn-danger btn-small"
                                            >

                                                <i
                                                    class="fas fa-trash"
                                                ></i>

                                                Delete

                                            </button>

                                        </form>


                                    </div>


                                    <form
                                        method="POST"
                                        style="margin-top:7px;"
                                    >

                                        <input
                                            type="hidden"
                                            name="item_id"
                                            value="<?php
                                            echo $itemId;
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="new_status"
                                            value="<?php
                                            echo $itemStatus
                                                ? 0
                                                : 1;
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="toggle_item"
                                            value="1"
                                            class="btn btn-light btn-small"
                                            style="width:100%;"
                                        >

                                            <i
                                                class="
                                                    fas
                                                    <?php
                                                    echo $itemStatus
                                                        ? 'fa-eye-slash'
                                                        : 'fa-eye';
                                                    ?>
                                                "
                                            ></i>

                                            <?php
                                            echo $itemStatus
                                                ? 'Mark Unavailable'
                                                : 'Mark Available';
                                            ?>

                                        </button>

                                    </form>


                                </div>


                            </div>


                        <?php } ?>


                    </div>


                <?php } ?>


            </div>


        </div>


    <?php } ?>


</section>


</main>


</body>


<!-- =====================================================
     EDIT MODAL
====================================================== -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h2>
                Edit Menu Item
            </h2>


            <button
                type="button"
                class="close"
                onclick="closeEditModal()"
            >

                <i class="fas fa-times"></i>

            </button>

        </div>


        <div class="modal-body">


            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <input
                    type="hidden"
                    name="item_id"
                    id="editItemId"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Item Name *
                        </label>

                        <input
                            type="text"
                            name="item_name"
                            id="editItemName"
                            class="input"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Category *
                        </label>

                        <select
                            name="category"
                            id="editCategory"
                            class="select"
                            required
                        >

                            <?php foreach (
                                $categories
                                as $category
                            ) { ?>

                                <option
                                    value="<?php
                                    echo e(
                                        $category
                                    );
                                    ?>"
                                >

                                    <?php
                                    echo e(
                                        $category
                                    );
                                    ?>

                                </option>

                            <?php } ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>
                            Your Price (Rs.) *
                        </label>

                        <input
                            type="number"
                            name="owner_price"
                            id="editOwnerPrice"
                            class="input"
                            min="1"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Customer Price
                        </label>

                        <input
                            type="text"
                            id="editCustomerPrice"
                            class="input"
                            readonly
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="editDescription"
                            class="textarea"
                        ></textarea>

                    </div>


                    <div class="form-group">

                        <label>
                            Replace Image
                        </label>

                        <input
                            type="file"
                            name="item_image"
                            class="file"
                            accept=".jpg,.jpeg,.png,.webp"
                        >

                    </div>


                    <div class="checkbox-row">

                        <input
                            type="checkbox"
                            name="available"
                            id="editAvailable"
                            value="1"
                        >

                        <label
                            for="editAvailable"
                        >
                            Item is available
                        </label>

                    </div>


                </div>


                <div class="actions">

                    <button
                        type="button"
                        class="btn btn-light"
                        onclick="closeEditModal()"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        name="edit_item"
                        value="1"
                        class="btn btn-primary"
                    >

                        <i class="fas fa-save"></i>

                        Save Changes

                    </button>

                </div>


            </form>

        </div>


    </div>


</div>


<script>

/* =========================================================
   ADMIN PERCENTAGE
========================================================= */

const adminPercentage =
    <?php
    echo json_encode(
        $adminPercentage
    );
    ?>;


/* =========================================================
   PRICE CALCULATOR
========================================================= */

function calculateCustomerPrice(
    ownerPrice
) {

    ownerPrice =
        parseFloat(
            ownerPrice
        ) || 0;

    const customerPrice =
        ownerPrice +
        (
            ownerPrice *
            adminPercentage /
            100
        );

    return customerPrice;
}


/* =========================================================
   NEW ITEM LIVE PRICE
========================================================= */

const newOwnerPrice =
    document.getElementById(
        'newOwnerPrice'
    );

const newCustomerPrice =
    document.getElementById(
        'newCustomerPrice'
    );


if (
    newOwnerPrice &&
    newCustomerPrice
) {

    newOwnerPrice.addEventListener(
        'input',
        function () {

            const price =
                calculateCustomerPrice(
                    this.value
                );

            newCustomerPrice.value =
                'Rs. ' +
                price.toFixed(2);

        }
    );
}


/* =========================================================
   EDIT MODAL
========================================================= */

const editModal =
    document.getElementById(
        'editModal'
    );


function openEditModal(
    id,
    name,
    description,
    category,
    price,
    status
) {

    document.getElementById(
        'editItemId'
    ).value = id;


    document.getElementById(
        'editItemName'
    ).value = name;


    document.getElementById(
        'editDescription'
    ).value = description;


    document.getElementById(
        'editCategory'
    ).value = category;


    document.getElementById(
        'editOwnerPrice'
    ).value = price;


    document.getElementById(
        'editAvailable'
    ).checked =
        parseInt(status) === 1;


    updateEditPrice();


    editModal.classList.add(
        'show'
    );
}


function closeEditModal()
{
    editModal.classList.remove(
        'show'
    );
}


/* =========================================================
   EDIT PRICE
========================================================= */

const editOwnerPrice =
    document.getElementById(
        'editOwnerPrice'
    );

const editCustomerPrice =
    document.getElementById(
        'editCustomerPrice'
    );


function updateEditPrice()
{

    if (
        !editOwnerPrice ||
        !editCustomerPrice
    ) {
        return;
    }

    const price =
        calculateCustomerPrice(
            editOwnerPrice.value
        );

    editCustomerPrice.value =
        'Rs. ' +
        price.toFixed(2);
}


if (editOwnerPrice) {

    editOwnerPrice.addEventListener(
        'input',
        updateEditPrice
    );
}


/* =========================================================
   CLOSE MODAL ON BACKGROUND
========================================================= */

if (editModal) {

    editModal.addEventListener(
        'click',
        function(event) {

            if (
                event.target ===
                editModal
            ) {

                closeEditModal();

            }

        }
    );
}

</script>


</html>