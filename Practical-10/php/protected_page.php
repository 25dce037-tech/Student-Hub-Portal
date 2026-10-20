<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
startAuthSession();
requireLogin();

$pages = [
    'Profile' => 'Profile.html',
    'Attendance' => 'Attendance.html',
    'Timetable' => 'Timetable.html',
    'Courses' => 'Courses.html',
    'Assignment' => 'Assignment.html',
    'Result' => 'Result.html',
    'Notices' => 'Notices.html',
];
$page = $_GET['page'] ?? '';

if (!is_string($page) || !isset($pages[$page])) {
    http_response_code(404);
    exit('Page not found.');
}

header('Cache-Control: no-store');
readfile(__DIR__ . '/../pages/' . $pages[$page]);
