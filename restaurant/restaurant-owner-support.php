<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR - RESTAURANT OWNER SUPPORT
|--------------------------------------------------------------------------
| File:
| restaurant/restaurant-owner-support.php
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CONNECTION
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {

    $dbFiles = [
        __DIR__ . '/../includes/db.php',
        __DIR__ . '/../includes/database.php',
        __DIR__ . '/../includes/config.php',
        __DIR__ . '/../config.php'
    ];

    foreach ($dbFiles as $dbFile) {

        if (file_exists($dbFile)) {
            require_once $dbFile;
            break;
        }
    }
}


/* =========================================================
   OWNER LOGIN CHECK
========================================================= */

$ownerId =
    $_SESSION['restaurant_owner_id']
    ?? $_SESSION['restaurant_user_id']
    ?? $_SESSION['owner_id']
    ?? null;

$ownerEmail =
    $_SESSION['restaurant_owner_email']
    ?? $_SESSION['email']
    ?? '';


/*
|--------------------------------------------------------------------------
| Agar owner login nahi hai
|--------------------------------------------------------------------------
*/

if (!$ownerId && !$ownerEmail) {

    header(
        "Location: restaurant-owner-login.php"
    );

    exit;
}


/* =========================================================
   OWNER INFORMATION
========================================================= */

$owner = null;


/* Find by ID */

if (
    $ownerId &&
    isset($conn) &&
    $conn instanceof mysqli
) {

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

    if ($stmt) {

        $ownerId = (int)$ownerId;

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


/* Find by email */

if (
    !$owner &&
    $ownerEmail &&
    isset($conn) &&
    $conn instanceof mysqli
) {

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

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result) {
            $owner = $result->fetch_assoc();
        }

        $stmt->close();
    }
}


/* =========================================================
   OWNER DATA
========================================================= */

$ownerId =
    $owner['id']
    ?? $ownerId
    ?? 0;

$ownerName =
    $owner['full_name']
    ?? $_SESSION['restaurant_owner_name']
    ?? $_SESSION['name']
    ?? 'Restaurant Owner';

$restaurantName =
    $owner['restaurant_name']
    ?? $_SESSION['restaurant_name']
    ?? 'Restaurant';

$ownerEmail =
    $owner['email']
    ?? $ownerEmail
    ?? '';

$ownerPhone =
    $owner['phone']
    ?? '';


/* =========================================================
   MESSAGE
========================================================= */

$successMessage = '';
$errorMessage = '';


/* =========================================================
   SUPPORT TABLE
========================================================= */

if (
    isset($conn) &&
    $conn instanceof mysqli
) {

    /*
    | Create table automatically if it does not exist.
    */

    $conn->query("
        CREATE TABLE IF NOT EXISTS restaurant_support_tickets (

            id INT AUTO_INCREMENT PRIMARY KEY,

            owner_id INT NOT NULL,

            restaurant_name VARCHAR(255) NULL,

            owner_name VARCHAR(255) NULL,

            email VARCHAR(255) NULL,

            phone VARCHAR(50) NULL,

            subject VARCHAR(255) NOT NULL,

            category VARCHAR(100) NOT NULL,

            message TEXT NOT NULL,

            status VARCHAR(50) NOT NULL DEFAULT 'open',

            admin_reply TEXT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP

        )
    ");
}


/* =========================================================
   SUBMIT SUPPORT TICKET
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['submit_ticket'])
) {

    $subject = trim(
        $_POST['subject'] ?? ''
    );

    $category = trim(
        $_POST['category'] ?? ''
    );

    $message = trim(
        $_POST['message'] ?? ''
    );


    /* Validation */

    if ($subject === '') {

        $errorMessage =
            'Please enter a subject.';

    } elseif ($category === '') {

        $errorMessage =
            'Please select a category.';

    } elseif ($message === '') {

        $errorMessage =
            'Please write your message.';

    } elseif (
        strlen($message) < 10
    ) {

        $errorMessage =
            'Please write a little more detail about your issue.';

    } else {


        /* Insert ticket */

        $stmt = $conn->prepare("
            INSERT INTO restaurant_support_tickets
            (
                owner_id,
                restaurant_name,
                owner_name,
                email,
                phone,
                subject,
                category,
                message,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'open')
        ");


        if ($stmt) {

            $stmt->bind_param(
                "isssssss",
                $ownerId,
                $restaurantName,
                $ownerName,
                $ownerEmail,
                $ownerPhone,
                $subject,
                $category,
                $message
            );


            if ($stmt->execute()) {

                $successMessage =
                    'Your support request has been submitted successfully. Our team will review it and get back to you.';

            } else {

                $errorMessage =
                    'Unable to submit your support request. Please try again.';
            }


            $stmt->close();

        } else {

            $errorMessage =
                'Support system is currently unavailable.';
        }
    }
}


/* =========================================================
   GET PREVIOUS TICKETS
========================================================= */

$tickets = [];


if (
    isset($conn) &&
    $conn instanceof mysqli &&
    $ownerId
) {

    $stmt = $conn->prepare("
        SELECT
            id,
            subject,
            category,
            message,
            status,
            admin_reply,
            created_at
        FROM restaurant_support_tickets
        WHERE owner_id = ?
        ORDER BY id DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $ownerId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result) {

            while (
                $row = $result->fetch_assoc()
            ) {

                $tickets[] = $row;
            }
        }

        $stmt->close();
    }
}


