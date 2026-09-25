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



$message = "";



/* =========================================
   ADD DESTINATION
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'add'
){


    $destination_name =
        trim($_POST['destination_name']);


    $location =
        trim($_POST['location']);


    $description =
        trim($_POST['description']);


    $image_url =
        trim($_POST['image_url']);


    $active = 1;



    /* CHECK REQUIRED FIELDS */


    if($destination_name == ''){

        $message =
            "Destination name is required.";

    }


    else{


        /* CHECK DUPLICATE NAME */


        $check_sql = "

        SELECT destination_id

        FROM destinations

        WHERE destination_name = ?

        ";


        $check_stmt =
            mysqli_prepare(
                $conn,
                $check_sql
            );


        mysqli_stmt_bind_param(
            $check_stmt,
            "s",
            $destination_name
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
                "This destination already exists.";


        }


        else{


            $insert_sql = "

            INSERT INTO destinations
            (
                destination_name,
                location,
                description,
                image_url,
                active
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
                "ssssi",
                $destination_name,
                $location,
                $description,
                $image_url,
                $active
            );


            mysqli_stmt_execute(
                $insert_stmt
            );


            header(
                "Location: destination.php?added=1"
            );


            exit;


        }


    }


}



/* =========================================
   UPDATE DESTINATION
========================================= */


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['action'])
    &&
    $_POST['action'] == 'edit'
){


    $destination_id =
        intval(
            $_POST['destination_id']
        );


    $destination_name =
        trim(
            $_POST['destination_name']
        );


    $location =
        trim(
            $_POST['location']
        );


    $description =
        trim(
            $_POST['description']
        );


    $image_url =
        trim(
            $_POST['image_url']
        );



    if($destination_name == ''){


        $message =
            "Destination name is required.";


    }


    else{


        $update_sql = "

        UPDATE destinations

        SET

            destination_name = ?,

            location = ?,

            description = ?,

            image_url = ?

        WHERE destination_id = ?

        ";


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            );


        mysqli_stmt_bind_param(
            $update_stmt,
            "ssssi",
            $destination_name,
            $location,
            $description,
            $image_url,
            $destination_id
        );


        mysqli_stmt_execute(
            $update_stmt
        );


        header(
            "Location: destination.php?updated=1"
        );


        exit;


    }


}



/* =========================================
   ACTIVE / INACTIVE
========================================= */


if(
    isset($_GET['toggle'])
){


    $destination_id =
        intval(
            $_GET['toggle']
        );



    $toggle_sql = "

    UPDATE destinations

    SET active =

        CASE

            WHEN active = 1
            THEN 0

            ELSE 1

        END

    WHERE destination_id = ?

    ";


    $toggle_stmt =
        mysqli_prepare(
            $conn,
            $toggle_sql
        );


    mysqli_stmt_bind_param(
        $toggle_stmt,
        "i",
        $destination_id
    );


    mysqli_stmt_execute(
        $toggle_stmt
    );


    header(
        "Location: destination.php?status=1"
    );


    exit;


}



/* =========================================
   EDIT DESTINATION DATA
========================================= */


$edit_destination = NULL;


if(isset($_GET['edit'])){


    $edit_id =
        intval(
            $_GET['edit']
        );


    $edit_sql = "

    SELECT *

    FROM destinations

    WHERE destination_id = ?

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


    $edit_destination =
        mysqli_fetch_assoc(
            $edit_result
        );


}



/* =========================================
   SEARCH AND PAGINATION
========================================= */

// Read the search keyword and requested page.
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 2; // Change to 5 (or another number) when you want more rows.
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

// Count matching destinations, not just the destinations on this page.
if ($search !== '') {
    $search_value = '%' . $search . '%';
    $filtered_count_sql = "
        SELECT COUNT(*) AS matching_total
        FROM destinations
        WHERE destination_name LIKE ?
           OR location LIKE ?
           OR description LIKE ?
    ";
    $filtered_count_stmt = mysqli_prepare($conn, $filtered_count_sql);
    mysqli_stmt_bind_param(
        $filtered_count_stmt, 'sss',
        $search_value, $search_value, $search_value
    );
    mysqli_stmt_execute($filtered_count_stmt);
    $filtered_count_result = mysqli_stmt_get_result($filtered_count_stmt);
} else {
    $filtered_count_result = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS matching_total FROM destinations'
    );
}

