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
   VERIFY USER ACCOUNT
========================================================= */

$user_sql = "
    SELECT
        user_id,
        name,
        email,
        role
    FROM users
    WHERE user_id = ?
    LIMIT 1
";


$user_stmt = mysqli_prepare(
    $conn,
    $user_sql
);


if (!$user_stmt) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $user_stmt,
    "i",
    $user_id
);


mysqli_stmt_execute(
    $user_stmt
);


$user_result =
    mysqli_stmt_get_result(
        $user_stmt
    );


$user =
    mysqli_fetch_assoc(
        $user_result
    );


mysqli_stmt_close(
    $user_stmt
);


/* =========================================================
   ACCOUNT NOT FOUND
========================================================= */

if (!$user) {

    session_unset();

    session_destroy();

    header("Location: login.php");

    exit;

}


/* =========================================================
   GET BOOKING ID
========================================================= */

$booking_id = 0;


if (isset($_GET['booking_id'])) {

    $booking_id =
        (int) $_GET['booking_id'];

}
elseif (isset($_POST['booking_id'])) {

    $booking_id =
        (int) $_POST['booking_id'];

}


/* =========================================================
   CHECK BOOKING ID
========================================================= */

if ($booking_id <= 0) {

    die(
        "Invalid booking request."
    );

}


/* =========================================================
   GET BOOKING INFORMATION
========================================================= */

$booking_sql = "

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
        d.location

    FROM bookings b

    INNER JOIN tour_packages p
        ON b.package_id = p.package_id

    INNER JOIN destinations d
        ON p.destination_id = d.destination_id

    WHERE b.booking_id = ?
      AND b.user_id = ?

    LIMIT 1

";


$booking_stmt =
    mysqli_prepare(
        $conn,
        $booking_sql
    );


if (!$booking_stmt) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $booking_stmt,
    "ii",
    $booking_id,
    $user_id
);


mysqli_stmt_execute(
    $booking_stmt
);


$booking_result =
    mysqli_stmt_get_result(
        $booking_stmt
    );


$booking =
    mysqli_fetch_assoc(
        $booking_result
    );


mysqli_stmt_close(
    $booking_stmt
);


/* =========================================================
   BOOKING NOT FOUND
========================================================= */

if (!$booking) {

    die(
        "Booking not found or you do not have permission to access it."
    );

}


/* =========================================================
   CHECK EXISTING PAYMENT
========================================================= */

$existing_payment = null;


$payment_check_sql = "

    SELECT
        payment_id,
        payment_status

    FROM payments

    WHERE booking_id = ?

    ORDER BY payment_id DESC

    LIMIT 1

";


$payment_check =
    mysqli_prepare(
        $conn,
        $payment_check_sql
    );


if (!$payment_check) {

    die(
        "Database error: "
        . mysqli_error($conn)
    );

}


mysqli_stmt_bind_param(
    $payment_check,
    "i",
    $booking_id
);


mysqli_stmt_execute(
    $payment_check
);


$payment_check_result =
    mysqli_stmt_get_result(
        $payment_check
    );


$existing_payment =
    mysqli_fetch_assoc(
        $payment_check_result
    );


mysqli_stmt_close(
    $payment_check
);


/* =========================================================
   IF ALREADY PAID AND CONFIRMED
========================================================= */

if (
    $existing_payment
    &&
    $existing_payment['payment_status'] === 'paid'
    &&
    $booking['status'] === 'confirmed'
) {

    header(
        "Location: confirmation.php?booking_id="
        . $booking_id
    );

    exit;

}


/* =========================================================
   ERROR
========================================================= */

$error = "";


