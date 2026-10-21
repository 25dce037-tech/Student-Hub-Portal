<?php
declare(strict_types=1);

require_once __DIR__ . '/php/auth.php';
startAuthSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.html', true, 302);
    exit;
}

function adminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$stats = ['users' => 0, 'students' => 0, 'events' => 0];
$roles = ['admin' => 0, 'student' => 0];
$registrations = [];
$loadError = false;

try {
    require_once __DIR__ . '/php/database.php';

    foreach (['users' => 'user_accounts', 'students' => 'students', 'events' => 'events'] as $key => $table) {
        $statement = $conn->prepare("SELECT COUNT(*) AS total FROM `$table`");
        $statement->execute();
        $stats[$key] = (int) $statement->get_result()->fetch_assoc()['total'];
        $statement->close();
    }

    $statement = $conn->prepare('SELECT `role`, COUNT(*) AS total FROM user_accounts GROUP BY `role`');
    $statement->execute();
    foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $roleCount) {
        if (isset($roles[$roleCount['role']])) {
            $roles[$roleCount['role']] = (int) $roleCount['total'];
        }
    }
    $statement->close();

    $statement = $conn->prepare(
        'SELECT full_name, email, course, registered_at
         FROM registrations ORDER BY registered_at DESC LIMIT 5'
    );
    $statement->execute();
    $registrations = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Admin dashboard database error: ' . $exception->getMessage());
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    $loadError = true;
}

$navItems = [
    'Profile' => 'pages/Profile.html',
    'Attendance' => 'pages/Attendance.html',
    'Timetable' => 'pages/Timetable.html',
    'Courses' => 'pages/Courses.html',
    'Assignments' => 'pages/Assignment.html',
    'Results' => 'pages/Result.html',
    'Notices' => 'pages/Notices.html',
    'Students' => 'pages/Students.php',
    'Events' => 'pages/Events.php',
];
$csrf = csrfToken();
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator Dashboard</title>
    <link rel="stylesheet" href="css/script.css">
</head>
<body>
    <h1 class="page-header">Administrator Dashboard</h1>
    <main>
        <nav class="home-nav" aria-label="Administrator navigation">
            <div class="home-nav-links">
                <?php foreach ($navItems as $label => $href): ?>
                    <a href="<?= adminEscape($href) ?>" class="btn"><?= adminEscape($label) ?></a>
                <?php endforeach; ?>
                <form action="php/logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= adminEscape($csrf) ?>">
                    <button type="submit" class="btn">Logout</button>
                </form>
            </div>
        </nav>
        <?php if ($loadError): ?>
            <section class="card">
                <p class="student-message error" role="alert">Dashboard statistics are unavailable. Check the database setup.</p>
            </section>
        <?php else: ?>
            <section class="stats-grid" aria-label="Portal statistics">
                <article class="stat-card"><h4>Total Users</h4><p><?= $stats['users'] ?></p><small><?= $roles['student'] ?> students · <?= $roles['admin'] ?> admins</small></article>
                <article class="stat-card"><h4>Students</h4><p><?= $stats['students'] ?></p></article>
                <article class="stat-card"><h4>Events</h4><p><?= $stats['events'] ?></p></article>
            </section>
            <section class="card">
                <h2>Latest Registrations</h2>
                <div class="student-table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Course</th><th>Registered</th></tr></thead>
                        <tbody>
                            <?php if ($registrations === []): ?>
                                <tr><td colspan="4">No registrations yet.</td></tr>
                            <?php else: foreach ($registrations as $registration): ?>
                                <tr>
                                    <td><?= adminEscape($registration['full_name']) ?></td>
                                    <td><?= adminEscape($registration['email']) ?></td>
                                    <td><?= adminEscape($registration['course']) ?></td>
                                    <td><?= adminEscape($registration['registered_at']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <footer><p>Student Hub Portal</p></footer>
</body>
</html>
