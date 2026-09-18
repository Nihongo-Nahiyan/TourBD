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
   TOTAL REVENUE
========================================= */


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
   TOTAL BOOKINGS
========================================= */


$booking_sql = "

SELECT COUNT(*) AS total

FROM bookings

";


$booking_result =
    mysqli_query(
        $conn,
        $booking_sql
    );


$booking_row =
    mysqli_fetch_assoc(
        $booking_result
    );


$total_bookings =
    $booking_row['total'];



/* =========================================
   TOTAL TRAVELERS
========================================= */


$traveler_sql = "

SELECT

    COALESCE(
        SUM(travelers),
        0
    ) AS total

FROM bookings

";


$traveler_result =
    mysqli_query(
        $conn,
        $traveler_sql
    );


$traveler_row =
    mysqli_fetch_assoc(
        $traveler_result
    );


$total_travelers =
    $traveler_row['total'];



/* =========================================
   AVERAGE BOOKING VALUE

   Average of successful paid payments
========================================= */


$average_sql = "

SELECT

    COALESCE(
        AVG(amount),
        0
    ) AS average_amount

FROM payments

WHERE LOWER(payment_status) = 'paid'

";


$average_result =
    mysqli_query(
        $conn,
        $average_sql
    );


$average_row =
    mysqli_fetch_assoc(
        $average_result
    );


$average_booking =
    $average_row['average_amount'];



/* =========================================
   CONFIRMED BOOKINGS
========================================= */


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



/* =========================================
   CANCELLED BOOKINGS
========================================= */


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



/* =========================================
   CONFIRMED / CANCELLATION RATE
========================================= */


$confirmed_rate = 0;

$cancellation_rate = 0;


if($total_bookings > 0){


    $confirmed_rate =

        (
            $total_confirmed
            /
            $total_bookings
        )

        * 100;



    $cancellation_rate =

        (
            $total_cancelled
            /
            $total_bookings
        )

        * 100;


}



/* =========================================
   REVENUE BY DESTINATION
========================================= */


$destination_sql = "

SELECT

    destinations.destination_id,

    destinations.destination_name,


    (
        SELECT COUNT(*)

        FROM bookings

        JOIN tour_packages AS p1

        ON bookings.package_id =
           p1.package_id

        WHERE p1.destination_id =
              destinations.destination_id

    ) AS booking_count,


    (
        SELECT

            COALESCE(
                SUM(bookings.travelers),
                0
            )

        FROM bookings

        JOIN tour_packages AS p2

        ON bookings.package_id =
           p2.package_id

        WHERE p2.destination_id =
              destinations.destination_id

    ) AS traveler_count,


    (
        SELECT COUNT(*)

        FROM bookings

        JOIN tour_packages AS p3

        ON bookings.package_id =
           p3.package_id

        WHERE p3.destination_id =
              destinations.destination_id

        AND LOWER(bookings.status)
            = 'confirmed'

    ) AS confirmed_count,


    (
        SELECT

            COALESCE(
                SUM(payments.amount),
                0
            )

        FROM payments

        JOIN bookings

        ON payments.booking_id =
           bookings.booking_id

        JOIN tour_packages AS p4

        ON bookings.package_id =
           p4.package_id

        WHERE p4.destination_id =
              destinations.destination_id

        AND LOWER(
            payments.payment_status
        ) = 'paid'

    ) AS revenue


FROM destinations


ORDER BY revenue DESC

";


$destination_result =
    mysqli_query(
        $conn,
        $destination_sql
    );



/* SAVE DESTINATION DATA */


$destination_data =
    array();


$highest_destination_revenue = 0;


while(
    $destination =
    mysqli_fetch_assoc(
        $destination_result
    )
){


    $destination_data[] =
        $destination;


    if(
        $destination['revenue']
        >
        $highest_destination_revenue
    ){


        $highest_destination_revenue =
            $destination['revenue'];


    }


}



/* =========================================
   REVENUE BY PAYMENT METHOD
========================================= */


$payment_method_sql = "

SELECT

    method,

    SUM(amount) AS revenue

FROM payments

WHERE LOWER(payment_status) = 'paid'

GROUP BY method

ORDER BY revenue DESC

";


$payment_method_result =
    mysqli_query(
        $conn,
        $payment_method_sql
    );



/* SAVE PAYMENT METHOD DATA */


$payment_method_data =
    array();


$highest_method_revenue = 0;


while(
    $method =
    mysqli_fetch_assoc(
        $payment_method_result
    )
){


    $payment_method_data[] =
        $method;


    if(
        $method['revenue']
        >
        $highest_method_revenue
    ){


        $highest_method_revenue =
            $method['revenue'];


    }


}



