<?php

/* =========================================
   SESSION AND DATABASE
========================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";


/* =========================================
   ADMIN SECURITY
========================================= */

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
   ADD, EDIT AND DELETE TRANSPORT
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================
       ADD OR EDIT TRANSPORT
    ===================================== */

    if ($action === 'add' || $action === 'edit') {

        $transport_id = (int)(
            $_POST['transport_id'] ?? 0
        );

        $type = trim(
            $_POST['type'] ?? ''
        );

        $route = trim(
            $_POST['route'] ?? ''
        );

        $provider = trim(
            $_POST['provider'] ?? ''
        );

        $price_per_person = (float)(
            $_POST['price_per_person'] ?? 0
        );

        $departure = trim(
            $_POST['departure'] ?? ''
        );


        /* VALIDATION */

        if ($type === '') {

            $message = "Transport type is required.";

        }
        elseif ($route === '') {

            $message = "Route is required.";

        }
        elseif ($provider === '') {

            $message = "Transport operator is required.";

        }
        elseif ($price_per_person < 0) {

            $message = "Price cannot be negative.";

        }
        elseif (
            $action === 'edit'
            && $transport_id <= 0
        ) {

            $message = "Invalid transport ID.";

        }
        elseif ($action === 'add') {

            /* INSERT TRANSPORT */

            $insert_sql = "
                INSERT INTO transport
                (
                    type,
                    route,
                    provider,
                    price_per_person,
                    departure
                )
                VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = mysqli_prepare(
                $conn,
                $insert_sql
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssds",
                $type,
                $route,
                $provider,
                $price_per_person,
                $departure
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                header(
                    "Location: transport.php?added=1"
                );

                exit;

            }

            $message = "Could not add transport.";

            mysqli_stmt_close($stmt);

        }
        elseif ($action === 'edit') {

            /* =================================
               UPDATE TRANSPORT
            ================================= */

            $update_sql = "
                UPDATE transport
                SET
                    type = ?,
                    route = ?,
                    provider = ?,
                    price_per_person = ?,
                    departure = ?
                WHERE transport_id = ?
            ";

            $stmt = mysqli_prepare(
                $conn,
                $update_sql
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sssdsi",
                $type,
                $route,
                $provider,
                $price_per_person,
                $departure,
                $transport_id
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                /* =============================
                   RETURN TO EDITED TRANSPORT
                ============================= */

                // Remember the search that was
                // active before editing.

                $return_search = trim(
                    $_POST['return_search'] ?? ''
                );

                $return_search_by =
                    $_POST['return_search_by'] ?? 'type';

                $allowed_return_fields = [
                    'type',
                    'route',
                    'operator'
                ];

                if (
                    !in_array(
                        $return_search_by,
                        $allowed_return_fields,
                        true
                    )
                ) {
                    $return_search_by = 'type';
                }

                // Map the visible search options
                // to the database column names.

                $return_columns = [
                    'type' => 'type',
                    'route' => 'route',
                    'operator' => 'provider'
                ];

                $return_column =
                    $return_columns[$return_search_by];

                // If the edited transport no
                // longer matches the old search,
                // clear the search so the updated
                // record will still be visible.

                $edited_value = [
                    'type' => $type,
                    'route' => $route,
                    'operator' => $provider
                ][$return_search_by];

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
                   FIND THE UPDATED ROW'S PAGE
                ============================= */

                /*
                   The table is ordered by
                   transport_id ASC.

                   Count the records appearing
                   up to and including the
                   updated transport.

                   Dividing this position by 10
                   gives the correct page.
                */

                if ($return_search !== '') {

                    $position_sql = "
                        SELECT COUNT(*) AS position
                        FROM transport
                        WHERE $return_column LIKE ?
                          AND transport_id <= ?
                    ";

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
                        $transport_id
                    );

                }
                else {

                    $position_sql = "
                        SELECT COUNT(*) AS position
                        FROM transport
                        WHERE transport_id <= ?
                    ";

                    $position_stmt = mysqli_prepare(
                        $conn,
                        $position_sql
                    );

                    mysqli_stmt_bind_param(
                        $position_stmt,
                        "i",
                        $transport_id
                    );

                }

                mysqli_stmt_execute(
                    $position_stmt
                );

                $position_result =
                    mysqli_stmt_get_result(
                        $position_stmt
                    );

                $position_row =
                    mysqli_fetch_assoc(
                        $position_result
                    );

                mysqli_stmt_close(
                    $position_stmt
                );

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
                    'highlight' => $transport_id
                ];

                $redirect_url =
                    "transport.php?"
                    . http_build_query(
                        $redirect_params
                    );

                // Scroll directly to the row.

                $redirect_url .=
                    "#transport-" . $transport_id;

                header(
                    "Location: " . $redirect_url
                );

                exit;

            }

            $message = "Could not update transport.";

            mysqli_stmt_close($stmt);

        }
    }


    /* =====================================
       DELETE TRANSPORT
    ===================================== */

    if ($action === 'delete') {

        $transport_id = (int)(
            $_POST['transport_id'] ?? 0
        );

        if ($transport_id <= 0) {

            $message = "Invalid transport.";

        }
        else {

            /* CHECK IF USED BY A PACKAGE */

            $check_sql = "
                SELECT COUNT(*) AS total
                FROM tour_packages
                WHERE transport_id = ?
            ";

            $stmt = mysqli_prepare(
                $conn,
                $check_sql
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $transport_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result(
                $stmt
            );

            $row = mysqli_fetch_assoc(
                $result
            );

            mysqli_stmt_close($stmt);

            if ((int)$row['total'] > 0) {

                header(
                    "Location: transport.php?cannotdelete=1"
                );

                exit;

            }
            else {

                $delete_sql = "
                    DELETE FROM transport
                    WHERE transport_id = ?
                ";

                $stmt = mysqli_prepare(
                    $conn,
                    $delete_sql
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $transport_id
                );

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    header(
                        "Location: transport.php?deleted=1"
                    );

                    exit;

                }

                $message = "Could not delete transport.";

                mysqli_stmt_close($stmt);

            }
        }
    }
}


