<?php

/* =========================================================
   START SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE CONNECTION
========================================================= */

require_once "config/db.php";


/* =========================================================
   CHECK LOGIN
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit;

}


$user_id = (int) $_SESSION['user_id'];


/* =========================================================
   GET USER NAME
========================================================= */

$user_name = "User";

if (
    isset($_SESSION['name']) &&
    $_SESSION['name'] !== ""
) {

    $user_name = $_SESSION['name'];

}


/* =========================================================
   HANDLE CANCEL BOOKING
========================================================= */

$error = "";
$success = "";


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_booking'])
) {


    $cancel_booking_id = isset($_POST['booking_id'])
        ? (int) $_POST['booking_id']
        : 0;


    if ($cancel_booking_id <= 0) {

        $error = "Invalid booking.";

    }
    else {


        /*
           Only allow the logged-in customer
           to cancel their own booking.
        */

        $cancel_sql = "
            UPDATE bookings
            SET status = 'cancelled'
            WHERE booking_id = ?
              AND user_id = ?
              AND status <> 'cancelled'
        ";


        $cancel_stmt = mysqli_prepare(
            $conn,
            $cancel_sql
        );


        if (!$cancel_stmt) {

            $error = "Could not process the cancellation.";

        }
        else {


            mysqli_stmt_bind_param(
                $cancel_stmt,
                "ii",
                $cancel_booking_id,
                $user_id
            );


            if (
                mysqli_stmt_execute(
                    $cancel_stmt
                )
            ) {

                if (
                    mysqli_stmt_affected_rows(
                        $cancel_stmt
                    ) === 1
                ) {

                    $success =
                        "Booking cancelled successfully.";

                }
                else {

                    $error =
                        "This booking could not be cancelled.";

                }

            }
            else {

                $error =
                    "Could not cancel the booking.";

            }


            mysqli_stmt_close(
                $cancel_stmt
            );

        }

    }

}


/* =========================================================
   GET CUSTOMER BOOKINGS
========================================================= */

$sql = "
    SELECT

        b.booking_id,
        b.user_id,
        b.package_id,
        b.travelers,
        b.travel_date,
        b.total_amount,
        b.status,

        p.package_name,
        p.price,
        p.duration_days,
        p.duration_nights,
        p.image_url,

        d.destination_name,
        d.location,

        pay.payment_id,
        pay.method,
        pay.payment_status,
        pay.transaction_ref,
        pay.payment_date

    FROM bookings b

    INNER JOIN tour_packages p
        ON b.package_id = p.package_id

    INNER JOIN destinations d
        ON p.destination_id = d.destination_id

    LEFT JOIN payments pay
        ON b.booking_id = pay.booking_id

    WHERE b.user_id = ?

    ORDER BY b.booking_id DESC
";


