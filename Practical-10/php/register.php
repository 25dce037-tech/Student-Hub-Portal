<?php
declare(strict_types=1);

session_start();

function cleanInput(string $value): string
{
    return trim(strip_tags($value));
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$values = [
    'full_name' => '',
    'student_id' => '',
    'date_of_birth' => '',
    'gender' => '',
    'mobile' => '',
    'email' => '',
    'address' => '',
    'course' => '',
    'year' => '',
    'division' => '',
];
$errors = [];

if (!isset($_SESSION['registration_csrf_token'])) {
    $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['csrf_token'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['csrf_token' => $_SESSION['registration_csrf_token']], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Location: ../pages/Register.html', true, 302);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($values as $field => $_value) {
        $postedValue = $_POST[$field] ?? '';
        $values[$field] = is_string($postedValue) ? cleanInput($postedValue) : '';
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    $submittedToken = is_string($submittedToken) ? $submittedToken : '';

    if (!hash_equals($_SESSION['registration_csrf_token'], $submittedToken)) {
        $errors[] = 'Your session has expired. Please submit the form again.';
    }

    if ($values['full_name'] === '' || strlen($values['full_name']) > 200) {
        $errors[] = 'Enter your name using no more than 200 characters.';
    }

    if ($values['student_id'] === '' || strlen($values['student_id']) > 50) {
        $errors[] = 'Enter a student ID using no more than 50 characters.';
    }

    $dateOfBirth = DateTime::createFromFormat('!Y-m-d', $values['date_of_birth']);
    if (
        $dateOfBirth === false
        || $dateOfBirth->format('Y-m-d') !== $values['date_of_birth']
        || $dateOfBirth > new DateTime('today')
    ) {
        $errors[] = 'Enter a valid date of birth that is not in the future.';
    }

    if (!in_array($values['gender'], ['Male', 'Female'], true)) {
        $errors[] = 'Select Male or Female for gender.';
    }

    if (
        $values['mobile'] === ''
        || strlen($values['mobile']) > 25
        || preg_match('/^[0-9+(). -]{7,25}$/', $values['mobile']) !== 1
    ) {
        $errors[] = 'Enter a valid mobile number.';
    }

    if (
        $values['email'] === ''
        || strlen($values['email']) > 254
        || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 bytes.';
    }

    if ($values['address'] === '' || strlen($values['address']) > 1000) {
        $errors[] = 'Enter an address using no more than 1,000 characters.';
    }

    if (!in_array($values['course'], [
        'Computer Engineering',
        'Information Technology',
        'Mechanical Engineering',
    ], true)) {
        $errors[] = 'Select a valid course.';
    }

    if (!in_array($values['year'], [
        'First Year',
        'Second Year',
        'Third Year',
        'Fourth Year',
    ], true)) {
        $errors[] = 'Select a valid year.';
    }

    if ($values['division'] === '' || strlen($values['division']) > 20) {
        $errors[] = 'Enter a division using no more than 20 characters.';
    }

    if ($errors === []) {
        $transactionStarted = false;
        try {
            require_once __DIR__ . '/database.php';

            $statement = $conn->prepare(
                'SELECT id FROM students WHERE email = ?
                 UNION SELECT registration_id FROM registrations WHERE email = ?
                 UNION SELECT id FROM user_accounts WHERE email = ? LIMIT 1'
            );
            $statement->bind_param('sss', $values['email'], $values['email'], $values['email']);
            $statement->execute();
            $statement->store_result();
            if ($statement->num_rows > 0) {
                $errors[] = 'That email address is already registered.';
            }
            $statement->close();

            $statement = $conn->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
            $statement->bind_param('s', $values['student_id']);
            $statement->execute();
            $statement->store_result();
            if ($statement->num_rows > 0) {
                $errors[] = 'That student ID is already registered.';
            }
            $statement->close();

            if ($errors !== []) {
                $conn->close();
            } else {
                $conn->begin_transaction();
                $transactionStarted = true;

                $statement = $conn->prepare(
                    'INSERT INTO students
                        (full_name, student_id, date_of_birth, gender, mobile, email, address, course, academic_year, division)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->bind_param(
                    'ssssssssss',
                    $values['full_name'],
                    $values['student_id'],
                    $values['date_of_birth'],
                    $values['gender'],
                    $values['mobile'],
                    $values['email'],
                    $values['address'],
                    $values['course'],
                    $values['year'],
                    $values['division']
                );
                $statement->execute();
                $statement->close();

                $studentDatabaseId = $conn->insert_id;
                $statement = $conn->prepare(
                    'INSERT INTO registrations
                        (student_id, form_student_id, full_name, date_of_birth, gender, mobile, email, address, course, academic_year, division)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->bind_param(
                    'issssssssss',
                    $studentDatabaseId,
                    $values['student_id'],
                    $values['full_name'],
                    $values['date_of_birth'],
                    $values['gender'],
                    $values['mobile'],
                    $values['email'],
                    $values['address'],
                    $values['course'],
                    $values['year'],
                    $values['division']
                );
                $statement->execute();
                $statement->close();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $statement = $conn->prepare(
                    'INSERT INTO user_accounts (student_id, email, password_hash)
                     VALUES (?, ?, ?)'
                );
                $statement->bind_param(
                    'iss',
                    $studentDatabaseId,
                    $values['email'],
                    $passwordHash
                );
                $statement->execute();
                $statement->close();

                $conn->commit();
                $transactionStarted = false;
                $conn->close();

                $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
                header('Location: ../pages/Register.html?registration=success', true, 303);
                exit;
            }
        } catch (mysqli_sql_exception $exception) {
            if (isset($conn) && $conn instanceof mysqli) {
                if ($transactionStarted) {
                    $conn->rollback();
                }
                $conn->close();
            }
            if ((int) $exception->getCode() === 1062) {
                $errors[] = 'That student ID or email address is already registered.';
            } elseif ((int) $exception->getCode() === 1146) {
                error_log('Student registration database setup is incomplete: ' . $exception->getMessage());
                $errors[] = 'The registration database is not fully set up. Import database/student_hub_portal.sql and try again.';
            } else {
                error_log('Student registration database error: ' . $exception->getMessage());
                $errors[] = 'Registration could not be saved. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration Error</title>
    <link rel="stylesheet" href="../css/script.css">
    <script src="../js/script.js" defer></script>
</head>
<body>
    <main>
        <h1>Registration</h1>
        <?php if ($errors !== []): ?>
            <div role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeHtml($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p><a href="../pages/Register.html">Return to the registration form</a></p>
    </main>
</body>
</html>