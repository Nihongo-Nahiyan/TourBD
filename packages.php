<?php

require_once "config/db.php";

/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   PAGE TITLE
========================================================= */

$pageTitle = "Packages";


/* =========================================================
   FILTER VALUES
========================================================= */

$search = trim($_GET['search'] ?? '');

$destinationId = (int)($_GET['destination_id'] ?? 0);

$maxPrice = (float)($_GET['max_price'] ?? 20000);

$sort = $_GET['sort'] ?? 'default';

$category = $_GET['category'] ?? 'All';


/* =========================================================
   PAGINATION
========================================================= */

$page = max(1, (int)($_GET['page'] ?? 1));

$perPage = 10;

$offset = ($page - 1) * $perPage;


/* =========================================================
   GET DESTINATIONS
========================================================= */

$destination_sql = "
    SELECT destination_id, destination_name
    FROM destinations
    WHERE active = 1
    ORDER BY destination_name
";

$destinations = mysqli_query(
    $conn,
    $destination_sql
);


/* =========================================================
   GET PACKAGES
========================================================= */

$sql = "
    SELECT *
    FROM available_packages
    WHERE 1=1
";

$params = [];
$types = "";


/* =========================================================
   SEARCH FILTER
========================================================= */

if ($search !== "") {

    $sql .= "
        AND (
            package_name LIKE ?
            OR destination_name LIKE ?
        )
    ";

    $like = "%" . $search . "%";

    $params[] = $like;
    $params[] = $like;

    $types .= "ss";
}


/* =========================================================
   DESTINATION FILTER
========================================================= */

if ($destinationId > 0) {

    $sql .= "
        AND destination_id = ?
    ";

    $params[] = $destinationId;

    $types .= "i";
}


/* =========================================================
   CATEGORY FILTER
========================================================= */

if ($category === "Beach") {

    $sql .= "
        AND (
            LOWER(destination_name) LIKE '%cox%'
            OR LOWER(destination_name) LIKE '%beach%'
        )
    ";

}
elseif ($category === "Hill") {

    $sql .= "
        AND (
            LOWER(destination_name) LIKE '%sajek%'
            OR LOWER(destination_name) LIKE '%rangamati%'
            OR LOWER(destination_name) LIKE '%bandarban%'
            OR LOWER(destination_name) LIKE '%hill%'
        )
    ";

}
elseif ($category === "Nature") {

    $sql .= "
        AND NOT (
            LOWER(destination_name) LIKE '%cox%'
            OR LOWER(destination_name) LIKE '%beach%'
            OR LOWER(destination_name) LIKE '%sajek%'
            OR LOWER(destination_name) LIKE '%rangamati%'
            OR LOWER(destination_name) LIKE '%bandarban%'
            OR LOWER(destination_name) LIKE '%hill%'
        )
    ";

}
elseif ($category === "Adventure") {

    $sql .= "
        AND (
            LOWER(package_name) LIKE '%adventure%'
            OR LOWER(package_name) LIKE '%trek%'
            OR LOWER(package_name) LIKE '%tour%'
            OR LOWER(description) LIKE '%adventure%'
            OR LOWER(description) LIKE '%trek%'
        )
    ";
}


/* =========================================================
   MAX PRICE
========================================================= */

if ($maxPrice > 0) {

    $sql .= "
        AND price <= ?
    ";

    $params[] = $maxPrice;

    $types .= "d";
}


/* =========================================================
   PAGINATION COUNT
========================================================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM (
        $sql
    ) AS filtered_packages
";

$countStmt = mysqli_prepare(
    $conn,
    $countSql
);

if (!$countStmt) {

    die(
        "Count query preparation failed: "
        . mysqli_error($conn)
    );

}

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $countStmt,
        $types,
        ...$params
    );

}

mysqli_stmt_execute($countStmt);

$countResult =
    mysqli_stmt_get_result(
        $countStmt
    );

$countRow =
    mysqli_fetch_assoc(
        $countResult
    );

$totalPackages =
    (int)$countRow['total'];

$totalPages =
    max(
        1,
        (int)ceil(
            $totalPackages / $perPage
        )
    );

if ($page > $totalPages) {

    $page = $totalPages;

    $offset =
        ($page - 1) * $perPage;
}


/* =========================================================
   SORTING
========================================================= */

