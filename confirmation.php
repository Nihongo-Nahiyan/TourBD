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
   GET BOOKING ID
========================================================= */

$booking_id = 0;

if (isset($_GET['booking_id'])) {

    $booking_id = (int) $_GET['booking_id'];

}
elseif (isset($_POST['booking_id'])) {

    $booking_id = (int) $_POST['booking_id'];

}


/* =========================================================
   CHECK BOOKING ID
========================================================= */

if ($booking_id <= 0) {

    die("Invalid booking request.");

}


/* =========================================================
   GET BOOKING + USER + PACKAGE + PAYMENT INFORMATION
========================================================= */

$sql = "

    SELECT

        b.booking_id,
        b.user_id,
        b.package_id,
        b.travelers,
        b.booking_date,
        b.travel_date,
        b.total_amount,
        b.status,

        u.name AS user_name,
        u.email AS user_email,

        p.package_name,
        p.price,
        p.duration_days,
        p.duration_nights,
        p.image_url,

        d.destination_name,
        d.location,

        pay.method AS payment_method,
        pay.payment_status,
        pay.transaction_ref,
        pay.payment_date

    FROM bookings b

    INNER JOIN users u
        ON b.user_id = u.user_id

    INNER JOIN tour_packages p
        ON b.package_id = p.package_id

    INNER JOIN destinations d
        ON p.destination_id = d.destination_id

    INNER JOIN payments pay
        ON b.booking_id = pay.booking_id

    WHERE b.booking_id = ?
      AND b.user_id = ?
      AND b.status = 'confirmed'
      AND pay.payment_status = 'paid'

    LIMIT 1

";


$stmt = mysqli_prepare($conn, $sql);


if ($stmt === false) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $booking_id,
    $user_id
);


if (!mysqli_stmt_execute($stmt)) {

    die(
        "Query error: "
        . mysqli_stmt_error($stmt)
    );

}


$result = mysqli_stmt_get_result($stmt);


if ($result === false) {

    die(
        "Could not get booking result."
    );

}


$booking = mysqli_fetch_assoc($result);


mysqli_stmt_close($stmt);


/* =========================================================
   VERIFY BOOKING
========================================================= */

if (!$booking) {

    /*
       If payment has not been recorded as paid
       or booking is not confirmed, do not show
       the confirmation page.
    */

    header(
        "Location: payment.php?booking_id="
        . $booking_id
    );

    exit;

}


/* =========================================================
   SAFE OUTPUT
========================================================= */

function safe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   PAGE TITLE
========================================================= */

$pageTitle = "Booking Confirmation";


/* =========================================================
   HEADER
========================================================= */

require_once "includes/header.php";

?>


<style>

/* =========================================================
   CONFIRMATION PAGE
========================================================= */

.confirmation-page {

    background: #faf9f6;

    min-height: 650px;

    padding: 48px 20px 55px;

}


/* =========================================================
   SUCCESS AREA
========================================================= */

.confirmation-success {

    text-align: center;

    max-width: 760px;

    margin: 0 auto 34px;

}


.confirmation-icon {

    width: 96px;

    height: 96px;

    margin: 0 auto 25px;

    border-radius: 50%;

    background: #12a69c;

    color: #111827;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 52px;

    font-weight: 400;

    box-shadow:
        0 12px 30px rgba(18,128,120,.20);

}


.confirmation-success h1 {

    margin: 0 0 13px;

    font-size: 32px;

    font-weight: 600;

}


.confirmation-message {

    margin: 0;

    color: #78716c;

    font-size: 14px;

    line-height: 1.7;

}


.confirmation-message strong {

    color: #087d75;

}


/* =========================================================
   MAIN BOOKING CARD
========================================================= */

.confirmation-card {

    max-width: 590px;

    margin: 0 auto;

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    box-shadow:
        0 7px 22px rgba(0,0,0,.06);

    padding: 32px;

}


/* =========================================================
   CARD HEADER
========================================================= */

.confirmation-card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 28px;

}


.confirmation-card-header h2 {

    margin: 0;

    font-size: 20px;

    font-weight: 500;

}


/* =========================================================
   CONFIRMED BADGE
========================================================= */

