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
   ADD TRANSPORT
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'add'
){


    $type =
        trim(
            $_POST['type']
        );


    $route =
        trim(
            $_POST['route']
        );


    $provider =
        trim(
            $_POST['provider']
        );


    $price_per_person =
        floatval(
            $_POST['price_per_person']
        );


    $departure =
        trim(
            $_POST['departure']
        );



    /* VALIDATION */


    if($type == ''){


        $message =
            "Transport type is required.";


    }


    elseif($provider == ''){


        $message =
            "Transport operator is required.";


    }


    elseif($route == ''){


        $message =
            "Route is required.";


    }


    elseif($price_per_person < 0){


        $message =
            "Price cannot be negative.";


    }


    else{


        $insert_sql = "

        INSERT INTO transport
        (
            type,
            route,
            provider,
            price_per_person,
            departure
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


        mysqli_stmt_bind_param(
            $insert_stmt,
            "sssds",
            $type,
            $route,
            $provider,
            $price_per_person,
            $departure
        );


        mysqli_stmt_execute(
            $insert_stmt
        );


        header(
            "Location: transport.php?added=1"
        );


        exit;


    }


}



/* =========================================
   EDIT TRANSPORT
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'edit'
){


    $transport_id =
        intval(
            $_POST['transport_id']
        );


    $type =
        trim(
            $_POST['type']
        );


    $route =
        trim(
            $_POST['route']
        );


    $provider =
        trim(
            $_POST['provider']
        );


    $price_per_person =
        floatval(
            $_POST['price_per_person']
        );


    $departure =
        trim(
            $_POST['departure']
        );



    if($type == ''){


        $message =
            "Transport type is required.";


    }


    elseif($provider == ''){


        $message =
            "Transport operator is required.";


    }


    elseif($route == ''){


        $message =
            "Route is required.";


    }


    else{


        $update_sql = "

        UPDATE transport

        SET

            type = ?,

            route = ?,

            provider = ?,

            price_per_person = ?,

            departure = ?

        WHERE transport_id = ?

        ";


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );


        mysqli_stmt_bind_param(
            $update_stmt,
            "sssdsi",
            $type,
            $route,
            $provider,
            $price_per_person,
            $departure,
            $transport_id
        );


        mysqli_stmt_execute(
            $update_stmt
        );


        header(
            "Location: transport.php?updated=1"
        );


        exit;


    }


}



/* =========================================
   DELETE TRANSPORT
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'delete'
){


    $transport_id =
        intval(
            $_POST['transport_id']
        );



    /* CHECK IF TRANSPORT IS USED */


    $check_sql = "

    SELECT COUNT(*) AS total

    FROM tour_packages

    WHERE transport_id = ?

    ";


    $check_stmt =
        mysqli_prepare(
            $conn,
            $check_sql
        );


    mysqli_stmt_bind_param(
        $check_stmt,
        "i",
        $transport_id
    );


    mysqli_stmt_execute(
        $check_stmt
    );


    $check_result =
        mysqli_stmt_get_result(
            $check_stmt
        );


    $check_row =
        mysqli_fetch_assoc(
            $check_result
        );



    if(
        $check_row['total'] > 0
    ){


        header(
            "Location: transport.php?cannotdelete=1"
        );


        exit;


    }


    else{


        $delete_sql = "

        DELETE FROM transport

        WHERE transport_id = ?

        ";


        $delete_stmt =
            mysqli_prepare(
                $conn,
                $delete_sql
            );


        mysqli_stmt_bind_param(
            $delete_stmt,
            "i",
            $transport_id
        );


        mysqli_stmt_execute(
            $delete_stmt
        );


        header(
            "Location: transport.php?deleted=1"
        );


        exit;


    }


}



/* =========================================
   GET TRANSPORT FOR EDIT
========================================= */


$edit_transport = NULL;


if(isset($_GET['edit'])){


    $edit_id =
        intval(
            $_GET['edit']
        );


    $edit_sql = "

    SELECT *

    FROM transport

    WHERE transport_id = ?

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


    $edit_transport =
        mysqli_fetch_assoc(
            $edit_result
        );


}



/* =========================================
   GET ALL TRANSPORT
========================================= */


$transport_sql = "

SELECT *

FROM transport

ORDER BY transport_id

";


$transport_result =
    mysqli_query(
        $conn,
        $transport_sql
    );


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Transport - TourBD Admin

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


        <a
        href="transport.php"
        class="active"
        >

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


<section class="transport-page-heading">


    <div>


        <h1>

            Manage Transport

        </h1>


        <p>

            TourBD Admin ·

            <?php
            echo $current_date;
            ?>

        </p>


    </div>


</section>



<!-- ADD TRANSPORT BUTTON -->


<div class="transport-add-row">


    <a
    href="#transport-form"
    class="transport-add-button"
    >

        + Add Transport

    </a>


</div>



<!-- =========================================
     MESSAGES
========================================= -->


<?php if(isset($_GET['added'])){ ?>


<div class="admin-message success">

    Transport added successfully.

</div>


<?php } ?>