$matching_destinations = (int) mysqli_fetch_assoc(
    $filtered_count_result
)['matching_total'];

$total_pages = max(1, (int) ceil($matching_destinations / $per_page));
$page = min($page, $total_pages); // Avoid an empty page after deleting records.
$offset = ($page - 1) * $per_page;

// Fetch only the rows for the current page.
if ($search !== '') {
    $destination_sql = "
        SELECT *
        FROM destinations
        WHERE destination_name LIKE ?
           OR location LIKE ?
           OR description LIKE ?
        ORDER BY destination_id DESC
        LIMIT ? OFFSET ?
    ";
    $destination_stmt = mysqli_prepare($conn, $destination_sql);
    mysqli_stmt_bind_param(
        $destination_stmt, 'sssii',
        $search_value, $search_value, $search_value,
        $per_page, $offset
    );
} else {
    $destination_sql = "
        SELECT *
        FROM destinations
        ORDER BY destination_id DESC
        LIMIT ? OFFSET ?
    ";
    $destination_stmt = mysqli_prepare($conn, $destination_sql);
    mysqli_stmt_bind_param(
        $destination_stmt, 'ii',
        $per_page, $offset
    );
}

mysqli_stmt_execute($destination_stmt);
$destination_result = mysqli_stmt_get_result($destination_stmt);

// Numbers shown below the table.
$first_shown = $matching_destinations > 0 ? $offset + 1 : 0;
$last_shown = min($offset + $per_page, $matching_destinations);


/* =========================================
   COUNT DESTINATIONS
========================================= */


$count_sql = "

SELECT

    COUNT(*) AS total,

    SUM(
        CASE
            WHEN active = 1
            THEN 1
            ELSE 0
        END
    ) AS active_total

FROM destinations

";


$count_result =
    mysqli_query(
        $conn,
        $count_sql
    );


$count_row =
    mysqli_fetch_assoc(
        $count_result
    );


$total_destinations =
    $count_row['total'];


$active_destinations =
    $count_row['active_total'];


if($active_destinations === NULL){

    $active_destinations = 0;

}


?>


<!DOCTYPE html>


<html>


<head>


<meta charset="UTF-8">


<title>

Destinations - TourBD Admin

</title>


<link
rel="stylesheet"
href="admin.css"
>

