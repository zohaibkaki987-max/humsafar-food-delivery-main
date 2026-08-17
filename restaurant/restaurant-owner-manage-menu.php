<?php
/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| RESTAURANT OWNER - MENU MANAGEMENT
|--------------------------------------------------------------------------
|
| Important:
| Categories are loaded from the database `categories` table.
| This removes the old hard-coded category list.
|
| Images:
| Restaurant/menu images are stored in:
| assets/images/restaurants/
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer-pricing.php';
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE
========================================================= */

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {
    die('Database connection is not available.');
}


/* =========================================================
   HELPER
========================================================= */

if (!function_exists('e')) {

    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }

}


/* =========================================================
   OWNER
========================================================= */

$owner = null;
$ownerId = 0;


/*
|--------------------------------------------------------------------------
| Find owner by possible session IDs
|--------------------------------------------------------------------------
*/

$possibleIds = [

    $_SESSION['restaurant_owner_id'] ?? 0,
    $_SESSION['restaurant_user_id'] ?? 0,
    $_SESSION['owner_id'] ?? 0

];


foreach (
    $possibleIds
    as $possibleId
) {

    $possibleId =
        (int)$possibleId;

    if (
        $possibleId <= 0
    ) {
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


    $stmt->bind_param(
        "i",
        $possibleId
    );


    if (
        $stmt->execute()
    ) {

        $result =
            $stmt->get_result();

        if ($result) {

            $owner =
                $result->fetch_assoc();
        }
    }


    $stmt->close();


    if ($owner) {

        $ownerId =
            (int)$owner['id'];

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
        trim(
            (string)(
                $_SESSION['restaurant_owner_email']
                ?? $_SESSION['email']
                ?? ''
            )
        );


    if (
        $ownerEmail !== ''
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


            if (
                $stmt->execute()
            ) {

                $result =
                    $stmt->get_result();

                if ($result) {

                    $owner =
                        $result->fetch_assoc();
                }
            }


            $stmt->close();
        }
    }

}


/*
|--------------------------------------------------------------------------
| No owner
|--------------------------------------------------------------------------
*/

if (!$owner) {

    header(
        'Location: restaurant-owner-login.php'
    );

    exit;
}


/* =========================================================
   OWNER DATA
========================================================= */

$ownerId =
    (int)$owner['id'];


$ownerName =
    trim(
        (string)(
            $owner['full_name']
            ?? ''
        )
    );


$ownerEmail =
    trim(
        (string)(
            $owner['email']
            ?? ''
        )
    );


$ownerRestaurantName =
    trim(
        (string)(
            $owner['restaurant_name']
            ?? ''
        )
    );


$ownerStatus =
    strtolower(
        trim(
            (string)(
                $owner['status']
                ?? 'pending'
            )
        )
    );


$isApproved =
    in_array(
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
    __DIR__ .
    '/../assets/images/restaurants/';


$imageUrl =
    '../assets/images/restaurants/';


if (
    !is_dir(
        $imageDirectory
    )
) {

    @mkdir(
        $imageDirectory,
        0755,
        true
    );
}


/* =========================================================
   RESTAURANT OWNER_ID COLUMN CHECK
========================================================= */

$hasOwnerId = false;


$ownerColumnResult =
    $conn->query(
        "SHOW COLUMNS FROM restaurants LIKE 'owner_id'"
    );


if (
    $ownerColumnResult &&
    $ownerColumnResult->num_rows > 0
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


            if (
                $stmt->execute()
            ) {

                $result =
                    $stmt->get_result();

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


            if (
                $stmt->execute()
            ) {

                $result =
                    $stmt->get_result();

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
   ADMIN MARKUP
========================================================= */

$adminPercentage = 0.00;


/*
|--------------------------------------------------------------------------
| First use global app_settings percentage
|--------------------------------------------------------------------------
*/

$settingsTable =
    $conn->query(
        "SHOW TABLES LIKE 'app_settings'"
    );


if (
    $settingsTable &&
    $settingsTable->num_rows > 0
) {

    $stmt = $conn->prepare("
        SELECT
            setting_value
        FROM app_settings
        WHERE setting_key =
              'platform_markup_percent'
        LIMIT 1
    ");


    if ($stmt) {

        $stmt->execute();

        $setting =
            $stmt
                ->get_result()
                ->fetch_assoc();

        $stmt->close();


        if ($setting) {

            $adminPercentage =
                max(
                    0,
                    (float)$setting['setting_value']
                );
        }
    }
}



/* =========================================================
   MENU_ITEMS COLUMN CHECK
========================================================= */

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


$hasCategoryId =
    in_array(
        'category_id',
        $menuColumns,
        true
    );


$hasCategory =
    in_array(
        'category',
        $menuColumns,
        true
    );


$hasImage =
    in_array(
        'image',
        $menuColumns,
        true
    );


$hasDescription =
    in_array(
        'description',
        $menuColumns,
        true
    );


$hasStatus =
    in_array(
        'status',
        $menuColumns,
        true
    );


$hasPrice =
    in_array(
        'price',
        $menuColumns,
        true
    );


$hasRestaurantId =
    in_array(
        'restaurant_id',
        $menuColumns,
        true
    );


$hasName =
    in_array(
        'name',
        $menuColumns,
        true
    );


/* =========================================================
   DATABASE CATEGORIES
========================================================= */

$categories = [];


$categoryResult =
    $conn->query("
        SELECT
            id,
            name,
            image
        FROM categories
        WHERE status = 1
        ORDER BY id ASC
    ");


if ($categoryResult) {

    while (
        $category =
        $categoryResult->fetch_assoc()
    ) {

        $categories[] =
            $category;
    }
}


/*
|--------------------------------------------------------------------------
| Category helper map
|--------------------------------------------------------------------------
*/

$categoryMap = [];

foreach (
    $categories
    as $category
) {

    $categoryMap[
        (int)$category['id']
    ] =
        $category['name'];
}


/* =========================================================
   UTILITY
========================================================= */

function calculateCustomerPrice(
    $ownerPrice,
    $percentage
) {

    $ownerPrice =
        (float)$ownerPrice;

    $percentage =
        (float)$percentage;

    return
        $ownerPrice +
        (
            $ownerPrice *
            ($percentage / 100)
        );
}


/*
|--------------------------------------------------------------------------
| Save category IDs where available.
|--------------------------------------------------------------------------
*/

function getCategoryIdByName(
    mysqli $conn,
    string $categoryName
) {

    $stmt = $conn->prepare("
        SELECT
            id
        FROM categories
        WHERE LOWER(TRIM(name))
              =
              LOWER(TRIM(?))
          AND status = 1
        LIMIT 1
    ");


    if (!$stmt) {
        return 0;
    }


    $stmt->bind_param(
        "s",
        $categoryName
    );


    $stmt->execute();


    $row =
        $stmt
            ->get_result()
            ->fetch_assoc();


    $stmt->close();


    return
        $row
            ? (int)$row['id']
            : 0;
}


/* =========================================================
   ADD MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_item'])
) {


    if (!$isApproved) {

        $errorMessage =
            'Your account must be approved before managing menu items.';

    } elseif (!$restaurant) {

        $errorMessage =
            'Restaurant record not found.';

    } else {


        $itemName =
            trim(
                (string)(
                    $_POST['item_name']
                    ?? ''
                )
            );


        $description =
            trim(
                (string)(
                    $_POST['description']
                    ?? ''
                )
            );


        $selectedCategoryId =
            (int)(
                $_POST['category_id']
                ?? 0
            );


        $selectedCategory =
            trim(
                (string)(
                    $_POST['category']
                    ?? ''
                )
            );


        $ownerPrice =
            $_POST['owner_price']
            ?? '';


        $available =
            isset(
                $_POST['available']
            )
                ? 1
                : 0;


        /*
        |--------------------------------------------------------------------------
        | If category_id was selected, get official category name
        |--------------------------------------------------------------------------
        */

        if (
            $selectedCategoryId > 0 &&
            isset(
                $categoryMap[
                    $selectedCategoryId
                ]
            )
        ) {

            $selectedCategory =
                $categoryMap[
                    $selectedCategoryId
                ];
        }


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $itemName === ''
        ) {

            $errorMessage =
                'Please enter item name.';

        } elseif (
            $selectedCategoryId <= 0 ||
            $selectedCategory === ''
        ) {

            $errorMessage =
                'Please select a valid category.';

        } elseif (
            $ownerPrice === '' ||
            !is_numeric($ownerPrice) ||
            (float)$ownerPrice <= 0
        ) {

            $errorMessage =
                'Please enter a valid owner price.';

        } elseif (
            !$hasRestaurantId ||
            !$hasName ||
            !$hasPrice ||
            !$hasCategory
        ) {

            $errorMessage =
                'Required menu_items columns are missing.';

        } else {


            $ownerPrice =
                (float)$ownerPrice;


            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            $itemImage = '';


            if (
                isset(
                    $_FILES['item_image']
                ) &&
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

                        $errorMessage =
                            'Only JPG, JPEG, PNG and WEBP images are allowed.';

                    } else {


                        $imageInfo =
                            @getimagesize(
                                $_FILES['item_image']['tmp_name']
                            );


                        if (
                            $imageInfo === false
                        ) {

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


            /*
            |--------------------------------------------------------------------------
            | INSERT
            |--------------------------------------------------------------------------
            */

            if (
                $errorMessage === ''
            ) {


                /*
                |--------------------------------------------------------------------------
                | If category_id exists, save both:
                | category_id
                | category
                |--------------------------------------------------------------------------
                */

                if (
                    $hasCategoryId
                ) {


                    $stmt = $conn->prepare("
                        INSERT INTO menu_items
                        (
                            restaurant_id,
                            name,
                            description,
                            price,
                            image,
                            category,
                            category_id,
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
                            ?,
                            ?
                        )
                    ");


                    if ($stmt) {

                        $stmt->bind_param(
                            "issdssii",
                            $restaurantId,
                            $itemName,
                            $description,
                            $ownerPrice,
                            $itemImage,
                            $selectedCategory,
                            $selectedCategoryId,
                            $available
                        );


                    } else {

                        $errorMessage =
                            'Database error: ' .
                            $conn->error;

                    }

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | Backward-compatible insert
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


                    if ($stmt) {

                        $stmt->bind_param(
                            "issdssi",
                            $restaurantId,
                            $itemName,
                            $description,
                            $ownerPrice,
                            $itemImage,
                            $selectedCategory,
                            $available
                        );


                    } else {

                        $errorMessage =
                            'Database error: ' .
                            $conn->error;

                    }

                }


                if (
                    $errorMessage === '' &&
                    $stmt
                ) {


                    if (
                        $stmt->execute()
                    ) {

                        $successMessage =
                            'Menu item added successfully.';

                    } else {

                        $errorMessage =
                            'Unable to add menu item: ' .
                            $stmt->error;


                        /*
                        |--------------------------------------------------------------------------
                        | Remove uploaded image if insert failed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $itemImage !== ''
                        ) {

                            $failedImage =
                                $imageDirectory .
                                basename(
                                    $itemImage
                                );


                            if (
                                is_file(
                                    $failedImage
                                )
                            ) {

                                @unlink(
                                    $failedImage
                                );
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
   EDIT MENU ITEM
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['edit_item'])
) {


    if (!$isApproved) {

        $errorMessage =
            'Your account must be approved before managing menu items.';

    } elseif (!$restaurant) {

        $errorMessage =
            'Restaurant record not found.';

    } else {


        $itemId =
            (int)(
                $_POST['item_id']
                ?? 0
            );


        $itemName =
            trim(
                (string)(
                    $_POST['item_name']
                    ?? ''
                )
            );


        $description =
            trim(
                (string)(
                    $_POST['description']
                    ?? ''
                )
            );


        $selectedCategoryId =
            (int)(
                $_POST['category_id']
                ?? 0
            );


        $selectedCategory =
            trim(
                (string)(
                    $_POST['category']
                    ?? ''
                )
            );


        $ownerPrice =
            $_POST['owner_price']
            ?? '';


        $available =
            isset(
                $_POST['available']
            )
                ? 1
                : 0;


        if (
            $selectedCategoryId > 0 &&
            isset(
                $categoryMap[
                    $selectedCategoryId
                ]
            )
        ) {

            $selectedCategory =
                $categoryMap[
                    $selectedCategoryId
                ];
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $itemId <= 0
        ) {

            $errorMessage =
                'Invalid menu item.';

        } elseif (
            $itemName === ''
        ) {

            $errorMessage =
                'Please enter item name.';

        } elseif (
            $selectedCategoryId <= 0 ||
            $selectedCategory === ''
        ) {

            $errorMessage =
                'Please select a valid category.';

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
            | GET OLD ITEM / IMAGE
            |--------------------------------------------------------------------------
            */

            $oldImage = '';


            $stmt =
                $conn->prepare("
                    SELECT
                        image,
                        category,
                        category_id
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


                $oldItem =
                    $stmt
                        ->get_result()
                        ->fetch_assoc();


                $stmt->close();


                if ($oldItem) {

                    $oldImage =
                        (string)(
                            $oldItem['image']
                            ?? ''
                        );

                } else {

                    $errorMessage =
                        'Menu item not found.';
                }

            }


            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            $itemImage =
                $oldImage;

            $newImageUploaded =
                false;


            if (
                $errorMessage === '' &&
                isset(
                    $_FILES['item_image']
                ) &&
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

                        $errorMessage =
                            'Invalid image format.';

                    } else {


                        $imageInfo =
                            @getimagesize(
                                $_FILES['item_image']['tmp_name']
                            );


                        if (
                            $imageInfo === false
                        ) {

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

            if (
                $errorMessage === ''
            ) {


                if (
                    $hasCategoryId
                ) {


                    $stmt = $conn->prepare("
                        UPDATE menu_items
                        SET
                            name = ?,
                            description = ?,
                            price = ?,
                            image = ?,
                            category = ?,
                            category_id = ?,
                            status = ?
                        WHERE id = ?
                          AND restaurant_id = ?
                        LIMIT 1
                    ");


                    if ($stmt) {

                        $stmt->bind_param(
                            "ssdssiiii",
                            $itemName,
                            $description,
                            $ownerPrice,
                            $itemImage,
                            $selectedCategory,
                            $selectedCategoryId,
                            $available,
                            $itemId,
                            $restaurantId
                        );
                    }

                } else {


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


                    if ($stmt) {

                        $stmt->bind_param(
                            "ssdssiii",
                            $itemName,
                            $description,
                            $ownerPrice,
                            $itemImage,
                            $selectedCategory,
                            $available,
                            $itemId,
                            $restaurantId
                        );
                    }

                }


                if (
                    $errorMessage === '' &&
                    $stmt
                ) {


                    if (
                        $stmt->execute()
                    ) {

                        $successMessage =
                            'Menu item updated successfully.';


                        /*
                        |--------------------------------------------------------------------------
                        | Delete old image
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $newImageUploaded &&
                            $oldImage !== '' &&
                            basename(
                                $oldImage
                            ) !== basename(
                                $itemImage
                            )
                        ) {

                            $oldPath =
                                $imageDirectory .
                                basename(
                                    $oldImage
                                );


                            if (
                                is_file(
                                    $oldPath
                                )
                            ) {

                                @unlink(
                                    $oldPath
                                );
                            }

                        }

                    } else {

                        $errorMessage =
                            'Unable to update menu item: ' .
                            $stmt->error;


                        /*
                        |--------------------------------------------------------------------------
                        | Remove new image if update failed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $newImageUploaded
                        ) {

                            $newPath =
                                $imageDirectory .
                                basename(
                                    $itemImage
                                );


                            if (
                                is_file(
                                    $newPath
                                )
                            ) {

                                @unlink(
                                    $newPath
                                );
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


    if (
        !$isApproved ||
        !$restaurant
    ) {

        $errorMessage =
            'You are not allowed to delete menu items.';

    } else {


        $itemId =
            (int)(
                $_POST['item_id']
                ?? 0
            );


        if (
            $itemId > 0
        ) {


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


                $stmt->execute();


                $item =
                    $stmt
                        ->get_result()
                        ->fetch_assoc();


                $stmt->close();


                if ($item) {

                    $oldImage =
                        (string)(
                            $item['image']
                            ?? ''
                        );
                }
            }


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

                        $oldPath =
                            $imageDirectory .
                            basename(
                                $oldImage
                            );


                        if (
                            is_file(
                                $oldPath
                            )
                        ) {

                            @unlink(
                                $oldPath
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

}


/* =========================================================
   TOGGLE AVAILABILITY
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_item'])
) {


    if (
        !$isApproved ||
        !$restaurant
    ) {

        $errorMessage =
            'You are not allowed to change item availability.';

    } else {


        $itemId =
            (int)(
                $_POST['item_id']
                ?? 0
            );


        $newStatus =
            !empty(
                $_POST['new_status']
            )
                ? 1
                : 0;


        if (
            $itemId > 0
        ) {


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

}


/* =========================================================
   GET MENU ITEMS
========================================================= */

$menuItems = [];


if (
    $isApproved &&
    $restaurantId > 0
) {


    $selectFields = [

        'id',
        'name',
        'price',
        'status'

    ];


    if ($hasDescription) {
        $selectFields[] =
            'description';
    }


    if ($hasImage) {
        $selectFields[] =
            'image';
    }


    if ($hasCategory) {
        $selectFields[] =
            'category';
    }


    if ($hasCategoryId) {
        $selectFields[] =
            'category_id';
    }


    $selectSql =
        implode(
            ', ',
            $selectFields
        );


    $stmt =
        $conn->prepare("
            SELECT
                {$selectSql}
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


        while (
            $row =
            $result->fetch_assoc()
        ) {

            $menuItems[] =
                $row;
        }


        $stmt->close();
    }

}


/* =========================================================
   MENU CATEGORY GROUPS
========================================================= */

$groupedMenu = [];


foreach (
    $menuItems
    as $menuItem
) {

    $categoryName =
        trim(
            (string)(
                $menuItem['category']
                ?? ''
            )
        );


    $categoryId =
        (int)(
            $menuItem['category_id']
            ?? 0
        );


    if (
        $categoryId > 0 &&
        isset(
            $categoryMap[
                $categoryId
            ]
        )
    ) {

        $categoryName =
            $categoryMap[
                $categoryId
            ];
    }


    if (
        $categoryName === ''
    ) {

        $categoryName =
            'Other';
    }


    if (
        !isset(
            $groupedMenu[
                $categoryName
            ]
        )
    ) {

        $groupedMenu[
            $categoryName
        ] = [];
    }


    $groupedMenu[
        $categoryName
    ][] =
        $menuItem;

}


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
   MAIN
========================================================= */

.main {

    margin-left: 230px;

    min-height: 100vh;
}


.topbar {

    height: 65px;

    padding:
        0 28px;

    background: #ffffff;

    border-bottom:
        1px solid #e8e8eb;

    display: flex;

    align-items: center;

    justify-content:
        space-between;
}


.topbar small {

    display: block;

    font-size: 8px;

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

    border:
        1px solid #e4e5e8;

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


.top-avatar {

    width: 32px;

    height: 32px;

    border-radius: 50%;

    background: #ed0038;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 900;
}


.top-user-text strong {

    font-size: 11px;
}


.top-user-text span {

    display: block;

    margin-top: 2px;

    color: #999;

    font-size: 8px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        32px 28px 55px;

    max-width: 1450px;
}


.heading {

    margin-bottom: 22px;
}


.eyebrow {

    color: #ed0038;

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1.5px;
}


.heading h1 {

    margin:
        7px 0 5px;

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

    padding:
        13px 16px;

    margin-bottom: 20px;

    border-radius: 10px;

    font-size: 11px;

    font-weight: 700;
}


.alert.success {

    background: #eaf8ef;

    border:
        1px solid #c9ead4;

    color: #17743d;
}


.alert.error {

    background: #fff0f3;

    border:
        1px solid #ffd0d9;

    color: #b4233c;
}


/* =========================================================
   LOCK
========================================================= */

.lock-card {

    padding:
        60px 25px;

    background: #ffffff;

    border:
        1px solid #e8e9ed;

    border-radius: 18px;

    text-align: center;
}


.lock-icon {

    width: 70px;

    height: 70px;

    margin:
        0 auto 18px;

    background: #fff0f4;

    color: #ed0038;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;
}


.lock-card h2 {

    margin:
        0 0 8px;

    font-size: 20px;
}


.lock-card p {

    max-width: 500px;

    margin:
        0 auto;

    color: #888;

    font-size: 11px;

    line-height: 1.6;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background: #ffffff;

    border:
        1px solid #e8e8eb;

    border-radius: 14px;

    margin-bottom: 20px;

    overflow: hidden;
}


.card-header {

    padding:
        17px 20px;

    border-bottom:
        1px solid #eeeeef;
}


.card-header h2 {

    margin: 0;

    font-size: 16px;
}


.card-header p {

    margin:
        5px 0 0;

    color: #91949b;

    font-size: 10px;
}


/* =========================================================
   ADD FORM
========================================================= */

.form-body {

    padding: 20px;
}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 15px;
}


.form-group {

    min-width: 0;
}


.form-group.full {

    grid-column:
        1 / -1;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #444;

    font-size: 10px;

    font-weight: 800;
}


.input,
.select,
.textarea,
.file {

    width: 100%;

    border:
        1px solid #dedfe3;

    border-radius: 8px;

    background: #ffffff;

    outline: none;

    font-family: inherit;

    font-size: 11px;
}


.input,
.select {

    height: 42px;

    padding:
        0 12px;
}


.textarea {

    min-height: 90px;

    padding:
        11px 12px;

    resize: vertical;
}


.input:focus,
.select:focus,
.textarea:focus {

    border-color:
        #ed0038;

    box-shadow:
        0 0 0 3px
        rgba(237,0,56,.07);
}


.file {

    padding: 9px;

    background: #fafafa;
}


.help {

    margin-top: 6px;

    color: #999;

    font-size: 8px;

    line-height: 1.5;
}


/* =========================================================
   CATEGORY SELECT
========================================================= */

.category-select-wrap {

    position: relative;
}


.category-select-info {

    margin-top: 6px;

    display: flex;

    align-items: center;

    gap: 7px;

    color: #8b8e96;

    font-size: 8px;
}


.category-select-info i {

    color: #ed0038;
}


/* =========================================================
   PRICE BOX
========================================================= */

.price-info {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 12px;

    margin-top: 15px;
}


.price-box {

    padding: 13px;

    background: #fafbfc;

    border:
        1px solid #eeeeef;

    border-radius: 9px;
}


.price-box.customer {

    background: #fff3f6;

    border-color:
        #f3ccd8;
}


.price-label {

    color: #999;

    font-size: 8px;

    font-weight: 800;

    text-transform: uppercase;
}


.price-value {

    margin-top: 5px;

    color: #333;

    font-size: 17px;

    font-weight: 900;
}


.price-box.customer
.price-value {

    color: #ed0038;
}


.price-note {

    margin-top: 5px;

    color: #999;

    font-size: 8px;
}


/* =========================================================
   CHECKBOX
========================================================= */

.checkbox-row {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 15px;

    padding:
        11px 12px;

    background: #fafbfc;

    border:
        1px solid #eeeeef;

    border-radius: 8px;
}


.checkbox-row input {

    width: 15px;

    height: 15px;

    accent-color: #ed0038;
}


.checkbox-row label {

    margin: 0;

    color: #444;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   BUTTONS
========================================================= */

.actions {

    margin-top: 18px;

    display: flex;

    justify-content: flex-end;

    gap: 8px;
}


.btn {

    min-height: 38px;

    padding:
        0 15px;

    border: 0;

    border-radius: 8px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    cursor: pointer;

    font-family: inherit;

    font-size: 10px;

    font-weight: 800;

    text-decoration: none;
}


.btn-primary {

    background: #ed0038;

    color: #ffffff;
}


.btn-primary:hover {

    background: #d90035;
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

    padding:
        0 11px;

    font-size: 9px;
}


.btn-success {

    background: #e9f8ef;

    color: #187641;
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

    padding:
        7px 11px;

    border-radius: 20px;

    background: #fff0f4;

    color: #ed0038;

    font-size: 9px;

    font-weight: 900;
}


/* =========================================================
   CATEGORY GROUP
========================================================= */

.menu-category-group {

    border-top:
        1px solid #eeeeef;
}


.menu-category-group:first-child {

    border-top: 0;
}


.menu-category-title {

    padding:
        15px 20px;

    background: #fafbfc;

    border-bottom:
        1px solid #eeeeef;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}


.menu-category-title h3 {

    margin: 0;

    color: #333;

    font-size: 13px;

    font-weight: 900;
}


.menu-category-title span {

    padding:
        5px 9px;

    border-radius: 20px;

    background: #fff0f4;

    color: #ed0038;

    font-size: 8px;

    font-weight: 900;
}


/* =========================================================
   MENU GRID
========================================================= */

.menu-grid {

    padding: 17px;

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0,1fr));

    gap: 17px;
}


.menu-item {

    overflow: hidden;

    border:
        1px solid #e8e8eb;

    border-radius: 14px;

    background: #ffffff;

    transition: .2s ease;
}


.menu-item:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 30px
        rgba(20,20,20,.07);
}


/* =========================================================
   ITEM IMAGE
========================================================= */

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

    padding:
        6px 9px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: 900;

    background: #ffffff;
}


.available {

    color: #187641;
}


.unavailable {

    color: #a13b3b;
}


/* =========================================================
   ITEM BODY
========================================================= */

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

    margin:
        6px 0 5px;

    font-size: 15px;

    font-weight: 900;
}


.item-description {

    min-height: 34px;

    max-height: 34px;

    overflow: hidden;

    color: #8d9098;

    font-size: 9px;

    line-height: 1.55;
}


/* =========================================================
   PRICES
========================================================= */

.prices {

    margin-top: 14px;

    padding-top: 12px;

    border-top:
        1px solid #eeeeef;
}


.price-row {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    gap: 10px;

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


/* =========================================================
   ACTIONS
========================================================= */

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

    padding:
        60px 20px;

    text-align: center;
}


.empty-icon {

    width: 70px;

    height: 70px;

    margin:
        auto;

    border-radius: 50%;

    background: #fff0f4;

    color: #ed0038;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;
}


.empty h3 {

    margin:
        17px 0 7px;

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

    background: #ffffff;

    border-radius: 17px;
}


.modal-header {

    padding:
        19px 22px;

    border-bottom:
        1px solid #eeeeee;

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

}


@media (max-width: 900px) {

    .sidebar {

        width: 70px;
    }


    .brand-text,
    .nav-title,
    .nav a span,
    .sidebar-owner-info {

        display: none;
    }


    .brand {

        justify-content: center;

        padding: 0;
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

        padding:
            23px 14px 40px;
    }


    .form-grid,
    .price-info,
    .menu-grid {

        grid-template-columns: 1fr;
    }


    .form-group.full {

        grid-column:
            auto;
    }


    .topbar {

        padding:
            0 14px;
    }


    .topbar small {

        display: none;
    }


    .topbar strong {

        font-size: 12px;
    }


    .top-user-text {

        display: none;
    }


    .menu-grid {

        padding: 12px;
    }


    .restaurant-info-box {

        grid-template-columns: 1fr;
    }

}


@media (max-width: 480px) {

    .main {

        margin-left: 0;
    }


    .sidebar {

        display: none;
    }


    .content {

        padding:
            18px 10px 30px;
    }


    .heading h1 {

        font-size: 23px;
    }


    .heading p {

        font-size: 10px;
    }


    .card-header,
    .form-body {

        padding:
            14px;
    }


    .menu-grid {

        padding: 9px;

        gap: 10px;
    }


    .item-image {

        height: 150px;
    }


    .item-actions {

        flex-direction: column;
    }


    .actions {

        flex-direction: column;
    }


    .actions .btn {

        width: 100%;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     EXISTING RESTAURANT SIDEBAR
========================================================= -->

<?php

$sidebarFile =
    __DIR__ .
    '/restaurant-sidebar.php';


if (
    file_exists(
        $sidebarFile
    )
) {

    include $sidebarFile;

}

?>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOP BAR -->

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


                <div class="top-avatar">

                    <?= e(
                        $initial
                    ) ?>

                </div>


                <div
                    class="top-user-text"
                >

                    <strong>

                        <?= e(
                            $ownerName !== ''
                                ? $ownerName
                                : 'Restaurant Owner'
                        ) ?>

                    </strong>


                    <span>
                        Restaurant Owner
                    </span>

                </div>


            </div>


        </div>

    </header>



    <!-- CONTENT -->

    <div class="content">


        <!-- HEADING -->

        <section class="heading">


            <div class="eyebrow">
                RESTAURANT MENU
            </div>


            <h1>
                Manage Your Menu
            </h1>


            <p>

                Add, edit, organize and control
                the availability of your restaurant menu items.

            </p>

        </section>



        <!-- MESSAGES -->

        <?php if (
            $successMessage !== ''
        ): ?>

            <div class="alert success">

                <i
                    class="fas fa-circle-check"
                ></i>

                &nbsp;

                <?= e(
                    $successMessage
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            $errorMessage !== ''
        ): ?>

            <div class="alert error">

                <i
                    class="fas fa-circle-exclamation"
                ></i>

                &nbsp;

                <?= e(
                    $errorMessage
                ) ?>

            </div>

        <?php endif; ?>



        <?php if (!$isApproved): ?>


            <!-- LOCKED -->

            <div class="lock-card">


                <div class="lock-icon">

                    <i
                        class="fas fa-lock"
                    ></i>

                </div>


                <h2>
                    Menu Management Locked
                </h2>


                <p>

                    Your restaurant owner account
                    must be approved by the administrator
                    before you can manage menu items.

                </p>


            </div>


        <?php elseif (!$restaurant): ?>


            <!-- RESTAURANT MISSING -->

            <div class="lock-card">


                <div class="lock-icon">

                    <i
                        class="fas fa-store-slash"
                    ></i>

                </div>


                <h2>
                    Restaurant Not Found
                </h2>


                <p>

                    Your owner account is approved,
                    but no restaurant record was found
                    for this account.

                </p>


            </div>


        <?php else: ?>


            <!-- =================================================
                 ADD ITEM
            ================================================== -->

            <section class="card">


                <div class="card-header">


                    <h2>
                        Add New Menu Item
                    </h2>


                    <p>

                        Add food, sides, extras or any
                        other item from your approved categories.

                    </p>


                </div>


                <div class="form-body">


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
                                    placeholder="e.g. Chicken Biryani"
                                    required
                                >


                            </div>



                            <!-- CATEGORY -->

                            <div
                                class="form-group"
                            >


                                <label>
                                    Category *
                                </label>


                                <div
                                    class="category-select-wrap"
                                >


                                    <select
                                        name="category_id"
                                        id="newCategoryId"
                                        class="select"
                                        required
                                    >


                                        <option value="">
                                            Select Category
                                        </option>


                                        <?php foreach (
                                            $categories
                                            as $category
                                        ): ?>


                                            <option
                                                value="<?= (int)$category['id'] ?>"
                                            >

                                                <?= e(
                                                    $category['name']
                                                ) ?>

                                            </option>


                                        <?php endforeach; ?>


                                    </select>


                                    <input
                                        type="hidden"
                                        name="category"
                                        id="newCategoryName"
                                        value=""
                                    >


                                </div>


                                <div
                                    class="category-select-info"
                                >

                                    <i
                                        class="fas fa-database"
                                    ></i>

                                    Categories are managed from the database.

                                </div>


                            </div>



                            <!-- OWNER PRICE -->

                            <div
                                class="form-group"
                            >


                                <label>
                                    Your Price (Rs.) *
                                </label>


                                <input
                                    type="number"
                                    name="owner_price"
                                    id="newOwnerPrice"
                                    class="input"
                                    min="1"
                                    step="0.01"
                                    placeholder="500"
                                    required
                                >


                                <div class="help">

                                    This is your base restaurant price.
                                    Admin markup:
                                    <?= number_format(
                                        $adminPercentage,
                                        2
                                    ) ?>%

                                </div>


                            </div>



                            <!-- CUSTOMER PRICE -->

                            <div
                                class="form-group"
                            >


                                <label>
                                    Customer Price
                                </label>


                                <input
                                    type="text"
                                    id="newCustomerPrice"
                                    class="input"
                                    value="Rs. 0"
                                    readonly
                                >


                                <div class="help">

                                    Estimated customer selling price
                                    after admin markup.

                                </div>


                            </div>



                            <!-- DESCRIPTION -->

                            <div
                                class="form-group full"
                            >


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

                            <div
                                class="form-group"
                            >


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
                                    Image will be saved in
                                    assets/images/restaurants/

                                </div>


                            </div>


                        </div>



                        <!-- AVAILABILITY -->

                        <div
                            class="checkbox-row"
                        >

                            <input
                                type="checkbox"
                                name="available"
                                id="available"
                                value="1"
                                checked
                            >


                            <label
                                for="available"
                            >

                                Item is available

                            </label>

                        </div>



                        <!-- SUBMIT -->

                        <div
                            class="actions"
                        >


                            <button
                                type="submit"
                                name="add_item"
                                value="1"
                                class="btn btn-primary"
                            >

                                <i
                                    class="fas fa-plus"
                                ></i>

                                Add Menu Item

                            </button>


                        </div>


                    </form>

                </div>

            </section>



            <!-- =================================================
                 MENU ITEMS
            ================================================== -->

            <section class="card">


                <div class="card-header">


                    <div
                        class="menu-header"
                    >


                        <div>

                            <h2>
                                Your Menu Items
                            </h2>


                            <p>

                                These items will appear
                                on your restaurant page for customers.

                            </p>

                        </div>


                        <span
                            class="item-count"
                        >

                            <?= count(
                                $menuItems
                            ) ?>

                            Items

                        </span>


                    </div>

                </div>



                <?php if (
                    empty($menuItems)
                ): ?>


                    <div class="empty">


                        <div
                            class="empty-icon"
                        >

                            <i
                                class="fas fa-utensils"
                            ></i>

                        </div>


                        <h3>
                            No Menu Items Yet
                        </h3>


                        <p>

                            Add your first menu item
                            using the form above.

                        </p>


                    </div>


                <?php else: ?>


                    <?php foreach (
                        $groupedMenu
                        as $categoryName =>
                        $items
                    ): ?>


                        <div
                            class="menu-category-group"
                        >


                            <div
                                class="menu-category-title"
                            >


                                <h3>

                                    <i
                                        class="fas fa-tag"
                                        style="
                                            color:#ed0038;
                                            margin-right:7px;
                                        "
                                    ></i>

                                    <?= e(
                                        $categoryName
                                    ) ?>

                                </h3>


                                <span>

                                    <?= count(
                                        $items
                                    ) ?>

                                    Items

                                </span>


                            </div>



                            <div
                                class="menu-grid"
                            >


                                <?php foreach (
                                    $items
                                    as $item
                                ): ?>


                                    <?php

                                    $itemId =
                                        (int)(
                                            $item['id']
                                            ?? 0
                                        );


                                    $itemName =
                                        (string)(
                                            $item['name']
                                            ?? ''
                                        );


                                    $itemDescription =
                                        (string)(
                                            $item['description']
                                            ?? ''
                                        );


                                    $itemPrice =
                                        (float)(
                                            $item['price']
                                            ?? 0
                                        );


                                    $itemImage =
                                        trim(
                                            (string)(
                                                $item['image']
                                                ?? ''
                                            )
                                        );


                                    $itemStatus =
                                        !empty(
                                            $item['status']
                                        );


                                    $itemCategoryId =
                                        (int)(
                                            $item['category_id']
                                            ?? 0
                                        );



                                        $customerPrice = humsafar_customer_price_from_db(
                                        $conn,
                                        $itemPrice
                                        );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Normalize image URL
                                    |--------------------------------------------------------------------------
                                    */

                                    $itemImageUrl = '';


                                    if (
                                        $itemImage !== ''
                                    ) {

                                        if (
                                            preg_match(
                                                '/^(https?:\/\/|data:)/i',
                                                $itemImage
                                            )
                                        ) {

                                            $itemImageUrl =
                                                $itemImage;

                                        } else {

                                            $itemImageUrl =
                                                $imageUrl .
                                                basename(
                                                    $itemImage
                                                );

                                        }

                                    }

                                    ?>


                                    <article
                                        class="menu-item"
                                    >


                                        <!-- IMAGE -->

                                        <div
                                            class="item-image"
                                        >


                                            <?php if (
                                                $itemImageUrl !== ''
                                            ): ?>


                                                <img
                                                    src="<?= e(
                                                        $itemImageUrl
                                                    ) ?>"
                                                    alt="<?= e(
                                                        $itemName
                                                    ) ?>"
                                                    onerror="
                                                        this.style.display='none';
                                                        this.nextElementSibling.style.display='flex';
                                                    "
                                                >


                                                <div
                                                    class="no-image"
                                                    style="display:none;"
                                                >

                                                    <i
                                                        class="fas fa-utensils"
                                                    ></i>

                                                </div>


                                            <?php else: ?>


                                                <div
                                                    class="no-image"
                                                >

                                                    <i
                                                        class="fas fa-utensils"
                                                    ></i>

                                                </div>


                                            <?php endif; ?>


                                            <span
                                                class="
                                                    availability
                                                    <?= $itemStatus
                                                        ? 'available'
                                                        : 'unavailable' ?>"
                                            >

                                                <i
                                                    class="fas
                                                    <?= $itemStatus
                                                        ? 'fa-circle-check'
                                                        : 'fa-circle-xmark' ?>"
                                                ></i>


                                                <?= $itemStatus
                                                    ? 'Available'
                                                    : 'Unavailable' ?>

                                            </span>


                                        </div>



                                        <!-- BODY -->

                                        <div
                                            class="item-body"
                                        >


                                            <div
                                                class="item-category"
                                            >

                                                <?= e(
                                                    $categoryName
                                                ) ?>

                                            </div>


                                            <div
                                                class="item-name"
                                            >

                                                <?= e(
                                                    $itemName
                                                ) ?>

                                            </div>


                                            <div
                                                class="item-description"
                                            >

                                                <?= e(
                                                    $itemDescription !== ''
                                                        ? $itemDescription
                                                        : 'No description added.'
                                                ) ?>

                                            </div>



                                            <!-- PRICES -->

                                            <div
                                                class="prices"
                                            >


                                                <div
                                                    class="price-row"
                                                >

                                                    <span>
                                                        Your Price
                                                    </span>


                                                    <strong
                                                        class="owner-price"
                                                    >

                                                        Rs.

                                                        <?= number_format(
                                                            $itemPrice,
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>


                                                <div
                                                    class="price-row"
                                                >

                                                    <span>
                                                        Admin Markup
                                                    </span>


                                                    <strong>

                                                        <?= number_format(
                                                            $adminPercentage,
                                                            2
                                                        ) ?>%

                                                    </strong>

                                                </div>


                                                <div
                                                    class="price-row"
                                                >

                                                    <span>
                                                        Customer Price
                                                    </span>


                                                    <strong
                                                        class="customer-price"
                                                    >

                                                        Rs.

                                                        <?= number_format(
                                                            $customerPrice,
                                                            2
                                                        ) ?>

                                                    </strong>

                                                </div>


                                            </div>



                                            <!-- ACTIONS -->

                                            <div
                                                class="item-actions"
                                            >


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="btn btn-light btn-small"
                                                    onclick='openEditModal(
                                                        <?= json_encode(
                                                            [
                                                                "id" =>
                                                                    $itemId,
                                                                "name" =>
                                                                    $itemName,
                                                                "description" =>
                                                                    $itemDescription,
                                                                "category_id" =>
                                                                    $itemCategoryId,
                                                                "category" =>
                                                                    $categoryName,
                                                                "price" =>
                                                                    $itemPrice,
                                                                "status" =>
                                                                    $itemStatus
                                                                    ? 1
                                                                    : 0,
                                                                "image" =>
                                                                    $itemImageUrl
                                                            ],
                                                            JSON_HEX_TAG |
                                                            JSON_HEX_APOS |
                                                            JSON_HEX_AMP |
                                                            JSON_HEX_QUOT
                                                        ) ?>
                                                    )'
                                                >

                                                    <i
                                                        class="fas fa-pen"
                                                    ></i>

                                                    Edit

                                                </button>



                                                <!-- DELETE -->

                                                <form
                                                    method="POST"
                                                    onsubmit="
                                                        return confirm(
                                                            'Are you sure you want to delete this menu item?'
                                                        );
                                                    "
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="item_id"
                                                        value="<?= $itemId ?>"
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



                                            <!-- AVAILABLE -->

                                            <form
                                                method="POST"
                                                style="
                                                    margin-top:7px;
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="item_id"
                                                    value="<?= $itemId ?>"
                                                >


                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="<?= $itemStatus
                                                        ? 0
                                                        : 1 ?>"
                                                >


                                                <button
                                                    type="submit"
                                                    name="toggle_item"
                                                    value="1"
                                                    class="
                                                        btn
                                                        btn-light
                                                        btn-small
                                                    "
                                                    style="
                                                        width:100%;
                                                    "
                                                >

                                                    <i
                                                        class="
                                                        fas
                                                        <?= $itemStatus
                                                            ? 'fa-eye-slash'
                                                            : 'fa-eye' ?>
                                                        "
                                                    ></i>


                                                    <?= $itemStatus
                                                        ? 'Mark Unavailable'
                                                        : 'Mark Available' ?>


                                                </button>


                                            </form>


                                        </div>


                                    </article>


                                <?php endforeach; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </section>


        <?php endif; ?>


    </div>


</main>



<!-- =========================================================
     EDIT MODAL
========================================================= -->

<div
    class="modal"
    id="editModal"
>


    <div
        class="modal-box"
    >


        <div
            class="modal-header"
        >


            <h2>
                Edit Menu Item
            </h2>


            <button
                type="button"
                class="close"
                onclick="closeEditModal()"
            >

                <i
                    class="fas fa-times"
                ></i>

            </button>


        </div>



        <div
            class="modal-body"
        >


            <form
                method="POST"
                enctype="multipart/form-data"
            >


                <input
                    type="hidden"
                    name="item_id"
                    id="editItemId"
                >


                <div
                    class="form-grid"
                >


                    <!-- NAME -->

                    <div
                        class="form-group"
                    >

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



                    <!-- CATEGORY -->

                    <div
                        class="form-group"
                    >

                        <label>
                            Category *
                        </label>


                        <select
                            name="category_id"
                            id="editCategoryId"
                            class="select"
                            required
                        >


                            <option value="">
                                Select Category
                            </option>


                            <?php foreach (
                                $categories
                                as $category
                            ): ?>

                                <option
                                    value="<?= (int)$category['id'] ?>"
                                >

                                    <?= e(
                                        $category['name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>


                        <input
                            type="hidden"
                            name="category"
                            id="editCategoryName"
                        >

                    </div>



                    <!-- PRICE -->

                    <div
                        class="form-group"
                    >

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


                        <div
                            class="help"
                        >

                            Admin markup:
                            <?= number_format(
                                $adminPercentage,
                                2
                            ) ?>%

                        </div>

                    </div>



                    <!-- CUSTOMER PRICE -->

                    <div
                        class="form-group"
                    >

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



                    <!-- DESCRIPTION -->

                    <div
                        class="form-group full"
                    >

                        <label>
                            Description
                        </label>


                        <textarea
                            name="description"
                            id="editDescription"
                            class="textarea"
                        ></textarea>

                    </div>



                    <!-- IMAGE -->

                    <div
                        class="form-group"
                    >

                        <label>
                            Replace Image
                        </label>


                        <input
                            type="file"
                            name="item_image"
                            class="file"
                            accept=".jpg,.jpeg,.png,.webp"
                        >


                        <div
                            class="help"
                        >

                            Leave empty to keep existing image.

                        </div>

                    </div>



                    <!-- CURRENT IMAGE -->

                    <div
                        class="form-group"
                    >

                        <label>
                            Current Image
                        </label>


                        <div
                            id="editCurrentImage"
                            style="
                                height:130px;
                                background:#fff0f4;
                                border-radius:9px;
                                overflow:hidden;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:#ed0038;
                                font-size:25px;
                            "
                        >

                            <i
                                class="fas fa-image"
                            ></i>

                        </div>

                    </div>


                </div>



                <!-- STATUS -->

                <div
                    class="checkbox-row"
                >

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



                <div
                    class="actions"
                >

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

                        <i
                            class="fas fa-save"
                        ></i>

                        Save Changes

                    </button>

                </div>


            </form>


        </div>


    </div>

</div>



<script>

/*
|--------------------------------------------------------------------------
| ADMIN MARKUP
|--------------------------------------------------------------------------
*/

const adminPercentage =
    <?= json_encode(
        (float)$adminPercentage
    ) ?>;


/*
|--------------------------------------------------------------------------
| CUSTOMER PRICE
|--------------------------------------------------------------------------
*/

function calculateCustomerPrice(
    price
) {

    const amount =
        parseFloat(price) || 0;


    return amount +
        (
            amount *
            (
                adminPercentage /
                100
            )
        );

}


/*
|--------------------------------------------------------------------------
| NEW ITEM LIVE PRICE
|--------------------------------------------------------------------------
*/

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

    function updateNewCustomerPrice() {

        const finalPrice =
            calculateCustomerPrice(
                newOwnerPrice.value
            );


        newCustomerPrice.value =
            'Rs. ' +
            finalPrice.toFixed(2);

    }


    newOwnerPrice.addEventListener(
        'input',
        updateNewCustomerPrice
    );


    updateNewCustomerPrice();

}


/*
|--------------------------------------------------------------------------
| NEW CATEGORY NAME
|--------------------------------------------------------------------------
*/

const newCategoryId =
    document.getElementById(
        'newCategoryId'
    );


const newCategoryName =
    document.getElementById(
        'newCategoryName'
    );


if (
    newCategoryId &&
    newCategoryName
) {

    newCategoryId.addEventListener(
        'change',
        function () {

            const selected =
                newCategoryId.options[
                    newCategoryId.selectedIndex
                ];


            newCategoryName.value =
                selected
                    ? selected.text.trim()
                    : '';

        }
    );

}


/*
|--------------------------------------------------------------------------
| EDIT MODAL
|--------------------------------------------------------------------------
*/

function openEditModal(
    item
) {

    const modal =
        document.getElementById(
            'editModal'
        );


    document.getElementById(
        'editItemId'
    ).value =
        item.id || '';


    document.getElementById(
        'editItemName'
    ).value =
        item.name || '';


    document.getElementById(
        'editDescription'
    ).value =
        item.description || '';


    document.getElementById(
        'editOwnerPrice'
    ).value =
        item.price || '';


    document.getElementById(
        'editAvailable'
    ).checked =
        parseInt(
            item.status || 0
        ) === 1;


    const categoryId =
        document.getElementById(
            'editCategoryId'
        );


    const categoryName =
        document.getElementById(
            'editCategoryName'
        );


    if (categoryId) {

        categoryId.value =
            item.category_id || '';

    }


    if (
        categoryName
    ) {

        categoryName.value =
            item.category || '';

    }


    /*
    |--------------------------------------------------------------------------
    | Customer Price
    |--------------------------------------------------------------------------
    */

    const customerPrice =
        calculateCustomerPrice(
            item.price || 0
        );


    document.getElementById(
        'editCustomerPrice'
    ).value =
        'Rs. ' +
        customerPrice.toFixed(2);


    /*
    |--------------------------------------------------------------------------
    | Image
    |--------------------------------------------------------------------------
    */

    const imageBox =
        document.getElementById(
            'editCurrentImage'
        );


    if (
        imageBox &&
        item.image
    ) {

        imageBox.innerHTML =

            '<img ' +
            'src="' +
            escapeHtmlAttribute(
                item.image
            ) +
            '" ' +
            'style="' +
            'width:100%;' +
            'height:100%;' +
            'object-fit:cover;" ' +
            'alt="Current item image"' +
            ' onerror="this.style.display=\'none\';' +
            'this.parentNode.innerHTML=\'<i class=&quot;fas fa-image&quot;></i>\';">';

    } else if (
        imageBox
    ) {

        imageBox.innerHTML =
            '<i class="fas fa-image"></i>';

    }


    if (modal) {

        modal.classList.add(
            'show'
        );

    }

}


function closeEditModal()
{

    const modal =
        document.getElementById(
            'editModal'
        );


    if (modal) {

        modal.classList.remove(
            'show'
        );

    }

}


function escapeHtmlAttribute(
    value
) {

    return String(
        value || ''
    )
        .replace(
            /&/g,
            '&amp;'
        )
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /</g,
            '&lt;'
        )
        .replace(
            />/g,
            '&gt;'
        );

}


/*
|--------------------------------------------------------------------------
| EDIT PRICE LIVE
|--------------------------------------------------------------------------
*/

const editOwnerPrice =
    document.getElementById(
        'editOwnerPrice'
    );


const editCustomerPrice =
    document.getElementById(
        'editCustomerPrice'
    );


if (
    editOwnerPrice &&
    editCustomerPrice
) {

    editOwnerPrice.addEventListener(
        'input',
        function () {

            const price =
                calculateCustomerPrice(
                    editOwnerPrice.value
                );


            editCustomerPrice.value =
                'Rs. ' +
                price.toFixed(2);

        }
    );

}


/*
|--------------------------------------------------------------------------
| EDIT CATEGORY NAME
|--------------------------------------------------------------------------
*/

const editCategoryId =
    document.getElementById(
        'editCategoryId'
    );


const editCategoryName =
    document.getElementById(
        'editCategoryName'
    );


if (
    editCategoryId &&
    editCategoryName
) {

    editCategoryId.addEventListener(
        'change',
        function () {

            const selected =
                editCategoryId.options[
                    editCategoryId.selectedIndex
                ];


            editCategoryName.value =
                selected
                    ? selected.text.trim()
                    : '';

        }
    );

}


/*
|--------------------------------------------------------------------------
| Close modal on background click
|--------------------------------------------------------------------------
*/

const editModal =
    document.getElementById(
        'editModal'
    );


if (editModal) {

    editModal.addEventListener(
        'click',
        function (event) {

            if (
                event.target ===
                editModal
            ) {

                closeEditModal();

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key ===
            'Escape'
        ) {

            closeEditModal();

        }

    }
);

</script>


</body>

</html>