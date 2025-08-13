<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "job-seeker";
try {
    $pdo =   new PDO("mysql:host=$servername;dbname=$dbname;", $username, $password);
} catch (PDOException $e) {
    echo "Fail to Connect" . $e->getMessage();
}
