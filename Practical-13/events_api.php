<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
startAuthSession();

function eventApiResponse(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    eventApiResponse(401, ['message' => 'Please sign in again.']);
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    eventApiResponse(403, ['message' => 'Administrator access required.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    eventApiResponse(405, ['message' => 'Method not allowed.']);
}

try {
    require_once __DIR__ . '/database.php';
} catch (mysqli_sql_exception $exception) {
    error_log('Event API connection error: ' . $exception->getMessage());
    eventApiResponse(500, ['message' => 'Events are temporarily unavailable.']);
}

$posterDirectory = __DIR__ . '/../uploads/event-posters';
$posterMaxSize = 2 * 1024 * 1024;
$newPoster = '';
$transactionStarted = false;

try {
    if ($method === 'GET') {
        $statement = $conn->prepare(
            'SELECT id, title, event_date, location, poster_path FROM events ORDER BY event_date, id'
        );
        $statement->execute();
        $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        foreach ($events as &$event) {
            $event['poster_path'] = is_string($event['poster_path'])
                ? basename($event['poster_path'])
                : '';
        }
        unset($event);
        $conn->close();
        eventApiResponse(200, ['events' => $events]);
    }

    if (!validCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        $conn->close();
        eventApiResponse(403, ['message' => 'Session expired. Refresh and try again.']);
    }

    $action = $_POST['action'] ?? '';
    $action = is_string($action) ? $action : '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id !== false && $id > 0 ? $id : 0;

    if ($action === 'delete' && $id > 0) {
        $conn->begin_transaction();
        $transactionStarted = true;
        $statement = $conn->prepare('SELECT poster_path FROM events WHERE id = ?');
        $statement->bind_param('i', $id);
        $statement->execute();
        $event = $statement->get_result()->fetch_assoc();
        $statement->close();
        if (!$event) {
            $conn->rollback();
            $transactionStarted = false;
            $conn->close();
            eventApiResponse(404, ['message' => 'Event not found.']);
        }
        $statement = $conn->prepare('DELETE FROM events WHERE id = ?');
        $statement->bind_param('i', $id);
        $statement->execute();
        $statement->close();
        $conn->commit();
        $transactionStarted = false;
        $oldPoster = is_string($event['poster_path']) ? $event['poster_path'] : '';
        if ($oldPoster !== '') {
            $file = $posterDirectory . DIRECTORY_SEPARATOR . basename($oldPoster);
            if (is_file($file) && !unlink($file)) {
                error_log('Could not remove poster for deleted event ' . $id);
            }
        }
        $conn->close();
        eventApiResponse(200, ['message' => 'Event deleted.']);
    }

    if (!in_array($action, ['add', 'update'], true) || ($action === 'update' && $id === 0)) {
        $conn->close();
        eventApiResponse(400, ['message' => 'Choose a valid event action.']);
    }

    $title = trim(is_string($_POST['title'] ?? null) ? $_POST['title'] : '');
    $date = trim(is_string($_POST['event_date'] ?? null) ? $_POST['event_date'] : '');
    $location = trim(is_string($_POST['location'] ?? null) ? $_POST['location'] : '');
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
    if (
        $title === '' || strlen($title) > 200
        || $location === '' || strlen($location) > 255
        || $parsedDate === false || $parsedDate->format('Y-m-d') !== $date
    ) {
        $conn->close();
        eventApiResponse(422, ['message' => 'Enter a title, valid date, and location.']);
    }

    $oldPoster = '';
    if ($action === 'update') {
        $statement = $conn->prepare('SELECT poster_path FROM events WHERE id = ?');
        $statement->bind_param('i', $id);
        $statement->execute();
        $existing = $statement->get_result()->fetch_assoc();
        $statement->close();
        if (!$existing) {
            $conn->close();
            eventApiResponse(404, ['message' => 'Event not found.']);
        }
        $oldPoster = is_string($existing['poster_path']) ? $existing['poster_path'] : '';
    }

    $upload = $_FILES['poster'] ?? null;
    if (is_array($upload) && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > $posterMaxSize) {
            $conn->close();
            eventApiResponse(422, ['message' => 'Poster must be a valid image no larger than 2 MB.']);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $image = @getimagesize($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime]) || $image === false || $image['mime'] !== $mime) {
            $conn->close();
            eventApiResponse(422, ['message' => 'Poster must be a JPG, PNG, or WebP image.']);
        }
        if (!is_dir($posterDirectory) && !mkdir($posterDirectory, 0755, true) && !is_dir($posterDirectory)) {
            $conn->close();
            eventApiResponse(500, ['message' => 'Could not create the poster upload folder.']);
        }
        $newPoster = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $posterDirectory . DIRECTORY_SEPARATOR . $newPoster)) {
            $newPoster = '';
            $conn->close();
            eventApiResponse(500, ['message' => 'Could not save the poster.']);
        }
    }

    $poster = $newPoster !== '' ? $newPoster : $oldPoster;
    if ($action === 'add') {
        $statement = $conn->prepare(
            'INSERT INTO events (title, event_date, location, poster_path) VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param('ssss', $title, $date, $location, $poster);
    } else {
        $statement = $conn->prepare(
            'UPDATE events SET title = ?, event_date = ?, location = ?, poster_path = ? WHERE id = ?'
        );
        $statement->bind_param('ssssi', $title, $date, $location, $poster, $id);
    }
    $statement->execute();
    $eventId = $action === 'add' ? $conn->insert_id : $id;
    $statement->close();
    $conn->close();

    if ($newPoster !== '' && $oldPoster !== '') {
        $oldFile = $posterDirectory . DIRECTORY_SEPARATOR . basename($oldPoster);
        if (is_file($oldFile) && !unlink($oldFile)) {
            error_log('Could not remove replaced event poster for event ' . $id);
        }
    }
    eventApiResponse($action === 'add' ? 201 : 200, [
        'message' => $action === 'add' ? 'Event added.' : 'Event updated.',
        'id' => $eventId,
    ]);
} catch (mysqli_sql_exception $exception) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (mysqli_sql_exception $rollbackException) {
            error_log('Event API rollback error: ' . $rollbackException->getMessage());
        }
    }
    if ($newPoster !== '') {
        $file = $posterDirectory . DIRECTORY_SEPARATOR . $newPoster;
        if (is_file($file) && !unlink($file)) {
            error_log('Could not clean up poster after event API failure.');
        }
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('Event API database error: ' . $exception->getMessage());
    if ($exception->getCode() === 1062) {
        eventApiResponse(409, ['message' => 'An event with that title already exists.']);
    }
    eventApiResponse(500, ['message' => 'The database could not complete the request.']);
} catch (RuntimeException $exception) {
    if ($newPoster !== '') {
        $file = $posterDirectory . DIRECTORY_SEPARATOR . $newPoster;
        if (is_file($file) && !unlink($file)) {
            error_log('Could not clean up poster after event API failure.');
        }
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    error_log('Event API error: ' . $exception->getMessage());
    eventApiResponse(500, ['message' => 'The event request could not be completed.']);
}
