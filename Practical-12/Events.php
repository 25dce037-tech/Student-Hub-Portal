<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
startAuthSession();
requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

function eventEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eventRedirect(string $message): never
{
    header('Location: Events.php?message=' . rawurlencode($message), true, 303);
    exit;
}

const EVENT_MAX_POSTER_SIZE = 2097152;
$posterDirectory = __DIR__ . '/../uploads/event-posters';
$posterUrl = '../uploads/event-posters/';
$fields = ['title' => '', 'event_date' => '', 'location' => ''];
$message = '';
$messageType = '';
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT);
$editId = $editId !== false && $editId > 0 ? $editId : 0;
$notices = [
    'added' => 'Event added.',
    'updated' => 'Event updated.',
    'deleted' => 'Event deleted.',
];
$notice = $_GET['message'] ?? '';
if (is_string($notice) && isset($notices[$notice])) {
    $message = $notices[$notice];
    $messageType = 'success';
}

try {
    require_once __DIR__ . '/../php/database.php';
} catch (mysqli_sql_exception $exception) {
    error_log('Event management connection error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Events are unavailable. Check the database connection.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($fields as $field => $_) {
        $value = $_POST[$field] ?? '';
        $fields[$field] = is_string($value) ? trim($value) : '';
    }
    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id !== false && $id > 0 ? $id : 0;
    $newPoster = '';
    $oldPoster = '';

    if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Session expired. Refresh and try again.';
        $messageType = 'error';
    } else {
        try {
            if ($action === 'delete' && $id > 0) {
                $conn->begin_transaction();
                $statement = $conn->prepare('SELECT poster_path FROM events WHERE id = ?');
                $statement->bind_param('i', $id);
                $statement->execute();
                $event = $statement->get_result()->fetch_assoc();
                $statement->close();

                if (!$event) {
                    $conn->rollback();
                    $message = 'Event not found.';
                    $messageType = 'error';
                } else {
                    $statement = $conn->prepare('DELETE FROM event_registrations WHERE event_id = ?');
                    $statement->bind_param('i', $id);
                    $statement->execute();
                    $statement->close();
                    $statement = $conn->prepare('DELETE FROM events WHERE id = ?');
                    $statement->bind_param('i', $id);
                    $statement->execute();
                    $statement->close();
                    $conn->commit();
                    if (
                        is_string($event['poster_path'])
                        && $event['poster_path'] !== ''
                    ) {
                        $posterFile = $posterDirectory . DIRECTORY_SEPARATOR . basename($event['poster_path']);
                        if (is_file($posterFile) && !unlink($posterFile)) {
                            error_log('Could not remove poster for deleted event ' . $id);
                        }
                    }
                    eventRedirect('deleted');
                }
            } elseif (in_array($action, ['add', 'edit'], true) && ($action === 'add' || $id > 0)) {
                if (
                    $fields['title'] === ''
                    || strlen($fields['title']) > 200
                    || $fields['location'] === ''
                    || strlen($fields['location']) > 255
                ) {
                    $message = 'Enter a title and location within the allowed lengths.';
                }
                $date = DateTime::createFromFormat('!Y-m-d', $fields['event_date']);
                if (
                    $message === ''
                    && ($date === false || $date->format('Y-m-d') !== $fields['event_date'])
                ) {
                    $message = 'Enter a valid event date.';
                }

                $upload = $_FILES['poster'] ?? null;
                if ($message === '' && is_array($upload) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($upload['error'] !== UPLOAD_ERR_OK) {
                        $message = 'Poster upload failed. Try a smaller image.';
                    } elseif ($upload['size'] > EVENT_MAX_POSTER_SIZE) {
                        $message = 'Poster must be 2 MB or smaller.';
                    } else {
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                        $types = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                        ];
                        $image = @getimagesize($upload['tmp_name']);
                        if (
                            !is_string($mime)
                            || !isset($types[$mime])
                            || $image === false
                            || $image['mime'] !== $mime
                        ) {
                            $message = 'Poster must be a valid JPG, PNG, or WebP image.';
                        } elseif (!is_dir($posterDirectory) && !mkdir($posterDirectory, 0755, true) && !is_dir($posterDirectory)) {
                            throw new RuntimeException('Unable to create poster directory.');
                        } else {
                            $newPoster = bin2hex(random_bytes(16)) . '.' . $types[$mime];
                            if (!move_uploaded_file($upload['tmp_name'], $posterDirectory . DIRECTORY_SEPARATOR . $newPoster)) {
                                $newPoster = '';
                                $message = 'Could not save the uploaded poster.';
                            }
                        }
                    }
                }

                if ($message === '' && $action === 'edit') {
                    $statement = $conn->prepare('SELECT poster_path FROM events WHERE id = ?');
                    $statement->bind_param('i', $id);
                    $statement->execute();
                    $current = $statement->get_result()->fetch_assoc();
                    $statement->close();
                    if (!$current) {
                        $message = 'Event not found.';
                    } else {
                        $oldPoster = is_string($current['poster_path']) ? $current['poster_path'] : '';
                    }
                }

                if ($message === '') {
                    $poster = $newPoster !== '' ? $newPoster : $oldPoster;
                    if ($action === 'add') {
                        $statement = $conn->prepare(
                            'INSERT INTO events (title, event_date, location, poster_path) VALUES (?, ?, ?, ?)'
                        );
                        $statement->bind_param('ssss', $fields['title'], $fields['event_date'], $fields['location'], $poster);
                    } else {
                        $statement = $conn->prepare(
                            'UPDATE events SET title = ?, event_date = ?, location = ?, poster_path = ? WHERE id = ?'
                        );
                        $statement->bind_param('ssssi', $fields['title'], $fields['event_date'], $fields['location'], $poster, $id);
                    }
                    $statement->execute();
                    $statement->close();

                    if ($newPoster !== '' && $oldPoster !== '') {
                        $oldFile = $posterDirectory . DIRECTORY_SEPARATOR . basename($oldPoster);
                        if (is_file($oldFile) && !unlink($oldFile)) {
                            error_log('Could not remove replaced event poster for event ' . $id);
                        }
                    }
                    eventRedirect($action === 'add' ? 'added' : 'updated');
                }
                if ($newPoster !== '') {
                    $newFile = $posterDirectory . DIRECTORY_SEPARATOR . $newPoster;
                    if (is_file($newFile) && !unlink($newFile)) {
                        error_log('Could not clean up rejected poster upload.');
                    }
                }
                if ($action === 'edit') {
                    $editId = $id;
                }
                $messageType = 'error';
            } else {
                $message = 'Invalid event request.';
                $messageType = 'error';
            }
        } catch (mysqli_sql_exception $exception) {
            try {
                $conn->rollback();
            } catch (mysqli_sql_exception $rollbackException) {
                error_log('Event management rollback error: ' . $rollbackException->getMessage());
            }
            if ($newPoster !== '') {
                $newFile = $posterDirectory . DIRECTORY_SEPARATOR . $newPoster;
                if (is_file($newFile) && !unlink($newFile)) {
                    error_log('Could not clean up poster after database failure.');
                }
            }
            error_log('Event management database error: ' . $exception->getMessage());
            $message = $exception->getCode() === 1062
                ? 'An event with that title already exists.'
                : 'The database could not complete the request.';
            $messageType = 'error';
            $editId = $id;
        } catch (RuntimeException $exception) {
            if ($newPoster !== '') {
                $newFile = $posterDirectory . DIRECTORY_SEPARATOR . $newPoster;
                if (is_file($newFile) && !unlink($newFile)) {
                    error_log('Could not clean up poster after storage failure.');
                }
            }
            error_log('Event poster storage error: ' . $exception->getMessage());
            $message = 'Could not save the poster. Check the uploads folder permissions.';
            $messageType = 'error';
            $editId = $id;
        }
    }
}

