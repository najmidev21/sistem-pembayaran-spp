<?php
// logout: session dikosongin trus dihancurin, abis itu balik ke halaman login
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
