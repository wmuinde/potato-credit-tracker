
<?php
require_once 'config.php';

// Destroy session and redirect to login page
session_destroy();
redirect('login.php');
