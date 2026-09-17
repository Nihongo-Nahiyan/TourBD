<?php

if (!isset($pageTitle)) {
    $pageTitle = 'TourBD';
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
        <?= htmlspecialchars($pageTitle) ?> - TourBD
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


            <a
                href="index.php"
                class="brand"
            >

                ✈ TourBD

            </a>


            <div class="nav-links">

                <a href="index.php">
                    Home
                </a>

                <a href="packages.php">
                    Packages
                </a>

                <a href="login.php">
                    Login
                </a>

            </div>


            <div class="nav-actions">

                <a
                    href="register.php"
                    class="btn-primary"
                >

                    Register

                </a>

            </div>


        </nav>

    </div>

</header>