<?php if(isset($_GET['updated'])){ ?>


<div class="admin-message success">

    Transport updated successfully.

</div>


<?php } ?>



<?php if(isset($_GET['deleted'])){ ?>


<div class="admin-message success">

    Transport deleted successfully.

</div>


<?php } ?>



<?php if(isset($_GET['cannotdelete'])){ ?>


<div class="admin-message error">

    This transport option is currently used
    by a tour package, so it cannot be deleted.

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
     TRANSPORT TABLE
========================================= -->


<section class="transport-table-card">


<div class="table-wrapper">


<table class="transport-admin-table">


<thead>


<tr>


    <th>

        #

    </th>


    <th>

        Type

    </th>


    <th>

        Route

    </th>


    <th>

        Operator

    </th>


    <th>

        Price/Person

    </th>


    <th>

        Departure

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
        $transport_result
    ) > 0
){


    while(
        $transport =
        mysqli_fetch_assoc(
            $transport_result
        )
    ){


?>


<tr>



<!-- ID -->


<td class="transport-id">


    <?php
    echo $transport[
        'transport_id'
    ];
    ?>


</td>



<!-- TYPE -->


<td>


    <span class="
    transport-type-badge
    ">


        <?php
        echo e(
            $transport[
                'type'
            ]
        );
        ?>


    </span>


</td>



<!-- ROUTE -->


<td class="transport-route">


    <?php
    echo e(
        $transport[
            'route'
        ]
    );
    ?>


</td>



<!-- PROVIDER -->


<td>


    <?php
    echo e(
        $transport[
            'provider'
        ]
    );
    ?>


</td>



<!-- PRICE -->


<td class="transport-price">


    ৳<?php

    echo number_format(
        $transport[
            'price_per_person'
        ],
        0
    );

    ?>


</td>



<!-- DEPARTURE -->


<td class="transport-departure">


    <?php


    if(
        $transport[
            'departure'
        ] != ''
    ){


        echo e(
            $transport[
                'departure'
            ]
        );


    }

    else{


        echo "-";


    }


    ?>


</td>



<!-- ACTIONS -->


<td>


<div class="transport-action-buttons">



<a

href="transport.php?edit=<?php
echo $transport[
    'transport_id'
];
?>#transport-form"

class="transport-edit-button"

>

Edit

</a>



<form

method="POST"

onsubmit="
return confirm(
'Are you sure you want to delete this transport option?'
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

name="transport_id"

value="<?php
echo $transport[
    'transport_id'
];
?>"

>


<button

type="submit"

class="transport-delete-button"

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

No transport options found.

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
     ADD / EDIT TRANSPORT FORM
========================================= -->


<section
class="transport-form-card"
id="transport-form"
>


<?php


if($edit_transport){


?>


<h2>

    Edit Transport

</h2>


<p>

    Update the selected transport information.

</p>


<?php


}

else{


?>


<h2>

    Add New Transport

</h2>


<p>

    Add a new transport option for TourBD packages.

</p>


<?php


}


?>



<form method="POST">



<?php


if($edit_transport){


?>


<input

type="hidden"

name="action"

value="edit"

>


<input

type="hidden"

name="transport_id"

value="<?php
echo $edit_transport[
    'transport_id'
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


<div class="transport-form-grid">



<div class="admin-form-group">


<label>

    Transport Type

</label>


<input

type="text"

name="type"

value="<?php

if($edit_transport){

    echo e(
        $edit_transport[
            'type'
        ]
    );

}

?>"

placeholder="Example: AC Bus"

required

>


</div>



<div class="admin-form-group">


<label>

    Operator

</label>


<input

type="text"

name="provider"

value="<?php

if($edit_transport){

    echo e(
        $edit_transport[
            'provider'
        ]
    );

}

?>"

placeholder="Example: Green Line Paribahan"

required

>


</div>



<div class="admin-form-group">


<label>

    Price Per Person

</label>


<input

type="number"

name="price_per_person"

min="0"

step="0.01"

value="<?php

if($edit_transport){

    echo $edit_transport[
        'price_per_person'
    ];

}

?>"

placeholder="Example: 1100"

required

>


</div>


</div>



<!-- SECOND ROW -->


<div class="transport-form-grid-two">



<div class="admin-form-group">


<label>

    Route

</label>


<input

type="text"

name="route"

value="<?php

if($edit_transport){

    echo e(
        $edit_transport[
            'route'
        ]
    );

}

?>"

placeholder="Example: Dhaka → Cox's Bazar"

required

>


</div>



<div class="admin-form-group">


<label>

    Departure

</label>


<input

type="text"

name="departure"

value="<?php

if($edit_transport){

    echo e(
        $edit_transport[
            'departure'
        ]
    );

}

?>"

placeholder="Example: 10:00 PM or Flexible"

>


<small>

    You can enter a time or write Flexible.

</small>


</div>


</div>



<!-- BUTTONS -->


<div class="form-buttons">


<button

type="submit"

class="save-transport-button"

>


<?php


if($edit_transport){

    echo "Update Transport";

}

else{

    echo "Add Transport";

}


?>


</button>



<?php


if($edit_transport){


?>


<a
href="transport.php"
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