/* =========================================================
   ESCAPE FUNCTION
========================================================= */

function supportSafe($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
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

    <title>
        Support | Humsafar Restaurant
    </title>


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: #f6f7fb;

            color: #252525;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        .support-content {

            margin-left: 223px;

            padding: 30px;

            min-height: 100vh;
        }


        /* =================================================
           TOP
        ================================================= */

        .support-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 25px;
        }


        .support-title-box h1 {

            margin: 0;

            font-size: 27px;

            font-weight: 800;

            color: #202020;
        }


        .support-title-box p {

            margin: 7px 0 0;

            color: #777;

            font-size: 13px;
        }


        .support-icon-header {

            width: 52px;

            height: 52px;

            border-radius: 13px;

            background: #fff0f4;

            color: #f5003d;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;
        }


        /* =================================================
           ALERTS
        ================================================= */

        .support-alert {

            padding: 13px 16px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 13px;

            font-weight: 600;
        }


        .support-success {

            background: #eaf8ef;

            color: #18733b;

            border:
                1px solid #bce8ca;
        }


        .support-error {

            background: #fff0f0;

            color: #c62828;

            border:
                1px solid #f1bcbc;
        }


        /* =================================================
           GRID
        ================================================= */

        .support-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.5fr)
                minmax(280px, .8fr);

            gap: 22px;

            margin-bottom: 25px;
        }


        /* =================================================
           CARD
        ================================================= */

        .support-card {

            background: #ffffff;

            border-radius: 15px;

            border:
                1px solid #eeeeee;

            box-shadow:
                0 5px 20px
                rgba(0,0,0,.04);

            padding: 24px;
        }


        .support-card-title {

            display: flex;

            align-items: center;

            gap: 11px;

            margin-bottom: 20px;
        }


        .support-card-title-icon {

            width: 38px;

            height: 38px;

            border-radius: 9px;

            background: #fff0f4;

            color: #f5003d;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 15px;
        }


        .support-card-title h2 {

            margin: 0;

            font-size: 17px;

            font-weight: 800;
        }


        .support-card-title p {

            margin: 3px 0 0;

            color: #888;

            font-size: 11px;
        }


        /* =================================================
           FORM
        ================================================= */

        .form-group {

            margin-bottom: 17px;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 12px;

            font-weight: 700;

            color: #444;
        }


        .form-control {

            width: 100%;

            min-height: 44px;

            border:
                1px solid #dedede;

            border-radius: 9px;

            padding:
                10px 12px;

            outline: none;

            font-size: 13px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #ffffff;

            transition: .2s;
        }


        .form-control:focus {

            border-color: #f5003d;

            box-shadow:
                0 0 0 3px
                rgba(245,0,61,.08);
        }


        textarea.form-control {

            min-height: 125px;

            resize: vertical;
        }


        select.form-control {

            cursor: pointer;
        }


        .submit-button {

            border: none;

            background: #f5003d;

            color: #ffffff;

            min-height: 44px;

            padding:
                0 20px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            display: inline-flex;

            align-items: center;

            gap: 8px;

            transition: .2s;
        }


        .submit-button:hover {

            background: #d90035;

            transform:
                translateY(-1px);
        }


        /* =================================================
           CONTACT BOX
        ================================================= */

        .contact-item {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            padding: 13px 0;

            border-bottom:
                1px solid #eeeeee;
        }


        .contact-item:last-child {

            border-bottom: none;
        }


        .contact-icon {

            width: 34px;

            height: 34px;

            min-width: 34px;

            border-radius: 8px;

            background: #fff0f4;

            color: #f5003d;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;
        }


        .contact-text strong {

            display: block;

            font-size: 12px;

            margin-bottom: 3px;
        }


        .contact-text span {

            color: #777;

            font-size: 11px;

            word-break: break-word;
        }


        /* =================================================
           INFO BOX
        ================================================= */

        .support-info {

            margin-top: 17px;

            padding: 13px;

            background: #fff8fa;

            border-radius: 9px;

            border:
                1px solid #ffe0e8;

            color: #777;

            font-size: 11px;

            line-height: 1.6;
        }


        .support-info i {

            color: #f5003d;

            margin-right: 5px;
        }


        /* =================================================
           TICKETS
        ================================================= */

        .tickets-card {

            margin-top: 5px;
        }


        .ticket {

            border:
                1px solid #eeeeee;

            border-radius: 11px;

            padding: 16px;

            margin-bottom: 12px;

            background: #ffffff;
        }


        .ticket:last-child {

            margin-bottom: 0;
        }


        .ticket-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 9px;
        }


        .ticket-subject {

            font-size: 13px;

            font-weight: 800;

            color: #292929;
        }


        .ticket-meta {

            color: #999;

            font-size: 10px;

            margin-top: 4px;
        }


        .ticket-status {

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            white-space: nowrap;
        }


        .status-open {

            background: #fff5df;

            color: #a56600;
        }


        .status-pending {

            background: #fff5df;

            color: #a56600;
        }


        .status-resolved {

            background: #eaf8ef;

            color: #18733b;
        }


        .status-closed {

            background: #eeeeee;

            color: #666;
        }


        .ticket-message {

            color: #666;

            font-size: 11px;

            line-height: 1.6;

            margin-top: 10px;

            white-space: pre-wrap;
        }


        .admin-reply {

            margin-top: 13px;

            padding: 11px;

            border-radius: 8px;

            background: #f8f8f8;

            border-left:
                3px solid #f5003d;

            font-size: 11px;

            color: #555;

            line-height: 1.6;
        }


        .admin-reply strong {

            color: #f5003d;

            display: block;

            margin-bottom: 4px;
        }


        .empty-tickets {

            text-align: center;

            padding: 35px 15px;

            color: #999;

            font-size: 12px;
        }


        .empty-tickets i {

            font-size: 30px;

            color: #ddd;

            margin-bottom: 10px;

            display: block;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 900px) {

            .support-grid {

                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 768px) {

            .support-content {

                margin-left: 72px;

                padding: 20px;
            }

        }


        @media (max-width: 500px) {

            .support-content {

                padding: 15px;
            }


            .support-header {

                align-items: flex-start;
            }


            .support-title-box h1 {

                font-size: 22px;
            }


            .support-card {

                padding: 17px;
            }

        }

    </style>

</head>


<body>


<?php
/*
|--------------------------------------------------------------------------
| SHARED RESTAURANT SIDEBAR
|--------------------------------------------------------------------------
*/

include __DIR__ . '/restaurant-sidebar.php';
?>


<!-- ======================================================
     MAIN CONTENT
====================================================== -->

<main class="support-content">


    <!-- HEADER -->

    <div class="support-header">

        <div class="support-title-box">

            <h1>
                Support Center
            </h1>

            <p>
                Need help? Submit a support request and our team will assist you.
            </p>

        </div>


        <div class="support-icon-header">

            <i class="fa-solid fa-headset"></i>

        </div>

    </div>


    <!-- SUCCESS -->

    <?php if ($successMessage): ?>

        <div class="support-alert support-success">

            <i class="fa-solid fa-circle-check"></i>

            <?php
            echo supportSafe(
                $successMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($errorMessage): ?>

        <div class="support-alert support-error">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php
            echo supportSafe(
                $errorMessage
            );
            ?>

        </div>

    <?php endif; ?>


    <!-- TOP GRID -->

    <div class="support-grid">


        <!-- CONTACT FORM -->

        <div class="support-card">


            <div class="support-card-title">

                <div class="support-card-title-icon">

                    <i class="fa-solid fa-paper-plane"></i>

                </div>


                <div>

                    <h2>
                        Submit a Support Request
                    </h2>

                    <p>
                        Tell us what you need help with.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action=""
            >


                <!-- SUBJECT -->

                <div class="form-group">

                    <label>
                        Subject
                    </label>

                    <input
                        type="text"
                        name="subject"
                        class="form-control"
                        placeholder="e.g. Unable to update menu"
                        maxlength="255"
                        required
                    >

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select an issue
                        </option>

                        <option value="Restaurant">
                            Restaurant
                        </option>

                        <option value="Menu">
                            Menu Management
                        </option>

                        <option value="Orders">
                            Orders
                        </option>

                        <option value="Payments">
                            Payments
                        </option>

                        <option value="Account">
                            Account
                        </option>

                        <option value="Technical">
                            Technical Issue
                        </option>

                        <option value="Other">
                            Other
                        </option>

                    </select>

                </div>


                <!-- MESSAGE -->

                <div class="form-group">

                    <label>
                        Message
                    </label>

                    <textarea
                        name="message"
                        class="form-control"
                        placeholder="Describe your problem in detail..."
                        required
                    ></textarea>

                </div>


                <button
                    type="submit"
                    name="submit_ticket"
                    class="submit-button"
                >

                    <i class="fa-solid fa-paper-plane"></i>

                    Submit Request

                </button>


            </form>

        </div>


        <!-- CONTACT INFORMATION -->

        <div class="support-card">


            <div class="support-card-title">

                <div class="support-card-title-icon">

                    <i class="fa-solid fa-headset"></i>

                </div>


                <div>

                    <h2>
                        Contact Support
                    </h2>

                    <p>
                        We're here to help.
                    </p>

                </div>

            </div>


            <div class="contact-item">

                <div class="contact-icon">

                    <i class="fa-solid fa-store"></i>

                </div>


                <div class="contact-text">

                    <strong>
                        Restaurant
                    </strong>

                    <span>
                        <?php
                        echo supportSafe(
                            $restaurantName
                        );
                        ?>
                    </span>

                </div>

            </div>


            <div class="contact-item">

                <div class="contact-icon">

                    <i class="fa-solid fa-user"></i>

                </div>


                <div class="contact-text">

                    <strong>
                        Owner
                    </strong>

                    <span>
                        <?php
                        echo supportSafe(
                            $ownerName
                        );
                        ?>
                    </span>

                </div>

            </div>


            <div class="contact-item">

                <div class="contact-icon">

                    <i class="fa-solid fa-envelope"></i>

                </div>


                <div class="contact-text">

                    <strong>
                        Email
                    </strong>

                    <span>
                        <?php
                        echo supportSafe(
                            $ownerEmail ?: 'Not available'
                        );
                        ?>
                    </span>

                </div>

            </div>


            <div class="contact-item">

                <div class="contact-icon">

                    <i class="fa-solid fa-phone"></i>

                </div>


                <div class="contact-text">

                    <strong>
                        Phone
                    </strong>

                    <span>
                        <?php
                        echo supportSafe(
                            $ownerPhone ?: 'Not available'
                        );
                        ?>
                    </span>

                </div>

            </div>


            <div class="support-info">

                <i class="fa-solid fa-circle-info"></i>

                Please provide as much detail as possible when submitting a request. This helps our support team resolve your issue faster.

            </div>


        </div>


    </div>


    <!-- ==================================================
         PREVIOUS REQUESTS
    ================================================== -->

    <div class="support-card tickets-card">


        <div class="support-card-title">

            <div class="support-card-title-icon">

                <i class="fa-solid fa-clock-rotate-left"></i>

            </div>


            <div>

                <h2>
                    My Support Requests
                </h2>

                <p>
                    Track your previous support requests and replies.
                </p>

            </div>

        </div>


        <?php if (count($tickets) > 0): ?>


            <?php foreach ($tickets as $ticket): ?>


                <?php

                $ticketStatus =
                    strtolower(
                        trim(
                            $ticket['status']
                            ?? 'open'
                        )
                    );


                $statusClass =
                    'status-open';


                if (
                    $ticketStatus === 'resolved'
                ) {

                    $statusClass =
                        'status-resolved';

                } elseif (
                    $ticketStatus === 'closed'
                ) {

                    $statusClass =
                        'status-closed';

                } elseif (
                    $ticketStatus === 'pending'
                ) {

                    $statusClass =
                        'status-pending';
                }

                ?>


                <div class="ticket">


                    <div class="ticket-top">

                        <div>

                            <div class="ticket-subject">

                                <?php
                                echo supportSafe(
                                    $ticket['subject']
                                );
                                ?>

                            </div>


                            <div class="ticket-meta">

                                #<?php
                                echo supportSafe(
                                    $ticket['id']
                                );
                                ?>

                                &nbsp; • &nbsp;

                                <?php
                                echo supportSafe(
                                    $ticket['category']
                                );
                                ?>

                                &nbsp; • &nbsp;

                                <?php
                                echo supportSafe(
                                    $ticket['created_at']
                                );
                                ?>

                            </div>

                        </div>


                        <div
                            class="
                                ticket-status
                                <?php
                                echo supportSafe(
                                    $statusClass
                                );
                                ?>
                            "
                        >

                            <?php
                            echo supportSafe(
                                $ticketStatus
                            );
                            ?>

                        </div>

                    </div>


                    <div class="ticket-message">

                        <?php
                        echo supportSafe(
                            $ticket['message']
                        );
                        ?>

                    </div>


                    <?php
                    if (
                        !empty(
                            $ticket['admin_reply']
                        )
                    ):
                    ?>

                        <div class="admin-reply">

                            <strong>
                                <i class="fa-solid fa-reply"></i>
                                Admin Reply
                            </strong>

                            <?php
                            echo supportSafe(
                                $ticket['admin_reply']
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-tickets">

                <i class="fa-regular fa-comments"></i>

                You haven't submitted any support requests yet.

            </div>


        <?php endif; ?>


    </div>


</main>


</body>

</html>