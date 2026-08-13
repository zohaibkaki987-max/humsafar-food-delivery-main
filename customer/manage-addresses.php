<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| MANAGE ADDRESSES
|--------------------------------------------------------------------------
*/

session_start();


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0
) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$error = '';



/*
|--------------------------------------------------------------------------
| DELETE ADDRESS
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {

    $address_id = (int) $_GET['delete'];

    if ($address_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM customer_addresses
            WHERE id = ?
            AND user_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $address_id,
                $user_id
            );

            $stmt->execute();

            $stmt->close();
        }
    }

    header("Location: manage-addresses.php");
    exit;
}



/*
|--------------------------------------------------------------------------
| SET DEFAULT ADDRESS
|--------------------------------------------------------------------------
*/

if (isset($_GET['default'])) {

    $address_id = (int) $_GET['default'];

    if ($address_id > 0) {

        /*
        | Remove default from all user's addresses
        */

        $stmt = $conn->prepare("
            UPDATE customer_addresses
            SET is_default = 0
            WHERE user_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $user_id
            );

            $stmt->execute();

            $stmt->close();
        }


        /*
        | Set selected address as default
        */

        $stmt = $conn->prepare("
            UPDATE customer_addresses
            SET is_default = 1
            WHERE id = ?
            AND user_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $address_id,
                $user_id
            );

            $stmt->execute();

            $stmt->close();
        }
    }

    header("Location: manage-addresses.php");
    exit;
}



/*
|--------------------------------------------------------------------------
| SAVE NEW ADDRESS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_address'])
) {

    $address_title = trim(
        $_POST['address_title'] ?? ''
    );

    $address_line = trim(
        $_POST['address_line'] ?? ''
    );

    $area = trim(
        $_POST['area'] ?? ''
    );

    $city = trim(
        $_POST['city'] ?? ''
    );

    $phone = trim(
        $_POST['phone'] ?? ''
    );

    $is_default = isset(
        $_POST['is_default']
    ) ? 1 : 0;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $address_title === '' ||
        $address_line === '' ||
        $area === '' ||
        $city === '' ||
        $phone === ''
    ) {

        $error =
            "Please fill all required fields.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | CHECK EXISTING ADDRESSES
        |--------------------------------------------------------------------------
        */

        $total_addresses = 0;

        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM customer_addresses
            WHERE user_id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $user_id
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if ($result) {

                $row =
                    $result->fetch_assoc();

                $total_addresses =
                    (int) (
                        $row['total'] ?? 0
                    );
            }

            $stmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | FIRST ADDRESS WILL BE DEFAULT
        |--------------------------------------------------------------------------
        */

        if ($total_addresses === 0) {

            $is_default = 1;
        }


        /*
        |--------------------------------------------------------------------------
        | IF NEW ADDRESS IS DEFAULT
        | REMOVE OLD DEFAULT
        |--------------------------------------------------------------------------
        */

        if ($is_default === 1) {

            $stmt = $conn->prepare("
                UPDATE customer_addresses
                SET is_default = 0
                WHERE user_id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "i",
                    $user_id
                );

                $stmt->execute();

                $stmt->close();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT ADDRESS
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO customer_addresses
            (
                user_id,
                address_title,
                address_line,
                area,
                city,
                phone,
                is_default
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "isssssi",
                $user_id,
                $address_title,
                $address_line,
                $area,
                $city,
                $phone,
                $is_default
            );

            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: manage-addresses.php"
                );

                exit;

            } else {

                $error =
                    "Unable to save address.";

                $stmt->close();
            }

        } else {

            $error =
                "Database error. Unable to save address.";
        }
    }
}



/*
|--------------------------------------------------------------------------
| GET SAVED ADDRESSES
|--------------------------------------------------------------------------
*/

$addresses = [];

$stmt = $conn->prepare("
    SELECT
        id,
        address_title,
        address_line,
        area,
        city,
        phone,
        is_default
    FROM customer_addresses
    WHERE user_id = ?
    ORDER BY
        is_default DESC,
        id DESC
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $addresses[] = $row;
        }
    }

    $stmt->close();
}



