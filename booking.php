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
   GET PACKAGE ID
========================================================= */

$package_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


/* =========================================================
   CHECK PACKAGE ID
========================================================= */

if ($package_id <= 0) {
    die("Invalid booking request.");
}


/* =========================================================
   GET PACKAGE INFORMATION
========================================================= */

$sql = "
    SELECT *
    FROM available_packages
    WHERE package_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $package_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$package = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/* =========================================================
   PACKAGE NOT FOUND
========================================================= */

if (!$package) {
    die("Package not found.");
}


/* =========================================================
   GET CUSTOMER INFORMATION
========================================================= */

$user_name = "";
$user_email = "";

$user_sql = "
    SELECT
        name,
        email
    FROM users
    WHERE user_id = ?
    LIMIT 1
";

$user_stmt = mysqli_prepare(
    $conn,
    $user_sql
);

if (!$user_stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($user_stmt);

$user_result =
    mysqli_stmt_get_result($user_stmt);

$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);


if ($user) {

    $user_name =
        $user['name'];

    $user_email =
        $user['email'];

}


/* =========================================================
   BOOKING VARIABLES
========================================================= */

$message = "";

$travelers = 1;

$travel_date =
    $package['departure_date'];

$special_requests = "";


/* =========================================================
   BOOKING PROCESS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $travelers =
        isset($_POST['travelers'])
        ? (int)$_POST['travelers']
        : 1;


    $travel_date =
        isset($_POST['travel_date'])
        ? trim($_POST['travel_date'])
        : "";


    $special_requests =
        isset($_POST['special_requests'])
        ? trim($_POST['special_requests'])
        : "";


    /* =====================================================
       VALIDATE TRAVELERS
    ===================================================== */

    if ($travelers < 1) {

        $message =
            "Number of travelers must be at least 1.";

    }


    /* =====================================================
       VALIDATE TRAVEL DATE
    ===================================================== */

    elseif (
        $travel_date !==
        $package['departure_date']
    ) {

        $message =
            "Travel date must match the package departure date.";

    }


    /* =====================================================
       DATABASE TRANSACTION
    ===================================================== */

    else {

        mysqli_begin_transaction($conn);

        $booking_success = true;


        try {


            /* =============================================
               CHECK AVAILABLE SEATS
            ============================================= */

            $check_sql = "
                SELECT
                    available_seats,
                    price
                FROM tour_packages
                WHERE package_id = ?
                FOR UPDATE
            ";

            $check_stmt =
                mysqli_prepare(
                    $conn,
                    $check_sql
                );

            if (!$check_stmt) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

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

            $current_package =
                mysqli_fetch_assoc(
                    $check_result
                );

            mysqli_stmt_close(
                $check_stmt
            );


            /* =============================================
               PACKAGE CHECK
            ============================================= */

            if (!$current_package) {

                throw new Exception(
                    "Package could not be found."
                );

            }


            /* =============================================
               SEAT CHECK
            ============================================= */

            if (
                $travelers >
                (int)$current_package['available_seats']
            ) {

                throw new Exception(
                    "There are not enough seats left."
                );

            }


            /* =============================================
               CALCULATE TOTAL
            ============================================= */

            $price =
                (float)$current_package['price'];

            $total_amount =
                $price * $travelers;

            $status =
                "pending";


            /* =============================================
               CREATE BOOKING
            ============================================= */

            $booking_sql = "
                INSERT INTO bookings
                (
                    user_id,
                    package_id,
                    travelers,
                    travel_date,
                    total_amount,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";

            $booking_stmt =
                mysqli_prepare(
                    $conn,
                    $booking_sql
                );

            if (!$booking_stmt) {

                throw new Exception(
                    mysqli_error($conn)
                );

            }


            mysqli_stmt_bind_param(
                $booking_stmt,
                "iiisds",
                $user_id,
                $package_id,
                $travelers,
                $travel_date,
                $total_amount,
                $status
            );


            if (
                !mysqli_stmt_execute(
                    $booking_stmt
                )
            ) {

                throw new Exception(
                    mysqli_stmt_error(
                        $booking_stmt
                    )
                );

            }


            /* =============================================
               GET NEW BOOKING ID
            ============================================= */

            $new_booking_id =
                mysqli_insert_id($conn);


            mysqli_stmt_close(
                $booking_stmt
            );


            if ($new_booking_id <= 0) {

                throw new Exception(
                    "Could not create booking ID."
                );

            }


            /* =============================================
               REDUCE AVAILABLE SEATS
            ============================================= */

            $update_sql = "
                UPDATE tour_packages
                SET available_seats =
                    available_seats - ?
                WHERE package_id = ?
                  AND available_seats >= ?
            ";

            $update_stmt =
                mysqli_prepare(
                    $conn,
                    $update_sql
                );

            if (!$update_stmt) {

                throw new Exception(
                    mysqli_error($conn)
                );

            }


            mysqli_stmt_bind_param(
                $update_stmt,
                "iii",
                $travelers,
                $package_id,
                $travelers
            );


            if (
                !mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                throw new Exception(
                    mysqli_stmt_error(
                        $update_stmt
                    )
                );

            }


            if (
                mysqli_stmt_affected_rows(
                    $update_stmt
                ) !== 1
            ) {

                throw new Exception(
                    "Could not update available seats."
                );

            }


            mysqli_stmt_close(
                $update_stmt
            );


            /* =============================================
               COMMIT
            ============================================= */

            mysqli_commit($conn);


            /* =============================================
               GO TO PAYMENT WITH BOOKING ID
            ============================================= */

            header(
                "Location: payment.php?booking_id="
                . $new_booking_id
            );

            exit;


        }
        catch (Exception $e) {

            mysqli_rollback($conn);

            $booking_success = false;

            $message =
                $e->getMessage();

        }

    }

}


