<?php
// This is just a wrapper that includes login.php with admin context
// Save this as admin_login.php
$_GET['admin'] = true;
include 'login.php';
?>