$events = [];
try {
    if ($editId > 0 && $messageType !== 'error') {
        $statement = $conn->prepare('SELECT title, event_date, location FROM events WHERE id = ?');
        $statement->bind_param('i', $editId);
        $statement->execute();
        $event = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($event) {
            $fields = $event;
        } else {
            $editId = 0;
            $message = 'Event not found.';
            $messageType = 'error';
        }
    }
    $result = $conn->prepare(
        'SELECT id, title, event_date, location, poster_path FROM events ORDER BY event_date'
    );
    $result->execute();
    $events = $result->get_result()->fetch_all(MYSQLI_ASSOC);
    $result->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Event management load error: ' . $exception->getMessage());
    $message = 'Could not load events. Check the database setup.';
    $messageType = 'error';
}
$token = csrfToken();
$conn->close();
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Events</title>
    <link rel="stylesheet" href="../css/script.css">
</head>
<body>
    <h1 class="page-header">Manage Events</h1>
    <main>
        <section class="card">
            <p><a class="btn" href="../admin.html">Admin Dashboard</a></p>
            <?php if ($message !== ''): ?>
                <p class="student-message <?= eventEscape($messageType) ?>" role="status"><?= eventEscape($message) ?></p>
            <?php endif; ?>
            <h2><?= $editId > 0 ? 'Edit Event' : 'Add Event' ?></h2>
            <form class="event-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= eventEscape($token) ?>">
                <input type="hidden" name="action" value="<?= $editId > 0 ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" value="<?= $editId ?>">
                <label>Event title
                    <input name="title" maxlength="200" value="<?= eventEscape($fields['title']) ?>" required>
                </label>
                <label>Date
                    <input type="date" name="event_date" value="<?= eventEscape($fields['event_date']) ?>" required>
                </label>
                <label>Location
                    <input name="location" maxlength="255" value="<?= eventEscape($fields['location']) ?>" required>
                </label>
                <label>Poster (JPG, PNG, WebP; max 2 MB)
                    <input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </label>
                <button class="btn" type="submit"><?= $editId > 0 ? 'Save Event' : 'Add Event' ?></button>
                <?php if ($editId > 0): ?><a class="btn" href="Events.php">Cancel</a><?php endif; ?>
            </form>
        </section>
        <section class="card">
            <h2>Events</h2>
            <div class="student-table-wrap">
                <table>
                    <thead><tr><th>Poster</th><th>Event</th><th>Date</th><th>Location</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($events === []): ?>
                        <tr><td colspan="5">No events found.</td></tr>
                    <?php else: foreach ($events as $event): ?>
                        <tr>
                            <td>
                                <?php if (is_string($event['poster_path']) && $event['poster_path'] !== ''): ?>
                                    <img class="event-poster" src="<?= eventEscape($posterUrl . rawurlencode(basename($event['poster_path']))) ?>" alt="Poster for <?= eventEscape($event['title']) ?>">
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= eventEscape($event['title']) ?></td>
                            <td><?= eventEscape($event['event_date']) ?></td>
                            <td><?= eventEscape($event['location']) ?></td>
                            <td><div class="student-actions">
                                <a class="btn" href="Events.php?edit=<?= (int) $event['id'] ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this event?')">
                                    <input type="hidden" name="csrf_token" value="<?= eventEscape($token) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                                    <button class="btn" type="submit">Delete</button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <footer><p>Student Hub Portal</p></footer>
</body>
</html>