.status-confirmed {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    background: #d1fae5;

    color: #047857;

    font-size: 12px;

    font-weight: 600;

    white-space: nowrap;

}


/* =========================================================
   BOOKING DETAILS GRID
========================================================= */

.confirmation-details-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    column-gap: 55px;

    row-gap: 24px;

}


/* =========================================================
   DETAIL ITEM
========================================================= */

.confirmation-detail {

    min-width: 0;

}


.confirmation-detail-label {

    color: #78716c;

    font-size: 12px;

    font-weight: 600;

    letter-spacing: .5px;

    text-transform: uppercase;

    margin-bottom: 7px;

}


.confirmation-detail-value {

    color: #111827;

    font-size: 14px;

    font-weight: 600;

    line-height: 1.4;

    word-break: break-word;

}


.confirmation-detail-value.highlight {

    color: #087d75;

}


/* =========================================================
   PAYMENT STATUS
========================================================= */

.paid-badge {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    background: #d1fae5;

    color: #047857;

    font-size: 12px;

    font-weight: 600;

}


/* =========================================================
   TRANSACTION ID
========================================================= */

.transaction-value {

    font-size: 14px;

    font-weight: 600;

}


/* =========================================================
   TOTAL
========================================================= */

.confirmation-total {

    margin-top: 27px;

    padding-top: 20px;

    border-top: 2px solid #128078;

    display: flex;

    justify-content: space-between;

    align-items: center;

}


.confirmation-total-label {

    color: #78716c;

    font-size: 14px;

    font-weight: 600;

}


.confirmation-total-value {

    color: #087d75;

    font-size: 27px;

    font-weight: 600;

}


/* =========================================================
   WHAT HAPPENS NEXT
========================================================= */

.next-box {

    max-width: 590px;

    margin: 24px auto 0;

    background: #f0fdfa;

    border: 1px solid #72e0d4;

    border-radius: 16px;

    padding: 22px 26px;

}


.next-box h3 {

    margin: 0 0 13px;

    color: #087d75;

    font-size: 16px;

    font-weight: 600;

}


.next-box p {

    margin: 9px 0;

    font-size: 13px;

    line-height: 1.5;

    color: #292524;

}


/* =========================================================
   BUTTONS
========================================================= */

.confirmation-actions {

    display: flex;

    justify-content: center;

    gap: 16px;

    margin-top: 30px;

}


.confirmation-btn {

    display: inline-block;

    text-decoration: none;

    padding: 12px 24px;

    border-radius: 8px;

    font-size: 14px;

    font-weight: 600;

    text-align: center;

}


.confirmation-btn-primary {

    background: #128078;

    color: white;

}


.confirmation-btn-primary:hover {

    background: #0f6e68;

}


.confirmation-btn-secondary {

    background: white;

    color: #087d75;

    border: 1px solid #128078;

}


.confirmation-btn-secondary:hover {

    background: #f0fdfa;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .confirmation-page {

        padding: 35px 16px 45px;

    }


    .confirmation-card {

        padding: 25px 20px;

    }


    .confirmation-details-grid {

        grid-template-columns: 1fr;

        row-gap: 20px;

    }


    .confirmation-card-header {

        align-items: flex-start;

    }


    .confirmation-success h1 {

        font-size: 28px;

    }


    .confirmation-actions {

        flex-direction: column;

    }


    .confirmation-btn {

        width: 100%;

        box-sizing: border-box;

    }

}

</style>