/* =========================================================
   PAGE TITLE
========================================================= */

$pageTitle = "Booking Details";


/* =========================================================
   HEADER
========================================================= */

require_once "includes/header.php";

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.booking-page {

    background: #faf9f6;

    min-height: 650px;

    padding: 28px 20px 55px;

}


/* =========================================================
   PROGRESS
========================================================= */

.booking-progress {

    max-width: 860px;

    margin: 0 auto 38px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

}


.progress-step {

    position: relative;

    text-align: center;

    color: #78716c;

    font-size: 12px;

}


.progress-step:not(:last-child)::after {

    content: "";

    position: absolute;

    top: 16px;

    left: 50%;

    width: 100%;

    height: 2px;

    background: #e7e5e4;

    z-index: 0;

}


.progress-step.done:not(:last-child)::after,
.progress-step.active:not(:last-child)::after {

    background: #128078;

}


.progress-circle {

    width: 32px;

    height: 32px;

    margin: 0 auto 7px;

    border-radius: 50%;

    background: #e7e5e4;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 700;

    position: relative;

    z-index: 1;

}


.progress-step.done .progress-circle,
.progress-step.active .progress-circle {

    background: #128078;

    color: white;

}


.progress-step.done,
.progress-step.active {

    color: #128078;

    font-weight: 600;

}


/* =========================================================
   MAIN LAYOUT
========================================================= */

.booking-layout {

    max-width: 1000px;

    margin: 0 auto;

    display: grid;

    grid-template-columns:
        minmax(0, 1.5fr)
        minmax(300px, .8fr);

    gap: 32px;

    align-items: start;

}


/* =========================================================
   LEFT CARD
========================================================= */

.booking-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    box-shadow:
        0 7px 22px rgba(0,0,0,.06);

    padding: 34px;

}


.booking-card h1 {

    margin: 0 0 7px;

    font-size: 28px;

    font-weight: 600;

}


.booking-subtitle {

    margin: 0 0 25px;

    color: #78716c;

    font-size: 14px;

    line-height: 1.6;

}


.booking-subtitle strong {

    color: #292524;

}


/* =========================================================
   CUSTOMER BOX
========================================================= */

.booking-for {

    background: #f0fdfa;

    border: 1px solid #72e0d4;

    border-radius: 10px;

    padding: 15px 17px;

    margin-bottom: 25px;

    font-size: 14px;

    color: #57534e;

}


.booking-for strong {

    color: #087d75;

}


/* =========================================================
   FORM LABEL
========================================================= */

.form-label {

    display: block;

    font-size: 14px;

    font-weight: 600;

    margin-bottom: 9px;

}


.required {

    color: #ef4444;

}


/* =========================================================
   TRAVELER SELECTOR
========================================================= */

.traveler-row {

    display: flex;

    align-items: center;

    gap: 16px;

    margin-bottom: 26px;

}


.stepper-btn {

    width: 38px;

    height: 38px;

    border: 1px solid #d6d3d1;

    border-radius: 50%;

    background: white;

    font-size: 20px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

}


.stepper-btn:hover {

    border-color: #128078;

    color: #128078;

}


