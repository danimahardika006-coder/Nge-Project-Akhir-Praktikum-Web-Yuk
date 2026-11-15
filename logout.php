<?php
session_start();

// Hapus seluruh data session
session_unset();

// Hancurkan session
session_destroy();

header("Location: login.php");
exit();