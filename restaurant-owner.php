<?php

session_start();

/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

$host = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "humsafar";

$conn = new mysqli(
    $host,
    $db_user,
    $db_password,
    $db_name
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


/* =========================================================
   HELPER
   ========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   GET OWNER ID FROM SESSION
   ========================================================= */

$owner_id = 0;

if (isset($_SESSION["restaurant_owner_id"])) {

    $owner_id = (int)$_SESSION["restaurant_owner_id"];

} elseif (isset($_SESSION["owner_id"])) {

    $owner_id = (int)$_SESSION["owner_id"];

} elseif (isset($_SESSION["user_id"])) {

    $owner_id = (int)$_SESSION["user_id"];

} elseif (isset($_SESSION["id"])) {

    $owner_id = (int)$_SESSION["id"];
}


/* =========================================================
   LOGIN CHECK
   ========================================================= */

if ($owner_id <= 0) {

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
}


/* =========================================================
   GET RESTAURANT OWNER
   ========================================================= */

$sql = "
    SELECT
        id,
        restaurant_name,
        full_name,
        email,
        phone,
        status,
        payment_status,
        approval_fee
    FROM restaurant_users
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query error: " . $conn->error);
}

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$owner = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   OWNER NOT FOUND
   ========================================================= */

if (!$owner) {

    session_unset();
    session_destroy();

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
}


/* =========================================================
   OWNER DATA
   ========================================================= */

$restaurant_name =
    $owner["restaurant_name"] ?? "Restaurant";

$full_name =
    $owner["full_name"] ?? "";

$email =
    $owner["email"] ?? "";

$phone =
    $owner["phone"] ?? "";

$status =
    strtolower(
        trim(
            (string)(
                $owner["status"] ?? "pending"
            )
        )
    );

$payment_status =
    strtolower(
        trim(
            (string)(
                $owner["payment_status"] ?? "unpaid"
            )
        )
    );

$approval_fee =
    (float)(
        $owner["approval_fee"] ?? 0
    );


/* =========================================================
   IF ALREADY PAID
   ========================================================= */

if ($payment_status === "paid") {

    $already_paid = true;

} else {

    $already_paid = false;
}


/* =========================================================
   CHECK EXISTING PAYMENT SUBMISSION
   ========================================================= */

$existing_payment = null;

$payment_sql = "
    SELECT
        id,
        amount,
        payment_method,
        transaction_id,
        receipt_file,
        payment_status,
        admin_note,
        submitted_at,
        verified_at
    FROM restaurant_payments
    WHERE restaurant_owner_id = ?
    ORDER BY id DESC
    LIMIT 1
";

$payment_stmt = $conn->prepare($payment_sql);

if ($payment_stmt) {

    $payment_stmt->bind_param(
        "i",
        $owner_id
    );

    $payment_stmt->execute();

    $payment_result =
        $payment_stmt->get_result();

    $existing_payment =
        $payment_result->fetch_assoc();

    $payment_stmt->close();
}


/* =========================================================
   FORM VARIABLES
   ========================================================= */

$error = "";

$success = "";