/*
|--------------------------------------------------------------------------
| CUSTOMER HEADER
|--------------------------------------------------------------------------
|
| manage-addresses.php is inside:
|
| /customer/
|
| Header itself remains unchanged.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/customer-header.php';

?>


<!-- =========================================================
     FIX HEADER LINKS ONLY ON THIS PAGE
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | MANAGE-ADDRESSES.PHP IS INSIDE /CUSTOMER/
        |
        | Header links normally point to root pages.
        | Fix them only on this page.
        |--------------------------------------------------------------------------
        */


        const rootPages = [

            'index.php',

            'restaurants.php',

            'deals.php',

            'my_orders.php',

            'cart.php',

            'my-account.php',

            'payment.php'

        ];


        /*
        |--------------------------------------------------------------------------
        | FIND CUSTOMER HEADER LINKS
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                'a[href]'
            )
            .forEach(
                function (link) {


                    let href =
                        link.getAttribute(
                            'href'
                        );


                    if (!href) {

                        return;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | REMOVE ./ IF PRESENT
                    |--------------------------------------------------------------------------
                    */

                    if (
                        href.startsWith('./')
                    ) {

                        href =
                            href.substring(
                                2
                            );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ROOT PAGE LINKS
                    |
                    | index.php
                    |     ↓
                    | ../index.php
                    |--------------------------------------------------------------------------
                    */

                    if (
                        rootPages.includes(
                            href
                        )
                    ) {

                        link.setAttribute(
                            'href',
                            '../' + href
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | MANAGE ADDRESS LINK
                    |
                    | customer/manage-addresses.php
                    |     ↓
                    | manage-addresses.php
                    |--------------------------------------------------------------------------
                    */

                    if (
                        href ===
                        'customer/manage-addresses.php'
                    ) {

                        link.setAttribute(
                            'href',
                            'manage-addresses.php'
                        );

                    }

                }
            );

    }
);

</script>



<style>

/* =========================================================
   MANAGE ADDRESS PAGE
========================================================= */

.manage-address-page {

    width: 100%;

    max-width: 1100px;

    margin: 35px auto;

    padding: 0 20px 60px;

}


/* =========================================================
   PAGE TITLE
========================================================= */

.manage-address-title {

    margin-bottom: 25px;

}

.manage-address-title h1 {

    margin: 0 0 7px;

    color: #ed0038;

    font-size: 30px;

    font-weight: 800;

}

.manage-address-title p {

    margin: 0;

    color: #777;

    font-size: 14px;

}


/* =========================================================
   ERROR
========================================================= */

.address-error {

    background: #fff0f3;

    color: #c80035;

    border: 1px solid #f5bdcb;

    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

}


/* =========================================================
   SECTION TITLE
========================================================= */

.address-section-title {

    margin-bottom: 18px;

}

.address-section-title h2 {

    margin: 0;

    color: #333;

    font-size: 21px;

    font-weight: 800;

}


/* =========================================================
   SAVED ADDRESS GRID
========================================================= */

.saved-addresses-grid {

    display: grid;

    grid-template-columns:
        repeat(
            auto-fit,
            minmax(300px, 1fr)
        );

    gap: 20px;

    margin-bottom: 40px;

}


/* =========================================================
   ADDRESS CARD
========================================================= */

.saved-address-card {

    background: #ffffff;

    border: 1px solid #eeeeee;

    border-radius: 16px;

    padding: 20px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.06);

    transition: .2s ease;

}

.saved-address-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 22px
        rgba(0,0,0,.09);

}


/* =========================================================
   CARD TOP
========================================================= */

.saved-address-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 12px;

    margin-bottom: 15px;

}

.saved-address-name {

    display: flex;

    align-items: center;

    gap: 8px;

    color: #222;

    font-size: 18px;

    font-weight: 800;

}

.saved-address-name i {

    color: #ed0038;

    font-size: 18px;

}


/* =========================================================
   DEFAULT BADGE
========================================================= */

.address-default-badge {

    flex-shrink: 0;

    background: #ed0038;

    color: #ffffff;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 800;

}


/* =========================================================
   ADDRESS CONTENT
========================================================= */

.saved-address-content {

    color: #555;

    font-size: 14px;

    line-height: 1.6;

}

.saved-address-content p {

    margin: 0 0 6px;

}

