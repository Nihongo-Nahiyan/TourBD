<?php
/* =========================================
   START SESSION
========================================= */
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

/* =========================================
   DATABASE CONNECTION
========================================= */
require_once "../config/db.php";

/* =========================================
   ADMIN SECURITY
========================================= */
if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

if($_SESSION['role'] != 'admin'){
    header("Location: ../index.php");
    exit;
}

/* =========================================
   DATE
========================================= */
date_default_timezone_set("Asia/Dhaka");

$current_date = date("l, F j, Y");

$message = "";

/* =========================================
   ADD PACKAGE
========================================= */
if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'add'
){

    $destination_id =
        intval($_POST['destination_id']);

    $hotel_id =
        intval($_POST['hotel_id']);

    $transport_id =
        intval($_POST['transport_id']);

    $package_name =
        trim($_POST['package_name']);

    $description =
        trim($_POST['description']);

    $duration_days =
        intval($_POST['duration_days']);

    $duration_nights =
        intval($_POST['duration_nights']);

    $price =
        floatval($_POST['price']);

    $total_seats =
        intval($_POST['total_seats']);

    $available_seats =
        intval($_POST['available_seats']);

    $departure_date =
        $_POST['departure_date'];

    $image_url =
        trim($_POST['image_url']);

    $status =
        $_POST['status'];

    /* VALIDATION */

    if($package_name == ''){

        $message =
            "Package name is required.";

    }

    elseif($destination_id <= 0){

        $message =
            "Please select a destination.";

    }

    elseif($hotel_id <= 0){

        $message =
            "Please select a hotel.";

    }

    elseif($transport_id <= 0){

        $message =
            "Please select transport.";

    }

    elseif($duration_days <= 0){

        $message =
            "Duration must be greater than 0.";

    }

    elseif($price <= 0){

        $message =
            "Price must be greater than 0.";

    }

    elseif($total_seats <= 0){

        $message =
            "Total seats must be greater than 0.";

    }

    elseif(
        $available_seats < 0
        ||
        $available_seats > $total_seats
    ){

        $message =
            "Available seats must be between 0 and total seats.";

    }

    else{

        $insert_sql = "
        INSERT INTO tour_packages
        (
            destination_id,
            hotel_id,
            transport_id,
            package_name,
            description,
            duration_days,
            duration_nights,
            price,
            total_seats,
            available_seats,
            departure_date,
            image_url,
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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
        ";

        $insert_stmt =
            mysqli_prepare(
                $conn,
                $insert_sql
            );

        mysqli_stmt_bind_param(
            $insert_stmt,
            "iiissiidiisss",
            $destination_id,
            $hotel_id,
            $transport_id,
            $package_name,
            $description,
            $duration_days,
            $duration_nights,
            $price,
            $total_seats,
            $available_seats,
            $departure_date,
            $image_url,
            $status
        );

        mysqli_stmt_execute(
            $insert_stmt
        );

        header(
            "Location: packages.php?added=1"
        );

        exit;

    }

}

/* =========================================
   UPDATE PACKAGE
========================================= */
if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'edit'
){

    $package_id =
        intval($_POST['package_id']);

    $destination_id =
        intval($_POST['destination_id']);

    $hotel_id =
        intval($_POST['hotel_id']);

    $transport_id =
        intval($_POST['transport_id']);

    $package_name =
        trim($_POST['package_name']);

    $description =
        trim($_POST['description']);

    $duration_days =
        intval($_POST['duration_days']);

    $duration_nights =
        intval($_POST['duration_nights']);

    $price =
        floatval($_POST['price']);

    $total_seats =
        intval($_POST['total_seats']);

    $available_seats =
        intval($_POST['available_seats']);

    $departure_date =
        $_POST['departure_date'];

    $image_url =
        trim($_POST['image_url']);

    $status =
        $_POST['status'];

    if($package_name == ''){

        $message =
            "Package name is required.";

    }

    elseif(
        $available_seats < 0
        ||
        $available_seats > $total_seats
    ){

        $message =
            "Available seats cannot be greater than total seats.";

    }

    else{

        $update_sql = "
        UPDATE tour_packages
        SET
            destination_id = ?,
            hotel_id = ?,
            transport_id = ?,
            package_name = ?,
            description = ?,
            duration_days = ?,
            duration_nights = ?,
            price = ?,
            total_seats = ?,
            available_seats = ?,
            departure_date = ?,
            image_url = ?,
            status = ?
        WHERE package_id = ?
        ";

        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );

        mysqli_stmt_bind_param(
            $update_stmt,
            "iiissiidiisssi",
            $destination_id,
            $hotel_id,
            $transport_id,
            $package_name,
            $description,
            $duration_days,
            $duration_nights,
            $price,
            $total_seats,
            $available_seats,
            $departure_date,
            $image_url,
            $status,
            $package_id
        );

        mysqli_stmt_execute(
            $update_stmt
        );

        header(
            "Location: packages.php?updated=1"
        );

        exit;

    }

}

