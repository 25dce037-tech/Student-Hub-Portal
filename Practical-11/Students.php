<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/auth.php';
startAuthSession();
requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Administrator access required.');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$fields = [
    'student_id' => '',
    'full_name' => '',
    'date_of_birth' => '',
    'gender' => '',
    'mobile' => '',
    'email' => '',
    'address' => '',
    'course' => '',
    'academic_year' => '',
    'division' => '',
];
$message = '';
$messageType = '';
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT);
$editId = $editId !== false && $editId > 0 ? $editId : 0;
$search = $_GET['search'] ?? '';
$search = is_string($search) ? trim(substr($search, 0, 100)) : '';
$course = $_GET['course'] ?? '';
$course = is_string($course) ? trim(substr($course, 0, 100)) : '';

if (isset($_GET['message']) && is_string($_GET['message'])) {
    $messages = [
        'added' => 'Student added successfully.',
        'updated' => 'Student updated successfully.',
        'deleted' => 'Student deleted successfully.',
    ];
    $message = $messages[$_GET['message']] ?? '';
    $messageType = 'success';
}

try {
    require_once __DIR__ . '/../php/database.php';
} catch (mysqli_sql_exception $exception) {
    error_log('Student management connection error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Student records are unavailable. Check the database connection.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($fields as $field => $_) {
        $value = $_POST[$field] ?? '';
        $fields[$field] = is_string($value) ? trim($value) : '';
    }
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id !== false && $id > 0 ? $id : 0;

    if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Session expired. Refresh the page and try again.';
        $messageType = 'error';
    } else {
        try {
            if ($action === 'delete' && $id > 0) {
                $statement = $conn->prepare('DELETE FROM students WHERE id = ?');
                $statement->bind_param('i', $id);
                $statement->execute();
                $deleted = $statement->affected_rows;
                $statement->close();
                if ($deleted === 1) {
                    header('Location: Students.php?message=deleted', true, 303);
                    exit;
                }
                $message = 'Student not found; nothing was deleted.';
            } elseif (in_array($action, ['add', 'edit'], true)) {
                foreach ($fields as $value) {
                    if ($value === '') {
                        $message = 'Please complete all fields.';
                        break;
                    }
                }
                if ($message === '' && filter_var($fields['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $message = 'Enter a valid email address.';
                }
                $date = DateTime::createFromFormat('!Y-m-d', $fields['date_of_birth']);
                if (
                    $message === ''
                    && ($date === false || $date->format('Y-m-d') !== $fields['date_of_birth'])
                ) {
                    $message = 'Enter a valid date of birth.';
                }
                if ($message === '' && !in_array($fields['gender'], ['Male', 'Female'], true)) {
                    $message = 'Select a valid gender.';
                }
                if ($message === '') {
                    $lengths = [
                        'student_id' => 50, 'full_name' => 200, 'mobile' => 25,
                        'email' => 254, 'address' => 1000, 'course' => 100,
                        'academic_year' => 20, 'division' => 20,
                    ];
                    foreach ($lengths as $field => $max) {
                        if (strlen($fields[$field]) > $max) {
                            $message = 'One or more fields exceed the allowed length.';
                            break;
                        }
                    }
                }

                if ($message === '' && $action === 'add') {
                    $statement = $conn->prepare(
                        'INSERT INTO students
                         (student_id, full_name, date_of_birth, gender, mobile, email, address, course, academic_year, division)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $statement->bind_param(
                        'ssssssssss',
                        $fields['student_id'], $fields['full_name'], $fields['date_of_birth'],
                        $fields['gender'], $fields['mobile'], $fields['email'], $fields['address'],
                        $fields['course'], $fields['academic_year'], $fields['division']
                    );
                    $statement->execute();
                    $statement->close();
                    header('Location: Students.php?message=added', true, 303);
                    exit;
                }
                if ($message === '' && $action === 'edit' && $id > 0) {
                    $statement = $conn->prepare(
                        'UPDATE students SET student_id=?, full_name=?, date_of_birth=?, gender=?,
                         mobile=?, email=?, address=?, course=?, academic_year=?, division=? WHERE id=?'
                    );
                    $statement->bind_param(
                        'ssssssssssi',
                        $fields['student_id'], $fields['full_name'], $fields['date_of_birth'],
                        $fields['gender'], $fields['mobile'], $fields['email'], $fields['address'],
                        $fields['course'], $fields['academic_year'], $fields['division'], $id
                    );
                    $statement->execute();
                    $statement->close();
                    header('Location: Students.php?message=updated', true, 303);
                    exit;
                }
                if ($action === 'edit') {
                    $editId = $id;
                }
            } else {
                $message = 'Invalid request.';
            }
            $messageType = 'error';
        } catch (mysqli_sql_exception $exception) {
            error_log('Student management database error: ' . $exception->getMessage());
            $message = $exception->getCode() === 1062
                ? 'Student ID or email already exists.'
                : 'The database could not complete the request.';
            $messageType = 'error';
            $editId = $id;
        }
    }
}

try {
    if ($editId > 0 && $messageType !== 'error') {
        $statement = $conn->prepare('SELECT * FROM students WHERE id = ?');
        $statement->bind_param('i', $editId);
        $statement->execute();
        $record = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($record) {
            foreach ($fields as $field => $_) {
                $fields[$field] = $record[$field];
            }
        } else {
            $editId = 0;
            $message = 'Student not found.';
            $messageType = 'error';
        }
    }

    $courseStatement = $conn->prepare('SELECT DISTINCT course FROM students ORDER BY course');
    $courseStatement->execute();
    $courses = $courseStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $courseStatement->close();

    $term = '%' . $search . '%';
    $list = $conn->prepare(
        'SELECT id, student_id, full_name, email, course, academic_year, division
         FROM students
         WHERE (student_id LIKE ? OR full_name LIKE ? OR email LIKE ?)
           AND (? = "" OR course = ?)
         ORDER BY full_name'
    );
    $list->bind_param('sssss', $term, $term, $term, $course, $course);
    $list->execute();
    $students = $list->get_result()->fetch_all(MYSQLI_ASSOC);
    $list->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Student management load error: ' . $exception->getMessage());
    $message = 'Could not load student records. Check the database connection.';
    $messageType = 'error';
    $students = [];
    $courses = [];
}
$token = csrfToken();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Students</title>
    <link rel="stylesheet" href="../css/script.css">
</head>
<body>
    <h1 class="page-header">Manage Students</h1>
    <main>
        <section class="card">
            <p><a class="btn" href="../admin.html">Admin Dashboard</a></p>
            <?php if ($message !== ''): ?>
                <p class="student-message <?= e($messageType) ?>" role="status"><?= e($message) ?></p>
            <?php endif; ?>
            <h2><?= $editId > 0 ? 'Edit Student' : 'Add Student' ?></h2>
            <form class="student-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="<?= $editId > 0 ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" value="<?= $editId ?>">
                <?php foreach ([
                    'student_id' => 'Student ID', 'full_name' => 'Full name',
                    'date_of_birth' => 'Date of birth', 'mobile' => 'Mobile',
                    'email' => 'Email', 'address' => 'Address', 'course' => 'Course',
                    'academic_year' => 'Academic year', 'division' => 'Division',
                ] as $field => $label): ?>
                    <label><?= e($label) ?>
                        <input
                            name="<?= e($field) ?>"
                            type="<?= $field === 'date_of_birth' ? 'date' : ($field === 'email' ? 'email' : 'text') ?>"
                            value="<?= e($fields[$field]) ?>"
                            required
                        >
                    </label>
                <?php endforeach; ?>
                <label>Gender
                    <select name="gender" required>
                        <option value="">Select</option>
                        <?php foreach (['Male', 'Female'] as $gender): ?>
                            <option value="<?= $gender ?>" <?= $fields['gender'] === $gender ? 'selected' : '' ?>><?= $gender ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Student' ?></button>
                <?php if ($editId > 0): ?><a class="btn" href="Students.php">Cancel</a><?php endif; ?>
            </form>
        </section>

        <section class="card">
            <h2>Student Records</h2>
            <form class="student-filters" method="get">
                <label>Search
                    <input name="search" value="<?= e($search) ?>" placeholder="ID, name or email">
                </label>
                <label>Course
                    <select name="course">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $item): ?>
                            <option value="<?= e($item['course']) ?>" <?= $course === $item['course'] ? 'selected' : '' ?>>
                                <?= e($item['course']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit">Filter</button>
            </form>
            <div class="student-table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Course</th><th>Year / Division</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($students === []): ?>
                        <tr><td colspan="6">No student records found.</td></tr>
                    <?php else: foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['student_id']) ?></td>
                            <td><?= e($student['full_name']) ?></td>
                            <td><?= e($student['email']) ?></td>
                            <td><?= e($student['course']) ?></td>
                            <td><?= e($student['academic_year']) ?> / <?= e($student['division']) ?></td>
                            <td><div class="student-actions">
                                <a class="btn" href="Students.php?edit=<?= (int) $student['id'] ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this student?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
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
