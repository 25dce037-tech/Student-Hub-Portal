<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
startAuthSession();
requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}
$csrf = csrfToken();
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>Manage Events</title>
    <link rel="stylesheet" href="../css/script.css">
    <script src="../js/events.js" defer></script>
</head>
<body>
    <h1 class="page-header">Manage Events</h1>
    <main>
        <section class="card">
            <p><a class="btn" href="../admin.html">Admin Dashboard</a></p>
            <p id="event-message" class="student-message" role="status" hidden></p>
            <h2 id="event-form-title">Add Event</h2>
            <form id="event-form" class="event-form" enctype="multipart/form-data">
                <input type="hidden" name="id" value="">
                <label>Event title
                    <input name="title" maxlength="200" required>
                </label>
                <label>Date
                    <input type="date" name="event_date" required>
                </label>
                <label>Location
                    <input name="location" maxlength="255" required>
                </label>
                <label>Poster (JPG, PNG, WebP; max 2 MB)
                    <input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </label>
                <div class="student-actions">
                    <button id="event-submit" class="btn" type="submit">Add Event</button>
                    <button id="event-cancel" class="btn" type="button" hidden>Cancel</button>
                </div>
            </form>
        </section>
        <section class="card">
            <h2>Events</h2>
            <div class="student-table-wrap">
                <table>
                    <thead><tr><th>Poster</th><th>Event</th><th>Date</th><th>Location</th><th>Actions</th></tr></thead>
                    <tbody id="event-list"><tr><td colspan="5">Loading events…</td></tr></tbody>
                </table>
            </div>
        </section>
    </main>
    <footer><p>Student Hub Portal</p></footer>
</body>
</html>
