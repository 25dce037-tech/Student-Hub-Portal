<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv('STUDENTHUB_DB_HOST') ?: 'localhost';
$dbName = getenv('STUDENTHUB_DB_NAME') ?: 'student_hub_portal';
$dbUser = getenv('STUDENTHUB_DB_USER') ?: 'root';
$dbPassword = getenv('STUDENTHUB_DB_PASSWORD') ?: '';
$dbPort = (int) (getenv('STUDENTHUB_DB_PORT') ?: 3306);

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName, $dbPort);
$conn->set_charset('utf8mb4');