/* =========================================
   DELETE PACKAGE
========================================= */
if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'delete'
){

    $package_id =
        intval($_POST['package_id']);

    /* CHECK IF PACKAGE HAS BOOKINGS */

    $check_sql = "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE package_id = ?
    ";

    $check_stmt =
        mysqli_prepare(
            $conn,
            $check_sql
        );

    mysqli_stmt_bind_param(
        $check_stmt,
        "i",
        $package_id
    );

    mysqli_stmt_execute(
        $check_stmt
    );

    $check_result =
        mysqli_stmt_get_result(
            $check_stmt
        );

    $check_row =
        mysqli_fetch_assoc(
            $check_result
        );

    if($check_row['total'] > 0){

        header(
            "Location: packages.php?cannotdelete=1"
        );

        exit;

    }

    else{

        $delete_sql = "
        DELETE FROM tour_packages
        WHERE package_id = ?
        ";

        $delete_stmt =
            mysqli_prepare(
                $conn,
                $delete_sql
            );

        mysqli_stmt_bind_param(
            $delete_stmt,
            "i",
            $package_id
        );

        mysqli_stmt_execute(
            $delete_stmt
        );

        header(
            "Location: packages.php?deleted=1"
        );

        exit;

    }

}

/* =========================================
   GET PACKAGE FOR EDIT
========================================= */

$edit_package = NULL;

if(isset($_GET['edit'])){

    $edit_id =
        intval($_GET['edit']);

    $edit_sql = "
    SELECT *
    FROM tour_packages
    WHERE package_id = ?
    ";

    $edit_stmt =
        mysqli_prepare(
            $conn,
            $edit_sql
        );

    mysqli_stmt_bind_param(
        $edit_stmt,
        "i",
        $edit_id
    );

    mysqli_stmt_execute(
        $edit_stmt
    );

    $edit_result =
        mysqli_stmt_get_result(
            $edit_stmt
        );

    $edit_package =
        mysqli_fetch_assoc(
            $edit_result
        );

}

/* =========================================
   GET DESTINATIONS
========================================= */

$destination_sql = "
SELECT *
FROM destinations
WHERE active = 1
ORDER BY destination_name
";

$destination_result =
    mysqli_query(
        $conn,
        $destination_sql
    );

/* =========================================
   GET HOTELS
========================================= */

$hotel_sql = "
SELECT *
FROM hotels
ORDER BY hotel_name
";

$hotel_result =
    mysqli_query(
        $conn,
        $hotel_sql
    );

/* =========================================
   GET TRANSPORT
========================================= */

$transport_sql = "
SELECT *
FROM transport
ORDER BY provider
";

$transport_result =
    mysqli_query(
        $conn,
        $transport_sql
    );

/* =========================================
   SEARCH + PAGINATION
========================================= */

$search =
    isset($_GET['search'])
    &&
    is_string($_GET['search'])
        ? trim($_GET['search'])
        : '';

$per_page = 10;