.traveler-number {

    min-width: 25px;

    text-align: center;

    font-size: 20px;

    font-weight: 700;

}


.seats-note {

    color: #78716c;

    font-size: 12px;

}


/* =========================================================
   INPUTS
========================================================= */

.input-control,
.textarea-control {

    width: 100%;

    box-sizing: border-box;

    border: 1px solid #d6d3d1;

    border-radius: 9px;

    padding: 13px 14px;

    background: white;

    font: inherit;

}


.input-control {

    height: 45px;

}


.textarea-control {

    min-height: 100px;

    resize: vertical;

}


.input-control:focus,
.textarea-control:focus {

    outline: none;

    border-color: #128078;

    box-shadow:
        0 0 0 2px rgba(18,128,120,.08);

}


.form-help {

    color: #78716c;

    font-size: 12px;

    margin: 6px 0 24px;

}


/* =========================================================
   TOTAL BOX
========================================================= */

.total-box {

    background: #f0fdfa;

    border-radius: 10px;

    padding: 17px 18px;

    margin-top: 24px;

    margin-bottom: 20px;

    border: 1px solid #d5f5f0;

}


.total-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin: 6px 0;

    font-size: 14px;

}


.total-row span {

    color: #57534e;

}


.total-row strong {

    color: #292524;

}


.total-row.final {

    margin-top: 12px;

    padding-top: 12px;

    border-top: 1px solid #b8e9e2;

}


.total-row.final span {

    font-weight: 700;

    color: #087d75;

}


.total-row.final strong {

    color: #087d75;

    font-size: 22px;

}


/* =========================================================
   CONTINUE BUTTON
========================================================= */

.booking-submit {

    width: 100%;

    border: none;

    border-radius: 9px;

    padding: 15px;

    background: #128078;

    color: white;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;

}


.booking-submit:hover {

    background: #0f6e68;

}


/* =========================================================
   ERROR
========================================================= */

.booking-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;

    border-radius: 9px;

    padding: 12px 15px;

    margin-bottom: 20px;

    font-size: 13px;

}


/* =========================================================
   RIGHT SUMMARY
========================================================= */

.booking-summary-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    box-shadow:
        0 7px 22px rgba(0,0,0,.06);

    padding: 25px;

    position: sticky;

    top: 90px;

}


.booking-summary-card h2 {

    margin: 0 0 18px;

    font-size: 20px;

    font-weight: 600;

}


/* =========================================================
   IMAGE
========================================================= */

.summary-image {

    width: 100%;

    height: 175px;

    object-fit: cover;

    border-radius: 10px;

    display: block;

    margin-bottom: 17px;

}


/* =========================================================
   PACKAGE NAME
========================================================= */

.summary-name {

    margin: 0 0 8px;

    font-size: 16px;

    font-weight: 700;

    line-height: 1.4;

}


.summary-meta {

    color: #78716c;

    font-size: 13px;

    margin: 0 0 18px;

    line-height: 1.6;

}


/* =========================================================
   SUMMARY ROWS
========================================================= */

.summary-divider {

    border: 0;

    border-top: 1px solid #e7e5e4;

    margin: 0 0 15px;

}


.summary-row {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding: 11px 0;

    border-bottom: 1px solid #f0efed;

    font-size: 13px;

}


.summary-row span {

    color: #78716c;

}


.summary-row strong {

    color: #292524;

}


.summary-row.seats strong {

    color: #087d75;

}


/* =========================================================
   SUMMARY TOTAL
========================================================= */

.summary-total {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 18px;

    padding-top: 16px;

    border-top: 1px solid #d6d3d1;

}


.summary-total span {

    font-weight: 600;

}


.summary-total strong {

    color: #087d75;

    font-size: 23px;

}


/* =========================================================
   INFO BOX
========================================================= */

.summary-info {

    margin-top: 20px;

    padding: 13px 14px;

    background: #f0fdfa;

    border-radius: 9px;

    color: #087d75;

    font-size: 12px;

    line-height: 1.6;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .booking-layout {

        grid-template-columns: 1fr;

        padding: 0 5px;

    }


    .booking-summary-card {

        position: static;

    }


    .booking-card {

        padding: 25px 20px;

    }

}

</style>


