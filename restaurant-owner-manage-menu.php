<?php

require_once 'includes/session.php';
require_once 'includes/config.php';


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}


/* =========================================================
   FIND RESTAURANT OWNER
========================================================= */

$owner = null;

$ownerId = 0;

$ownerEmail = '';


$ownerIds = array(
    isset($_SESSION['restaurant_owner_id'])
        ? $_SESSION['restaurant_owner_id']
        : null,

    isset($_SESSION['restaurant_user_id'])
        ? $_SESSION['restaurant_user_id']
        : null,

    isset($_SESSION['owner_id'])
        ? $_SESSION['owner_id']
        : null
);


if (!empty($_SESSION['restaurant_owner_email'])) {

    $ownerEmail = trim(
        (string) $_SESSION['restaurant_owner_email']
    );

}


if ($ownerEmail === '' && !empty($_SESSION['email'])) {

    $ownerEmail = trim(
        (string) $_SESSION['email']
    );

}


/* =========================================================
   SEARCH OWNER BY ID
========================================================= */

foreach ($ownerIds as $possibleId) {

    $possibleId = (int) $possibleId;

    if ($possibleId <= 0) {
        continue;
    }


    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
            status
        FROM restaurant_users
        WHERE id = ?
        LIMIT 1
    ");


    if (!$stmt) {
        continue;
    }


    $stmt->bind_param(
        "i",
        $possibleId
    );


    $stmt->execute();


    $result = $stmt->get_result();


    $owner = $result->fetch_assoc();


    $stmt->close();


    if ($owner) {

        break;

    }

}


/* =========================================================
   SEARCH OWNER BY EMAIL
========================================================= */

if (!$owner && $ownerEmail !== '') {

    $stmt = $conn->prepare("
        SELECT
            id,
            restaurant_name,
            full_name,
            email,
            phone,
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


        $owner = $result->fetch_assoc();


        $stmt->close();

    }

}


/* =========================================================
   OWNER NOT FOUND
========================================================= */

if (!$owner) {

    die(
        '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Owner Not Found</title>
            <style>
                body {
                    margin: 0;
                    background: #fff7fa;
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }

                .box {
                    width: 90%;
                    max-width: 500px;
                    background: #fff;
                    padding: 40px;
                    text-align: center;
                    border-radius: 16px;
                    box-shadow: 0 10px 40px rgba(0,0,0,.08);
                }

                h1 {
                    color: #e00038;
                }

                a {
                    display: inline-block;
                    margin-top: 15px;
                    padding: 12px 20px;
                    background: #e00038;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>

            <div class="box">

                <h1>Restaurant Owner Not Found</h1>

                <p>
                    Please login again using the restaurant owner login.
                </p>

                <a href="restaurant-owner-login.php">
                    Login Again
                </a>

            </div>

        </body>
        </html>'
    );

}


$ownerId = (int) $owner['id'];


$restaurantName = trim(
    (string) $owner['restaurant_name']
);


/* =========================================================
   FIND RESTAURANT
========================================================= */

$restaurant = null;

$restaurantId = 0;


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
        $restaurantName
    );


    $stmt->execute();


    $result = $stmt->get_result();


    $restaurant = $result->fetch_assoc();


    $stmt->close();

}


if ($restaurant) {

    $restaurantId = (int) $restaurant['id'];

}


/* =========================================================
   MESSAGES
========================================================= */

$successMessage = '';

$errorMessage = '';


/* =========================================================
   IMAGE DIRECTORY
========================================================= */

$imageDirectory = __DIR__ . '/uploads/menu/';

$imageUrl = 'uploads/menu/';


if (!is_dir($imageDirectory)) {

    @mkdir(
        $imageDirectory,
        0755,
        true
    );

}


