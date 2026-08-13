<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   LOGIN CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}


$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   HELPER
========================================================= */

function paymentH($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   USER
========================================================= */

$userStmt = $conn->prepare("
    SELECT
        id,
        full_name,
        email,
        phone
    FROM users
    WHERE id = ?
    LIMIT 1
");


if (!$userStmt) {
    die("Database error: " . $conn->error);
}


$userStmt->bind_param(
    "i",
    $user_id
);


$userStmt->execute();


$userResult =
    $userStmt->get_result();


$customer =
    $userResult->fetch_assoc();


$userStmt->close();


if (!$customer) {

    session_destroy();

    header("Location: login.php");

    exit;

}


/* =========================================================
   MESSAGES
========================================================= */

$successMessage = '';
$errorMessage = '';


/* =========================================================
   ADD / SAVE PAYMENT METHOD
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['save_payment_method'])
) {

    $methodType =
        isset($_POST['method_type'])
        ? trim($_POST['method_type'])
        : 'cash_on_delivery';


    $title =
        isset($_POST['title'])
        ? trim($_POST['title'])
        : '';


    $accountName =
        isset($_POST['account_name'])
        ? trim($_POST['account_name'])
        : '';


    $accountLast4 =
        isset($_POST['account_last4'])
        ? trim($_POST['account_last4'])
        : '';


    $makeDefault =
        isset($_POST['is_default'])
        ? 1
        : 0;


    /* =====================================================
       VALIDATE METHOD
    ===================================================== */

    $allowedMethods = array(
        'cash_on_delivery',
        'online'
    );


    if (
        !in_array(
            $methodType,
            $allowedMethods,
            true
        )
    ) {

        $methodType =
            'cash_on_delivery';

    }


    /*
     * Online payment is not live yet.
     * It will be connected with JazzCash
     * after merchant credentials are available.
     */

    if ($methodType === 'online') {

        $errorMessage =
            "Online payment is coming soon. "
            . "JazzCash gateway will be connected after "
            . "merchant approval.";

    }


    if (
        $methodType === 'cash_on_delivery'
        &&
        $title === ''
    ) {

        $title =
            'Cash on Delivery';

    }


    /* =====================================================
       SAVE COD
    ===================================================== */

    if (
        $errorMessage === ''
        &&
        $methodType === 'cash_on_delivery'
    ) {

        $conn->begin_transaction();


        try {

            /*
             * Remove previous default
             */

            if ($makeDefault === 1) {

                $resetStmt =
                    $conn->prepare("
                        UPDATE customer_payment_methods
                        SET is_default = 0
                        WHERE user_id = ?
                    ");


                if (!$resetStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                $resetStmt->bind_param(
                    "i",
                    $user_id
                );


                if (
                    !$resetStmt->execute()
                ) {

                    throw new Exception(
                        $resetStmt->error
                    );

                }


                $resetStmt->close();

            }


            /*
             * Check whether COD already exists
             */

            $checkStmt =
                $conn->prepare("
                    SELECT id
                    FROM customer_payment_methods
                    WHERE user_id = ?
                    AND method_type = 'cash_on_delivery'
                    LIMIT 1
                ");


            if (!$checkStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $checkStmt->bind_param(
                "i",
                $user_id
            );


            $checkStmt->execute();


            $checkResult =
                $checkStmt->get_result();


            $existing =
                $checkResult->fetch_assoc();


            $checkStmt->close();


            if ($existing) {

                /*
                 * Update existing COD
                 */

                $updateStmt =
                    $conn->prepare("
                        UPDATE customer_payment_methods
                        SET
                            title = ?,
                            account_name = ?,
                            is_default = ?,
                            status = 1
                        WHERE id = ?
                        AND user_id = ?
                    ");


                if (!$updateStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                $existingId =
                    (int) $existing['id'];


                $updateStmt->bind_param(
                    "ssiii",
                    $title,
                    $accountName,
                    $makeDefault,
                    $existingId,
                    $user_id
                );


                if (
                    !$updateStmt->execute()
                ) {

                    throw new Exception(
                        $updateStmt->error
                    );

                }


                $updateStmt->close();


            } else {

                /*
                 * Add new COD
                 */

                $insertStmt =
                    $conn->prepare("
                        INSERT INTO customer_payment_methods
                        (
                            user_id,
                            method_type,
                            provider,
                            title,
                            account_name,
                            account_last4,
                            is_default,
                            status
                        )
                        VALUES
                        (
                            ?,
                            'cash_on_delivery',
                            NULL,
                            ?,
                            ?,
                            NULL,
                            ?,
                            1
                        )
                    ");


                if (!$insertStmt) {
                    throw new Exception(
                        $conn->error
                    );
                }


                $insertStmt->bind_param(
                    "issi",
                    $user_id,
                    $title,
                    $accountName,
                    $makeDefault
                );


                if (
                    !$insertStmt->execute()
                ) {

                    throw new Exception(
                        $insertStmt->error
                    );

                }


                $insertStmt->close();

            }


            /*
             * If this is the only payment method,
             * automatically make it default.
             */

            $countStmt =
                $conn->prepare("
                    SELECT COUNT(*) AS total
                    FROM customer_payment_methods
                    WHERE user_id = ?
                    AND status = 1
                ");


            if (!$countStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $countStmt->bind_param(
                "i",
                $user_id
            );


            $countStmt->execute();


            $countResult =
                $countStmt->get_result();


            $countRow =
                $countResult->fetch_assoc();


            $countStmt->close();


            if (
                isset($countRow['total'])
                &&
                (int) $countRow['total'] === 1
            ) {

                $defaultStmt =
                    $conn->prepare("
                        UPDATE customer_payment_methods
                        SET is_default = 1
                        WHERE user_id = ?
                        AND status = 1
                    ");


                if ($defaultStmt) {

                    $defaultStmt->bind_param(
                        "i",
                        $user_id
                    );

                    $defaultStmt->execute();

                    $defaultStmt->close();

                }

            }


            $conn->commit();


            $successMessage =
                "Cash on Delivery has been saved.";


        } catch (Exception $e) {

            $conn->rollback();


            $errorMessage =
                "Unable to save payment method. "
                . "Please try again.";

        }

    }

}


/* =========================================================
   SET DEFAULT PAYMENT METHOD
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['set_default_payment'])
) {

    $paymentId =
        isset($_POST['payment_id'])
        ? (int) $_POST['payment_id']
        : 0;


    if ($paymentId > 0) {

        $conn->begin_transaction();


        try {

            /*
             * First verify ownership
             */

            $verifyStmt =
                $conn->prepare("
                    SELECT id
                    FROM customer_payment_methods
                    WHERE id = ?
                    AND user_id = ?
                    AND status = 1
                    LIMIT 1
                ");


            if (!$verifyStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $verifyStmt->bind_param(
                "ii",
                $paymentId,
                $user_id
            );


            $verifyStmt->execute();


            $verifyResult =
                $verifyStmt->get_result();


            if (
                $verifyResult->num_rows === 0
            ) {

                throw new Exception(
                    "Invalid payment method."
                );

            }


            $verifyStmt->close();


            /*
             * Remove old default
             */

            $resetStmt =
                $conn->prepare("
                    UPDATE customer_payment_methods
                    SET is_default = 0
                    WHERE user_id = ?
                ");


            if (!$resetStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $resetStmt->bind_param(
                "i",
                $user_id
            );


            if (
                !$resetStmt->execute()
            ) {

                throw new Exception(
                    $resetStmt->error
                );

            }


            $resetStmt->close();


            /*
             * Set selected default
             */

            $defaultStmt =
                $conn->prepare("
                    UPDATE customer_payment_methods
                    SET is_default = 1
                    WHERE id = ?
                    AND user_id = ?
                ");


            if (!$defaultStmt) {
                throw new Exception(
                    $conn->error
                );
            }


            $defaultStmt->bind_param(
                "ii",
                $paymentId,
                $user_id
            );


            if (
                !$defaultStmt->execute()
            ) {

                throw new Exception(
                    $defaultStmt->error
                );

            }


            $defaultStmt->close();


            $conn->commit();


            $successMessage =
                "Default payment method updated.";


        } catch (Exception $e) {

            $conn->rollback();


            $errorMessage =
                "Unable to update default payment method.";

        }

    }

}


/* =========================================================
   DELETE PAYMENT METHOD
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['delete_payment'])
) {

    $paymentId =
        isset($_POST['payment_id'])
        ? (int) $_POST['payment_id']
        : 0;


    if ($paymentId > 0) {

        /*
         * We do not physically delete payment records.
         * We deactivate them instead.
         */

        $deleteStmt =
            $conn->prepare("
                UPDATE customer_payment_methods
                SET
                    status = 0,
                    is_default = 0
                WHERE id = ?
                AND user_id = ?
            ");


        if ($deleteStmt) {

            $deleteStmt->bind_param(
                "ii",
                $paymentId,
                $user_id
            );


            if (
                $deleteStmt->execute()
            ) {

                $successMessage =
                    "Payment method removed.";

            } else {

                $errorMessage =
                    "Unable to remove payment method.";

            }


            $deleteStmt->close();

        } else {

            $errorMessage =
                "Database error.";

        }

    }

}


/* =========================================================
   GET PAYMENT METHODS
========================================================= */

$paymentMethods = array();


$paymentStmt =
    $conn->prepare("
        SELECT
            id,
            method_type,
            provider,
            title,
            account_name,
            account_last4,
            gateway_customer_id,
            gateway_payment_method_id,
            is_default,
            status,
            created_at
        FROM customer_payment_methods
        WHERE user_id = ?
        AND status = 1
        ORDER BY
            is_default DESC,
            id DESC
    ");


if ($paymentStmt) {

    $paymentStmt->bind_param(
        "i",
        $user_id
    );


    $paymentStmt->execute();


    $paymentResult =
        $paymentStmt->get_result();


    while (
        $row =
        $paymentResult->fetch_assoc()
    ) {

        $paymentMethods[] =
            $row;

    }


    $paymentStmt->close();

}


/* =========================================================
   CHECK WHETHER COD EXISTS
========================================================= */

$hasCod = false;


foreach (
    $paymentMethods
    as $method
) {

    if (
        $method['method_type']
        === 'cash_on_delivery'
    ) {

        $hasCod = true;

        break;

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
        Payments - Humsafar
    </title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

/* =========================================================
   PAGE
========================================================= */

.payment-page {

    max-width: 1100px;

    margin: 0 auto;

    padding:
        35px 25px
        70px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.payment-header {

    margin-bottom: 28px;
}


.payment-header h1 {

    margin: 0 0 8px;

    color: #E23744;

    font-size: 30px;

    font-weight: 700;
}


.payment-header p {

    margin: 0;

    color: #777777;

    font-size: 14px;

    line-height: 1.6;
}


/* =========================================================
   ALERT
========================================================= */

.payment-alert {

    padding:
        13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

    display: flex;

    align-items: center;

    gap: 9px;
}


.payment-alert.success {

    background: #eefaf2;

    color: #187642;

    border:
        1px solid #ccebd8;
}


.payment-alert.error {

    background: #fff1f3;

    color: #b4233a;

    border:
        1px solid #ffd2da;
}


/* =========================================================
   MAIN GRID
========================================================= */

.payment-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.5fr)
        minmax(300px, .9fr);

    gap: 22px;

    align-items: start;
}


/* =========================================================
   CARD
========================================================= */

.payment-card {

    background: #ffffff;

    border:
        1px solid #eeeeee;

    border-radius: 15px;

    box-shadow:
        0 6px 25px
        rgba(0,0,0,.05);

    overflow: hidden;
}


.payment-card-header {

    padding:
        20px 22px;

    border-bottom:
        1px solid #eeeeee;

    display: flex;

    align-items: center;

    gap: 12px;
}


.payment-card-header-icon {

    width: 40px;

    height: 40px;

    border-radius: 10px;

    background: #fff0f3;

    color: #E23744;

    display: flex;

    align-items: center;

    justify-content: center;
}


.payment-card-header h2 {

    margin: 0;

    color: #333333;

    font-size: 19px;

    font-weight: 700;
}


.payment-card-body {

    padding: 20px 22px;
}


/* =========================================================
   PAYMENT METHOD
========================================================= */

.payment-method {

    position: relative;

    border:
        2px solid #eeeeee;

    border-radius: 13px;

    padding: 17px;

    margin-bottom: 13px;

    transition: .2s ease;
}


.payment-method:hover {

    border-color: #f0a0aa;

    background: #fffafb;
}


.payment-method.default {

    border-color: #E23744;

    background: #fff7f8;
}


.payment-method-top {

    display: flex;

    align-items: center;

    gap: 13px;
}


.payment-method-icon {

    width: 45px;

    height: 45px;

    flex-shrink: 0;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #fff0f3;

    color: #E23744;

    font-size: 19px;
}


.payment-method-content {

    flex: 1;

    min-width: 0;
}


.payment-method-title {

    display: flex;

    align-items: center;

    flex-wrap: wrap;

    gap: 7px;

    margin-bottom: 4px;

    color: #333333;

    font-size: 15px;

    font-weight: 700;
}


.default-badge {

    display: inline-block;

    padding:
        4px 8px;

    border-radius: 20px;

    background: #E23744;

    color: #ffffff;

    font-size: 10px;

    font-weight: 700;
}


.payment-method-subtitle {

    margin: 0;

    color: #777777;

    font-size: 12px;

    line-height: 1.5;
}


/* =========================================================
   PAYMENT ACTIONS
========================================================= */

.payment-actions {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 14px;

    padding-top: 13px;

    border-top:
        1px solid #eeeeee;
}


.payment-action-btn {

    border: 0;

    background: transparent;

    cursor: pointer;

    padding:
        6px 8px;

    border-radius: 6px;

    color: #E23744;

    font-size: 12px;

    font-weight: 600;
}


.payment-action-btn:hover {

    background: #fff0f3;
}


.payment-action-btn.delete {

    color: #b4233a;
}


/* =========================================================
   ADD PAYMENT FORM
========================================================= */

.form-group {

    margin-bottom: 16px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #444444;

    font-size: 13px;

    font-weight: 600;
}


.form-control {

    width: 100%;

    box-sizing: border-box;

    height: 44px;

    padding:
        0 12px;

    border:
        1px solid #dddddd;

    border-radius: 8px;

    outline: none;

    color: #333333;

    font-size: 13px;

    background: #ffffff;
}


.form-control:focus {

    border-color: #E23744;

    box-shadow:
        0 0 0 3px
        rgba(226,55,68,.08);
}


/* =========================================================
   CHECKBOX
========================================================= */

.default-check {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 17px;

    color: #555555;

    font-size: 13px;
}


.default-check input {

    accent-color: #E23744;

    width: 16px;

    height: 16px;
}


/* =========================================================
   SAVE BUTTON
========================================================= */

.save-payment-btn {

    width: 100%;

    min-height: 44px;

    border: 0;

    border-radius: 8px;

    background: #E23744;

    color: #ffffff;

    cursor: pointer;

    font-size: 13px;

    font-weight: 700;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    transition: .2s ease;
}


.save-payment-btn:hover {

    background: #cf3040;
}


/* =========================================================
   ONLINE PAYMENT
========================================================= */

.online-payment-box {

    margin-top: 15px;

    padding: 15px;

    border:
        1px solid #eeeeee;

    border-radius: 10px;

    background: #fafafa;
}


.online-payment-icon {

    width: 42px;

    height: 42px;

    margin-bottom: 10px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #fff0f3;

    color: #E23744;

    font-size: 18px;
}


.online-payment-box h3 {

    margin:
        0 0 6px;

    color: #333333;

    font-size: 15px;
}


.online-payment-box p {

    margin: 0;

    color: #777777;

    font-size: 12px;

    line-height: 1.6;
}


.coming-soon {

    display: inline-block;

    margin-top: 10px;

    padding:
        5px 9px;

    border-radius: 20px;

    background: #eeeeee;

    color: #666666;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   INFO CARD
========================================================= */

.info-box {

    padding: 17px;

    border-radius: 11px;

    background: #fff8f9;

    border:
        1px solid #f5d3d8;

    margin-bottom: 17px;
}


.info-box h3 {

    margin:
        0 0 9px;

    color: #333333;

    font-size: 15px;
}


.info-box p {

    margin: 0;

    color: #666666;

    font-size: 12px;

    line-height: 1.7;
}


/* =========================================================
   SECURITY
========================================================= */

.security-list {

    list-style: none;

    margin: 0;

    padding: 0;
}


.security-list li {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    padding:
        9px 0;

    color: #666666;

    font-size: 12px;

    line-height: 1.5;
}


.security-list i {

    color: #E23744;

    margin-top: 2px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-payment {

    padding:
        35px 15px;

    text-align: center;
}


.empty-payment i {

    color: #E23744;

    font-size: 40px;

    margin-bottom: 12px;
}


.empty-payment h3 {

    margin:
        0 0 7px;

    color: #333333;

    font-size: 17px;
}


.empty-payment p {

    margin: 0;

    color: #777777;

    font-size: 12px;
}


/* =========================================================
   BACK
========================================================= */

.back-account {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 20px;

    color: #E23744;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}


.back-account:hover {

    color: #c72e3b;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .payment-grid {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 500px) {

    .payment-page {

        padding:
            25px 15px
            50px;
    }


    .payment-header h1 {

        font-size: 26px;
    }


    .payment-card-header,
    .payment-card-body {

        padding:
            17px;
    }

}

    </style>

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
*/

require_once
    __DIR__
    . '/includes/customer-header.php';

?>


<main class="payment-page">


    <!-- =====================================================
         BACK TO ACCOUNT
    ====================================================== -->

    <a
        href="my-account.php"
        class="back-account"
    >

        <i class="fas fa-arrow-left"></i>

        Back to My Account

    </a>


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="payment-header">

        <h1>
            Payment Methods
        </h1>

        <p>
            Manage your preferred payment method
            for Humsafar orders.
        </p>

    </div>


    <!-- =====================================================
         ALERT
    ====================================================== -->

    <?php if (
        $successMessage !== ''
    ) { ?>

        <div class="payment-alert success">

            <i class="fas fa-circle-check"></i>

            <?php
            echo paymentH(
                $successMessage
            );
            ?>

        </div>

    <?php } ?>


    <?php if (
        $errorMessage !== ''
    ) { ?>

        <div class="payment-alert error">

            <i class="fas fa-circle-exclamation"></i>

            <?php
            echo paymentH(
                $errorMessage
            );
            ?>

        </div>

    <?php } ?>


    <!-- =====================================================
         GRID
    ====================================================== -->

    <div class="payment-grid">


        <!-- =================================================
             SAVED METHODS
        ================================================== -->

        <section class="payment-card">


            <div class="payment-card-header">

                <div class="payment-card-header-icon">

                    <i class="fas fa-wallet"></i>

                </div>


                <h2>
                    Saved Payment Methods
                </h2>

            </div>


            <div class="payment-card-body">


                <?php if (
                    !empty($paymentMethods)
                ) { ?>


                    <?php foreach (
                        $paymentMethods
                        as $method
                    ) { ?>


                        <?php

                        $isCod =
                            $method['method_type']
                            === 'cash_on_delivery';

                        ?>


                        <div
                            class="
                                payment-method
                                <?php
                                echo (
                                    (int)
                                    $method['is_default']
                                    === 1
                                )
                                ? 'default'
                                : '';
                                ?>
                            "
                        >


                            <div class="payment-method-top">


                                <div class="payment-method-icon">

                                    <?php if (
                                        $isCod
                                    ) { ?>

                                        <i
                                            class="fas fa-money-bill-wave"
                                        ></i>

                                    <?php } else { ?>

                                        <i
                                            class="fas fa-credit-card"
                                        ></i>

                                    <?php } ?>

                                </div>


                                <div class="payment-method-content">


                                    <div class="payment-method-title">

                                        <?php
                                        echo paymentH(
                                            $method['title']
                                        );
                                        ?>


                                        <?php if (
                                            (int)
                                            $method['is_default']
                                            === 1
                                        ) { ?>

                                            <span
                                                class="default-badge"
                                            >
                                                Default
                                            </span>

                                        <?php } ?>


                                    </div>


                                    <p
                                        class="
                                            payment-method-subtitle
                                        "
                                    >

                                        <?php if (
                                            $isCod
                                        ) { ?>

                                            Pay cash when your
                                            order is delivered.

                                        <?php } else { ?>

                                            Online payment method

                                        <?php } ?>

                                    </p>


                                </div>


                            </div>


                            <div
                                class="
                                    payment-actions
                                "
                            >


                                <?php if (
                                    (int)
                                    $method['is_default']
                                    !== 1
                                ) { ?>

                                    <form
                                        method="POST"
                                    >

                                        <input
                                            type="hidden"
                                            name="payment_id"
                                            value="<?php
                                            echo (int)
                                            $method['id'];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            name="set_default_payment"
                                            class="
                                                payment-action-btn
                                            "
                                        >

                                            <i
                                                class="fas fa-check"
                                            ></i>

                                            Set as Default

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
                                        name="payment_id"
                                        value="<?php
                                        echo (int)
                                        $method['id'];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_payment"
                                        class="
                                            payment-action-btn
                                            delete
                                        "
                                    >

                                        <i
                                            class="fas fa-trash"
                                        ></i>

                                        Remove

                                    </button>

                                </form>


                            </div>


                        </div>


                    <?php } ?>


                <?php } else { ?>


                    <div class="empty-payment">

                        <i
                            class="fas fa-wallet"
                        ></i>


                        <h3>
                            No Payment Method Saved
                        </h3>


                        <p>
                            Cash on Delivery can be
                            added from this page.
                        </p>

                    </div>


                <?php } ?>


            </div>

        </section>


        <!-- =================================================
             ADD / SET METHOD
        ================================================== -->

        <section class="payment-card">


            <div class="payment-card-header">

                <div class="payment-card-header-icon">

                    <i class="fas fa-plus"></i>

                </div>


                <h2>
                    Payment Options
                </h2>

            </div>


            <div class="payment-card-body">


                <!-- =========================================
                     COD
                ========================================== -->

                <form
                    method="POST"
                >


                    <div class="form-group">

                        <label
                            for="method_type"
                        >
                            Payment Method
                        </label>


                        <select
                            name="method_type"
                            id="method_type"
                            class="form-control"
                            onchange="
                                togglePaymentForm(this.value);
                            "
                        >

                            <option
                                value="cash_on_delivery"
                            >
                                Cash on Delivery
                            </option>


                            <option
                                value="online"
                            >
                                Online Payment
                            </option>

                        </select>

                    </div>


                    <!-- =====================================
                         COD FORM
                    ====================================== -->

                    <div
                        id="codFields"
                    >

                        <div class="form-group">

                            <label
                                for="title"
                            >
                                Payment Method Name
                            </label>


                            <input
                                type="text"
                                name="title"
                                id="title"
                                class="form-control"
                                value="Cash on Delivery"
                            >

                        </div>


                        <div class="form-group">

                            <label
                                for="account_name"
                            >
                                Name
                            </label>


                            <input
                                type="text"
                                name="account_name"
                                id="account_name"
                                class="form-control"
                                value="<?php
                                echo paymentH(
                                    $customer['full_name']
                                );
                                ?>"
                            >

                        </div>


                        <label
                            class="default-check"
                        >

                            <input
                                type="checkbox"
                                name="is_default"
                                value="1"
                                <?php
                                echo $hasCod
                                    ? ''
                                    : 'checked';
                                ?>
                            >


                            <span>
                                Make this my default
                                payment method
                            </span>

                        </label>


                        <button
                            type="submit"
                            name="save_payment_method"
                            class="save-payment-btn"
                        >

                            <i
                                class="fas fa-floppy-disk"
                            ></i>

                            Save Cash on Delivery

                        </button>


                    </div>


                    <!-- =====================================
                         ONLINE PAYMENT INFO
                    ====================================== -->

                    <div
                        id="onlineFields"
                        style="display:none;"
                    >

                        <div class="online-payment-box">

                            <div
                                class="online-payment-icon"
                            >

                                <i
                                    class="fas fa-mobile-screen-button"
                                ></i>

                            </div>


                            <h3>
                                JazzCash / Online Payment
                            </h3>


                            <p>

                                Online payments will be
                                connected through the
                                Humsafar payment gateway.

                                JazzCash merchant credentials
                                will be added after approval.

                                Your card number, CVV or PIN
                                will not be stored in Humsafar.

                            </p>


                            <span
                                class="coming-soon"
                            >
                                COMING SOON
                            </span>

                        </div>

                    </div>


                </form>


            </div>

        </section>


    </div>


    <!-- =====================================================
         PAYMENT SECURITY
    ====================================================== -->

    <section
        class="payment-card"
        style="margin-top:22px;"
    >


        <div class="payment-card-header">

            <div class="payment-card-header-icon">

                <i class="fas fa-shield-halved"></i>

            </div>


            <h2>
                Payment Security
            </h2>

        </div>


        <div class="payment-card-body">


            <div class="info-box">

                <h3>
                    Your payment information is protected
                </h3>


                <p>

                    Humsafar will not store your complete
                    card number, CVV, PIN or online banking
                    password. When the JazzCash gateway is
                    connected, sensitive payment information
                    will be handled by the payment provider.

                </p>

            </div>


            <ul class="security-list">


                <li>

                    <i
                        class="fas fa-check-circle"
                    ></i>

                    <span>
                        Cash on Delivery is available now.
                    </span>

                </li>


                <li>

                    <i
                        class="fas fa-check-circle"
                    ></i>

                    <span>
                        Online payment is prepared for
                        future gateway integration.
                    </span>

                </li>


                <li>

                    <i
                        class="fas fa-check-circle"
                    ></i>

                    <span>
                        JazzCash credentials will remain
                        on the server and will never be
                        shown to customers.
                    </span>

                </li>


                <li>

                    <i
                        class="fas fa-check-circle"
                    ></i>

                    <span>
                        Payment status will be verified
                        before an online order is marked
                        as paid.
                    </span>

                </li>


            </ul>


        </div>

    </section>


</main>


<script>

/* =========================================================
   PAYMENT OPTION TOGGLE
========================================================= */

function togglePaymentForm(
    value
) {

    const codFields =
        document.getElementById(
            'codFields'
        );


    const onlineFields =
        document.getElementById(
            'onlineFields'
        );


    if (
        !codFields ||
        !onlineFields
    ) {

        return;

    }


    if (
        value === 'online'
    ) {

        codFields.style.display =
            'none';

        onlineFields.style.display =
            'block';

    } else {

        codFields.style.display =
            'block';

        onlineFields.style.display =
            'none';

    }

}

</script>


</body>

</html>