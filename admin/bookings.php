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

$current_date =
    date("l, F j, Y");



/* =========================================
   CONFIRM BOOKING
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'confirm'
){


    $booking_id =
        intval(
            $_POST['booking_id']
        );


    $confirm_sql = "

    UPDATE bookings

    SET status = 'confirmed'

    WHERE booking_id = ?

    AND LOWER(status) = 'pending'

    ";


    $confirm_stmt =
        mysqli_prepare(
            $conn,
            $confirm_sql
        );


    mysqli_stmt_bind_param(
        $confirm_stmt,
        "i",
        $booking_id
    );


    mysqli_stmt_execute(
        $confirm_stmt
    );


    header(
        "Location: bookings.php?confirmed=1"
    );


    exit;


}



/* =========================================
   CANCEL BOOKING
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'cancel'
){


    $booking_id =
        intval(
            $_POST['booking_id']
        );



    /* START TRANSACTION */


    mysqli_begin_transaction($conn);



    $cancel_success = true;



    /* GET BOOKING INFORMATION */


    $booking_check_sql = "

    SELECT

        package_id,
        travelers,
        status

    FROM bookings

    WHERE booking_id = ?

    FOR UPDATE

    ";


    $booking_check_stmt =
        mysqli_prepare(
            $conn,
            $booking_check_sql
        );


    mysqli_stmt_bind_param(
        $booking_check_stmt,
        "i",
        $booking_id
    );


    mysqli_stmt_execute(
        $booking_check_stmt
    );


    $booking_check_result =
        mysqli_stmt_get_result(
            $booking_check_stmt
        );


    $booking_data =
        mysqli_fetch_assoc(
            $booking_check_result
        );



    if(!$booking_data){


        $cancel_success = false;


    }


    elseif(
        strtolower(
            $booking_data['status']
        )
        != 'cancelled'
    ){


        /* UPDATE BOOKING STATUS */


        $cancel_sql = "

        UPDATE bookings

        SET status = 'cancelled'

        WHERE booking_id = ?

        ";


        $cancel_stmt =
            mysqli_prepare(
                $conn,
                $cancel_sql
            );


        mysqli_stmt_bind_param(
            $cancel_stmt,
            "i",
            $booking_id
        );


        if(
            !mysqli_stmt_execute(
                $cancel_stmt
            )
        ){


            $cancel_success = false;


        }



        /* RESTORE AVAILABLE SEATS */


        if($cancel_success){


            $seat_sql = "

            UPDATE tour_packages

            SET available_seats =
                available_seats + ?

            WHERE package_id = ?

            ";


            $seat_stmt =
                mysqli_prepare(
                    $conn,
                    $seat_sql
                );


            mysqli_stmt_bind_param(
                $seat_stmt,
                "ii",
                $booking_data['travelers'],
                $booking_data['package_id']
            );


            if(
                !mysqli_stmt_execute(
                    $seat_stmt
                )
            ){


                $cancel_success = false;


            }


        }



        /* REFUND PAID PAYMENT */


        if($cancel_success){


            $payment_sql = "

            UPDATE payments

            SET payment_status = 'refunded'

            WHERE booking_id = ?

            AND LOWER(payment_status) = 'paid'

            ";


            $payment_stmt =
                mysqli_prepare(
                    $conn,
                    $payment_sql
                );


            mysqli_stmt_bind_param(
                $payment_stmt,
                "i",
                $booking_id
            );


            if(
                !mysqli_stmt_execute(
                    $payment_stmt
                )
            ){


                $cancel_success = false;


            }


        }


    }



    /* SAVE OR UNDO */


    if($cancel_success){


        mysqli_commit($conn);


        header(
            "Location: bookings.php?cancelled=1"
        );


        exit;


    }

    else{


        mysqli_rollback($conn);


        header(
            "Location: bookings.php?error=1"
        );


        exit;


    }


}



/* =========================================
   SUMMARY COUNTS
========================================= */


/* TOTAL BOOKINGS */


$total_sql = "

SELECT COUNT(*) AS total

FROM bookings

";


$total_result =
    mysqli_query(
        $conn,
        $total_sql
    );


$total_row =
    mysqli_fetch_assoc(
        $total_result
    );


$total_bookings =
    $total_row['total'];



/* CONFIRMED */


$confirmed_sql = "

SELECT COUNT(*) AS total