/* =========================================
   GET TRANSPORT FOR EDIT
========================================= */

$edit_transport = null;

if (isset($_GET['edit'])) {

    $edit_id = (int)$_GET['edit'];

    if ($edit_id > 0) {

        $edit_sql = "
            SELECT *
            FROM transport
            WHERE transport_id = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare(
            $conn,
            $edit_sql
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $edit_id
        );

        mysqli_stmt_execute($stmt);

        $edit_result = mysqli_stmt_get_result(
            $stmt
        );

        $edit_transport = mysqli_fetch_assoc(
            $edit_result
        );

        mysqli_stmt_close($stmt);

    }
}


/* =========================================
   TRANSPORT SEARCH
========================================= */

$search = trim(
    $_GET['search'] ?? ''
);

$search_by =
    $_GET['search_by'] ?? 'type';

$allowed_fields = [
    'type',
    'route',
    'operator'
];

if (
    !in_array(
        $search_by,
        $allowed_fields,
        true
    )
) {

    $search_by = 'type';

}

/*
   The operator column in the database
   is named provider.
*/

$search_columns = [
    'type' => 'type',
    'route' => 'route',
    'operator' => 'provider'
];

$search_column =
    $search_columns[$search_by];

$where = "";

if ($search !== '') {

    $where = "
        WHERE $search_column LIKE ?
    ";

    $search_value =
        "%" . $search . "%";

}


/* =========================================
   PAGINATION
   10 TRANSPORT RECORDS PER PAGE
========================================= */

$records_per_page = 10;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);


/* =========================================
   COUNT MATCHING TRANSPORT
========================================= */

$count_sql = "
    SELECT COUNT(*) AS total
    FROM transport
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

mysqli_stmt_execute(
    $count_stmt
);

$count_result =
    mysqli_stmt_get_result(
        $count_stmt
    );

$count_row =
    mysqli_fetch_assoc(
        $count_result
    );

$total_transport =
    (int)$count_row['total'];

mysqli_stmt_close(
    $count_stmt
);


/* =========================================
   CALCULATE TOTAL PAGES
========================================= */

$total_pages = max(
    1,
    (int)ceil(
        $total_transport
        / $records_per_page
    )
);

$page = min(
    $page,
    $total_pages
);

$offset =
    ($page - 1)
    * $records_per_page;


/* =========================================
   GET ONLY 10 TRANSPORT RECORDS
========================================= */

$transport_sql = "
    SELECT *
    FROM transport
    $where
    ORDER BY transport_id ASC
    LIMIT ? OFFSET ?