<section class="booking-page">


    <!-- =====================================================
         PROGRESS
    ===================================================== -->

    <div class="booking-progress">


        <div class="progress-step done">

            <div class="progress-circle">
                ✓
            </div>

            Browse Package

        </div>


        <div class="progress-step active">

            <div class="progress-circle">
                2
            </div>

            Booking Details

        </div>


        <div class="progress-step">

            <div class="progress-circle">
                3
            </div>

            Payment

        </div>


        <div class="progress-step">

            <div class="progress-circle">
                4
            </div>

            Confirmation

        </div>


    </div>



    <!-- =====================================================
         MAIN
    ===================================================== -->

    <div class="booking-layout">


        <!-- =================================================
             BOOKING FORM
        ================================================= -->

        <div class="booking-card">


            <h1>
                Booking Details
            </h1>


            <p class="booking-subtitle">

                Fill in your travel preferences for

                <strong>
                    <?php
                    echo e(
                        $package['package_name']
                    );
                    ?>
                </strong>

            </p>



            <?php if ($message !== "") { ?>

                <div class="booking-error">

                    <?php
                    echo e($message);
                    ?>

                </div>

            <?php } ?>



            <!-- CUSTOMER -->

            <div class="booking-for">

                <strong>
                    Booking for:
                </strong>

                <?php
                echo e($user_name);
                ?>

                <?php if ($user_email !== "") { ?>

                    (
                    <?php
                    echo e($user_email);
                    ?>
                    )

                <?php } ?>

            </div>



            <form
                method="POST"
                action="booking.php?id=<?php
                    echo $package_id;
                ?>"
            >


                <!-- =========================================
                     TRAVELERS
                ========================================== -->

                <label class="form-label">

                    Number of Travelers

                    <span class="required">
                        *
                    </span>

                </label>


                <div class="traveler-row">


                    <button
                        type="button"
                        class="stepper-btn"
                        id="minusBtn"
                    >
                        −
                    </button>


                    <span
                        class="traveler-number"
                        id="travelerDisplay"
                    >
                        <?php
                        echo e($travelers);
                        ?>
                    </span>


                    <button
                        type="button"
                        class="stepper-btn"
                        id="plusBtn"
                    >
                        +
                    </button>


                    <span class="seats-note">

                        <?php
                        echo e(
                            $package['available_seats']
                        );
                        ?>

                        seats available

                    </span>


                </div>


                <input
                    type="hidden"
                    name="travelers"
                    id="travelers"
                    value="<?php
                        echo e($travelers);
                    ?>"
                >



                <!-- =========================================
                     TRAVEL DATE
                ========================================== -->

                <label class="form-label">

                    Travel Date

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="date"
                    name="travel_date"
                    id="travelDate"
                    class="input-control"
                    value="<?php
                        echo e($travel_date);
                    ?>"
                    required
                >


                <p class="form-help">

                    Package departure date:

                    <?php
                    echo e(
                        $package['departure_date']
                    );
                    ?>

                </p>



                <!-- =========================================
                     SPECIAL REQUESTS
                ========================================== -->

                <label class="form-label">

                    Special Requests

                    <span
                        style="
                            font-weight:400;
                            color:#78716c;
                        "
                    >
                        (optional)
                    </span>

                </label>


                <textarea
                    class="textarea-control"
                    name="special_requests"
                    placeholder="Any dietary requirements, accessibility needs, or special occasions..."
                ><?php
                    echo e($special_requests);
                ?></textarea>



                <!-- =========================================
                     TOTAL
                ========================================== -->

                <div class="total-box">


                    <div class="total-row">

                        <span>
                            Package Price
                        </span>

                        <strong>

                            ৳<?php
                            echo number_format(
                                (float)$package['price'],
                                2
                            );
                            ?>

                        </strong>

                    </div>


                    <div class="total-row">

                        <span>
                            Travelers
                        </span>

                        <strong id="totalTravelers">

                            ×
                            <?php
                            echo e($travelers);
                            ?>

                        </strong>

                    </div>


                    <div class="total-row final">

                        <span>
                            Your Total
                        </span>

                        <strong id="totalAmount">

                            ৳<?php
                            echo number_format(
                                (float)$package['price']
                                * $travelers,
                                2
                            );
                            ?>

                        </strong>

                    </div>


                </div>



                <!-- =========================================
                     CONTINUE
                ========================================== -->

                <button
                    type="submit"
                    class="booking-submit"
                >

                    Continue to Payment →

                </button>


            </form>


        </div>



        <!-- =================================================
             BOOKING SUMMARY
        ================================================= -->

        <aside class="booking-summary-card">


            <h2>
                Booking Summary
            </h2>


            <?php
            if (
                isset($package['image_url'])
                &&
                $package['image_url'] !== ""
            ) {
            ?>

                <img
                    src="assets/images/<?php
                        echo e(
                            $package['image_url']
                        );
                    ?>"
                    class="summary-image"
                    alt="<?php
                        echo e(
                            $package['package_name']
                        );
                    ?>"
                >

            <?php
            }
            ?>


            <p class="summary-name">

                <?php
                echo e(
                    $package['package_name']
                );
                ?>

            </p>


            <p class="summary-meta">

                📍

                <?php
                echo e(
                    $package['destination_name']
                );
                ?>

                <?php
                if (
                    isset($package['duration_days'])
                    &&
                    isset($package['duration_nights'])
                ) {
                ?>

                    ·

                    <?php
                    echo e(
                        $package['duration_days']
                    );
                    ?>

                    Days /

                    <?php
                    echo e(
                        $package['duration_nights']
                    );
                    ?>

                    Nights

                <?php
                }
                ?>

            </p>


            <hr class="summary-divider">


            <div class="summary-row">

                <span>
                    Price/person
                </span>

                <strong>

                    ৳<?php
                    echo number_format(
                        (float)$package['price'],
                        2
                    );
                    ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Travelers
                </span>

                <strong id="summaryTravelers">

                    ×
                    <?php
                    echo e($travelers);
                    ?>

                </strong>

            </div>


            <div class="summary-row seats">

                <span>
                    Seats Left
                </span>

                <strong>

                    <?php
                    echo e(
                        $package['available_seats']
                    );
                    ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Travel Date
                </span>

                <strong id="summaryDate">

                    <?php
                    echo e($travel_date);
                    ?>

                </strong>

            </div>


            <div class="summary-total">

                <span>
                    Total
                </span>

                <strong id="summaryTotal">

                    ৳<?php
                    echo number_format(
                        (float)$package['price']
                        * $travelers,
                        0
                    );
                    ?>

                </strong>

            </div>


            <div class="summary-info">

                ✓ Secure booking<br>
                ✓ Package information stored safely<br>
                ✓ Continue to payment after booking

            </div>


        </aside>


    </div>