switch ($sort) {

    case "price_low":

        $sql .= "
            ORDER BY price ASC
        ";

        break;


    case "price_high":

        $sql .= "
            ORDER BY price DESC
        ";

        break;


    case "duration":

        $sql .= "
            ORDER BY duration_days ASC
        ";

        break;


    default:

        $sql .= "
            ORDER BY departure_date ASC, price ASC
        ";

        break;
}


/* =========================================================
   LIMIT + OFFSET
========================================================= */

$sql .= "
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$types .= "ii";


/* =========================================================
   PREPARE
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    $sql
);

if (!$stmt) {

    die(
        "Query preparation failed: "
        . mysqli_error($conn)
    );

}


/* =========================================================
   BIND PARAMETERS
========================================================= */

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );

}


/* =========================================================
   EXECUTE
========================================================= */

mysqli_stmt_execute($stmt);


/* =========================================================
   RESULT
========================================================= */

$packages = mysqli_stmt_get_result($stmt);

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        TourBD - Packages
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >


<style>

/* =========================================================
   NAVBAR - EXACTLY MATCH HOME PAGE
========================================================= */

.navbar {
    height: 64px;

    background: #ffffff;

    display: flex;
    align-items: center;

    padding: 0 80px;

    box-sizing: border-box;

    border-bottom: 1px solid #eeeeee;

    position: relative;

    z-index: 1000;
}


/* =========================================================
   TOURBD LOGO
========================================================= */

.logo {
    display: flex;

    align-items: center;

    gap: 6px;

    text-decoration: none;

    color: #6b21a8 !important;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 16px;

    font-weight: 400;

    white-space: nowrap;
}


/* =========================================================
   AIRPLANE ICON
========================================================= */

.logo-mark {
    width: auto;
    height: auto;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    background: transparent;

    color: #6b21a8 !important;

    border-radius: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 17px;

    flex-shrink: 0;
}


/* =========================================================
   TOURBD TEXT
========================================================= */

.logo-text {
    color: #6b21a8 !important;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 16px;

    font-weight: 400;
}


/* =========================================================
   CENTER NAVIGATION
========================================================= */

.nav-center {
    position: absolute;

    left: 50%;

    transform: translateX(-50%);

    height: 100%;

    display: flex;

    align-items: center;

    gap: 34px;
}


/* =========================================================
   NAVIGATION LINKS
========================================================= */

.nav-center a {
    color: #111111;

    text-decoration: none;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 16px;

    font-weight: 400;

    height: 100%;

    display: flex;

    align-items: center;

    position: relative;

    white-space: nowrap;
}


.nav-center a:hover {
    color: #111111;
}


/* =========================================================
   ACTIVE PACKAGES
========================================================= */

.nav-center a.active {
    color: #111111;

    font-weight: 400;
}


.nav-center a.active::after {
    content: "";

    position: absolute;

    bottom: 0;

    left: 0;

    right: 0;

    height: 2px;

    background: #087f78;
}


/* =========================================================
   RIGHT SIDE
========================================================= */

.nav-right {
    margin-left: auto;

    display: flex;

    align-items: center;

    gap: 10px;
}


/* USER */

.nav-user {
    color: #111111;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 16px;

    font-weight: 400;

    white-space: nowrap;
}


/* =========================================================
   LOGOUT
========================================================= */

.logout-btn {
    display: flex;

    align-items: center;

    justify-content: center;

    height: 40px;

    padding: 0 18px;

    border: 1px solid #087f78;

    border-radius: 8px;

    background: #087f78;

    color: #ffffff;

    text-decoration: none;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 14px;

    font-weight: 600;

    box-sizing: border-box;
}