/* =========================================================
   HANDLE PAYMENT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['make_payment'])
) {


    /* =====================================================
       PAYMENT METHOD
    ===================================================== */

    $method =
        isset($_POST['method'])
        ? trim($_POST['method'])
        : "";


    /* =====================================================
       TRANSACTION REFERENCE
    ===================================================== */

    $transaction_ref =
        isset($_POST['transaction_ref'])
        ? trim($_POST['transaction_ref'])
        : "";


    /* =====================================================
       ALLOWED METHODS
    ===================================================== */

    $allowed_methods = [

        "bKash",
        "Nagad",
        "Rocket",
        "Card",
        "Cash"

    ];


    /* =====================================================
       CHECK PAYMENT METHOD
    ===================================================== */

    if (
        !in_array(
            $method,
            $allowed_methods,
            true
        )
    ) {

        $error =
            "Please select a valid payment method.";

    }


    /* =====================================================
       TRANSACTION ID REQUIRED
       FOR NON-CASH PAYMENT
    ===================================================== */

    elseif (
        $method !== "Cash"
        &&
        $transaction_ref === ""
    ) {

        $error =
            "Please enter your Transaction ID.";

    }


    /* =====================================================
       PROCESS PAYMENT
    ===================================================== */

    if ($error === "") {


        mysqli_begin_transaction(
            $conn
        );


        try {


            /* =============================================
               VERIFY BOOKING AGAIN
            ============================================= */

            $verify_sql = "

                SELECT
                    booking_id,
                    user_id,
                    total_amount,
                    status

                FROM bookings

                WHERE booking_id = ?
                  AND user_id = ?

                LIMIT 1

            ";


            $verify_stmt =
                mysqli_prepare(
                    $conn,
                    $verify_sql
                );


            if (!$verify_stmt) {

                throw new Exception(
                    mysqli_error($conn)
                );

            }


            mysqli_stmt_bind_param(
                $verify_stmt,
                "ii",
                $booking_id,
                $user_id
            );


            mysqli_stmt_execute(
                $verify_stmt
            );


            $verify_result =
                mysqli_stmt_get_result(
                    $verify_stmt
                );


            $verified_booking =
                mysqli_fetch_assoc(
                    $verify_result
                );


            mysqli_stmt_close(
                $verify_stmt
            );


            /* =============================================
               BOOKING VERIFICATION FAILED
            ============================================= */

            if (!$verified_booking) {

                throw new Exception(
                    "Booking verification failed."
                );

            }


            /* =============================================
               ALREADY CONFIRMED
            ============================================= */

            if (
                $verified_booking['status']
                === 'confirmed'
            ) {

                throw new Exception(
                    "This booking has already been confirmed."
                );

            }


            /* =============================================
               CHECK DUPLICATE PAYMENT
            ============================================= */

            $duplicate_sql = "

                SELECT
                    payment_id

                FROM payments

                WHERE booking_id = ?

                LIMIT 1

            ";


            $duplicate_stmt =
                mysqli_prepare(
                    $conn,
                    $duplicate_sql
                );


            if (!$duplicate_stmt) {

                throw new Exception(
                    mysqli_error($conn)
                );

            }


            mysqli_stmt_bind_param(
                $duplicate_stmt,
                "i",
                $booking_id
            );


            mysqli_stmt_execute(
                $duplicate_stmt
            );


            $duplicate_result =
                mysqli_stmt_get_result(
                    $duplicate_stmt
                );


            $duplicate_payment =
                mysqli_fetch_assoc(
                    $duplicate_result
                );


            mysqli_stmt_close(
                $duplicate_stmt
            );


            if ($duplicate_payment) {

                throw new Exception(
                    "Payment has already been recorded for this booking."
                );

            }


            /* =============================================
               PAYMENT STATUS
            ============================================= */

            /*
               This project does not use admin payment
               verification.

               Once the customer submits the payment,
               it is recorded as paid.
            */

            $payment_status =
                "paid";


            $amount =
                (float)
                $verified_booking[
                    'total_amount'
                ];


            /* =============================================
               TRANSACTION VALUE
            ============================================= */

            if ($method === "Cash") {

                $transaction_value = null;

            }
            else {

                $transaction_value =
                    $transaction_ref;

            }


            /* =============================================
               INSERT PAYMENT
            ============================================= */

            $insert_sql = "

                INSERT INTO payments
                (
                    booking_id,
                    amount,
                    method,
                    payment_status,
                    transaction_ref
                )

                VALUES
                (
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


            if (!$insert_stmt) {

                throw new Exception(
                    mysqli_error($conn)
                );

            }


            mysqli_stmt_bind_param(
                $insert_stmt,
                "idsss",
                $booking_id,
                $amount,
                $method,
                $payment_status,
                $transaction_value
            );


            if (
                !mysqli_stmt_execute(
                    $insert_stmt
                )
            ) {

                throw new Exception(
                    mysqli_stmt_error(
                        $insert_stmt
                    )
                );

            }


            mysqli_stmt_close(
                $insert_stmt
            );


            /* =============================================
               CONFIRM BOOKING
            ============================================= */

            $update_sql = "

                UPDATE bookings

                SET status = 'confirmed'

                WHERE booking_id = ?
                  AND user_id = ?

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
                "ii",
                $booking_id,
                $user_id
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


            mysqli_stmt_close(
                $update_stmt
            );


            /* =============================================
               COMMIT
            ============================================= */

            mysqli_commit(
                $conn
            );


            /* =============================================
               GO DIRECTLY TO CONFIRMATION
            ============================================= */

            header(
                "Location: confirmation.php?booking_id="
                . $booking_id
            );

            exit;


        }
        catch (Exception $e) {


            /* =============================================
               ROLLBACK
            ============================================= */

            mysqli_rollback(
                $conn
            );


            $error =
                $e->getMessage();

        }

    }

}


