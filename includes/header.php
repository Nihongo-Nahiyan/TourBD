<?php

/* START SESSION */

if(session_status() === PHP_SESSION_NONE){

    session_start();

}


/* PAGE TITLE */

if(!isset($pageTitle)){

    $pageTitle = "TourBD";

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($pageTitle); ?> - TourBD
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>


<body>


<header class="site-header">

<div class="container">

<nav class="navbar">


    <!-- LOGO -->

    <a
        href="index.php"
        class="brand"
    >

        ✈ TourBD

    </a>



    <!-- NAVIGATION -->

    <div class="nav-links">


        <a href="index.php">

            Home

        </a>


        <a href="packages.php">

            Packages

        </a>



        <!-- LOGGED IN USER -->

        <?php if(isset($_SESSION['user_id'])){ ?>



            <!-- CUSTOMER -->

            <?php if($_SESSION['role'] == 'user'){ ?>


                <a href="my_bookings.php">

                    My Bookings

                </a>


            <?php } ?>



            <!-- ADMIN -->

            <?php if($_SESSION['role'] == 'admin'){ ?>


                <a href="admin/dashboard.php">

                    Admin

                </a>


            <?php } ?>



        <?php } else { ?>



            <!-- NOT LOGGED IN -->

            <a href="login.php">

                Login

            </a>



        <?php } ?>


    </div>



    <!-- RIGHT SIDE -->

    <div class="nav-actions">



        <?php if(isset($_SESSION['user_id'])){ ?>


            <span class="user-name">

                <?php

                echo htmlspecialchars(
                    $_SESSION['name']
                );

                ?>

            </span>



            <a
                href="logout.php"
                class="btn-primary"
            >

                Logout

            </a>



        <?php } else { ?>


            <a
                href="register.php"
                class="btn-primary"
            >

                Register

            </a>


        <?php } ?>


    </div>


</nav>

</div>

</header>