$page =
    isset($_GET['page'])
        ? max(1, (int) $_GET['page'])
        : 1;

/* COUNT MATCHING PACKAGES */

$count_sql = "
    SELECT COUNT(*) AS total
    FROM tour_packages
    JOIN destinations
      ON tour_packages.destination_id =
         destinations.destination_id
";

if($search !== ''){

    $count_sql .= "
        WHERE tour_packages.package_name LIKE ?
           OR destinations.destination_name LIKE ?
    ";

    $search_value =
        '%' . $search . '%';

    $count_stmt =
        mysqli_prepare(
            $conn,
            $count_sql
        );

    mysqli_stmt_bind_param(
        $count_stmt,
        'ss',
        $search_value,
        $search_value
    );

    mysqli_stmt_execute(
        $count_stmt
    );

    $count_result =
        mysqli_stmt_get_result(
            $count_stmt
        );

}

else{

    $count_result =
        mysqli_query(
            $conn,
            $count_sql
        );

}

$total_packages =
    (int) mysqli_fetch_assoc(
        $count_result
    )['total'];

$total_pages =
    max(
        1,
        (int) ceil(
            $total_packages / $per_page
        )
    );

$page =
    min(
        $page,
        $total_pages
    );

$offset =
    ($page - 1) * $per_page;

/* GET PACKAGES FOR THE CURRENT PAGE */

$package_sql = "
    SELECT
        tour_packages.*,
        destinations.destination_name

    FROM tour_packages

    JOIN destinations
      ON tour_packages.destination_id =
         destinations.destination_id
";

if($search !== ''){

    $package_sql .= "
        WHERE tour_packages.package_name LIKE ?
           OR destinations.destination_name LIKE ?
    ";

}

$package_sql .= "
    ORDER BY tour_packages.package_id DESC
    LIMIT ? OFFSET ?
";

$package_stmt =
    mysqli_prepare(
        $conn,
        $package_sql
    );

if($search !== ''){

    mysqli_stmt_bind_param(
        $package_stmt,
        'ssii',
        $search_value,
        $search_value,
        $per_page,
        $offset
    );

}

else{

    mysqli_stmt_bind_param(
        $package_stmt,
        'ii',
        $per_page,
        $offset
    );

}

mysqli_stmt_execute(
    $package_stmt
);

$package_result =
    mysqli_stmt_get_result(
        $package_stmt
    );

/* =========================================
   DISPLAY SERIAL NUMBER
========================================= */

/*
   This number is ONLY for displaying a
   continuous number on the website.

   It is NOT package_id.

   The real package_id is still used for
   Edit and Delete operations, so foreign-key
   relationships are not affected.
*/

$display_number =
    $total_packages > 0
        ? $offset + 1
        : 1;

?>


<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>

Manage Tour Packages - TourBD

</title>

<link
rel="stylesheet"
href="admin.css"
>

<style>

/* Pagination for the Manage Tour Packages table */

.package-pagination {

    display: flex;

    align-items: center;

    justify-content: space-between;

    flex-wrap: wrap;

    gap: 14px;

    margin: 22px 0 35px;

    font-family: Arial, sans-serif;

}

.package-pagination-summary {

    color: #626971;

    font-size: 14px;

    margin: 0;

}

.package-pagination-links {

    display: flex;

    gap: 8px;

    align-items: center;

    flex-wrap: wrap;

}

.package-pagination-links a,
.package-pagination-links span {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 38px;

    padding: 9px 13px;

    border: 1px solid #dce5e4;

    border-radius: 8px;

    text-decoration: none;

    background: #fff;

    color: #167d76;

    font-weight: 600;

}

.package-pagination-links a:hover,
.package-pagination-links a.active,
.package-pagination-links a[aria-current="page"] {

    background: #157e78;

    color: #fff;

    border-color: #157e78;

}

.package-pagination-links .disabled {

    color: #9ca3af;

    background: #f5f5f5;

    cursor: default;

}

</style>

</head>

<body>

<!-- =========================================
     TOP NAVBAR