/* =========================================================
   PAGE
========================================================= */

$pageTitle = "Payment";


require_once "includes/header.php";

?>


<style>

/* =========================================================
   PAYMENT PAGE
========================================================= */

.payment-page {

    background: #faf9f6;

    min-height: 650px;

    padding: 32px 20px 55px;

}


/* =========================================================
   PROGRESS
========================================================= */

.payment-progress {

    max-width: 860px;

    margin: 0 auto 38px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

}


.payment-step {

    position: relative;

    text-align: center;

    color: #78716c;

    font-size: 12px;

}


.payment-step:not(:last-child)::after {

    content: "";

    position: absolute;

    top: 16px;

    left: 50%;

    width: 100%;

    height: 2px;

    background: #e7e5e4;

    z-index: 0;

}


.payment-step.done {

    color: #128078;

    font-weight: 600;

}


.payment-step.done:not(:last-child)::after {

    background: #128078;

}


.payment-circle {

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


.payment-step.done .payment-circle {

    background: #128078;

    color: white;

}


/* =========================================================
   MAIN LAYOUT
========================================================= */

.payment-layout {

    max-width: 850px;

    margin: 0 auto;

    display: grid;

    grid-template-columns:
        1.45fr 0.85fr;

    gap: 30px;

    align-items: start;

}


/* =========================================================
   PAYMENT CARD
========================================================= */

.payment-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    box-shadow:
        0 7px 22px rgba(0,0,0,.06);

    padding: 30px 32px 32px;

}


.payment-card h1 {

    margin: 0 0 6px;

    font-size: 27px;

    font-weight: 600;

}


.payment-subtitle {

    color: #78716c;

    font-size: 13px;

    margin: 0 0 27px;

}


/* =========================================================
   ERROR
========================================================= */

.payment-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;

    border-radius: 9px;

    padding: 13px 15px;

    margin-bottom: 20px;

    font-size: 13px;

}


/* =========================================================
   PAYMENT METHOD
========================================================= */

.payment-method-title {

    font-size: 14px;

    font-weight: 600;

    margin-bottom: 10px;

}


.payment-methods {

    display: grid;

    gap: 9px;

}


.payment-method {

    display: flex;

    align-items: center;

    gap: 12px;

    min-height: 69px;

    box-sizing: border-box;

    padding: 12px 14px;

    border: 1px solid #d6d3d1;

    border-radius: 9px;

    cursor: pointer;

}


.payment-method:hover {

    border-color: #128078;

    background: #f0fdfa;

}


.payment-method input {

    width: 14px;

    height: 14px;

    accent-color: #128078;

}


.payment-method-icon {

    width: 26px;

    font-size: 17px;

    text-align: center;

    flex-shrink: 0;

}


.payment-method-content {

    display: flex;

    flex-direction: column;

    gap: 2px;

}