/* =========================================================
   ADD MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_item'])
) {


    if ($restaurantId <= 0) {

        $errorMessage =
            'Your restaurant has not been created yet.';

    } else {


        $itemName = trim(
            (string) (
                isset($_POST['name'])
                    ? $_POST['name']
                    : ''
            )
        );


        $description = trim(
            (string) (
                isset($_POST['description'])
                    ? $_POST['description']
                    : ''
            )
        );


        $price = trim(
            (string) (
                isset($_POST['price'])
                    ? $_POST['price']
                    : ''
            )
        );


        $category = trim(
            (string) (
                isset($_POST['category'])
                    ? $_POST['category']
                    : ''
            )
        );


        if ($itemName === '') {

            $errorMessage =
                'Please enter menu item name.';

        } elseif (
            $price === '' ||
            !is_numeric($price)
        ) {

            $errorMessage =
                'Please enter a valid price.';

        } else {


            $imageName = '';


            /* -----------------------------------------
               IMAGE UPLOAD
            ----------------------------------------- */

            if (
                isset($_FILES['image']) &&
                isset($_FILES['image']['error']) &&
                $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {


                if (
                    $_FILES['image']['error'] !==
                    UPLOAD_ERR_OK
                ) {

                    $errorMessage =
                        'Image upload failed.';

                } else {


                    if (
                        $_FILES['image']['size'] >
                        5 * 1024 * 1024
                    ) {

                        $errorMessage =
                            'Image must be less than 5 MB.';

                    } else {


                        $extension = strtolower(
                            pathinfo(
                                $_FILES['image']['name'],
                                PATHINFO_EXTENSION
                            )
                        );


                        $allowedExtensions = array(
                            'jpg',
                            'jpeg',
                            'png',
                            'webp'
                        );


                        if (
                            !in_array(
                                $extension,
                                $allowedExtensions,
                                true
                            )
                        ) {

                            $errorMessage =
                                'Only JPG, JPEG, PNG and WEBP images are allowed.';

                        } else {


                            $checkImage = @getimagesize(
                                $_FILES['image']['tmp_name']
                            );


                            if ($checkImage === false) {

                                $errorMessage =
                                    'Selected file is not a valid image.';

                            } else {


                                $imageName =
                                    'menu_' .
                                    date('YmdHis') .
                                    '_' .
                                    mt_rand(1000, 999999) .
                                    '.' .
                                    $extension;


                                $imagePath =
                                    $imageDirectory .
                                    $imageName;


                                if (
                                    !move_uploaded_file(
                                        $_FILES['image']['tmp_name'],
                                        $imagePath
                                    )
                                ) {

                                    $errorMessage =
                                        'Could not save uploaded image.';

                                    $imageName = '';

                                }

                            }

                        }

                    }

                }

            }


            /* -----------------------------------------
               INSERT ITEM
            ----------------------------------------- */

            if ($errorMessage === '') {


                $priceValue = (float) $price;


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
                        1
                    )
                ");


                if (!$stmt) {

                    $errorMessage =
                        'Database error: ' .
                        $conn->error;

                } else {


                    $stmt->bind_param(
                        "issdss",
                        $restaurantId,
                        $itemName,
                        $description,
                        $priceValue,
                        $imageName,
                        $category
                    );


                    if ($stmt->execute()) {

                        $successMessage =
                            'Menu item added successfully.';

                    } else {

                        $errorMessage =
                            'Could not add menu item: ' .
                            $stmt->error;


                        if ($imageName !== '') {

                            $oldImage =
                                $imageDirectory .
                                basename($imageName);


                            if (is_file($oldImage)) {

                                @unlink($oldImage);

                            }

                        }

                    }


                    $stmt->close();

                }

            }

        }

    }

}


