<?php

/*
|--------------------------------------------------------------------------
| HUMSAFAR FOOD DELIVERY
| RESTAURANT OWNER - DEAL MANAGEMENT
|--------------------------------------------------------------------------
|
| This page follows the existing restaurant-owner system:
|
| - Existing session.php
| - Existing config.php / mysqli $conn
| - Existing restaurant-sidebar.php
| - restaurant_users owner lookup
| - restaurants owner restaurant lookup
| - Existing admin markup system
|
| Deal workflow:
|
| Restaurant Owner
|       ↓
| Create Deal
|       ↓
| Pending
|       ↓
| Admin Approval
|       ↓
| Approved
|       ↓
| Customer
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';


/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CHECK
========================================================= */

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {
    die(
        'Database connection is not available.'
    );
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
   SAFE PREPARE
========================================================= */

function safePrepare(
    $conn,
    $sql
) {

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    return $stmt;
}


/* =========================================================
   TABLE EXISTS
========================================================= */

function tableExists(
    $conn,
    $table
) {

    $table =
        $conn->real_escape_string(
            $table
        );

    $result =
        $conn->query(
            "SHOW TABLES LIKE '$table'"
        );

    return
        $result &&
        $result->num_rows > 0;
}


/* =========================================================
   COLUMN EXISTS
========================================================= */

function columnExists(
    $conn,
    $table,
    $column
) {

    if (
        !tableExists(
            $conn,
            $table
        )
    ) {
        return false;
    }

    $stmt =
        safePrepare(
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

    $result =
        $stmt->get_result();

    $exists =
        $result &&
        $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


/* =========================================================
   FIND RESTAURANT OWNER
========================================================= */

$owner = null;

$ownerId = 0;

$ownerEmail = '';


/*
|--------------------------------------------------------------------------
| SESSION OWNER ID
|--------------------------------------------------------------------------
*/

$possibleOwnerIds = [

    $_SESSION['restaurant_owner_id']
        ?? 0,

    $_SESSION['restaurant_user_id']
        ?? 0,

    $_SESSION['owner_id']
        ?? 0

];


foreach (
    $possibleOwnerIds
    as $possibleId
) {

    $possibleId =
        (int)$possibleId;

    if (
        $possibleId <= 0
    ) {
        continue;
    }


    $stmt =
        safePrepare(
            $conn,
            "
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
            "
        );


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
| FALLBACK BY EMAIL
|--------------------------------------------------------------------------
*/

if (!$owner) {

    $ownerEmail =
        trim(
            (string)(
                $_SESSION[
                    'restaurant_owner_email'
                ]
                ??
                $_SESSION['email']
                ??
                ''
            )
        );


    if (
        $ownerEmail !== ''
    ) {

        $stmt =
            safePrepare(
                $conn,
                "
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
                "
            );


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


/* =========================================================
   OWNER NOT FOUND
========================================================= */

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
            ?? 'Restaurant Owner'
        )
    );


$restaurantName =
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
   FIND RESTAURANT
========================================================= */

$restaurant = null;

$restaurantId = 0;


/*
|--------------------------------------------------------------------------
| Prefer owner_id when available
|--------------------------------------------------------------------------
*/

$hasOwnerId =
    columnExists(
        $conn,
        'restaurants',
        'owner_id'
    );


if (
    $hasOwnerId &&
    $ownerId > 0
) {

    $stmt =
        safePrepare(
            $conn,
            "
            SELECT *
            FROM restaurants
            WHERE owner_id = ?
            LIMIT 1
            "
        );


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
    $restaurantName !== ''
) {

    $stmt =
        safePrepare(
            $conn,
            "
            SELECT *
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
        (int)(
            $restaurant['id']
            ?? 0
        );

}


/* =========================================================
   ADMIN MARKUP
========================================================= */

$adminPercentage = 0.00;


/*
|--------------------------------------------------------------------------
| First: app_settings
|--------------------------------------------------------------------------
*/

if (
    tableExists(
        $conn,
        'app_settings'
    )
) {

    $stmt =
        safePrepare(
            $conn,
            "
            SELECT setting_value
            FROM app_settings
            WHERE setting_key =
                  'platform_markup_percent'
            LIMIT 1
            "
        );


    if ($stmt) {

        $stmt->execute();

        $result =
            $stmt->get_result();

        $setting =
            $result
                ? $result->fetch_assoc()
                : null;


        if ($setting) {

            $adminPercentage =
                max(
                    0,
                    (float)(
                        $setting[
                            'setting_value'
                        ]
                    )
                );

        }


        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| Fallback: restaurants.admin_percentage
|--------------------------------------------------------------------------
*/

if (
    $adminPercentage <= 0 &&
    $restaurant
) {

    $hasAdminPercentage =
        columnExists(
            $conn,
            'restaurants',
            'admin_percentage'
        );


    if ($hasAdminPercentage) {

        $adminPercentage =
            max(
                0,
                (float)(
                    $restaurant[
                        'admin_percentage'
                    ]
                    ?? 0
                )
            );

    }

}


/* =========================================================
   CREATE DEALS TABLE
========================================================= */

/*
|--------------------------------------------------------------------------
| The current repo does not have a dedicated deals table.
|
| We create it automatically so the owner page works without
| requiring a separate manual SQL step.
|--------------------------------------------------------------------------
*/

$createDealsTable = "

CREATE TABLE IF NOT EXISTS restaurant_deals (

    id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    restaurant_id INT NOT NULL,

    owner_id INT NOT NULL,

    name VARCHAR(150) NOT NULL,

    description TEXT NULL,

    image VARCHAR(255) NULL,

    owner_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    valid_from DATE NULL,

    valid_until DATE NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'pending',

    admin_markup_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    admin_markup_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    customer_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    admin_note TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY restaurant_id (restaurant_id),

    KEY owner_id (owner_id),

    KEY status (status)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
";


if (
    !tableExists(
        $conn,
        'restaurant_deals'
    )
) {

    if (
        !$conn->query(
            $createDealsTable
        )
    ) {

        die(
            'Unable to create deals table: ' .
            $conn->error
        );

    }

}


/* =========================================================
   VARIABLES
========================================================= */

$successMessage = '';

$errorMessage = '';

$editDeal = null;


/* =========================================================
   IMAGE DIRECTORY
========================================================= */

/*
|--------------------------------------------------------------------------
| Existing menu management stores restaurant/menu images here.
|--------------------------------------------------------------------------
*/

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
   CUSTOMER PRICE
========================================================= */

function calculateCustomerPrice(
    $ownerPrice,
    $markupPercentage
) {

    $ownerPrice =
        (float)$ownerPrice;

    $markupPercentage =
        (float)$markupPercentage;

    return
        $ownerPrice +
        (
            $ownerPrice *
            (
                $markupPercentage /
                100
            )
        );

}


/* =========================================================
   ADD DEAL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_deal'])
) {


    if (!$isApproved) {

        $errorMessage =
            'Your restaurant owner account must be approved before creating deals.';

    } elseif (
        $restaurantId <= 0
    ) {

        $errorMessage =
            'Restaurant record was not found.';

    } else {


        $dealName =
            trim(
                (string)(
                    $_POST['deal_name']
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


        $ownerPrice =
            $_POST['owner_price']
            ?? '';


        $discount =
            $_POST['discount_percent']
            ?? 0;


        $validFrom =
            trim(
                (string)(
                    $_POST['valid_from']
                    ?? ''
                )
            );


        $validUntil =
            trim(
                (string)(
                    $_POST['valid_until']
                    ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $dealName === ''
        ) {

            $errorMessage =
                'Please enter a deal name.';

        } elseif (
            $ownerPrice === '' ||
            !is_numeric($ownerPrice) ||
            (float)$ownerPrice <= 0
        ) {

            $errorMessage =
                'Please enter a valid deal price.';

        } elseif (
            !is_numeric($discount) ||
            (float)$discount < 0 ||
            (float)$discount > 100
        ) {

            $errorMessage =
                'Discount must be between 0 and 100 percent.';

        } elseif (
            $validFrom !== '' &&
            $validUntil !== '' &&
            strtotime($validUntil)
            <
            strtotime($validFrom)
        ) {

            $errorMessage =
                'Valid until date cannot be before valid from date.';

        } else {


            $ownerPrice =
                (float)$ownerPrice;


            $discount =
                (float)$discount;


            /*
            |--------------------------------------------------------------------------
            | Calculate customer price
            |--------------------------------------------------------------------------
            |
            | Owner price is the price restaurant wants to receive.
            |
            | Admin markup is added separately.
            |--------------------------------------------------------------------------
            */

            $customerPrice =
                calculateCustomerPrice(
                    $ownerPrice,
                    $adminPercentage
                );


            /*
            |--------------------------------------------------------------------------
            | IMAGE
            |--------------------------------------------------------------------------
            */

            $dealImage = '';


            if (
                isset(
                    $_FILES['deal_image']
                ) &&
                $_FILES['deal_image']['error']
                !==
                UPLOAD_ERR_NO_FILE
            ) {


                $file =
                    $_FILES['deal_image'];


                if (
                    $file['error']
                    !==
                    UPLOAD_ERR_OK
                ) {

                    $errorMessage =
                        'Deal image upload failed.';

                } elseif (
                    $file['size']
                    >
                    5 * 1024 * 1024
                ) {

                    $errorMessage =
                        'Deal image must be less than 5 MB.';

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

                        $errorMessage =
                            'Only JPG, JPEG, PNG and WEBP images are allowed.';

                    } elseif (
                        @getimagesize(
                            $file['tmp_name']
                        ) === false
                    ) {

                        $errorMessage =
                            'Selected deal image is not a valid image.';

                    } else {


                        $dealImage =
                            'deal_' .
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
                            $dealImage;


                        if (
                            !move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )
                        ) {

                            $errorMessage =
                                'Unable to save deal image.';

                        }

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT DEAL
            |--------------------------------------------------------------------------
            */

            if (
                $errorMessage === ''
            ) {


                /*
                |--------------------------------------------------------------------------
                | Every new deal starts as PENDING.
                |--------------------------------------------------------------------------
                */

                $status =
                    'pending';


                $stmt =
                    safePrepare(
                        $conn,
                        "
                        INSERT INTO restaurant_deals
                        (
                            restaurant_id,
                            owner_id,
                            name,
                            description,
                            image,
                            owner_price,
                            discount_percent,
                            valid_from,
                            valid_until,
                            status,
                            admin_markup_percent,
                            admin_markup_amount,
                            customer_price
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
                            NULLIF(?, ''),
                            NULLIF(?, ''),
                            ?,
                            ?,
                            ?,
                            ?
                        )
                        "
                    );


                if (!$stmt) {

                    $errorMessage =
                        'Database error: ' .
                        $conn->error;

                } else {


                    $markupAmount =
                        $ownerPrice *
                        (
                            $adminPercentage /
                            100
                        );


                    $stmt->bind_param(
                        "iisssddssdddd",
                        $restaurantId,
                        $ownerId,
                        $dealName,
                        $description,
                        $dealImage,
                        $ownerPrice,
                        $discount,
                        $validFrom,
                        $validUntil,
                        $status,
                        $adminPercentage,
                        $markupAmount,
                        $customerPrice
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $successMessage =
                            'Deal created successfully and sent to admin for approval.';

                    } else {

                        $errorMessage =
                            'Unable to create deal: ' .
                            $stmt->error;


                        /*
                        |--------------------------------------------------------------------------
                        | Remove image if DB insert failed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $dealImage !== ''
                        ) {

                            $failedImage =
                                $imageDirectory .
                                basename(
                                    $dealImage
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
   DELETE DEAL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_deal'])
) {


    $dealId =
        (int)(
            $_POST['deal_id']
            ?? 0
        );


    if (
        !$isApproved
    ) {

        $errorMessage =
            'Your account is not approved.';

    } elseif (
        $dealId <= 0
    ) {

        $errorMessage =
            'Invalid deal.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | First get image
        |--------------------------------------------------------------------------
        */

        $dealImage = '';


        $stmt =
            safePrepare(
                $conn,
                "
                SELECT image
                FROM restaurant_deals
                WHERE id = ?
                  AND restaurant_id = ?
                  AND owner_id = ?
                LIMIT 1
                "
            );


        if ($stmt) {

            $stmt->bind_param(
                "iii",
                $dealId,
                $restaurantId,
                $ownerId
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            if ($result) {

                $row =
                    $result->fetch_assoc();


                if ($row) {

                    $dealImage =
                        trim(
                            (string)(
                                $row['image']
                                ?? ''
                            )
                        );

                }

            }


            $stmt->close();

        }


        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        $stmt =
            safePrepare(
                $conn,
                "
                DELETE FROM restaurant_deals
                WHERE id = ?
                  AND restaurant_id = ?
                  AND owner_id = ?
                LIMIT 1
                "
            );


        if ($stmt) {

            $stmt->bind_param(
                "iii",
                $dealId,
                $restaurantId,
                $ownerId
            );


            if (
                $stmt->execute()
            ) {

                $successMessage =
                    'Deal deleted successfully.';


                /*
                |--------------------------------------------------------------------------
                | Delete image
                |--------------------------------------------------------------------------
                */

                if (
                    $dealImage !== ''
                ) {

                    $imageFile =
                        $imageDirectory .
                        basename(
                            $dealImage
                        );


                    if (
                        is_file(
                            $imageFile
                        )
                    ) {

                        @unlink(
                            $imageFile
                        );

                    }

                }

            } else {

                $errorMessage =
                    'Unable to delete deal: ' .
                    $stmt->error;

            }


            $stmt->close();

        } else {

            $errorMessage =
                'Database error: ' .
                $conn->error;

        }

    }

}


/* =========================================================
   EDIT DEAL - LOAD
========================================================= */

if (
    isset($_GET['edit'])
) {

    $editId =
        (int)$_GET['edit'];


    if (
        $editId > 0
    ) {

        $stmt =
            safePrepare(
                $conn,
                "
                SELECT *
                FROM restaurant_deals
                WHERE id = ?
                  AND restaurant_id = ?
                  AND owner_id = ?
                LIMIT 1
                "
            );


        if ($stmt) {

            $stmt->bind_param(
                "iii",
                $editId,
                $restaurantId,
                $ownerId
            );


            $stmt->execute();


            $result =
                $stmt->get_result();


            if ($result) {

                $editDeal =
                    $result->fetch_assoc();

            }


            $stmt->close();

        }

    }

}


/* =========================================================
   EDIT DEAL - SAVE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_deal'])
) {


    if (!$isApproved) {

        $errorMessage =
            'Your account is not approved.';

    } else {


        $dealId =
            (int)(
                $_POST['deal_id']
                ?? 0
            );


        $dealName =
            trim(
                (string)(
                    $_POST['deal_name']
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


        $ownerPrice =
            $_POST['owner_price']
            ?? '';


        $discount =
            $_POST['discount_percent']
            ?? 0;


        $validFrom =
            trim(
                (string)(
                    $_POST['valid_from']
                    ?? ''
                )
            );


        $validUntil =
            trim(
                (string)(
                    $_POST['valid_until']
                    ?? ''
                )
            );


        if (
            $dealId <= 0
        ) {

            $errorMessage =
                'Invalid deal.';

        } elseif (
            $dealName === ''
        ) {

            $errorMessage =
                'Please enter a deal name.';

        } elseif (
            !is_numeric($ownerPrice) ||
            (float)$ownerPrice <= 0
        ) {

            $errorMessage =
                'Please enter a valid deal price.';

        } elseif (
            !is_numeric($discount) ||
            (float)$discount < 0 ||
            (float)$discount > 100
        ) {

            $errorMessage =
                'Discount must be between 0 and 100 percent.';

        } else {


            $ownerPrice =
                (float)$ownerPrice;


            $discount =
                (float)$discount;


            $customerPrice =
                calculateCustomerPrice(
                    $ownerPrice,
                    $adminPercentage
                );


            $markupAmount =
                $ownerPrice *
                (
                    $adminPercentage /
                    100
                );


            /*
            |--------------------------------------------------------------------------
            | Existing image
            |--------------------------------------------------------------------------
            */

            $oldImage = '';


            $stmt =
                safePrepare(
                    $conn,
                    "
                    SELECT image
                    FROM restaurant_deals
                    WHERE id = ?
                      AND restaurant_id = ?
                      AND owner_id = ?
                    LIMIT 1
                    "
                );


            if ($stmt) {

                $stmt->bind_param(
                    "iii",
                    $dealId,
                    $restaurantId,
                    $ownerId
                );


                $stmt->execute();


                $result =
                    $stmt->get_result();


                if ($result) {

                    $row =
                        $result->fetch_assoc();


                    if ($row) {

                        $oldImage =
                            trim(
                                (string)(
                                    $row['image']
                                    ?? ''
                                )
                            );

                    }

                }


                $stmt->close();

            }


            $newImage =
                $oldImage;


            /*
            |--------------------------------------------------------------------------
            | New image
            |--------------------------------------------------------------------------
            */

            if (
                isset(
                    $_FILES['deal_image']
                ) &&
                $_FILES['deal_image']['error']
                !==
                UPLOAD_ERR_NO_FILE
            ) {


                $file =
                    $_FILES['deal_image'];


                if (
                    $file['error']
                    !==
                    UPLOAD_ERR_OK
                ) {

                    $errorMessage =
                        'Deal image upload failed.';

                } elseif (
                    $file['size']
                    >
                    5 * 1024 * 1024
                ) {

                    $errorMessage =
                        'Deal image must be less than 5 MB.';

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

                        $errorMessage =
                            'Only JPG, JPEG, PNG and WEBP images are allowed.';

                    } elseif (
                        @getimagesize(
                            $file['tmp_name']
                        ) === false
                    ) {

                        $errorMessage =
                            'Selected file is not a valid image.';

                    } else {


                        $newImage =
                            'deal_' .
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
                            $newImage;


                        if (
                            !move_uploaded_file(
                                $file['tmp_name'],
                                $destination
                            )
                        ) {

                            $errorMessage =
                                'Unable to save new deal image.';

                        }

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */

            if (
                $errorMessage === ''
            ) {


                /*
                |--------------------------------------------------------------------------
                | Important:
                | Any owner edit sends the deal back to PENDING.
                |
                | Admin must approve the updated deal again.
                |--------------------------------------------------------------------------
                */

                $status =
                    'pending';


                $stmt =
                    safePrepare(
                        $conn,
                        "
                        UPDATE restaurant_deals

                        SET
                            name = ?,
                            description = ?,
                            image = ?,
                            owner_price = ?,
                            discount_percent = ?,
                            valid_from =
                                NULLIF(?, ''),
                            valid_until =
                                NULLIF(?, ''),
                            status = ?,
                            admin_markup_percent = ?,
                            admin_markup_amount = ?,
                            customer_price = ?,
                            admin_note = NULL

                        WHERE id = ?
                          AND restaurant_id = ?
                          AND owner_id = ?

                        LIMIT 1
                        "
                    );


                if (!$stmt) {

                    $errorMessage =
                        'Database error: ' .
                        $conn->error;

                } else {


                    $stmt->bind_param(
                        "sssddsssdddiii",
                        $dealName,
                        $description,
                        $newImage,
                        $ownerPrice,
                        $discount,
                        $validFrom,
                        $validUntil,
                        $status,
                        $adminPercentage,
                        $markupAmount,
                        $customerPrice,
                        $dealId,
                        $restaurantId,
                        $ownerId
                    );


                    if (
                        $stmt->execute()
                    ) {

                        $successMessage =
                            'Deal updated and sent to admin for approval again.';


                        /*
                        |--------------------------------------------------------------------------
                        | Delete old image after successful update
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $newImage !== $oldImage &&
                            $oldImage !== ''
                        ) {

                            $oldImageFile =
                                $imageDirectory .
                                basename(
                                    $oldImage
                                );


                            if (
                                is_file(
                                    $oldImageFile
                                )
                            ) {

                                @unlink(
                                    $oldImageFile
                                );

                            }

                        }


                        $editDeal =
                            null;

                    } else {

                        $errorMessage =
                            'Unable to update deal: ' .
                            $stmt->error;


                        /*
                        |--------------------------------------------------------------------------
                        | Remove new image if update failed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $newImage !== $oldImage &&
                            $newImage !== ''
                        ) {

                            $newImageFile =
                                $imageDirectory .
                                basename(
                                    $newImage
                                );


                            if (
                                is_file(
                                    $newImageFile
                                )
                            ) {

                                @unlink(
                                    $newImageFile
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
   FETCH DEALS
========================================================= */

$deals = [];


if (
    $restaurantId > 0
) {

    $stmt =
        safePrepare(
            $conn,
            "
            SELECT *
            FROM restaurant_deals

            WHERE restaurant_id = ?
              AND owner_id = ?

            ORDER BY
                created_at DESC,
                id DESC
            "
        );


    if ($stmt) {

        $stmt->bind_param(
            "ii",
            $restaurantId,
            $ownerId
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        if ($result) {

            while (
                $row =
                $result->fetch_assoc()
            ) {

                $deals[] =
                    $row;

            }

        }


        $stmt->close();

    }

}


/* =========================================================
   COUNTS
========================================================= */

$totalDeals =
    count($deals);

$pendingDeals = 0;

$approvedDeals = 0;

$rejectedDeals = 0;


foreach (
    $deals
    as $deal
) {

    $status =
        strtolower(
            trim(
                (string)(
                    $deal['status']
                    ?? ''
                )
            )
        );


    if (
        $status === 'pending'
    ) {

        $pendingDeals++;

    } elseif (
        $status === 'approved' ||
        $status === 'active'
    ) {

        $approvedDeals++;

    } elseif (
        $status === 'rejected'
    ) {

        $rejectedDeals++;

    }

}


/* =========================================================
   OWNER INITIAL
========================================================= */

$nameParts =
    preg_split(
        '/\s+/',
        trim($ownerName)
    );


if (
    count($nameParts) >= 2
) {

    $initial =
        strtoupper(
            substr(
                $nameParts[0],
                0,
                1
            )
            .
            substr(
                $nameParts[1],
                0,
                1
            )
        );

} else {

    $initial =
        strtoupper(
            substr(
                $ownerName,
                0,
                1
            )
        );

}


if (
    $initial === ''
) {

    $initial =
        'R';

}


/* =========================================================
   HTML
========================================================= */

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
        Deal Management | Humsafar
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

            background: #fff8fb;

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
        input,
        textarea,
        select {
            font-family: inherit;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .deal-main {

            margin-left: 223px;

            min-height: 100vh;

            padding:
                16px 28px 40px;
        }


        /* =====================================================
           TOPBAR
        ===================================================== */

        .deal-topbar {

            min-height: 60px;

            background: #fff;

            border:
                1px solid #f0e4e9;

            box-shadow:
                0 4px 16px
                rgba(0,0,0,.04);

            padding:
                10px 14px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 16px;
        }


        .topbar-title small {

            display: block;

            color: #999;

            font-size: 8px;

            letter-spacing: 1px;

            font-weight: 800;
        }


        .topbar-title strong {

            display: block;

            margin-top: 3px;

            font-size: 15px;

            font-weight: 900;
        }


        .topbar-right {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .top-bell {

            width: 35px;

            height: 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            background: #fff0f4;

            color: #ed0038;

            font-size: 13px;
        }


        .top-owner {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .top-owner-avatar {

            width: 34px;

            height: 34px;

            border-radius: 50%;

            background: #ffc400;

            color: #111;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 10px;

            font-weight: 900;
        }


        .top-owner-text strong {

            display: block;

            font-size: 9px;
        }


        .top-owner-text span {

            display: block;

            margin-top: 2px;

            color: #999;

            font-size: 7px;
        }


        /* =====================================================
           PAGE HEADING
        ===================================================== */

        .page-heading {

            margin-bottom: 18px;
        }


        .page-heading .eyebrow {

            color: #ed0038;

            font-size: 8px;

            letter-spacing: 1.3px;

            font-weight: 900;

            text-transform: uppercase;
        }


        .page-heading h1 {

            margin:
                5px 0 5px;

            font-size: 25px;

            font-weight: 900;
        }


        .page-heading p {

            margin: 0;

            color: #8b8e96;

            font-size: 10px;

            line-height: 1.6;
        }


        /* =====================================================
           ALERTS
        ===================================================== */

        .alert {

            margin-bottom: 16px;

            padding:
                11px 14px;

            border-radius: 9px;

            font-size: 10px;

            font-weight: 700;
        }


        .alert.success {

            color: #177245;

            background: #e7f8ed;

            border:
                1px solid #c6ecd5;
        }


        .alert.error {

            color: #b42318;

            background: #fff0ee;

            border:
                1px solid #f2c7c1;
        }


        /* =====================================================
           PAGE HEADER
        ===================================================== */

        .deal-banner {

            background:
                linear-gradient(
                    110deg,
                    #ee003d,
                    #f34882
                );

            color: #fff;

            border-radius: 17px;

            padding:
                21px 23px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 18px;

            box-shadow:
                0 9px 25px
                rgba(239,0,60,.12);
        }


        .deal-banner h2 {

            margin:
                0 0 5px;

            font-size: 20px;
        }


        .deal-banner p {

            margin: 0;

            font-size: 10px;

            opacity: .92;

            line-height: 1.5;
        }


        .deal-banner-icon {

            width: 55px;

            height: 55px;

            flex-shrink: 0;

            border-radius: 15px;

            background:
                rgba(255,255,255,.18);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;
        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(
                    4,
                    minmax(0,1fr)
                );

            gap: 11px;

            margin-bottom: 18px;
        }


        .stat {

            min-height: 100px;

            padding: 14px;

            border:
                1px solid #f1dfe7;

            border-radius: 13px;

            background: #fff;

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .stat-icon {

            width: 38px;

            height: 38px;

            flex-shrink: 0;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fff0f5;

            color: #ef003c;

            font-size: 13px;
        }


        .stat strong {

            display: block;

            font-size: 21px;

            font-weight: 900;
        }


        .stat span {

            display: block;

            margin-top: 3px;

            color: #888;

            font-size: 8px;
        }


        .stat.pending .stat-icon {

            background: #fff5df;

            color: #d88a00;
        }


        .stat.approved .stat-icon {

            background: #e8f8ef;

            color: #18824d;
        }


        .stat.rejected .stat-icon {

            background: #fff0ee;

            color: #c63224;
        }


        /* =====================================================
           CONTENT GRID
        ===================================================== */

        .content-grid {

            display: grid;

            grid-template-columns:
                390px
                minmax(0,1fr);

            gap: 18px;

            align-items: start;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .card {

            background: #fff;

            border:
                1px solid #f0dfe6;

            border-radius: 14px;

            overflow: hidden;
        }


        .card-header {

            padding:
                16px 18px;

            border-bottom:
                1px solid #eeeeef;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;
        }


        .card-header h2 {

            margin: 0;

            font-size: 14px;

            font-weight: 900;
        }


        .card-header p {

            margin:
                4px 0 0;

            color: #999;

            font-size: 8px;
        }


        .card-header-icon {

            width: 34px;

            height: 34px;

            border-radius: 9px;

            background: #fff0f4;

            color: #ed0038;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-body {

            padding: 18px;
        }


        .form-group {

            margin-bottom: 13px;
        }


        .form-group label {

            display: block;

            margin-bottom: 6px;

            color: #444;

            font-size: 9px;

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

            background: #fff;

            color: #333;

            outline: none;

            font-size: 10px;
        }


        .input,
        .select {

            height: 39px;

            padding:
                0 11px;
        }


        .textarea {

            min-height: 85px;

            padding:
                10px 11px;

            resize: vertical;

            line-height: 1.5;
        }


        .file {

            padding: 9px;

            font-size: 9px;
        }


        .input:focus,
        .select:focus,
        .textarea:focus {

            border-color: #ed0038;

            box-shadow:
                0 0 0 3px
                rgba(237,0,56,.06);
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2,minmax(0,1fr));

            gap: 11px;
        }


        .full {

            grid-column:
                1 / -1;
        }


        .help {

            margin-top: 5px;

            color: #999;

            font-size: 7px;

            line-height: 1.5;
        }


        /* =====================================================
           PRICE PREVIEW
        ===================================================== */

        .price-preview {

            margin:
                13px 0;

            padding:
                12px;

            border-radius: 10px;

            background: #fff5f7;

            border:
                1px solid #ffe0e7;
        }


        .price-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 7px;
        }


        .price-row:last-child {

            margin-bottom: 0;
        }


        .price-row span {

            color: #888;

            font-size: 8px;
        }


        .price-row strong {

            color: #555;

            font-size: 10px;
        }


        .price-row.final {

            padding-top: 8px;

            margin-top: 8px;

            border-top:
                1px solid #f3dce3;
        }


        .price-row.final span {

            color: #555;

            font-weight: 800;
        }


        .price-row.final strong {

            color: #ed0038;

            font-size: 17px;

            font-weight: 900;
        }


        .markup-info {

            margin-top: 9px;

            padding:
                8px 10px;

            border-radius: 8px;

            background: #f6faf7;

            color: #47715a;

            font-size: 8px;

            line-height: 1.5;
        }


        .markup-info strong {

            color: #177245;
        }


        /* =====================================================
           BUTTONS
        ===================================================== */

        .btn {

            min-height: 39px;

            padding:
                0 14px;

            border: 0;

            border-radius: 8px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            cursor: pointer;

            font-size: 9px;

            font-weight: 800;

            transition:
                .18s ease;
        }


        .btn-primary {

            width: 100%;

            background:
                linear-gradient(
                    135deg,
                    #ed0038,
                    #f34882
                );

            color: #fff;
        }


        .btn-primary:hover {

            filter:
                brightness(.95);
        }


        .btn-light {

            background: #f4f5f7;

            color: #444;
        }


        .btn-danger {

            background: #fff0ee;

            color: #c63224;
        }


        .btn-small {

            min-height: 31px;

            padding:
                0 9px;

            font-size: 8px;
        }


        .actions {

            display: flex;

            gap: 7px;
        }


        .actions .btn {

            flex: 1;
        }


        /* =====================================================
           DEAL GRID
        ===================================================== */

        .deal-list {

            padding: 15px;

            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0,1fr)
                );

            gap: 13px;
        }


        .deal-card {

            overflow: hidden;

            border:
                1px solid #e8e8eb;

            border-radius: 12px;

            background: #fff;
        }


        .deal-image {

            position: relative;

            height: 150px;

            background: #fff0f4;

            overflow: hidden;
        }


        .deal-image img {

            width: 100%;

            height: 100%;

            display: block;

            object-fit: cover;
        }


        .deal-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #ed0038;

            font-size: 35px;
        }


        .status-badge {

            position: absolute;

            top: 9px;

            right: 9px;

            padding:
                6px 8px;

            border-radius: 20px;

            background: #fff;

            font-size: 7px;

            font-weight: 900;
        }


        .status-pending {

            color: #c17a00;
        }


        .status-approved,
        .status-active {

            color: #177245;
        }


        .status-rejected {

            color: #bd2d22;
        }


        .deal-card-body {

            padding: 13px;
        }


        .deal-card-body h3 {

            margin:
                0 0 4px;

            font-size: 14px;

            font-weight: 900;

            overflow-wrap: anywhere;
        }


        .deal-description {

            min-height: 31px;

            margin: 0 0 10px;

            color: #8d9098;

            font-size: 8px;

            line-height: 1.5;

            display:
                -webkit-box;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        .deal-status-line {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 8px;

            margin-bottom: 10px;
        }


        .deal-status-text {

            font-size: 8px;

            font-weight: 800;
        }


        .deal-date {

            color: #999;

            font-size: 7px;
        }


        .deal-prices {

            padding-top: 9px;

            border-top:
                1px solid #eeeeef;
        }


        .deal-price-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 5px;

            gap: 8px;
        }


        .deal-price-row span {

            color: #999;

            font-size: 7px;
        }


        .deal-price-row strong {

            color: #555;

            font-size: 9px;
        }


        .deal-price-row.customer strong {

            color: #ed0038;

            font-size: 14px;
        }


        .deal-actions {

            display: flex;

            gap: 6px;

            margin-top: 11px;
        }


        .deal-actions a,
        .deal-actions button {

            flex: 1;
        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            padding:
                60px 20px;

            text-align: center;

            grid-column:
                1 / -1;
        }


        .empty-icon {

            width: 65px;

            height: 65px;

            margin:
                0 auto 15px;

            border-radius: 50%;

            background: #fff0f4;

            color: #ed0038;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 23px;
        }


        .empty h3 {

            margin:
                0 0 7px;

            font-size: 16px;
        }


        .empty p {

            margin: 0;

            color: #999;

            font-size: 9px;

            line-height: 1.5;
        }


        /* =====================================================
           PENDING NOTICE
        ===================================================== */

        .approval-note {

            margin-top: 13px;

            padding:
                10px 11px;

            border-radius: 9px;

            background: #fff9eb;

            border:
                1px solid #f3dfaa;

            color: #856404;

            font-size: 8px;

            line-height: 1.55;
        }


        /* =====================================================
           EDIT FORM
        ===================================================== */

        .edit-banner {

            margin-bottom: 13px;

            padding:
                10px 11px;

            border-radius: 8px;

            background: #fff0f4;

            color: #b4002c;

            font-size: 8px;

            font-weight: 700;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .content-grid {

                grid-template-columns:
                    330px
                    minmax(0,1fr);
            }


            .deal-list {

                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 900px) {

            .deal-main {

                margin-left: 72px;

                padding:
                    16px 18px 35px;
            }


            .stats {

                grid-template-columns:
                    repeat(
                        2,
                        minmax(0,1fr)
                    );
            }


            .content-grid {

                grid-template-columns:
                    1fr;
            }

        }


        @media (max-width: 600px) {

            .deal-main {

                margin-left: 72px;

                padding:
                    12px 10px 30px;
            }


            .top-owner-text {

                display: none;
            }


            .deal-banner {

                padding:
                    18px;
            }


            .deal-banner h2 {

                font-size: 17px;
            }


            .stats {

                grid-template-columns:
                    1fr 1fr;
            }


            .form-grid {

                grid-template-columns:
                    1fr;
            }


            .full {

                grid-column:
                    auto;
            }


            .deal-list {

                grid-template-columns:
                    1fr;

                padding: 10px;
            }

        }


        @media (max-width: 480px) {

            .deal-main {

                margin-left: 0;
            }

            .stats {

                grid-template-columns:
                    1fr;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     EXISTING RESTAURANT SIDEBAR
========================================================= -->

<?php

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| We are using the actual existing restaurant-sidebar.php
| from the repository.
|--------------------------------------------------------------------------
*/

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

<main class="deal-main">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="deal-topbar">


        <div class="topbar-title">

            <small>
                RESTAURANT PARTNER PORTAL
            </small>

            <strong>
                Deal Management
            </strong>

        </div>


        <div class="topbar-right">


            <div class="top-bell">

                <i class="far fa-bell"></i>

            </div>


            <div class="top-owner">


                <div class="top-owner-avatar">

                    <?= e($initial) ?>

                </div>


                <div class="top-owner-text">

                    <strong>

                        <?= e(
                            $ownerName
                        ) ?>

                    </strong>

                    <span>
                        Restaurant Owner
                    </span>

                </div>


            </div>


        </div>


    </header>


    <!-- =====================================================
         PAGE HEADING
    ====================================================== -->

    <section class="page-heading">

        <div class="eyebrow">
            RESTAURANT DEALS
        </div>

        <h1>
            Manage Your Deals
        </h1>

        <p>
            Create special offers for your customers.
            Every new deal will be reviewed by Humsafar admin
            before it becomes visible to customers.
        </p>

    </section>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if (
        $successMessage !== ''
    ): ?>

        <div class="alert success">

            <i class="fas fa-circle-check"></i>

            <?= e(
                $successMessage
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $errorMessage !== ''
    ): ?>

        <div class="alert error">

            <i class="fas fa-circle-exclamation"></i>

            <?= e(
                $errorMessage
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         BANNER
    ====================================================== -->

    <section class="deal-banner">


        <div>

            <h2>

                <i class="fas fa-tags"></i>

                Special Restaurant Deals

            </h2>

            <p>

                Set your restaurant's deal price.
                Humsafar's admin markup is added automatically
                to calculate the customer price.

            </p>

        </div>


        <div class="deal-banner-icon">

            <i class="fas fa-percent"></i>

        </div>


    </section>


    <!-- =====================================================
         STATS
    ====================================================== -->

    <section class="stats">


        <!-- TOTAL -->

        <div class="stat">

            <div class="stat-icon">

                <i class="fas fa-tags"></i>

            </div>

            <div>

                <strong>
                    <?= $totalDeals ?>
                </strong>

                <span>
                    Total Deals
                </span>

            </div>

        </div>


        <!-- PENDING -->

        <div class="stat pending">

            <div class="stat-icon">

                <i class="fas fa-clock"></i>

            </div>

            <div>

                <strong>
                    <?= $pendingDeals ?>
                </strong>

                <span>
                    Pending Approval
                </span>

            </div>

        </div>


        <!-- APPROVED -->

        <div class="stat approved">

            <div class="stat-icon">

                <i class="fas fa-circle-check"></i>

            </div>

            <div>

                <strong>
                    <?= $approvedDeals ?>
                </strong>

                <span>
                    Approved Deals
                </span>

            </div>

        </div>


        <!-- REJECTED -->

        <div class="stat rejected">

            <div class="stat-icon">

                <i class="fas fa-circle-xmark"></i>

            </div>

            <div>

                <strong>
                    <?= $rejectedDeals ?>
                </strong>

                <span>
                    Rejected Deals
                </span>

            </div>

        </div>


    </section>


    <!-- =====================================================
         CONTENT GRID
    ====================================================== -->

    <section class="content-grid">


        <!-- =================================================
             CREATE / EDIT FORM
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>

                        <?php if (
                            $editDeal
                        ): ?>

                            Edit Deal

                        <?php else: ?>

                            Create New Deal

                        <?php endif; ?>

                    </h2>

                    <p>
                        Add your special restaurant offer
                    </p>

                </div>


                <div class="card-header-icon">

                    <i class="fas fa-tag"></i>

                </div>

            </div>


            <div class="form-body">


                <?php if (
                    $editDeal
                ): ?>


                    <div class="edit-banner">

                        <i class="fas fa-rotate"></i>

                        Editing this deal will send it back
                        to admin for approval.

                    </div>


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <input
                            type="hidden"
                            name="deal_id"
                            value="<?= (int)(
                                $editDeal['id']
                                ?? 0
                            ) ?>"
                        >


                        <div class="form-group">

                            <label>
                                Deal Name *
                            </label>

                            <input
                                type="text"
                                name="deal_name"
                                class="input"
                                maxlength="150"
                                required
                                value="<?= e(
                                    $editDeal['name']
                                    ?? ''
                                ) ?>"
                                placeholder="Example: Family Dinner Deal"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="textarea"
                                placeholder="Describe what is included in this deal..."
                            ><?= e(
                                $editDeal['description']
                                ?? ''
                            ) ?></textarea>

                        </div>


                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Your Deal Price (Rs.) *
                                </label>

                                <input
                                    type="number"
                                    id="editOwnerPrice"
                                    name="owner_price"
                                    class="input"
                                    min="1"
                                    step="0.01"
                                    required
                                    value="<?= e(
                                        $editDeal['owner_price']
                                        ?? ''
                                    ) ?>"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Discount (%)
                                </label>

                                <input
                                    type="number"
                                    name="discount_percent"
                                    class="input"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="<?= e(
                                        $editDeal['discount_percent']
                                        ?? 0
                                    ) ?>"
                                >

                            </div>


                        </div>


                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Valid From
                                </label>

                                <input
                                    type="date"
                                    name="valid_from"
                                    class="input"
                                    value="<?= e(
                                        $editDeal['valid_from']
                                        ?? ''
                                    ) ?>"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Valid Until
                                </label>

                                <input
                                    type="date"
                                    name="valid_until"
                                    class="input"
                                    value="<?= e(
                                        $editDeal['valid_until']
                                        ?? ''
                                    ) ?>"
                                >

                            </div>


                        </div>


                        <div class="form-group">

                            <label>
                                Deal Image
                            </label>

                            <input
                                type="file"
                                name="deal_image"
                                class="file"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <div class="help">
                                JPG, JPEG, PNG or WEBP.
                                Maximum 5 MB.
                                Leave empty to keep current image.
                            </div>

                        </div>


                        <div class="price-preview">

                            <div class="price-row">

                                <span>
                                    Your Price
                                </span>

                                <strong id="editOwnerPriceText">
                                    Rs. 0.00
                                </strong>

                            </div>


                            <div class="price-row">

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


                            <div class="price-row final">

                                <span>
                                    Customer Price
                                </span>

                                <strong
                                    id="editCustomerPrice"
                                >
                                    Rs. 0.00
                                </strong>

                            </div>


                        </div>


                        <button
                            type="submit"
                            name="update_deal"
                            value="1"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-save"></i>

                            Update Deal

                        </button>


                        <a
                            href="restaurant-owner-manage-deals.php"
                            class="btn btn-light"
                            style="
                                width:100%;
                                margin-top:7px;
                            "
                        >

                            <i class="fas fa-times"></i>

                            Cancel Editing

                        </a>


                    </form>


                <?php else: ?>


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                    >


                        <!-- DEAL NAME -->

                        <div class="form-group">

                            <label>
                                Deal Name *
                            </label>

                            <input
                                type="text"
                                name="deal_name"
                                class="input"
                                maxlength="150"
                                required
                                placeholder="Example: Family Dinner Deal"
                            >

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group">

                            <label>
                                Description
                            </label>

                            <textarea
                                name="description"
                                class="textarea"
                                placeholder="Describe what is included in this deal..."
                            ></textarea>

                        </div>


                        <!-- PRICE -->

                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Your Deal Price (Rs.) *
                                </label>

                                <input
                                    type="number"
                                    id="newOwnerPrice"
                                    name="owner_price"
                                    class="input"
                                    min="1"
                                    step="0.01"
                                    required
                                    placeholder="1000"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Discount (%)
                                </label>

                                <input
                                    type="number"
                                    name="discount_percent"
                                    class="input"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="0"
                                    placeholder="10"
                                >

                            </div>


                        </div>


                        <!-- DATES -->

                        <div class="form-grid">


                            <div class="form-group">

                                <label>
                                    Valid From
                                </label>

                                <input
                                    type="date"
                                    name="valid_from"
                                    class="input"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Valid Until
                                </label>

                                <input
                                    type="date"
                                    name="valid_until"
                                    class="input"
                                >

                            </div>


                        </div>


                        <!-- IMAGE -->

                        <div class="form-group">

                            <label>
                                Deal Image
                            </label>

                            <input
                                type="file"
                                name="deal_image"
                                class="file"
                                accept=".jpg,.jpeg,.png,.webp"
                            >

                            <div class="help">

                                JPG, JPEG, PNG or WEBP.
                                Maximum 5 MB.

                            </div>

                        </div>


                        <!-- PRICE PREVIEW -->

                        <div class="price-preview">


                            <div class="price-row">

                                <span>
                                    Your Deal Price
                                </span>

                                <strong
                                    id="newOwnerPriceText"
                                >
                                    Rs. 0.00
                                </strong>

                            </div>


                            <div class="price-row">

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


                            <div class="price-row final">

                                <span>
                                    Customer Price
                                </span>

                                <strong
                                    id="newCustomerPrice"
                                >
                                    Rs. 0.00
                                </strong>

                            </div>


                            <div class="markup-info">

                                <i class="fas fa-info-circle"></i>

                                Humsafar admin markup of

                                <strong>
                                    <?= number_format(
                                        $adminPercentage,
                                        2
                                    ) ?>%
                                </strong>

                                will be added automatically.

                            </div>


                        </div>


                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            name="add_deal"
                            value="1"
                            class="btn btn-primary"
                        >

                            <i class="fas fa-plus"></i>

                            Create Deal

                        </button>


                        <!-- APPROVAL -->

                        <div class="approval-note">

                            <i class="fas fa-shield-check"></i>

                            Your deal will first go to

                            <strong>
                                Admin Approval
                            </strong>.

                            Customers will only see the deal
                            after it has been approved.

                        </div>


                    </form>


                <?php endif; ?>


            </div>


        </div>


        <!-- =================================================
             DEAL LIST
        ================================================== -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>
                        Your Deals
                    </h2>

                    <p>
                        Manage your restaurant offers
                    </p>

                </div>


                <div class="card-header-icon">

                    <i class="fas fa-list"></i>

                </div>

            </div>


            <div class="deal-list">


                <?php if (
                    !empty($deals)
                ): ?>


                    <?php foreach (
                        $deals
                        as $deal
                    ): ?>


                        <?php

                        $dealId =
                            (int)(
                                $deal['id']
                                ?? 0
                            );


                        $dealName =
                            trim(
                                (string)(
                                    $deal['name']
                                    ?? 'Special Deal'
                                )
                            );


                        $dealDescription =
                            trim(
                                (string)(
                                    $deal['description']
                                    ?? ''
                                )
                            );


                        $dealImage =
                            trim(
                                (string)(
                                    $deal['image']
                                    ?? ''
                                )
                            );


                        $ownerPrice =
                            (float)(
                                $deal['owner_price']
                                ?? 0
                            );


                        $markupPercent =
                            (float)(
                                $deal[
                                    'admin_markup_percent'
                                ]
                                ?? $adminPercentage
                            );


                        $markupAmount =
                            (float)(
                                $deal[
                                    'admin_markup_amount'
                                ]
                                ?? (
                                    $ownerPrice *
                                    (
                                        $markupPercent /
                                        100
                                    )
                                )
                            );


                        $customerPrice =
                            (float)(
                                $deal[
                                    'customer_price'
                                ]
                                ?? (
                                    $ownerPrice +
                                    $markupAmount
                                )
                            );


                        $discount =
                            (float)(
                                $deal[
                                    'discount_percent'
                                ]
                                ?? 0
                            );


                        $status =
                            strtolower(
                                trim(
                                    (string)(
                                        $deal['status']
                                        ?? 'pending'
                                    )
                                )
                            );


                        $validUntil =
                            trim(
                                (string)(
                                    $deal['valid_until']
                                    ?? ''
                                )
                            );


                        $dealImageUrl = '';


                        if (
                            $dealImage !== ''
                        ) {

                            if (
                                preg_match(
                                    '/^(https?:\/\/|data:)/i',
                                    $dealImage
                                )
                            ) {

                                $dealImageUrl =
                                    $dealImage;

                            } else {

                                $dealImageUrl =
                                    $imageUrl .
                                    basename(
                                        $dealImage
                                    );

                            }

                        }


                        $statusClass =
                            'status-' .
                            $status;


                        ?>


                        <article class="deal-card">


                            <!-- IMAGE -->

                            <div class="deal-image">


                                <?php if (
                                    $dealImageUrl !== ''
                                ): ?>


                                    <img
                                        src="<?= e(
                                            $dealImageUrl
                                        ) ?>"
                                        alt="<?= e(
                                            $dealName
                                        ) ?>"
                                        onerror="
                                            this.style.display='none';
                                            this.nextElementSibling.style.display='flex';
                                        "
                                    >


                                    <div
                                        class="deal-placeholder"
                                        style="display:none;"
                                    >

                                        <i class="fas fa-tags"></i>

                                    </div>


                                <?php else: ?>


                                    <div class="deal-placeholder">

                                        <i class="fas fa-tags"></i>

                                    </div>


                                <?php endif; ?>


                                <span
                                    class="
                                        status-badge
                                        <?= e(
                                            $statusClass
                                        ) ?>
                                    "
                                >

                                    <?php if (
                                        $status === 'pending'
                                    ): ?>

                                        <i class="fas fa-clock"></i>

                                        Pending

                                    <?php elseif (
                                        $status === 'approved' ||
                                        $status === 'active'
                                    ): ?>

                                        <i class="fas fa-check-circle"></i>

                                        Approved

                                    <?php elseif (
                                        $status === 'rejected'
                                    ): ?>

                                        <i class="fas fa-times-circle"></i>

                                        Rejected

                                    <?php else: ?>

                                        <?= e(
                                            ucfirst(
                                                $status
                                            )
                                        ) ?>

                                    <?php endif; ?>

                                </span>


                            </div>


                            <!-- BODY -->

                            <div class="deal-card-body">


                                <h3>

                                    <?= e(
                                        $dealName
                                    ) ?>

                                </h3>


                                <p class="deal-description">

                                    <?= e(
                                        $dealDescription !== ''
                                            ? $dealDescription
                                            : 'No description added.'
                                    ) ?>

                                </p>


                                <!-- STATUS -->

                                <div class="deal-status-line">


                                    <span
                                        class="
                                            deal-status-text
                                            <?= e(
                                                $statusClass
                                            ) ?>
                                        "
                                    >

                                        <?php if (
                                            $status === 'pending'
                                        ): ?>

                                            Waiting for admin approval

                                        <?php elseif (
                                            $status === 'approved' ||
                                            $status === 'active'
                                        ): ?>

                                            Live / Approved

                                        <?php elseif (
                                            $status === 'rejected'
                                        ): ?>

                                            Rejected by admin

                                        <?php else: ?>

                                            <?= e(
                                                ucfirst(
                                                    $status
                                                )
                                            ) ?>

                                        <?php endif; ?>

                                    </span>


                                    <?php if (
                                        $validUntil !== ''
                                    ): ?>

                                        <span class="deal-date">

                                            Until:
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $validUntil
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <!-- PRICES -->

                                <div class="deal-prices">


                                    <div class="deal-price-row">

                                        <span>
                                            Your Price
                                        </span>

                                        <strong>

                                            Rs.
                                            <?= number_format(
                                                $ownerPrice,
                                                2
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="deal-price-row">

                                        <span>
                                            Admin Markup
                                        </span>

                                        <strong>

                                            <?= number_format(
                                                $markupPercent,
                                                2
                                            ) ?>%

                                        </strong>

                                    </div>


                                    <div class="deal-price-row">

                                        <span>
                                            Markup Amount
                                        </span>

                                        <strong>

                                            Rs.
                                            <?= number_format(
                                                $markupAmount,
                                                2
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div
                                        class="
                                            deal-price-row
                                            customer
                                        "
                                    >

                                        <span>
                                            Customer Price
                                        </span>

                                        <strong>

                                            Rs.
                                            <?= number_format(
                                                $customerPrice,
                                                2
                                            ) ?>

                                        </strong>

                                    </div>


                                </div>


                                <!-- DISCOUNT -->

                                <?php if (
                                    $discount > 0
                                ): ?>

                                    <div
                                        style="
                                            margin-top:8px;
                                            color:#ed0038;
                                            font-size:8px;
                                            font-weight:800;
                                        "
                                    >

                                        <i class="fas fa-percent"></i>

                                        <?= number_format(
                                            $discount,
                                            0
                                        ) ?>% Discount

                                    </div>

                                <?php endif; ?>


                                <!-- ACTIONS -->

                                <div class="deal-actions">


                                    <a
                                        href="
                                            restaurant-owner-manage-deals.php
                                            ?edit=<?= $dealId ?>
                                        "
                                        class="
                                            btn
                                            btn-light
                                            btn-small
                                        "
                                    >

                                        <i class="fas fa-pen"></i>

                                        Edit

                                    </a>


                                    <form
                                        method="POST"
                                        style="flex:1;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to delete this deal?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="deal_id"
                                            value="<?= $dealId ?>"
                                        >


                                        <button
                                            type="submit"
                                            name="delete_deal"
                                            value="1"
                                            class="
                                                btn
                                                btn-danger
                                                btn-small
                                            "
                                            style="width:100%;"
                                        >

                                            <i class="fas fa-trash"></i>

                                            Delete

                                        </button>

                                    </form>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div class="empty">

                        <div class="empty-icon">

                            <i class="fas fa-tags"></i>

                        </div>


                        <h3>
                            No Deals Yet
                        </h3>


                        <p>

                            Create your first restaurant deal
                            using the form on the left.

                        </p>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </section>


</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

/*
|--------------------------------------------------------------------------
| Existing Admin Markup
|--------------------------------------------------------------------------
*/

const adminPercentage =
    <?= json_encode(
        (float)$adminPercentage
    ) ?>;


/*
|--------------------------------------------------------------------------
| Calculate Customer Price
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
| NEW DEAL PRICE PREVIEW
|--------------------------------------------------------------------------
*/

const newOwnerPrice =
    document.getElementById(
        'newOwnerPrice'
    );


const newOwnerPriceText =
    document.getElementById(
        'newOwnerPriceText'
    );


const newCustomerPrice =
    document.getElementById(
        'newCustomerPrice'
    );


function updateNewPrice()
{

    if (
        !newOwnerPrice
    ) {
        return;
    }


    const price =
        parseFloat(
            newOwnerPrice.value
        ) || 0;


    const customerPrice =
        calculateCustomerPrice(
            price
        );


    if (
        newOwnerPriceText
    ) {

        newOwnerPriceText.textContent =
            'Rs. ' +
            price.toFixed(2);

    }


    if (
        newCustomerPrice
    ) {

        newCustomerPrice.textContent =
            'Rs. ' +
            customerPrice.toFixed(2);

    }

}


if (
    newOwnerPrice
) {

    newOwnerPrice.addEventListener(
        'input',
        updateNewPrice
    );


    updateNewPrice();

}


/*
|--------------------------------------------------------------------------
| EDIT DEAL PRICE PREVIEW
|--------------------------------------------------------------------------
*/

const editOwnerPrice =
    document.getElementById(
        'editOwnerPrice'
    );


const editOwnerPriceText =
    document.getElementById(
        'editOwnerPriceText'
    );


const editCustomerPrice =
    document.getElementById(
        'editCustomerPrice'
    );


function updateEditPrice()
{

    if (
        !editOwnerPrice
    ) {
        return;
    }


    const price =
        parseFloat(
            editOwnerPrice.value
        ) || 0;


    const customerPrice =
        calculateCustomerPrice(
            price
        );


    if (
        editOwnerPriceText
    ) {

        editOwnerPriceText.textContent =
            'Rs. ' +
            price.toFixed(2);

    }


    if (
        editCustomerPrice
    ) {

        editCustomerPrice.textContent =
            'Rs. ' +
            customerPrice.toFixed(2);

    }

}


if (
    editOwnerPrice
) {

    editOwnerPrice.addEventListener(
        'input',
        updateEditPrice
    );


    updateEditPrice();

}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const forms =
            document.querySelectorAll(
                'form'
            );


        forms.forEach(
            function (form) {


                form.addEventListener(
                    'submit',
                    function (event) {


                        const validFrom =
                            form.querySelector(
                                'input[name="valid_from"]'
                            );


                        const validUntil =
                            form.querySelector(
                                'input[name="valid_until"]'
                            );


                        if (
                            validFrom &&
                            validUntil &&
                            validFrom.value !== '' &&
                            validUntil.value !== ''
                        ) {


                            if (
                                validUntil.value
                                <
                                validFrom.value
                            ) {

                                event.preventDefault();


                                alert(
                                    'Valid until date cannot be before valid from date.'
                                );


                                validUntil.focus();

                            }

                        }

                    }
                );

            }
        );

    }
);

</script>


</body>

</html>