<!-- Destination search and pagination: page-specific styling -->
<style>
.destination-search-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 18px 0 22px;
}
.destination-search-form input[type="search"] {
    flex: 1 1 240px;
    max-width: 390px;
    padding: 12px 15px;
    border: 1px solid #d7dfde;
    border-radius: 9px;
    background: #fff;
    color: #202b2a;
    font: inherit;
}
.destination-search-form button {
    padding: 12px 20px;
    border: none;
    border-radius: 9px;
    color: white;
    background: #087e78;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}
.destination-search-form button:hover { background: #06655f; }
.destination-clear-search {
    text-decoration: none;
    color: #087e78;
    font-weight: 600;
}
.destination-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 22px;
}
.destination-page-info { color: #66746f; font-size: 14px; }
.destination-page-links { display: flex; gap: 7px; flex-wrap: wrap; }
.destination-page-links a,
.destination-page-links span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    padding: 8px 12px;
    border: 1px solid #d7dfde;
    border-radius: 7px;
    text-decoration: none;
    color: #087e78;
    background: #fff;
    font-weight: 600;
}
.destination-page-links a:hover,
.destination-page-links .current {
    color: #fff;
    background: #087e78;
    border-color: #087e78;
}
.destination-page-links .disabled {
    opacity: .4;
    cursor: default;
    color: #66746f;
}
</style>



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


        <a
        href="destination.php"
        class="active"
        >

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
     MAIN
========================================= -->


<main class="admin-main">



<!-- PAGE TITLE -->


<section class="admin-page-title">


    <div>


        <h1>

            Destinations

        </h1>


        <p>

            Manage travel destinations available in TourBD.

        </p>


    </div>



    <a
    href="#destination-form"
    class="add-admin-button"
    >

        + Add Destination

    </a>


</section>



<!-- =========================================
     SUMMARY CARDS
========================================= -->


<section class="destination-summary">


    <div class="destination-summary-card">


        <span>

            🗺️

        </span>


        <div>


            <p>

                Total Destinations

            </p>


            <h2>

                <?php
                echo $total_destinations;
                ?>

            </h2>


        </div>


    </div>



    <div class="destination-summary-card">


        <span>

            ✅

        </span>


        <div>


            <p>

                Active Destinations

            </p>


            <h2>

                <?php
                echo $active_destinations;
                ?>

            </h2>


        </div>


    </div>


</section>



<!-- =========================================
     MESSAGE
========================================= -->


<?php if(isset($_GET['added'])){ ?>


<div class="admin-message success">

    Destination added successfully.

</div>


<?php } ?>


<?php if(isset($_GET['updated'])){ ?>


<div class="admin-message success">

    Destination updated successfully.

</div>


<?php } ?>


<?php if(isset($_GET['status'])){ ?>


<div class="admin-message success">

    Destination status updated successfully.

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
     DESTINATION TABLE
========================================= -->


<section class="admin-table-card" id="destination-list">


    <div class="table-card-heading">


        <div>


            <h2>

                Destination List

            </h2>


            <p>

                View and manage all destinations.

            </p>


        </div>


    </div>

    <!-- SEARCH BAR -->
    <form method="GET" action="destination.php#destination-list"
          class="destination-search-form" role="search">
        <input type="search" name="search"
               value="<?php echo e($search); ?>"
               placeholder="Search name, location or description..."
               aria-label="Search destinations">
        <button type="submit">Search</button>
        <?php if ($search !== '') { ?>
            <a href="destination.php#destination-list"
               class="destination-clear-search">Clear</a>
        <?php } ?>
    </form>

    <div class="table-wrapper">


        <table>


            <thead>


                <tr>


                    <th>

                        ID

                    </th>


                    <th>

                        Destination

                    </th>


                    <th>

                        Location

                    </th>


                    <th>

                        Image

                    </th>


                    <th>

                        Status

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
                    $destination_result
                ) > 0
            ){


                while(
                    $destination =
                    mysqli_fetch_assoc(
                        $destination_result
                    )
                ){


            ?>


                <tr>


                    <td class="booking-number">


                        #<?php
                        echo $destination[
                            'destination_id'
                        ];
                        ?>


                    </td>



                    <td>


                        <strong>


                            <?php
                            echo e(
                                $destination[
                                    'destination_name'
                                ]
                            );
                            ?>


                        </strong>


                        <p class="table-description">


                            <?php
                            echo e(
                                $destination[
                                    'description'
                                ]
                            );
                            ?>


                        </p>


                    </td>



                    <td>


                        <?php
                        echo e(
                            $destination[
                                'location'
                            ]
                        );
                        ?>


                    </td>



                    <td>


                        <?php


                        if(
                            $destination[
                                'image_url'
                            ] != ''
                        ){


                        ?>


                            <img

                            src="../assets/images/<?php
                            echo e(
                                $destination[
                                    'image_url'
                                ]
                            );
                            ?>"

                            class="admin-destination-image"

                            alt="Destination image"

                            >


                        <?php


                        }

                        else{


                            echo "No image";


                        }


                        ?>


                    </td>



                    <td>


                        <?php


                        if(
                            $destination['active']
                            == 1
                        ){


                        ?>


                            <span class="
                            destination-status
                            active-status
                            ">

                                Active

                            </span>


                        <?php


                        }

                        else{


                        ?>


                            <span class="
                            destination-status
                            inactive-status
                            ">

                                Inactive

                            </span>


                        <?php


                        }


                        ?>


                    </td>



                    <td>


                        <div class="action-buttons">


                            <a

                            href="destination.php?edit=<?php
                            echo $destination[
                                'destination_id'
                            ];
                            ?>#destination-form"

                            class="edit-button"

                            >

                                Edit

                            </a>



                            <a

                            href="destination.php?toggle=<?php
                            echo $destination[
                                'destination_id'
                            ];
                            ?>"

                            class="status-button"

                            >


                                <?php


                                if(
                                    $destination[
                                        'active'
                                    ] == 1
                                ){


                                    echo "Deactivate";


                                }

                                else{


                                    echo "Activate";


                                }


                                ?>


                            </a>


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

                        No destinations found.

                    </td>


                </tr>


            <?php


            }


            ?>


            </tbody>


        </table>

    </div>

    <!-- PAGINATION: keep the search term in every page link -->
    <div class="destination-pagination">
        <span class="destination-page-info">
            Showing <?php echo $first_shown; ?>–<?php echo $last_shown; ?>
            of <?php echo $matching_destinations; ?> destinations
        </span>

        <?php if ($total_pages > 1) { ?>
            <nav class="destination-page-links" aria-label="Destination pages">
                <?php if ($page > 1) { ?>
                    <a href="destination.php?<?php echo e(http_build_query([
                        'search' => $search, 'page' => $page - 1
                    ])); ?>#destination-list">Previous</a>
                <?php } else { ?>
                    <span class="disabled">Previous</span>
                <?php } ?>

                <?php for ($n = 1; $n <= $total_pages; $n++) { ?>
                    <a href="destination.php?<?php echo e(http_build_query([
                        'search' => $search, 'page' => $n
                    ])); ?>#destination-list"
                       class="<?php echo $n === $page ? 'current' : ''; ?>"
                       <?php if ($n === $page) { ?>aria-current="page"<?php } ?>>
                        <?php echo $n; ?>
                    </a>
                <?php } ?>

                <?php if ($page < $total_pages) { ?>
                    <a href="destination.php?<?php echo e(http_build_query([
                        'search' => $search, 'page' => $page + 1
                    ])); ?>#destination-list">Next</a>
                <?php } else { ?>
                    <span class="disabled">Next</span>
                <?php } ?>
            </nav>
        <?php } ?>
    </div>