.saved-address-content .phone {

    margin-top: 9px;

    color: #444;

    font-weight: 600;

}


/* =========================================================
   ADDRESS ACTIONS
========================================================= */

.saved-address-actions {

    display: flex;

    align-items: center;

    gap: 15px;

    border-top: 1px solid #eeeeee;

    margin-top: 17px;

    padding-top: 14px;

}

.saved-address-actions a {

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

}

.set-default-address {

    color: #ed0038;

}

.delete-address {

    color: #d00000;

}

.saved-address-actions a:hover {

    text-decoration: underline;

}


/* =========================================================
   NO ADDRESS
========================================================= */

.no-address-box {

    background: #ffffff;

    border: 1px dashed #d9d9d9;

    border-radius: 15px;

    text-align: center;

    padding: 35px 20px;

    margin-bottom: 40px;

    color: #777;

}

.no-address-box i {

    display: block;

    color: #ed0038;

    font-size: 35px;

    margin-bottom: 10px;

}

.no-address-box strong {

    display: block;

    color: #333;

    font-size: 16px;

    margin-bottom: 5px;

}


/* =========================================================
   ADD ADDRESS BOX
========================================================= */

.add-address-box {

    background: #ffffff;

    border: 1px solid #eeeeee;

    border-radius: 18px;

    padding: 25px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.05);

}

.add-address-heading {

    margin-bottom: 22px;

}

.add-address-heading h2 {

    margin: 0 0 5px;

    color: #333;

    font-size: 21px;

    font-weight: 800;

}

.add-address-heading p {

    margin: 0;

    color: #888;

    font-size: 13px;

}


/* =========================================================
   FORM
========================================================= */

.address-form {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 17px;

}

.address-form-group {

    display: flex;

    flex-direction: column;

}

.address-form-group.full {

    grid-column: 1 / -1;

}

.address-form-group label {

    color: #333;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 7px;

}

.address-form-group input,
.address-form-group textarea {

    width: 100%;

    border: 1px solid #dddddd;

    border-radius: 10px;

    background: #ffffff;

    color: #333;

    padding: 12px 13px;

    outline: none;

    font-family: inherit;

    font-size: 14px;

    transition: .2s ease;

}

.address-form-group textarea {

    min-height: 100px;

    resize: vertical;

}

.address-form-group input:focus,
.address-form-group textarea:focus {

    border-color: #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.08);

}


/* =========================================================
   DEFAULT CHECKBOX
========================================================= */

.default-address-check {

    grid-column: 1 / -1;

    display: flex;

    align-items: center;

    gap: 9px;

    cursor: pointer;

    color: #444;

    font-size: 13px;

    font-weight: 600;

}

.default-address-check input {

    width: 17px;

    height: 17px;

    margin: 0;

    accent-color: #ed0038;

}


/* =========================================================
   SAVE BUTTON
========================================================= */

.save-address-button {

    grid-column: 1 / -1;

    width: 100%;

    border: none;

    border-radius: 10px;

    background: #ed0038;

    color: #ffffff;

    padding: 14px 20px;

    cursor: pointer;

    font-size: 15px;

    font-weight: 800;

    transition: .2s ease;

}

.save-address-button:hover {

    background: #d90035;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 750px) {

    .manage-address-page {

        margin-top: 25px;

        padding-left: 15px;

        padding-right: 15px;

    }

    .manage-address-title h1 {

        font-size: 25px;

    }

    .address-form {

        grid-template-columns: 1fr;

    }

    .address-form-group.full {

        grid-column: auto;

    }

    .default-address-check {

        grid-column: auto;

    }

    .save-address-button {

        grid-column: auto;

    }

}

</style>



<!-- =========================================================
     MANAGE ADDRESSES CONTENT
========================================================= -->