/* =========================================
   PACKAGE PERFORMANCE
========================================= */


$package_sql = "

SELECT

    tour_packages.package_id,

    tour_packages.package_name,

    tour_packages.total_seats,

    tour_packages.available_seats,

    destinations.destination_name,


    (
        SELECT COUNT(*)

        FROM bookings

        WHERE bookings.package_id =
              tour_packages.package_id

    ) AS booking_count,


    (
        SELECT

            COALESCE(
                SUM(bookings.travelers),
                0
            )

        FROM bookings

        WHERE bookings.package_id =
              tour_packages.package_id

    ) AS traveler_count,


    (
        SELECT

            COALESCE(
                SUM(payments.amount),
                0
            )

        FROM payments

        JOIN bookings

        ON payments.booking_id =
           bookings.booking_id

        WHERE bookings.package_id =
              tour_packages.package_id

        AND LOWER(
            payments.payment_status
        ) = 'paid'

    ) AS revenue


FROM tour_packages


JOIN destinations

ON tour_packages.destination_id =
   destinations.destination_id


ORDER BY revenue DESC,
         booking_count DESC

";


$package_result =
    mysqli_query(
        $conn,
        $package_sql
    );


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Reports - TourBD Admin

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


        <a href="destinations.php">

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


        <a href="bookings.php">

            📋 Bookings

        </a>


        <a
        href="reports.php"
        class="active"
        >

            📈 Reports

        </a>


    </div>


</aside>



<!-- =========================================
     MAIN CONTENT
========================================= -->


<main class="admin-main">



<!-- PAGE HEADING -->


<section class="report-page-heading">


    <h1>

        Reports & Analytics

    </h1>


    <p>

        TourBD Admin ·

        <?php
        echo $current_date;
        ?>

    </p>


</section>



<!-- =========================================
     REPORT CARDS
========================================= -->


<section class="report-summary-grid">



<!-- TOTAL REVENUE -->


<div class="report-summary-card">


<div>


<p>

TOTAL REVENUE (PAID)

</p>


<h2 class="report-green">

৳<?php

echo number_format(
    $total_revenue,
    0
);

?>

</h2>


</div>


<span class="report-icon">

💰

</span>


</div>



<!-- TOTAL BOOKINGS -->


<div class="report-summary-card">


<div>


<p>

TOTAL BOOKINGS

</p>


<h2 class="report-blue">

<?php
echo $total_bookings;
?>

</h2>


</div>


<span class="report-icon">

📋

</span>


</div>



<!-- TOTAL TRAVELERS -->


<div class="report-summary-card">


<div>


<p>

TOTAL TRAVELERS

</p>


<h2 class="report-purple">

<?php
echo $total_travelers;
?>

</h2>


</div>


<span class="report-icon">

🧳

</span>


</div>



<!-- AVG BOOKING -->


<div class="report-summary-card">


<div>


<p>

AVG. BOOKING VALUE

</p>


<h2 class="report-orange">

৳<?php

echo number_format(
    $average_booking,
    0
);

?>

</h2>


</div>


<span class="report-icon">

📊

</span>


</div>



<!-- CONFIRMED RATE -->


<div class="report-summary-card">


<div>


<p>

CONFIRMED RATE

</p>


<h2 class="report-green">

<?php

echo number_format(
    $confirmed_rate,
    0
);

?>%

</h2>


</div>


<span class="report-rate-icon">

✓

</span>


</div>



<!-- CANCELLATION RATE -->


<div class="report-summary-card">


<div>


<p>

CANCELLATION RATE

</p>


<h2 class="report-red">

<?php

echo number_format(
    $cancellation_rate,
    0
);

?>%

</h2>


</div>


<span class="report-rate-icon">

X

</span>


</div>


</section>



<!-- =========================================
     REVENUE REPORTS
========================================= -->


<section class="report-chart-grid">



<!-- =====================================
     REVENUE BY DESTINATION
===================================== -->


<div class="report-chart-card">


<h2>

Revenue by Destination

</h2>



<?php


if(
    count(
        $destination_data
    ) > 0
){


    foreach(
        $destination_data
        as
        $destination
    ){


        $destination_percent = 0;


        if(
            $highest_destination_revenue
            > 0
        ){


            $destination_percent =

                (
                    $destination[
                        'revenue'
                    ]
                    /
                    $highest_destination_revenue
                )

                * 100;


        }


?>


<div class="report-progress-item">



<div class="report-progress-heading">


<strong>

📍

<?php
echo e(
    $destination[
        'destination_name'
    ]
);
?>


</strong>


<span>

৳<?php

echo number_format(
    $destination[
        'revenue'
    ],
    0
);

?>

</span>


</div>



<div class="report-progress-background">


<div

class="
report-progress-bar
destination-revenue-bar
"

style="
width:
<?php
echo $destination_percent;
?>%;
"

>

</div>


</div>



<div class="destination-report-info">


<span>

<?php
echo $destination[
    'booking_count'
];
?>

bookings

</span>


<span>

<?php
echo $destination[
    'traveler_count'
];
?>

travelers

</span>


<span>

<?php
echo $destination[
    'confirmed_count'
];
?>

confirmed

</span>


</div>


</div>


<?php


    }


}

