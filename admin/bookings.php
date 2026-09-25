
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
   CONFIRM AND CANCEL BOOKING
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        $message = "Invalid booking ID.";
    }

    /* =====================================
       CONFIRM BOOKING
    ===================================== */

    elseif ($action === 'confirm') {

        /*
           Only confirm a booking if payment
           is already recorded as paid.
        */

        $confirm_sql = "
            UPDATE bookings b
            SET b.status = 'confirmed'
            WHERE b.booking_id = ?
              AND LOWER(b.status) = 'pending'
              AND EXISTS (
                  SELECT 1
                  FROM payments p
                  WHERE p.booking_id = b.booking_id
                    AND LOWER(p.payment_status) = 'paid'
              )
        ";

        $stmt = mysqli_prepare($conn, $confirm_sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $booking_id
        );

        if (mysqli_stmt_execute($stmt)) {

            $changed = mysqli_stmt_affected_rows($stmt);

            mysqli_stmt_close($stmt);

            if ($changed === 1) {
                header("Location: bookings.php?confirmed=1");
            } else {
                header("Location: bookings.php?error=1");
            }

            exit;

        }

        $message = "Could not confirm booking.";
        mysqli_stmt_close($stmt);
    }

    /* =====================================
       CANCEL BOOKING
    ===================================== */

    elseif ($action === 'cancel') {

        mysqli_begin_transaction($conn);

        try {

            /* GET BOOKING AND LOCK ROW */

            $check_sql = "
                SELECT
                    package_id,
                    travelers,
                    status
                FROM bookings
                WHERE booking_id = ?
                FOR UPDATE
            ";

            $stmt = mysqli_prepare($conn, $check_sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $booking_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $booking = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$booking) {
                throw new Exception("Booking not found.");
            }

            if (strtolower($booking['status']) === 'cancelled') {
                throw new Exception("Booking is already cancelled.");
            }

            /* UPDATE BOOKING STATUS */

            $cancel_sql = "
                UPDATE bookings
                SET status = 'cancelled'
                WHERE booking_id = ?
                  AND LOWER(status) <> 'cancelled'
            ";

            $stmt = mysqli_prepare($conn, $cancel_sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $booking_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);

            /* RESTORE PACKAGE SEATS */

            $seat_sql = "
                UPDATE tour_packages
                SET available_seats = available_seats + ?
                WHERE package_id = ?
            ";

            $stmt = mysqli_prepare($conn, $seat_sql);

            $travelers = (int)$booking['travelers'];
            $package_id = (int)$booking['package_id'];

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $travelers,
                $package_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);

            /*
               A paid booking requires an actual
               refund process. Do not mark payment
               as refunded merely because the
               booking was cancelled.
            */

            mysqli_commit($conn);

            header("Location: bookings.php?cancelled=1");
            exit;

        }
        catch (Exception $e) {

            mysqli_rollback($conn);

            $message = $e->getMessage();
        }
    }
}

/* =========================================
   DASHBOARD SUMMARY
========================================= */

/* TOTAL BOOKINGS */

$total_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM bookings"
);

$total_bookings = (int)mysqli_fetch_assoc(
    $total_result
)['total'];

/* CONFIRMED */

$confirmed_result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE LOWER(status) = 'confirmed'
    "
);

$total_confirmed = (int)mysqli_fetch_assoc(
    $confirmed_result
)['total'];

/* PENDING */

$pending_result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE LOWER(status) = 'pending'
    "
);

$total_pending = (int)mysqli_fetch_assoc(
    $pending_result
)['total'];

/* CANCELLED */

$cancelled_result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE LOWER(status) = 'cancelled'
    "
);

$total_cancelled = (int)mysqli_fetch_assoc(
    $cancelled_result
)['total'];

/* PAID REVENUE */

$revenue_result = mysqli_query(
    $conn,
    "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE LOWER(payment_status) = 'paid'
    "
);

$total_revenue = (float)mysqli_fetch_assoc(
    $revenue_result
)['total'];

/* =========================================
   GET FILTER VALUES
========================================= */

$search = trim($_GET['search'] ?? '');

$status_filter = strtolower(
    trim($_GET['status'] ?? '')
);

$payment_filter = strtolower(
    trim($_GET['payment'] ?? '')
);

$destination_filter = (int)(
    $_GET['destination'] ?? 0
);

/* VALIDATE FILTERS */

$allowed_statuses = [
    'confirmed',
    'pending',
    'cancelled'
];

$allowed_payments = [
    'paid',
    'pending',
    'refunded'
];

if (
    !in_array(
        $status_filter,
        $allowed_statuses,
        true
    )
) {
    $status_filter = '';
}

if (
    !in_array(
        $payment_filter,
        $allowed_payments,
        true
    )
) {
    $payment_filter = '';
}

