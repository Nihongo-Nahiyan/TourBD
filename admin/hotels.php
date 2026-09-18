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



$message = "";



/* =========================================
   ADD HOTEL
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'add'
){


    $destination_id =
        intval(
            $_POST['destination_id']
        );


    $hotel_name =
        trim(
            $_POST['hotel_name']
        );


    $contact =
        trim(
            $_POST['contact']
        );


    $stars =
        intval(
            $_POST['stars']
        );


    $price_per_night =
        floatval(
            $_POST['price_per_night']
        );


    $amenities =
        trim(
            $_POST['amenities']
        );



    /* VALIDATION */


    if($hotel_name == ''){


        $message =
            "Hotel name is required.";


    }


    elseif($destination_id <= 0){


        $message =
            "Please select a destination.";


    }


    elseif(
        $stars < 1
        ||
        $stars > 5
    ){


        $message =
            "Stars must be between 1 and 5.";


    }


    elseif($price_per_night < 0){


        $message =
            "Price cannot be negative.";


    }


    else{


        /* CHECK DUPLICATE HOTEL */


        $check_sql = "

        SELECT hotel_id

        FROM hotels

        WHERE hotel_name = ?

        AND destination_id = ?

        ";


        $check_stmt =
            mysqli_prepare(
                $conn,
                $check_sql
            );


        mysqli_stmt_bind_param(
            $check_stmt,
            "si",
            $hotel_name,
            $destination_id
        );


        mysqli_stmt_execute(
            $check_stmt
        );


        $check_result =
            mysqli_stmt_get_result(
                $check_stmt
            );



        if(
            mysqli_num_rows(
                $check_result
            ) > 0
        ){


            $message =
                "This hotel already exists for this destination.";


        }


        else{


            $insert_sql = "

            INSERT INTO hotels
            (
                destination_id,
                hotel_name,
                contact,
                stars,
                price_per_night,
                amenities
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


            $insert_stmt =
                mysqli_prepare(
                    $conn,
                    $insert_sql
                );


            mysqli_stmt_bind_param(
                $insert_stmt,
                "issids",
                $destination_id,
                $hotel_name,
                $contact,
                $stars,
                $price_per_night,
                $amenities
            );


            mysqli_stmt_execute(
                $insert_stmt
            );


            header(
                "Location: hotels.php?added=1"
            );


            exit;


        }


    }


}



/* =========================================
   EDIT HOTEL
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'edit'
){


    $hotel_id =
        intval(
            $_POST['hotel_id']
        );


    $destination_id =
        intval(
            $_POST['destination_id']
        );


    $hotel_name =
        trim(
            $_POST['hotel_name']
        );


    $contact =
        trim(
            $_POST['contact']
        );


    $stars =
        intval(
            $_POST['stars']
        );


    $price_per_night =
        floatval(
            $_POST['price_per_night']
        );


    $amenities =
        trim(
            $_POST['amenities']
        );



    if($hotel_name == ''){


        $message =
            "Hotel name is required.";


    }


    elseif(
        $stars < 1
        ||
        $stars > 5
    ){


        $message =
            "Stars must be between 1 and 5.";


    }


    else{


        $update_sql = "

        UPDATE hotels

        SET

            destination_id = ?,

            hotel_name = ?,

            contact = ?,

            stars = ?,

            price_per_night = ?,

            amenities = ?

        WHERE hotel_id = ?

        ";


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );


        mysqli_stmt_bind_param(
            $update_stmt,
            "issidsi",
            $destination_id,
            $hotel_name,
            $contact,
            $stars,
            $price_per_night,
            $amenities,
            $hotel_id
        );


        mysqli_stmt_execute(
            $update_stmt
        );


        header(
            "Location: hotels.php?updated=1"
        );


        exit;


    }


}



/* =========================================
   DELETE HOTEL
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'delete'
){


    $hotel_id =
        intval(
            $_POST['hotel_id']
        );



    /* CHECK IF HOTEL IS USED BY PACKAGE */


    $check_package_sql = "

    SELECT COUNT(*) AS total

    FROM tour_packages

    WHERE hotel_id = ?

    ";


    $check_package_stmt =
        mysqli_prepare(
            $conn,
            $check_package_sql
        );


    mysqli_stmt_bind_param(
        $check_package_stmt,
        "i",
        $hotel_id
    );


    mysqli_stmt_execute(
        $check_package_stmt
    );


    $check_package_result =
        mysqli_stmt_get_result(
            $check_package_stmt
        );


    $check_package_row =
        mysqli_fetch_assoc(
            $check_package_result
        );



    if(
        $check_package_row['total']
        > 0
    ){


        header(
            "Location: hotels.php?cannotdelete=1"
        );


        exit;


    }


    else{


        $delete_sql = "

        DELETE FROM hotels

        WHERE hotel_id = ?

        ";


        $delete_stmt =
            mysqli_prepare(
                $conn,
                $delete_sql
            );


        mysqli_stmt_bind_param(
            $delete_stmt,
            "i",
            $hotel_id
        );


        mysqli_stmt_execute(
            $delete_stmt
        );


        header(
            "Location: hotels.php?deleted=1"
        );


        exit;


    }


}



