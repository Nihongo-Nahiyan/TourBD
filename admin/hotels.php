
<?php

/* =========================================
   SESSION AND DATABASE
========================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";

/* ADMIN SECURITY */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

date_default_timezone_set("Asia/Dhaka");

$current_date = date("l, F j, Y");
$message = "";

/* =========================================
   ADD, EDIT AND DELETE HOTEL
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $hotel_id = (int)($_POST['hotel_id'] ?? 0);
        $destination_id = (int)($_POST['destination_id'] ?? 0);
        $hotel_name = trim($_POST['hotel_name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $stars = (int)($_POST['stars'] ?? 0);
        $price_per_night = (float)($_POST['price_per_night'] ?? 0);
        $amenities = trim($_POST['amenities'] ?? '');

        /* VALIDATION */

        if ($hotel_name === '') {
            $message = "Hotel name is required.";
        }
        elseif ($destination_id <= 0) {
            $message = "Please select a destination.";
        }
        elseif ($stars < 1 || $stars > 5) {
            $message = "Stars must be between 1 and 5.";
        }
        elseif ($price_per_night < 0) {
            $message = "Price cannot be negative.";
        }
        elseif ($action === 'edit' && $hotel_id <= 0) {
            $message = "Invalid hotel ID.";
        }
        else {

            /* CHECK DUPLICATE HOTEL */

            $check_sql = "
                SELECT hotel_id
                FROM hotels
                WHERE hotel_name = ?
                  AND destination_id = ?
                  AND hotel_id <> ?
                LIMIT 1
            ";

            $check_stmt = mysqli_prepare($conn, $check_sql);

            mysqli_stmt_bind_param(
                $check_stmt,
                "sii",
                $hotel_name,
                $destination_id,
                $hotel_id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result = mysqli_stmt_get_result($check_stmt);
            $duplicate = mysqli_fetch_assoc($check_result);

            mysqli_stmt_close($check_stmt);

            if ($duplicate) {

                $message =
                    "This hotel already exists for this destination.";

            }
            elseif ($action === 'add') {

                /* =================================
                   INSERT HOTEL
                ================================= */

                $insert_sql = "
                    INSERT INTO hotels
                    (
                        destination_id,
                        hotel_name,
                        contact,
                        stars,
                        price_per_night,
                        amenities
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ";

                $stmt = mysqli_prepare($conn, $insert_sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "issids",
                    $destination_id,
                    $hotel_name,
                    $contact,
                    $stars,
                    $price_per_night,
                    $amenities
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    header("Location: hotels.php?added=1");
                    exit;
                }

                $message = "Could not add hotel.";
                mysqli_stmt_close($stmt);

            }
            elseif ($action === 'edit') {

                /* =================================
                   UPDATE HOTEL
                ================================= */

                $update_sql = "
                    UPDATE hotels
                    SET
                        destination_id = ?,
                        hotel_name = ?,
                        contact = ?,
                        stars = ?,
                        price_per_night = ?,
                        amenities = ?
                    WHERE hotel_id = ?
                ";

                $stmt = mysqli_prepare($conn, $update_sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "issidsi",
                    $destination_id,
                    $hotel_name,
                    $contact,
                    $stars,
                    $price_per_night,
                    $amenities,
                    $hotel_id
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    /* =============================
                       RETURN TO UPDATED HOTEL
                    ============================= */

                    $return_search = trim(
                        $_POST['return_search'] ?? ''
                    );

                    $return_search_by =
                        $_POST['return_search_by'] ?? 'hotel_name';

                    if (
                        !in_array(
                            $return_search_by,
                            ['hotel_name', 'destination'],
                            true
                        )
                    ) {
                        $return_search_by = 'hotel_name';
                    }

                    /*
                       Retrieve the updated hotel's
                       destination name so we can
                       check whether the original
                       search still matches.
                    */

                    $destination_stmt = mysqli_prepare(
                        $conn,
                        "
                        SELECT destination_name
                        FROM destinations
                        WHERE destination_id = ?
                        LIMIT 1
                        "
                    );

                    mysqli_stmt_bind_param(
                        $destination_stmt,
                        "i",
                        $destination_id
                    );

                    mysqli_stmt_execute($destination_stmt);

                    $destination_result =
                        mysqli_stmt_get_result($destination_stmt);

                    $destination_row =
                        mysqli_fetch_assoc($destination_result);

                    $updated_destination_name =
                        $destination_row['destination_name'] ?? '';

                    mysqli_stmt_close($destination_stmt);

                    /*
                       If a hotel name or destination
                       was changed so the hotel no
                       longer matches the search,
                       clear the search to ensure
                       it remains visible.
                    */

                    $edited_value =
                        $return_search_by === 'destination'
                        ? $updated_destination_name
                        : $hotel_name;

                    if (
                        $return_search !== ''
                        &&
                        stripos(
                            $edited_value,
                            $return_search
                        ) === false
                    ) {
                        $return_search = '';
                    }

                    /* =============================
                       FIND UPDATED HOTEL'S PAGE
                    ============================= */

                    /*
                       The hotel table is ordered by
                       hotel_id ASC.

                       Count the hotels before and
                       including the updated hotel,
                       then calculate its page
                       using 10 records per page.
                    */

                    if ($return_search !== '') {

                        if ($return_search_by === 'destination') {

                            $position_sql = "
                                SELECT COUNT(*) AS position
                                FROM hotels h
                                INNER JOIN destinations d
                                    ON h.destination_id =
                                       d.destination_id
                                WHERE d.destination_name LIKE ?
                                  AND h.hotel_id <= ?
                            ";

                        }
                        else {

                            $position_sql = "
                                SELECT COUNT(*) AS position
                                FROM hotels h
                                WHERE h.hotel_name LIKE ?
                                  AND h.hotel_id <= ?
                            ";

                        }

                        $position_stmt = mysqli_prepare(
                            $conn,
                            $position_sql
                        );

                        $return_like =
                            "%" . $return_search . "%";

                        mysqli_stmt_bind_param(
                            $position_stmt,
                            "si",
                            $return_like,
                            $hotel_id
                        );

                    }
                    else {

                        $position_sql = "
                            SELECT COUNT(*) AS position
                            FROM hotels
                            WHERE hotel_id <= ?
                        ";

                        $position_stmt = mysqli_prepare(
                            $conn,
                            $position_sql
                        );

                        mysqli_stmt_bind_param(
                            $position_stmt,
                            "i",
                            $hotel_id
                        );

                    }

                    mysqli_stmt_execute($position_stmt);

                    $position_result =
                        mysqli_stmt_get_result($position_stmt);

                    $position_row =
                        mysqli_fetch_assoc($position_result);

                    mysqli_stmt_close($position_stmt);

                    $position = (int)(
                        $position_row['position'] ?? 0
                    );

                    $return_page = max(
                        1,
                        (int)ceil($position / 10)
                    );

                    /* =============================
                       REDIRECT AND HIGHLIGHT
                    ============================= */

                    $redirect_params = [
                        'updated' => 1,
                        'page' => $return_page,
                        'search' => $return_search,
                        'search_by' => $return_search_by,
                        'highlight' => $hotel_id
                    ];

                    $redirect_url =
                        "hotels.php?"
                        . http_build_query($redirect_params);

                    $redirect_url .=
                        "#hotel-" . $hotel_id;

                    header("Location: " . $redirect_url);
                    exit;

                }

                $message = "Could not update hotel.";
                mysqli_stmt_close($stmt);
            }
        }
    }

    /* =====================================
       DELETE HOTEL
    ===================================== */

    if ($action === 'delete') {

        $hotel_id = (int)($_POST['hotel_id'] ?? 0);

        if ($hotel_id <= 0) {

            $message = "Invalid hotel.";

        }
        else {

            /* CHECK IF USED BY A PACKAGE */

            $check_sql = "
                SELECT COUNT(*) AS total
                FROM tour_packages
                WHERE hotel_id = ?
            ";

            $stmt = mysqli_prepare($conn, $check_sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $hotel_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if ((int)$row['total'] > 0) {

                header("Location: hotels.php?cannotdelete=1");
                exit;

            }
            else {

                $delete_sql = "
                    DELETE FROM hotels
                    WHERE hotel_id = ?
                ";

                $stmt = mysqli_prepare($conn, $delete_sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $hotel_id
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    header("Location: hotels.php?deleted=1");
                    exit;
                }

                $message = "Could not delete hotel.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}

/* =========================================
   GET HOTEL FOR EDIT
========================================= */

$edit_hotel = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    if ($edit_id > 0) {

        $stmt = mysqli_prepare(
            $conn,
            "
            SELECT *
            FROM hotels
            WHERE hotel_id = ?
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $edit_id
        );

        mysqli_stmt_execute($stmt);

        $edit_result = mysqli_stmt_get_result($stmt);
        $edit_hotel = mysqli_fetch_assoc($edit_result);

        mysqli_stmt_close($stmt);
    }
}

/* =========================================
   GET DESTINATIONS
========================================= */

$destination_result = mysqli_query(
    $conn,
    "
    SELECT destination_id, destination_name
    FROM destinations
    WHERE active = 1
    ORDER BY destination_name
    "
);

/* =========================================
   HOTEL SEARCH
========================================= */

$search = trim($_GET['search'] ?? '');

$search_by =
    $_GET['search_by'] ?? 'hotel_name';

$allowed_fields = [
    'hotel_name',
    'destination'
];

if (
    !in_array(
        $search_by,
        $allowed_fields,
        true
    )
) {
    $search_by = 'hotel_name';
}

$where = "";

if ($search !== '') {

    $where =
        $search_by === 'destination'
        ? "WHERE d.destination_name LIKE ?"
        : "WHERE h.hotel_name LIKE ?";

    $search_value =
        "%" . $search . "%";
}

/* =========================================
   PAGINATION: 10 HOTELS PER PAGE
========================================= */

$records_per_page = 10;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

/* =========================================
   COUNT MATCHING HOTELS
========================================= */

$count_sql = "
    SELECT COUNT(*) AS total
    FROM hotels h
    INNER JOIN destinations d
        ON h.destination_id = d.destination_id
    $where
";

$count_stmt = mysqli_prepare(
    $conn,
    $count_sql
);

if ($search !== '') {

    mysqli_stmt_bind_param(
        $count_stmt,
        "s",
        $search_value
    );
}

mysqli_stmt_execute($count_stmt);

$count_result =
    mysqli_stmt_get_result($count_stmt);

$count_row =
    mysqli_fetch_assoc($count_result);

$total_hotels =
    (int)$count_row['total'];

mysqli_stmt_close($count_stmt);

/* =========================================
   CALCULATE TOTAL PAGES
========================================= */

$total_pages = max(
    1,
    (int)ceil(
        $total_hotels / $records_per_page
    )
);

$page = min(
    $page,
    $total_pages
);

$offset =
    ($page - 1) * $records_per_page;

/* =========================================
   GET ONLY 10 HOTELS
========================================= */

$hotel_sql = "
    SELECT
        h.*,
        d.destination_name
    FROM hotels h
    INNER JOIN destinations d
        ON h.destination_id = d.destination_id
    $where
    ORDER BY h.hotel_id ASC
    LIMIT ? OFFSET ?
";

$hotel_stmt = mysqli_prepare(
    $conn,
    $hotel_sql
);

if ($search !== '') {

    mysqli_stmt_bind_param(
        $hotel_stmt,
        "sii",
        $search_value,
        $records_per_page,
        $offset
    );

}
else {

    mysqli_stmt_bind_param(
        $hotel_stmt,
        "ii",
        $records_per_page,
        $offset
    );
}

mysqli_stmt_execute($hotel_stmt);

$hotel_result =
    mysqli_stmt_get_result($hotel_stmt);

/* =========================================
   PRESERVE SEARCH DURING PAGINATION
========================================= */

$pagination_params = [
    'search' => $search,
    'search_by' => $search_by
];

function hotelPageUrl($page, $params) {

    $params['page'] = $page;

    return "hotels.php?"
        . http_build_query($params);
}

/* =========================================
   RESULT COUNTER
========================================= */

$showing_from =
    $total_hotels > 0
    ? $offset + 1
    : 0;

$showing_to = min(
    $offset + $records_per_page,
    $total_hotels
);

/* =========================================
   UPDATED HOTEL HIGHLIGHT
========================================= */

$highlight_id = max(
    0,
    (int)($_GET['highlight'] ?? 0)
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

    <title>Hotels - TourBD Admin</title>

    <link rel="stylesheet" href="admin.css">

    <style>

        /* SEARCH BAR */

        .admin-search-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .admin-search-bar select,
        .admin-search-bar input {
            height: 42px;
            padding: 0 12px;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            background: white;
            font: inherit;
        }

        .admin-search-bar input {
            min-width: 260px;
        }

        .admin-search-bar button {
            height: 42px;
            padding: 0 18px;
            background: #087f78;
            color: white;
            border: none;
            border-radius: 8px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .admin-search-bar button:hover {
            background: #066e68;
        }

        .clear-search {
            display: inline-flex;
            align-items: center;
            height: 42px;
            padding: 0 15px;
            border: 1px solid #087f78;
            border-radius: 8px;
            color: #087f78;
            text-decoration: none;
        }

        /* RESULT COUNTER */

        .hotel-result-count {
            margin-bottom: 14px;
            color: #78716c;
            font-size: 13px;
        }

        /* PAGINATION */

        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin: 24px 0;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            background: white;
            color: #292524;
            text-decoration: none;
        }

        .pagination a:hover {
            border-color: #087f78;
            color: #087f78;
        }

        .pagination .active {
            background: #087f78;
            border-color: #087f78;
            color: white;
            font-weight: 700;
        }

        .pagination .disabled {
            opacity: .45;
            pointer-events: none;
        }

        /* HIGHLIGHT UPDATED HOTEL */

        .hotel-admin-table tr.hotel-highlight,
        .hotel-admin-table tr.hotel-highlight td {
            background: #e4f7f1;
        }

        .hotel-admin-table tr.hotel-highlight td {
            border-top: 1px solid #8acbb9;
            border-bottom: 1px solid #8acbb9;
        }

        .hotel-admin-table tbody tr {
            scroll-margin-top: 120px;
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

        <span>TourBD</span>

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
            echo e($_SESSION['name'] ?? 'Admin');
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
     ADMIN SIDEBAR
========================================= -->

<aside class="admin-sidebar">

    <div class="sidebar-title">

        <h2>Admin Panel</h2>

        <p>
            <?php
            echo e($_SESSION['name'] ?? 'Admin');
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

        <a href="packages.php">
            🎒 Packages
        </a>

        <a
            href="hotels.php"
            class="active"
        >
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
     MAIN CONTENT
========================================= -->

<main class="admin-main">

    <!-- PAGE HEADING -->

    <section class="hotel-page-heading">

        <div>

            <h1>Manage Hotels</h1>

            <p>
                TourBD Admin ·
                <?php echo e($current_date); ?>
            </p>

        </div>

    </section>

    <!-- ADD HOTEL BUTTON -->

    <div class="hotel-add-row">

        <a
            href="#hotel-form"
            class="hotel-add-button"
        >
            + Add Hotel
        </a>

    </div>

    <!-- =====================================
         MESSAGES
    ====================================== -->

    <?php if (isset($_GET['added'])): ?>

        <div class="admin-message success">
            Hotel added successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>

        <div class="admin-message success">
            Hotel updated successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>

        <div class="admin-message success">
            Hotel deleted successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['cannotdelete'])): ?>

        <div class="admin-message error">

            This hotel is currently used by a tour
            package, so it cannot be deleted.

        </div>

    <?php endif; ?>

    <?php if ($message !== ''): ?>

        <div class="admin-message error">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <!-- =====================================
         HOTEL SEARCH BAR
    ====================================== -->

    <form
        method="GET"
        action="hotels.php"
        class="admin-search-bar"
    >

        <select name="search_by">

            <option
                value="hotel_name"
                <?php
                echo $search_by === 'hotel_name'
                    ? 'selected'
                    : '';
                ?>
            >
                Hotel Name
            </option>

            <option
                value="destination"
                <?php
                echo $search_by === 'destination'
                    ? 'selected'
                    : '';
                ?>
            >
                Destination
            </option>

        </select>

        <input
            type="text"
            name="search"
            value="<?php echo e($search); ?>"
            placeholder="Search hotels..."
        >

        <button type="submit">
            Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="hotels.php"
                class="clear-search"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

    <!-- =====================================
         HOTEL TABLE
    ====================================== -->

    <section class="hotel-table-card">

        <p class="hotel-result-count">

            Showing

            <?php echo $showing_from; ?>

            –

            <?php echo $showing_to; ?>

            of

            <?php echo $total_hotels; ?>

            hotels

        </p>

        <div class="table-wrapper">

            <table class="hotel-admin-table">

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Hotel Name</th>
                        <th>Destination</th>
                        <th>Stars</th>
                        <th>Price/Night</th>
                        <th>Amenities</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($hotel_result) > 0
                    ): ?>

                        <?php while (
                            $hotel = mysqli_fetch_assoc(
                                $hotel_result
                            )
                        ): ?>

                            <?php

                            $current_hotel_id =
                                (int)$hotel['hotel_id'];

                            $is_highlighted =
                                $highlight_id ===
                                $current_hotel_id;

                            ?>

                            <!-- HOTEL ROW -->

                            <tr
                                id="hotel-<?php
                                echo $current_hotel_id;
                                ?>"
                                class="<?php
                                echo $is_highlighted
                                    ? 'hotel-highlight'
                                    : '';
                                ?>"
                            >

                                <!-- HOTEL ID -->

                                <td class="hotel-id">

                                    <?php
                                    echo $current_hotel_id;
                                    ?>

                                </td>

                                <!-- HOTEL NAME -->

                                <td class="hotel-name">

                                    <?php
                                    echo e(
                                        $hotel['hotel_name']
                                    );
                                    ?>

                                </td>

                                <!-- DESTINATION -->

                                <td>

                                    <?php
                                    echo e(
                                        $hotel['destination_name']
                                    );
                                    ?>

                                </td>

                                <!-- STARS -->

                                <td class="hotel-stars">

                                    <?php
                                    echo str_repeat(
                                        "★",
                                        (int)$hotel['stars']
                                    );
                                    ?>

                                </td>

                                <!-- PRICE -->

                                <td class="hotel-price">

                                    ৳<?php
                                    echo number_format(
                                        (float)$hotel['price_per_night'],
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- AMENITIES -->

                                <td class="hotel-amenities">

                                    <?php
                                    echo e(
                                        $hotel['amenities']
                                    );
                                    ?>

                                </td>

                                <!-- ACTIONS -->

                                <td>

                                    <div class="hotel-action-buttons">

                                        <!-- EDIT -->

                                        <?php

                                        $edit_params =
                                            $pagination_params;

                                        $edit_params['page'] =
                                            $page;

                                        $edit_params['edit'] =
                                            $current_hotel_id;

                                        ?>

                                        <a
                                            href="hotels.php?<?php
                                            echo e(
                                                http_build_query(
                                                    $edit_params
                                                )
                                            );
                                            ?>#hotel-form"
                                            class="hotel-edit-button"
                                        >
                                            Edit
                                        </a>

                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this hotel?'
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
                                                name="hotel_id"
                                                value="<?php
                                                echo $current_hotel_id;
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="hotel-delete-button"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="7"
                                class="no-data"
                            >
                                No hotels found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- =====================================
             PAGINATION
        ====================================== -->

        <?php if ($total_pages > 1): ?>

            <div class="pagination">

                <!-- PREVIOUS -->

                <a
                    href="<?php
                    echo e(
                        hotelPageUrl(
                            max(1, $page - 1),
                            $pagination_params
                        )
                    );
                    ?>"
                    class="<?php
                    echo $page <= 1
                        ? 'disabled'
                        : '';
                    ?>"
                >
                    Previous
                </a>

                <!-- PAGE NUMBERS -->

                <?php for (
                    $i = 1;
                    $i <= $total_pages;
                    $i++
                ): ?>

                    <?php if ($i === $page): ?>

                        <span class="active">
                            <?php echo $i; ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="<?php
                            echo e(
                                hotelPageUrl(
                                    $i,
                                    $pagination_params
                                )
                            );
                            ?>"
                        >
                            <?php echo $i; ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

                <!-- NEXT -->

                <a
                    href="<?php
                    echo e(
                        hotelPageUrl(
                            min(
                                $total_pages,
                                $page + 1
                            ),
                            $pagination_params
                        )
                    );
                    ?>"
                    class="<?php
                    echo $page >= $total_pages
                        ? 'disabled'
                        : '';
                    ?>"
                >
                    Next
                </a>

            </div>

        <?php endif; ?>

    </section>

    <!-- =====================================
         ADD / EDIT HOTEL FORM
    ====================================== -->

    <section
        class="hotel-form-card"
        id="hotel-form"
    >

        <h2>

            <?php
            echo $edit_hotel
                ? "Edit Hotel"
                : "Add New Hotel";
            ?>

        </h2>

        <p>

            <?php
            echo $edit_hotel
                ? "Update hotel information."
                : "Add another hotel to a TourBD destination.";
            ?>

        </p>

        <form method="POST">

            <!-- ACTION -->

            <input
                type="hidden"
                name="action"
                value="<?php
                echo $edit_hotel ? 'edit' : 'add';
                ?>"
            >

            <!-- =================================
                 REMEMBER PAGE AND SEARCH
            ================================== -->

            <input
                type="hidden"
                name="return_page"
                value="<?php echo $page; ?>"
            >

            <input
                type="hidden"
                name="return_search"
                value="<?php echo e($search); ?>"
            >

            <input
                type="hidden"
                name="return_search_by"
                value="<?php echo e($search_by); ?>"
            >

            <!-- HOTEL ID WHEN EDITING -->

            <?php if ($edit_hotel): ?>

                <input
                    type="hidden"
                    name="hotel_id"
                    value="<?php
                    echo (int)$edit_hotel['hotel_id'];
                    ?>"
                >

            <?php endif; ?>

            <!-- FIRST ROW -->

            <div class="hotel-form-grid">

                <!-- HOTEL NAME -->

                <div class="admin-form-group">

                    <label>Hotel Name</label>

                    <input
                        type="text"
                        name="hotel_name"
                        value="<?php
                        echo e(
                            $edit_hotel['hotel_name'] ?? ''
                        );
                        ?>"
                        placeholder="Example: Sea Pearl Hotel"
                        required
                    >

                </div>

                <!-- DESTINATION -->

                <div class="admin-form-group">

                    <label>Destination</label>

                    <select
                        name="destination_id"
                        required
                    >

                        <option value="">
                            Select Destination
                        </option>

                        <?php while (
                            $destination = mysqli_fetch_assoc(
                                $destination_result
                            )
                        ): ?>

                            <option
                                value="<?php
                                echo (int)$destination['destination_id'];
                                ?>"
                                <?php
                                echo (
                                    $edit_hotel
                                    &&
                                    (int)$edit_hotel['destination_id']
                                    ===
                                    (int)$destination['destination_id']
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php
                                echo e(
                                    $destination['destination_name']
                                );
                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <!-- CONTACT -->

                <div class="admin-form-group">

                    <label>Contact</label>

                    <input
                        type="text"
                        name="contact"
                        value="<?php
                        echo e(
                            $edit_hotel['contact'] ?? ''
                        );
                        ?>"
                        placeholder="Example: 01800000000"
                    >

                </div>

            </div>

            <!-- SECOND ROW -->

            <div class="hotel-form-grid">

                <!-- STARS -->

                <div class="admin-form-group">

                    <label>Stars</label>

                    <select
                        name="stars"
                        required
                    >

                        <?php for (
                            $star = 1;
                            $star <= 5;
                            $star++
                        ): ?>

                            <option
                                value="<?php echo $star; ?>"
                                <?php
                                echo (
                                    (int)(
                                        $edit_hotel['stars'] ?? 3
                                    ) === $star
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo $star; ?> Star
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>

                <!-- PRICE -->

                <div class="admin-form-group">

                    <label>Price Per Night</label>

                    <input
                        type="number"
                        name="price_per_night"
                        min="0"
                        step="0.01"
                        value="<?php
                        echo e(
                            $edit_hotel['price_per_night'] ?? ''
                        );
                        ?>"
                        placeholder="Example: 6500"
                        required
                    >

                </div>

            </div>

            <!-- AMENITIES -->

            <div class="admin-form-group">

                <label>Amenities</label>

                <input
                    type="text"
                    name="amenities"
                    value="<?php
                    echo e(
                        $edit_hotel['amenities'] ?? ''
                    );
                    ?>"
                    placeholder="Example: Pool, Spa, Sea View, WiFi"
                >

                <small>
                    Separate amenities using commas.
                </small>

            </div>

            <!-- FORM BUTTONS -->

            <div class="form-buttons">

                <button
                    type="submit"
                    class="save-hotel-button"
                >

                    <?php
                    echo $edit_hotel
                        ? "Update Hotel"
                        : "Add Hotel";
                    ?>

                </button>

                <?php if ($edit_hotel): ?>

                    <a
                        href="<?php
                        echo e(
                            hotelPageUrl(
                                $page,
                                $pagination_params
                            )
                        );
                        ?>"
                        class="cancel-edit-button"
                    >
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- FOOTER -->

    <footer class="admin-footer">

        <div class="admin-footer-logo">
            ✈ TourBD
        </div>

        <p>
            © 2026 TourBD — Tour Package & Travel Booking
            Management System. All rights reserved.
        </p>

        <p>
            Cox's Bazar · Sajek · Sylhet · Bandarban
        </p>

    </footer>

</main>

</body>
</html>
