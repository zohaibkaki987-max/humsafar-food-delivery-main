<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| RESTAURANT OWNER PROFILE
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
| HELPERS
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
| FIND OWNER SESSION
|--------------------------------------------------------------------------
*/

$ownerId = 0;
$ownerEmail = '';

if (!empty($_SESSION['restaurant_owner_id'])) {

    $ownerId = (int)$_SESSION['restaurant_owner_id'];

}

if (
    $ownerId <= 0 &&
    !empty($_SESSION['restaurant_user_id'])
) {

    $ownerId = (int)$_SESSION['restaurant_user_id'];

}

if (
    $ownerId <= 0 &&
    !empty($_SESSION['owner_id'])
) {

    $ownerId = (int)$_SESSION['owner_id'];

}


if (!empty($_SESSION['restaurant_owner_email'])) {

    $ownerEmail = trim(
        (string)$_SESSION['restaurant_owner_email']
    );

}

if (
    $ownerEmail === '' &&
    !empty($_SESSION['email'])
) {

    $ownerEmail = trim(
        (string)$_SESSION['email']
    );

}


/*
|--------------------------------------------------------------------------
| GET OWNER
|--------------------------------------------------------------------------
*/

$owner = null;


/*
|--------------------------------------------------------------------------
| FIND BY ID
|--------------------------------------------------------------------------
*/

