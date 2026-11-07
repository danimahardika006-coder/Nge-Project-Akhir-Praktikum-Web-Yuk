<?php
    session_start();

    //menghapus seluruh sesion
    session_unset();

    //menghancurkan s
    session_destroy();

    header("Location: login.php");
    exit();
    
?>