</section>



<!-- =========================================
     ADD / EDIT FORM
========================================= -->


<section
class="destination-form-card"
id="destination-form"
>


    <?php


    if($edit_destination){


    ?>


        <h2>

            Edit Destination

        </h2>


        <p>

            Update the selected destination information.

        </p>


    <?php


    }

    else{


    ?>


        <h2>

            Add New Destination

        </h2>


        <p>

            Add another travel destination to TourBD.

        </p>


    <?php


    }


    ?>



    <form
    method="POST"
    >



        <?php


        if($edit_destination){


        ?>


            <input

            type="hidden"

            name="action"

            value="edit"

            >


            <input

            type="hidden"

            name="destination_id"

            value="<?php
            echo $edit_destination[
                'destination_id'
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



        <div class="admin-form-grid">



            <div class="admin-form-group">


                <label>

                    Destination Name

                </label>


                <input

                type="text"

                name="destination_name"

                value="<?php

                if($edit_destination){

                    echo e(
                        $edit_destination[
                            'destination_name'
                        ]
                    );

                }

                ?>"

                placeholder="Example: Cox's Bazar"

                required

                >


            </div>



            <div class="admin-form-group">


                <label>

                    Location

                </label>


                <input

                type="text"

                name="location"

                value="<?php

                if($edit_destination){

                    echo e(
                        $edit_destination[
                            'location'
                        ]
                    );

                }

                ?>"

                placeholder="Example: Chittagong"

                >


            </div>


        </div>



        <div class="admin-form-group">


            <label>

                Description

            </label>


            <textarea

            name="description"

            rows="4"

            placeholder="Write a short destination description..."

            ><?php

            if($edit_destination){

                echo e(
                    $edit_destination[
                        'description'
                    ]
                );

            }

            ?></textarea>


        </div>



        <div class="admin-form-group">


            <label>

                Image Filename

            </label>


            <input

            type="text"

            name="image_url"

            value="<?php

            if($edit_destination){

                echo e(
                    $edit_destination[
                        'image_url'
                    ]
                );

            }

            ?>"

            placeholder="Example: cox.jpg"

            >


            <small>

                The image must exist inside
                assets/images/.

            </small>


        </div>



        <div class="form-buttons">


            <button
            type="submit"
            class="save-destination-button"
            >


                <?php


                if($edit_destination){

                    echo "Update Destination";

                }

                else{

                    echo "Add Destination";

                }


                ?>


            </button>



            <?php


            if($edit_destination){


            ?>


                <a
                href="destination.php"
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