/* =========================================
   GET HOTEL FOR EDIT
========================================= */


$edit_hotel = NULL;


if(isset($_GET['edit'])){


    $edit_id =
        intval(
            $_GET['edit']
        );


    $edit_sql = "

    SELECT *

    FROM hotels

    WHERE hotel_id = ?

    ";


    $edit_stmt =
        mysqli_prepare(
            $conn,
            $edit_sql
        );


    mysqli_stmt_bind_param(
        $edit_stmt,
        "i",
        $edit_id
    );


    mysqli_stmt_execute(
        $edit_stmt
    );


    $edit_result =
        mysqli_stmt_get_result(
            $edit_stmt
        );


    $edit_hotel =
        mysqli_fetch_assoc(
            $edit_result
        );


}



/* =========================================
   GET DESTINATIONS
========================================= */


$destination_sql = "

SELECT *

FROM destinations

WHERE active = 1

ORDER BY destination_name

";


$destination_result =
    mysqli_query(
        $conn,
        $destination_sql
    );



/* =========================================
   GET ALL HOTELS
========================================= */


$hotel_sql = "

SELECT

    hotels.*,

    destinations.destination_name

FROM hotels


JOIN destinations

ON hotels.destination_id =
   destinations.destination_id


ORDER BY hotels.hotel_id

";


$hotel_result =
    mysqli_query(
        $conn,
        $hotel_sql
    );


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Hotels - TourBD Admin

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


        <a
        href="hotels.php"
        class="active"
        >

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



<!-- PAGE TITLE -->


<section class="hotel-page-heading">


    <div>


        <h1>

            Manage Hotels

        </h1>


        <p>

            TourBD Admin ·

            <?php
            echo $current_date;
            ?>

        </p>


    </div>


</section>



<!-- ADD HOTEL BUTTON -->


<div class="hotel-add-row">


    <a
    href="#hotel-form"
    class="hotel-add-button"
    >

        + Add Hotel

    </a>


</div>



<!-- =========================================
     MESSAGES
========================================= -->


<?php if(isset($_GET['added'])){ ?>


<div class="admin-message success">

    Hotel added successfully.

</div>


<?php } ?>



<?php if(isset($_GET['updated'])){ ?>


<div class="admin-message success">

    Hotel updated successfully.

</div>


<?php } ?>



<?php if(isset($_GET['deleted'])){ ?>


<div class="admin-message success">

    Hotel deleted successfully.

</div>


<?php } ?>



<?php if(isset($_GET['cannotdelete'])){ ?>


<div class="admin-message error">

    This hotel is currently used by a tour package,
    so it cannot be deleted.

</div>


<?php } ?>



<?php if($message != ''){ ?>


<div class="admin-message error">


    <?php
    echo e($message);
    ?>


</div>


<?php } ?>



<!-- =========================================
     HOTEL TABLE
========================================= -->


<section class="hotel-table-card">


<div class="table-wrapper">


<table class="hotel-admin-table">


<thead>


