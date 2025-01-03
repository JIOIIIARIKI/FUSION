<?php
session_start();

unset($_SESSION['domain']);
unset($_SESSION['ip']);
unset($_SESSION['prefix']);
unset($_SESSION['from']);
unset($_SESSION['to']);

header("Location: history.php");
exit();
?>