else{


?>


<p class="no-data-report">

No destination report data available.

</p>


<?php


}


?>


</div>



<!-- =====================================
     REVENUE BY PAYMENT METHOD
===================================== -->


<div class="report-chart-card">


<h2>

Revenue by Payment Method

</h2>



<?php


if(
    count(
        $payment_method_data
    ) > 0
){


    foreach(
        $payment_method_data
        as
        $method
    ){


        $method_bar_percent = 0;

        $total_percent = 0;



        if(
            $highest_method_revenue
            > 0
        ){


            $method_bar_percent =

                (
                    $method['revenue']
                    /
                    $highest_method_revenue
                )

                * 100;


        }



        if($total_revenue > 0){


            $total_percent =

                (
                    $method['revenue']
                    /
                    $total_revenue
                )

                * 100;


        }



        /* PAYMENT ICON */


        $payment_icon = "💳";


        if(
            strtolower(
                $method['method']
            )
            == 'bkash'
        ){


            $payment_icon = "📱";


        }


        elseif(
            strtolower(
                $method['method']
            )
            == 'nagad'
        ){


            $payment_icon = "📱";


        }


        elseif(
            strtolower(
                $method['method']
            )
            == 'cash'
        ){


            $payment_icon = "💵";


        }


?>


<div class="report-progress-item">



<div class="report-progress-heading">


<strong>

<?php
echo $payment_icon;
?>

<?php
echo e(
    $method['method']
);
?>


</strong>


<span>

৳<?php

echo number_format(
    $method['revenue'],
    0
);

?>

</span>


</div>



<div class="report-progress-background">


<div

class="
report-progress-bar
payment-revenue-bar
"

style="
width:
<?php
echo $method_bar_percent;
?>%;
"

>

</div>


</div>



<div class="payment-percentage">


<?php

echo number_format(
    $total_percent,
    0
);

?>%

of total revenue


</div>


</div>


<?php


    }


}

else{


?>


<p class="no-data-report">

No paid payment data available.

</p>


<?php


}


?>


</div>


</section>



<!-- =========================================
     PACKAGE PERFORMANCE
========================================= -->


<section class="package-performance-card">


<h2>

Package Performance Report

</h2>



<div class="table-wrapper">


<table class="performance-table">


<thead>


<tr>


<th>

Package

</th>


<th>

Destination

</th>


<th>

Bookings

</th>


<th>

Travelers

</th>


<th>

Revenue

</th>


<th>

Occupancy

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



        /* =================================
           OCCUPANCY CALCULATION
        ================================= */


        $occupancy = 0;


        if(
            $package[
                'total_seats'
            ] > 0
        ){


            $used_seats =

                $package[
                    'total_seats'
                ]

                -

                $package[
                    'available_seats'
                ];



            $occupancy =

                (
                    $used_seats
                    /
                    $package[
                        'total_seats'
                    ]
                )

                * 100;


        }



        /* KEEP BETWEEN 0 AND 100 */


        if($occupancy < 0){

            $occupancy = 0;

        }


        if($occupancy > 100){

            $occupancy = 100;

        }



?>


<tr>



<!-- PACKAGE -->


<td class="performance-package">


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



<!-- BOOKINGS -->


<td class="performance-number">


<?php
echo $package[
    'booking_count'
];
?>


</td>



<!-- TRAVELERS -->


<td class="performance-number">


<?php
echo $package[
    'traveler_count'
];
?>


</td>



<!-- REVENUE -->


<td class="performance-revenue">


৳<?php

echo number_format(
    $package[
        'revenue'
    ],
    0
);

?>


</td>



<!-- OCCUPANCY -->


<td>


<div class="occupancy-container">


<div class="occupancy-background">


<div

class="
occupancy-bar

<?php

if($occupancy >= 100){

    echo "full-occupancy";

}

?>

"

style="
width:
<?php
echo $occupancy;
?>%;
"

>

</div>


</div>


<span>


<?php

echo number_format(
    $occupancy,
    0
);

?>%


</span>


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
colspan="6"
class="no-data"
>

No package data available.

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