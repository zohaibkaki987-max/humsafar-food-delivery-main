<?php

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CUSTOMER ID
|--------------------------------------------------------------------------
| Existing project login flow:
| customer_id preferred, then user_id.
|--------------------------------------------------------------------------
*/

$customerId = 0;

if (
    isset($_SESSION['customer_id']) &&
    (int) $_SESSION['customer_id'] > 0
) {
    $customerId = (int) $_SESSION['customer_id'];

} elseif (
    isset($_SESSION['user_id']) &&
    (int) $_SESSION['user_id'] > 0
) {
    $customerId = (int) $_SESSION['user_id'];
}


if ($customerId <= 0) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/customer-header.php';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['address_csrf_token'])) {

    $_SESSION['address_csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['address_csrf_token'];


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';


/*
|--------------------------------------------------------------------------
| FORM DATA
|--------------------------------------------------------------------------
*/

$form = [
    'id' => 0,
    'address_title' => 'Home',
    'full_name' => '',
    'phone' => '',
    'address' => '',
    'area' => '',
    'city' => 'Hyderabad',
    'delivery_instructions' => '',
    'latitude' => '',
    'longitude' => '',
    'is_default' => 0
];


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        $errorMessage =
            'Security verification failed. Please refresh the page.';

    } else {

        $action =
            $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | SAVE / UPDATE ADDRESS
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_address') {

            $addressId =
                isset($_POST['address_id']) &&
                ctype_digit(
                    (string) $_POST['address_id']
                )
                ? (int) $_POST['address_id']
                : 0;


            $addressTitle =
                trim(
                    $_POST['address_title'] ?? 'Home'
                );


            $fullName =
                trim(
                    $_POST['full_name'] ?? ''
                );


            $phone =
                trim(
                    $_POST['phone'] ?? ''
                );


            $address =
                trim(
                    $_POST['address'] ?? ''
                );


            $area =
                trim(
                    $_POST['area'] ?? ''
                );


            $city =
                trim(
                    $_POST['city'] ?? ''
                );


            $deliveryInstructions =
                trim(
                    $_POST['delivery_instructions'] ?? ''
                );


            $latitudeInput =
                trim(
                    $_POST['latitude'] ?? ''
                );


            $longitudeInput =
                trim(
                    $_POST['longitude'] ?? ''
                );


            $isDefault =
                isset($_POST['is_default'])
                ? 1
                : 0;


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if ($fullName === '') {

                $errorMessage =
                    'Please enter your full name.';

            } elseif ($phone === '') {

                $errorMessage =
                    'Please enter your phone number.';

            } elseif ($address === '') {

                $errorMessage =
                    'Please enter your complete address.';

            } elseif ($city === '') {

                $errorMessage =
                    'Please enter your city.';

            } else {


                $latitude =
                    $latitudeInput !== ''
                    ? (float) $latitudeInput
                    : null;


                $longitude =
                    $longitudeInput !== ''
                    ? (float) $longitudeInput
                    : null;


                /*
                |--------------------------------------------------------------------------
                | REMOVE OLD DEFAULT
                |--------------------------------------------------------------------------
                */

                if ($isDefault === 1) {

                    $stmt =
                        $conn->prepare("
                            UPDATE customer_addresses
                            SET is_default = 0
                            WHERE user_id = ?
                        ");

                    if ($stmt) {

                        $stmt->bind_param(
                            "i",
                            $customerId
                        );

                        $stmt->execute();

                        $stmt->close();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE EXISTING ADDRESS
                |--------------------------------------------------------------------------
                */

                if ($addressId > 0) {

                    $stmt =
                        $conn->prepare("
                            UPDATE customer_addresses
                            SET
                                address_title = ?,
                                full_name = ?,
                                phone = ?,
                                address = ?,
                                area = ?,
                                city = ?,
                                delivery_instructions = ?,
                                latitude = ?,
                                longitude = ?,
                                is_default = ?
                            WHERE id = ?
                            AND user_id = ?
                        ");


                    if (!$stmt) {

                        $errorMessage =
                            'Database error: ' .
                            $conn->error;

                    } else {

                        $stmt->bind_param(
                            "sssssssddiii",
                            $addressTitle,
                            $fullName,
                            $phone,
                            $address,
                            $area,
                            $city,
                            $deliveryInstructions,
                            $latitude,
                            $longitude,
                            $isDefault,
                            $addressId,
                            $customerId
                        );


                        if ($stmt->execute()) {

                            $successMessage =
                                'Address updated successfully.';

                        } else {

                            $errorMessage =
                                'Unable to update address: ' .
                                $stmt->error;
                        }


                        $stmt->close();
                    }


                /*
                |--------------------------------------------------------------------------
                | INSERT NEW ADDRESS
                |--------------------------------------------------------------------------
                */

                } else {

                    $stmt =
                        $conn->prepare("
                            INSERT INTO customer_addresses
                            (
                                user_id,
                                address_title,
                                full_name,
                                phone,
                                address,
                                area,
                                city,
                                delivery_instructions,
                                latitude,
                                longitude,
                                is_default
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
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
                            "issssssddi",
                            $customerId,
                            $addressTitle,
                            $fullName,
                            $phone,
                            $address,
                            $area,
                            $city,
                            $deliveryInstructions,
                            $latitude,
                            $longitude,
                            $isDefault
                        );


                        if ($stmt->execute()) {

                            $successMessage =
                                'Address saved successfully.';

                        } else {

                            $errorMessage =
                                'Unable to save address: ' .
                                $stmt->error;
                        }


                        $stmt->close();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | IF THERE IS NO DEFAULT ADDRESS
                |--------------------------------------------------------------------------
                */

                if ($successMessage !== '') {

                    $stmt =
                        $conn->prepare("
                            SELECT id
                            FROM customer_addresses
                            WHERE user_id = ?
                            AND is_default = 1
                            LIMIT 1
                        ");


                    $hasDefault = false;


                    if ($stmt) {

                        $stmt->bind_param(
                            "i",
                            $customerId
                        );

                        $stmt->execute();

                        $result =
                            $stmt->get_result();

                        $hasDefault =
                            (bool)
                            $result->fetch_assoc();

                        $stmt->close();
                    }


                    /*
                    | First saved address becomes default
                    */

                    if (!$hasDefault) {

                        $stmt =
                            $conn->prepare("
                                UPDATE customer_addresses
                                SET is_default = 1
                                WHERE user_id = ?
                                ORDER BY id ASC
                                LIMIT 1
                            ");


                        if ($stmt) {

                            $stmt->bind_param(
                                "i",
                                $customerId
                            );

                            $stmt->execute();

                            $stmt->close();
                        }
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SET DEFAULT ADDRESS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'set_default') {

            $addressId =
                isset($_POST['address_id']) &&
                ctype_digit(
                    (string) $_POST['address_id']
                )
                ? (int) $_POST['address_id']
                : 0;


            if ($addressId > 0) {


                /*
                | Remove current default
                */

                $stmt =
                    $conn->prepare("
                        UPDATE customer_addresses
                        SET is_default = 0
                        WHERE user_id = ?
                    ");


                if ($stmt) {

                    $stmt->bind_param(
                        "i",
                        $customerId
                    );

                    $stmt->execute();

                    $stmt->close();
                }


                /*
                | Set selected address as default
                */

                $stmt =
                    $conn->prepare("
                        UPDATE customer_addresses
                        SET is_default = 1
                        WHERE id = ?
                        AND user_id = ?
                    ");


                if ($stmt) {

                    $stmt->bind_param(
                        "ii",
                        $addressId,
                        $customerId
                    );


                    if ($stmt->execute()) {

                        $successMessage =
                            'Default address updated successfully.';

                    } else {

                        $errorMessage =
                            'Unable to set default address.';
                    }


                    $stmt->close();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE ADDRESS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete_address') {

            $addressId =
                isset($_POST['address_id']) &&
                ctype_digit(
                    (string) $_POST['address_id']
                )
                ? (int) $_POST['address_id']
                : 0;


            if ($addressId > 0) {


                /*
                | Check if address is default
                */

                $wasDefault = 0;


                $stmt =
                    $conn->prepare("
                        SELECT is_default
                        FROM customer_addresses
                        WHERE id = ?
                        AND user_id = ?
                        LIMIT 1
                    ");


                if ($stmt) {

                    $stmt->bind_param(
                        "ii",
                        $addressId,
                        $customerId
                    );

                    $stmt->execute();

                    $result =
                        $stmt->get_result();

                    $row =
                        $result->fetch_assoc();


                    if ($row) {

                        $wasDefault =
                            (int)
                            $row['is_default'];
                    }


                    $stmt->close();
                }


                /*
                | Delete
                */

                $stmt =
                    $conn->prepare("
                        DELETE FROM customer_addresses
                        WHERE id = ?
                        AND user_id = ?
                    ");


                if ($stmt) {

                    $stmt->bind_param(
                        "ii",
                        $addressId,
                        $customerId
                    );


                    if ($stmt->execute()) {

                        $successMessage =
                            'Address deleted successfully.';

                    } else {

                        $errorMessage =
                            'Unable to delete address: ' .
                            $stmt->error;
                    }


                    $stmt->close();
                }


                /*
                | Select another address as default
                */

                if (
                    $successMessage !== '' &&
                    $wasDefault === 1
                ) {

                    $stmt =
                        $conn->prepare("
                            UPDATE customer_addresses
                            SET is_default = 1
                            WHERE user_id = ?
                            ORDER BY id ASC
                            LIMIT 1
                        ");


                    if ($stmt) {

                        $stmt->bind_param(
                            "i",
                            $customerId
                        );

                        $stmt->execute();

                        $stmt->close();
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD EDIT ADDRESS
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit']) &&
    ctype_digit(
        (string) $_GET['edit']
    )
) {

    $editId =
        (int) $_GET['edit'];


    $stmt =
        $conn->prepare("
            SELECT
                id,
                address_title,
                full_name,
                phone,
                address,
                area,
                city,
                delivery_instructions,
                latitude,
                longitude,
                is_default
            FROM customer_addresses
            WHERE id = ?
            AND user_id = ?
            LIMIT 1
        ");


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $editId,
            $customerId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $editData =
            $result->fetch_assoc();

        $stmt->close();


        if ($editData) {

            $form = $editData;
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD SAVED ADDRESSES
|--------------------------------------------------------------------------
*/

$addresses = [];


$stmt =
    $conn->prepare("
        SELECT
            id,
            user_id,
            address_title,
            full_name,
            phone,
            address,
            area,
            city,
            delivery_instructions,
            latitude,
            longitude,
            is_default,
            created_at
        FROM customer_addresses
        WHERE user_id = ?
        ORDER BY
            is_default DESC,
            id DESC
    ");


if ($stmt) {

    $stmt->bind_param(
        "i",
        $customerId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    while (
        $row =
        $result->fetch_assoc()
    ) {

        $addresses[] =
            $row;
    }


    $stmt->close();
}


$isEditing =
    (int) $form['id'] > 0;

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
        My Addresses - Humsafar
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            background: #f7f7f8;
            color: #333;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        .addresses-page {
            max-width: 1150px;
            margin: 35px auto;
            padding: 0 20px 60px;
        }


        /* PAGE TITLE */

        .page-title {
            margin-bottom: 25px;
        }


        .page-title h1 {
            margin: 0;
            color: #ed0038;
            font-size: 32px;
            font-weight: 800;
        }


        .page-title p {
            margin: 7px 0 0;
            color: #ed0038;
            font-size: 14px;
            font-weight: 600;
        }


        /* ALERTS */

        .alert {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }


        .alert-success {
            background: #eaf8ef;
            color: #187943;
            border: 1px solid #c7ead3;
        }


        .alert-error {
            background: #fff0f2;
            color: #c6283d;
            border: 1px solid #ffd1d8;
        }


        /* MAIN GRID */

        .address-grid {
            display: grid;
            grid-template-columns: 390px 1fr;
            gap: 22px;
            align-items: start;
        }


        /* CARD */

        .card {
            background: #fff;
            border: 1px solid #ebebeb;
            border-radius: 18px;
            box-shadow:
                0 7px 25px rgba(0,0,0,.05);
            overflow: hidden;
        }


        .card-title {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
        }


        .card-title h2,
        .saved-header h2 {
            margin: 0;
            color: #222;
            font-size: 18px;
            font-weight: 800;
        }


        .card-title span {
            display: block;
            margin-top: 4px;
            color: #999;
            font-size: 11px;
        }


        /* FORM */

        .form {
            padding: 20px;
        }


        .form-group {
            margin-bottom: 15px;
        }


        .form-label {
            display: block;
            margin-bottom: 7px;
            color: #444;
            font-size: 12px;
            font-weight: 700;
        }


        .required {
            color: #ed0038;
        }


        .input {
            width: 100%;
            min-height: 44px;
            padding: 11px 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 9px;
            outline: none;
            color: #333;
            font-size: 13px;
        }


        .input:focus {
            border-color: #ed0038;
            box-shadow:
                0 0 0 3px
                rgba(237,0,56,.08);
        }


        textarea.input {
            min-height: 82px;
            resize: vertical;
        }


        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }


        /* ADDRESS TYPES */

        .types {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 7px;
        }


        .types input {
            display: none;
        }


        .types label {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 1px solid #e4e4e4;
            border-radius: 9px;
            background: #fafafa;
            color: #777;
            cursor: pointer;
            font-size: 10px;
            font-weight: 700;
        }


        .types input:checked + label {
            color: #ed0038;
            background: #fff1f5;
            border-color: #ed0038;
        }


        /* DEFAULT */

        .default-option {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 9px;
        }


        .default-option input {
            width: 16px;
            height: 16px;
            accent-color: #ed0038;
        }


        .default-option label {
            color: #555;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }


        /* BUTTONS */

        .form-buttons {
            display: flex;
            gap: 8px;
            margin-top: 18px;
        }


        .btn {
            min-height: 43px;
            padding: 10px 15px;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }


        .btn-save {
            flex: 1;
            background: #ed0038;
            color: #fff;
        }


        .btn-save:hover {
            background: #d90032;
        }


        .btn-cancel {
            background: #eee;
            color: #555;
        }


        /* SAVED HEADER */

        .saved-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
        }


        .count {
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff1f5;
            color: #ed0038;
            font-size: 10px;
            font-weight: 700;
        }


        /* SAVED LIST */

        .saved-list {
            padding: 15px;
        }


        .address-box {
            padding: 17px;
            margin-bottom: 12px;
            border: 1px solid #e8e8e8;
            border-radius: 14px;
            background: #fff;
        }


        .address-box:last-child {
            margin-bottom: 0;
        }


        .address-box.default {
            border-color: #f2a7b9;
            background:
                linear-gradient(
                    135deg,
                    #fff8fa,
                    #fff
                );
        }


        .address-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 13px;
        }


        .address-name-wrap {
            display: flex;
            align-items: center;
            gap: 9px;
        }


        .address-icon {
            width: 39px;
            height: 39px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #fff1f5;
            color: #ed0038;
        }


        .address-title {
            color: #222;
            font-size: 14px;
            font-weight: 800;
        }


        .customer-name {
            margin-top: 3px;
            color: #999;
            font-size: 10px;
        }


        .default-badge {
            padding: 5px 8px;
            border-radius: 20px;
            background: #ed0038;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }


        .address-info {
            padding-left: 48px;
        }


        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 8px;
            color: #555;
            font-size: 11px;
            line-height: 1.5;
        }


        .info-row i {
            width: 14px;
            flex-shrink: 0;
            color: #ed0038;
        }


        .instructions {
            margin-top: 10px;
            padding: 9px;
            border-radius: 8px;
            background: #fafafa;
            color: #777;
            font-size: 10px;
            line-height: 1.5;
        }


        /* ACTIONS */

        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            padding-top: 13px;
            margin-top: 13px;
            border-top: 1px solid #eee;
        }


        .action-btn {
            min-height: 33px;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 9px;
            font-weight: 700;
        }


        .edit {
            background: #f2f2f2;
            color: #555;
            border: 1px solid #e5e5e5;
        }


        .default-btn {
            background: #fff1f5;
            color: #ed0038;
            border: 1px solid #ffd0da;
        }


        .delete {
            margin-left: auto;
            background: #fff0f1;
            color: #c92c3d;
            border: 1px solid #ffd1d6;
        }


        /* EMPTY */

        .empty {
            padding: 55px 20px;
            text-align: center;
        }


        .empty-icon {
            width: 65px;
            height: 65px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #fff1f5;
            color: #ed0038;
            font-size: 24px;
        }


        .empty h3 {
            margin: 0;
            color: #333;
            font-size: 16px;
        }


        .empty p {
            max-width: 340px;
            margin: 7px auto 0;
            color: #888;
            font-size: 11px;
            line-height: 1.5;
        }


        .note {
            margin: 0 15px 15px;
            padding: 12px;
            border-radius: 10px;
            background: #fafafa;
            color: #777;
            font-size: 10px;
            line-height: 1.5;
        }


        /* RESPONSIVE */

        @media (max-width: 900px) {

            .address-grid {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 600px) {

            .addresses-page {
                margin-top: 25px;
                padding: 0 12px 40px;
            }


            .page-title h1 {
                font-size: 27px;
            }


            .two-col {
                grid-template-columns: 1fr;
            }


            .address-info {
                padding-left: 0;
            }


            .delete {
                margin-left: 0;
            }
        }

    </style>

</head>


<body>


<main class="addresses-page">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <h1>
            My Addresses
        </h1>

        <p>
            Add, edit and manage your delivery addresses
        </p>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if ($successMessage !== ''): ?>

        <div class="alert alert-success">

            <?php
            echo e($successMessage);
            ?>

        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-error">

            <?php
            echo e($errorMessage);
            ?>

        </div>

    <?php endif; ?>


    <div class="address-grid">


        <!-- =====================================================
             LEFT - ADD / EDIT ADDRESS
        ====================================================== -->

        <section class="card">


            <div class="card-title">

                <h2>

                    <?php
                    echo $isEditing
                        ? 'Edit Address'
                        : 'Add New Address';
                    ?>

                </h2>

                <span>
                    Your address will be available in Cart and Checkout
                </span>

            </div>


            <form
                method="POST"
                class="form"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                    echo e($csrfToken);
                    ?>"
                >


                <input
                    type="hidden"
                    name="action"
                    value="save_address"
                >


                <input
                    type="hidden"
                    name="address_id"
                    value="<?php
                    echo (int) $form['id'];
                    ?>"
                >


                <!-- ADDRESS TYPE -->

                <div class="form-group">

                    <label class="form-label">
                        Address Type
                    </label>


                    <div class="types">


                        <div>

                            <input
                                type="radio"
                                id="home"
                                name="address_title"
                                value="Home"
                                <?php
                                echo
                                    $form['address_title']
                                    === 'Home'
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <label for="home">
                                Home
                            </label>

                        </div>


                        <div>

                            <input
                                type="radio"
                                id="work"
                                name="address_title"
                                value="Work"
                                <?php
                                echo
                                    $form['address_title']
                                    === 'Work'
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <label for="work">
                                Work
                            </label>

                        </div>


                        <div>

                            <input
                                type="radio"
                                id="other"
                                name="address_title"
                                value="Other"
                                <?php
                                echo
                                    !in_array(
                                        $form['address_title'],
                                        [
                                            'Home',
                                            'Work'
                                        ],
                                        true
                                    )
                                    ? 'checked'
                                    : '';
                                ?>
                            >

                            <label for="other">
                                Other
                            </label>

                        </div>


                    </div>

                </div>


                <!-- NAME / PHONE -->

                <div class="two-col">


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="full_name"
                        >

                            Full Name
                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="input"
                            maxlength="150"
                            required
                            value="<?php
                            echo e(
                                $form['full_name']
                            );
                            ?>"
                            placeholder="Full name"
                        >

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="phone"
                        >

                            Phone
                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="input"
                            maxlength="30"
                            required
                            value="<?php
                            echo e(
                                $form['phone']
                            );
                            ?>"
                            placeholder="03XXXXXXXXX"
                        >

                    </div>


                </div>


                <!-- ADDRESS -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="address"
                    >

                        Complete Address
                        <span class="required">*</span>

                    </label>


                    <textarea
                        id="address"
                        name="address"
                        class="input"
                        maxlength="255"
                        required
                        placeholder="House number, street, road..."
                    ><?php
                    echo e(
                        $form['address']
                    );
                    ?></textarea>

                </div>


                <!-- AREA / CITY -->

                <div class="two-col">


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="area"
                        >
                            Area
                        </label>


                        <input
                            type="text"
                            id="area"
                            name="area"
                            class="input"
                            maxlength="150"
                            value="<?php
                            echo e(
                                $form['area']
                            );
                            ?>"
                            placeholder="Area"
                        >

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="city"
                        >

                            City
                            <span class="required">*</span>

                        </label>


                        <input
                            type="text"
                            id="city"
                            name="city"
                            class="input"
                            maxlength="100"
                            required
                            value="<?php
                            echo e(
                                $form['city']
                            );
                            ?>"
                            placeholder="City"
                        >

                    </div>


                </div>


                <!-- DELIVERY INSTRUCTIONS -->

                <div class="form-group">

                    <label
                        class="form-label"
                        for="delivery_instructions"
                    >

                        Delivery Instructions

                    </label>


                    <textarea
                        id="delivery_instructions"
                        name="delivery_instructions"
                        class="input"
                        placeholder="Example: Call me when you arrive..."
                    ><?php
                    echo e(
                        $form[
                            'delivery_instructions'
                        ]
                    );
                    ?></textarea>

                </div>


                <!-- LOCATION DATA -->

                <input
                    type="hidden"
                    name="latitude"
                    value="<?php
                    echo e(
                        $form['latitude']
                    );
                    ?>"
                >


                <input
                    type="hidden"
                    name="longitude"
                    value="<?php
                    echo e(
                        $form['longitude']
                    );
                    ?>"
                >


                <!-- DEFAULT -->

                <div class="default-option">


                    <input
                        type="checkbox"
                        id="is_default"
                        name="is_default"
                        value="1"
                        <?php
                        echo
                            (int)
                            $form['is_default'] === 1
                            ? 'checked'
                            : '';
                        ?>
                    >


                    <label for="is_default">

                        Make this my default address

                    </label>


                </div>


                <!-- BUTTONS -->

                <div class="form-buttons">


                    <button
                        type="submit"
                        class="btn btn-save"
                    >

                        <?php
                        echo $isEditing
                            ? 'Update Address'
                            : 'Save Address';
                        ?>

                    </button>


                    <?php if ($isEditing): ?>

                        <a
                            href="manage-addresses.php"
                            class="btn btn-cancel"
                        >
                            Cancel
                        </a>

                    <?php endif; ?>


                </div>


            </form>

        </section>


        <!-- =====================================================
             RIGHT - SAVED ADDRESSES
        ====================================================== -->

        <section class="card">


            <div class="saved-header">


                <h2>
                    Saved Addresses
                </h2>


                <span class="count">

                    <?php
                    echo count($addresses);
                    ?>

                    Address<?php
                    echo
                        count($addresses) === 1
                        ? ''
                        : 'es';
                    ?>

                </span>


            </div>


            <?php if (empty($addresses)): ?>


                <!-- EMPTY STATE -->

                <div class="empty">


                    <div class="empty-icon">
                        📍
                    </div>


                    <h3>
                        No Saved Addresses
                    </h3>


                    <p>
                        Add your first delivery address
                        using the form on the left.
                    </p>


                </div>


            <?php else: ?>


                <div class="saved-list">


                    <?php foreach (
                        $addresses
                        as $saved
                    ): ?>


                        <div
                            class="
                                address-box
                                <?php
                                echo
                                    (int)
                                    $saved['is_default'] === 1
                                    ? 'default'
                                    : '';
                                ?>
                            "
                        >


                            <!-- ADDRESS HEADER -->

                            <div class="address-head">


                                <div
                                    class="
                                        address-name-wrap
                                    "
                                >


                                    <div
                                        class="
                                            address-icon
                                        "
                                    >

                                        <?php

                                        $title =
                                            strtolower(
                                                $saved[
                                                    'address_title'
                                                ]
                                            );


                                        if (
                                            $title === 'home'
                                        ) {

                                            echo '🏠';

                                        } elseif (
                                            $title === 'work'
                                        ) {

                                            echo '💼';

                                        } else {

                                            echo '📍';
                                        }

                                        ?>

                                    </div>


                                    <div>


                                        <div
                                            class="
                                                address-title
                                            "
                                        >

                                            <?php
                                            echo e(
                                                $saved[
                                                    'address_title'
                                                ]
                                            );
                                            ?>

                                        </div>


                                        <div
                                            class="
                                                customer-name
                                            "
                                        >

                                            <?php
                                            echo e(
                                                $saved[
                                                    'full_name'
                                                ]
                                            );
                                            ?>

                                        </div>


                                    </div>


                                </div>


                                <?php if (
                                    (int)
                                    $saved[
                                        'is_default'
                                    ] === 1
                                ): ?>


                                    <span
                                        class="
                                            default-badge
                                        "
                                    >
                                        Default
                                    </span>


                                <?php endif; ?>


                            </div>


                            <!-- ADDRESS INFORMATION -->

                            <div class="address-info">


                                <div class="info-row">

                                    <i>
                                        📍
                                    </i>


                                    <span>


                                        <?php
                                        echo e(
                                            $saved[
                                                'address'
                                            ]
                                        );
                                        ?>


                                        <?php if (
                                            !empty(
                                                $saved['area']
                                            )
                                        ): ?>

                                            ,
                                            <?php
                                            echo e(
                                                $saved['area']
                                            );
                                            ?>

                                        <?php endif; ?>


                                        ,
                                        <?php
                                        echo e(
                                            $saved['city']
                                        );
                                        ?>


                                    </span>


                                </div>


                                <div class="info-row">

                                    <i>
                                        📞
                                    </i>


                                    <span>

                                        <?php
                                        echo e(
                                            $saved['phone']
                                        );
                                        ?>

                                    </span>


                                </div>


                                <?php if (
                                    !empty(
                                        $saved[
                                            'delivery_instructions'
                                        ]
                                    )
                                ): ?>


                                    <div
                                        class="
                                            instructions
                                        "
                                    >

                                        <strong>
                                            Rider Note:
                                        </strong>


                                        <?php
                                        echo e(
                                            $saved[
                                                'delivery_instructions'
                                            ]
                                        );
                                        ?>


                                    </div>


                                <?php endif; ?>


                            </div>


                            <!-- ACTIONS -->

                            <div class="actions">


                                <!-- EDIT -->

                                <a
                                    href="
                                        manage-addresses.php?edit=<?php
                                        echo (int)
                                            $saved['id'];
                                        ?>"
                                    class="
                                        action-btn
                                        edit
                                    "
                                >

                                    Edit

                                </a>


                                <!-- SET DEFAULT -->

                                <?php if (
                                    (int)
                                    $saved[
                                        'is_default'
                                    ] !== 1
                                ): ?>


                                    <form
                                        method="POST"
                                        style="margin:0;"
                                    >


                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php
                                            echo e(
                                                $csrfToken
                                            );
                                            ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="set_default"
                                        >


                                        <input
                                            type="hidden"
                                            name="address_id"
                                            value="<?php
                                            echo (int)
                                                $saved['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="
                                                action-btn
                                                default-btn
                                            "
                                        >

                                            Set Default

                                        </button>


                                    </form>


                                <?php endif; ?>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    style="margin:0;"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to delete this address?'
                                        );
                                    "
                                >


                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php
                                        echo e(
                                            $csrfToken
                                        );
                                        ?>"
                                    >


                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete_address"
                                    >


                                    <input
                                        type="hidden"
                                        name="address_id"
                                        value="<?php
                                        echo (int)
                                            $saved['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="
                                            action-btn
                                            delete
                                        "
                                    >

                                        Delete

                                    </button>


                                </form>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


            <!-- CART / CHECKOUT NOTE -->

            <div class="note">

                <strong>
                    Delivery Address:
                </strong>

                The addresses saved here are stored
                for this customer and can be used by
                Cart and Checkout. Your default address
                is automatically marked for delivery.

            </div>


        </section>


    </div>


</main>


</body>

</html>