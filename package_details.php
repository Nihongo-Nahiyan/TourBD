<?php

require_once "config/db.php";

$pageTitle = "Package Details";

require_once "includes/header.php";



/* GET PACKAGE ID */


$id = 0;


if(isset($_GET['id'])){

    $id = intval($_GET['id']);

}



/* GET PACKAGE INFORMATION */


$sql = "

SELECT *

FROM available_packages

WHERE package_id = ?

";


$stmt = mysqli_prepare($conn,$sql);


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


$package = mysqli_fetch_assoc($result);



/* PACKAGE NOT FOUND */


if(!$package){

?>

<div class="container">

    <div class="alert alert-error">

        Package not found or no longer available.

    </div>

</div>

<?php

require_once "includes/footer.php";

exit;

}

?>





<!-- PACKAGE DETAILS -->


<section class="package-details-section">


<div class="details-container">



<!-- PACKAGE IMAGE -->


<div class="details-image-box">


<img

src="assets/images/<?php echo $package['image_url']; ?>"

alt="<?php echo $package['package_name']; ?>"

class="details-image"

>


</div>





<!-- PACKAGE INFORMATION -->


<div class="details-content">



<span class="available">

<?php echo $package['available_seats']; ?>

seats available

</span>



<h1>

<?php echo $package['package_name']; ?>

</h1>



<p class="details-description">

<?php echo $package['description']; ?>

</p>





<div class="details-info">


<p>

<strong>
Destination:
</strong>

<?php echo $package['destination_name']; ?>

</p>



<p>

<strong>
Hotel:
</strong>

<?php echo $package['hotel_name']; ?>

</p>



<p>

<strong>
Transport:
</strong>

<?php echo $package['transport_type']; ?>

-

<?php echo $package['transport_provider']; ?>

</p>



<p>

<strong>
Duration:
</strong>

<?php echo $package['duration_days']; ?>

Days /

<?php echo $package['duration_nights']; ?>

Nights

</p>



<p>

<strong>
Departure:
</strong>

<?php echo $package['departure_date']; ?>

</p>


</div>





<h2 class="details-price">

৳<?php echo number_format($package['price'],2); ?>

<span>
/ person
</span>

</h2>





<!-- BOOKING BUTTON -->


<?php


if(isset($_SESSION['user_id'])){


    if($_SESSION['role'] != 'admin'){

?>


<a

href="booking.php?id=<?php echo $package['package_id']; ?>"

class="book-btn"

>

Book Package

</a>


<?php

    }

}

else{

?>


<a

href="login.php"

class="book-btn"

>

Login to Book

</a>


<?php

}

?>


</div>


</div>


</section>



<?php

require_once "includes/footer.php";

?>