========================================= -->

<header class="top-navbar">

    <div class="top-logo">

        <div class="logo-icon">

            ✈

        </div>

        <span>

            TourBD

        </span>

    </div>

    <nav class="top-links">

        <a href="../index.php">

            Home

        </a>

        <a href="../packages.php">

            Packages

        </a>

        <a href="../my_bookings.php">

            My Bookings

        </a>

        <a
        href="dashboard.php"
        class="active"
        >

            Admin

        </a>

    </nav>

    <div class="top-user">

        <strong>

            <?php

            echo e(
                $_SESSION['name']
            );

            ?>

        </strong>

        <span class="admin-badge">

            Admin

        </span>

        <a
        href="../logout.php"
        class="logout-button"
        >

            Logout

        </a>

    </div>

</header>

<!-- =========================================
     SIDEBAR
========================================= -->

<aside class="admin-sidebar">

    <div class="sidebar-title">

        <h2>

            Admin Panel

        </h2>

        <p>

            <?php

            echo e(
                $_SESSION['name']
            );

            ?>

        </p>

    </div>

    <div class="sidebar-menu">

        <a href="dashboard.php">

            📊 Overview

        </a>

        <a href="destination.php">

            🗺️ Destinations

        </a>

        <a
        href="packages.php"
        class="active"
        >

            🎒 Packages

        </a>

        <a href="hotels.php">

            🏨 Hotels

        </a>

        <a href="transport.php">

            🚌 Transport

        </a>

        <a href="bookings.php">

            📋 Bookings

        </a>

        <a href="reports.php">

            📈 Reports

        </a>

    </div>

</aside>

<!-- =========================================
     MAIN
========================================= -->

<main class="admin-main">

<!-- PAGE TITLE -->

<section class="dashboard-title">

    <h1>

        Manage Tour Packages

    </h1>

    <p>

        TourBD Admin ·

        <?php echo $current_date; ?>

    </p>

</section>

<!-- =========================================
     SEARCH + ADD
========================================= -->

<section class="package-toolbar">

    <form
    method="GET"
    class="package-search-form"
    >

        <input

        type="text"

        name="search"

        value="<?php

        echo e($search);

        ?>"

        placeholder="Search packages..."

        >

    </form>

    <a
    href="packages.php?add=1#package-form"
    class="package-add-button"
    >

        + Add Package

    </a>

</section>

<!-- =========================================
     MESSAGES
========================================= -->

<?php if(isset($_GET['added'])){ ?>

<div class="admin-message success">

    Package added successfully.

</div>

<?php } ?>


<?php if(isset($_GET['updated'])){ ?>

<div class="admin-message success">

    Package updated successfully.

</div>

<?php } ?>


<?php if(isset($_GET['deleted'])){ ?>

<div class="admin-message success">

    Package deleted successfully.

</div>

<?php } ?>


<?php if(isset($_GET['cannotdelete'])){ ?>

<div class="admin-message error">

    This package has existing bookings
    and cannot be deleted.

</div>

<?php } ?>


<?php if($message != ''){ ?>

<div class="admin-message error">

    <?php

    echo e($message);

    ?>

</div>

<?php } ?>

<!-- =========================================
     PACKAGE TABLE
========================================= -->

<section
class="figma-package-table"
id="package-list"
>

<table>

<thead>

<tr>

    <th>

        No.

    </th>

    <th>

        Title

    </th>

    <th>

        Destination

    </th>

    <th>

        Duration

    </th>

    <th>

        Price/Person

    </th>

    <th>

        Seats

    </th>

    <th>

        Status

    </th>

    <th>

        Actions

    </th>

</tr>

</thead>

<tbody>

<?php

