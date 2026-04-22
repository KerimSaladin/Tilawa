<?php
$_GET['token'] = 'tilawa_setup_2024';
ob_start();
require 'setup.php';
$output = ob_get_clean();
file_put_contents('c:/xampp/htdocs/Tilawa/setup_output_internal.txt', $output);
echo "done";
