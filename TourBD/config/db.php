<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "tour_booking_db";


$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);


if(!$conn){
    die("Connection failed: " . mysqli_connect_error());
}


mysqli_set_charset(
    $conn,
    "utf8mb4"
);



/* =========================
   ESCAPE OUTPUT
========================= */

function e($value)
{

    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );

}



/* =========================
   MONEY FORMAT
========================= */

function money($amount)
{

    return '৳' .
        number_format(
            (float)$amount,
            2
        );

}


?>