/* =========================================================
   PROCESS PAYMENT FORM
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -----------------------------------------------------
       SECURITY TOKEN
       ----------------------------------------------------- */

    if (
        !isset($_POST["payment_form_token"]) ||
        !isset($_SESSION["payment_form_token"]) ||
        !hash_equals(
            $_SESSION["payment_form_token"],
            $_POST["payment_form_token"]
        )
    ) {

        $error =
            "Invalid form request. Please refresh the page and try again.";

    } elseif ($already_paid) {

        $error =
            "Your approval payment has already been verified.";

    } else {


        /* -------------------------------------------------
           GET FORM DATA
           ------------------------------------------------- */

        $payment_method =
            trim(
                $_POST["payment_method"] ?? ""
            );

        $transaction_id =
            trim(
                $_POST["transaction_id"] ?? ""
            );


        /* -------------------------------------------------
           VALIDATION
           ------------------------------------------------- */

        $allowed_methods = [
            "Easypaisa",
            "JazzCash",
            "Bank Transfer"
        ];


        if ($payment_method === "") {

            $error =
                "Please select a payment method.";

        } elseif (
            !in_array(
                $payment_method,
                $allowed_methods,
                true
            )
        ) {

            $error =
                "Invalid payment method.";

        } elseif ($transaction_id === "") {

            $error =
                "Please enter your transaction ID.";

        } elseif (
            strlen($transaction_id) < 4 ||
            strlen($transaction_id) > 150
        ) {

            $error =
                "Please enter a valid transaction ID.";

        } else {


            /* ---------------------------------------------
               CHECK DUPLICATE TRANSACTION
               --------------------------------------------- */

            $duplicate_sql = "
                SELECT id
                FROM restaurant_payments
                WHERE transaction_id = ?
                LIMIT 1
            ";

            $duplicate_stmt =
                $conn->prepare(
                    $duplicate_sql
                );


            if ($duplicate_stmt) {

                $duplicate_stmt->bind_param(
                    "s",
                    $transaction_id
                );

                $duplicate_stmt->execute();

                $duplicate_result =
                    $duplicate_stmt->get_result();

                $duplicate =
                    $duplicate_result->fetch_assoc();

                $duplicate_stmt->close();

            } else {

                $duplicate = null;
            }


            if ($duplicate) {

                $error =
                    "This transaction ID has already been submitted.";

            } else {


                /* -----------------------------------------
                   CHECK PENDING PAYMENT
                   ----------------------------------------- */

                $pending_sql = "
                    SELECT id
                    FROM restaurant_payments
                    WHERE restaurant_owner_id = ?
                    AND payment_status = 'pending'
                    LIMIT 1
                ";

                $pending_stmt =
                    $conn->prepare(
                        $pending_sql
                    );


                if ($pending_stmt) {

                    $pending_stmt->bind_param(
                        "i",
                        $owner_id
                    );

                    $pending_stmt->execute();

                    $pending_result =
                        $pending_stmt->get_result();

                    $pending =
                        $pending_result->fetch_assoc();

                    $pending_stmt->close();

                } else {

                    $pending = null;
                }


                if ($pending) {

                    $error =
                        "You already have a payment submission waiting for admin verification.";

                } else {


                    /* -------------------------------------
                       RECEIPT UPLOAD
                       ------------------------------------- */

                    $receipt_file_name = null;


                    if (
                        isset($_FILES["receipt"]) &&
                        $_FILES["receipt"]["error"] !== UPLOAD_ERR_NO_FILE
                    ) {


                        if (
                            $_FILES["receipt"]["error"] !== UPLOAD_ERR_OK
                        ) {

                            $error =
                                "There was a problem uploading the receipt.";

                        } else {


                            $max_size =
                                5 * 1024 * 1024;


                            if (
                                $_FILES["receipt"]["size"] >
                                $max_size
                            ) {

                                $error =
                                    "Receipt file must be 5MB or smaller.";

                            } else {


                                $allowed_extensions = [
                                    "jpg",
                                    "jpeg",
                                    "png",
                                    "webp",
                                    "pdf"
                                ];


                                $original_name =
                                    $_FILES["receipt"]["name"];


                                $extension =
                                    strtolower(
                                        pathinfo(
                                            $original_name,
                                            PATHINFO_EXTENSION
                                        )
                                    );


                                if (
                                    !in_array(
                                        $extension,
                                        $allowed_extensions,
                                        true
                                    )
                                ) {

                                    $error =
                                        "Only JPG, JPEG, PNG, WEBP or PDF receipt files are allowed.";

                                } else {


                                    /* ---------------------------------
                                       MIME CHECK
                                       --------------------------------- */

                                    $finfo =
                                        finfo_open(
                                            FILEINFO_MIME_TYPE
                                        );


                                    $mime_type =
                                        finfo_file(
                                            $finfo,
                                            $_FILES["receipt"]["tmp_name"]
                                        );


                                    finfo_close($finfo);


                                    $allowed_mimes = [
                                        "image/jpeg",
                                        "image/png",
                                        "image/webp",
                                        "application/pdf"
                                    ];


                                    if (
                                        !in_array(
                                            $mime_type,
                                            $allowed_mimes,
                                            true
                                        )
                                    ) {

                                        $error =
                                            "Invalid receipt file type.";

                                    } else {


                                        /* -----------------------------
                                           CREATE UPLOAD DIRECTORY
                                           ----------------------------- */

                                        $upload_dir =
                                            __DIR__ .
                                            DIRECTORY_SEPARATOR .
                                            "uploads" .
                                            DIRECTORY_SEPARATOR .
                                            "restaurant-payments";


                                        if (
                                            !is_dir(
                                                $upload_dir
                                            )
                                        ) {

                                            if (
                                                !mkdir(
                                                    $upload_dir,
                                                    0755,
                                                    true
                                                )
                                            ) {

                                                $error =
                                                    "Unable to create receipt upload directory.";
                                            }
                                        }


                                        if (
                                            $error === ""
                                        ) {


                                            /* -------------------------
                                               UNIQUE FILE NAME
                                               ------------------------- */

                                            $receipt_file_name =
                                                "owner_" .
                                                $owner_id .
                                                "_" .
                                                time() .
                                                "_" .
                                                bin2hex(
                                                    random_bytes(5)
                                                ) .
                                                "." .
                                                $extension;


                                            $destination =
                                                $upload_dir .
                                                DIRECTORY_SEPARATOR .
                                                $receipt_file_name;


                                            if (
                                                !move_uploaded_file(
                                                    $_FILES["receipt"]["tmp_name"],
                                                    $destination
                                                )
                                            ) {

                                                $error =
                                                    "Unable to save the receipt file.";

                                                $receipt_file_name =
                                                    null;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                    } else {

                        $error =
                            "Please upload your payment receipt.";
                    }


                    /* -----------------------------------------
                       SAVE PAYMENT
                       ----------------------------------------- */

                    if ($error === "") {


                        $insert_sql = "
                            INSERT INTO restaurant_payments (
                                restaurant_owner_id,
                                restaurant_name,
                                amount,
                                payment_method,
                                transaction_id,
                                receipt_file,
                                payment_status
                            )
                            VALUES (?, ?, ?, ?, ?, ?, 'pending')
                        ";


                        $insert_stmt =
                            $conn->prepare(
                                $insert_sql
                            );


                        if (!$insert_stmt) {

                            $error =
                                "Database query error: " .
                                $conn->error;

                        } else {


                            $insert_stmt->bind_param(
                                "isdsss",
                                $owner_id,
                                $restaurant_name,
                                $approval_fee,
                                $payment_method,
                                $transaction_id,
                                $receipt_file_name
                            );


                            if (
                                $insert_stmt->execute()
                            ) {

                                $success =
                                    "Your payment has been submitted successfully. Please wait for admin verification.";

                                $existing_payment = [
                                    "id" =>
                                        $insert_stmt->insert_id,

                                    "amount" =>
                                        $approval_fee,

                                    "payment_method" =>
                                        $payment_method,

                                    "transaction_id" =>
                                        $transaction_id,

                                    "receipt_file" =>
                                        $receipt_file_name,

                                    "payment_status" =>
                                        "pending",

                                    "admin_note" =>
                                        null,

                                    "submitted_at" =>
                                        date(
                                            "Y-m-d H:i:s"
                                        ),

                                    "verified_at" =>
                                        null
                                ];

                            } else {

                                $error =
                                    "Unable to submit payment. Please try again.";

                                if (
                                    $receipt_file_name !== null
                                ) {

                                    $uploaded_file =
                                        __DIR__ .
                                        DIRECTORY_SEPARATOR .
                                        "uploads" .
                                        DIRECTORY_SEPARATOR .
                                        "restaurant-payments" .
                                        DIRECTORY_SEPARATOR .
                                        $receipt_file_name;


                                    if (
                                        file_exists(
                                            $uploaded_file
                                        )
                                    ) {

                                        unlink(
                                            $uploaded_file
                                        );
                                    }
                                }
                            }


                            $insert_stmt->close();
                        }
                    }
                }
            }
        }
    }
}


/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (
    !isset(
        $_SESSION["payment_form_token"]
    )
) {

    $_SESSION["payment_form_token"] =
        bin2hex(
            random_bytes(32)
        );
}


$payment_form_token =
    $_SESSION["payment_form_token"];


/* =========================================================
   EXISTING PAYMENT STATUS
   ========================================================= */

$show_pending_message = false;

$show_rejected_message = false;

$show_verified_message = false;


if ($existing_payment) {

    if (
        $existing_payment["payment_status"] ===
        "pending"
    ) {

        $show_pending_message = true;

    } elseif (
        $existing_payment["payment_status"] ===
        "verified"
    ) {

        $show_verified_message = true;

    } elseif (
        $existing_payment["payment_status"] ===
        "rejected"
    ) {

        $show_rejected_message = true;
    }
}


/* =========================================================
   LOGOUT
   ========================================================= */

if (
    isset($_GET["logout"]) &&
    $_GET["logout"] === "1"
) {

    session_unset();

    session_destroy();

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
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
    Restaurant Approval Payment - Humsafar
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =========================================================
   GLOBAL
   ========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #fff8fb;

    color: #292929;

    font-family:
        "Segoe UI",
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;
}


/* =========================================================
   NAVBAR
   ========================================================= */

.navbar {

    width: 100%;

    background: #ffffff;

    border-bottom:
        1px solid #f0dce5;

    position: sticky;

    top: 0;

    z-index: 100;
}


.navbar-inner {

    max-width: 1250px;

    min-height: 70px;

    margin: 0 auto;

    padding:
        0 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #292929;
}


.brand-icon {

    width: 43px;

    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f94f87
        );

    font-size: 18px;
}


.brand-text {

    font-size: 18px;

    font-weight: 800;
}


.brand-text span {

    color: #ed0038;
}


.nav-links {

    display: flex;

    align-items: center;

    gap: 10px;
}


.nav-btn {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        9px 13px;

    border:
        1px solid #f0dce5;

    border-radius: 8px;

    background: #ffffff;

    color: #555;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;
}


.nav-btn:hover {

    color: #ed0038;

    background: #fff3f7;

    border-color: #edb5c7;
}


/* =========================================================
   PAGE
   ========================================================= */

.page {

    max-width: 1100px;

    margin: 0 auto;

    padding:
        35px 20px 65px;
}


/* =========================================================
   HEADER
   ========================================================= */

.page-header {

    margin-bottom: 25px;
}


.page-header h1 {

    margin:
        0 0 7px;

    font-size: 29px;

    font-weight: 800;
}


.page-header p {

    margin: 0;

    color: #888;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {

    margin-bottom: 22px;

    padding:
        15px 17px;

    border-radius: 12px;

    font-size: 12px;

    line-height: 1.6;
}


.alert-success {

    color: #166c40;

    border:
        1px solid #bfe5cd;

    background: #ebf9f0;
}


.alert-error {

    color: #a5232c;

    border:
        1px solid #efc0c5;

    background: #fff0f2;
}


.alert-info {

    color: #7a5b00;

    border:
        1px solid #ead89d;

    background: #fff9df;
}


.alert i {

    margin-right: 7px;
}


/* =========================================================
   MAIN GRID
   ========================================================= */

.content-grid {

    display: grid;

    grid-template-columns:
        .85fr 1.15fr;

    gap: 22px;

    align-items: start;
}


/* =========================================================
   CARD
   ========================================================= */

.card {

    padding:
        24px;

    border:
        1px solid #f0dce5;

    border-radius: 18px;

    background: #ffffff;

    box-shadow:
        0 8px 28px
        rgba(80, 25, 50, .05);
}


.card-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 19px;

    padding-bottom: 14px;

    border-bottom:
        1px solid #f3e7eb;
}


.card-title-icon {

    width: 39px;

    height: 39px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    color: #ed0038;

    background: #fff0f5;
}


.card-title h2 {

    margin: 0;

    font-size: 17px;

    font-weight: 800;
}


/* =========================================================
   RESTAURANT INFO
   ========================================================= */

.restaurant-box {

    margin-bottom: 18px;

    padding:
        16px;

    border:
        1px solid #f0dce5;

    border-radius: 13px;

    background: #fffafd;
}


.restaurant-name {

    margin-bottom: 4px;

    color: #ed0038;

    font-size: 18px;

    font-weight: 800;
}


.owner-name {

    color: #777;

    font-size: 11px;
}


.info-row {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding:
        11px 0;

    border-bottom:
        1px solid #f4e9ed;
}


.info-row:last-child {

    border-bottom: 0;
}


.info-label {

    color: #999;

    font-size: 11px;
}


.info-value {

    color: #333;

    font-size: 11px;

    font-weight: 700;

    text-align: right;

    word-break: break-word;
}


/* =========================================================
   FEE
   ========================================================= */

.fee-box {

    margin-top: 20px;

    padding:
        20px;

    border-radius: 15px;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f94f87
        );

    text-align: center;

    box-shadow:
        0 8px 20px
        rgba(237, 0, 56, .18);
}


.fee-label {

    margin-bottom: 5px;

    font-size: 10px;

    opacity: .9;

    text-transform: uppercase;

    font-weight: 700;
}


.fee {

    font-size: 31px;

    font-weight: 900;
}


/* =========================================================
   PAYMENT INSTRUCTIONS
   ========================================================= */

.instruction {

    margin-bottom: 14px;

    padding:
        14px;

    border:
        1px solid #f0dce5;

    border-radius: 12px;

    background: #fffafd;
}


.instruction h3 {

    margin:
        0 0 9px;

    font-size: 13px;

    font-weight: 800;
}


.instruction p {

    margin:
        5px 0;

    color: #777;

    font-size: 11px;

    line-height: 1.6;
}


.payment-detail {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-top: 7px;

    padding:
        8px 10px;

    border-radius: 7px;

    background: #ffffff;
}


.payment-detail span {

    color: #999;

    font-size: 10px;
}


.payment-detail strong {

    color: #333;

    font-size: 11px;
}


/* =========================================================
   FORM
   ========================================================= */

.form-group {

    margin-bottom: 17px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #444;

    font-size: 11px;

    font-weight: 700;
}


.form-control {

    width: 100%;

    min-height: 44px;

    padding:
        10px 12px;

    border:
        1px solid #e7d3dc;

    border-radius: 9px;

    outline: none;

    background: #ffffff;

    color: #333;

    font-family: inherit;

    font-size: 12px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease;
}


.form-control:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237, 0, 56, .08);
}