/* =========================================
   BUILD FILTER QUERY
========================================= */

$where = "WHERE 1 = 1";

$params = [];
$types = "";

/* SEARCH BY NAME, EMAIL, PACKAGE OR ID */

if ($search !== '') {

    $where .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR tp.package_name LIKE ?
            OR CAST(b.booking_id AS CHAR) LIKE ?
        )
    ";

    $like = "%" . $search . "%";

    for ($i = 0; $i < 4; $i++) {
        $params[] = $like;
    }

    $types .= "ssss";
}

/* BOOKING STATUS */

if ($status_filter !== '') {

    $where .= "
        AND LOWER(b.status) = ?
    ";

    $params[] = $status_filter;
    $types .= "s";
}

/* PAYMENT STATUS */

if ($payment_filter !== '') {

    if ($payment_filter === 'pending') {

        $where .= "
            AND (
                LOWER(p.payment_status) = 'pending'
                OR p.payment_status IS NULL
            )
        ";

    } else {

        $where .= "
            AND LOWER(p.payment_status) = ?
        ";

        $params[] = $payment_filter;
        $types .= "s";
    }
}

/* DESTINATION */

if ($destination_filter > 0) {

    $where .= "
        AND d.destination_id = ?
    ";

    $params[] = $destination_filter;
    $types .= "i";
}

/* =========================================
   SHARED TABLE JOINS
========================================= */

$joins = "
    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.user_id

    INNER JOIN tour_packages tp
        ON b.package_id = tp.package_id

    INNER JOIN destinations d
        ON tp.destination_id = d.destination_id

    LEFT JOIN (
        SELECT p1.*
        FROM payments p1
        INNER JOIN (
            SELECT
                booking_id,
                MAX(payment_id) AS latest_payment_id
            FROM payments
            GROUP BY booking_id
        ) latest
            ON p1.payment_id = latest.latest_payment_id
    ) p
        ON b.booking_id = p.booking_id
";

/* =========================================
   PAGINATION: 10 BOOKINGS PER PAGE
========================================= */

$records_per_page = 10;

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

/* COUNT FILTERED BOOKINGS */

$count_sql = "
    SELECT COUNT(*) AS total
    $joins
    $where
";

$count_stmt = mysqli_prepare(
    $conn,
    $count_sql
);

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $count_stmt,
        $types,
        ...$params
    );
}

mysqli_stmt_execute($count_stmt);

$count_result = mysqli_stmt_get_result(
    $count_stmt
);

$count_row = mysqli_fetch_assoc(
    $count_result
);

$total_filtered_bookings = (int)$count_row['total'];

mysqli_stmt_close($count_stmt);

/* CALCULATE TOTAL PAGES */

$total_pages = max(
    1,
    (int)ceil(
        $total_filtered_bookings / $records_per_page
    )
);

$page = min($page, $total_pages);

$offset = (
    $page - 1
) * $records_per_page;

/* =========================================
   GET ONLY 10 BOOKINGS
========================================= */

$booking_sql = "
    SELECT
        b.booking_id,
        b.travel_date,
        b.travelers,
        b.total_amount,
        b.status,

        u.name,
        u.email,

        tp.package_name,

        d.destination_name,

        p.method,

        COALESCE(
            p.payment_status,
            'pending'
        ) AS payment_status

    $joins

    $where

    ORDER BY b.booking_id DESC

    LIMIT ? OFFSET ?
";

$booking_stmt = mysqli_prepare(
    $conn,
    $booking_sql
);

$list_params = $params;

$list_params[] = $records_per_page;
$list_params[] = $offset;

$list_types = $types . "ii";

mysqli_stmt_bind_param(
    $booking_stmt,
    $list_types,
    ...$list_params
);

mysqli_stmt_execute($booking_stmt);

$booking_result = mysqli_stmt_get_result(
    $booking_stmt
);

/* =========================================
   FILTERED REVENUE
========================================= */

$filtered_revenue_sql = "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(p.payment_status) = 'paid'
                    THEN p.amount
                    ELSE 0
                END
            ),
            0
        ) AS total

    $joins

    $where
";

$revenue_stmt = mysqli_prepare(
    $conn,
    $filtered_revenue_sql
);

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $revenue_stmt,
        $types,
        ...$params
    );
}

mysqli_stmt_execute($revenue_stmt);

$revenue_result = mysqli_stmt_get_result(
    $revenue_stmt
);

$filtered_revenue = (float)mysqli_fetch_assoc(
    $revenue_result
)['total'];

mysqli_stmt_close($revenue_stmt);

/* =========================================
   DESTINATION DROPDOWN
========================================= */

$destination_result = mysqli_query(
    $conn,
    "
    SELECT destination_id, destination_name
    FROM destinations
    ORDER BY destination_name
    "
);