";

$transport_stmt = mysqli_prepare(
    $conn,
    $transport_sql
);

if ($search !== '') {

    mysqli_stmt_bind_param(
        $transport_stmt,
        "sii",
        $search_value,
        $records_per_page,
        $offset
    );

}
else {

    mysqli_stmt_bind_param(
        $transport_stmt,
        "ii",
        $records_per_page,
        $offset
    );

}

mysqli_stmt_execute(
    $transport_stmt
);

$transport_result =
    mysqli_stmt_get_result(
        $transport_stmt
    );


/* =========================================
   PRESERVE SEARCH DURING PAGINATION
========================================= */

$pagination_params = [
    'search' => $search,
    'search_by' => $search_by
];

function transportPageUrl(
    $page,
    $params
) {

    $params['page'] = $page;

    return "transport.php?"
        . http_build_query($params);

}


/* =========================================
   RESULT COUNTER
========================================= */

$showing_from =
    $total_transport > 0
    ? $offset + 1
    : 0;

$showing_to = min(
    $offset + $records_per_page,
    $total_transport
);


/* =========================================
   DISPLAY NUMBER
========================================= */

/*
   This number is ONLY for display.

   It is NOT the real transport_id.

   The actual transport_id is still used
   for Edit, Delete and database relationships.
*/

$display_number =
    $total_transport > 0
    ? $offset + 1
    : 1;