/* =========================================================
   DELETE MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_item'])
) {


    $itemId = (int) (
        isset($_POST['item_id'])
            ? $_POST['item_id']
            : 0
    );


    if ($itemId <= 0) {

        $errorMessage =
            'Invalid menu item.';

    } else {


        $oldImage = '';


        $stmt = $conn->prepare("
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


            $stmt->execute();


            $result =
                $stmt->get_result();


            $oldItem =
                $result->fetch_assoc();


            $stmt->close();


            if ($oldItem) {

                $oldImage =
                    trim(
                        (string) $oldItem['image']
                    );

            }

        }


        $stmt = $conn->prepare("
            DELETE FROM menu_items
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
                "ii",
                $itemId,
                $restaurantId
            );


            if ($stmt->execute()) {


                if ($stmt->affected_rows > 0) {

                    $successMessage =
                        'Menu item deleted successfully.';


                    if ($oldImage !== '') {

                        $oldImagePath =
                            $imageDirectory .
                            basename($oldImage);


                        if (is_file($oldImagePath)) {

                            @unlink($oldImagePath);

                        }

                    }

                } else {

                    $errorMessage =
                        'Menu item not found.';

                }

            } else {

                $errorMessage =
                    'Could not delete menu item.';

            }


            $stmt->close();

        }

    }

}


/* =========================================================
   TOGGLE MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_item'])
) {


    $itemId = (int) (
        isset($_POST['item_id'])
            ? $_POST['item_id']
            : 0
    );


    if ($itemId > 0) {


        $stmt = $conn->prepare("
            UPDATE menu_items
            SET status =
                CASE
                    WHEN status = 1 THEN 0
                    ELSE 1
                END
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

                $successMessage =
                    'Menu item status updated.';

            } else {

                $errorMessage =
                    'Could not update item status.';

            }


            $stmt->close();

        }

    }

}


/* =========================================================
   GET MENU ITEMS
========================================================= */

$menuItems = array();


$stmt = $conn->prepare("
    SELECT
        id,
        restaurant_id,
        name,
        description,
        price,
        image,
        category,
        status,
        created_at
    FROM menu_items
    WHERE restaurant_id = ?
    ORDER BY id DESC
");


if ($stmt) {


    $stmt->bind_param(
        "i",
        $restaurantId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $menuItems[] = $row;

    }


    $stmt->close();

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
    Manage Menu - Humsafar
</title>


<style>

* {
    box-sizing: border-box;
}


body {
    margin: 0;
    background: #fff7fa;
    color: #292929;
    font-family: Arial, sans-serif;
}


.topbar {
    background: #ffffff;
    border-bottom: 1px solid #eeeeee;
    padding: 16px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}


.logo {
    color: #e00038;
    text-decoration: none;
    font-size: 22px;
    font-weight: 800;
}


.top-links {
    display: flex;
    gap: 10px;
}


.top-links a {
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: bold;
}


.dashboard-link {
    background: #eeeeee;
    color: #333333;
}


.login-link {
    background: #fff0f3;
    color: #e00038;
}


.container {
    width: 94%;
    max-width: 1150px;
    margin: auto;
    padding: 35px 0 60px;
}


.heading {
    margin-bottom: 25px;
}


.restaurant-name {
    display: inline-block;
    padding: 8px 14px;
    background: #fff0f3;
    color: #e00038;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 12px;
}


.heading h1 {
    margin: 0 0 8px;
    font-size: 30px;
}


.heading p {
    margin: 0;
    color: #777777;
}


.message {
    padding: 14px 17px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: bold;
}


.success {
    background: #eaf8ef;
    color: #18733e;
}


.error {
    background: #fff0f0;
    color: #c52323;
}


.card {
    background: #ffffff;
    border: 1px solid #eee5e9;
    border-radius: 16px;
    margin-bottom: 25px;
    overflow: hidden;
}


.card-header {
    padding: 20px;
    border-bottom: 1px solid #eeeeee;
}


.card-header h2 {
    margin: 0 0 5px;
    font-size: 19px;
}


.card-header p {
    margin: 0;
    color: #888888;
    font-size: 12px;
}


.form {
    padding: 22px;
}


.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 16px;
}


.form-group {
    display: flex;
    flex-direction: column;
}


.form-group.full {
    grid-column: 1 / -1;
}


.form-group label {
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: bold;
}


.form-group input,
.form-group textarea {
    width: 100%;
    border: 1px solid #dddddd;
    border-radius: 8px;
    padding: 11px;
    font-size: 13px;
    outline: none;
}


.form-group input {
    height: 44px;
}


.form-group textarea {
    min-height: 90px;
    resize: vertical;
}


.form-group input:focus,
.form-group textarea:focus {
    border-color: #e00038;
}


.add-button {
    margin-top: 18px;
    background: #e00038;
    color: #ffffff;
    border: 0;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: bold;
    cursor: pointer;
}


.menu-list {
    padding: 0;
}


.menu-item {
    padding: 18px 20px;
    border-top: 1px solid #eeeeee;
    display: flex;
    align-items: center;
    gap: 15px;
}


.menu-item:first-child {
    border-top: 0;
}


.menu-image {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    background: #fff0f3;
}


.no-image {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    background: #fff0f3;
    color: #e00038;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 25px;
}


.item-info {
    flex: 1;
}


.item-name {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}


.item-description {
    color: #777777;
    font-size: 12px;
    margin-bottom: 6px;
}


.item-category {
    display: inline-block;
    background: #f4f4f4;
    color: #666666;
    padding: 5px 8px;
    border-radius: 12px;
    font-size: 10px;
}


.item-price {
    color: #e00038;
    font-weight: bold;
    margin-top: 7px;
}


.item-status {
    margin-right: 10px;
}


.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 15px;
    font-size: 10px;
    font-weight: bold;
}