<main class="manage-address-page">


    <!-- PAGE TITLE -->

    <div class="manage-address-title">

        <h1>
            Delivery Address
        </h1>

        <p>
            Manage your saved delivery addresses
        </p>

    </div>


    <!-- ERROR MESSAGE -->

    <?php if ($error !== ''): ?>

        <div class="address-error">

            <?php

            echo htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SAVED ADDRESSES
    ====================================================== -->

    <div class="address-section-title">

        <h2>
            Saved Addresses
        </h2>

    </div>


    <?php if (!empty($addresses)): ?>


        <div class="saved-addresses-grid">


            <?php foreach ($addresses as $address): ?>


                <div class="saved-address-card">


                    <!-- CARD HEADER -->

                    <div class="saved-address-top">


                        <div class="saved-address-name">

                            <i class="fa-solid fa-location-dot"></i>

                            <?php

                            echo htmlspecialchars(
                                $address['address_title'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </div>


                        <?php

                        if (
                            (int)
                            $address['is_default']
                            === 1
                        ):

                        ?>

                            <span
                                class="address-default-badge"
                            >

                                Default

                            </span>

                        <?php endif; ?>


                    </div>


                    <!-- ADDRESS DETAILS -->

                    <div class="saved-address-content">


                        <p>

                            <?php

                            echo nl2br(
                                htmlspecialchars(
                                    $address['address_line'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            );

                            ?>

                        </p>


                        <p>

                            <?php

                            echo htmlspecialchars(
                                $address['area'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>,

                            <?php

                            echo htmlspecialchars(
                                $address['city'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </p>


                        <p class="phone">

                            <i
                                class="fa-solid fa-phone"
                            ></i>

                            <?php

                            echo htmlspecialchars(
                                $address['phone'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </p>


                    </div>


                    <!-- ACTIONS -->

                    <div class="saved-address-actions">


                        <?php

                        if (
                            (int)
                            $address['is_default']
                            !== 1
                        ):

                        ?>

                            <a
                                href="manage-addresses.php?default=<?php echo (int) $address['id']; ?>"
                                class="set-default-address"
                            >

                                Set as Default

                            </a>

                        <?php endif; ?>


                        <a
                            href="manage-addresses.php?delete=<?php echo (int) $address['id']; ?>"
                            class="delete-address"
                            onclick="return confirm('Are you sure you want to delete this address?');"
                        >

                            Delete

                        </a>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <!-- NO ADDRESSES -->

        <div class="no-address-box">

            <i
                class="fa-solid fa-location-dot"
            ></i>

            <strong>
                No Saved Addresses
            </strong>

            <p>
                Add your delivery address below
                to place an order.
            </p>

        </div>


    <?php endif; ?>


    <!-- =====================================================
         ADD NEW ADDRESS
    ====================================================== -->

    <div class="add-address-box">


        <div class="add-address-heading">

            <h2>
                Add New Address
            </h2>

            <p>
                Enter your delivery details below.
            </p>

        </div>


        <form
            method="POST"
            action="manage-addresses.php"
            class="address-form"
        >


            <!-- ADDRESS TITLE -->

            <div class="address-form-group">

                <label for="address_title">

                    Address Title

                </label>

                <input
                    type="text"
                    id="address_title"
                    name="address_title"
                    placeholder="Home / Office"
                    maxlength="100"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="address-form-group">

                <label for="phone">

                    Phone Number

                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="03XXXXXXXXX"
                    maxlength="20"
                    required
                >

            </div>


            <!-- COMPLETE ADDRESS -->

            <div class="address-form-group full">

                <label for="address_line">

                    Complete Address

                </label>

                <textarea
                    id="address_line"
                    name="address_line"
                    placeholder="House No, Street No, Block, Building, etc."
                    maxlength="500"
                    required
                ></textarea>

            </div>


            <!-- AREA -->

            <div class="address-form-group">

                <label for="area">

                    Area

                </label>

                <input
                    type="text"
                    id="area"
                    name="area"
                    placeholder="Latifabad"
                    maxlength="100"
                    required
                >

            </div>


            <!-- CITY -->

            <div class="address-form-group">

                <label for="city">

                    City

                </label>

                <input
                    type="text"
                    id="city"
                    name="city"
                    placeholder="Hyderabad"
                    maxlength="100"
                    required
                >

            </div>


            <!-- DEFAULT -->

            <label class="default-address-check">

                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                >

                <span>
                    Set this address as my default delivery address
                </span>

            </label>


            <!-- SAVE -->

            <button
                type="submit"
                name="save_address"
                class="save-address-button"
            >

                <i
                    class="fa-solid fa-location-dot"
                ></i>

                Save Address

            </button>


        </form>


    </div>


</main>