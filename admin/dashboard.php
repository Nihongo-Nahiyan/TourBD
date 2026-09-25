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



/* =========================================
   TOTAL REVENUE
========================================= */


$revenue_sql = "

SELECT SUM(amount) AS total

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


if($total_revenue === NULL){

    $total_revenue = 0;

}



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
   ACTIVE PACKAGES
========================================= */


$package_sql = "

SELECT COUNT(*) AS total

FROM tour_packages

WHERE LOWER(status) != 'sold_out'

AND available_seats > 0

";


$package_result =
    mysqli_query(
        $conn,
        $package_sql
    );


$package_row =
    mysqli_fetch_assoc(
        $package_result
    );


$active_packages =
    $package_row['total'];



/* =========================================
   REGISTERED USERS
========================================= */


$user_sql = "

SELECT COUNT(*) AS total

FROM users

WHERE role = 'user'

";


$user_result =
    mysqli_query(
        $conn,
        $user_sql
    );


$user_row =
    mysqli_fetch_assoc(
        $user_result
    );


$total_users =
    $user_row['total'];



/* =========================================
   DESTINATIONS
========================================= */


$destination_count_sql = "

SELECT COUNT(*) AS total

FROM destinations

WHERE active = 1

";


$destination_count_result =
    mysqli_query(
        $conn,
        $destination_count_sql
    );


$destination_count_row =
    mysqli_fetch_assoc(
        $destination_count_result
    );


$total_destinations =
    $destination_count_row['total'];



/* =========================================
   HOTELS
========================================= */


$hotel_sql = "

SELECT COUNT(*) AS total

FROM hotels

";


$hotel_result =
    mysqli_query(
        $conn,
        $hotel_sql
    );


$hotel_row =
    mysqli_fetch_assoc(
        $hotel_result
    );


$total_hotels =
    $hotel_row['total'];



/* =========================================
   TRANSPORT OPTIONS
========================================= */


$transport_sql = "

SELECT COUNT(*) AS total

FROM transport

";


$transport_result =
    mysqli_query(
        $conn,
        $transport_sql
    );


$transport_row =
    mysqli_fetch_assoc(
        $transport_result
    );


$total_transport =
    $transport_row['total'];



/* =========================================
   TOTAL TRAVELERS
========================================= */


$traveler_sql = "

SELECT SUM(travelers) AS total

FROM bookings

WHERE LOWER(status) != 'cancelled'

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


if($total_travelers === NULL){

    $total_travelers = 0;

}



/* =========================================
   RECENT BOOKINGS
========================================= */


$recent_sql = "

SELECT

    bookings.booking_id,

    bookings.travel_date,

    bookings.total_amount,

    bookings.status,

    users.name,

    tour_packages.package_name,

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


LEFT JOIN payments

ON bookings.booking_id =
   payments.booking_id


ORDER BY bookings.booking_id DESC


LIMIT 5

";


$recent_result =
    mysqli_query(
        $conn,
        $recent_sql
    );



/* =========================================
   BOOKING STATUS
========================================= */


$confirmed_bookings = 0;

$pending_bookings = 0;

$cancelled_bookings = 0;


$status_sql = "

SELECT

    LOWER(status) AS status,

    COUNT(*) AS total

FROM bookings

GROUP BY LOWER(status)

";


$status_result =
    mysqli_query(
        $conn,
        $status_sql
    );


while(
    $status_row =
    mysqli_fetch_assoc(
        $status_result
    )
){


    if(
        $status_row['status']
        == 'confirmed'
    ){

        $confirmed_bookings =
            $status_row['total'];

    }


    if(
        $status_row['status']
        == 'pending'
    ){

        $pending_bookings =
            $status_row['total'];

    }


    if(
        $status_row['status']
        == 'cancelled'
    ){

        $cancelled_bookings =
            $status_row['total'];

    }


}



/* BOOKING STATUS PERCENTAGE */


$confirmed_percent = 0;

$pending_percent = 0;

$cancelled_percent = 0;