.payment-method strong {

    display: block;

    font-size: 14px;

    font-weight: 600;

}


.payment-method span {

    color: #78716c;

    font-size: 12px;

}


/* =========================================================
   TRANSACTION ID
========================================================= */

.payment-field {

    margin-top: 22px;

}


.payment-field label {

    display: block;

    font-size: 13px;

    font-weight: 600;

    margin-bottom: 7px;

}


.payment-required {

    color: #ef4444;

}


.payment-field input {

    width: 100%;

    box-sizing: border-box;

    height: 42px;

    padding: 11px 13px;

    border: 1px solid #d6d3d1;

    border-radius: 8px;

    font-family: inherit;

    font-size: 13px;

}


.payment-field input:focus {

    outline: none;

    border-color: #128078;

}


.payment-help {

    color: #78716c;

    font-size: 11px;

    margin-top: 5px;

}


/* =========================================================
   BOOKING INFORMATION
========================================================= */

.payment-info-box {

    background: #f0fdfa;

    border-radius: 9px;

    padding: 14px 18px;

    margin-top: 20px;

}


.payment-info-row {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 4px 0;

    font-size: 13px;

}


.payment-info-row span {

    color: #78716c;

}


.payment-info-row strong {

    color: #292524;

    text-align: right;

}


.payment-info-total {

    border-top: 1px solid #72e0d4;

    margin-top: 8px;

    padding-top: 10px;

}


.payment-info-total span {

    color: #087d75;

    font-weight: 700;

}


.payment-info-total strong {

    color: #087d75;

    font-size: 19px;

}


/* =========================================================
   BUTTON
========================================================= */

.payment-button {

    width: 100%;

    margin-top: 20px;

    padding: 14px;

    border: none;

    border-radius: 8px;

    background: #128078;

    color: white;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

}


.payment-button:hover {

    background: #0f6e68;

}


/* =========================================================
   SUMMARY
========================================================= */

.payment-summary {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    box-shadow:
        0 7px 22px rgba(0,0,0,.06);

    padding: 24px;

    height: fit-content;

}


.payment-summary h2 {

    margin: 0 0 17px;

    font-size: 19px;

    font-weight: 600;

}


.summary-image {

    width: 100%;

    height: 130px;

    object-fit: cover;

    border-radius: 9px;

    display: block;

    margin-bottom: 15px;

}


.summary-name {

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 8px;

    line-height: 1.4;

}


.summary-location {

    color: #78716c;

    font-size: 12px;

    margin-bottom: 17px;

}


.summary-line {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 10px 0;

    border-bottom: 1px solid #f0efed;

    font-size: 12px;

}


.summary-line span {

    color: #78716c;

}


.summary-line strong {

    color: #292524;

    text-align: right;

}


.summary-total {

    display: flex;

    justify-content: space-between;

    margin-top: 16px;

    padding-top: 14px;

    border-top: 1px solid #d6d3d1;

}


.summary-total span {

    font-weight: 700;

}


.summary-total strong {

    color: #087d75;

    font-size: 20px;

}


/* =========================================================
   SECURITY NOTICE
========================================================= */

.security-notice {

    margin-top: 18px;

    padding: 13px 14px;

    border-radius: 9px;

    background: #fff3c4;

    color: #8a5a00;

    font-size: 11px;

    line-height: 1.5;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .payment-layout {

        grid-template-columns: 1fr;

        max-width: 600px;

    }

}

</style>