.logout-btn:hover {
    background: #087f78;

    color: #ffffff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .navbar {
        padding: 0 30px;
    }

    .nav-center {
        gap: 20px;
    }

}


@media (max-width: 700px) {

    .navbar {
        padding: 0 20px;
    }

    .nav-center {
        position: static;

        transform: none;

        margin-left: auto;

        margin-right: 20px;

        gap: 15px;
    }

    .nav-user {
        display: none;
    }

}


/* =========================================================
   PACKAGES PAGE
========================================================= */

.packages-page {

    background: #faf9f6;

    min-height: 700px;

    padding: 38px 0 60px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.packages-header {

    max-width: 1235px;

    margin: 0 auto 28px;

}


.packages-header h1 {

    margin: 0 0 8px;

    font-size: 32px;

    font-weight: 500;

    color: #292524;

}


.packages-header p {

    margin: 0;

    color: #78716c;

    font-size: 15px;

}


/* =========================================================
   MAIN LAYOUT
========================================================= */

.packages-layout {

    max-width: 1235px;

    margin: 0 auto;

    display: grid;

    grid-template-columns: 240px 1fr;

    gap: 32px;

    align-items: start;

}


/* =========================================================
   FILTER BOX
========================================================= */

.filter-box {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 16px;

    padding: 25px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.05);

}


.filter-box h2 {

    margin: 0 0 22px;

    font-size: 17px;

    font-weight: 600;

    color: #292524;

}


.filter-group {

    margin-bottom: 22px;

}


.filter-group label {

    display: block;

    margin-bottom: 8px;

    font-size: 13px;

    font-weight: 600;

    color: #292524;

}


.filter-group input[type="text"],
.filter-group select {

    width: 100%;

    box-sizing: border-box;

    height: 44px;

    padding: 0 13px;

    border: 1px solid #d6d3d1;

    border-radius: 8px;

    background: white;

    font: inherit;

    color: #292524;

}


.filter-group input[type="text"]::placeholder {

    color: #a8a29e;

}


.filter-group input[type="text"]:focus,
.filter-group select:focus {

    outline: none;

    border-color: #128078;

}


/* =========================================================
   CATEGORY
========================================================= */

.category-buttons {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;

}


.category-button {

    border: 1px solid #e7e5e4;

    background: white;

    border-radius: 18px;

    padding: 8px 13px;

    font: inherit;

    font-size: 12px;

    cursor: pointer;

    color: #292524;

}


.category-button:hover {

    border-color: #128078;

    color: #087d75;

}


.category-button.active {

    background: #128078;

    border-color: #128078;

    color: white;

}


/* =========================================================
   PRICE
========================================================= */

.price-label {

    display: flex !important;

    justify-content: space-between;

    align-items: center;

}


.price-label strong {

    color: #087d75;

}


.price-slider {

    width: 100%;

    accent-color: #128078;

}


.price-range {

    display: flex;

    justify-content: space-between;

    margin-top: 5px;

    color: #78716c;

    font-size: 11px;

}


/* =========================================================
   SORT
========================================================= */

.sort-select {

    width: 100%;

    height: 44px;

    padding: 0 12px;

    border: 1px solid #d6d3d1;

    border-radius: 8px;

    background: white;

    font: inherit;

}


/* =========================================================
   APPLY FILTERS
========================================================= */

.filter-submit {

    width: 100%;

    border: none;

    border-radius: 8px;

    padding: 12px;

    background: #128078;

    color: white;

    font: inherit;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}


.filter-submit:hover {

    background: #0f6e68;

}


/* =========================================================
   RESULTS
========================================================= */

.package-results {

    min-width: 0;

}


.results-header {

    margin-bottom: 18px;

    color: #78716c;

    font-size: 13px;

}


