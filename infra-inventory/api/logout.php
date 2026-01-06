<?php
// api/logout.php
include_once 'config.php';
session_destroy();
echo json_encode(["message" => "Logged out"]);
?>