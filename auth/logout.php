<?php
session_start();

// Clear all session data including notification flag
session_unset();
session_destroy();

header("location: ../index.php");
exit();
?>