</section>



<script>

(function () {


    const maxSeats =
        <?php
        echo (int)$package['available_seats'];
        ?>;


    const price =
        <?php
        echo (float)$package['price'];
        ?>;


    const departureDate =
        <?php
        echo json_encode(
            $package['departure_date']
        );
        ?>;


    const input =
        document.getElementById(
            "travelers"
        );


    const display =
        document.getElementById(
            "travelerDisplay"
        );


    const totalTravelers =
        document.getElementById(
            "totalTravelers"
        );


    const summaryTravelers =
        document.getElementById(
            "summaryTravelers"
        );


    const totalAmount =
        document.getElementById(
            "totalAmount"
        );


    const summaryTotal =
        document.getElementById(
            "summaryTotal"
        );


    const date =
        document.getElementById(
            "travelDate"
        );


    const summaryDate =
        document.getElementById(
            "summaryDate"
        );


    function update() {


        let n =
            parseInt(
                input.value,
                10
            ) || 1;


        n =
            Math.max(
                1,
                Math.min(
                    n,
                    maxSeats
                )
            );


        input.value = n;


        display.textContent = n;


        totalTravelers.textContent =
            "× " + n;


        summaryTravelers.textContent =
            "× " + n;


        const amount =
            price * n;


        totalAmount.textContent =
            "৳" +
            amount.toLocaleString(
                "en-BD",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );


        summaryTotal.textContent =
            "৳" +
            amount.toLocaleString(
                "en-BD",
                {
                    maximumFractionDigits: 0
                }
            );

    }


    document
        .getElementById("minusBtn")
        .addEventListener(
            "click",
            function () {

                let value =
                    parseInt(
                        input.value,
                        10
                    ) || 1;


                if (value > 1) {

                    input.value =
                        value - 1;

                    update();

                }

            }
        );


    document
        .getElementById("plusBtn")
        .addEventListener(
            "click",
            function () {

                let value =
                    parseInt(
                        input.value,
                        10
                    ) || 1;


                if (value < maxSeats) {

                    input.value =
                        value + 1;

                    update();

                }

            }
        );


    date.min =
        departureDate;

    date.max =
        departureDate;


    date.addEventListener(
        "change",
        function () {

            summaryDate.textContent =
                date.value;

        }
    );


    update();


})();

</script>


<?php

require_once "includes/footer.php";

?>