$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    die(
        "Database error: "
        . htmlspecialchars(
            mysqli_error($conn),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);


mysqli_stmt_execute(
    $stmt
);


$result = mysqli_stmt_get_result(
    $stmt
);


/* =========================================================
   STORE BOOKINGS
========================================================= */

$bookings = [];


while (
    $row = mysqli_fetch_assoc(
        $result
    )
) {

    $bookings[] = $row;

}


mysqli_stmt_close(
    $stmt
);


/* =========================================================
   CALCULATE SUMMARY
========================================================= */

$total_bookings = count(
    $bookings
);


$confirmed_bookings = 0;

$pending_bookings = 0;

$total_spent = 0;


foreach (
    $bookings as $booking
) {


    $booking_status =
        strtolower(
            trim(
                $booking['status']
            )
        );


    $payment_status =
        isset(
            $booking['payment_status']
        )
        ? strtolower(
            trim(
                $booking['payment_status']
            )
        )
        : "";


    /* -----------------------------------------
       CONFIRMED
    ----------------------------------------- */

    if (
        $booking_status === "confirmed"
    ) {

        $confirmed_bookings++;

    }


    /* -----------------------------------------
       PENDING
    ----------------------------------------- */

    if (
        $booking_status === "pending"
    ) {

        $pending_bookings++;

    }


    /* -----------------------------------------
       TOTAL SPENT
    ----------------------------------------- */

    /*
       Only count paid payments
       as spent.
    */

    if (
        $payment_status === "paid"
    ) {

        $total_spent +=
            (float) $booking['total_amount'];

    }

}


/* =========================================================
   PAGE TITLE
========================================================= */

$pageTitle = "My Bookings";


/* =========================================================
   HEADER
========================================================= */

require_once "includes/header.php";

?>


<style>

/* =========================================================
   MY BOOKINGS PAGE
========================================================= */

.my-bookings-page {

    background: #faf9f6;

    min-height: 650px;

    padding: 38px 20px 60px;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.my-bookings-container {

    max-width: 1050px;

    margin: 0 auto;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.bookings-heading {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    margin-bottom: 30px;

}


.bookings-heading h1 {

    margin: 0 0 8px;

    font-size: 32px;

    font-weight: 600;

    color: #111111;

}


.bookings-heading p {

    margin: 0;

    color: #78716c;

    font-size: 14px;

}


/* =========================================================
   BOOK NEW TRIP BUTTON
========================================================= */

.new-trip-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 13px 20px;

    background: #087f78;

    color: white;

    text-decoration: none;

    border-radius: 8px;

    font-size: 14px;

    font-weight: 700;

    white-space: nowrap;

}


.new-trip-button:hover {

    background: #066e68;

}


/* =========================================================
   MESSAGE
========================================================= */

.booking-message {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;

}


.booking-success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;

}


.booking-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;

}


/* =========================================================
   SUMMARY GRID
========================================================= */

.booking-summary-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 32px;

}


/* =========================================================
   SUMMARY CARD
========================================================= */

.booking-summary-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 14px;

    padding: 22px 15px;

    text-align: center;

    box-shadow:
        0 4px 15px rgba(0,0,0,.04);

}


.summary-icon {

    font-size: 22px;

    margin-bottom: 8px;

}


.summary-number {
color: #087f78;

    font-size: 26px;

    font-weight: 700;

    margin-bottom: 5px;

}


.summary-label {

    color: #78716c;

    font-size: 13px;

}


/* =========================================================
   BOOKING CARD
========================================================= */

.booking-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 15px;

    padding: 26px 24px;

    margin-bottom: 18px;

    box-shadow:
        0 4px 15px rgba(0,0,0,.04);

}


/* =========================================================
   BOOKING TOP
========================================================= */

.booking-top {

    display: grid;

    grid-template-columns:
        1.05fr 2.1fr 1.15fr 1fr 0.8fr;

    gap: 25px;

    align-items: start;

}


.booking-column label {

    display: block;

    color: #78716c;

    font-size: 11px;

    font-weight: 600;

    letter-spacing: .5px;

    margin-bottom: 7px;

    text-transform: uppercase;

}


.booking-number {

    color: #087f78;

    font-size: 17px;

    font-weight: 700;

}


.package-name {

    color: #111111;
font-size: 17px;

    font-weight: 700;

    line-height: 1.25;

}


.destination {

    color: #78716c;

    font-size: 13px;

    margin-top: 5px;

}


.travel-info {

    color: #111111;

    font-size: 14px;

    font-weight: 600;

}


.travel-icon {

    margin-right: 5px;

}


/* =========================================================
   PRICE
========================================================= */

.booking-price {

    color: #087f78;
font-size: 25px;

    font-weight: 700;

    text-align: right;

}


/* =========================================================
   DIVIDER
========================================================= */

.booking-divider {

    border: none;

    border-top: 1px solid #eeeeee;

    margin: 22px 0 18px;

}


/* =========================================================
   BOOKING BOTTOM
========================================================= */

.booking-bottom {

    display: grid;

    grid-template-columns:
        1fr 1fr 1fr 1.4fr auto;

    gap: 22px;

    align-items: center;

}


