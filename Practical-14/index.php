<?php
declare(strict_types=1);

require_once __DIR__ . '/php/auth.php';
startAuthSession();
requireLogin();

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: admin.html', true, 302);
    exit;
}

header('Cache-Control: no-store');
$page = file_get_contents(__DIR__ . '/index.html');
if ($page === false) {
    http_response_code(500);
    exit('Dashboard could not be loaded.');
}

$studentLinks = [
    'Profile' => 'pages/Profile.html',
    'Attendance' => 'pages/Attendance.html',
    'Timetable' => 'pages/Timetable.html',
    'Courses' => 'pages/Courses.html',
    'Assignments' => 'pages/Assignment.html',
    'Results' => 'pages/Result.html',
    'Notices' => 'pages/Notices.html',
    'Contact' => 'pages/Contact.html',
];
$navigation = '';
foreach ($studentLinks as $label => $href) {
    $navigation .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="btn">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
}
$navigation .= '<form action="php/logout.php" method="post">'
    . '<input type="hidden" name="csrf_token" id="logout-csrf-token">'
    . '<button type="submit" class="btn">Logout</button></form>';
$page = preg_replace(
    '~<div class="home-nav-links">.*?</div>~s',
    '<div class="home-nav-links">' . $navigation . '</div>',
    $page,
    1
) ?? $page;
echo $page;