select.form-control {

    cursor: pointer;
}


.file-input {

    padding:
        8px;

    cursor: pointer;
}


.form-help {

    display: block;

    margin-top: 6px;

    color: #999;

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   SUBMIT
   ========================================================= */

.submit-btn {

    width: 100%;

    min-height: 46px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    border: 0;

    border-radius: 9px;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #ed0038,
            #f94f87
        );

    cursor: pointer;

    font-family: inherit;

    font-size: 12px;

    font-weight: 800;

    box-shadow:
        0 7px 18px
        rgba(237, 0, 56, .18);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.submit-btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 10px 23px
        rgba(237, 0, 56, .24);
}


/* =========================================================
   PAYMENT STATUS
   ========================================================= */

.status-box {

    margin-top: 20px;

    padding:
        18px;

    border-radius: 13px;
}


.status-box.pending {

    border:
        1px solid #ead89d;

    background: #fff9df;

    color: #795d00;
}


.status-box.verified {

    border:
        1px solid #bfe5cd;

    background: #ebf9f0;

    color: #166c40;
}


.status-box.rejected {

    border:
        1px solid #efc0c5;

    background: #fff0f2;

    color: #a5232c;
}


.status-box h3 {

    margin:
        0 0 7px;

    font-size: 14px;

    font-weight: 800;
}


