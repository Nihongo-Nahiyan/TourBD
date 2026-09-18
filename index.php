<?php


include "config/db.php";
// STATS FROM DATABASE

$package_count_sql = "SELECT COUNT(*) AS total FROM tour_packages";
$package_count_result = mysqli_query($conn, $package_count_sql);
$package_count_row = mysqli_fetch_assoc($package_count_result);
$total_packages = $package_count_row['total'];


$destination_count_sql = "SELECT COUNT(*) AS total FROM destinations WHERE active = 1";
$destination_count_result = mysqli_query($conn, $destination_count_sql);
$destination_count_row = mysqli_fetch_assoc($destination_count_result);
$total_destinations = $destination_count_row['total'];

// DESTINATIONS

$destination_sql = "

SELECT *

FROM destinations

WHERE active = 1

LIMIT 4

";

$destination_result = mysqli_query($conn,$destination_sql);

// PACKAGES

$package_sql = "

SELECT 

tour_packages.*,
destinations.destination_name

FROM tour_packages

JOIN destinations

ON tour_packages.destination_id = destinations.destination_id

LIMIT 4

";

$package_result = mysqli_query($conn,$package_sql);

?>

<!DOCTYPE html>

<html>

<head>

<title>TourBD</title>

<link rel="stylesheet" href="css/style.css">

</head>

<body>

<!-- NAVBAR -->

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

<a href="register.php" class="register-btn">
Register
</a>

</div>

</nav>

</header>

<!-- HERO -->

<section class="hero">

<div class="hero-content">

<div class="tag">

Bangladesh's #1 Travel Booking Platform

</div>

<h1>

Discover the Hidden

<br>

<span>
Beauty of Bangladesh
</span>

</h1>

<p>

Explore amazing destinations with complete tour packages including hotels and transport.

</p>

<form class="search-box" action="packages.php" method="GET">

    <input
        type="text"
        name="search"
        placeholder="Search destination (Cox's Bazar, Sajek...)"
    >

    <button type="submit">
        🔍 Search
    </button>

</form>

</div>

</section>

<!-- STATS -->

<section class="stats">

<div>
<h2><?php echo $total_packages; ?></h2>
<p>Tour Packages</p>
</div>

<div>
<h2><?php echo $total_destinations; ?></h2>
<p>Destinations</p>
</div>

<div>
<h2>1200+</h2>
<p>Happy Travelers</p>
</div>

<div>
<h2>4.8★</h2>
<p>Average Rating</p>
</div>

</section>

<!-- DESTINATIONS -->

<section class="destination-section">

    <h1>
        Popular Destinations
    </h1>

    <p class="subtitle">
        Four iconic destinations across Bangladesh await you
    </p>

    <div class="destination-container">

        <?php while($row=mysqli_fetch_assoc($destination_result)){ ?>

            <a
                href="packages.php?destination_id=<?php echo $row['destination_id']; ?>"
                class="destination-card"
            >

                <img
                    src="assets/images/<?php echo $row['image_url']; ?>"
                    alt="<?php echo $row['destination_name']; ?>"
                >

                <div class="destination-text">

                    <h3>
                        <?php echo $row['destination_name']; ?>
                    </h3>

                    <p>
                        <?php echo $row['location']; ?>
                    </p>

                </div>

            </a>

        <?php } ?>

    </div>

</section>

<!-- PACKAGES -->

<section class="package-section">

<div class="package-header">

<div>

<h1>
Featured Packages
</h1>

<p>
Hand-picked tours for every kind of traveler
</p>

</div>

<a href="packages.php">
View All Packages →
</a>

</div>

<div class="package-container">

<?php while($row=mysqli_fetch_assoc($package_result)){ ?>

<div class="package-card">

<img 
src="assets/images/<?php echo $row['image_url']; ?>"
>

<div class="package-content">

<h3>

<?php echo $row['package_name']; ?>

</h3>

<p>

📍 <?php echo $row['destination_name']; ?>

</p>

<p>

⏱ <?php echo $row['duration_days']; ?>

Days /

<?php echo $row['duration_nights']; ?>

Nights

</p>

<h4>

৳<?php echo $row['price']; ?>

/person

</h4>

<a href="package_details.php?id=<?php echo $row['package_id']; ?>">

<button>

View Details

</button>

</a>

</div>

</div>

<?php } ?>

</div>

</section>

<!-- WHY CHOOSE -->

<section class="why">

<h1>
Why Choose TourBD?
</h1>

<p>
We make your Bangladesh travel experience seamless and memorable
</p>

<div class="why-container">

<div class="why-card">

<h3>
🛡 Safe & Trusted
</h3>

<p>
Verified tour packages with reliable service.
</p>

</div>

<div class="why-card">

<h3>
💰 Best Price Guarantee
</h3>

<p>
Affordable packages with transparent pricing.
</p>

</div>

<div class="why-card">

<h3>
⏰ 24/7 Support
</h3>

<p>
Support before and during your journey.
</p>

</div>

<div class="why-card">

<h3>
🎯 Curated Experiences
</h3>

<p>
Carefully selected travel experiences.
</p>

</div>

</div>

</section>

<!-- CTA -->

<section class="cta">

<div>

<h2>
Ready for Your Next Adventure?
</h2>

<p>
Browse our curated packages and book your dream trip today.
</p>

</div>

<div class="cta-buttons">

<a href="packages.php">
Browse Packages
</a>

<a href="register.php">
Register Free
</a>

</div>

</section>

<footer>

<h3>
✈ TourBD
</h3>

<p>
© 2026 TourBD - Tour Package & Travel Booking Management System
</p>

</footer>

</body>

</html>