.badge-active {
    background: #e7f8ed;
    color: #18733e;
}


.badge-hidden {
    background: #eeeeee;
    color: #777777;
}


.actions {
    display: flex;
    gap: 7px;
}


.action-button {
    border: 0;
    border-radius: 7px;
    padding: 9px 12px;
    cursor: pointer;
    font-size: 11px;
    font-weight: bold;
}


.toggle-button {
    background: #edf6ff;
    color: #1769aa;
}


.delete-button {
    background: #fff0f0;
    color: #c52323;
}


.empty {
    padding: 55px 20px;
    text-align: center;
    color: #888888;
}


.restaurant-warning {
    padding: 25px;
    background: #fff8e8;
    color: #8a6200;
    border-radius: 12px;
    margin-bottom: 25px;
}


@media (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }


    .form-group.full {
        grid-column: auto;
    }


    .menu-item {
        flex-wrap: wrap;
    }


    .item-info {
        min-width: 200px;
    }


    .item-status {
        margin-right: 0;
    }


    .topbar {
        padding: 14px 4%;
    }

}


</style>

</head>


<body>


<header class="topbar">

    <a
        href="restaurant-owner-dashboard.php"
        class="logo"
    >
        Humsafar
    </a>


    <div class="top-links">

        <a
            href="restaurant-owner-dashboard.php"
            class="dashboard-link"
        >
            Dashboard
        </a>


        <a
            href="restaurant-owner-login.php"
            class="login-link"
        >
            Logout
        </a>

    </div>

</header>


