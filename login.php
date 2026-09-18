<?php


/* =========================
   START SESSION
========================= */

if(session_status() === PHP_SESSION_NONE){

    session_start();

}


/* =========================
   DATABASE CONNECTION
========================= */

require_once "config/db.php";


$message = "";



/* =========================
   LOGIN PROCESS
========================= */

if($_SERVER['REQUEST_METHOD'] === 'POST'){


    $email =
        trim($_POST['email']);


    $password =
        $_POST['password'];



    /* FIND USER BY EMAIL */


    $sql = "

    SELECT *

    FROM users

    WHERE email = ?

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $email
    );


    mysqli_stmt_execute(
        $stmt
    );


    $result =
        mysqli_stmt_get_result(
            $stmt
        );


    $user =
        mysqli_fetch_assoc(
            $result
        );



    /* CHECK EMAIL + PASSWORD */


    if(
        $user
        &&
        password_verify(
            $password,
            $user['password_hash']
        )
    ){


        /* SAVE USER IN SESSION */


        $_SESSION['user_id'] =
            $user['user_id'];


        $_SESSION['name'] =
            $user['name'];


        $_SESSION['role'] =
            $user['role'];



        /* ADMIN LOGIN */


        if($user['role'] === 'admin'){


            header(
                "Location: admin/dashboard.php"
            );


            exit;


        }


        /* CUSTOMER LOGIN */


        else{


            header(
                "Location: index.php"
            );


            exit;


        }


    }


    else{


        $message =
            "Invalid email or password.";


    }


}



/* =========================
   LOAD PAGE AFTER LOGIN CODE
========================= */

$pageTitle = "Login";

require_once "includes/header.php";

?>



<div class="container">


    <div class="form-card">


        <h2>

            Login

        </h2>


        <p class="section-subtitle">

            Customer or admin login.

        </p>



        <?php if(isset($_GET['registered'])){ ?>


            <div class="alert alert-success">

                Registration successful.
                Please login.

            </div>


        <?php } ?>



        <?php if($message != ""){ ?>


            <div class="alert alert-error">

                <?php
                echo e($message);
                ?>

            </div>


        <?php } ?>



        <form method="POST">


            <div class="form-group">


                <label>

                    Email

                </label>


                <input

                    type="email"

                    name="email"

                    required

                >


            </div>



            <div class="form-group">


                <label>

                    Password

                </label>


                <input

                    type="password"

                    name="password"

                    required

                >


            </div>



            <button

                class="btn-primary"

                type="submit"

            >

                Login

            </button>


        </form>


    </div>


</div>



<?php

require_once "includes/footer.php";

?>