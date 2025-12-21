<?php
session_start();
session_destroy(); // clear all sessions
header("Location: homepage.php"); // redirect to homepage
exit();
?>