.results-header strong {

    color: #292524;

}


/* =========================================================
   PACKAGE GRID
========================================================= */

.package-grid {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 20px;

}


/* =========================================================
   PACKAGE CARD
========================================================= */

.package-card {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 15px;

    overflow: hidden;

    box-shadow:
        0 4px 15px rgba(0,0,0,.05);

    display: flex;

    flex-direction: column;

}


/* =========================================================
   IMAGE
========================================================= */

.package-image-wrapper {

    position: relative;

    height: 190px;

    overflow: hidden;

    flex-shrink: 0;

}


.package-img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}


/* =========================================================
   AVAILABILITY
========================================================= */

.availability-badge {

    position: absolute;

    top: 11px;

    left: 11px;

    padding: 5px 9px;

    border-radius: 14px;

    background: #d1fae5;

    color: #087d75;

    font-size: 11px;

    font-weight: 600;

}


.availability-badge.sold {

    background: #fee2e2;

    color: #b91c1c;

}


/* =========================================================
   CATEGORY BADGE
========================================================= */

.category-badge {

    position: absolute;

    top: 11px;

    right: 11px;

    padding: 5px 9px;

    border-radius: 6px;

    background: rgba(41,37,36,.75);

    color: white;

    font-size: 10px;

    font-weight: 600;

}


/* =========================================================
   CONTENT
========================================================= */

.package-content {

    padding: 16px 18px 18px;

    display: flex;

    flex-direction: column;

    flex: 1;

}


/* =========================================================
   LOCATION
========================================================= */

.package-location {

    margin: 0 0 5px;

    color: #087d75;

    font-size: 12px;

    font-weight: 600;

}


/* =========================================================
   NAME
========================================================= */

.package-card h3 {

    margin: 0 0 10px;

    color: #292524;

    font-size: 17px;

    line-height: 1.3;

    font-weight: 500;

    min-height: 44px;

}


/* =========================================================
   META
========================================================= */

.package-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;

    margin-bottom: 11px;

    color: #78716c;

    font-size: 11px;

}


.package-meta span {

    white-space: nowrap;

}


/* =========================================================
   SEATS
========================================================= */

.package-seats {

    margin: 0 0 10px;

    color: #78716c;

    font-size: 11px;

}


/* =========================================================
   HOTEL
========================================================= */

.package-hotel {

    margin: 0 0 15px;

    color: #78716c;

    font-size: 11px;

}


/* =========================================================
   DESCRIPTION
========================================================= */

.package-description {

    margin: 0 0 15px;

    color: #78716c;

    font-size: 11px;

    line-height: 1.5;

    min-height: 34px;

}


/* =========================================================
   PRICE + BOOK NOW
========================================================= */

.package-bottom {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 10px;

    margin-top: auto;

}


.package-price {

    color: #087d75;

    font-size: 20px;

    font-weight: 700;

    white-space: nowrap;

}


.package-price small {

    color: #78716c;

    font-size: 10px;

    font-weight: 400;

}


/* =========================================================
   BOOK NOW
========================================================= */

.book-now-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 86px;

    height: 34px;

    box-sizing: border-box;

    padding: 8px 13px;

    border-radius: 8px;

    background: #128078;

    color: white;

    text-decoration: none;

    font-size: 11px;

    font-weight: 600;

    flex-shrink: 0;

}


.book-now-btn:hover {

    background: #0f6e68;

}


/* =========================================================
   VIEW DETAILS
========================================================= */

.details-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 86px;

    height: 34px;

    box-sizing: border-box;

    padding: 8px 12px;

    border-radius: 8px;

    border: 1px solid #128078;

    background: white;

    color: #087d75;

    text-decoration: none;

    font-size: 11px;

    font-weight: 600;

}


.details-btn:hover {

    background: #f0fdfa;

}


/* =========================================================
   SOLD OUT
========================================================= */

.package-card.sold-out {

    opacity: .78;

}