FROM bookings

WHERE LOWER(status) = 'confirmed'

";


$confirmed_result =
    mysqli_query(
        $conn,
        $confirmed_sql
    );


$confirmed_row =
    mysqli_fetch_assoc(
        $confirmed_result
    );


$total_confirmed =
    $confirmed_row['total'];



/* PENDING */


$pending_sql = "

SELECT COUNT(*) AS total

FROM bookings

WHERE LOWER(status) = 'pending'

";


$pending_result =
    mysqli_query(
        $conn,
        $pending_sql
    );


$pending_row =
    mysqli_fetch_assoc(
        $pending_result
    );


$total_pending =
    $pending_row['total'];



/* CANCELLED */


$cancelled_sql = "

SELECT COUNT(*) AS total

FROM bookings

WHERE LOWER(status) = 'cancelled'

";


$cancelled_result =
    mysqli_query(
        $conn,
        $cancelled_sql
    );


$cancelled_row =
    mysqli_fetch_assoc(
        $cancelled_result
    );


$total_cancelled =
    $cancelled_row['total'];



/* PAID REVENUE */


$revenue_sql = "

SELECT

    COALESCE(
        SUM(amount),
        0
    ) AS total

FROM payments

WHERE LOWER(payment_status) = 'paid'

";


$revenue_result =
    mysqli_query(
        $conn,
        $revenue_sql
    );


$revenue_row =
    mysqli_fetch_assoc(
        $revenue_result
    );


$total_revenue =
    $revenue_row['total'];



/* =========================================
   GET FILTER VALUES
========================================= */


$search = "";

$status_filter = "";

$payment_filter = "";

$destination_filter = 0;



if(isset($_GET['search'])){


    $search =
        trim(
            $_GET['search']
        );


}



if(isset($_GET['status'])){


    $status_filter =
        trim(
            $_GET['status']
        );


}



if(isset($_GET['payment'])){


    $payment_filter =
        trim(
            $_GET['payment']
        );


}



if(isset($_GET['destination'])){


    $destination_filter =
        intval(
            $_GET['destination']
        );


}



/* =========================================
   BUILD FILTER
========================================= */


$where = "

WHERE 1 = 1

";



/* SEARCH */


if($search != ''){


    $safe_search =
        mysqli_real_escape_string(
            $conn,
            $search
        );


    $where .= "

    AND
    (
        users.name LIKE '%$safe_search%'

        OR users.email LIKE '%$safe_search%'

        OR tour_packages.package_name
        LIKE '%$safe_search%'

        OR bookings.booking_id
        LIKE '%$safe_search%'
    )

    ";


}



/* STATUS FILTER */


$allowed_statuses =
    array(
        "confirmed",
        "pending",
        "cancelled"
    );


if(
    in_array(
        strtolower($status_filter),
        $allowed_statuses
    )
){


    $safe_status =
        mysqli_real_escape_string(
            $conn,
            strtolower($status_filter)
        );


    $where .= "

    AND LOWER(bookings.status)
    = '$safe_status'

    ";


}



/* PAYMENT FILTER */


$allowed_payments =
    array(
        "paid",
        "pending",
        "refunded"
    );


if(
    in_array(
        strtolower($payment_filter),
        $allowed_payments
    )
){


    $safe_payment =
        mysqli_real_escape_string(
            $conn,
            strtolower($payment_filter)
        );


    if($safe_payment == 'pending'){


        $where .= "

        AND
        (
            LOWER(payments.payment_status)
            = 'pending'

            OR payments.payment_status
            IS NULL
        )

        ";


    }

    else{


        $where .= "

        AND LOWER(
            payments.payment_status
        ) = '$safe_payment'

        ";


    }


}



/* DESTINATION FILTER */


if($destination_filter > 0){


    $where .= "

    AND destinations.destination_id
    = $destination_filter

    ";


}



/* =========================================
   GET BOOKINGS
========================================= */


$booking_list_sql = "

SELECT

    bookings.booking_id,

    bookings.travel_date,

    bookings.travelers,

    bookings.total_amount,

    bookings.status,

    users.name,

    users.email,

    tour_packages.package_name,

    destinations.destination_name,

    payments.method,

    COALESCE(
        payments.payment_status,
        'pending'
    ) AS payment_status


FROM bookings


JOIN users

ON bookings.user_id =
   users.user_id