if(
    mysqli_num_rows(
        $package_result
    ) > 0
){

    while(
        $package =
        mysqli_fetch_assoc(
            $package_result
        )
    ){

        $package_status =
            strtolower(
                $package['status']
            );

?>

<tr>

<!-- DISPLAY NUMBER -->

<td>

    #<?php

    echo $display_number++;

    ?>

</td>

<!-- PACKAGE TITLE -->

<td class="figma-package-title">

    <?php

    echo e(
        $package[
            'package_name'
        ]
    );

    ?>

</td>

<!-- DESTINATION -->

<td>

    📍

    <?php

    echo e(
        $package[
            'destination_name'
        ]
    );

    ?>

</td>

<!-- DURATION -->

<td>

    <?php

    echo $package[
        'duration_days'
    ];

    ?>

    Days /

    <br>

    <?php

    echo $package[
        'duration_nights'
    ];

    ?>

    Nights

</td>

<!-- PRICE -->

<td class="figma-package-price">

    ৳<?php

    echo number_format(
        $package[
            'price'
        ],
        0
    );

    ?>

</td>

<!-- SEATS -->

<td>

    <?php

    echo $package[
        'available_seats'
    ];

    ?>

    /

    <?php

    echo $package[
        'total_seats'
    ];

    ?>

</td>

<!-- STATUS -->

<td>

<?php

if(
    $package[
        'available_seats'
    ] <= 0

    ||

    $package_status ==
    'sold_out'

    ||

    $package_status ==
    'sold out'
){

?>

    <span class="
    figma-package-status
    sold-out
    ">

        Sold Out

    </span>

<?php

}

elseif(
    $package_status ==
    'limited'
){

?>

    <span class="
    figma-package-status
    limited
    ">

        Limited

    </span>

<?php

}

else{

?>

    <span class="
    figma-package-status
    available
    ">

        Available

    </span>

<?php

}

?>

</td>

<!-- ACTIONS -->

<td>

<div class="figma-action-buttons">

    <a

    href="packages.php?edit=<?php

    echo $package[
        'package_id'
    ];

    ?>#package-form"

    class="figma-edit-button"

    >

        Edit

    </a>

    <form

    method="POST"

    onsubmit="
    return confirm(
    'Are you sure you want to delete this package?'
    );
    "

    >

        <input
        type="hidden"
        name="action"
        value="delete"
        >

        <input
        type="hidden"
        name="package_id"
        value="<?php

        echo $package[
            'package_id'
        ];

        ?>"
        >

        <button
        type="submit"
        class="figma-delete-button"
        >

            Delete

        </button>

    </form>

</div>

</td>

</tr>

<?php

    }

}

