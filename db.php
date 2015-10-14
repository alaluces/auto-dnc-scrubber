<?php

$host   = '192.168.1.5';
$dbname = 'dnc';
$user   = 'dncms';
$pass   = '1234';

$DBH    = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass); 
?>