JOIN tour_packages

ON bookings.package_id =
   tour_packages.package_id


JOIN destinations

ON tour_packages.destination_id =
   destinations.destination_id


LEFT JOIN payments

ON bookings.booking_id =
   payments.booking_id


$where


ORDER BY bookings.booking_id DESC

";


$booking_list_result =
    mysqli_query(
        $conn,
        $booking_list_sql
    );



/* =========================================
   FILTERED REVENUE
========================================= */


$filtered_revenue_sql = "

SELECT

    COALESCE(

        SUM(

            CASE

                WHEN
                LOWER(payments.payment_status)
                = 'paid'

                THEN payments.amount

                ELSE 0

            END

        ),

        0

    ) AS total


FROM bookings


JOIN users

ON bookings.user_id =
   users.user_id


JOIN tour_packages

ON bookings.package_id =
   tour_packages.package_id


JOIN destinations

ON tour_packages.destination_id =
   destinations.destination_id


LEFT JOIN payments

ON bookings.booking_id =
   payments.booking_id


$where

";


$filtered_revenue_result =
    mysqli_query(
        $conn,
        $filtered_revenue_sql
    );


$filtered_revenue_row =
    mysqli_fetch_assoc(
        $filtered_revenue_result
    );


$filtered_revenue =
    $filtered_revenue_row['total'];



/* =========================================
   DESTINATION DROPDOWN
========================================= */


$destination_sql = "

SELECT

    destination_id,

    destination_name

FROM destinations

ORDER BY destination_name

";


$destination_result =
    mysqli_query(
        $conn,
        $destination_sql
    );


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Bookings - TourBD Admin

</title>


<link
rel="stylesheet"
href="admin.css"
>


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


    <h1>

        Manage Bookings

    </h1>


    <p>

        TourBD Admin ·

        <?php
        echo $current_date;
        ?>

    </p>


</section>



<!-- =========================================
     SUMMARY CARDS
========================================= -->


<section class="booking-summary-grid">



<!-- TOTAL -->


<div class="
booking-summary-card
booking-total-card
">


<h2>

<?php
echo $total_bookings;
?>

</h2>


<p>

Total Bookings

</p>


</div>



<!-- CONFIRMED -->


<div class="
booking-summary-card
booking-confirmed-card
">


<h2>

<?php
echo $total_confirmed;
?>

</h2>


<p>

Confirmed

</p>


</div>



<!-- PENDING -->


<div class="
booking-summary-card
booking-pending-card
">


<h2>

<?php
echo $total_pending;
?>

</h2>


<p>

Pending

</p>


</div>



<!-- CANCELLED -->


<div class="
booking-summary-card
booking-cancelled-card
">


<h2>

<?php
echo $total_cancelled;
?>

</h2>


<p>

Cancelled

</p>


</div>



<!-- REVENUE -->


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


<p>

Revenue (Paid)

</p>


</div>


</section>



<!-- =========================================
     MESSAGES
========================================= -->


<?php if(isset($_GET['confirmed'])){ ?>


<div class="admin-message success">

Booking confirmed successfully.

</div>


<?php } ?>



<?php if(isset($_GET['cancelled'])){ ?>


<div class="admin-message success">

Booking cancelled successfully.

</div>


<?php } ?>



<?php if(isset($_GET['error'])){ ?>


<div class="admin-message error">

Booking could not be updated.

</div>


<?php } ?>



<!-- =========================================
     FILTER FORM
========================================= -->


<form

method="GET"

class="booking-filter-form"

>


<input

type="text"

name="search"

value="<?php
echo e($search);
?>"

placeholder="Search by name, ID, package..."

>



<select name="status">


<option value="">

All Statuses

</option>


<option
value="confirmed"
<?php

if(
    strtolower($status_filter)
    == 'confirmed'
){

    echo "selected";

}

?>
>

Confirmed

</option>


<option
value="pending"
<?php

if(
    strtolower($status_filter)
    == 'pending'
){

    echo "selected";

}

?>
>

Pending

</option>


<option
value="cancelled"
<?php

if(
    strtolower($status_filter)
    == 'cancelled'
){

    echo "selected";

}

?>
>

Cancelled

</option>


</select>



<select name="payment">


<option value="">

All Payments

</option>


<option
value="paid"
<?php