.booking-detail label {

    display: block;

    color: #78716c;

    font-size: 11px;

    font-weight: 600;

    letter-spacing: .5px;

    margin-bottom: 7px;

    text-transform: uppercase;

}


.booking-detail-value {

    color: #111111;

    font-size: 14px;

    font-weight: 600;

}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;

}


.status-confirmed {

    background: #d1fae5;

    color: #047857;

}


.status-pending {

    background: #fef3c7;

    color: #b45309;

}


.status-cancelled {

    background: #fee2e2;

    color: #b91c1c;

}


/* =========================================================
   PAYMENT STATUS
========================================================= */

.payment-paid {

    background: #d1fae5;

    color: #047857;

}


.payment-pending {

    background: #fef3c7;

    color: #b45309;

}


.payment-other {

    background: #f3f4f6;

    color: #4b5563;

}


/* =========================================================
   ACTIONS
========================================================= */

.booking-actions {

    display: flex;

    flex-direction: column;

    gap: 8px;

    align-items: stretch;

}


.view-package-button {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 9px 14px;

    border: 1px solid #087f78;

    border-radius: 8px;

    background: white;

    color: #087f78;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    white-space: nowrap;

}


.view-package-button:hover {

    background: #f0fdfa;

}


.cancel-booking-button {

    border: none;

    border-radius: 7px;

    padding: 9px 14px;

    background: #ef4444;

    color: white;

    font-family: inherit;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    white-space: nowrap;

}


.cancel-booking-button:hover {

    background: #dc2626;

}


/* =========================================================
   TRANSACTION
========================================================= */

.transaction-id {

    color: #44403c;

    font-size: 12px;

}


/* =========================================================
   NO BOOKINGS
========================================================= */

.no-bookings {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 15px;

    padding: 55px 20px;

    text-align: center;

}


.no-bookings-icon {

    font-size: 35px;

    margin-bottom: 12px;

}


.no-bookings h2 {

    margin: 0 0 8px;
font-size: 22px;

}


.no-bookings p {

    margin: 0 0 20px;

    color: #78716c;

    font-size: 14px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .booking-top {

        grid-template-columns:
            1fr 2fr 1fr 1fr;

    }

    .booking-price {

        text-align: left;

    }

    .booking-bottom {

        grid-template-columns:
            repeat(3, 1fr);

    }

}


@media (max-width: 750px) {

    .bookings-heading {

        flex-direction: column;

        gap: 18px;

    }

    .booking-summary-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .booking-top {

        grid-template-columns: 1fr 1fr;

    }

    .booking-bottom {

        grid-template-columns: 1fr 1fr;

    }

}


@media (max-width: 500px) {

    .my-bookings-page {

        padding:
            25px 15px 40px;

    }

    .booking-summary-grid {

        grid-template-columns: 1fr;

    }

    .booking-top {

        grid-template-columns: 1fr;

    }

    .booking-bottom {

        grid-template-columns: 1fr;

    }

    .booking-card {

        padding: 20px 17px;

    }

}

</style>