/* HIGHLIGHT THE UPDATED TRANSPORT */

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

    <title>
        Transport - TourBD Admin
    </title>

    <link
        rel="stylesheet"
        href="admin.css"
    >

    <style>

        /* =================================
           SEARCH BAR
        ================================= */

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

        /* =================================
           RESULT COUNTER
        ================================= */

        .transport-result-count {
            margin-bottom: 14px;
            color: #78716c;
            font-size: 13px;
        }

        /* =================================
           PAGINATION
        ================================= */

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
            background: white;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
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

        /* =================================
           HIGHLIGHT UPDATED TRANSPORT
        ================================= */

        .transport-admin-table tr.transport-highlight,
        .transport-admin-table tr.transport-highlight td {
            background: #e4f7f1;
        }

        .transport-admin-table tr.transport-highlight td {
            border-top: 1px solid #8acbb9;
            border-bottom: 1px solid #8acbb9;
        }

        .transport-admin-table tbody tr {
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
                $_SESSION['name'] ?? 'Admin'
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
     ADMIN SIDEBAR
========================================= -->

<aside class="admin-sidebar">

    <div class="sidebar-title">

        <h2>

            Admin Panel

        </h2>

        <p>

            <?php

            echo e(
                $_SESSION['name'] ?? 'Admin'
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

        <a href="packages.php">

            🎒 Packages

        </a>

        <a href="hotels.php">

            🏨 Hotels

        </a>

        <a
            href="transport.php"
            class="active"
        >

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

    <!-- =====================================
         PAGE HEADING
    ====================================== -->

    <section class="transport-page-heading">

        <div>

            <h1>

                Manage Transport

            </h1>

            <p>

                TourBD Admin ·

                <?php echo e($current_date); ?>

            </p>

        </div>

    </section>


    <!-- ADD TRANSPORT BUTTON -->

    <div class="transport-add-row">

        <a
            href="#transport-form"
            class="transport-add-button"
        >

            + Add Transport

        </a>

    </div>


    <!-- =====================================
         MESSAGES
    ====================================== -->

    <?php if (isset($_GET['added'])): ?>

        <div class="admin-message success">

            Transport added successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['updated'])): ?>

        <div class="admin-message success">

            Transport updated successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['deleted'])): ?>

        <div class="admin-message success">

            Transport deleted successfully.

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['cannotdelete'])): ?>

        <div class="admin-message error">

            This transport is currently used by
            a tour package and cannot be deleted.

        </div>

    <?php endif; ?>


    <?php if ($message !== ''): ?>

        <div class="admin-message error">

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         SEARCH BAR
    ====================================== -->

    <form
        method="GET"
        action="transport.php"
        class="admin-search-bar"
    >

        <!-- SEARCH BY -->

        <select name="search_by">

            <option
                value="type"
                <?php

                echo $search_by === 'type'
                    ? 'selected'
                    : '';

                ?>
            >

                Type

            </option>

            <option
                value="route"
                <?php

                echo $search_by === 'route'
                    ? 'selected'
                    : '';

                ?>
            >

                Route

            </option>

            <option
                value="operator"
                <?php

                echo $search_by === 'operator'
                    ? 'selected'
                    : '';

                ?>
            >

                Operator

            </option>

        </select>


        <!-- SEARCH INPUT -->

        <input
            type="text"
            name="search"
            value="<?php

            echo e($search);

            ?>"
            placeholder="Search transport..."
        >


        <!-- SEARCH BUTTON -->

        <button type="submit">

            Search

        </button>


        <!-- CLEAR SEARCH -->

        <?php if ($search !== ''): ?>

            <a
                href="transport.php"
                class="clear-search"
            >

                Clear

            </a>

        <?php endif; ?>

    </form>


    <!-- =====================================
         TRANSPORT TABLE
    ====================================== -->

    <section class="transport-table-card">

        <!-- RESULT COUNTER -->

        <p class="transport-result-count">

            Showing

            <?php echo $showing_from; ?>

            –

            <?php echo $showing_to; ?>

            of

            <?php echo $total_transport; ?>

            transport records

        </p>


        <div class="table-wrapper">

            <table class="transport-admin-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Type</th>

                        <th>Route</th>

                        <th>Operator</th>

                        <th>Price/Person</th>

                        <th>Departure</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        mysqli_num_rows(
                            $transport_result
                        ) > 0
                    ): ?>


                        <?php while (
                            $transport =
                                mysqli_fetch_assoc(
                                    $transport_result
                                )
                        ): ?>

                            <?php

                            $current_transport_id =
                                (int)$transport[
                                    'transport_id'
                                ];

                            $is_highlighted =
                                $highlight_id ===
                                $current_transport_id;

                            ?>


                            <!-- =====================
                                 TRANSPORT ROW
                            ====================== -->

                            <tr
                                id="transport-<?php

                                echo $current_transport_id;

                                ?>"
                                class="<?php

                                echo $is_highlighted
                                    ? 'transport-highlight'
                                    : '';

                                ?>"
                            >


                                <!-- DISPLAY NUMBER -->

                                <td class="transport-id">

                                    <?php

                                    echo $display_number++;

                                    ?>

                                </td>


                                <!-- TYPE -->

                                <td>

                                    <span class="transport-type">

                                        <?php

                                        echo e(
                                            $transport['type']
                                        );

                                        ?>

                                    </span>

                                </td>


                                <!-- ROUTE -->

                                <td class="transport-route">

                                    <?php

                                    echo e(
                                        $transport['route']
                                    );

                                    ?>

                                </td>


                                <!-- OPERATOR -->

                                <td>

                                    <?php

                                    echo e(
                                        $transport['provider']
                                    );

                                    ?>

                                </td>


                                <!-- PRICE -->

                                <td class="transport-price">

                                    ৳<?php

                                    echo number_format(
                                        (float)$transport[
                                            'price_per_person'
                                        ],
                                        0
                                    );

                                    ?>

                                </td>


                                <!-- DEPARTURE -->

                                <td class="transport-departure">

                                    <?php

                                    echo e(
                                        $transport['departure']
                                    );

                                    ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="transport-action-buttons">


                                        <!-- EDIT -->

                                        <?php

                                        $edit_params =
                                            $pagination_params;

                                        $edit_params['page'] =
                                            $page;

                                        $edit_params['edit'] =
                                            $current_transport_id;

                                        ?>

                                        <a
                                            href="transport.php?<?php

                                            echo e(
                                                http_build_query(
                                                    $edit_params
                                                )
                                            );

                                            ?>#transport-form"
                                            class="transport-edit-button"
                                        >

                                            Edit

                                        </a>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this transport?'
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
                                                name="transport_id"
                                                value="<?php

                                                echo $current_transport_id;

                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="transport-delete-button"
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

                                No transport records found.

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
                        transportPageUrl(
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
                                transportPageUrl(
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
                        transportPageUrl(
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
         ADD / EDIT TRANSPORT FORM
    ====================================== -->

    <section
        class="transport-form-card"
        id="transport-form"
    >

        <h2>

            <?php

            echo $edit_transport
                ? "Edit Transport"
                : "Add New Transport";

            ?>

        </h2>

        <p>

            <?php

            echo $edit_transport
                ? "Update the selected transport information."
                : "Add a new transport option for TourBD packages.";

            ?>

        </p>


        <form method="POST">


            <!-- ACTION -->

            <input
                type="hidden"
                name="action"
                value="<?php

                echo $edit_transport
                    ? 'edit'
                    : 'add';

                ?>"
            >


            <!-- =================================
                 REMEMBER PAGE AND SEARCH
            ================================== -->

            <input
                type="hidden"
                name="return_page"
                value="<?php

                echo $page;

                ?>"
            >

            <input
                type="hidden"
                name="return_search"
                value="<?php

                echo e($search);

                ?>"
            >

            <input
                type="hidden"
                name="return_search_by"
                value="<?php

                echo e($search_by);

                ?>"
            >


            <!-- TRANSPORT ID WHEN EDITING -->

            <?php if ($edit_transport): ?>

                <input
                    type="hidden"
                    name="transport_id"
                    value="<?php

                    echo (int)$edit_transport[
                        'transport_id'
                    ];

                    ?>"
                >

            <?php endif; ?>


            <!-- =================================
                 FIRST ROW
            ================================== -->

            <div class="transport-form-grid">


                <!-- TYPE -->

                <div class="admin-form-group">

                    <label>

                        Transport Type

                    </label>

                    <select
                        name="type"
                        required
                    >

                        <?php

                        $selected_type =
                            $edit_transport['type'] ?? '';

                        $transport_types = [
                            'Bus',
                            'Train',
                            'Flight',
                            'Car',
                            'Microbus',
                            'Boat'
                        ];

                        foreach (
                            $transport_types as $type_option
                        ):

                        ?>

                            <option
                                value="<?php

                                echo e($type_option);

                                ?>"
                                <?php

                                echo $selected_type ===
                                    $type_option
                                    ? 'selected'
                                    : '';

                                ?>
                            >

                                <?php

                                echo e($type_option);

                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ROUTE -->

                <div class="admin-form-group">

                    <label>

                        Route

                    </label>

                    <input
                        type="text"
                        name="route"
                        value="<?php

                        echo e(
                            $edit_transport['route'] ?? ''
                        );

                        ?>"
                        placeholder="Example: Dhaka to Cox's Bazar"
                        required
                    >

                </div>


                <!-- OPERATOR -->

                <div class="admin-form-group">

                    <label>

                        Operator

                    </label>

                    <input
                        type="text"
                        name="provider"
                        value="<?php

                        echo e(
                            $edit_transport['provider'] ?? ''
                        );

                        ?>"
                        placeholder="Example: Green Line"
                        required
                    >

                </div>

            </div>


            <!-- =================================
                 SECOND ROW
            ================================== -->

            <div class="transport-form-grid-two">


                <!-- PRICE -->

                <div class="admin-form-group">

                    <label>

                        Price Per Person

                    </label>

                    <input
                        type="number"
                        name="price_per_person"
                        min="0"
                        step="0.01"
                        value="<?php

                        echo e(
                            $edit_transport[
                                'price_per_person'
                            ] ?? ''
                        );

                        ?>"
                        placeholder="Example: 1500"
                        required
                    >

                </div>


                <!-- DEPARTURE -->

                <div class="admin-form-group">

                    <label>

                        Departure

                    </label>

                    <input
                        type="text"
                        name="departure"
                        value="<?php

                        echo e(
                            $edit_transport['departure'] ?? ''
                        );

                        ?>"
                        placeholder="Example: 10:00 PM"
                    >

                </div>

            </div>


            <!-- =================================
                 FORM BUTTONS
            ================================== -->

            <div class="form-buttons">

                <button
                    type="submit"
                    class="save-transport-button"
                >

                    <?php

                    echo $edit_transport
                        ? "Update Transport"
                        : "Add Transport";

                    ?>

                </button>


                <?php if ($edit_transport): ?>

                    <a
                        href="<?php

                        echo e(
                            transportPageUrl(
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


    <!-- =====================================
         FOOTER
    ====================================== -->

    <footer class="admin-footer">

        <div class="admin-footer-logo">

            ✈ TourBD

        </div>

        <p>

            © 2026 TourBD — Tour Package & Travel
            Booking Management System.
            All rights reserved.

        </p>

        <p>

            Cox's Bazar · Sajek · Sylhet · Bandarban

        </p>

    </footer>

</main>

</body>

</html>