if($total_bookings > 0){


    $confirmed_percent =
        (
            $confirmed_bookings
            /
            $total_bookings
        )
        * 100;


    $pending_percent =
        (
            $pending_bookings
            /
            $total_bookings
        )
        * 100;


    $cancelled_percent =
        (
            $cancelled_bookings
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

    destinations.destination_name,

    COUNT(
        DISTINCT bookings.booking_id
    ) AS booking_count,

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

    ) AS revenue


FROM destinations


LEFT JOIN tour_packages

ON destinations.destination_id =
   tour_packages.destination_id


LEFT JOIN bookings

ON tour_packages.package_id =
   bookings.package_id


LEFT JOIN payments

ON bookings.booking_id =
   payments.booking_id


GROUP BY

    destinations.destination_id,

    destinations.destination_name


ORDER BY revenue DESC


LIMIT 4

";


$destination_result =
    mysqli_query(
        $conn,
        $destination_sql
    );



/* SAVE DESTINATION RESULTS */


$destination_data = array();


$highest_revenue = 0;


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
        $highest_revenue
    ){

        $highest_revenue =
            $destination['revenue'];

    }


}


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Admin Overview - TourBD

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


        <a
        href="dashboard.php"
        class="active"
        >

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



    <!-- DASHBOARD TITLE -->


    <section class="dashboard-title">


        <h1>

            Dashboard Overview

        </h1>


        <p>

            TourBD Admin ·

            <?php
            echo $current_date;
            ?>

        </p>


    </section>




    <!-- =====================================
         OVERVIEW STAT CARDS
    ====================================== -->


    <section class="stats-grid">




        <!-- TOTAL REVENUE -->


        <div class="
        stat-card
        revenue-card
        ">


            <div>


                <p>

                    Total Revenue

                </p>


                <h2>

                    ৳<?php

                    echo number_format(
                        $total_revenue,
                        0
                    );

                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                💰

            </span>


        </div>




        <!-- TOTAL BOOKINGS -->


        <div class="
        stat-card
        booking-card
        ">


            <div>


                <p>

                    Total Bookings

                </p>


                <h2>

                    <?php
                    echo $total_bookings;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                📋

            </span>


        </div>




        <!-- ACTIVE PACKAGES -->


        <div class="
        stat-card
        package-card
        ">


            <div>


                <p>

                    Active Packages

                </p>


                <h2>

                    <?php
                    echo $active_packages;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                🎒

            </span>


        </div>




        <!-- REGISTERED USERS -->


        <div class="
        stat-card
        user-card
        ">


            <div>


                <p>

                    Registered Users

                </p>


                <h2>

                    <?php
                    echo $total_users;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                👤

            </span>


        </div>




        <!-- DESTINATIONS -->


        <div class="
        stat-card
        destination-card
        ">


            <div>


                <p>

                    Destinations

                </p>


                <h2>

                    <?php
                    echo $total_destinations;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                🗺️

            </span>


        </div>




        <!-- HOTELS -->


        <div class="
        stat-card
        hotel-card
        ">


            <div>


                <p>

                    Hotels Listed

                </p>


                <h2>

                    <?php
                    echo $total_hotels;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                🏨

            </span>


        </div>




        <!-- TRANSPORT -->


        <div class="
        stat-card
        transport-card
        ">


            <div>


                <p>

                    Transport Options

                </p>


                <h2>

                    <?php
                    echo $total_transport;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                🚌

            </span>


        </div>




        <!-- TRAVELERS -->


        <div class="
        stat-card
        traveler-card
        ">


            <div>


                <p>

                    Total Travelers

                </p>


                <h2>

                    <?php
                    echo $total_travelers;
                    ?>

                </h2>


            </div>


            <span class="stat-icon">

                🧳

            </span>


        </div>


    </section>




    <!-- =====================================
         RECENT BOOKINGS
    ====================================== -->


    <section class="recent-bookings">


        <h2>

            Recent Bookings

        </h2>



        <div class="table-wrapper">


            <table>


                <thead>


                    <tr>


                        <th>

                            Booking #

                        </th>


                        <th>

                            Package

                        </th>


                        <th>

                            Traveler

                        </th>


                        <th>

                            Date

                        </th>


                        <th>

                            Amount

                        </th>


                        <th>

                            Status

                        </th>


                        <th>

                            Payment

                        </th>


                    </tr>


                </thead>



                <tbody>


                <?php


                if(
                    mysqli_num_rows(
                        $recent_result
                    )
                    > 0
                ){


                    while(
                        $booking =
                        mysqli_fetch_assoc(
                            $recent_result
                        )
                    ){


                ?>


                    <tr>


                        <td class="booking-number">


                            #<?php
                            echo $booking[
                                'booking_id'
                            ];
                            ?>


                        </td>



                        <td class="package-name">


                            <?php
                            echo e(
                                $booking[
                                    'package_name'
                                ]
                            );
                            ?>


                        </td>



                        <td>


                            <?php
                            echo e(
                                $booking[
                                    'name'
                                ]
                            );
                            ?>


                        </td>



                        <td>


                            <?php
                            echo $booking[
                                'travel_date'
                            ];
                            ?>


                        </td>



                        <td class="amount">


                            ৳<?php

                            echo number_format(
                                $booking[
                                    'total_amount'
                                ],
                                0
                            );

                            ?>


                        </td>



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


                    </tr>


                <?php


                    }


                }

                else{


                ?>


                    <tr>


                        <td
                        colspan="7"
                        class="no-data"
                        >


                            No bookings available yet.


                        </td>


                    </tr>


                <?php


                }


                ?>


                </tbody>


            </table>


        </div>


    </section>




    <!-- =====================================
         BOTTOM ANALYTICS
    ====================================== -->


    <section class="analytics-grid">




        <!-- BOOKING STATUS -->


        <div class="analytics-card">


            <h2>

                Booking Status

            </h2>



            <!-- CONFIRMED -->


            <div class="progress-item">


                <div class="progress-title">


                    <strong>

                        Confirmed

                    </strong>


                    <span>

                        <?php
                        echo $confirmed_bookings;
                        ?>

                        /

                        <?php
                        echo $total_bookings;
                        ?>

                    </span>


                </div>


                <div class="progress-background">


                    <div
                    class="
                    progress-bar
                    confirmed-bar
                    "
                    style="
                    width:
                    <?php
                    echo $confirmed_percent;
                    ?>%;
                    "
                    >

                    </div>


                </div>


            </div>




            <!-- PENDING -->


            <div class="progress-item">


                <div class="progress-title">


                    <strong>

                        Pending

                    </strong>


                    <span>

                        <?php
                        echo $pending_bookings;
                        ?>

                        /

                        <?php
                        echo $total_bookings;
                        ?>

                    </span>


                </div>


                <div class="progress-background">


                    <div
                    class="
                    progress-bar
                    pending-bar
                    "
                    style="
                    width:
                    <?php
                    echo $pending_percent;
                    ?>%;
                    "
                    >

                    </div>


                </div>


            </div>




            <!-- CANCELLED -->


            <div class="progress-item">


                <div class="progress-title">


                    <strong>

                        Cancelled

                    </strong>


                    <span>

                        <?php
                        echo $cancelled_bookings;
                        ?>

                        /

                        <?php
                        echo $total_bookings;
                        ?>

                    </span>


                </div>


                <div class="progress-background">


                    <div
                    class="
                    progress-bar
                    cancelled-bar
                    "
                    style="
                    width:
                    <?php
                    echo $cancelled_percent;
                    ?>%;
                    "
                    >

                    </div>


                </div>


            </div>


        </div>




        <!-- REVENUE BY DESTINATION -->


        <div class="analytics-card">


            <h2>

                Revenue by Destination

            </h2>



            <?php


            if(
                count(
                    $destination_data
                )
                > 0
            ){


                foreach(
                    $destination_data
                    as
                    $destination
                ){


                    $revenue_percent = 0;


                    if(
                        $highest_revenue
                        > 0
                    ){


                        $revenue_percent =

                            (
                                $destination[
                                    'revenue'
                                ]
                                /
                                $highest_revenue
                            )

                            * 100;


                    }


            ?>


                <div class="
                destination-progress
                ">


                    <div class="
                    destination-heading
                    ">


                        <strong>


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



                    <div class="
                    progress-background
                    ">


                        <div
                        class="
                        progress-bar
                        revenue-bar
                        "
                        style="
                        width:
                        <?php
                        echo $revenue_percent;
                        ?>%;
                        "
                        >


                        </div>


                    </div>



                    <p>


                        <?php
                        echo $destination[
                            'booking_count'
                        ];
                        ?>

                        bookings


                    </p>


                </div>


            <?php


                }


            }

            else{


            ?>


                <p class="no-destination">

                    No destination data available.

                </p>


            <?php


            }


            ?>


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