<main class="container">


    <section class="heading">

        <div class="restaurant-name">

            Restaurant:
            <?= h($restaurantName) ?>

        </div>


        <h1>
            Manage Menu
        </h1>


        <p>
            Add, hide, activate or delete your restaurant menu items.
        </p>

    </section>


    <?php if ($successMessage !== ''): ?>

        <div class="message success">

            <?= h($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage !== ''): ?>

        <div class="message error">

            <?= h($errorMessage) ?>

        </div>

    <?php endif; ?>


    <?php if (!$restaurant): ?>

        <div class="restaurant-warning">

            <strong>
                Restaurant is not available.
            </strong>

            <br>

            Your restaurant owner account contains:

            <strong>
                <?= h($restaurantName) ?>
            </strong>

            <br><br>

            But this restaurant has not been found in the
            <strong>restaurants</strong> table.

        </div>

    <?php else: ?>


        <!-- =====================================================
             ADD MENU ITEM
        ====================================================== -->

        <section class="card">

            <div class="card-header">

                <h2>
                    Add New Menu Item
                </h2>

                <p>
                    Add the food item that you want customers to see.
                </p>

            </div>


            <div class="form">

                <form
                    method="POST"
                    enctype="multipart/form-data"
                >


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Item Name *
                            </label>

                            <input
                                type="text"
                                name="name"
                                maxlength="150"
                                placeholder="Chicken Biryani"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Price (PKR) *
                            </label>

                            <input
                                type="number"
                                name="price"
                                min="0"
                                step="0.01"
                                placeholder="450"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Category
                            </label>

                            <input
                                type="text"
                                name="category"
                                maxlength="100"
                                placeholder="Biryani"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Item Image
                            </label>

                            <input
                                type="file"
                                name="image"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                        </div>


                        <div class="form-group full">

                            <label>
                                Description
                            </label>

                            <textarea
                                name="description"
                                maxlength="2000"
                                placeholder="Describe your food item..."
                            ></textarea>

                        </div>


                    </div>


                    <button
                        type="submit"
                        name="add_item"
                        value="1"
                        class="add-button"
                    >
                        Add Menu Item
                    </button>


                </form>

            </div>

        </section>


        <!-- =====================================================
             MENU ITEMS
        ====================================================== -->

        <section class="card">

            <div class="card-header">

                <h2>
                    Restaurant Menu
                </h2>

                <p>
                    Your menu items are linked with
                    <?= h($restaurantName) ?>.
                </p>

            </div>


            <?php if (!empty($menuItems)): ?>


                <div class="menu-list">


                    <?php foreach ($menuItems as $item): ?>


                        <div class="menu-item">


                            <?php

                            $itemImage = trim(
                                (string) (
                                    isset($item['image'])
                                        ? $item['image']
                                        : ''
                                )
                            );

                            ?>


                            <?php if ($itemImage !== ''): ?>

                                <img
                                    src="<?= h(
                                        $imageUrl .
                                        basename($itemImage)
                                    ) ?>"
                                    alt="<?= h(
                                        $item['name']
                                    ) ?>"
                                    class="menu-image"
                                >

                            <?php else: ?>

                                <div class="no-image">

                                    🍽

                                </div>

                            <?php endif; ?>


                            <div class="item-info">


                                <div class="item-name">

                                    <?= h(
                                        $item['name']
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $item['description']
                                    )
                                ): ?>

                                    <div class="item-description">

                                        <?= h(
                                            $item['description']
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $item['category']
                                    )
                                ): ?>

                                    <span class="item-category">

                                        <?= h(
                                            $item['category']
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <div class="item-price">

                                    Rs.
                                    <?= number_format(
                                        (float) $item['price'],
                                        2
                                    ) ?>

                                </div>


                            </div>


                            <div class="item-status">


                                <?php if (
                                    (int) $item['status'] === 1
                                ): ?>

                                    <span
                                        class="badge badge-active"
                                    >
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="badge badge-hidden"
                                    >
                                        Hidden
                                    </span>

                                <?php endif; ?>


                            </div>


                            <div class="actions">


                                <form
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int) $item['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="toggle_item"
                                        value="1"
                                        class="action-button toggle-button"
                                    >

                                        <?php if (
                                            (int) $item['status'] === 1
                                        ): ?>

                                            Hide

                                        <?php else: ?>

                                            Show

                                        <?php endif; ?>

                                    </button>

                                </form>


                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this menu item?');"
                                >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int) $item['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_item"
                                        value="1"
                                        class="action-button delete-button"
                                    >
                                        Delete
                                    </button>

                                </form>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty">

                    <h3>
                        No Menu Items
                    </h3>

                    <p>
                        Add your first menu item using the form above.
                    </p>

                </div>


            <?php endif; ?>


        </section>


    <?php endif; ?>


</main>


</body>

</html>