.status-box p {

    margin: 0;

    font-size: 11px;

    line-height: 1.6;
}


.status-box .admin-note {

    margin-top: 10px;

    padding:
        9px;

    border-radius: 7px;

    background:
        rgba(255,255,255,.7);
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer {

    padding:
        25px 15px;

    text-align: center;

    color: #aaa;

    font-size: 10px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 850px) {

    .content-grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 600px) {

    .navbar-inner {

        min-height: 62px;

        padding:
            0 13px;
    }


    .brand-text {

        font-size: 16px;
    }


    .page {

        padding:
            25px 13px 50px;
    }


    .page-header h1 {

        font-size: 25px;
    }


    .card {

        padding: 18px;
    }


    .nav-btn span {

        display: none;
    }


    .nav-btn {

        width: 35px;

        height: 35px;

        justify-content: center;

        padding: 0;
    }


    .info-row {

        flex-direction: column;

        gap: 4px;
    }


    .info-value {

        text-align: left;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
     ===================================================== -->

<nav class="navbar">


    <div class="navbar-inner">


        <a
            href="restaurant-owner-dashboard.php"
            class="brand"
        >

            <div class="brand-icon">

                <i class="fas fa-utensils"></i>

            </div>


            <div class="brand-text">

                Humsafar
                <span>Food</span>

            </div>

        </a>


        <div class="nav-links">


            <a
                href="restaurant-owner-dashboard.php"
                class="nav-btn"
            >

                <i class="fas fa-gauge"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="?logout=1"
                class="nav-btn"
                onclick="
                    return confirm(
                        'Are you sure you want to logout?'
                    );
                "
            >

                <i class="fas fa-right-from-bracket"></i>

                <span>
                    Logout
                </span>

            </a>


        </div>


    </div>


</nav>


<!-- =====================================================
     MAIN PAGE
     ===================================================== -->

<main class="page">


    <div class="page-header">

        <h1>
            Restaurant Approval Payment
        </h1>

        <p>

            Complete your approval fee payment
            and submit the transaction details
            for admin verification.

        </p>

    </div>


    <!-- =================================================
         SUCCESS MESSAGE
         ================================================= -->

    <?php if ($success !== ""): ?>

        <div class="alert alert-success">

            <i class="fas fa-circle-check"></i>

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ERROR MESSAGE
         ================================================= -->

    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            <i class="fas fa-circle-exclamation"></i>

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         CONTENT GRID
         ================================================= -->

    <div class="content-grid">


        <!-- =================================================
             LEFT SIDE
             ================================================= -->

        <div>


            <div class="card">


                <div class="card-title">


                    <div class="card-title-icon">

                        <i class="fas fa-store"></i>

                    </div>


                    <h2>
                        Restaurant Details
                    </h2>


                </div>


                <div class="restaurant-box">


                    <div class="restaurant-name">

                        <?= e($restaurant_name) ?>

                    </div>


                    <div class="owner-name">

                        Owner:
                        <?= e($full_name) ?>

                    </div>


                </div>


                <div class="info-row">


                    <span class="info-label">
                        Email
                    </span>


                    <span class="info-value">

                        <?= e($email) ?>

                    </span>


                </div>


                <div class="info-row">


                    <span class="info-label">
                        Phone
                    </span>


                    <span class="info-value">

                        <?= e($phone) ?>

                    </span>


                </div>


                <div class="info-row">


                    <span class="info-label">
                        Account Status
                    </span>


                    <span class="info-value">

                        <?= e(ucfirst($status)) ?>

                    </span>


                </div>


                <div class="fee-box">


                    <div class="fee-label">

                        Restaurant Approval Fee

                    </div>


                    <div class="fee">

                        Rs.
                        <?= e(
                            number_format(
                                250,
                                2
                            )
                        ) ?>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 PAYMENT INSTRUCTIONS
                 ================================================= -->

            <div class="card" style="margin-top:20px;">


                <div class="card-title">


                    <div class="card-title-icon">

                        <i class="fas fa-wallet"></i>

                    </div>


                    <h2>
                        Payment Instructions
                    </h2>


                </div>


                <div class="instruction">


                    <h3>
                        Easypaisa
                    </h3>


                    <p>

                        Send the exact approval fee
                        to the Easypaisa account below.

                    </p>


                    <div class="payment-detail">

                        <span>
                            Account Number
                        </span>


                        <strong>
                            03XX-XXXXXXX
                        </strong>

                    </div>


                    <div class="payment-detail">

                        <span>
                            Account Title
                        </span>


                        <strong>
                            Humsafar Food
                        </strong>

                    </div>


                </div>


                <div class="instruction">


                    <h3>
                        JazzCash
                    </h3>


                    <p>

                        You can also pay through
                        JazzCash using the following
                        account details.

                    </p>


                    <div class="payment-detail">

                        <span>
                            Account Number
                        </span>


                        <strong>
                            03XX-XXXXXXX
                        </strong>

                    </div>


                    <div class="payment-detail">

                        <span>
                            Account Title
                        </span>


                        <strong>
                            Humsafar Food
                        </strong>

                    </div>


                </div>


                <div class="instruction">


                    <h3>
                        Bank Transfer
                    </h3>


                    <p>

                        Bank transfer payment details
                        can be provided by the Humsafar
                        administration.

                    </p>


                    <div class="payment-detail">

                        <span>
                            Bank
                        </span>


                        <strong>
                            Contact Admin
                        </strong>

                    </div>


                </div>


                <div class="alert alert-info">

                    <i class="fas fa-circle-info"></i>

                    Please send exactly
                    <strong>
                        Rs. <?= e(
                            number_format(
                                $approval_fee,
                                2
                            )
                        ) ?>
                    </strong>
                    and keep your transaction ID
                    and payment receipt safe.

                </div>


            </div>


        </div>


        <!-- =================================================
             RIGHT SIDE
             ================================================= -->

        <div class="card">


            <div class="card-title">


                <div class="card-title-icon">

                    <i class="fas fa-file-invoice-dollar"></i>

                </div>


                <h2>
                    Submit Payment
                </h2>


            </div>


            <?php if ($show_verified_message): ?>


                <div class="status-box verified">


                    <h3>

                        <i class="fas fa-circle-check"></i>

                        Payment Verified

                    </h3>


                    <p>

                        Your approval payment has been
                        verified by the administration.

                        You can return to your dashboard
                        to check your restaurant approval
                        status.

                    </p>


                </div>


                <div style="margin-top:15px;">

                    <a
                        href="restaurant-owner-dashboard.php"
                        class="submit-btn"
                        style="text-decoration:none;"
                    >

                        <i class="fas fa-gauge"></i>

                        Back to Dashboard

                    </a>

                </div>


            <?php elseif ($show_pending_message): ?>


                <div class="status-box pending">


                    <h3>

                        <i class="fas fa-clock"></i>

                        Payment Verification Pending

                    </h3>


                    <p>

                        Your payment details have already
                        been submitted. Our admin team will
                        verify your transaction and update
                        your payment status.

                    </p>


                    <?php if (
                        !empty(
                            $existing_payment[
                                "transaction_id"
                            ]
                        )
                    ): ?>

                        <div
                            style="
                                margin-top:10px;
                                font-size:10px;
                            "
                        >

                            Transaction ID:

                            <strong>

                                <?= e(
                                    $existing_payment[
                                        "transaction_id"
                                    ]
                                ) ?>

                            </strong>

                        </div>

                    <?php endif; ?>


                </div>


                <div style="margin-top:15px;">

                    <a
                        href="restaurant-owner-dashboard.php"
                        class="submit-btn"
                        style="text-decoration:none;"
                    >

                        <i class="fas fa-gauge"></i>

                        Back to Dashboard

                    </a>

                </div>


            <?php else: ?>


                <?php if ($show_rejected_message): ?>


                    <div class="status-box rejected">


                        <h3>

                            <i class="fas fa-circle-xmark"></i>

                            Previous Payment Rejected

                        </h3>


                        <p>

                            Your previous payment submission
                            was rejected. Please check the
                            admin note below and submit the
                            correct payment details again.

                        </p>


                        <?php if (
                            !empty(
                                $existing_payment[
                                    "admin_note"
                                ]
                            )
                        ): ?>


                            <div class="admin-note">

                                <strong>
                                    Admin Note:
                                </strong>

                                <br>

                                <?= e(
                                    $existing_payment[
                                        "admin_note"
                                    ]
                                ) ?>

                            </div>


                        <?php endif; ?>


                    </div>


                    <div style="height:20px;"></div>


                <?php endif; ?>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                >


                    <input
                        type="hidden"
                        name="payment_form_token"
                        value="<?= e(
                            $payment_form_token
                        ) ?>"
                    >


                    <!-- PAYMENT METHOD -->

                    <div class="form-group">


                        <label for="payment_method">

                            Payment Method
                            <span style="color:#ed0038;">
                                *
                            </span>

                        </label>


                        <select
                            id="payment_method"
                            name="payment_method"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Select Payment Method
                            </option>


                            <option value="Easypaisa">
                                Easypaisa
                            </option>


                            <option value="JazzCash">
                                JazzCash
                            </option>


                            <option value="Bank Transfer">
                                Bank Transfer
                            </option>

                        </select>


                    </div>


                    <!-- TRANSACTION ID -->

                    <div class="form-group">


                        <label for="transaction_id">

                            Transaction ID
                            <span style="color:#ed0038;">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            id="transaction_id"
                            name="transaction_id"
                            class="form-control"
                            placeholder="Enter your transaction ID"
                            maxlength="150"
                            required
                        >


                        <small class="form-help">

                            Enter the transaction/reference
                            number shown after your payment.

                        </small>


                    </div>


                    <!-- RECEIPT -->

                    <div class="form-group">


                        <label for="receipt">

                            Payment Receipt
                            <span style="color:#ed0038;">
                                *
                            </span>

                        </label>


                        <input
                            type="file"
                            id="receipt"
                            name="receipt"
                            class="form-control file-input"
                            accept="
                                image/jpeg,
                                image/png,
                                image/webp,
                                application/pdf
                            "
                            required
                        >


                        <small class="form-help">

                            Accepted:
                            JPG, JPEG, PNG, WEBP and PDF.
                            Maximum file size: 5MB.

                        </small>


                    </div>


                    <!-- CONFIRMATION -->

                    <div
                        style="
                            margin-bottom:17px;
                            padding:12px;
                            border:1px solid #f0dce5;
                            border-radius:10px;
                            background:#fffafd;
                        "
                    >

                        <label
                            style="
                                display:flex;
                                align-items:flex-start;
                                gap:9px;
                                margin:0;
                                cursor:pointer;
                                color:#666;
                                font-size:10px;
                                line-height:1.6;
                            "
                        >

                            <input
                                type="checkbox"
                                required
                                style="margin-top:3px;"
                            >


                            <span>

                                I confirm that I have paid
                                the exact approval fee and
                                the transaction information
                                provided above is correct.

                            </span>


                        </label>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="submit-btn"
                    >

                        <i class="fas fa-paper-plane"></i>

                        Submit Payment for Verification

                    </button>


                </form>


            <?php endif; ?>


        </div>


    </div>


</main>


<footer class="footer">

    Humsafar Food Delivery
    &nbsp;•&nbsp;
    Restaurant Owner Payment

</footer>


</body>

</html>


<?php

$conn->close();

?>