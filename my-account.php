<?php
/*
=========================================================
    HUMSAFAR FOOD DELIVERY
    MY ACCOUNT PAGE
=========================================================
*/

require_once 'includes/config.php';
require_once 'includes/session.php';


/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}

$user_id = (int) $_SESSION['user_id'];


/* =====================================================
   HELPER
===================================================== */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =====================================================
   CREATE CUSTOMER TABLES IF NOT EXISTS
=====================================================

   Your existing SQL does not contain separate
   address/payment tables, so these are created
   automatically without touching the users table.
===================================================== */

$conn->query("
    CREATE TABLE IF NOT EXISTS customer_addresses (

        id INT(11) NOT NULL AUTO_INCREMENT,

        user_id INT(11) NOT NULL,

        address_title VARCHAR(100) NOT NULL,

        address_line VARCHAR(255) NOT NULL,

        city VARCHAR(100) NOT NULL,

        area VARCHAR(100) DEFAULT NULL,

        phone VARCHAR(30) DEFAULT NULL,

        is_default TINYINT(1) DEFAULT 0,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        INDEX (user_id)

    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
");


$conn->query("
    CREATE TABLE IF NOT EXISTS customer_payments (

        id INT(11) NOT NULL AUTO_INCREMENT,

        user_id INT(11) NOT NULL,

        payment_method VARCHAR(30) NOT NULL,

        provider VARCHAR(100) NOT NULL,

        account_holder VARCHAR(150) NOT NULL,

        account_number VARCHAR(255) DEFAULT NULL,

        card_type VARCHAR(50) DEFAULT NULL,

        card_last4 VARCHAR(4) DEFAULT NULL,

        expiry_month VARCHAR(2) DEFAULT NULL,

        expiry_year VARCHAR(4) DEFAULT NULL,

        is_default TINYINT(1) DEFAULT 0,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

        PRIMARY KEY (id),

        INDEX (user_id)

    )
    ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
");


/* =====================================================
   MESSAGE VARIABLES
===================================================== */

$success_message = '';
$error_message   = '';


/* =====================================================
   LOAD USER
===================================================== */

$user = null;

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        profile_image,
        status,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$user_result = $stmt->get_result();

$user = $user_result->fetch_assoc();

$stmt->close();


if (!$user) {

    session_destroy();

    header("Location: login.php");

    exit;

}


/* =====================================================
   HANDLE ACTIONS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action']
        ?? '';


    /* =================================================
       UPDATE PROFILE
    ================================================= */

    if ($action === 'update_profile') {

        $full_name =
            trim(
                $_POST['full_name']
                ?? ''
            );

        $email =
            trim(
                $_POST['email']
                ?? ''
            );

        $phone =
            trim(
                $_POST['phone']
                ?? ''
            );


        if ($full_name === '') {

            $error_message =
                'Please enter your full name.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error_message =
                'Please enter a valid email address.';

        } elseif ($phone === '') {

            $error_message =
                'Please enter your phone number.';

        } else {

            /* Check duplicate email */

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "si",
                $email,
                $user_id
            );

            $stmt->execute();

            $duplicate =
                $stmt->get_result()->fetch_assoc();

            $stmt->close();


            if ($duplicate) {

                $error_message =
                    'This email address is already in use.';

            } else {

                /* Check duplicate phone */

                $stmt = $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE phone = ?
                    AND id != ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    "si",
                    $phone,
                    $user_id
                );

                $stmt->execute();

                $duplicate_phone =
                    $stmt->get_result()->fetch_assoc();

                $stmt->close();


                if ($duplicate_phone) {

                    $error_message =
                        'This phone number is already in use.';

                } else {

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET
                            full_name = ?,
                            email = ?,
                            phone = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "sssi",
                        $full_name,
                        $email,
                        $phone,
                        $user_id
                    );

                    if ($stmt->execute()) {

                        $success_message =
                            'Profile updated successfully.';

                        $user['full_name'] =
                            $full_name;

                        $user['email'] =
                            $email;

                        $user['phone'] =
                            $phone;

                    } else {

                        $error_message =
                            'Unable to update your profile.';

                    }

                    $stmt->close();

                }

            }

        }

    }

    /* =================================================
       ADD ADDRESS
    ================================================= */

    if ($action === 'add_address') {

        $address_title =
            trim(
                $_POST['address_title']
                ?? ''
            );

        $address_line =
            trim(
                $_POST['address_line']
                ?? ''
            );

        $city =
            trim(
                $_POST['city']
                ?? ''
            );

        $area =
            trim(
                $_POST['area']
                ?? ''
            );

        $address_phone =
            trim(
                $_POST['address_phone']
                ?? ''
            );


        if (
            $address_title === '' ||
            $address_line === '' ||
            $city === ''
        ) {

            $error_message =
                'Please complete all required address fields.';

        } else {

            $is_default =
                isset(
                    $_POST['is_default']
                )
                ? 1
                : 0;


            if ($is_default) {

                $stmt = $conn->prepare("
                    UPDATE customer_addresses
                    SET is_default = 0
                    WHERE user_id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $user_id
                );

                $stmt->execute();

                $stmt->close();

            }


            $stmt = $conn->prepare("
                INSERT INTO customer_addresses
                (
                    user_id,
                    address_title,
                    address_line,
                    city,
                    area,
                    phone,
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
                    ?
                )
            ");

            $stmt->bind_param(
                "isssssi",
                $user_id,
                $address_title,
                $address_line,
                $city,
                $area,
                $address_phone,
                $is_default
            );


            if ($stmt->execute()) {

                $success_message =
                    'Address added successfully.';

            } else {

                $error_message =
                    'Unable to save address.';

            }

            $stmt->close();

        }

    }


    /* =================================================
       UPDATE ADDRESS
    ================================================= */

    if ($action === 'update_address') {

        $address_id =
            (int)(
                $_POST['address_id']
                ?? 0
            );

        $address_title =
            trim(
                $_POST['address_title']
                ?? ''
            );

        $address_line =
            trim(
                $_POST['address_line']
                ?? ''
            );

        $city =
            trim(
                $_POST['city']
                ?? ''
            );

        $area =
            trim(
                $_POST['area']
                ?? ''
            );

        $address_phone =
            trim(
                $_POST['address_phone']
                ?? ''
            );


        if (
            $address_id <= 0 ||
            $address_title === '' ||
            $address_line === '' ||
            $city === ''
        ) {

            $error_message =
                'Please complete all required address fields.';

        } else {

            $is_default =
                isset(
                    $_POST['is_default']
                )
                ? 1
                : 0;


            if ($is_default) {

                $stmt = $conn->prepare("
                    UPDATE customer_addresses
                    SET is_default = 0
                    WHERE user_id = ?
                ");

                $stmt->bind_param(
                    "i",
                    $user_id
                );

                $stmt->execute();

                $stmt->close();

            }


            $stmt = $conn->prepare("
                UPDATE customer_addresses
                SET
                    address_title = ?,
                    address_line = ?,
                    city = ?,
                    area = ?,
                    phone = ?,
                    is_default = ?
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param(
                "sssssiii",
                $address_title,
                $address_line,
                $city,
                $area,
                $address_phone,
                $is_default,
                $address_id,
                $user_id
            );


            if ($stmt->execute()) {

                $success_message =
                    'Address updated successfully.';

            } else {

                $error_message =
                    'Unable to update address.';

            }

            $stmt->close();

        }

    }


    /* =================================================
       DELETE ADDRESS
    ================================================= */

    if ($action === 'delete_address') {

        $address_id =
            (int)(
                $_POST['address_id']
                ?? 0
            );


        if ($address_id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM customer_addresses
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $address_id,
                $user_id
            );

            if ($stmt->execute()) {

                $success_message =
                    'Address removed successfully.';

            } else {

                $error_message =
                    'Unable to remove address.';

            }

            $stmt->close();

        }

    }


    /* =================================================
       SET DEFAULT ADDRESS
    ================================================= */

    if ($action === 'default_address') {

        $address_id =
            (int)(
                $_POST['address_id']
                ?? 0
            );


        $stmt = $conn->prepare("
            UPDATE customer_addresses
            SET is_default = 0
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $stmt->close();


        $stmt = $conn->prepare("
            UPDATE customer_addresses
            SET is_default = 1
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $address_id,
            $user_id
        );

        if ($stmt->execute()) {

            $success_message =
                'Default address updated.';

        }

        $stmt->close();

    }


    /* =================================================
       ADD PAYMENT
    ================================================= */

    if ($action === 'add_payment') {

        $payment_method =
            trim(
                $_POST['payment_method']
                ?? ''
            );

        $provider =
            trim(
                $_POST['provider']
                ?? ''
            );

        $account_holder =
            trim(
                $_POST['account_holder']
                ?? ''
            );

        $account_number =
            trim(
                $_POST['account_number']
                ?? ''
            );

        $card_type =
            trim(
                $_POST['card_type']
                ?? ''
            );

        $expiry_month =
            trim(
                $_POST['expiry_month']
                ?? ''
            );

        $expiry_year =
            trim(
                $_POST['expiry_year']
                ?? ''
            );


        if (
            !in_array(
                $payment_method,
                [
                    'wallet',
                    'bank',
                    'card'
                ],
                true
            )
        ) {

            $error_message =
                'Please select a valid payment method.';

        } elseif ($provider === '') {

            $error_message =
                'Please select a provider.';

        } elseif ($account_holder === '') {

            $error_message =
                'Please enter account holder name.';

        } elseif ($account_number === '') {

            $error_message =
                'Please enter the account number.';

        } elseif (
            $payment_method === 'card'
            &&
            $card_type === ''
        ) {

            $error_message =
                'Please select card type.';

        } elseif (
            $payment_method === 'card'
            &&
            (
                $expiry_month === ''
                ||
                $expiry_year === ''
            )
        ) {

            $error_message =
                'Please enter card expiry date.';

        } else {

            $is_default =
                isset(
                    $_POST['is_default_payment']
                )
                ? 1
                : 0;


            /*
             * Never store CVV.
             */

            $cvv =
                trim(
                    $_POST['cvv']
                    ?? ''
                );


            /*
             * For cards, keep only last four digits.
             * For wallet/bank, account number is stored
             * because it is needed to identify the payment
             * account in this local application.
             */

            $stored_account_number =
                $account_number;

            $card_last4 = null;


            if ($payment_method === 'card') {

                $digits =
                    preg_replace(
                        '/\D/',
                        '',
                        $account_number
                    );

                if (
                    strlen($digits) < 4
                ) {

                    $error_message =
                        'Please enter a valid card number.';

                } else {

                    $card_last4 =
                        substr(
                            $digits,
                            -4
                        );

                    /*
                     * Do not save complete card number.
                     */

                    $stored_account_number =
                        null;

                }

            }


            if ($error_message === '') {

                if ($is_default) {

                    $stmt = $conn->prepare("
                        UPDATE customer_payments
                        SET is_default = 0
                        WHERE user_id = ?
                    ");

                    $stmt->bind_param(
                        "i",
                        $user_id
                    );

                    $stmt->execute();

                    $stmt->close();

                }


                $stmt = $conn->prepare("
                    INSERT INTO customer_payments
                    (
                        user_id,
                        payment_method,
                        provider,
                        account_holder,
                        account_number,
                        card_type,
                        card_last4,
                        expiry_month,
                        expiry_year,
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
                        ?
                    )
                ");

                $stmt->bind_param(
                    "issssssssi",
                    $user_id,
                    $payment_method,
                    $provider,
                    $account_holder,
                    $stored_account_number,
                    $card_type,
                    $card_last4,
                    $expiry_month,
                    $expiry_year,
                    $is_default
                );


                if ($stmt->execute()) {

                    $success_message =
                        'Payment method added successfully.';

                } else {

                    $error_message =
                        'Unable to save payment method.';

                }

                $stmt->close();

            }

        }

    }


    /* =================================================
       DELETE PAYMENT
    ================================================= */

    if ($action === 'delete_payment') {

        $payment_id =
            (int)(
                $_POST['payment_id']
                ?? 0
            );


        if ($payment_id > 0) {

            $stmt = $conn->prepare("
                DELETE FROM customer_payments
                WHERE id = ?
                AND user_id = ?
            ");

            $stmt->bind_param(
                "ii",
                $payment_id,
                $user_id
            );


            if ($stmt->execute()) {

                $success_message =
                    'Payment method removed successfully.';

            } else {

                $error_message =
                    'Unable to remove payment method.';

            }

            $stmt->close();

        }

    }


    /* =================================================
       DEFAULT PAYMENT
    ================================================= */

    if ($action === 'default_payment') {

        $payment_id =
            (int)(
                $_POST['payment_id']
                ?? 0
            );


        $stmt = $conn->prepare("
            UPDATE customer_payments
            SET is_default = 0
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $stmt->close();


        $stmt = $conn->prepare("
            UPDATE customer_payments
            SET is_default = 1
            WHERE id = ?
            AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $payment_id,
            $user_id
        );


        if ($stmt->execute()) {

            $success_message =
                'Default payment method updated.';

        }

        $stmt->close();

    }

}


/* =================================================
   MY ORDERS
================================================= */

$orders = [];

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_number,
        o.restaurant_id,
        o.address_id,
        o.payment_method,
        o.subtotal,
        o.delivery_fee,
        o.discount,
        o.total,
        o.order_status,
        o.customer_note,
        o.created_at,
        r.name AS restaurant_name,
        r.image AS restaurant_image
    FROM orders o
    LEFT JOIN restaurants r
        ON o.restaurant_id = r.id
    WHERE o.user_id = ?
      AND o.order_status != 'cancelled'
    ORDER BY o.id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();

}

/* =====================================================
   LOAD ADDRESSES
===================================================== */

$addresses = [];

$stmt = $conn->prepare("
    SELECT
        id,
        address_title,
        address_line,
        city,
        area,
        phone,
        is_default
    FROM customer_addresses
    WHERE user_id = ?
    ORDER BY
        is_default DESC,
        id DESC
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$address_result =
    $stmt->get_result();

while (
    $row =
        $address_result->fetch_assoc()
) {

    $addresses[] =
        $row;

}

$stmt->close();


/* =====================================================
   LOAD PAYMENT METHODS
===================================================== */

$payments = [];

$stmt = $conn->prepare("
    SELECT
        id,
        payment_method,
        provider,
        account_holder,
        account_number,
        card_type,
        card_last4,
        expiry_month,
        expiry_year,
        is_default
    FROM customer_payments
    WHERE user_id = ?
    ORDER BY
        is_default DESC,
        id DESC
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$payment_result =
    $stmt->get_result();

while (
    $row =
        $payment_result->fetch_assoc()
) {

    $payments[] =
        $row;

}

$stmt->close();


/* =====================================================
   CART COUNT
===================================================== */

$cart_count = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(quantity),
        0
    ) AS total
    FROM cart
    WHERE user_id = ?
");

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$cart_row =
    $stmt->get_result()
        ->fetch_assoc();

$cart_count =
    (int)(
        $cart_row['total']
        ?? 0
    );

$stmt->close();


/* =====================================================
   USER DISPLAY
===================================================== */

$user_name =
    $user['full_name']
    ?: 'Customer';

$user_email =
    $user['email']
    ?: '';

$user_phone =
    $user['phone']
    ?: '';

$member_year =
    date(
        'Y',
        strtotime(
            $user['created_at']
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
        My Account - Humsafar
    </title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/css_header.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


<style>

/* =====================================================
   ACCOUNT PAGE
===================================================== */

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:#f8f8fc;
    color:#333;
    font-family:
        'Segoe UI',
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;
}

a{
    text-decoration:none;
}

.account-page{
    max-width:1250px;
    margin:40px auto;
    padding:0 20px 70px;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.account-page-header{
    background:
        linear-gradient(
            135deg,
            #e4002b,
            #ff6b9d
        );

    border-radius:22px;

    padding:32px;

    color:#fff;

    margin-bottom:28px;

    box-shadow:
        0 12px 30px
        rgba(228,0,43,.18);
}

.account-page-header h1{
    margin:0;
    font-size:32px;
    font-weight:800;
}

.account-page-header p{
    margin:8px 0 0;
    opacity:.92;
}


/* =====================================================
   MESSAGES
===================================================== */

.account-message{
    padding:15px 18px;
    border-radius:12px;
    margin-bottom:20px;
    font-weight:600;
}

.account-success{
    background:#e9f8ee;
    color:#198754;
    border-left:5px solid #198754;
}

.account-error{
    background:#fdeaea;
    color:#dc3545;
    border-left:5px solid #dc3545;
}


/* =====================================================
   DASHBOARD
===================================================== */

.account-layout{
    display:grid;
    grid-template-columns:290px 1fr;
    gap:25px;
    align-items:start;
}


/* =====================================================
   SIDEBAR
===================================================== */

.account-sidebar{
    background:#fff;
    border-radius:20px;
    padding:20px;
    box-shadow:
        0 8px 25px
        rgba(0,0,0,.07);
    position:sticky;
    top:20px;
}

.profile-mini{
    text-align:center;
    padding:12px 5px 22px;
    border-bottom:1px solid #eee;
}

.profile-avatar{
    width:82px;
    height:82px;
    border-radius:50%;
    margin:0 auto 12px;

    display:flex;
    align-items:center;
    justify-content:center;

    background:
        linear-gradient(
            135deg,
            #e4002b,
            #ff6b9d
        );

    color:#fff;
    font-size:38px;

    box-shadow:
        0 8px 20px
        rgba(228,0,43,.20);
}

.profile-mini h3{
    margin:0;
    font-size:18px;
    color:#222;
}

.profile-mini p{
    margin:5px 0;
    color:#777;
    font-size:13px;
    word-break:break-word;
}

.member-since{
    color:#e4002b;
    font-size:12px;
    font-weight:600;
}

.account-nav{
    padding-top:15px;
}

.account-nav button{
    width:100%;
    border:0;
    background:transparent;
    cursor:pointer;

    display:flex;
    align-items:center;
    gap:12px;

    padding:13px 14px;

    margin-bottom:5px;

    border-radius:11px;

    color:#555;

    font-family:inherit;
    font-size:14px;
    font-weight:600;

    text-align:left;

    transition:.25s;
}

.account-nav button:hover,
.account-nav button.active{
    background:
        linear-gradient(
            135deg,
            rgba(228,0,43,.10),
            rgba(255,107,157,.12)
        );

    color:#e4002b;
}

.account-nav button i{
    width:20px;
    text-align:center;
}


/* =====================================================
   CONTENT
===================================================== */

.account-content{
    min-width:0;
}

.account-section{
    display:none;
}

.account-section.active{
    display:block;
}

.section-card{
    background:#fff;
    border-radius:20px;
    padding:28px;
    margin-bottom:25px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.07);
}

.section-card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;

    margin-bottom:22px;

    padding-bottom:17px;

    border-bottom:1px solid #eee;
}

.section-card-header h2{
    margin:0;
    color:#222;
    font-size:22px;
}

.section-card-header p{
    margin:5px 0 0;
    color:#777;
    font-size:13px;
}


/* =====================================================
   FORMS
===================================================== */

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.form-group{
    margin-bottom:18px;
}

.form-group.full{
    grid-column:1 / -1;
}

.form-group label{
    display:block;
    margin-bottom:7px;
    color:#444;
    font-size:13px;
    font-weight:700;
}

.form-control{
    width:100%;
    height:46px;
    padding:0 14px;

    border:1px solid #ddd;
    border-radius:10px;

    background:#fff;

    color:#333;
    font-family:inherit;
    font-size:14px;

    outline:none;

    transition:.25s;
}

.form-control:focus{
    border-color:#ff6b9d;

    box-shadow:
        0 0 0 3px
        rgba(255,107,157,.13);
}

textarea.form-control{
    height:95px;
    padding:12px 14px;
    resize:vertical;
}


/* =====================================================
   BUTTONS
===================================================== */

.btn{
    border:0;
    border-radius:10px;

    padding:11px 18px;

    cursor:pointer;

    font-family:inherit;
    font-size:13px;
    font-weight:700;

    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;

    transition:.25s;
}

.btn-primary{
    background:
        linear-gradient(
            135deg,
            #e4002b,
            #ff6b9d
        );

    color:#fff;

    box-shadow:
        0 6px 16px
        rgba(228,0,43,.18);
}

.btn-primary:hover{
    transform:translateY(-1px);
}

.btn-light{
    background:#f5f5f7;
    color:#555;
}

.btn-danger{
    background:#fff0f1;
    color:#dc3545;
}

.btn-small{
    padding:8px 12px;
    font-size:12px;
}


/* =====================================================
   ADDRESS
===================================================== */

.address-list{
    display:grid;
    gap:15px;
}

.address-card{
    border:1px solid #e8e8e8;
    border-radius:15px;
    padding:18px;

    display:flex;
    justify-content:space-between;
    gap:20px;

    transition:.25s;
}

.address-card:hover{
    border-color:#ffb4ca;
    box-shadow:
        0 7px 20px
        rgba(0,0,0,.05);
}

.address-card.default{
    border-color:#ff6b9d;
    background:#fff8fb;
}

.address-main{
    min-width:0;
}

.address-title{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:8px;
}

.address-title h3{
    margin:0;
    color:#222;
    font-size:16px;
}

.default-badge{
    background:#ffe2ec;
    color:#e4002b;
    border-radius:20px;
    padding:4px 8px;
    font-size:10px;
    font-weight:800;
}

.address-main p{
    margin:5px 0;
    color:#666;
    font-size:13px;
    line-height:1.5;
}

.address-actions{
    display:flex;
    flex-direction:column;
    gap:7px;
    min-width:105px;
}


/* =====================================================
   PAYMENT METHOD
===================================================== */

.payment-methods{
    display:grid;
    grid-template-columns:
        repeat(3,1fr);

    gap:12px;

    margin-bottom:20px;
}

.payment-method-option{
    position:relative;
}

.payment-method-option input{
    position:absolute;
    opacity:0;
}

.payment-method-option label{
    min-height:88px;

    border:1px solid #ddd;
    border-radius:14px;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;

    gap:7px;

    cursor:pointer;

    color:#666;

    font-size:13px;
    font-weight:700;

    transition:.25s;
}

.payment-method-option label i{
    font-size:24px;
    color:#aaa;
}

.payment-method-option input:checked + label{
    border-color:#ff6b9d;

    background:
        linear-gradient(
            135deg,
            rgba(228,0,43,.07),
            rgba(255,107,157,.12)
        );

    color:#e4002b;
}

.payment-method-option input:checked + label i{
    color:#e4002b;
}


/* =====================================================
   DYNAMIC PROVIDER
===================================================== */

.provider-box{
    display:none;
}

.provider-box.active{
    display:block;
}

.provider-box label{
    display:block;
    margin-bottom:7px;
    color:#444;
    font-size:13px;
    font-weight:700;
}


/* =====================================================
   PAYMENT LIST
===================================================== */

.payment-list{
    display:grid;
    gap:15px;
}

.payment-card{
    border:1px solid #e8e8e8;
    border-radius:15px;
    padding:18px;

    display:flex;
    justify-content:space-between;
    align-items:center;

    gap:20px;
}

.payment-card.default{
    border-color:#ff6b9d;
    background:#fff8fb;
}

.payment-left{
    display:flex;
    align-items:center;
    gap:15px;
    min-width:0;
}

.payment-icon{
    width:52px;
    height:52px;
    flex-shrink:0;

    border-radius:13px;

    display:flex;
    align-items:center;
    justify-content:center;

    background:
        linear-gradient(
            135deg,
            rgba(228,0,43,.10),
            rgba(255,107,157,.14)
        );

    color:#e4002b;
    font-size:22px;
}

.payment-info{
    min-width:0;
}

.payment-info h3{
    margin:0 0 5px;
    color:#222;
    font-size:15px;
}

.payment-info p{
    margin:3px 0;
    color:#777;
    font-size:12px;
}

.payment-actions{
    display:flex;
    gap:7px;
    flex-wrap:wrap;
    justify-content:flex-end;
}


/* =====================================================
   MY ORDERS
===================================================== */

.orders-list{
    display:grid;
    gap:15px;
}

.account-order-card{
    border:1px solid #e8e8e8;
    border-radius:15px;
    padding:18px;
    background:#fff;
    transition:.25s;
}

.account-order-card:hover{
    border-color:#ffb4ca;
    box-shadow:
        0 7px 20px
        rgba(0,0,0,.05);
}

.account-order-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    padding-bottom:15px;
    border-bottom:1px solid #eee;
}

.account-order-title{
    min-width:0;
}

.account-order-title h3{
    margin:0 0 5px;
    color:#222;
    font-size:16px;
}

.account-order-title p{
    margin:0;
    color:#777;
    font-size:13px;
}

.account-order-status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:800;
    white-space:nowrap;
}

.account-order-status.pending{
    background:#fff4d6;
    color:#9a6b00;
}

.account-order-status.confirmed,
.account-order-status.accepted{
    background:#e9f8ee;
    color:#198754;
}

.account-order-status.preparing{
    background:#f0eaff;
    color:#7048c8;
}

.account-order-status.out_for_delivery,
.account-order-status.on_the_way,
.account-order-status.out-for-delivery{
    background:#e8f4ff;
    color:#1670b8;
}

.account-order-status.delivered,
.account-order-status.completed{
    background:#e9f8ee;
    color:#16834b;
}

.account-order-status.cancelled,
.account-order-status.canceled{
    background:#fdeaea;
    color:#dc3545;
}

.account-order-status.default{
    background:#f5f5f7;
    color:#666;
}

.account-order-meta{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
    padding:15px 0;
}

.account-order-meta-item{
    background:#fafafa;
    border-radius:10px;
    padding:10px 12px;
    min-width:0;
}

.account-order-meta-item span{
    display:block;
    color:#888;
    font-size:11px;
    margin-bottom:4px;
}

.account-order-meta-item strong{
    display:block;
    color:#333;
    font-size:13px;
    word-break:break-word;
}

.account-order-bottom{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    padding-top:14px;
    border-top:1px solid #eee;
}

.account-order-total{
    color:#222;
    font-size:14px;
    font-weight:800;
}

.account-order-view{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:9px 13px;
    border-radius:9px;
    background:linear-gradient(135deg,#e4002b,#ff6b9d);
    color:#fff;
    font-size:12px;
    font-weight:700;
}

.account-order-view:hover{
    color:#fff;
    opacity:.92;
}

@media(max-width:700px){

    .account-order-top,
    .account-order-bottom{
        align-items:flex-start;
        flex-direction:column;
    }

    .account-order-meta{
        grid-template-columns:1fr;
    }

    .account-order-view{
        width:100%;
    }

}


/* =====================================================
   EMPTY
===================================================== */

.empty-box{
    text-align:center;
    padding:40px 20px;
    color:#777;
}

.empty-box i{
    font-size:42px;
    color:#ff6b9d;
    margin-bottom:12px;
}

.empty-box h3{
    margin:0;
    color:#333;
}


/* =====================================================
   MODAL
===================================================== */

.modal{
    display:none;

    position:fixed;

    inset:0;

    background:
        rgba(0,0,0,.55);

    z-index:9999;

    align-items:center;
    justify-content:center;

    padding:20px;
}

.modal.show{
    display:flex;
}

.modal-box{
    background:#fff;

    width:100%;
    max-width:620px;

    max-height:90vh;

    overflow-y:auto;

    border-radius:20px;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.20);
}

.modal-header{
    padding:22px 25px;

    display:flex;
    justify-content:space-between;
    align-items:center;

    border-bottom:1px solid #eee;
}

.modal-header h2{
    margin:0;
    font-size:20px;
    color:#222;
}

.close-modal{
    width:35px;
    height:35px;

    border:0;
    border-radius:50%;

    background:#f5f5f5;

    cursor:pointer;

    font-size:18px;
}

.modal-body{
    padding:25px;
}

.modal-footer{
    padding:18px 25px;

    display:flex;
    justify-content:flex-end;
    gap:10px;

    border-top:1px solid #eee;
}


/* =====================================================
   FOOTER
===================================================== */

footer{
    background:#222;
    color:#fff;
    margin-top:20px;
}

.footer-content{
    max-width:1250px;
    margin:auto;
    padding:45px 20px;

    display:grid;
    grid-template-columns:
        repeat(4,1fr);

    gap:30px;
}

.footer-column h3{
    margin:0 0 15px;
    font-size:17px;
}

.footer-column ul{
    list-style:none;
    padding:0;
    margin:0;
}

.footer-column li{
    margin-bottom:9px;
}

.footer-column a{
    color:#bbb;
    font-size:14px;
}

.footer-column a:hover{
    color:#ff6b9d;
}

.social-icons{
    display:flex;
    gap:10px;
    margin-top:15px;
}

.social-icons a{
    width:35px;
    height:35px;

    border-radius:50%;

    display:flex;
    align-items:center;
    justify-content:center;

    background:#333;
    color:#fff;
}

.social-icons a:hover{
    background:#e4002b;
}

.copyright{
    border-top:1px solid #444;

    text-align:center;

    padding:18px 15px;

    color:#aaa;

    font-size:13px;
}

.copyright p{
    margin:0;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:950px){

    .account-layout{
        grid-template-columns:1fr;
    }

    .account-sidebar{
        position:static;
    }

    .account-nav{
        display:grid;
        grid-template-columns:
            repeat(2,1fr);

        gap:5px;
    }

    .account-nav button{
        margin:0;
    }

}


@media(max-width:700px){

    .account-page{
        margin-top:25px;
        padding:0 12px 45px;
    }

    .account-page-header{
        padding:25px 20px;
    }

    .account-page-header h1{
        font-size:27px;
    }

    .form-grid{
        grid-template-columns:1fr;
    }

    .form-group.full{
        grid-column:auto;
    }

    .payment-methods{
        grid-template-columns:1fr;
    }

    .address-card,
    .payment-card{
        flex-direction:column;
        align-items:stretch;
    }

    .address-actions{
        flex-direction:row;
        flex-wrap:wrap;
    }

    .payment-actions{
        justify-content:flex-start;
    }

    .footer-content{
        grid-template-columns:
            1fr 1fr;
    }

}


@media(max-width:450px){

    .section-card{
        padding:20px 15px;
    }

    .account-nav{
        grid-template-columns:1fr;
    }

    .footer-content{
        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header>

    <div class="top-bar">

        <a
            href="index.php"
            class="logo"
        >

            <i class="fas fa-utensils"></i>

            <h1>
                Humsafar
            </h1>

        </a>


        <div class="search-bar">

            <input
                type="text"
                placeholder="Search for restaurants or food..."
            >

            <i class="fas fa-search"></i>

        </div>


        <div class="user-actions">

            <a
                href="cart.php"
                id="cart-btn"
            >

                <i class="fas fa-shopping-cart"></i>

                Cart

                <?php if ($cart_count > 0) { ?>

                    <span class="cart-count">

                        <?php
                            echo $cart_count;
                        ?>

                    </span>

                <?php } ?>

            </a>


            <a
                href="logout.php"
                class="sign-in"
            >

                Logout

            </a>

        </div>

    </div>


    <nav>

        <ul>

            <li>
                <a href="index.php">
                    Home
                </a>
            </li>

            <li>
                <a href="restaurants.php">
                    Restaurants
                </a>
            </li>

            <li>
                <a href="deals.php">
                    Deals
                </a>
            </li>

            <li>
                <a
                    href="my-account.php"
                    class="active"
                >
                    My Account
                </a>
            </li>

            <li>
                <a href="#">
                    Help
                </a>
            </li>



        </ul>

    </nav>

</header>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="account-page">


    <div class="account-page-header">

        <h1>

            <i class="fas fa-user-circle"></i>

            My Account

        </h1>

        <p>
            Manage your profile, delivery addresses and payment methods.
        </p>

    </div>


    <?php if ($success_message !== '') { ?>

        <div class="account-message account-success">

            <i class="fas fa-check-circle"></i>

            <?php
                echo h(
                    $success_message
                );
            ?>

        </div>

    <?php } ?>


    <?php if ($error_message !== '') { ?>

        <div class="account-message account-error">

            <i class="fas fa-exclamation-circle"></i>

            <?php
                echo h(
                    $error_message
                );
            ?>

        </div>

    <?php } ?>


    <div class="account-layout">


        <!-- =================================================
             SIDEBAR
        ================================================= -->

        <aside class="account-sidebar">


            <div class="profile-mini">

                <div class="profile-avatar">

                    <i class="fas fa-user"></i>

                </div>


                <h3>

                    <?php
                        echo h(
                            $user_name
                        );
                    ?>

                </h3>


                <p>

                    <?php
                        echo h(
                            $user_email
                        );
                    ?>

                </p>


                <span class="member-since">

                    Member since
                    <?php
                        echo h(
                            $member_year
                        );
                    ?>

                </span>

            </div>


            <div class="account-nav">


                <button
                    type="button"
                    class="active"
                    data-section="profile-section"
                >

                    <i class="fas fa-user"></i>

                    Profile

                </button>

                
                <button
                     type="button"
                     data-section="orders-section"
>

                    <i class="fas fa-box"></i>

                     My Orders

                </button>


                <button
                    type="button"
                    data-section="addresses-section"
                >

                    <i class="fas fa-location-dot"></i>

                    My Addresses

                </button>


                <button
                    type="button"
                    data-section="payments-section"
                >

                    <i class="fas fa-credit-card"></i>

                    Payment Methods

                </button>


                <button
                    type="button"
                    onclick="window.location.href='cart.php'"
                >

                    <i class="fas fa-shopping-cart"></i>

                    My Cart

                </button>


                <button
                    type="button"
                    onclick="window.location.href='logout.php'"
                >

                    <i class="fas fa-right-from-bracket"></i>

                    Logout

                </button>


            </div>


        </aside>


        <!-- =================================================
             CONTENT
        ================================================= -->

        <section class="account-content">


            <!-- =============================================
                 PROFILE
            ============================================= -->

            <div
                class="account-section active"
                id="profile-section"
            >


                <div class="section-card">


                    <div class="section-card-header">

                        <div>

                            <h2>
                                Profile Information
                            </h2>

                            <p>
                                Your details from your Humsafar account.
                            </p>

                        </div>

                    </div>


                    <form
                        method="POST"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >


                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    name="full_name"
                                    class="form-control"
                                    value="<?php
                                        echo h(
                                            $user_name
                                        );
                                    ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Phone Number
                                </label>

                                <input
                                    type="tel"
                                    name="phone"
                                    class="form-control"
                                    value="<?php
                                        echo h(
                                            $user_phone
                                        );
                                    ?>"
                                    required
                                >

                            </div>


                            <div class="form-group full">

                                <label>
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="<?php
                                        echo h(
                                            $user_email
                                        );
                                    ?>"
                                    required
                                >

                            </div>


                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-save"></i>

                            Save Changes

                        </button>

                    </form>


                </div>


            </div>


            <!-- =============================================
                 MY ORDERS
            ============================================= -->

            <div
                class="account-section"
                id="orders-section"
            >

                <div class="section-card">

                    <div class="section-card-header">

                        <div>

                            <h2>
                                My Orders
                            </h2>

                            <p>
                                View your placed orders and their current status.
                            </p>

                        </div>

                    </div>


                    <?php if (!empty($orders)) { ?>

                        <div class="orders-list">

                            <?php foreach ($orders as $order) { ?>

                                <?php

                                $order_status =
                                    strtolower(
                                        trim(
                                            $order['order_status']
                                            ?? 'pending'
                                        )
                                    );

                                $status_label =
                                    ucwords(
                                        str_replace(
                                            ['_', '-'],
                                            ' ',
                                            $order_status
                                        )
                                    );

                                $status_icon = 'fa-circle-info';

                                if ($order_status === 'pending') {
                                    $status_icon = 'fa-clock';
                                } elseif (
                                    $order_status === 'confirmed'
                                    ||
                                    $order_status === 'accepted'
                                ) {
                                    $status_icon = 'fa-circle-check';
                                } elseif (
                                    $order_status === 'preparing'
                                ) {
                                    $status_icon = 'fa-kitchen-set';
                                } elseif (
                                    $order_status === 'out_for_delivery'
                                    ||
                                    $order_status === 'on_the_way'
                                    ||
                                    $order_status === 'out-for-delivery'
                                ) {
                                    $status_icon = 'fa-motorcycle';
                                } elseif (
                                    $order_status === 'delivered'
                                    ||
                                    $order_status === 'completed'
                                ) {
                                    $status_icon = 'fa-check-double';
                                } elseif (
                                    $order_status === 'cancelled'
                                    ||
                                    $order_status === 'canceled'
                                ) {
                                    $status_icon = 'fa-circle-xmark';
                                }

                                $restaurant_name =
                                    $order['restaurant_name']
                                    ?? 'Restaurant';

                                $order_number =
                                    $order['order_number']
                                    ?? $order['id'];

                                $order_total =
                                    (float)(
                                        $order['total']
                                        ?? 0
                                    );

                                ?>


                                <div class="account-order-card">

                                    <div class="account-order-top">

                                        <div class="account-order-title">

                                            <h3>
                                                Order #<?php
                                                    echo h(
                                                        $order_number
                                                    );
                                                ?>
                                            </h3>

                                            <p>
                                                <i class="fas fa-store"></i>
                                                <?php
                                                    echo h(
                                                        $restaurant_name
                                                    );
                                                ?>
                                            </p>

                                        </div>

                                        <span
                                            class="account-order-status <?php
                                                echo h(
                                                    $order_status
                                                );
                                            ?>"
                                        >

                                            <i
                                                class="fas <?php
                                                    echo h(
                                                        $status_icon
                                                    );
                                                ?>"
                                            ></i>

                                            <?php
                                                echo h(
                                                    $status_label
                                                );
                                            ?>

                                        </span>

                                    </div>


                                    <div class="account-order-meta">

                                        <div class="account-order-meta-item">
                                            <span>Order Date</span>
                                            <strong>
                                                <?php
                                                if (!empty($order['created_at'])) {
                                                    echo h(
                                                        date(
                                                            'd M Y, h:i A',
                                                            strtotime(
                                                                $order['created_at']
                                                            )
                                                        )
                                                    );
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </strong>
                                        </div>

                                        <div class="account-order-meta-item">
                                            <span>Payment</span>
                                            <strong>
                                                <?php
                                                    echo h(
                                                        ucwords(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                $order['payment_method']
                                                                ?? '-'
                                                            )
                                                        )
                                                    );
                                                ?>
                                            </strong>
                                        </div>

                                        <div class="account-order-meta-item">
                                            <span>Total</span>
                                            <strong>
                                                Rs.
                                                <?php
                                                    echo number_format(
                                                        $order_total,
                                                        2
                                                    );
                                                ?>
                                            </strong>
                                        </div>

                                    </div>


                                    <div class="account-order-bottom">

                                        <div class="account-order-total">
                                            Order Status: <?php
                                                echo h(
                                                    $status_label
                                                );
                                            ?>
                                        </div>

                                        <a
                                            href="my_orders.php"
                                            class="account-order-view"
                                        >
                                            <i class="fas fa-eye"></i>
                                            View Orders
                                        </a>

                                    </div>

                                </div>

                            <?php } ?>

                        </div>

                    <?php } else { ?>

                        <div class="empty-box">

                            <i class="fas fa-box-open"></i>

                            <h3>
                                No Orders Yet
                            </h3>

                            <p>
                                Your placed orders will appear here.
                            </p>

                            <a
                                href="restaurants.php"
                                class="btn btn-primary"
                            >
                                <i class="fas fa-utensils"></i>
                                Browse Restaurants
                            </a>

                        </div>

                    <?php } ?>


                </div>

            </div>


            <!-- =============================================
                 ADDRESSES
            ============================================= -->

            <div
                class="account-section"
                id="addresses-section"
            >


                <div class="section-card">


                    <div class="section-card-header">

                        <div>

                            <h2>
                                Delivery Addresses
                            </h2>

                            <p>
                                Add and manage your delivery addresses.
                            </p>

                        </div>


                        <button
                            type="button"
                            class="btn btn-primary"
                            onclick="openAddressModal()"
                        >

                            <i class="fas fa-plus"></i>

                            Add Address

                        </button>

                    </div>


                    <?php if (!empty($addresses)) { ?>


                        <div class="address-list">


                            <?php foreach (
                                $addresses
                                as $address
                            ) { ?>


                                <div
                                    class="address-card <?php
                                        echo $address['is_default']
                                            ? 'default'
                                            : '';
                                    ?>"
                                >


                                    <div class="address-main">


                                        <div class="address-title">

                                            <h3>

                                                <?php
                                                    echo h(
                                                        $address['address_title']
                                                    );
                                                ?>

                                            </h3>


                                            <?php
                                            if (
                                                $address['is_default']
                                            ) {
                                            ?>

                                                <span class="default-badge">

                                                    DEFAULT

                                                </span>

                                            <?php } ?>

                                        </div>


                                        <p>

                                            <i class="fas fa-location-dot"></i>

                                            <?php
                                                echo h(
                                                    $address['address_line']
                                                );
                                            ?>

                                        </p>


                                        <p>

                                            <?php

                                            if (
                                                $address['area'] !== ''
                                            ) {

                                                echo
                                                    h(
                                                        $address['area']
                                                    )
                                                    . ', ';

                                            }

                                            echo
                                                h(
                                                    $address['city']
                                                );

                                            ?>

                                        </p>


                                        <?php
                                        if (
                                            $address['phone'] !== ''
                                        ) {
                                        ?>

                                            <p>

                                                <i class="fas fa-phone"></i>

                                                <?php
                                                    echo h(
                                                        $address['phone']
                                                    );
                                                ?>

                                            </p>

                                        <?php } ?>


                                    </div>


                                    <div class="address-actions">


                                        <?php
                                        if (
                                            !$address['is_default']
                                        ) {
                                        ?>

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="default_address"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="address_id"
                                                    value="<?php
                                                        echo (int)
                                                            $address['id'];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-light btn-small"
                                                >

                                                    Make Default

                                                </button>

                                            </form>

                                        <?php } ?>


                                        <button
                                            type="button"
                                            class="btn btn-light btn-small"
                                            onclick='editAddress(
                                                <?php
                                                    echo json_encode(
                                                        $address,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_HEX_AMP
                                                    );
                                                ?>
                                            )'
                                        >

                                            <i class="fas fa-pen"></i>

                                            Edit

                                        </button>


                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Remove this address?'
                                                );
                                            "
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
                                                        $address['id'];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger btn-small"
                                            >

                                                <i class="fas fa-trash"></i>

                                                Remove

                                            </button>

                                        </form>


                                    </div>


                                </div>


                            <?php } ?>


                        </div>


                    <?php } else { ?>


                        <div class="empty-box">

                            <i class="fas fa-location-dot"></i>

                            <h3>
                                No addresses added yet
                            </h3>

                            <p>
                                Add your delivery address to make checkout faster.
                            </p>

                        </div>


                    <?php } ?>


                </div>


            </div>


            <!-- =============================================
                 PAYMENTS
            ============================================= -->

            <div
                class="account-section"
                id="payments-section"
            >


                <div class="section-card">


                    <div class="section-card-header">

                        <div>

                            <h2>
                                Payment Methods
                            </h2>

                            <p>
                                Manage your saved payment methods.
                            </p>

                        </div>

                    </div>


                    <!-- PAYMENT METHOD -->

                    <form
                        method="POST"
                        id="payment-form"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="add_payment"
                        >


                        <div class="payment-methods">


                            <div class="payment-method-option">

                                <input
                                    type="radio"
                                    id="payment-wallet"
                                    name="payment_method"
                                    value="wallet"
                                    checked
                                >

                                <label
                                    for="payment-wallet"
                                >

                                    <i class="fas fa-wallet"></i>

                                    Digital Wallet

                                </label>

                            </div>


                            <div class="payment-method-option">

                                <input
                                    type="radio"
                                    id="payment-bank"
                                    name="payment_method"
                                    value="bank"
                                >

                                <label
                                    for="payment-bank"
                                >

                                    <i class="fas fa-building-columns"></i>

                                    Bank

                                </label>

                            </div>


                            <div class="payment-method-option">

                                <input
                                    type="radio"
                                    id="payment-card"
                                    name="payment_method"
                                    value="card"
                                >

                                <label
                                    for="payment-card"
                                >

                                    <i class="fas fa-credit-card"></i>

                                    Card

                                </label>

                            </div>


                        </div>


                        <!-- =================================
                             WALLET PROVIDER
                        ================================= -->

                        <div
                            class="provider-box active"
                            id="wallet-provider"
                        >

                            <label>
                                Wallet
                            </label>

                            <select
                                name="provider_wallet"
                                id="wallet-select"
                                class="form-control"
                            >

                                <option value="">
                                    Select Wallet
                                </option>

                                <option value="Easypaisa">
                                    Easypaisa
                                </option>

                                <option value="JazzCash">
                                    JazzCash
                                </option>

                                <option value="NayaPay">
                                    NayaPay
                                </option>

                                <option value="SadaPay">
                                    SadaPay
                                </option>

                                <option value="UPaisa">
                                    UPaisa
                                </option>

                                <option value="HBL Konnect">
                                    HBL Konnect
                                </option>

                                <option value="Zindigi">
                                    Zindigi
                                </option>

                            </select>

                            <input
                                type="hidden"
                                name="provider"
                                id="provider-value"
                                value=""
                            >

                        </div>


                        <!-- =================================
                             BANK PROVIDER
                        ================================= -->

                        <div
                            class="provider-box"
                            id="bank-provider"
                        >

                            <label>
                                Bank
                            </label>

                            <select
                                id="bank-select"
                                class="form-control"
                            >

                                <option value="">
                                    Select Bank
                                </option>

                                <option value="HBL">
                                    HBL
                                </option>

                                <option value="UBL">
                                    UBL
                                </option>

                                <option value="Meezan Bank">
                                    Meezan Bank
                                </option>

                                <option value="MCB Bank">
                                    MCB Bank
                                </option>

                                <option value="Allied Bank">
                                    Allied Bank
                                </option>

                                <option value="Bank Alfalah">
                                    Bank Alfalah
                                </option>

                                <option value="Bank Al Habib">
                                    Bank Al Habib
                                </option>

                                <option value="Faysal Bank">
                                    Faysal Bank
                                </option>

                                <option value="Askari Bank">
                                    Askari Bank
                                </option>

                                <option value="Habib Metropolitan Bank">
                                    Habib Metropolitan Bank
                                </option>

                                <option value="Standard Chartered">
                                    Standard Chartered
                                </option>

                                <option value="National Bank of Pakistan">
                                    National Bank of Pakistan
                                </option>

                            </select>

                        </div>


                        <!-- =================================
                             CARD PROVIDER / TYPE
                        ================================= -->

                        <div
                            class="provider-box"
                            id="card-provider"
                        >

                            <label>
                                Card
                            </label>

                            <select
                                id="card-select"
                                class="form-control"
                            >

                                <option value="">
                                    Select Card Type
                                </option>

                                <option value="Visa Card">
                                    Visa Card
                                </option>

                                <option value="Debit Card">
                                    Debit Card
                                </option>

                                <option value="Credit Card">
                                    Credit Card
                                </option>

                            </select>

                        </div>


                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Account Holder Name
                                </label>

                                <input
                                    type="text"
                                    name="account_holder"
                                    class="form-control"
                                    placeholder="Enter account holder name"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Account / Card Number
                                </label>

                                <input
                                    type="text"
                                    name="account_number"
                                    id="account-number"
                                    class="form-control"
                                    placeholder="Enter account number"
                                    required
                                >

                            </div>


                            <!-- CARD ONLY -->

                            <div
                                class="form-group card-only"
                                style="display:none;"
                            >

                                <label>
                                    Expiry Month
                                </label>

                                <select
                                    name="expiry_month"
                                    class="form-control"
                                >

                                    <option value="">
                                        Month
                                    </option>

                                    <?php
                                    for (
                                        $month = 1;
                                        $month <= 12;
                                        $month++
                                    ) {
                                    ?>

                                        <option value="<?php
                                            echo str_pad(
                                                $month,
                                                2,
                                                '0',
                                                STR_PAD_LEFT
                                            );
                                        ?>">

                                            <?php
                                                echo str_pad(
                                                    $month,
                                                    2,
                                                    '0',
                                                    STR_PAD_LEFT
                                                );
                                            ?>

                                        </option>

                                    <?php } ?>

                                </select>

                            </div>


                            <div
                                class="form-group card-only"
                                style="display:none;"
                            >

                                <label>
                                    Expiry Year
                                </label>

                                <select
                                    name="expiry_year"
                                    class="form-control"
                                >

                                    <option value="">
                                        Year
                                    </option>

                                    <?php
                                    for (
                                        $year = date('Y');
                                        $year <= date('Y') + 12;
                                        $year++
                                    ) {
                                    ?>

                                        <option value="<?php
                                            echo $year;
                                        ?>">

                                            <?php
                                                echo $year;
                                            ?>

                                        </option>

                                    <?php } ?>

                                </select>

                            </div>


                            <div
                                class="form-group card-only"
                                style="display:none;"
                            >

                                <label>
                                    CVV Number
                                </label>

                                <input
                                    type="password"
                                    name="cvv"
                                    class="form-control"
                                    maxlength="4"
                                    placeholder="CVV"
                                    autocomplete="off"
                                >

                            </div>


                        </div>


                        <div
                            style="
                                display:flex;
                                align-items:center;
                                gap:8px;
                                margin-bottom:18px;
                            "
                        >

                            <input
                                type="checkbox"
                                name="is_default_payment"
                                id="default-payment"
                            >

                            <label
                                for="default-payment"
                                style="
                                    margin:0;
                                    font-size:13px;
                                    font-weight:600;
                                    color:#555;
                                "
                            >

                                Make this my default payment method

                            </label>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-plus"></i>

                            Add Payment Method

                        </button>


                    </form>


                </div>


                <!-- =========================================
                     SAVED PAYMENT METHODS
                ========================================= -->

                <div class="section-card">


                    <div class="section-card-header">

                        <div>

                            <h2>
                                Saved Payment Methods
                            </h2>

                            <p>
                                Your saved wallet, bank and card methods.
                            </p>

                        </div>

                    </div>


                    <?php if (!empty($payments)) { ?>


                        <div class="payment-list">


                            <?php foreach (
                                $payments
                                as $payment
                            ) { ?>


                                <?php

                                if (
                                    $payment['payment_method']
                                    === 'wallet'
                                ) {

                                    $payment_icon =
                                        'fa-wallet';

                                    $payment_type =
                                        'Digital Wallet';

                                    $payment_number =
                                        'Account ending ' .
                                        substr(
                                            $payment['account_number'],
                                            -4
                                        );

                                } elseif (
                                    $payment['payment_method']
                                    === 'bank'
                                ) {

                                    $payment_icon =
                                        'fa-building-columns';

                                    $payment_type =
                                        'Bank';

                                    $payment_number =
                                        'Account ending ' .
                                        substr(
                                            $payment['account_number'],
                                            -4
                                        );

                                } else {

                                    $payment_icon =
                                        'fa-credit-card';

                                    $payment_type =
                                        $payment['card_type']
                                        ?: 'Card';

                                    $payment_number =
                                        'Card ending ****' .
                                        h(
                                            $payment['card_last4']
                                        );

                                }

                                ?>


                                <div
                                    class="payment-card <?php
                                        echo $payment['is_default']
                                            ? 'default'
                                            : '';
                                    ?>"
                                >


                                    <div class="payment-left">


                                        <div class="payment-icon">

                                            <i
                                                class="fas <?php
                                                    echo $payment_icon;
                                                ?>"
                                            ></i>

                                        </div>


                                        <div class="payment-info">


                                            <h3>

                                                <?php
                                                    echo h(
                                                        $payment['provider']
                                                    );
                                                ?>

                                                <?php
                                                if (
                                                    $payment['is_default']
                                                ) {
                                                ?>

                                                    <span class="default-badge">

                                                        DEFAULT

                                                    </span>

                                                <?php } ?>

                                            </h3>


                                            <p>

                                                <?php
                                                    echo h(
                                                        $payment_type
                                                    );
                                                ?>

                                                •
                                                <?php
                                                    echo h(
                                                        $payment_number
                                                    );
                                                ?>

                                            </p>


                                            <p>

                                                Account Holder:
                                                <?php
                                                    echo h(
                                                        $payment['account_holder']
                                                    );
                                                ?>

                                            </p>


                                            <?php
                                            if (
                                                $payment['payment_method']
                                                === 'card'
                                            ) {
                                            ?>

                                                <p>

                                                    Expiry:
                                                    <?php
                                                        echo h(
                                                            $payment['expiry_month']
                                                        );
                                                    ?>
                                                    /
                                                    <?php
                                                        echo h(
                                                            $payment['expiry_year']
                                                        );
                                                    ?>

                                                </p>

                                            <?php } ?>


                                        </div>


                                    </div>


                                    <div class="payment-actions">


                                        <?php
                                        if (
                                            !$payment['is_default']
                                        ) {
                                        ?>

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="default_payment"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="payment_id"
                                                    value="<?php
                                                        echo (int)
                                                            $payment['id'];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-light btn-small"
                                                >

                                                    Make Default

                                                </button>

                                            </form>

                                        <?php } ?>


                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Remove this payment method?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete_payment"
                                            >

                                            <input
                                                type="hidden"
                                                name="payment_id"
                                                value="<?php
                                                    echo (int)
                                                        $payment['id'];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-danger btn-small"
                                            >

                                                <i class="fas fa-trash"></i>

                                                Remove

                                            </button>

                                        </form>


                                    </div>


                                </div>


                            <?php } ?>


                        </div>


                    <?php } else { ?>


                        <div class="empty-box">

                            <i class="fas fa-credit-card"></i>

                            <h3>
                                No payment methods saved
                            </h3>

                            <p>
                                Add your preferred wallet, bank or card above.
                            </p>

                        </div>


                    <?php } ?>


                </div>


            </div>


        </section>


    </div>


</main>


<!-- =====================================================
     ADDRESS MODAL
===================================================== -->

<div
    class="modal"
    id="address-modal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h2 id="address-modal-title">
                Add Delivery Address
            </h2>

            <button
                type="button"
                class="close-modal"
                onclick="closeAddressModal()"
            >

                <i class="fas fa-times"></i>

            </button>

        </div>


        <form
            method="POST"
            id="address-form"
        >


            <div class="modal-body">


                <input
                    type="hidden"
                    name="action"
                    id="address-action"
                    value="add_address"
                >


                <input
                    type="hidden"
                    name="address_id"
                    id="address-id"
                    value=""
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label>
                            Address Title
                        </label>

                        <input
                            type="text"
                            name="address_title"
                            id="address-title"
                            class="form-control"
                            placeholder="Home, Office..."
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Phone Number
                        </label>

                        <input
                            type="tel"
                            name="address_phone"
                            id="address-phone"
                            class="form-control"
                            placeholder="03XXXXXXXXX"
                        >

                    </div>


                    <div class="form-group full">

                        <label>
                            Complete Address
                        </label>

                        <textarea
                            name="address_line"
                            id="address-line"
                            class="form-control"
                            placeholder="House / Flat / Street / Block..."
                            required
                        ></textarea>

                    </div>


                    <div class="form-group">

                        <label>
                            Area
                        </label>

                        <input
                            type="text"
                            name="area"
                            id="address-area"
                            class="form-control"
                            placeholder="Area / Town"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            City
                        </label>

                        <input
                            type="text"
                            name="city"
                            id="address-city"
                            class="form-control"
                            placeholder="City"
                            required
                        >

                    </div>


                    <div
                        class="form-group full"
                        style="margin-bottom:0;"
                    >

                        <label
                            style="
                                display:flex;
                                align-items:center;
                                gap:8px;
                                font-weight:600;
                            "
                        >

                            <input
                                type="checkbox"
                                name="is_default"
                                id="address-default"
                            >

                            Make this my default address

                        </label>

                    </div>


                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeAddressModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fas fa-save"></i>

                    Save Address

                </button>


            </div>


        </form>


    </div>


</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer>


    <div class="footer-content">


        <div class="footer-column">

            <h3>
                Humsafar
            </h3>

            <ul>

                <li>
                    <a href="#">
                        About Us
                    </a>
                </li>

                <li>
                    <a href="#">
                        Careers
                    </a>
                </li>

                <li>
                    <a href="#">
                        Press
                    </a>
                </li>

                <li>
                    <a href="#">
                        Blog
                    </a>
                </li>

            </ul>

        </div>


        <div class="footer-column">

            <h3>
                For Foodies
            </h3>

            <ul>

                <li>
                    <a href="#">
                        Code of Conduct
                    </a>
                </li>

                <li>
                    <a href="#">
                        Community
                    </a>
                </li>

                <li>
                    <a href="#">
                        Blogger Help
                    </a>
                </li>

                <li>
                    <a href="#">
                        Mobile Apps
                    </a>
                </li>

            </ul>

        </div>


       

        <div class="footer-column">

            <h3>
                Contact Us
            </h3>

            <ul>

                <li>
                    <a href="#">
                        Help & Support
                    </a>
                </li>

                <li>
                    <a href="#">
                        Partner with us
                    </a>
                </li>


            </ul>


            <div class="social-icons">

                <a href="#">
                    <i class="fab fa-facebook"></i>
                </a>

                <a href="#">
                    <i class="fab fa-twitter"></i>
                </a>

                <a href="#">
                    <i class="fab fa-instagram"></i>
                </a>

                <a href="#">
                    <i class="fab fa-youtube"></i>
                </a>

            </div>

        </div>


    </div>


    <div class="copyright">

        <p>

            &copy;
            <?php
                echo date('Y');
            ?>

            Humsafar Food Delivery.
            All rights reserved.

        </p>

    </div>


</footer>


<script>

/* =====================================================
   ACCOUNT TABS
===================================================== */

document
    .querySelectorAll(
        '.account-nav button[data-section]'
    )
    .forEach(function(button){

        button.addEventListener(
            'click',
            function(){

                document
                    .querySelectorAll(
                        '.account-nav button[data-section]'
                    )
                    .forEach(function(btn){

                        btn.classList.remove(
                            'active'
                        );

                    });


                document
                    .querySelectorAll(
                        '.account-section'
                    )
                    .forEach(function(section){

                        section.classList.remove(
                            'active'
                        );

                    });


                button.classList.add(
                    'active'
                );


                var section =
                    document.getElementById(
                        button.getAttribute(
                            'data-section'
                        )
                    );


                if (section) {

                    section.classList.add(
                        'active'
                    );

                }

            }
        );

    });


/* =====================================================
   ADDRESS MODAL
===================================================== */

function openAddressModal(){

    document
        .getElementById(
            'address-modal'
        )
        .classList.add(
            'show'
        );


    document
        .getElementById(
            'address-modal-title'
        )
        .textContent =
            'Add Delivery Address';


    document
        .getElementById(
            'address-action'
        )
        .value =
            'add_address';


    document
        .getElementById(
            'address-id'
        )
        .value =
            '';


    document
        .getElementById(
            'address-title'
        )
        .value =
            '';


    document
        .getElementById(
            'address-line'
        )
        .value =
            '';


    document
        .getElementById(
            'address-city'
        )
        .value =
            '';


    document
        .getElementById(
            'address-area'
        )
        .value =
            '';


    document
        .getElementById(
            'address-phone'
        )
        .value =
            '';


    document
        .getElementById(
            'address-default'
        )
        .checked =
            false;

}


function editAddress(address){

    document
        .getElementById(
            'address-modal'
        )
        .classList.add(
            'show'
        );


    document
        .getElementById(
            'address-modal-title'
        )
        .textContent =
            'Edit Delivery Address';


    document
        .getElementById(
            'address-action'
        )
        .value =
            'update_address';


    document
        .getElementById(
            'address-id'
        )
        .value =
            address.id;


    document
        .getElementById(
            'address-title'
        )
        .value =
            address.address_title
            || '';


    document
        .getElementById(
            'address-line'
        )
        .value =
            address.address_line
            || '';


    document
        .getElementById(
            'address-city'
        )
        .value =
            address.city
            || '';


    document
        .getElementById(
            'address-area'
        )
        .value =
            address.area
            || '';


    document
        .getElementById(
            'address-phone'
        )
        .value =
            address.phone
            || '';


    document
        .getElementById(
            'address-default'
        )
        .checked =
            address.is_default == 1;

}


function closeAddressModal(){

    document
        .getElementById(
            'address-modal'
        )
        .classList.remove(
            'show'
        );

}


window.addEventListener(
    'click',
    function(event){

        var modal =
            document.getElementById(
                'address-modal'
            );


        if (
            modal &&
            event.target === modal
        ){

            closeAddressModal();

        }

    }
);


/* =====================================================
   PAYMENT DYNAMIC FIELDS
===================================================== */

var paymentRadios =
    document.querySelectorAll(
        'input[name="payment_method"]'
    );


var walletBox =
    document.getElementById(
        'wallet-provider'
    );

var bankBox =
    document.getElementById(
        'bank-provider'
    );

var cardBox =
    document.getElementById(
        'card-provider'
    );

var walletSelect =
    document.getElementById(
        'wallet-select'
    );

var bankSelect =
    document.getElementById(
        'bank-select'
    );

var cardSelect =
    document.getElementById(
        'card-select'
    );

var providerValue =
    document.getElementById(
        'provider-value'
    );

var cardOnlyFields =
    document.querySelectorAll(
        '.card-only'
    );

var accountNumber =
    document.getElementById(
        'account-number'
    );


function updatePaymentFields(){

    var selected =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (!selected) {

        return;

    }


    var method =
        selected.value;


    walletBox
        .classList.remove(
            'active'
        );

    bankBox
        .classList.remove(
            'active'
        );

    cardBox
        .classList.remove(
            'active'
        );


    cardOnlyFields.forEach(
        function(field){

            field.style.display =
                'none';

        }
    );


    if (
        method === 'wallet'
    ){

        walletBox
            .classList.add(
                'active'
            );

        accountNumber
            .placeholder =
                'Enter wallet account number';

        accountNumber
            .inputMode =
                'numeric';

    }


    if (
        method === 'bank'
    ){

        bankBox
            .classList.add(
                'active'
            );

        accountNumber
            .placeholder =
                'Enter bank account number';

        accountNumber
            .inputMode =
                'numeric';

    }


    if (
        method === 'card'
    ){

        cardBox
            .classList.add(
                'active'
            );

        cardOnlyFields.forEach(
            function(field){

                field.style.display =
                    'block';

            }
        );

        accountNumber
            .placeholder =
                'Enter card number';

        accountNumber
            .inputMode =
                'numeric';

    }


    syncProvider();

}


function syncProvider(){

    var selected =
        document.querySelector(
            'input[name="payment_method"]:checked'
        );


    if (!selected) {

        return;

    }


    var method =
        selected.value;


    if (
        method === 'wallet'
    ){

        providerValue.value =
            walletSelect.value;

    }


    if (
        method === 'bank'
    ){

        providerValue.value =
            bankSelect.value;

    }


    if (
        method === 'card'
    ){

        providerValue.value =
            cardSelect.value;

    }

}


paymentRadios.forEach(
    function(radio){

        radio.addEventListener(
            'change',
            updatePaymentFields
        );

    }
);


walletSelect.addEventListener(
    'change',
    syncProvider
);


bankSelect.addEventListener(
    'change',
    syncProvider
);


cardSelect.addEventListener(
    'change',
    syncProvider
);


document
    .getElementById(
        'payment-form'
    )
    .addEventListener(
        'submit',
        function(event){

            syncProvider();


            if (
                providerValue.value === ''
            ){

                event.preventDefault();

                alert(
                    'Please select your payment provider.'
                );

            }

        }
    );


updatePaymentFields();


/* =====================================================
   AUTO SHOW SECTION AFTER POST
===================================================== */

<?php if (
    isset($_POST['action'])
    &&
    in_array(
        $_POST['action'],
        [
            'add_address',
            'update_address',
            'delete_address',
            'default_address'
        ],
        true
    )
) { ?>

document
    .querySelectorAll(
        '.account-nav button'
    )
    .forEach(
        function(button){

            button.classList.remove(
                'active'
            );

        }
    );

document
    .querySelectorAll(
        '.account-section'
    )
    .forEach(
        function(section){

            section.classList.remove(
                'active'
            );

        }
    );

document
    .querySelector(
        '[data-section="addresses-section"]'
    )
    .classList.add(
        'active'
    );

document
    .getElementById(
        'addresses-section'
    )
    .classList.add(
        'active'
    );

<?php } ?>


<?php if (
    isset($_POST['action'])
    &&
    in_array(
        $_POST['action'],
        [
            'add_payment',
            'delete_payment',
            'default_payment'
        ],
        true
    )
) { ?>

document
    .querySelectorAll(
        '.account-nav button'
    )
    .forEach(
        function(button){

            button.classList.remove(
                'active'
            );

        }
    );

document
    .querySelectorAll(
        '.account-section'
    )
    .forEach(
        function(section){

            section.classList.remove(
                'active'
            );

        }
    );

document
    .querySelector(
        '[data-section="payments-section"]'
    )
    .classList.add(
        'active'
    );

document
    .getElementById(
        'payments-section'
    )
    .classList.add(
        'active'
    );

<?php } ?>

</script>


</body>

</html>