if(
    strtolower($payment_filter)
    == 'paid'
){

    echo "selected";

}

?>
>

Paid

</option>


<option
value="pending"
<?php

if(
    strtolower($payment_filter)
    == 'pending'
){

    echo "selected";

}

?>
>

Pending

</option>


<option
value="refunded"
<?php

if(
    strtolower($payment_filter)
    == 'refunded'
){

    echo "selected";

}

?>
>

Refunded

</option>


</select>



<select name="destination">


<option value="">

All Destinations

</option>


<?php


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
    $destination_filter
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



<!-- =========================================
     BOOKING TABLE
========================================= -->


<section class="booking-table-card">


<div class="table-wrapper">


<table class="booking-admin-table">


<thead>


<tr>


<th>
#
</th>


<th>
Package
</th>


<th>
Traveler
</th>


<th>
Destination
</th>


<th>
Date
</th>


<th>
Travelers
</th>


<th>
Amount
</th>


<th>
Method
</th>


<th>
Status
</th>


<th>
Payment
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
        $booking_list_result
    ) > 0
){


    while(
        $booking =
        mysqli_fetch_assoc(
            $booking_list_result
        )
    ){


?>


<tr>



<!-- BOOKING ID -->


<td class="booking-number">


#<?php
echo $booking[
    'booking_id'
];
?>


</td>



<!-- PACKAGE -->


<td class="booking-package-name">


<?php
echo e(
    $booking[
        'package_name'
    ]
);
?>


</td>



<!-- TRAVELER -->


<td>


<div class="booking-traveler-name">


<?php
echo e(
    $booking[
        'name'
    ]
);
?>


</div>


<div class="booking-email">


<?php
echo e(
    $booking[
        'email'
    ]
);
?>


</div>


</td>



<!-- DESTINATION -->


<td>


📍

<?php
echo e(
    $booking[
        'destination_name'
    ]
);
?>


</td>



<!-- DATE -->


<td>


<?php
echo $booking[
    'travel_date'
];
?>


</td>



<!-- TRAVELERS -->


<td class="center-booking-value">


<?php
echo $booking[
    'travelers'
];
?>


</td>



<!-- AMOUNT -->


<td class="booking-amount">


৳<?php

echo number_format(
    $booking[
        'total_amount'
    ],
    0
);

?>


</td>



<!-- PAYMENT METHOD -->


<td>


<?php


if(
    $booking['method'] != ''
){


    echo e(
        $booking[
            'method'
        ]
    );


}

else{


    echo "-";


}


?>


</td>



<!-- BOOKING STATUS -->


<td>


<span
class="
booking-status
<?php
echo strtolower(
    $booking[
        'status'
    ]
);
?>
"
>


<?php

echo ucfirst(
    $booking[
        'status'
    ]
);

?>


</span>


</td>



<!-- PAYMENT STATUS -->


<td>


<span
class="
payment-status
<?php
echo strtolower(
    $booking[
        'payment_status'
    ]
);
?>
"
>


<?php

echo ucfirst(
    $booking[
        'payment_status'
    ]
);

?>


</span>


</td>



<!-- ACTIONS -->


<td>


<div class="booking-action-buttons">


<?php


if(
    strtolower(
        $booking['status']
    )
    == 'pending'
){


?>


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
echo $booking[
    'booking_id'
];
?>"
>


<button
type="submit"
class="confirm-booking-button"
>

Confirm

</button>


</form>


<?php


}


?>



<?php


if(
    strtolower(
        $booking['status']
    )
    != 'cancelled'
){


?>


<form

method="POST"

onsubmit="
return confirm(
'Are you sure you want to cancel this booking?'
);
"

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
echo $booking[
    'booking_id'
];
?>"
>


<button
type="submit"
class="cancel-booking-button"
>

Cancel

</button>


</form>


<?php


}


?>


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
colspan="11"
class="no-data"
>

No bookings found.

</td>


</tr>


<?php


}


?>


</tbody>


</table>


</div>


</section>



<!-- =========================================
     FOOTER
========================================= -->


<footer class="admin-footer">


<div class="admin-footer-logo">

✈ TourBD

</div>


<p>

© 2026 TourBD —
Tour Package & Travel Booking Management System.
All rights reserved.

</p>


<p>

Cox's Bazar · Sajek · Sylhet · Bandarban

</p>


</footer>



</main>


</body>


</html>