/* =========================================
   PRESERVE FILTERS DURING PAGINATION
========================================= */

$pagination_params = [
    'search' => $search,
    'status' => $status_filter,
    'payment' => $payment_filter,
    'destination' => $destination_filter
];

function bookingPageUrl($page, $params) {

    $params['page'] = $page;

    return "bookings.php?" . http_build_query($params);
}

/* PAGE COUNTER */

$showing_from = $total_filtered_bookings > 0
    ? $offset + 1
    : 0;

$showing_to = min(
    $offset + $records_per_page,
    $total_filtered_bookings
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

    <title>Bookings - TourBD Admin</title>

    <link rel="stylesheet" href="admin.css">

    <style>

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
            background: white;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            color: #292524;
            text-decoration: none;
        }

        .pagination .active {
            background: #087f78;
            color: white;
            border-color: #087f78;
            font-weight: 700;
        }

        .pagination .disabled {
            opacity: .45;
            pointer-events: none;
        }

        .booking-count-info {
            margin: 16px 0;
            font-size: 13px;
            color: #78716c;
        }

        .booking-filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .booking-filter-form input,
        .booking-filter-form select {
            min-height: 40px;
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

        <a href="hotels.php">
            🏨 Hotels
        </a>

        <a href="transport.php">
            🚌 Transport
        </a>

        <a
            href="bookings.php"
            class="active"
        >
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

    <!-- PAGE TITLE -->

    <section class="booking-page-heading">

        <h1>Manage Bookings</h1>

        <p>
            TourBD Admin ·
            <?php echo e($current_date); ?>
        </p>

    </section>

    <!-- =====================================
         SUMMARY CARDS
    ===================================== -->

    <section class="booking-summary-grid">

        <div class="
            booking-summary-card
            booking-total-card
        ">

            <h2>
                <?php echo $total_bookings; ?>
            </h2>

            <p>Total Bookings</p>

        </div>

        <div class="
            booking-summary-card
            booking-confirmed-card
        ">

            <h2>
                <?php echo $total_confirmed; ?>
            </h2>

            <p>Confirmed</p>

        </div>

        <div class="
            booking-summary-card
            booking-pending-card
        ">

            <h2>
                <?php echo $total_pending; ?>
            </h2>

            <p>Pending</p>

        </div>

        <div class="
            booking-summary-card
            booking-cancelled-card
        ">

            <h2>
                <?php echo $total_cancelled; ?>
            </h2>

            <p>Cancelled</p>

        </div>

        <div class="
            booking-summary-card
            booking-revenue-card
        ">

            <h2>
                ৳<?php
                echo number_format(
                    $total_revenue,
                    0
                );
                ?>
            </h2>

            <p>Revenue (Paid)</p>

        </div>

    </section>

    <!-- =====================================
         MESSAGES
    ===================================== -->

    <?php if (isset($_GET['confirmed'])): ?>

        <div class="admin-message success">
            Booking confirmed successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['cancelled'])): ?>

        <div class="admin-message success">
            Booking cancelled successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>

        <div class="admin-message error">
            Booking could not be updated.
            Check whether its payment is paid.
        </div>

    <?php endif; ?>

    <?php if ($message !== ''): ?>

        <div class="admin-message error">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <!-- =====================================
         SEARCH AND FILTER
    ===================================== -->

    <form
        method="GET"
        action="bookings.php"
        class="booking-filter-form"
    >

        <!-- SEARCH -->

        <input
            type="text"
            name="search"
            value="<?php echo e($search); ?>"
            placeholder="Search name, ID, package..."
        >

        <!-- BOOKING STATUS -->

        <select name="status">

            <option value="">
                All Statuses
            </option>

            <option
                value="confirmed"
                <?php
                echo $status_filter === 'confirmed'
                    ? 'selected'
                    : '';
                ?>
            >
                Confirmed
            </option>

            <option
                value="pending"
                <?php
                echo $status_filter === 'pending'
                    ? 'selected'
                    : '';
                ?>
            >
                Pending
            </option>

            <option
                value="cancelled"
                <?php
                echo $status_filter === 'cancelled'
                    ? 'selected'
                    : '';
                ?>
            >
                Cancelled
            </option>

        </select>

        <!-- PAYMENT STATUS -->

        <select name="payment">

            <option value="">
                All Payments
            </option>

            <option
                value="paid"
                <?php
                echo $payment_filter === 'paid'
                    ? 'selected'
                    : '';
                ?>
            >
                Paid
            </option>

            <option
                value="pending"
                <?php
                echo $payment_filter === 'pending'
                    ? 'selected'
                    : '';
                ?>
            >
                Pending
            </option>

            <option
                value="refunded"
                <?php
                echo $payment_filter === 'refunded'
                    ? 'selected'
                    : '';
                ?>
            >
                Refunded
            </option>

        </select>

        <!-- DESTINATION -->

        <select name="destination">

            <option value="0">
                All Destinations
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
                    echo $destination_filter ===
                        (int)$destination['destination_id']
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

        <button
            type="submit"
            class="booking-filter-button"
        >
            Apply Filters
        </button>

        <a
            href="bookings.php"
            class="booking-clear-button"
        >
            Clear
        </a>

    </form>

    <!-- FILTERED REVENUE -->

    <div class="filtered-revenue">

        <strong>
            Filtered Revenue:
        </strong>

        <span>
            ৳<?php
            echo number_format(
                $filtered_revenue,
                0
            );
            ?>
        </span>

    </div>

    <!-- =====================================
         BOOKING TABLE
    ===================================== -->

    <section class="booking-table-card">

        <p class="booking-count-info">

            Showing

            <?php echo $showing_from; ?>

            –

            <?php echo $showing_to; ?>

            of

            <?php echo $total_filtered_bookings; ?>

            bookings

        </p>

        <div class="table-wrapper">

            <table class="booking-admin-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Package</th>
                        <th>Traveler</th>
                        <th>Destination</th>
                        <th>Date</th>
                        <th>Travelers</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($booking_result) > 0
                    ): ?>

                        <?php while (
                            $booking = mysqli_fetch_assoc(
                                $booking_result
                            )
                        ): ?>

                            <?php

                            $booking_status = strtolower(
                                $booking['status']
                            );

                            $payment_status = strtolower(
                                $booking['payment_status']
                            );

                            ?>

                            <tr>

                                <!-- BOOKING ID -->

                                <td class="booking-number">

                                    #<?php
                                    echo (int)$booking['booking_id'];
                                    ?>

                                </td>

                                <!-- PACKAGE -->

                                <td class="booking-package-name">

                                    <?php
                                    echo e(
                                        $booking['package_name']
                                    );
                                    ?>

                                </td>

                                <!-- TRAVELER -->

                                <td>

                                    <div class="booking-traveler-name">

                                        <?php
                                        echo e($booking['name']);
                                        ?>

                                    </div>

                                    <div class="booking-email">

                                        <?php
                                        echo e($booking['email']);
                                        ?>

                                    </div>

                                </td>

                                <!-- DESTINATION -->

                                <td>

                                    📍
                                    <?php
                                    echo e(
                                        $booking['destination_name']
                                    );
                                    ?>

                                </td>

                                <!-- TRAVEL DATE -->

                                <td>

                                    <?php
                                    echo e(
                                        $booking['travel_date']
                                    );
                                    ?>

                                </td>

                                <!-- NUMBER OF TRAVELERS -->

                                <td class="center-booking-value">

                                    <?php
                                    echo (int)$booking['travelers'];
                                    ?>

                                </td>

                                <!-- AMOUNT -->

                                <td class="booking-amount">

                                    ৳<?php
                                    echo number_format(
                                        (float)$booking['total_amount'],
                                        0
                                    );
                                    ?>

                                </td>

                                <!-- PAYMENT METHOD -->

                                <td>

                                    <?php
                                    echo e(
                                        $booking['method'] ?: '—'
                                    );
                                    ?>

                                </td>

                                <!-- BOOKING STATUS -->

                                <td>

                                    <span
                                        class="booking-status <?php
                                        echo e($booking_status);
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            ucfirst($booking_status)
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- PAYMENT STATUS -->

                                <td>

                                    <span
                                        class="payment-status <?php
                                        echo e($payment_status);
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            ucfirst($payment_status)
                                        );
                                        ?>

                                    </span>

                                </td>

                                <!-- ACTIONS -->

                                <td>

                                    <div class="booking-action-buttons">

                                        <?php if (
                                            $booking_status === 'pending'
                                            &&
                                            $payment_status === 'paid'
                                        ): ?>

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="confirm"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="booking_id"
                                                    value="<?php
                                                    echo (int)$booking['booking_id'];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="confirm-booking-button"
                                                >
                                                    Confirm
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                        <?php if (
                                            $booking_status !== 'cancelled'
                                        ): ?>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm(
                                                    'Are you sure you want to cancel this booking?'
                                                );"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="cancel"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="booking_id"
                                                    value="<?php
                                                    echo (int)$booking['booking_id'];
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="cancel-booking-button"
                                                >
                                                    Cancel
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="11"
                                class="no-data"
                            >
                                No bookings found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- =====================================
             PAGINATION
        ===================================== -->

        <?php if ($total_pages > 1): ?>

            <div class="pagination">

                <!-- PREVIOUS -->

                <a
                    href="<?php
                    echo e(
                        bookingPageUrl(
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
                                bookingPageUrl(
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
                        bookingPageUrl(
                            min($total_pages, $page + 1),
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
         FOOTER
    ===================================== -->

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