/* =========================================================
   EMPTY
========================================================= */

.empty-state {

    background: white;

    border: 1px solid #e7e5e4;

    border-radius: 12px;

    padding: 40px;

    text-align: center;

    color: #78716c;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1250px) {

    .navbar,
    .packages-header,
    .packages-layout {

        margin-left: 25px;

        margin-right: 25px;

    }

}


@media(max-width:1050px) {

    .package-grid {

        grid-template-columns:
            repeat(2, minmax(0,1fr));

    }

}


@media(max-width:750px) {

    .navbar {

        padding: 0 20px;

    }


    .nav-center {

        gap: 15px;

    }


    .packages-layout {

        grid-template-columns: 1fr;

    }


    .package-grid {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>



<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="site-header">

    <div class="container">

        <nav class="navbar">

            <!-- LOGO -->

            <a href="index.php" class="brand">

                <span class="logo-mark">✈</span>

                <span class="logo-text">
                    TourBD
                </span>

            </a>


            <!-- NAVIGATION -->

            <div class="nav-links">

                <a href="index.php">
                    Home
                </a>

                <a href="packages.php" class="active">
                    Packages
                </a>

                <?php if(isset($_SESSION['user_id'])): ?>

                    <a href="my_bookings.php">
                        My Bookings
                    </a>

                <?php endif; ?>

            </div>


            <!-- RIGHT SIDE -->

            <div class="nav-actions">

                <?php if(isset($_SESSION['user_id'])): ?>

                    <span class="user-name">

                        <?php

                        if(isset($_SESSION['name'])) {

                            echo e($_SESSION['name']);

                        } else {

                            echo "User";

                        }

                        ?>

                    </span>


                    <a
                        href="logout.php"
                        class="btn-primary"
                    >
                        Logout
                    </a>

                <?php else: ?>

                    <a href="login.php">
                        Login
                    </a>


                    <a
                        href="register.php"
                        class="btn-primary"
                    >
                        Register
                    </a>

                <?php endif; ?>

            </div>

        </nav>

    </div>

</header>


<!-- =========================================================
     PACKAGES PAGE
========================================================= -->

<section class="packages-page">


    <!-- PAGE HEADER -->

    <div class="packages-header">

        <h1>
            Browse Tour Packages
        </h1>

        <p>
            Find the perfect Bangladesh adventure for you
        </p>

    </div>



    <!-- MAIN -->

    <div class="packages-layout">


        <!-- =================================================
             FILTER
        ================================================= -->

        <aside class="filter-box">

            <h2>
                🔍 Search & Filter
            </h2>


            <form
                method="get"
                id="filterForm"
            >


                <!-- SEARCH -->

                <div class="filter-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?php
                            echo e($search);
                        ?>"
                        placeholder="Destination or package..."
                    >

                </div>



                <!-- DESTINATION -->

                <div class="filter-group">

                    <label>
                        Destination
                    </label>

                    <select name="destination_id">

                        <option value="0">
                            All Destinations
                        </option>


                        <?php while(
                            $d =
                            mysqli_fetch_assoc(
                                $destinations
                            )
                        ): ?>

                            <option
                                value="<?php
                                    echo e(
                                        $d['destination_id']
                                    );
                                ?>"
                                <?php

                                if(
                                    $destinationId ==
                                    $d['destination_id']
                                ){

                                    echo "selected";

                                }

                                ?>
                            >

                                <?php

                                echo e(
                                    $d['destination_name']
                                );

                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>



                <!-- CATEGORY -->

                <div class="filter-group">

                    <label>
                        Category
                    </label>


                    <div class="category-buttons">


                        <button
                            type="button"
                            class="category-button
                            <?php
                            echo $category === "All"
                                ? "active"
                                : "";
                            ?>"
                            data-category="All"
                        >
                            All
                        </button>


                        <button
                            type="button"
                            class="category-button
                            <?php
                            echo $category === "Beach"
                                ? "active"
                                : "";
                            ?>"
                            data-category="Beach"
                        >
                            Beach
                        </button>


                        <button
                            type="button"
                            class="category-button
                            <?php
                            echo $category === "Hill"
                                ? "active"
                                : "";
                            ?>"
                            data-category="Hill"
                        >
                            Hill
                        </button>


                        <button
                            type="button"
                            class="category-button
                            <?php
                            echo $category === "Nature"
                                ? "active"
                                : "";
                            ?>"
                            data-category="Nature"
                        >
                            Nature
                        </button>


                        <button
                            type="button"
                            class="category-button
                            <?php
                            echo $category === "Adventure"
                                ? "active"
                                : "";
                            ?>"
                            data-category="Adventure"
                        >
                            Adventure
                        </button>


                    </div>


                    <input
                        type="hidden"
                        name="category"
                        id="categoryInput"
                        value="<?php
                            echo e($category);
                        ?>"
                    >

                </div>



                <!-- MAX PRICE -->

                <div class="filter-group">

                    <label class="price-label">

                        <span>
                            Max Price
                        </span>

                        <strong id="priceValue">

                            ৳<?php

                            echo number_format(
                                $maxPrice,
                                0
                            );

                            ?>

                        </strong>

                    </label>


                    <input
                        type="range"
                        class="price-slider"
                        name="max_price"
                        id="priceSlider"
                        min="5000"
                        max="20000"
                        step="500"
                        value="<?php
                            echo e($maxPrice);
                        ?>"
                    >


                    <div class="price-range">

                        <span>
                            ৳5,000
                        </span>

                        <span>
                            ৳20,000
                        </span>

                    </div>

                </div>



                <!-- SORT -->

                <div class="filter-group">

                    <label>
                        Sort By
                    </label>


                    <select
                        name="sort"
                        class="sort-select"
                    >

                        <option
                            value="default"
                            <?php

                            echo $sort === "default"
                                ? "selected"
                                : "";

                            ?>
                        >
                            Default
                        </option>


                        <option
                            value="price_low"
                            <?php

                            echo $sort === "price_low"
                                ? "selected"
                                : "";

                            ?>
                        >
                            Price: Low to High
                        </option>


                        <option
                            value="price_high"
                            <?php

                            echo $sort === "price_high"
                                ? "selected"
                                : "";

                            ?>
                        >
                            Price: High to Low
                        </option>


                        <option
                            value="duration"
                            <?php

                            echo $sort === "duration"
                                ? "selected"
                                : "";

                            ?>
                        >
                            Duration
                        </option>

                    </select>

                </div>



                <!-- APPLY -->

                <button
                    type="submit"
                    class="filter-submit"
                >
                    Apply Filters
                </button>


            </form>

        </aside>



        <!-- =================================================
             RESULTS
        ================================================= -->

        <div class="package-results">


            <div class="results-header">

                Showing

                <strong>

                    <?php
                    echo $totalPackages;
                    ?>

                </strong>

                packages

            </div>



            <?php if(
                mysqli_num_rows($packages) == 0
            ): ?>


                <div class="empty-state">

                    No packages match
                    your selected filters.

                </div>


            <?php else: ?>


                <div class="package-grid">


                    <?php while(
                        $p =
                        mysqli_fetch_assoc(
                            $packages
                        )
                    ): ?>


                        <?php

                        /* =================================
                           SOLD OUT
                        ================================= */

                        $isSoldOut =
                            ((int)$p['available_seats'] <= 0);


                        /* =================================
                           CATEGORY
                        ================================= */

                        $destinationName =
                            strtolower(
                                $p['destination_name']
                            );


                        if(
                            strpos(
                                $destinationName,
                                "cox"
                            ) !== false
                            ||
                            strpos(
                                $destinationName,
                                "beach"
                            ) !== false
                        ){

                            $displayCategory = "Beach";

                        }
                        elseif(
                            strpos(
                                $destinationName,
                                "sajek"
                            ) !== false
                            ||
                            strpos(
                                $destinationName,
                                "rangamati"
                            ) !== false
                            ||
                            strpos(
                                $destinationName,
                                "bandarban"
                            ) !== false
                            ||
                            strpos(
                                $destinationName,
                                "hill"
                            ) !== false
                        ){

                            $displayCategory = "Hill";

                        }
                        elseif(
                            stripos(
                                $p['package_name'],
                                "adventure"
                            ) !== false
                            ||
                            stripos(
                                $p['description'] ?? "",
                                "adventure"
                            ) !== false
                        ){

                            $displayCategory = "Adventure";

                        }
                        else {

                            $displayCategory = "Nature";

                        }

                        ?>


                        <!-- CARD -->

                        <div
                            class="package-card
                            <?php

                            echo $isSoldOut
                                ? 'sold-out'
                                : '';

                            ?>"
                        >


                            <!-- IMAGE -->

                            <div
                                class="package-image-wrapper"
                            >

                                <img
                                    class="package-img"
                                    src="assets/images/<?php
                                        echo e(
                                            $p['image_url']
                                        );
                                    ?>"
                                    alt="<?php
                                        echo e(
                                            $p['package_name']
                                        );
                                    ?>"
                                >


                                <!-- AVAILABILITY -->

                                <?php if($isSoldOut): ?>

                                    <span
                                        class="availability-badge sold"
                                    >
                                        ✕ Sold Out
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="availability-badge"
                                    >
                                        ✓ Available
                                    </span>

                                <?php endif; ?>


                                <!-- CATEGORY -->

                                <span
                                    class="category-badge"
                                >

                                    <?php

                                    echo e(
                                        $displayCategory
                                    );

                                    ?>

                                </span>


                            </div>



                            <!-- CONTENT -->

                            <div
                                class="package-content"
                            >


                                <!-- LOCATION -->

                                <p
                                    class="package-location"
                                >

                                    📍

                                    <?php

                                    echo e(
                                        $p['destination_name']
                                    );

                                    ?>

                                </p>



                                <!-- NAME -->

                                <h3>

                                    <?php

                                    echo e(
                                        $p['package_name']
                                    );

                                    ?>

                                </h3>



                                <!-- META -->

                                <div
                                    class="package-meta"
                                >

                                    <span>

                                        ◷

                                        <?php

                                        echo e(
                                            $p['duration_days']
                                        );

                                        ?>

                                        Days /

                                        <?php

                                        echo e(
                                            $p['duration_nights']
                                        );

                                        ?>

                                        Nights

                                    </span>


                                    <?php if(
                                        isset($p['rating'])
                                    ): ?>

                                        <span>

                                            ⭐

                                            <?php

                                            echo e(
                                                $p['rating']
                                            );

                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </div>



                                <!-- SEATS -->

                                <p
                                    class="package-seats"
                                >

                                    🪑

                                    <?php

                                    echo e(
                                        $p['available_seats']
                                    );

                                    ?>

                                    seats left

                                </p>



                                <!-- HOTEL -->

                                <?php if(
                                    isset($p['hotel_name'])
                                    &&
                                    $p['hotel_name'] != ""
                                ): ?>

                                    <p
                                        class="package-hotel"
                                    >

                                        🏨

                                        <?php

                                        echo e(
                                            $p['hotel_name']
                                        );

                                        ?>

                                    </p>

                                <?php endif; ?>



                                <!-- DESCRIPTION -->

                                <?php if(
                                    !empty(
                                        $p['description']
                                    )
                                ): ?>

                                    <p
                                        class="package-description"
                                    >

                                        <?php

                                        echo e(
                                            $p['description']
                                        );

                                        ?>

                                    </p>

                                <?php endif; ?>



                                <!-- PRICE + BUTTON -->

                                <div
                                    class="package-bottom"
                                >


                                    <strong
                                        class="package-price"
                                    >

                                        <?php

                                        echo money(
                                            $p['price']
                                        );

                                        ?>

                                        <small>
                                            /person
                                        </small>

                                    </strong>



                                    <?php if(
                                        $isSoldOut
                                    ): ?>


                                        <a
                                            href="package_details.php?id=<?php
                                                echo e(
                                                    $p['package_id']
                                                );
                                            ?>"
                                            class="details-btn"
                                        >
                                            View Details
                                        </a>


                                    <?php
                                    elseif(
                                        isset(
                                            $_SESSION['user_id']
                                        )
                                    ):
                                    ?>


                                        <a
                                            href="booking.php?id=<?php
                                                echo e(
                                                    $p['package_id']
                                                );
                                            ?>"
                                            class="book-now-btn"
                                        >
                                            Book Now
                                        </a>


                                    <?php else: ?>


                                        <a
                                            href="login.php"
                                            class="book-now-btn"
                                        >
                                            Book Now
                                        </a>


                                    <?php endif; ?>


                                </div>


                            </div>


                        </div>


                    <?php endwhile; ?>


                </div>


                <!-- =========================================================
                     PAGINATION
                ========================================================= -->

                <?php if ($totalPages > 1): ?>

                    <div class="pagination">

                        <?php if ($page > 1): ?>

                            <a
                                href="?<?php
                                    echo e(
                                        http_build_query(
                                            array_merge(
                                                $_GET,
                                                [
                                                    'page' => $page - 1
                                                ]
                                            )
                                        )
                                    );
                                ?>"
                            >
                                ← Previous
                            </a>

                        <?php endif; ?>


                        <?php for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ): ?>

                            <?php if ($i == $page): ?>

                                <span class="active">
                                    <?php echo $i; ?>
                                </span>

                            <?php else: ?>

                                <a
                                    href="?<?php
                                        echo e(
                                            http_build_query(
                                                array_merge(
                                                    $_GET,
                                                    [
                                                        'page' => $i
                                                    ]
                                                )
                                            )
                                        );
                                    ?>"
                                >
                                    <?php echo $i; ?>
                                </a>

                            <?php endif; ?>

                        <?php endfor; ?>


                        <?php if ($page < $totalPages): ?>

                            <a
                                href="?<?php
                                    echo e(
                                        http_build_query(
                                            array_merge(
                                                $_GET,
                                                [
                                                    'page' => $page + 1
                                                ]
                                            )
                                        )
                                    );
                                ?>"
                            >
                                Next →
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>


            <?php endif; ?>


        </div>


    </div>


</section>



<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

(function(){


    /* =====================================================
       PRICE SLIDER
    ===================================================== */

    const slider =
        document.getElementById(
            "priceSlider"
        );


    const priceValue =
        document.getElementById(
            "priceValue"
        );


    if(
        slider &&
        priceValue
    ){

        slider.addEventListener(
            "input",
            function(){

                priceValue.textContent =
                    "৳" +
                    Number(
                        this.value
                    ).toLocaleString(
                        "en-BD"
                    );

            }
        );

    }



    /* =====================================================
       CATEGORY BUTTONS
    ===================================================== */

    const categoryButtons =
        document.querySelectorAll(
            ".category-button"
        );


    const categoryInput =
        document.getElementById(
            "categoryInput"
        );


    const filterForm =
        document.getElementById(
            "filterForm"
        );


    categoryButtons.forEach(
        function(button){

            button.addEventListener(
                "click",
                function(){

                    const selectedCategory =
                        this.dataset.category;


                    categoryInput.value =
                        selectedCategory;


                    categoryButtons.forEach(
                        function(btn){

                            btn.classList.remove(
                                "active"
                            );

                        }
                    );


                    this.classList.add(
                        "active"
                    );


                    filterForm.submit();

                }
            );

        }
    );


})();

</script>


</body>

</html>