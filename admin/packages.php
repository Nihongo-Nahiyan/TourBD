<?php

require_once "config/db.php";

$pageTitle = "Packages";


/* =========================
   FILTER VALUES
========================= */

$search = trim($_GET['search'] ?? '');

$destinationId = intval(
    $_GET['destination_id'] ?? 0
);

$maxPrice = floatval(
    $_GET['max_price'] ?? 0
);


/* =========================
   DESTINATIONS
========================= */

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


/* =========================
   PACKAGES
========================= */

$sql = "
    SELECT *
    FROM available_packages
    WHERE 1=1
";


$params = [];
$types = "";


/* SEARCH */

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


/* DESTINATION */

if ($destinationId > 0) {

    $sql .= "
        AND destination_id = ?
    ";

    $params[] = $destinationId;

    $types .= "i";
}


/* MAX PRICE */

if ($maxPrice > 0) {

    $sql .= "
        AND price <= ?
    ";

    $params[] = $maxPrice;

    $types .= "d";
}


/* SORT */

$sql .= "
    ORDER BY departure_date, price
";


/* =========================
   PREPARE QUERY
========================= */

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


/* ADD PARAMETERS */

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );

}


/* EXECUTE */

mysqli_stmt_execute($stmt);


/* GET RESULT */

$packages = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        TourBD - Packages
    </title>

    <link rel="stylesheet"
          href="css/style.css">

</head>


<body>


<!-- =========================
     NAVBAR
========================= -->

<header>

<nav class="navbar">


    <div class="logo">

        ✈ TourBD

    </div>


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

        <a href="register.php"
           class="register-btn">

            Register

        </a>

    </div>


</nav>

</header>



<!-- =========================
     PAGE HEADER
========================= -->

<section class="packages-page-header">

    <h1>
        Browse Tour Packages
    </h1>

    <p>
        Find the perfect Bangladesh adventure for you
    </p>

</section>



<!-- =========================
     PACKAGES AREA
========================= -->

<section class="all-packages">

    <div class="packages-layout">


        <!-- =========================
             FILTER SIDEBAR
        ========================= -->

        <aside class="filter-box">


            <h2>
                🔍 Search & Filter
            </h2>


            <form method="get">


                <!-- SEARCH -->

                <div class="filter-group">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?php echo e($search); ?>"
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


                        <?php

                        while (
                            $d =
                            mysqli_fetch_assoc(
                                $destinations
                            )
                        ):

                        ?>

                            <option
                                value="<?php
                                    echo e(
                                        $d['destination_id']
                                    );
                                ?>"
                                <?php

                                if (
                                    $destinationId
                                    ==
                                    $d['destination_id']
                                ) {

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
                            class="category-active"
                        >
                            All
                        </button>

                        <button
                            type="button"
                        >
                            Beach
                        </button>

                        <button
                            type="button"
                        >
                            Hill
                        </button>

                        <button
                            type="button"
                        >
                            Nature
                        </button>

                        <button
                            type="button"
                        >
                            Adventure
                        </button>

                    </div>

                </div>



                <!-- MAX PRICE -->

                <div class="filter-group">

                    <label>
                        Max Price:
                        <strong>
                            ৳20,000
                        </strong>
                    </label>

                    <input
                        type="range"
                        name="max_price"
                        min="5000"
                        max="20000"
                        value="<?php
                            echo $maxPrice > 0
                                ? e($maxPrice)
                                : 20000;
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

                    <select>

                        <option>
                            Default
                        </option>

                        <option>
                            Price: Low to High
                        </option>

                        <option>
                            Price: High to Low
                        </option>

                    </select>

                </div>



                <!-- FILTER BUTTON -->

                <button
                    type="submit"
                    class="filter-submit"
                >

                    Apply Filters

                </button>


            </form>


        </aside>



        <!-- =========================
             PACKAGE RESULTS
        ========================= -->

        <div class="package-results">


            <div class="results-header">

                <span>

                    Showing
                    <strong>
                        <?php
                        echo mysqli_num_rows(
                            $packages
                        );
                        ?>
                    </strong>
                    packages

                </span>

            </div>



            <?php

            if (
                mysqli_num_rows($packages)
                == 0
            ):

            ?>

                <div class="empty-state">

                    No available packages
                    match your search.

                </div>


            <?php else: ?>


                <div class="package-grid">


                    <?php

                    while (
                        $p =
                        mysqli_fetch_assoc(
                            $packages
                        )
                    ):

                    ?>


                        <!-- PACKAGE CARD -->

                        <div class="package-card">


                            <!-- IMAGE -->

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



                            <div class="package-content">


                                <!-- STATUS -->

                                <?php

                                if (
                                    $p['available_seats']
                                    <= 3
                                ):

                                ?>

                                    <span class="available warning">

                                        ⚡ Limited

                                    </span>

                                <?php else: ?>

                                    <span class="available">

                                        ✓ Available

                                    </span>

                                <?php endif; ?>



                                <!-- DESTINATION -->

                                <p class="package-location">

                                    📍
                                    <?php

                                    echo e(
                                        $p['destination_name']
                                    );

                                    ?>

                                </p>



                                <!-- PACKAGE NAME -->

                                <h3>

                                    <?php

                                    echo e(
                                        $p['package_name']
                                    );

                                    ?>

                                </h3>



                                <!-- DETAILS -->

                                <div class="package-meta">

                                    <span>

                                        ⏱
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


                                    <span>

                                        🪑
                                        <?php

                                        echo e(
                                            $p['available_seats']
                                        );

                                        ?>

                                        seats left

                                    </span>


                                    <span>

                                        📅
                                        <?php

                                        echo e(
                                            $p['departure_date']
                                        );

                                        ?>

                                    </span>

                                </div>



                                <!-- DESCRIPTION -->

                                <p class="package-description">

                                    <?php

                                    echo e(
                                        $p['description']
                                    );

                                    ?>

                                </p>



                                <!-- PRICE + BUTTON -->

                                <div class="package-bottom">


                                    <strong class="package-price">

                                        <?php

                                        echo money(
                                            $p['price']
                                        );

                                        ?>

                                        <small>
                                            /person
                                        </small>

                                    </strong>


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


                                </div>


                            </div>


                        </div>


                    <?php endwhile; ?>


                </div>


            <?php endif; ?>


        </div>


    </div>

</section>



<!-- =========================
     FOOTER
========================= -->

<footer>

    <h3>
        ✈ TourBD
    </h3>

    <p>
        © 2026 TourBD -
        Tour Package & Travel Booking
        Management System
    </p>

</footer>


</body>

</html>