else{

?>

<tr>

<td
colspan="8"
class="no-data"
>

    No packages found.

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</section>

<!-- PACKAGE PAGINATION: below the table, above Add / Edit Package -->

<?php if ($total_packages > 0) { ?>

<div class="package-pagination">

    <p class="package-pagination-summary">

        Showing
        <?php echo $offset + 1; ?>–<?php

        echo min(
            $offset + $per_page,
            $total_packages
        );

        ?>

        of

        <?php echo $total_packages; ?>

        packages

    </p>

    <?php if ($total_pages > 1) { ?>

    <nav
    class="package-pagination-links"
    aria-label="Package pages"
    >

        <?php if ($page > 1) { ?>

            <a
            href="packages.php?<?php

            echo e(
                http_build_query([
                    'search' => $search,
                    'page' => $page - 1
                ])
            );

            ?>#package-list"
            >

                Previous

            </a>

        <?php } else { ?>

            <span class="disabled">

                Previous

            </span>

        <?php } ?>


        <?php

        for(
            $i = 1;
            $i <= $total_pages;
            $i++
        ){

        ?>

            <a
            href="packages.php?<?php

            echo e(
                http_build_query([
                    'search' => $search,
                    'page' => $i
                ])
            );

            ?>#package-list"

            class="<?php

            echo
                $i === $page
                    ? 'active'
                    : '';

            ?>"

            <?php

            if($i === $page){

            ?>

                aria-current="page"

            <?php

            }

            ?>
            >

                <?php echo $i; ?>

            </a>

        <?php

        }

        ?>


        <?php if ($page < $total_pages) { ?>

            <a
            href="packages.php?<?php

            echo e(
                http_build_query([
                    'search' => $search,
                    'page' => $page + 1
                ])
            );

            ?>#package-list"
            >

                Next

            </a>

        <?php } else { ?>

            <span class="disabled">

                Next

            </span>

        <?php } ?>

    </nav>

    <?php } ?>

</div>

<?php } ?>

<!-- =========================================
     ADD / EDIT PACKAGE FORM
========================================= -->

<?php

if(
    isset($_GET['add'])
    ||
    $edit_package
    ||
    $message != ''
){

?>

<section
class="package-form-card"
id="package-form"
>

<?php

if($edit_package){

?>

<h2>

    Edit Package

</h2>

<p>

    Update package information.

</p>

<?php

}

else{

?>

<h2>

    Add New Package

</h2>

<p>

    Enter the new package information.

</p>

<?php

}

?>

<form method="POST">

<?php

if($edit_package){

?>

<input
type="hidden"
name="action"
value="edit"
>

<input
type="hidden"
name="package_id"
value="<?php

echo $edit_package[
    'package_id'
];

?>"
>

<?php

}

else{

?>

<input
type="hidden"
name="action"
value="add"
>

<?php

}

?>

<!-- PACKAGE NAME -->

<div class="admin-form-group">

<label>

    Package Name

</label>

<input

type="text"

name="package_name"

value="<?php

if($edit_package){

    echo e(
        $edit_package[
            'package_name'
        ]
    );

}

?>"

required

>

</div>

<!-- DESCRIPTION -->

<div class="admin-form-group">

<label>

    Description

</label>

<textarea

name="description"

rows="4"

><?php

if($edit_package){

    echo e(
        $edit_package[
            'description'
        ]
    );

}

?></textarea>

</div>

<!-- DESTINATION HOTEL TRANSPORT -->

<div class="package-form-grid">

<!-- DESTINATION -->

<div class="admin-form-group">

<label>

    Destination

</label>

<select
name="destination_id"
required
>

<option value="">

    Select Destination

</option>

<?php

mysqli_data_seek(
    $destination_result,
    0
);

while(
    $destination =
    mysqli_fetch_assoc(
        $destination_result
    )
){

?>

<option

value="<?php

echo $destination[
    'destination_id'
];

?>"

<?php

if(
    $edit_package
    &&
    $edit_package[
        'destination_id'
    ]
    ==
    $destination[
        'destination_id'
    ]
){

    echo "selected";

}

?>

>

<?php

echo e(
    $destination[
        'destination_name'
    ]
);

?>

</option>

<?php

}

?>

</select>

</div>

<!-- HOTEL -->

<div class="admin-form-group">

<label>

    Hotel

</label>

<select
name="hotel_id"
required
>

<option value="">

    Select Hotel

</option>

<?php

mysqli_data_seek(
    $hotel_result,
    0
);

while(
    $hotel =
    mysqli_fetch_assoc(
        $hotel_result
    )
){

?>

<option

value="<?php

echo $hotel[
    'hotel_id'
];

?>"

<?php

if(
    $edit_package
    &&
    $edit_package[
        'hotel_id'
    ]
    ==
    $hotel[
        'hotel_id'
    ]
){

    echo "selected";

}

?>

>

<?php

echo e(
    $hotel[
        'hotel_name'
    ]
);

?>

</option>

<?php

}

?>

</select>

</div>

<!-- TRANSPORT -->

<div class="admin-form-group">

<label>

    Transport

</label>

<select
name="transport_id"
required
>

<option value="">

    Select Transport

</option>

<?php

mysqli_data_seek(
    $transport_result,
    0
);

while(
    $transport =
    mysqli_fetch_assoc(
        $transport_result
    )
){

?>

<option

value="<?php

echo $transport[
    'transport_id'
];

?>"

<?php

if(
    $edit_package
    &&
    $edit_package[
        'transport_id'
    ]
    ==
    $transport[
        'transport_id'
    ]
){

    echo "selected";

}

?>

>

<?php

echo e(
    $transport[
        'provider'
    ]
);

?>

-

<?php

echo e(
    $transport[
        'type'
    ]
);

?>

</option>

<?php

}

?>

</select>

</div>

</div>

<!-- DURATION + PRICE -->

<div class="package-form-grid">

<div class="admin-form-group">

<label>

    Duration Days

</label>

<input

type="number"

name="duration_days"

min="1"

value="<?php

if($edit_package){

    echo $edit_package[
        'duration_days'
    ];

}

?>"

required

>

</div>

<div class="admin-form-group">

<label>

    Duration Nights

</label>

<input

type="number"

name="duration_nights"

min="0"

value="<?php

if($edit_package){

    echo $edit_package[
        'duration_nights'
    ];

}

?>"

required

>

</div>

<div class="admin-form-group">

<label>

    Price / Person

</label>

<input

type="number"

name="price"

min="1"

step="0.01"

value="<?php

if($edit_package){

    echo $edit_package[
        'price'
    ];

}

?>"

required

>

</div>

</div>

<!-- SEATS + DATE -->

<div class="package-form-grid">

<div class="admin-form-group">

<label>

    Total Seats

</label>

<input

type="number"

name="total_seats"

min="1"

value="<?php

if($edit_package){

    echo $edit_package[
        'total_seats'
    ];

}

?>"

required

>

</div>

<div class="admin-form-group">

<label>

    Available Seats

</label>

<input

type="number"

name="available_seats"

min="0"

value="<?php

if($edit_package){

    echo $edit_package[
        'available_seats'
    ];

}

?>"

required

>

</div>

<div class="admin-form-group">

<label>

    Departure Date

</label>

<input

type="date"

name="departure_date"

value="<?php

if($edit_package){

    echo $edit_package[
        'departure_date'
    ];

}

?>"

required

>

</div>

</div>

<!-- IMAGE + STATUS -->

<div class="package-form-grid">

<div class="admin-form-group">

<label>

    Image Filename

</label>

<input

type="text"

name="image_url"

placeholder="Example: cox.jpg"

value="<?php

if($edit_package){

    echo e(
        $edit_package[
            'image_url'
        ]
    );

}

?>"

>

<small>

    Image must exist inside
    assets/images/.

</small>

</div>

<div class="admin-form-group">

<label>

    Status

</label>

<select
name="status"
required
>

<option
value="Available"

<?php

if(
    $edit_package
    &&
    strtolower(
        $edit_package[
            'status'
        ]
    )
    == 'available'
){

    echo "selected";

}

?>

>

Available

</option>

<option
value="Limited"

<?php

if(
    $edit_package
    &&
    strtolower(
        $edit_package[
            'status'
        ]
    )
    == 'limited'
){

    echo "selected";

}

?>

>

Limited

</option>

<option
value="Sold Out"

<?php

if(
    $edit_package
    &&
    (
        strtolower(
            $edit_package[
                'status'
            ]
        )
        == 'sold out'

        ||

        strtolower(
            $edit_package[
                'status'
            ]
        )
        == 'sold_out'
    )
){

    echo "selected";

}

?>

>

Sold Out

</option>

</select>

</div>

</div>

<!-- BUTTONS -->

<div class="form-buttons">

<button
type="submit"
class="save-package-button"
>

<?php

if($edit_package){

    echo "Update Package";

}

else{

    echo "Add Package";

}

?>

</button>

<a
href="packages.php"
class="cancel-edit-button"
>

    Cancel

</a>

</div>

</form>

</section>

<?php

}

?>

<!-- =========================================
     FOOTER
========================================= -->

<footer class="admin-footer">

<div class="admin-footer-logo">

    ✈ TourBD

</div>

<p>

    © 2026 TourBD — Tour Package & Travel Booking Management System.
    All rights reserved.

</p>

<p>

    Cox's Bazar · Sajek · Sylhet · Bandarban

</p>

</footer>

</main>

</body>

</html>