<tr>


    <th>

        #

    </th>


    <th>

        Hotel Name

    </th>


    <th>

        Destination

    </th>


    <th>

        Stars

    </th>


    <th>

        Price/Night

    </th>


    <th>

        Amenities

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
        $hotel_result
    ) > 0
){


    while(
        $hotel =
        mysqli_fetch_assoc(
            $hotel_result
        )
    ){


?>


<tr>



<!-- ID -->


<td class="hotel-id">


    <?php
    echo $hotel[
        'hotel_id'
    ];
    ?>


</td>



<!-- HOTEL NAME -->


<td class="hotel-name">


    <?php
    echo e(
        $hotel[
            'hotel_name'
        ]
    );
    ?>


</td>



<!-- DESTINATION -->


<td>


    <?php
    echo e(
        $hotel[
            'destination_name'
        ]
    );
    ?>


</td>



<!-- STARS -->


<td class="hotel-stars">


    <?php


    for(
        $i = 1;
        $i <= $hotel['stars'];
        $i++
    ){


        echo "★";


    }


    ?>


</td>



<!-- PRICE -->


<td class="hotel-price">


    ৳<?php

    echo number_format(
        $hotel[
            'price_per_night'
        ],
        0
    );

    ?>


</td>



<!-- AMENITIES -->


<td class="hotel-amenities">


    <?php
    echo e(
        $hotel[
            'amenities'
        ]
    );
    ?>


</td>



<!-- ACTIONS -->


<td>


<div class="hotel-action-buttons">



<a

href="hotels.php?edit=<?php
echo $hotel[
    'hotel_id'
];
?>#hotel-form"

class="hotel-edit-button"

>

Edit

</a>



<form

method="POST"

onsubmit="
return confirm(
'Are you sure you want to delete this hotel?'
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

name="hotel_id"

value="<?php
echo $hotel[
    'hotel_id'
];
?>"

>


<button

type="submit"

class="hotel-delete-button"

>

Delete

</button>


</form>


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
colspan="7"
class="no-data"
>

No hotels found.

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
     ADD / EDIT HOTEL FORM
========================================= -->


<section
class="hotel-form-card"
id="hotel-form"
>


<?php


if($edit_hotel){


?>


<h2>

    Edit Hotel

</h2>


<p>

    Update hotel information.

</p>


<?php


}

else{


?>


<h2>

    Add New Hotel

</h2>


<p>

    Add another hotel to a TourBD destination.

</p>


<?php


}


?>



<form method="POST">



<?php


if($edit_hotel){


?>


<input

type="hidden"

name="action"

value="edit"

>


<input

type="hidden"

name="hotel_id"

value="<?php
echo $edit_hotel[
    'hotel_id'
];
?>"

>


<?php


}

else{


?>


<input

type="hidden"

name="action"

value="add"

>


<?php


}


?>



<!-- FIRST ROW -->


<div class="hotel-form-grid">



<div class="admin-form-group">


<label>

    Hotel Name

</label>


<input

type="text"

name="hotel_name"

value="<?php

if($edit_hotel){

    echo e(
        $edit_hotel[
            'hotel_name'
        ]
    );

}

?>"

placeholder="Example: Sea Pearl Hotel"

required

>


</div>



<div class="admin-form-group">


<label>

    Destination

</label>


<select
name="destination_id"
required
>


<option value="">

    Select Destination

</option>


<?php


mysqli_data_seek(
    $destination_result,
    0
);


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
    $edit_hotel
    &&
    $edit_hotel[
        'destination_id'
    ]
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


</div>



<div class="admin-form-group">


<label>

    Contact

</label>


<input

type="text"

name="contact"

value="<?php

if($edit_hotel){

    echo e(
        $edit_hotel[
            'contact'
        ]
    );

}

?>"

placeholder="Example: 01800000000"

>


</div>


</div>



<!-- SECOND ROW -->


<div class="hotel-form-grid">



<div class="admin-form-group">


<label>

    Stars

</label>


<select
name="stars"
required
>


<?php


for($star = 1; $star <= 5; $star++){


?>


<option

value="<?php
echo $star;
?>"

<?php


if(
    $edit_hotel
    &&
    $edit_hotel['stars']
    == $star
){

    echo "selected";

}

elseif(
    !$edit_hotel
    &&
    $star == 3
){

    echo "selected";

}


?>

>


<?php
echo $star;
?>

Star


</option>


<?php


}


?>


</select>


</div>



<div class="admin-form-group">


<label>

    Price Per Night

</label>


<input

type="number"

name="price_per_night"

min="0"

step="0.01"

value="<?php

if($edit_hotel){

    echo $edit_hotel[
        'price_per_night'
    ];

}

?>"

placeholder="Example: 6500"

required

>


</div>


</div>



<!-- AMENITIES -->


<div class="admin-form-group">


<label>

    Amenities

</label>


<input

type="text"

name="amenities"

value="<?php

if($edit_hotel){

    echo e(
        $edit_hotel[
            'amenities'
        ]
    );

}

?>"

placeholder="Example: Pool, Spa, Sea View, WiFi"

>


<small>

    Separate amenities using commas.

</small>


</div>



<!-- BUTTONS -->


<div class="form-buttons">


<button

type="submit"

class="save-hotel-button"

>


<?php


if($edit_hotel){

    echo "Update Hotel";

}

else{

    echo "Add Hotel";

}


?>


</button>



<?php


if($edit_hotel){


?>


<a
href="hotels.php"
class="cancel-edit-button"
>

Cancel

</a>


<?php


}


?>


</div>


</form>


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