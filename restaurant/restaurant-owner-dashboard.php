<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| RESTAURANT OWNER DASHBOARD
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not available.");
}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| SAFE PREPARE
|--------------------------------------------------------------------------
*/

function safePrepare($conn, $sql)
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    return $stmt;
}


/*
|--------------------------------------------------------------------------
| TABLE EXISTS
|--------------------------------------------------------------------------
*/

function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $result = $conn->query(
        "SHOW TABLES LIKE '$table'"
    );

    return $result && $result->num_rows > 0;
}


/*
|--------------------------------------------------------------------------
| COLUMN EXISTS
|--------------------------------------------------------------------------
*/

function columnExists($conn, $table, $column)
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $stmt = safePrepare(
        $conn,
        "SHOW COLUMNS FROM `$table` LIKE ?"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "s",
        $column
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $exists =
        $result &&
        $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


/*
|--------------------------------------------------------------------------
| FIND OWNER
|--------------------------------------------------------------------------
*/

$owner = null;

$ownerId = 0;

$ownerEmail = '';


/*
|--------------------------------------------------------------------------
| SESSION OWNER ID
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['restaurant_owner_id'])) {

    $ownerId =
        (int)$_SESSION['restaurant_owner_id'];

}

if (
    $ownerId <= 0 &&
    !empty($_SESSION['restaurant_user_id'])
) {

    $ownerId =
        (int)$_SESSION['restaurant_user_id'];

}

if (
    $ownerId <= 0 &&
    !empty($_SESSION['owner_id'])
) {

    $ownerId =
        (int)$_SESSION['owner_id'];

}


/*
|--------------------------------------------------------------------------
| SESSION EMAIL
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['restaurant_owner_email'])) {

    $ownerEmail =
        trim(
            (string)
            $_SESSION['restaurant_owner_email']
        );

}

if (
    $ownerEmail === '' &&
    !empty($_SESSION['email'])
) {

    $ownerEmail =
        trim(
            (string)
            $_SESSION['email']
        );

}


/*
|--------------------------------------------------------------------------
| FIND OWNER BY ID
|--------------------------------------------------------------------------
*/

if ($ownerId > 0) {

    $stmt = safePrepare(
        $conn,
        "
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status,
            created_at,
            updated_at

        FROM restaurant_users

        WHERE id = ?

        LIMIT 1
        "
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $ownerId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $owner =
            $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| FIND OWNER BY EMAIL
|--------------------------------------------------------------------------
*/

if (
    !$owner &&
    $ownerEmail !== ''
) {

    $stmt = safePrepare(
        $conn,
        "
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status,
            created_at,
            updated_at

        FROM restaurant_users

        WHERE email = ?

        LIMIT 1
        "
    );

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $ownerEmail
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $owner =
            $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| OWNER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$owner) {

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
            Restaurant Owner Login Required
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;

                display: flex;
                align-items: center;
                justify-content: center;

                background: #fff7fa;

                font-family:
                    "Segoe UI",
                    Arial,
                    sans-serif;
            }

            .error-box {
                width: 92%;
                max-width: 480px;

                background: #ffffff;

                padding: 40px;

                text-align: center;

                border-radius: 18px;

                box-shadow:
                    0 15px 45px
                    rgba(0,0,0,.08);

                border:
                    1px solid #f0e0e7;
            }

            .error-icon {
                width: 65px;
                height: 65px;

                margin: 0 auto 20px;

                display: flex;
                align-items: center;
                justify-content: center;

                border-radius: 50%;

                background: #fff0f4;

                color: #e00038;

                font-size: 26px;
            }

            h1 {
                margin: 0 0 10px;

                font-size: 24px;
            }

            p {
                color: #777;

                line-height: 1.6;

                font-size: 13px;
            }

            .btn {
                display: inline-flex;

                align-items: center;
                justify-content: center;

                margin-top: 15px;

                padding: 12px 20px;

                background: #e00038;

                color: #fff;

                text-decoration: none;

                border-radius: 9px;

                font-weight: 700;
            }

        </style>

    </head>

    <body>

        <div class="error-box">

            <div class="error-icon">
                <i>!</i>
            </div>

            <h1>
                Restaurant Owner Login Required
            </h1>

            <p>
                Please login using your restaurant owner
                account to access the dashboard.
            </p>

            <a
                href="restaurant-owner-login.php"
                class="btn"
            >
                Login Again
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| OWNER DATA
|--------------------------------------------------------------------------
*/

$ownerId =
    (int)$owner['id'];

$ownerName =
    trim(
        (string)
        ($owner['full_name'] ?? 'Restaurant Owner')
    );

$restaurantName =
    trim(
        (string)
        ($owner['restaurant_name'] ?? '')
    );

$ownerEmail =
    trim(
        (string)
        ($owner['email'] ?? '')
    );

$ownerPhone =
    trim(
        (string)
        ($owner['phone'] ?? '')
    );


/*
|--------------------------------------------------------------------------
| NORMALIZE STATUS
|--------------------------------------------------------------------------
*/

$ownerStatus =
    strtolower(
        trim(
            (string)
            ($owner['status'] ?? 'pending')
        )
    );


/*
|--------------------------------------------------------------------------
| APPROVAL STATE
|--------------------------------------------------------------------------
*/

if ($ownerStatus === 'active') {

    $approvalState = 'active';

} elseif ($ownerStatus === 'blocked') {

    $approvalState = 'blocked';

} elseif ($ownerStatus === 'inactive') {

    $approvalState = 'inactive';

} else {

    $approvalState = 'pending';

}


/*
|--------------------------------------------------------------------------
| IMPORTANT
|
| ONLY ACTIVE = FULL ACCESS
|--------------------------------------------------------------------------
*/

$isApproved =
    ($approvalState === 'active');


/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| REGISTERED DATE
|--------------------------------------------------------------------------
*/

$registeredDate = 'N/A';

if (!empty($owner['created_at'])) {

    $timestamp =
        strtotime(
            $owner['created_at']
        );

    if ($timestamp) {

        $registeredDate =
            date(
                'd M Y',
                $timestamp
            );

    }

}


/*
|--------------------------------------------------------------------------
| FIND RESTAURANT
|--------------------------------------------------------------------------
*/

$restaurant = null;

$restaurantId = 0;

$restaurantImage = '';

$restaurantRating = 0;

$restaurantDescription = '';

$restaurantAddress = '';

$deliveryTime = '';

$deliveryFee = 0;


if (
    $restaurantName !== '' &&
    tableExists($conn, 'restaurants')
) {

    $stmt = safePrepare(
        $conn,
        "
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
        "
    );

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $restaurantName
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $restaurant =
            $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| RESTAURANT INFORMATION
|--------------------------------------------------------------------------
*/

if ($restaurant) {

    $restaurantId =
        (int)
        ($restaurant['id'] ?? 0);

    $restaurantImage =
        trim(
            (string)
            ($restaurant['image'] ?? '')
        );

    $restaurantRating =
        (float)
        ($restaurant['rating'] ?? 0);

    $restaurantDescription =
        trim(
            (string)
            ($restaurant['description'] ?? '')
        );

    $restaurantAddress =
        trim(
            (string)
            ($restaurant['address'] ?? '')
        );

    $deliveryTime =
        trim(
            (string)
            ($restaurant['delivery_time'] ?? '')
        );

    $deliveryFee =
        (float)
        ($restaurant['delivery_fee'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| RESTAURANT STATUS SYNC
|
| Pending/blocked = hidden
| Active = visible
|--------------------------------------------------------------------------
*/

if (
    $restaurantId > 0 &&
    tableExists($conn, 'restaurants') &&
    columnExists($conn, 'restaurants', 'status')
) {

    $restaurantPublicStatus =
        $isApproved
            ? 1
            : 0;

    $stmt = safePrepare(
        $conn,
        "
        UPDATE restaurants

        SET status = ?

        WHERE id = ?

        LIMIT 1
        "
    );

    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $restaurantPublicStatus,
            $restaurantId
        );

        $stmt->execute();

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| RESTAURANT COUNTS
|--------------------------------------------------------------------------
*/

$totalMenuItems = 0;

$totalOrders = 0;

$totalEarnings = 0;


if (
    $isApproved &&
    $restaurantId > 0
) {


    /*
    |--------------------------------------------------------------------------
    | MENU COUNT
    |--------------------------------------------------------------------------
    */

    if (
        tableExists($conn, 'menu_items')
    ) {

        $stmt = safePrepare(
            $conn,
            "
            SELECT COUNT(*) AS total

            FROM menu_items

            WHERE restaurant_id = ?
            "
        );

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $restaurantId
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $row =
                $result
                ? $result->fetch_assoc()
                : null;

            $totalMenuItems =
                (int)
                ($row['total'] ?? 0);

            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    if (
        tableExists($conn, 'orders')
    ) {

        $stmt = safePrepare(
            $conn,
            "
            SELECT
                COUNT(*) AS total,
                COALESCE(
                    SUM(total),
                    0
                ) AS earnings

            FROM orders

            WHERE restaurant_id = ?
            "
        );

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $restaurantId
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $row =
                $result
                ? $result->fetch_assoc()
                : null;

            $totalOrders =
                (int)
                ($row['total'] ?? 0);

            $totalEarnings =
                (float)
                ($row['earnings'] ?? 0);

            $stmt->close();

        }

    }

}


/*
|--------------------------------------------------------------------------
| RESTAURANT IMAGE UPLOAD
|--------------------------------------------------------------------------
|
| Owner can upload/change restaurant image.
|--------------------------------------------------------------------------
*/

$uploadMessage = '';

$uploadType = '';


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['upload_restaurant_image'])
) {


    if (!$isApproved) {

        $uploadMessage =
            "Restaurant image management is available after admin approval.";

        $uploadType =
            'error';

    } elseif ($restaurantId <= 0) {

        $uploadMessage =
            "Restaurant record was not found. Please contact admin.";

        $uploadType =
            'error';

    } elseif (
        !isset($_FILES['restaurant_image'])
    ) {

        $uploadMessage =
            "Please select an image.";

        $uploadType =
            'error';

    } else {

        $file =
            $_FILES['restaurant_image'];


        if (
            $file['error'] !==
            UPLOAD_ERR_OK
        ) {

            $uploadMessage =
                "Image upload failed.";

            $uploadType =
                'error';

        } elseif (
            $file['size'] >
            5 * 1024 * 1024
        ) {

            $uploadMessage =
                "Image must be less than 5 MB.";

            $uploadType =
                'error';

        } else {


            $extension =
                strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );


            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];


            if (
                !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {

                $uploadMessage =
                    "Only JPG, JPEG, PNG and WEBP images are allowed.";

                $uploadType =
                    'error';

            } else {


                $imageCheck =
                    @getimagesize(
                        $file['tmp_name']
                    );


                if ($imageCheck === false) {

                    $uploadMessage =
                        "The selected file is not a valid image.";

                    $uploadType =
                        'error';

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | CREATE DIRECTORY
                    |--------------------------------------------------------------------------
                    */

                    $uploadDirectory =
                        __DIR__ .
                        '/../assets/images/restaurants/';


                    if (
                        !is_dir(
                            $uploadDirectory
                        )
                    ) {

                        @mkdir(
                            $uploadDirectory,
                            0755,
                            true
                        );

                    }


                    $fileName =
                        'restaurant_' .
                        $ownerId .
                        '_' .
                        time() .
                        '_' .
                        mt_rand(
                            1000,
                            999999
                        ) .
                        '.' .
                        $extension;


                    $destination =
                        $uploadDirectory .
                        $fileName;


                    if (
                        move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {


                        /*
                        |--------------------------------------------------------------------------
                        | SAVE IMAGE PATH
                        |--------------------------------------------------------------------------
                        */

                       $imageDatabasePath = $fileName;


                        $stmt = safePrepare(
                            $conn,
                            "
                            UPDATE restaurants

                            SET image = ?

                            WHERE id = ?

                            LIMIT 1
                            "
                        );


                        if ($stmt) {

                            $stmt->bind_param(
                                "si",
                                $imageDatabasePath,
                                $restaurantId
                            );


                            if (
                                $stmt->execute()
                            ) {

                                $restaurantImage =
                                    $imageDatabasePath;

                                $uploadMessage =
                                    "Restaurant image uploaded successfully.";

                                $uploadType =
                                    'success';

                            } else {

                                $uploadMessage =
                                    "Image was uploaded but could not be saved to database.";

                                $uploadType =
                                    'error';

                            }


                            $stmt->close();

                        } else {

                            $uploadMessage =
                                "Unable to save restaurant image.";

                            $uploadType =
                                'error';

                        }

                    } else {

                        $uploadMessage =
                            "Unable to save uploaded image.";

                        $uploadType =
                            'error';

                    }

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| IMAGE URL
|--------------------------------------------------------------------------
*/

$imageUrl = '';

if ($restaurantImage !== '') {

    /*
     * Restaurant images are stored in:
     * /assets/images/restaurants/
     */

    $imageUrl =
        '../assets/images/restaurants/' .
        ltrim(
            basename($restaurantImage),
            '/'
        );

}


/*
|--------------------------------------------------------------------------
| STATUS DISPLAY
|--------------------------------------------------------------------------
*/

if ($approvalState === 'active') {

    $statusLabel =
        'APPROVED';

    $statusClass =
        'approved';

    $statusIcon =
        'fa-circle-check';

} elseif ($approvalState === 'blocked') {

    $statusLabel =
        'BLOCKED';

    $statusClass =
        'blocked';

    $statusIcon =
        'fa-ban';

} elseif ($approvalState === 'inactive') {

    $statusLabel =
        'INACTIVE';

    $statusClass =
        'inactive';

    $statusIcon =
        'fa-eye-slash';

} else {

    $statusLabel =
        'PENDING';

    $statusClass =
        'pending';

    $statusIcon =
        'fa-clock';

}


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

$currentDate =
    date('d M Y');


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
        Restaurant Owner Dashboard - Humsafar
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;

            min-height: 100%;
        }


        body {

            background: #f6f7fb;

            color: #292929;

            font-family:
                "Segoe UI",
                Tahoma,
                Geneva,
                Verdana,
                sans-serif;
        }


        a {
            text-decoration: none;
        }


        button,
        input {
            font-family: inherit;
        }


        /*
        |--------------------------------------------------------------------------
        | LAYOUT
        |--------------------------------------------------------------------------
        */

        .layout {

            min-height: 100vh;

            display: flex;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main {

            width:
                calc(100% - 223px);

            margin-left: 223px;

            min-height: 100vh;
        }


        /*
        |--------------------------------------------------------------------------
        | TOPBAR
        |--------------------------------------------------------------------------
        */

        .topbar {

            height: 62px;

            background: #fff;

            border-bottom:
                1px solid #e9e9ed;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 27px;

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .topbar-left small {

            display: block;

            margin-bottom: 3px;

            color: #9b9b9b;

            font-size: 8px;

            letter-spacing: 1.3px;

            font-weight: 900;
        }


        .topbar-left strong {

            display: block;

            font-size: 15px;

            font-weight: 900;
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .notification {

            width: 35px;
            height: 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            border:
                1px solid #e7e7eb;

            border-radius: 9px;

            color: #777;

            font-size: 12px;
        }


        .top-avatar {

            width: 36px;
            height: 36px;

            border-radius: 50%;

            background:
                #ffd000;

            color: #222;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 900;

            font-size: 12px;
        }


        .top-user {

            line-height: 1.2;
        }


        .top-user strong {

            display: block;

            font-size: 10px;
        }


        .top-user small {

            color: #999;

            font-size: 8px;
        }


        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .page {

            padding:
                27px;

            max-width: 1500px;

            margin: auto;
        }


        /*
        |--------------------------------------------------------------------------
        | WELCOME
        |--------------------------------------------------------------------------
        */

        .welcome-card {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 15px;

            padding:
                24px 26px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 15px;
        }


        .welcome-card small {

            color: #ef003c;

            font-size: 8px;

            letter-spacing: 1.2px;

            font-weight: 900;
        }


        .welcome-card h1 {

            margin:
                8px 0 5px;

            font-size: 25px;

            line-height: 1.2;
        }


        .welcome-card p {

            margin: 0;

            color: #888;

            font-size: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS BOX
        |--------------------------------------------------------------------------
        */

        .status-box {

            min-width: 225px;

            padding: 15px;

            border-radius: 11px;

            border: 1px solid;
        }


        .status-box.pending {

            background: #fff3f6;

            border-color: #ffd0dc;

            color: #df0038;
        }


        .status-box.approved {

            background: #edfff4;

            border-color: #bce8cc;

            color: #148342;
        }


        .status-box.blocked,
        .status-box.inactive {

            background: #fff1f1;

            border-color: #f2c6c6;

            color: #c52626;
        }


        .status-pill {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 8px;

            border-radius: 15px;

            background:
                rgba(255,255,255,.65);

            font-size: 8px;

            font-weight: 900;
        }


        .status-box strong {

            display: block;

            margin-top: 7px;

            color: #222;

            font-size: 12px;
        }


        .status-box p {

            margin-top: 4px;

            color: #999;

            font-size: 8px;
        }


        /*
        |--------------------------------------------------------------------------
        | STATS
        |--------------------------------------------------------------------------
        */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-bottom: 23px;
        }


        .stat-card {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 13px;

            padding: 17px;

            min-height: 105px;
        }


        .stat-icon {

            width: 34px;
            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background: #fff0f4;

            color: #ef003c;

            font-size: 12px;
        }


        .stat-card strong {

            display: block;

            margin-top: 10px;

            font-size: 21px;
        }


        .stat-card span {

            display: block;

            margin-top: 3px;

            color: #999;

            font-size: 8px;
        }


        /*
        |--------------------------------------------------------------------------
        | SECTION TITLE
        |--------------------------------------------------------------------------
        */

        .section-title {

            margin:
                0 0 10px;

            font-size: 13px;

            font-weight: 900;
        }


        .section-subtitle {

            margin:
                -5px 0 13px;

            color: #999;

            font-size: 8px;
        }


        /*
        |--------------------------------------------------------------------------
        | OVERVIEW GRID
        |--------------------------------------------------------------------------
        */

        .overview-grid {

            display: grid;

            grid-template-columns:
                1.35fr
                1fr;

            gap: 13px;

            margin-bottom: 22px;
        }


        .panel {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 13px;

            overflow: hidden;
        }


        .panel-header {

            min-height: 46px;

            padding:
                0 15px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border-bottom:
                1px solid #eeeeef;

            font-size: 10px;

            font-weight: 900;
        }


        .panel-header span {

            color: #aaa;

            font-size: 8px;

            font-weight: 600;
        }


        .panel-body {

            padding: 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | RESTAURANT INFORMATION
        |--------------------------------------------------------------------------
        */

        .restaurant-information {

            display: grid;

            grid-template-columns:
                1fr 125px;

            gap: 15px;
        }


        .info-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding:
                10px 0;

            border-bottom:
                1px solid #f1f1f3;
        }


        .info-row:last-child {

            border-bottom: none;
        }


        .info-label {

            color: #999;

            font-size: 8px;
        }


        .info-value {

            color: #333;

            font-size: 9px;

            font-weight: 800;

            text-align: right;

            word-break: break-word;
        }


        .info-value.status-active {

            color: #12a04d;
        }


        .info-value.status-pending {

            color: #ef003c;
        }


        /*
        |--------------------------------------------------------------------------
        | RESTAURANT IMAGE
        |--------------------------------------------------------------------------
        */

        .restaurant-image-box {

            position: relative;

            width: 125px;

            height: 125px;

            border-radius: 11px;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #fff0f4,
                    #ffe0e9
                );

            border:
                1px solid #f2dbe3;
        }


        .restaurant-image-box img {

            width: 100%;

            height: 100%;

            display: block;

            object-fit: cover;
        }


        .image-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-direction: column;

            color: #e9003d;

            gap: 7px;

            font-size: 20px;
        }


        .image-placeholder span {

            font-size: 7px;

            color: #999;
        }


        .image-upload {

            margin-top: 8px;

            text-align: center;
        }


        .upload-label {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                6px 8px;

            border-radius: 7px;

            background: #fff0f4;

            color: #e00038;

            font-size: 7px;

            font-weight: 800;

            cursor: pointer;
        }


        .upload-label input {

            display: none;
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVAL PROGRESS
        |--------------------------------------------------------------------------
        */

        .progress-box {

            padding: 18px;
        }


        .progress-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            font-size: 8px;

            font-weight: 800;
        }


        .progress-top span {

            color: #999;

            font-weight: 600;
        }


        .progress {

            width: 100%;

            height: 5px;

            margin:
                12px 0 18px;

            overflow: hidden;

            background: #eee;

            border-radius: 20px;
        }


        .progress-bar {

            height: 100%;

            border-radius: 20px;

            background:
                linear-gradient(
                    90deg,
                    #ed0038,
                    #f85b8d
                );
        }


        .progress-bar.pending {

            width: 35%;
        }


        .progress-bar.approved {

            width: 100%;

            background: #16a052;
        }


        .progress-bar.blocked {

            width: 100%;

            background: #d52c2c;
        }


        .timeline {

            display: grid;

            gap: 15px;
        }


        .timeline-item {

            display: flex;

            gap: 9px;

            align-items: flex-start;
        }


        .timeline-icon {

            width: 23px;
            height: 23px;

            flex-shrink: 0;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eee;

            color: #999;

            font-size: 8px;
        }


        .timeline-item.done
        .timeline-icon {

            background: #e5f9ed;

            color: #13a052;
        }


        .timeline-item.current
        .timeline-icon {

            background: #fff0f4;

            color: #e9003d;
        }


        .timeline-item strong {

            display: block;

            font-size: 8px;
        }


        .timeline-item small {

            display: block;

            margin-top: 3px;

            color: #999;

            font-size: 7px;

            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | QUICK ACCESS
        |--------------------------------------------------------------------------
        */

        .quick-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 12px;

            margin-bottom: 22px;
        }


        .quick-card {

            position: relative;

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 13px;

            padding: 16px;

            min-height: 145px;

            transition: .2s ease;
        }


        .quick-card.enabled:hover {

            transform:
                translateY(-2px);

            border-color:
                #f2b4c5;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.06);
        }


        .quick-card.locked {

            opacity: .58;

            background: #fbfbfc;

            cursor: not-allowed;
        }


        .quick-icon {

            width: 36px;
            height: 36px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background: #fff0f4;

            color: #ed003c;

            font-size: 12px;

            margin-bottom: 11px;
        }


        .quick-card h3 {

            margin:
                0 0 5px;

            font-size: 10px;
        }


        .quick-card p {

            margin: 0;

            color: #999;

            font-size: 8px;

            line-height: 1.5;
        }


        .quick-bottom {

            position: absolute;

            left: 16px;
            right: 16px;
            bottom: 13px;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .quick-status {

            font-size: 7px;

            font-weight: 800;
        }


        .quick-status.open {

            color: #14a052;
        }


        .quick-status.lock {

            color: #e00038;
        }


        .quick-arrow {

            color: #e00038;

            font-size: 9px;
        }


        /*
        |--------------------------------------------------------------------------
        | ACCOUNT CARD
        |--------------------------------------------------------------------------
        */

        .account-card {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 13px;

            overflow: hidden;

            margin-bottom: 30px;
        }


        .account-header {

            padding:
                15px;

            border-bottom:
                1px solid #eee;

            font-size: 11px;

            font-weight: 900;
        }


        .account-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            padding:
                12px 15px;

            border-bottom:
                1px solid #f2f2f3;
        }


        .account-row:last-child {

            border-bottom: none;
        }


        .account-label {

            color: #999;

            font-size: 8px;
        }


        .account-value {

            color: #333;

            font-size: 9px;

            font-weight: 800;

            text-align: right;
        }


        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {

            padding:
                11px 14px;

            margin-bottom: 14px;

            border-radius: 9px;

            font-size: 9px;

            font-weight: 700;
        }


        .alert.success {

            background: #e9faef;

            border:
                1px solid #c2ebd0;

            color: #137841;
        }


        .alert.error {

            background: #fff0f3;

            border:
                1px solid #ffc8d4;

            color: #b0002d;
        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {

            padding:
                20px;

            text-align: center;

            color: #999;

            font-size: 8px;
        }


        .footer strong {

            color: #e00038;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1050px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .quick-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }

            .overview-grid {

                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 750px) {

            .sidebar {

                width: 70px;
            }


            .brand {

                padding:
                    15px 10px;
            }


            .brand-name,
            .brand-sub,
            .menu-label,
            .nav-item span,
            .nav-lock,
            .sidebar-user > div {

                display: none;
            }


            .brand a {

                justify-content: center;
            }


            .nav-item {

                justify-content: center;

                padding: 12px;
            }


            .sidebar-user {

                justify-content: center;

                padding: 8px;
            }


            .main {

                width:
                    calc(100% - 70px);

                margin-left: 70px;
            }


            .page {

                padding: 17px;
            }


            .welcome-card {

                flex-direction: column;

                align-items: flex-start;
            }


            .status-box {

                width: 100%;
            }


            .restaurant-information {

                grid-template-columns: 1fr;
            }


            .restaurant-image-box {

                width: 100%;

                height: 180px;
            }

        }


        @media (max-width: 520px) {

            .top-user {

                display: none;
            }


            .stats {

                grid-template-columns: 1fr;
            }


            .quick-grid {

                grid-template-columns: 1fr;
            }


            .restaurant-information {

                display: flex;

                flex-direction: column;
            }


            .account-row {

                flex-direction: column;

                align-items: flex-start;
            }


            .account-value {

                text-align: left;
            }

        }

    </style>

</head>


<body>


<div class="layout">


    <?php include __DIR__ . '/restaurant-sidebar.php'; ?>
    
    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <!-- TOP BAR -->

        <header class="topbar">


            <div class="topbar-left">

                <small>
                    RESTAURANT PARTNER PORTAL
                </small>

                <strong>
                    Dashboard
                </strong>

            </div>


            <div class="topbar-right">


                <div class="notification">

                    <i class="far fa-bell"></i>

                </div>


                <div class="top-avatar">

                    <?= h($initial) ?>

                </div>


                <div class="top-user">

                    <strong>
                        <?= h($ownerName) ?>
                    </strong>

                    <small>
                        Restaurant Owner
                    </small>

                </div>


                <a
                    href="../logout.php"
                    class="notification"
                    title="Logout"
                >

                    <i
                        class="fas fa-right-from-bracket"
                    ></i>

                </a>


            </div>

        </header>


        <!-- PAGE -->

        <div class="page">


            <!-- UPLOAD MESSAGE -->

            <?php if ($uploadMessage !== ''): ?>

                <div
                    class="
                        alert
                        <?= $uploadType === 'success'
                            ? 'success'
                            : 'error'
                        ?>
                    "
                >

                    <i
                        class="
                            fas
                            <?= $uploadType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation'
                            ?>
                        "
                    ></i>

                    <?= h($uploadMessage) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 WELCOME
            ================================================== -->

            <section class="welcome-card">


                <div>

                    <small>
                        RESTAURANT OWNER DASHBOARD
                    </small>

                    <h1>
                        Welcome back,
                        <?= h($ownerName) ?>
                    </h1>

                    <p>
                        Manage your restaurant, menu and
                        orders from one place.
                    </p>

                </div>


                <div
                    class="
                        status-box
                        <?= h($statusClass) ?>
                    "
                >

                    <div class="status-pill">

                        <i
                            class="
                                fas
                                <?= h($statusIcon) ?>
                            "
                        ></i>

                        <?= h($statusLabel) ?>

                    </div>


                    <strong>
                        Account Status
                    </strong>


                    <?php if ($isApproved): ?>

                        <p>
                            Your restaurant partner account
                            is approved and active.
                        </p>

                    <?php elseif ($approvalState === 'pending'): ?>

                        <p>
                            Your account is waiting for
                            administrator approval.
                        </p>

                    <?php elseif ($approvalState === 'blocked'): ?>

                        <p>
                            Your account is currently blocked.
                        </p>

                    <?php else: ?>

                        <p>
                            Your account is currently inactive.
                        </p>

                    <?php endif; ?>


                </div>


            </section>


            <!-- =================================================
                 STATS
            ================================================== -->

            <div class="stats">


                <!-- ORDERS -->

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fas fa-receipt"></i>

                    </div>

                    <strong>
                        <?= $isApproved
                            ? h($totalOrders)
                            : '--'
                        ?>
                    </strong>

                    <span>
                        Total Orders
                    </span>

                </div>


                <!-- EARNINGS -->

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fas fa-wallet"></i>

                    </div>

                    <strong>
                        <?= $isApproved
                            ? 'Rs. ' .
                              number_format(
                                  $totalEarnings,
                                  0
                              )
                            : '--'
                        ?>
                    </strong>

                    <span>
                        Total Earnings
                    </span>

                </div>


                <!-- MENU -->

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fas fa-utensils"></i>

                    </div>

                    <strong>
                        <?= $isApproved
                            ? h($totalMenuItems)
                            : '--'
                        ?>
                    </strong>

                    <span>
                        Menu Items
                    </span>

                </div>


                <!-- RATING -->

                <div class="stat-card">

                    <div class="stat-icon">

                        <i class="fas fa-star"></i>

                    </div>

                    <strong>

                        <?= $isApproved &&
                           $restaurantRating > 0
                            ? number_format(
                                $restaurantRating,
                                1
                              )
                            : '--'
                        ?>

                    </strong>

                    <span>
                        Restaurant Rating
                    </span>

                </div>


            </div>


            <!-- =================================================
                 RESTAURANT OVERVIEW
            ================================================== -->

            <h2 class="section-title">
                Restaurant Overview
            </h2>

            <p class="section-subtitle">
                Your restaurant information and approval status.
            </p>


            <div class="overview-grid">


                <!-- RESTAURANT INFORMATION -->

                <section class="panel">


                    <div class="panel-header">

                        <span>
                            Restaurant Information
                        </span>

                        <span>
                            Owner Account
                        </span>

                    </div>


                    <div class="panel-body">


                        <div class="restaurant-information">


                            <div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Restaurant Name
                                    </span>

                                    <span class="info-value">
                                        <?= h(
                                            $restaurantName !== ''
                                                ? $restaurantName
                                                : 'Not provided'
                                        ) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Owner Name
                                    </span>

                                    <span class="info-value">
                                        <?= h($ownerName) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Email Address
                                    </span>

                                    <span class="info-value">
                                        <?= h($ownerEmail) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Phone Number
                                    </span>

                                    <span class="info-value">
                                        <?= h($ownerPhone) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Account Status
                                    </span>

                                    <span
                                        class="
                                            info-value
                                            <?= $isApproved
                                                ? 'status-active'
                                                : 'status-pending'
                                            ?>
                                        "
                                    >

                                        <?= h($statusLabel) ?>

                                    </span>

                                </div>


                            </div>


                            <!-- IMAGE -->

                            <div>


                                <div class="restaurant-image-box">


                                    <?php if ($imageUrl !== ''): ?>

                                        <img
                                            src="<?= h($imageUrl) ?>"
                                            alt="Restaurant Image"
                                        >

                                    <?php else: ?>

                                        <div
                                            class="image-placeholder"
                                        >

                                            <i
                                                class="
                                                    fas
                                                    fa-store
                                                "
                                            ></i>

                                            <span>
                                                No Restaurant Image
                                            </span>

                                        </div>

                                    <?php endif; ?>


                                </div>


                                <?php if ($isApproved): ?>

                                    <form
                                        method="POST"
                                        enctype="multipart/form-data"
                                        class="image-upload"
                                    >

                                        <label
                                            class="upload-label"
                                        >

                                            <i
                                                class="
                                                    fas
                                                    fa-camera
                                                "
                                            ></i>

                                            Upload Image

                                            <input
                                                type="file"
                                                name="restaurant_image"
                                                accept=".jpg,.jpeg,.png,.webp"
                                                required
                                                onchange="
                                                    this.form.submit();
                                                "
                                            >

                                        </label>


                                        <input
                                            type="hidden"
                                            name="upload_restaurant_image"
                                            value="1"
                                        >

                                    </form>

                                <?php else: ?>

                                    <div
                                        style="
                                            margin-top:7px;
                                            text-align:center;
                                            color:#aaa;
                                            font-size:7px;
                                        "
                                    >

                                        <i
                                            class="
                                                fas
                                                fa-lock
                                            "
                                        ></i>

                                        Available after approval

                                    </div>

                                <?php endif; ?>


                            </div>


                        </div>


                    </div>


                </section>


                <!-- APPROVAL PROGRESS -->

                <section class="panel">


                    <div class="panel-header">

                        Approval Progress

                        <span>

                            <?php if ($isApproved): ?>

                                Approved

                            <?php elseif ($approvalState === 'blocked'): ?>

                                Blocked

                            <?php else: ?>

                                In Review

                            <?php endif; ?>

                        </span>

                    </div>


                    <div class="progress-box">


                        <div class="progress-top">

                            <span>
                                <?= $isApproved
                                    ? 'Restaurant Approved'
                                    : 'Waiting for Approval'
                                ?>
                            </span>

                            <span>

                                <?= $isApproved
                                    ? '100%'
                                    : (
                                        $approvalState === 'blocked'
                                            ? '100%'
                                            : '35%'
                                    )
                                ?>

                            </span>

                        </div>


                        <div class="progress">

                            <div
                                class="
                                    progress-bar
                                    <?= $isApproved
                                        ? 'approved'
                                        : (
                                            $approvalState === 'blocked'
                                                ? 'blocked'
                                                : 'pending'
                                        )
                                    ?>
                                "
                            ></div>

                        </div>


                        <div class="timeline">


                            <!-- STEP 1 -->

                            <div class="timeline-item done">

                                <div class="timeline-icon">

                                    <i
                                        class="
                                            fas
                                            fa-check
                                        "
                                    ></i>

                                </div>

                                <div>

                                    <strong>
                                        Registration Completed
                                    </strong>

                                    <small>
                                        Your restaurant owner
                                        account has been created.
                                    </small>

                                </div>

                            </div>


                            <!-- STEP 2 -->

                            <div
                                class="
                                    timeline-item
                                    <?= $isApproved
                                        ? 'done'
                                        : 'current'
                                    ?>
                                "
                            >

                                <div class="timeline-icon">

                                    <i
                                        class="
                                            fas
                                            <?= $isApproved
                                                ? 'fa-check'
                                                : 'fa-hourglass-half'
                                            ?>
                                        "
                                    ></i>

                                </div>

                                <div>

                                    <strong>
                                        Admin Review
                                    </strong>

                                    <small>

                                        <?php if ($isApproved): ?>

                                            Your restaurant has been
                                            approved by Humsafar admin.

                                        <?php elseif ($approvalState === 'blocked'): ?>

                                            Your account has been blocked
                                            by Humsafar admin.

                                        <?php else: ?>

                                            Your restaurant is waiting
                                            for admin approval.

                                        <?php endif; ?>

                                    </small>

                                </div>

                            </div>


                            <!-- STEP 3 -->

                            <div
                                class="
                                    timeline-item
                                    <?= $isApproved
                                        ? 'done'
                                        : ''
                                    ?>
                                "
                            >

                                <div class="timeline-icon">

                                    <i
                                        class="
                                            fas
                                            <?= $isApproved
                                                ? 'fa-check'
                                                : 'fa-lock'
                                            ?>
                                        "
                                    ></i>

                                </div>

                                <div>

                                    <strong>
                                        Live on Humsafar
                                    </strong>

                                    <small>
                                        Restaurant becomes visible
                                        to customers after approval.
                                    </small>

                                </div>

                            </div>


                        </div>


                    </div>


                </section>


            </div>


            <!-- =================================================
                 QUICK ACCESS
            ================================================== -->

            <h2 class="section-title">
                Quick Access
            </h2>

            <p class="section-subtitle">
                These features unlock automatically after admin approval.
            </p>


            <div class="quick-grid">


                <!-- RESTAURANT -->

                <?php if ($isApproved): ?>

                    <a
                        href="restaurant-owner-manage.php"
                        class="
                            quick-card
                            enabled
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-store"></i>

                        </div>

                        <h3>
                            Restaurant
                        </h3>

                        <p>
                            Manage restaurant information,
                            image, address and details.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    open
                                "
                            >
                                Open
                            </span>

                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </a>

                <?php else: ?>

                    <div
                        class="
                            quick-card
                            locked
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-store"></i>

                        </div>

                        <h3>
                            Restaurant
                        </h3>

                        <p>
                            Manage restaurant information,
                            image, address and details.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    lock
                                "
                            >
                                Locked
                            </span>

                            <i
                                class="
                                    fas
                                    fa-lock
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- MENU -->

                <?php if ($isApproved): ?>

                    <a
                        href="restaurant-owner-manage-menu.php"
                        class="
                            quick-card
                            enabled
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-utensils"></i>

                        </div>

                        <h3>
                            Menu Management
                        </h3>

                        <p>
                            Add, edit and manage your
                            restaurant menu.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    open
                                "
                            >
                                Open
                            </span>

                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </a>

                <?php else: ?>

                    <div
                        class="
                            quick-card
                            locked
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-utensils"></i>

                        </div>

                        <h3>
                            Menu Management
                        </h3>

                        <p>
                            Add, edit and manage your
                            restaurant menu.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    lock
                                "
                            >
                                Locked
                            </span>

                            <i
                                class="
                                    fas
                                    fa-lock
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- ORDERS -->

                <?php if ($isApproved): ?>

                    <a
                        href="restaurant-owner-orders.php"
                        class="
                            quick-card
                            enabled
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-receipt"></i>

                        </div>

                        <h3>
                            Orders
                        </h3>

                        <p>
                            View incoming orders and
                            manage customer orders.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    open
                                "
                            >
                                Open
                            </span>

                            <i
                                class="
                                    fas
                                    fa-arrow-right
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </a>

                <?php else: ?>

                    <div
                        class="
                            quick-card
                            locked
                        "
                    >

                        <div class="quick-icon">

                            <i class="fas fa-receipt"></i>

                        </div>

                        <h3>
                            Orders
                        </h3>

                        <p>
                            View incoming orders and
                            manage customer orders.
                        </p>

                        <div class="quick-bottom">

                            <span
                                class="
                                    quick-status
                                    lock
                                "
                            >
                                Locked
                            </span>

                            <i
                                class="
                                    fas
                                    fa-lock
                                    quick-arrow
                                "
                            ></i>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- PROFILE -->

                <a
                    href="restaurant-owner-manage.php"
                    class="
                        quick-card
                        enabled
                    "
                >

                    <div class="quick-icon">

                        <i class="fas fa-user"></i>

                    </div>

                    <h3>
                        Owner Profile
                    </h3>

                    <p>
                        View and manage your account
                        information.
                    </p>

                    <div class="quick-bottom">

                        <span
                            class="
                                quick-status
                                open
                            "
                        >
                            Open
                        </span>

                        <i
                            class="
                                fas
                                fa-arrow-right
                                quick-arrow
                            "
                        ></i>

                    </div>

                </a>


            </div>


            <!-- =================================================
                 ACCOUNT INFORMATION
            ================================================== -->

            <section class="account-card">


                <div class="account-header">

                    <i
                        class="
                            fas
                            fa-user-circle
                        "
                    ></i>

                    &nbsp;

                    Account Information

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Restaurant Owner ID
                    </span>

                    <span class="account-value">
                        #<?= h($ownerId) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Restaurant Name
                    </span>

                    <span class="account-value">
                        <?= h(
                            $restaurantName !== ''
                                ? $restaurantName
                                : 'Not provided'
                        ) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Owner Name
                    </span>

                    <span class="account-value">
                        <?= h($ownerName) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Email
                    </span>

                    <span class="account-value">
                        <?= h($ownerEmail) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Phone
                    </span>

                    <span class="account-value">
                        <?= h($ownerPhone) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Registered On
                    </span>

                    <span class="account-value">
                        <?= h($registeredDate) ?>
                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Account Status
                    </span>

                    <span class="account-value">

                        <?= h($statusLabel) ?>

                    </span>

                </div>


                <div class="account-row">

                    <span class="account-label">
                        Customer Visibility
                    </span>

                    <span class="account-value">

                        <?php if ($isApproved): ?>

                            <span
                                style="
                                    color:#14984a;
                                "
                            >
                                <i
                                    class="
                                        fas
                                        fa-circle-check
                                    "
                                ></i>

                                LIVE

                            </span>

                        <?php else: ?>

                            <span
                                style="
                                    color:#e00038;
                                "
                            >
                                <i
                                    class="
                                        fas
                                        fa-lock
                                    "
                                ></i>

                                HIDDEN

                            </span>

                        <?php endif; ?>

                    </span>

                </div>


            </section>


            <!-- SUPPORT -->

            <div id="support"></div>


            <div class="footer">

                <strong>
                    Humsafar
                </strong>

                Food Delivery
                &nbsp;•&nbsp;
                Restaurant Owner Portal
                &nbsp;•&nbsp;
                <?= h($currentDate) ?>

            </div>


        </div>


    </main>


</div>


</body>

</html>