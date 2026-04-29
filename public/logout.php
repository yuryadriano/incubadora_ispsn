<?php
session_start();
session_unset();
session_destroy();
header('Location: /incubadora_ispsn/public/login.php');
exit;