<section class="confirmation-page">


    <!-- =====================================================
         SUCCESS MESSAGE
    ===================================================== -->

    <div class="confirmation-success">


        <div class="confirmation-icon">

            ✓

        </div>


        <h1>

            Booking Confirmed!

        </h1>


        <p class="confirmation-message">

            Your trip to

            <strong>
                <?php
                echo safe(
                    $booking['destination_name']
                );
                ?>
            </strong>

            is booked successfully.

            A confirmation has been sent to

            <strong>
                <?php
                echo safe(
                    $booking['user_email']
                );
                ?>
            </strong>.

        </p>


    </div>



    <!-- =====================================================
         BOOKING DETAILS CARD
    ===================================================== -->

    <div class="confirmation-card">


        <!-- CARD HEADER -->

        <div class="confirmation-card-header">


            <h2>

                Booking Details

            </h2>


            <span class="status-confirmed">

                ✓ Confirmed

            </span>


        </div>



        <!-- =================================================
             DETAILS GRID
        ================================================= -->

        <div class="confirmation-details-grid">


            <!-- BOOKING ID -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Booking ID

                </div>

                <div class="confirmation-detail-value highlight">

                    #<?php
                    echo safe(
                        $booking['booking_id']
                    );
                    ?>

                </div>

            </div>



            <!-- PACKAGE -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Package

                </div>

                <div class="confirmation-detail-value">

                    <?php
                    echo safe(
                        $booking['package_name']
                    );
                    ?>

                </div>

            </div>



            <!-- TRAVELER -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Traveler

                </div>

                <div class="confirmation-detail-value">

                    <?php
                    echo safe(
                        $booking['user_name']
                    );
                    ?>

                </div>

            </div>



            <!-- DESTINATION -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Destination

                </div>

                <div class="confirmation-detail-value">

                    📍

                    <?php
                    echo safe(
                        $booking['destination_name']
                    );
                    ?>

                </div>

            </div>



            <!-- TRAVEL DATE -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Travel Date

                </div>

                <div class="confirmation-detail-value">

                    📅

                    <?php
                    echo safe(
                        $booking['travel_date']
                    );
                    ?>

                </div>

            </div>



            <!-- TRAVELERS -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Travelers

                </div>

                <div class="confirmation-detail-value">

                    🧳

                    <?php
                    echo safe(
                        $booking['travelers']
                    );
                    ?>

                    <?php
                    if (
                        (int)$booking['travelers'] === 1
                    ) {
                        echo " person";
                    }
                    else {
                        echo " people";
                    }
                    ?>

                </div>

            </div>



            <!-- PAYMENT METHOD -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Payment Method

                </div>

                <div class="confirmation-detail-value">

                    <?php
                    echo safe(
                        $booking['payment_method']
                    );
                    ?>

                </div>

            </div>



            <!-- PAYMENT STATUS -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Payment Status

                </div>

                <div class="confirmation-detail-value">

                    <span class="paid-badge">

                        ✓ Paid

                    </span>

                </div>

            </div>



            <!-- TRANSACTION ID -->

            <div class="confirmation-detail">

                <div class="confirmation-detail-label">

                    Transaction ID

                </div>

                <div class="confirmation-detail-value transaction-value">

                    <?php

                    if (
                        isset(
                            $booking['transaction_ref']
                        )
                        &&
                        $booking['transaction_ref'] !== ""
                    ) {

                        echo safe(
                            $booking['transaction_ref']
                        );

                    }
                    else {

                        echo "N/A";

                    }

                    ?>

                </div>

            </div>


        </div>



        <!-- =================================================
             TOTAL
        ================================================= -->

        <div class="confirmation-total">


            <span class="confirmation-total-label">

                Total Amount Paid

            </span>


            <strong class="confirmation-total-value">

                ৳<?php

                echo number_format(
                    (float)$booking['total_amount'],
                    2
                );

                ?>

            </strong>


        </div>


    </div>



    <!-- =====================================================
         WHAT HAPPENS NEXT
    ===================================================== -->

    <div class="next-box">


        <h3>

            📋 What happens next?

        </h3>


        <p>

            ✓ Our team will contact you at

            <strong>
                <?php
                echo safe(
                    $booking['user_email']
                );
                ?>
            </strong>

            within 24 hours.

        </p>


        <p>

            ✓ You will receive a detailed itinerary
            and pickup instructions.

        </p>


        <p>

            ✓ Bring a copy of this confirmation
            on your travel day.

        </p>


    </div>



    <!-- =====================================================
         BUTTONS
    ===================================================== -->

    <div class="confirmation-actions">


        <a
            href="my_bookings.php"
            class="confirmation-btn confirmation-btn-primary"
        >

            📋 View My Bookings

        </a>


        <a
            href="packages.php"
            class="confirmation-btn confirmation-btn-secondary"
        >

            Browse More Packages

        </a>


    </div>


</section>


<?php

require_once "includes/footer.php";

?>