<section class="payment-page">


    <!-- =====================================================
         PROGRESS BAR
    ===================================================== -->

    <div class="payment-progress">


        <div class="payment-step done">

            <div class="payment-circle">
                ✓
            </div>

            Browse Package

        </div>


        <div class="payment-step done">

            <div class="payment-circle">
                ✓
            </div>

            Booking Details

        </div>


        <div class="payment-step done">

            <div class="payment-circle">
                3
            </div>

            Payment

        </div>


        <div class="payment-step">

            <div class="payment-circle">
                4
            </div>

            Confirmation

        </div>


    </div>



    <!-- =====================================================
         MAIN LAYOUT
    ===================================================== -->

    <div class="payment-layout">


        <!-- =================================================
             PAYMENT CARD
        ================================================= -->

        <div class="payment-card">


            <h1>
                Payment Details
            </h1>


            <p class="payment-subtitle">

                Record your payment for booking confirmation

            </p>



            <?php if ($error !== "") { ?>

                <div class="payment-error">

                    <?php

                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>

                </div>

            <?php } ?>



            <form
                method="POST"
                action="payment.php?booking_id=<?php
                    echo $booking_id;
                ?>"
            >


                <!-- =========================================
                     PAYMENT METHOD
                ========================================== -->

                <div class="payment-method-title">

                    Select Payment Method

                </div>


                <div class="payment-methods">


                    <!-- BKASH -->

                    <label class="payment-method">

                        <input
                            type="radio"
                            name="method"
                            value="bKash"
                            required
                            checked
                        >

                        <div class="payment-method-icon">

                            📱

                        </div>

                        <div class="payment-method-content">

                            <strong>
                                bKash
                            </strong>

                            <span>
                                Mobile banking — bKash
                            </span>

                        </div>

                    </label>



                    <!-- NAGAD -->

                    <label class="payment-method">

                        <input
                            type="radio"
                            name="method"
                            value="Nagad"
                        >

                        <div class="payment-method-icon">

                            📱

                        </div>

                        <div class="payment-method-content">

                            <strong>
                                Nagad
                            </strong>

                            <span>
                                Mobile banking — Nagad
                            </span>

                        </div>

                    </label>



                    <!-- ROCKET -->

                    <label class="payment-method">

                        <input
                            type="radio"
                            name="method"
                            value="Rocket"
                        >

                        <div class="payment-method-icon">

                            🚀

                        </div>

                        <div class="payment-method-content">

                            <strong>
                                Rocket
                            </strong>

                            <span>
                                DBBL Mobile Banking
                            </span>

                        </div>

                    </label>



                    <!-- CARD -->

                    <label class="payment-method">

                        <input
                            type="radio"
                            name="method"
                            value="Card"
                        >

                        <div class="payment-method-icon">

                            💳

                        </div>

                        <div class="payment-method-content">

                            <strong>
                                Debit/Credit Card
                            </strong>

                            <span>
                                Visa / Mastercard
                            </span>

                        </div>

                    </label>



                    <!-- CASH -->

                    <label class="payment-method">

                        <input
                            type="radio"
                            name="method"
                            value="Cash"
                        >

                        <div class="payment-method-icon">

                            💵

                        </div>

                        <div class="payment-method-content">

                            <strong>
                                Cash on Arrival
                            </strong>

                            <span>
                                Pay when you check in
                            </span>

                        </div>

                    </label>


                </div>



                <!-- =========================================
                     TRANSACTION ID
                ========================================== -->

                <div class="payment-field">


                    <label for="transaction_ref">

                        Transaction ID

                        <span
                            class="payment-required"
                            id="transactionRequired"
                        >
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="transaction_ref"
                        name="transaction_ref"
                        maxlength="80"
                        placeholder="Enter your transaction ID"
                    >


                    <div
                        class="payment-help"
                        id="transactionHelp"
                    >

                        Send
                        ৳<?php

                        echo number_format(
                            (float)
                            $booking['total_amount'],
                            0
                        );

                        ?>

                        to your selected payment service
                        and enter the Transaction ID above.

                    </div>


                </div>



                <!-- =========================================
                     BOOKING INFORMATION
                ========================================== -->

                <div class="payment-info-box">


                    <div class="payment-info-row">

                        <span>
                            Package
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking['package_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </strong>

                    </div>



                    <div class="payment-info-row">

                        <span>
                            Travelers
                        </span>

                        <strong>

                            <?php

                            echo (int)
                                $booking['travelers'];

                            ?>

                        </strong>

                    </div>



                    <div class="payment-info-row">

                        <span>
                            Travel Date
                        </span>

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $booking['travel_date'],
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            ?>

                        </strong>

                    </div>



                    <div
                        class="payment-info-row payment-info-total"
                    >

                        <span>
                            Total Amount
                        </span>

                        <strong>

                            ৳<?php

                            echo number_format(
                                (float)
                                $booking['total_amount'],
                                0
                            );

                            ?>

                        </strong>

                    </div>


                </div>



                <!-- =========================================
                     BOOKING ID
                ========================================== -->

                <input
                    type="hidden"
                    name="booking_id"
                    value="<?php
                        echo $booking_id;
                    ?>"
                >



                <!-- =========================================
                     SUBMIT
                ========================================== -->

                <button
                    type="submit"
                    name="make_payment"
                    class="payment-button"
                >

                    ✓ Confirm Booking & Payment

                </button>


            </form>


        </div>



        <!-- =================================================
             ORDER SUMMARY
        ================================================= -->

        <aside class="payment-summary">


            <h2>
                Order Summary
            </h2>



            <?php if (
                isset($booking['image_url'])
                &&
                $booking['image_url'] !== ""
            ) { ?>


                <img
                    src="assets/images/<?php

                        echo htmlspecialchars(
                            $booking['image_url'],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    ?>"
                    class="summary-image"
                    alt="<?php

                        echo htmlspecialchars(
                            $booking['package_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    ?>"
                >


            <?php } ?>



            <div class="summary-name">

                <?php

                echo htmlspecialchars(
                    $booking['package_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </div>



            <div class="summary-location">

                📍

                <?php

                echo htmlspecialchars(
                    $booking['destination_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

                ·

                <?php

                echo (int)
                    $booking['duration_days'];

                ?>

                Days /

                <?php

                echo (int)
                    $booking['duration_nights'];

                ?>

                Nights

            </div>



            <div class="summary-line">

                <span>
                    👤 Customer
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $user['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>

                </strong>

            </div>



            <div class="summary-line">

                <span>
                    📅 Travel Date
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $booking['travel_date'],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    ?>

                </strong>

            </div>



            <div class="summary-line">

                <span>
                    🧳 Travelers
                </span>

                <strong>

                    <?php

                    echo (int)
                        $booking['travelers'];

                    ?>

                </strong>

            </div>



            <div class="summary-line">

                <span>
                    Price/person
                </span>

                <strong>

                    ৳<?php

                    echo number_format(
                        (float)
                        $booking['price'],
                        2
                    );

                    ?>

                </strong>

            </div>



            <div class="summary-total">

                <span>
                    Total
                </span>

                <strong>

                    ৳<?php

                    echo number_format(
                        (float)
                        $booking['total_amount'],
                        2
                    );

                    ?>

                </strong>

            </div>



            <div class="security-notice">

                🔒 Your booking data is safe.


            </div>


        </aside>


    </div>


</section>



<script>

/* =========================================================
   TRANSACTION ID CONTROL
========================================================= */

(function () {


    const methods =
        document.querySelectorAll(
            'input[name="method"]'
        );


    const transactionInput =
        document.getElementById(
            "transaction_ref"
        );


    const transactionRequired =
        document.getElementById(
            "transactionRequired"
        );


    const transactionHelp =
        document.getElementById(
            "transactionHelp"
        );


    function updateTransactionField() {


        const selected =
            document.querySelector(
                'input[name="method"]:checked'
            );


        if (!selected) {

            return;

        }


        if (
            selected.value === "Cash"
        ) {


            transactionInput.required =
                false;


            transactionInput.value =
                "";


            transactionInput.placeholder =
                "Not required for cash payment";


            transactionRequired.style.display =
                "none";


            transactionHelp.textContent =
                "Payment will be made when you check in.";


        }
        else {


            transactionInput.required =
                true;


            transactionInput.placeholder =
                "Enter your transaction ID";


            transactionRequired.style.display =
                "inline";


            transactionHelp.textContent =
                "Send the required amount to your selected payment service and enter the Transaction ID above.";

        }

    }


    methods.forEach(
        function (method) {

            method.addEventListener(
                "change",
                updateTransactionField
            );

        }
    );


    updateTransactionField();


})();

</script>


<?php

require_once "includes/footer.php";

?>