<section class="my-bookings-page">


    <div class="my-bookings-container">


        <!-- =================================================
             PAGE HEADING
        ================================================== -->

        <div class="bookings-heading">


            <div>

                <h1>
                    My Bookings
                </h1>


                <p>

                    All your trips in one place,
                    <?php
                    echo htmlspecialchars(
                        $user_name,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

            </div>


            <a
                href="packages.php"
                class="new-trip-button"
            >

                + Book New Trip

            </a>


        </div>



        <!-- =================================================
             MESSAGES
        ================================================== -->

        <?php if ($success !== "") { ?>

            <div
                class="booking-message booking-success"
            >

                <?php
                echo htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php } ?>


        <?php if ($error !== "") { ?>

            <div
                class="booking-message booking-error"
            >

                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php } ?>



        <!-- =================================================
             SUMMARY CARDS
        ================================================== -->

        <div class="booking-summary-grid">


            <!-- TOTAL BOOKINGS -->

            <div class="booking-summary-card">

                <div class="summary-icon">
                    📋
                </div>

                <div class="summary-number">

                    <?php
                    echo $total_bookings;
                    ?>

                </div>

                <div class="summary-label">
                    Total Bookings
                </div>

            </div>



            <!-- CONFIRMED -->

            <div class="booking-summary-card">

                <div class="summary-icon">
                    ✓
                </div>

                <div class="summary-number">

                    <?php
                    echo $confirmed_bookings;
                    ?>

                </div>

                <div class="summary-label">
                    Confirmed
                </div>

            </div>



            <!-- PENDING -->

            <div class="booking-summary-card">

                <div class="summary-icon">
                    ⏳
                </div>

                <div class="summary-number">

                    <?php
                    echo $pending_bookings;
                    ?>

                </div>

                <div class="summary-label">
                    Pending
                </div>

            </div>



            <!-- TOTAL SPENT -->

            <div class="booking-summary-card">

                <div class="summary-icon">
                    💰
                </div>

                <div class="summary-number">

                    ৳<?php
                    echo number_format(
                        $total_spent,
                        0
                    );
                    ?>

                </div>

                <div class="summary-label">
                    Total Spent
                </div>

            </div>


        </div>



        <!-- =================================================
             BOOKINGS
        ================================================== -->

        <?php if (count($bookings) > 0) { ?>


            <?php foreach ($bookings as $booking) { ?>


                <?php

                /* -----------------------------------------
                   BOOKING STATUS
                ----------------------------------------- */

                $status =
                    strtolower(
                        trim(
                            $booking['status']
                        )
                    );


                if (
                    $status === "confirmed"
                ) {

                    $status_class =
                        "status-confirmed";

                    $status_icon =
                        "✓";

                }
                elseif (
                    $status === "cancelled"
                ) {

                    $status_class =
                        "status-cancelled";

                    $status_icon =
                        "✕";

                }
                else {

                    $status_class =
                        "status-pending";

                    $status_icon =
                        "⏳";

                }


                /* -----------------------------------------
                   PAYMENT STATUS
                ----------------------------------------- */

                $payment_status =
                    isset(
                        $booking['payment_status']
                    )
                    ? strtolower(
                        trim(
                            $booking['payment_status']
                        )
                    )
                    : "";


                if (
                    $payment_status === "paid"
                ) {

                    $payment_class =
                        "payment-paid";

                    $payment_icon =
                        "✓";

                }
                elseif (
                    $payment_status === "pending"
                ) {

                    $payment_class =
                        "payment-pending";

                    $payment_icon =
                        "⏳";

                }
                else {

                    $payment_class =
                        "payment-other";

                    $payment_icon =
                        "";

                }

                ?>


                <!-- =================================================
                     BOOKING CARD
                ================================================== -->

                <div class="booking-card">


                    <!-- TOP -->

                    <div class="booking-top">


                        <!-- BOOKING -->

                        <div class="booking-column">

                            <label>
                                Booking
                            </label>

                            <div class="booking-number">

                                #<?php
                                echo (int)
                                    $booking['booking_id'];
                                ?>

                            </div>

                        </div>



                        <!-- PACKAGE -->

                        <div class="booking-column">

                            <label>
                                Package
                            </label>

                            <div class="package-name">

                                <?php

                                echo htmlspecialchars(
                                    $booking['package_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </div>


                            <div class="destination">

                                📍

                                <?php

                                echo htmlspecialchars(
                                    $booking['destination_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </div>

                        </div>



                        <!-- TRAVEL DATE -->

                        <div class="booking-column">

                            <label>
                                Travel Date
                            </label>

                            <div class="travel-info">

                                📅

                                <?php

                                echo htmlspecialchars(
                                    $booking['travel_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                ?>

                            </div>

                        </div>



                        <!-- TRAVELERS -->

                        <div class="booking-column">

                            <label>
                                Travelers
                            </label>

                            <div class="travel-info">

                                🧳

                                <?php

                                echo (int)
                                    $booking['travelers'];

                                ?>

                            </div>

                        </div>



                        <!-- PRICE -->

                        <div class="booking-column">

                            <div class="booking-price">

                                ৳<?php

                                echo number_format(
                                    (float)
                                    $booking['total_amount'],
                                    0
                                );

                                ?>

                            </div>

                        </div>


                    </div>



                    <hr
                        class="booking-divider"
                    >



                    <!-- BOTTOM -->

                    <div class="booking-bottom">


                        <!-- STATUS -->

                        <div class="booking-detail">

                            <label>
                                Status
                            </label>


                            <span
                                class="
                                    status-badge
                                    <?php
                                    echo $status_class;
                                    ?>
                                "
                            >

                                <?php
                                echo $status_icon;
                                ?>

                                &nbsp;

                                <?php

                                echo ucfirst(
                                    $status
                                );

                                ?>

                            </span>

                        </div>



                        <!-- PAYMENT -->

                        <div class="booking-detail">

                            <label>
                                Payment
                            </label>


                            <?php if (
                                $payment_status !== ""
                            ) { ?>

                                <span
                                    class="
                                        status-badge
                                        <?php
                                        echo $payment_class;
                                        ?>
                                    "
                                >

                                    <?php
                                    echo $payment_icon;
                                    ?>

                                    &nbsp;

                                    <?php

                                    echo ucfirst(
                                        $payment_status
                                    );

                                    ?>

                                </span>

                            <?php } else { ?>

                                <span
                                    class="
                                        status-badge
                                        payment-other
                                    "
                                >

                                    Not Paid

                                </span>

                            <?php } ?>

                        </div>



                        <!-- METHOD -->

                        <div class="booking-detail">

                            <label>
                                Method
                            </label>


                            <div
                                class="booking-detail-value"
                            >

                                <?php

                                if (
                                    isset(
                                        $booking['method']
                                    )
                                    &&
                                    $booking['method'] !== ""
                                ) {

                                    echo htmlspecialchars(
                                        $booking['method'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                }
                                else {

                                    echo "—";

                                }

                                ?>

                            </div>

                        </div>



                        <!-- TRANSACTION ID -->

                        <div class="booking-detail">

                            <label>
                                Transaction ID
                            </label>


                            <div
                                class="transaction-id"
                            >

                                <?php

                                if (
                                    isset(
                                        $booking[
                                            'transaction_ref'
                                        ]
                                    )
                                    &&
                                    $booking[
                                        'transaction_ref'
                                    ] !== ""
                                ) {

                                    echo htmlspecialchars(
                                        $booking[
                                            'transaction_ref'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );

                                }
                                else {

                                    echo "—";

                                }

                                ?>

                            </div>

                        </div>



                        <!-- ACTIONS -->

                        <div class="booking-actions">


                            <!-- VIEW PACKAGE -->

                            <a
                                href="
                                    package_details.php?id=<?php
                                    echo (int)
                                        $booking['package_id'];
                                    ?>
                                "
                                class="
                                    view-package-button
                                "
                            >

                                View Package

                            </a>



                            <!-- CANCEL -->

                            <?php

                            if (
                                $status !== "cancelled"
                            ) {

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
                                        name="booking_id"
                                        value="<?php
                                        echo (int)
                                            $booking[
                                                'booking_id'
                                            ];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="cancel_booking"
                                        class="
                                            cancel-booking-button
                                        "
                                    >

                                        Cancel Booking

                                    </button>


                                </form>

                            <?php } ?>


                        </div>


                    </div>


                </div>


            <?php } ?>


        <?php } else { ?>


            <!-- =================================================
                 NO BOOKINGS
            ================================================== -->

            <div class="no-bookings">

                <div class="no-bookings-icon">
                    ✈️
                </div>


                <h2>
                    No Bookings Yet
                </h2>


                <p>
                    You haven't booked any trips yet.
                </p>


                <a
                    href="packages.php"
                    class="new-trip-button"
                >

                    Explore Packages

                </a>

            </div>


        <?php } ?>


    </div>

</section>


<?php

require_once "includes/footer.php";

?>