if ($ownerId > 0) {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            password,
            status,
            created_at,
            updated_at
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

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


/*
|--------------------------------------------------------------------------
| FIND BY EMAIL
|--------------------------------------------------------------------------
*/

if (
    !$owner &&
    $ownerEmail !== ''
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            password,
            status,
            created_at,
            updated_at
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


/*
|--------------------------------------------------------------------------
| LOGIN REQUIRED
|--------------------------------------------------------------------------
*/

if (!$owner) {

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| OWNER DATA
|--------------------------------------------------------------------------
*/

$ownerId = (int)$owner['id'];

$ownerName = trim(
    (string)($owner['full_name'] ?? '')
);

$restaurantName = trim(
    (string)($owner['restaurant_name'] ?? '')
);

$ownerEmail = trim(
    (string)($owner['email'] ?? '')
);

$ownerPhone = trim(
    (string)($owner['phone'] ?? '')
);

$ownerPassword = (string)(
    $owner['password'] ?? ''
);

$ownerStatus = strtolower(
    trim(
        (string)($owner['status'] ?? 'pending')
    )
);


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($ownerStatus === 'active') {

    $statusLabel = 'APPROVED';
    $statusClass = 'approved';
    $statusIcon = 'fa-circle-check';

} elseif ($ownerStatus === 'blocked') {

    $statusLabel = 'BLOCKED';
    $statusClass = 'blocked';
    $statusIcon = 'fa-ban';

} elseif ($ownerStatus === 'inactive') {

    $statusLabel = 'INACTIVE';
    $statusClass = 'inactive';
    $statusIcon = 'fa-eye-slash';

} else {

    $statusLabel = 'PENDING';
    $statusClass = 'pending';
    $statusIcon = 'fa-clock';

}


$isApproved = (
    $ownerStatus === 'active'
);


/*
|--------------------------------------------------------------------------
| INITIAL
|--------------------------------------------------------------------------
*/

$initialSource =
    $ownerName !== ''
        ? $ownerName
        : 'R';

$initial = strtoupper(
    substr(
        $initialSource,
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

    $timestamp = strtotime(
        $owner['created_at']
    );

    if ($timestamp) {

        $registeredDate = date(
            'd M Y',
            $timestamp
        );

    }

}


/*
|--------------------------------------------------------------------------
| FORM MESSAGES
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_profile'])
) {

    $newName = trim(
        $_POST['full_name'] ?? ''
    );

    $newPhone = trim(
        $_POST['phone'] ?? ''
    );


    if ($newName === '') {

        $message =
            "Please enter your full name.";

        $messageType = 'error';

    } elseif ($newPhone === '') {

        $message =
            "Please enter your phone number.";

        $messageType = 'error';

    } else {

        $stmt = $conn->prepare("
            UPDATE restaurant_users
            SET
                full_name = ?,
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

            if ($stmt->execute()) {

                $ownerName = $newName;
                $ownerPhone = $newPhone;

                $message =
                    "Profile information updated successfully.";

                $messageType = 'success';


                /*
                |--------------------------------------------------------------------------
                | UPDATE SESSION DISPLAY DATA
                |--------------------------------------------------------------------------
                */

                $_SESSION['restaurant_owner_name'] =
                    $newName;

            } else {

                $message =
                    "Unable to update profile. Please try again.";

                $messageType = 'error';

            }

            $stmt->close();

        } else {

            $message =
                "Database error. Please try again.";

            $messageType = 'error';

        }

    }

}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    if ($currentPassword === '') {

        $message =
            "Please enter your current password.";

        $messageType = 'error';

    } elseif (
        !password_verify(
            $currentPassword,
            $ownerPassword
        )
    ) {

        $message =
            "Current password is incorrect.";

        $messageType = 'error';

    } elseif (strlen($newPassword) < 6) {

        $message =
            "New password must be at least 6 characters.";

        $messageType = 'error';

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $message =
            "New passwords do not match.";

        $messageType = 'error';

    } else {

        $hashedPassword =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        if ($hashedPassword === false) {

            $message =
                "Unable to secure your new password.";

            $messageType = 'error';

        } else {

            $stmt = $conn->prepare("
                UPDATE restaurant_users
                SET password = ?
                WHERE id = ?
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $hashedPassword,
                    $ownerId
                );

                if ($stmt->execute()) {

                    $ownerPassword =
                        $hashedPassword;

                    $message =
                        "Password changed successfully.";

                    $messageType = 'success';

                } else {

                    $message =
                        "Unable to change password.";

                    $messageType = 'error';

                }

                $stmt->close();

            } else {

                $message =
                    "Database error. Please try again.";

                $messageType = 'error';

            }

        }

    }

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

    <title>
        Profile - Humsafar Restaurant Partner
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


        /* =====================================================
           LAYOUT
        ====================================================== */

        .layout {

            min-height: 100vh;

            display: flex;
        }


        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar {

            position: fixed;

            left: 0;
            top: 0;
            bottom: 0;

            width: 223px;

            background:
                linear-gradient(
                    180deg,
                    #ef003c 0%,
                    #f30043 48%,
                    #f62d69 100%
                );

            color: #fff;

            z-index: 1000;

            display: flex;

            flex-direction: column;

            box-shadow:
                5px 0 20px
                rgba(0,0,0,.08);
        }


        /* =====================================================
           BRAND
        ====================================================== */

        .brand {

            padding:
                18px 20px 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.16);
        }


        .brand a {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #fff;
        }


        .brand-icon {

            width: 38px;
            height: 38px;

            border-radius: 11px;

            background: #fff;

            color: #ed003c;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 17px;
        }


        .brand-name {

            font-size: 20px;

            font-weight: 900;
        }


        .brand-sub {

            margin:
                5px 0 0 48px;

            font-size: 8px;

            letter-spacing: 1.2px;

            font-weight: 800;

            opacity: .78;
        }


        /* =====================================================
           SIDEBAR CONTENT
        ====================================================== */

        .sidebar-content {

            flex: 1;

            padding:
                18px 12px;

            overflow-y: auto;
        }


        .menu-label {

            padding:
                0 12px;

            margin:
                7px 0 10px;

            font-size: 8px;

            letter-spacing: 1.4px;

            font-weight: 900;

            opacity: .65;
        }


        .nav-item {

            position: relative;

            display: flex;

            align-items: center;

            gap: 12px;

            min-height: 42px;

            padding:
                10px 13px;

            margin-bottom: 4px;

            border-radius: 9px;

            color:
                rgba(255,255,255,.88);

            font-size: 11px;

            font-weight: 700;

            transition: .2s ease;
        }


        .nav-item i {

            width: 17px;

            text-align: center;
        }


        .nav-item:hover {

            background:
                rgba(255,255,255,.12);

            color: #fff;
        }


        .nav-item.active {

            background: #fff;

            color: #e9003d;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,.08);
        }


        .nav-item.locked {

            opacity: .48;

            cursor: not-allowed;
        }


        .nav-lock {

            margin-left: auto;

            font-size: 9px;
        }


        /* =====================================================
           SIDEBAR USER
        ====================================================== */

        .sidebar-user {

            margin:
                0 12px 13px;

            padding: 11px;

            border:
                1px solid
                rgba(255,255,255,.18);

            border-radius: 11px;

            background:
                rgba(255,255,255,.08);

            display: flex;

            align-items: center;

            gap: 9px;
        }


        .sidebar-avatar {

            width: 33px;
            height: 33px;

            flex-shrink: 0;

            border-radius: 50%;

            background: #fff;

            color: #e9003d;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: 900;

            font-size: 12px;
        }


        .sidebar-user strong {

            display: block;

            max-width: 125px;

            overflow: hidden;

            white-space: nowrap;

            text-overflow: ellipsis;

            font-size: 10px;
        }


        .sidebar-user small {

            display: block;

            margin-top: 3px;

            font-size: 8px;

            opacity: .68;
        }


        /* =====================================================
           MAIN
        ====================================================== */

        .main {

            width:
                calc(100% - 223px);

            margin-left: 223px;

            min-height: 100vh;
        }


        /* =====================================================
           TOPBAR
        ====================================================== */

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

            background: #ffd000;

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


        /* =====================================================
           PAGE
        ====================================================== */

        .page {

            padding: 27px;

            max-width: 1500px;

            margin: auto;
        }


        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .page-heading {

            margin-bottom: 18px;
        }


        .page-heading small {

            display: block;

            color: #ef003c;

            font-size: 8px;

            letter-spacing: 1.3px;

            font-weight: 900;

            margin-bottom: 7px;
        }


        .page-heading h1 {

            margin: 0 0 5px;

            font-size: 27px;

            line-height: 1.2;

            font-weight: 900;
        }


        .page-heading p {

            margin: 0;

            color: #999;

            font-size: 10px;
        }


        /* =====================================================
           ALERT
        ====================================================== */

        .alert {

            display: flex;

            align-items: center;

            gap: 9px;

            padding:
                12px 14px;

            margin-bottom: 17px;

            border-radius: 9px;

            font-size: 10px;

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


        /* =====================================================
           PROFILE HERO
        ====================================================== */

        .profile-hero {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 15px;

            padding: 22px;

            margin-bottom: 15px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;
        }


        .profile-user {

            display: flex;

            align-items: center;

            gap: 15px;
        }


        .large-avatar {

            width: 70px;
            height: 70px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #ef003c,
                    #f94f87
                );

            color: #fff;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 25px;

            font-weight: 900;

            box-shadow:
                0 8px 20px
                rgba(239,0,60,.18);
        }


        .profile-user h2 {

            margin: 0 0 5px;

            font-size: 20px;
        }


        .profile-user p {

            margin: 0;

            color: #999;

            font-size: 9px;
        }


        .status-card {

            min-width: 190px;

            padding: 13px;

            border-radius: 11px;

            border: 1px solid;
        }


        .status-card.approved {

            background: #edfff4;

            border-color: #bce8cc;
        }


        .status-card.pending {

            background: #fff3f6;

            border-color: #ffd0dc;
        }


        .status-card.blocked,
        .status-card.inactive {

            background: #fff1f1;

            border-color: #f2c6c6;
        }


        .status-pill {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding:
                5px 8px;

            border-radius: 15px;

            background:
                rgba(255,255,255,.75);

            color: #e00038;

            font-size: 8px;

            font-weight: 900;
        }


        .status-card.approved .status-pill {

            color: #148342;
        }


        .status-card.blocked .status-pill,
        .status-card.inactive .status-pill {

            color: #c52626;
        }


        .status-card strong {

            display: block;

            margin-top: 7px;

            font-size: 10px;
        }


        .status-card small {

            display: block;

            margin-top: 3px;

            color: #999;

            font-size: 7px;

            line-height: 1.5;
        }


        /* =====================================================
           GRID
        ====================================================== */

        .profile-grid {

            display: grid;

            grid-template-columns:
                1.45fr
                1fr;

            gap: 15px;
        }


        /* =====================================================
           PANEL
        ====================================================== */

        .panel {

            background: #fff;

            border:
                1px solid #e7e7eb;

            border-radius: 13px;

            overflow: hidden;

            margin-bottom: 15px;
        }


        .panel-header {

            min-height: 48px;

            padding:
                0 16px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            border-bottom:
                1px solid #eeeeef;
        }


        .panel-header strong {

            font-size: 11px;

            font-weight: 900;
        }


        .panel-header span {

            color: #aaa;

            font-size: 8px;
        }


        .panel-body {

            padding: 17px;
        }


        /* =====================================================
           FORM
        ====================================================== */

        .form-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 14px;
        }


        .form-group {

            margin-bottom: 14px;
        }


        .form-group.full {

            grid-column:
                1 / -1;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: #333;

            font-size: 9px;

            font-weight: 800;
        }


        .input-wrap {

            position: relative;
        }


        .input-wrap > i {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #e4003b;

            font-size: 11px;

            pointer-events: none;
        }


        .form-control {

            width: 100%;

            height: 42px;

            padding:
                0 13px 0 36px;

            border:
                1px solid #dedee3;

            border-radius: 8px;

            outline: none;

            background: #fff;

            color: #333;

            font-size: 10px;

            transition: .2s ease;
        }


        .form-control:focus {

            border-color: #ef174b;

            box-shadow:
                0 0 0 3px
                rgba(239,23,75,.07);
        }


        .form-control.readonly {

            background: #f7f7f9;

            color: #777;

            cursor: not-allowed;
        }


        .field-note {

            margin-top: 5px;

            color: #999;

            font-size: 7px;

            line-height: 1.5;
        }


        .save-button {

            min-width: 145px;

            height: 40px;

            border: none;

            border-radius: 8px;

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f94f87
                );

            font-size: 10px;

            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 6px 16px
                rgba(237,0,56,.16);

            transition: .2s ease;
        }


        .save-button:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 9px 20px
                rgba(237,0,56,.22);
        }


        /* =====================================================
           PASSWORD
        ====================================================== */

        .password-field {

            position: relative;
        }


        .password-field .form-control {

            padding-right: 38px;
        }


        .password-toggle {

            position: absolute;

            right: 12px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color: #e00038;

            cursor: pointer;

            font-size: 11px;
        }


        /* =====================================================
           ACCOUNT DETAILS
        ====================================================== */

        .detail-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            padding:
                12px 0;

            border-bottom:
                1px solid #f1f1f3;
        }


        .detail-row:last-child {

            border-bottom: none;
        }


        .detail-label {

            color: #999;

            font-size: 8px;
        }


        .detail-value {

            color: #333;

            font-size: 9px;

            font-weight: 800;

            text-align: right;

            word-break: break-word;
        }


        .detail-value.green {

            color: #12a04d;
        }


        .detail-value.red {

            color: #ef003c;
        }


        /* =====================================================
           RESTAURANT CARD
        ====================================================== */

        .restaurant-card {

            background:
                linear-gradient(
                    135deg,
                    #fff6f8,
                    #fff
                );

            border:
                1px solid #f6d4de;

            border-radius: 10px;

            padding: 15px;
        }


        .restaurant-card-top {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 12px;
        }


        .restaurant-icon {

            width: 40px;
            height: 40px;

            border-radius: 9px;

            background: #fff;

            color: #ed003c;

            display: flex;

            align-items: center;
            justify-content: center;

            border:
                1px solid #f5d5df;
        }


        .restaurant-card h3 {

            margin: 0 0 3px;

            font-size: 11px;
        }


        .restaurant-card p {

            margin: 0;

            color: #999;

            font-size: 8px;
        }


        .manage-button {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            width: 100%;

            height: 38px;

            border-radius: 8px;

            background: #fff;

            border:
                1px solid #ed003c;

            color: #ed003c;

            font-size: 9px;

            font-weight: 800;

            transition: .2s ease;
        }


        .manage-button:hover {

            background: #ed003c;

            color: #fff;
        }


        /* =====================================================
           SECURITY NOTE
        ====================================================== */

        .security-note {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            padding: 12px;

            border-radius: 9px;

            background: #f8f8fa;

            margin-bottom: 15px;
        }


        .security-note i {

            color: #ef003c;

            margin-top: 2px;

            font-size: 11px;
        }


        .security-note strong {

            display: block;

            margin-bottom: 3px;

            font-size: 9px;
        }


        .security-note p {

            margin: 0;

            color: #999;

            font-size: 7px;

            line-height: 1.5;
        }


        /* =====================================================
           FOOTER
        ====================================================== */

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


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1050px) {

            .profile-grid {

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


            .profile-hero {

                flex-direction: column;

                align-items: flex-start;
            }


            .status-card {

                width: 100%;
            }

        }


        @media (max-width: 520px) {

            .top-user {

                display: none;
            }


            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full {

                grid-column: auto;
            }


            .profile-user {

                align-items: flex-start;
            }


            .large-avatar {

                width: 58px;
                height: 58px;

                font-size: 20px;
            }


            .detail-row {

                align-items: flex-start;

                flex-direction: column;

                gap: 5px;
            }


            .detail-value {

                text-align: left;
            }

        }

    </style>

</head>


<body>
<?php include __DIR__ . '/restaurant-sidebar.php'; ?>

<div class="layout">


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
                    Profile
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


            <!-- PAGE HEADING -->

            <div class="page-heading">

                <small>
                    ACCOUNT SETTINGS
                </small>

                <h1>
                    My Profile
                </h1>

                <p>
                    View and manage your restaurant partner account information.
                </p>

            </div>


            <!-- ALERT -->

            <?php if ($message !== ''): ?>

                <div
                    class="
                        alert
                        <?= $messageType === 'success'
                            ? 'success'
                            : 'error'
                        ?>
                    "
                >

                    <i
                        class="
                            fas
                            <?= $messageType === 'success'
                                ? 'fa-circle-check'
                                : 'fa-circle-exclamation'
                            ?>
                        "
                    ></i>

                    <?= h($message) ?>

                </div>

            <?php endif; ?>


            <!-- PROFILE HERO -->

            <section class="profile-hero">


                <div class="profile-user">

                    <div class="large-avatar">

                        <?= h($initial) ?>

                    </div>


                    <div>

                        <h2>
                            <?= h($ownerName) ?>
                        </h2>

                        <p>
                            <?= h($ownerEmail) ?>
                        </p>

                    </div>

                </div>


                <div
                    class="
                        status-card
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


                    <small>

                        <?php if ($isApproved): ?>

                            Your restaurant partner account is approved and active.

                        <?php elseif ($ownerStatus === 'pending'): ?>

                            Your account is waiting for administrator approval.

                        <?php elseif ($ownerStatus === 'blocked'): ?>

                            Your account is currently blocked.

                        <?php else: ?>

                            Your account is currently inactive.

                        <?php endif; ?>

                    </small>

                </div>


            </section>


            <!-- PROFILE GRID -->

            <div class="profile-grid">


                <!-- =================================================
                     LEFT
                ================================================== -->

                <div>


                    <!-- PERSONAL INFORMATION -->

                    <section class="panel">


                        <div class="panel-header">

                            <strong>
                                Personal Information
                            </strong>

                            <span>
                                Account details
                            </span>

                        </div>


                        <div class="panel-body">


                            <form
                                method="POST"
                                action=""
                            >


                                <div class="form-grid">


                                    <!-- FULL NAME -->

                                    <div class="form-group">

                                        <label for="full_name">
                                            Full Name
                                        </label>


                                        <div class="input-wrap">

                                            <i class="fas fa-user"></i>

                                            <input
                                                type="text"
                                                id="full_name"
                                                name="full_name"
                                                class="form-control"
                                                value="<?= h($ownerName) ?>"
                                                maxlength="100"
                                                required
                                            >

                                        </div>

                                    </div>


                                    <!-- PHONE -->

                                    <div class="form-group">

                                        <label for="phone">
                                            Phone Number
                                        </label>


                                        <div class="input-wrap">

                                            <i class="fas fa-phone"></i>

                                            <input
                                                type="tel"
                                                id="phone"
                                                name="phone"
                                                class="form-control"
                                                value="<?= h($ownerPhone) ?>"
                                                maxlength="30"
                                                required
                                            >

                                        </div>

                                    </div>


                                    <!-- EMAIL -->

                                    <div class="form-group">

                                        <label for="email">
                                            Email Address
                                        </label>


                                        <div class="input-wrap">

                                            <i class="fas fa-envelope"></i>

                                            <input
                                                type="email"
                                                id="email"
                                                class="form-control readonly"
                                                value="<?= h($ownerEmail) ?>"
                                                readonly
                                            >

                                        </div>

                                        <div class="field-note">

                                            Email address cannot be changed from the owner profile.

                                        </div>

                                    </div>


                                    <!-- RESTAURANT -->

                                    <div class="form-group">

                                        <label for="restaurant_name">
                                            Restaurant Name
                                        </label>


                                        <div class="input-wrap">

                                            <i class="fas fa-store"></i>

                                            <input
                                                type="text"
                                                id="restaurant_name"
                                                class="form-control readonly"
                                                value="<?= h($restaurantName !== '' ? $restaurantName : 'Not set') ?>"
                                                readonly
                                            >

                                        </div>

                                        <div class="field-note">

                                            Restaurant name can be changed from Manage Restaurant.

                                        </div>

                                    </div>


                                </div>


                                <button
                                    type="submit"
                                    name="update_profile"
                                    value="1"
                                    class="save-button"
                                >

                                    <i class="fas fa-save"></i>

                                    Save Changes

                                </button>


                            </form>


                        </div>

                    </section>


                    <!-- SECURITY -->

                    <section class="panel">


                        <div class="panel-header">

                            <strong>
                                Change Password
                            </strong>

                            <span>
                                Security
                            </span>

                        </div>


                        <div class="panel-body">


                            <div class="security-note">

                                <i class="fas fa-shield-halved"></i>


                                <div>

                                    <strong>
                                        Keep your account secure
                                    </strong>

                                    <p>
                                        Use a strong password with at least 6 characters.
                                    </p>

                                </div>

                            </div>


                            <form
                                method="POST"
                                action=""
                            >


                                <!-- CURRENT PASSWORD -->

                                <div class="form-group">

                                    <label for="current_password">
                                        Current Password
                                    </label>


                                    <div class="password-field">

                                        <input
                                            type="password"
                                            id="current_password"
                                            name="current_password"
                                            class="form-control"
                                            placeholder="Enter current password"
                                            autocomplete="current-password"
                                            required
                                        >


                                        <button
                                            type="button"
                                            class="password-toggle"
                                            onclick="togglePassword('current_password', this)"
                                        >

                                            <i class="fas fa-eye"></i>

                                        </button>

                                    </div>

                                </div>


                                <div class="form-grid">


                                    <!-- NEW PASSWORD -->

                                    <div class="form-group">

                                        <label for="new_password">
                                            New Password
                                        </label>


                                        <div class="password-field">

                                            <input
                                                type="password"
                                                id="new_password"
                                                name="new_password"
                                                class="form-control"
                                                placeholder="Enter new password"
                                                minlength="6"
                                                autocomplete="new-password"
                                                required
                                            >


                                            <button
                                                type="button"
                                                class="password-toggle"
                                                onclick="togglePassword('new_password', this)"
                                            >

                                                <i class="fas fa-eye"></i>

                                            </button>

                                        </div>

                                    </div>


                                    <!-- CONFIRM -->

                                    <div class="form-group">

                                        <label for="confirm_password">
                                            Confirm New Password
                                        </label>


                                        <div class="password-field">

                                            <input
                                                type="password"
                                                id="confirm_password"
                                                name="confirm_password"
                                                class="form-control"
                                                placeholder="Confirm new password"
                                                minlength="6"
                                                autocomplete="new-password"
                                                required
                                            >


                                            <button
                                                type="button"
                                                class="password-toggle"
                                                onclick="togglePassword('confirm_password', this)"
                                            >

                                                <i class="fas fa-eye"></i>

                                            </button>

                                        </div>

                                    </div>


                                </div>


                                <button
                                    type="submit"
                                    name="change_password"
                                    value="1"
                                    class="save-button"
                                >

                                    <i class="fas fa-key"></i>

                                    Change Password

                                </button>


                            </form>


                        </div>

                    </section>


                </div>


                <!-- =================================================
                     RIGHT
                ================================================== -->

                <div>


                    <!-- ACCOUNT INFORMATION -->

                    <section class="panel">


                        <div class="panel-header">

                            <strong>
                                Account Information
                            </strong>

                            <span>
                                Humsafar
                            </span>

                        </div>


                        <div class="panel-body">


                            <div class="detail-row">

                                <span class="detail-label">
                                    Account ID
                                </span>

                                <span class="detail-value">
                                    #<?= h($ownerId) ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Account Status
                                </span>

                                <span
                                    class="
                                        detail-value
                                        <?= $isApproved
                                            ? 'green'
                                            : 'red'
                                        ?>
                                    "
                                >
                                    <?= h($statusLabel) ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Registered
                                </span>

                                <span class="detail-value">
                                    <?= h($registeredDate) ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Email
                                </span>

                                <span class="detail-value">
                                    <?= h($ownerEmail) ?>
                                </span>

                            </div>


                            <div class="detail-row">

                                <span class="detail-label">
                                    Phone
                                </span>

                                <span class="detail-value">
                                    <?= h($ownerPhone !== '' ? $ownerPhone : 'Not set') ?>
                                </span>

                            </div>


                        </div>

                    </section>


                    <!-- RESTAURANT -->

                    <section class="panel">


                        <div class="panel-header">

                            <strong>
                                My Restaurant
                            </strong>

                            <span>
                                Restaurant profile
                            </span>

                        </div>


                        <div class="panel-body">


                            <div class="restaurant-card">


                                <div class="restaurant-card-top">


                                    <div class="restaurant-icon">

                                        <i class="fas fa-store"></i>

                                    </div>


                                    <div>

                                        <h3>
                                            <?= h(
                                                $restaurantName !== ''
                                                    ? $restaurantName
                                                    : 'Restaurant Not Set'
                                            ) ?>
                                        </h3>

                                        <p>
                                            Restaurant Partner
                                        </p>

                                    </div>


                                </div>


                                <?php if ($isApproved): ?>

                                    <a
                                        href="restaurant-owner-manage.php"
                                        class="manage-button"
                                    >

                                        <i class="fas fa-pen"></i>

                                        Manage Restaurant

                                    </a>

                                <?php else: ?>

                                    <div
                                        class="manage-button"
                                        style="
                                            opacity:.55;
                                            cursor:not-allowed;
                                        "
                                    >

                                        <i class="fas fa-lock"></i>

                                        Available After Approval

                                    </div>

                                <?php endif; ?>


                            </div>


                        </div>

                    </section>


                    <!-- SUPPORT -->

                    <section
                        class="panel"
                        id="support"
                    >


                        <div class="panel-header">

                            <strong>
                                Need Help?
                            </strong>

                            <span>
                                Support
                            </span>

                        </div>


                        <div class="panel-body">


                            <div class="security-note">

                                <i class="fas fa-circle-question"></i>


                                <div>

                                    <strong>
                                        Restaurant Partner Support
                                    </strong>

                                    <p>
                                        If you have an issue with your account,
                                        restaurant approval, menu or orders,
                                        please contact Humsafar administration.
                                    </p>

                                </div>

                            </div>


                        </div>

                    </section>


                </div>


            </div>


            <!-- FOOTER -->

            <div class="footer">

                © <?= date('Y') ?>

                <strong>
                    Humsafar
                </strong>

                Restaurant Partner Portal.

            </div>


        </div>


    </main>


</div>


<script>

/*
|--------------------------------------------------------------------------
| PASSWORD TOGGLE
|--------------------------------------------------------------------------
*/

function togglePassword(
    inputId,
    button
) {

    const input =
        document.getElementById(inputId);

    const icon =
        button.querySelector('i');


    if (
        input.type === 'password'
    ) {

        input.type = 'text';

        icon.classList.remove(
            'fa-eye'
        );

        icon.classList.add(
            'fa-eye-slash'
        );

    } else {

        input.type = 'password';

        icon.classList.remove(
            'fa-eye-slash'
        );

        icon.classList.add(
            'fa-eye'
        );

    }

}

</script>


</body>

</html>