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

function protectCsvCell(string $value): string
{
    if (preg_match('/^[=+\-@\t\r\n]/', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

function rollbackRegistration(
    ?mysqli $conn,
    bool $transactionStarted,
    $csvFile,
    ?int $csvOriginalSize
): void {
    if (is_resource($csvFile)) {
        if ($csvOriginalSize !== null) {
            if (!ftruncate($csvFile, $csvOriginalSize) || !fflush($csvFile)) {
                error_log('Could not restore registrations CSV after a failed student registration.');
            }
        }

        flock($csvFile, LOCK_UN);
        fclose($csvFile);
    }

    if ($conn !== null) {
        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (mysqli_sql_exception $exception) {
                error_log('Could not roll back student registration: ' . $exception->getMessage());
            }
        }

        $conn->close();
    }
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
        $conn = null;
        $statement = null;
        $transactionStarted = false;
        $csvFile = false;
        $csvOriginalSize = null;

        try {
            require_once __DIR__ . '/database.php';
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
            $studentRecordId = (int) $conn->insert_id;
            $statement->close();

            $statement = $conn->prepare(
                'INSERT INTO registrations
                    (student_id, form_student_id, full_name, date_of_birth, gender, mobile, email, address, course, academic_year, division)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'issssssssss',
                $studentRecordId,
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

            $csvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'registrations.csv';
            $csvFile = fopen($csvPath, 'c+');
            if ($csvFile === false) {
                throw new RuntimeException('Could not open registrations CSV for writing.');
            }

            if (!flock($csvFile, LOCK_EX)) {
                throw new RuntimeException('Could not lock registrations CSV for writing.');
            }

            $fileInfo = fstat($csvFile);
            if ($fileInfo === false) {
                throw new RuntimeException('Could not inspect registrations CSV.');
            }
            $csvOriginalSize = (int) $fileInfo['size'];

            $csvHeader = [
                'submitted_at',
                'full_name',
                'student_id',
                'date_of_birth',
                'gender',
                'mobile',
                'email',
                'address',
                'course',
                'year',
                'division',
                'legacy_password_hash',
            ];

            if ($csvOriginalSize === 0) {
                if (fputcsv($csvFile, $csvHeader, ',', '"', '') === false) {
                    throw new RuntimeException('Could not write registrations CSV header.');
                }
            } else {
                rewind($csvFile);
                $existingHeader = fgetcsv($csvFile, null, ',', '"', '');
                if ($existingHeader !== $csvHeader) {
                    throw new RuntimeException('Registrations CSV header does not match the expected format.');
                }
            }

            if (fseek($csvFile, 0, SEEK_END) !== 0) {
                throw new RuntimeException('Could not seek to the end of registrations CSV.');
            }

            $csvRow = [
                gmdate('c'),
                protectCsvCell($values['full_name']),
                protectCsvCell($values['student_id']),
                $values['date_of_birth'],
                $values['gender'],
                protectCsvCell($values['mobile']),
                protectCsvCell($values['email']),
                protectCsvCell($values['address']),
                protectCsvCell($values['course']),
                protectCsvCell($values['year']),
                protectCsvCell($values['division']),
                '',
            ];
            if (fputcsv($csvFile, $csvRow, ',', '"', '') === false || !fflush($csvFile)) {
                throw new RuntimeException('Could not append registration to CSV.');
            }

            $conn->commit();
            $transactionStarted = false;
            flock($csvFile, LOCK_UN);
            fclose($csvFile);
            $csvFile = false;
            $conn->close();
            $conn = null;

            $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
            header('Location: ../index.html', true, 303);
            exit;
        } catch (mysqli_sql_exception $exception) {
            if ($statement instanceof mysqli_stmt) {
                $statement->close();
            }
            rollbackRegistration($conn, $transactionStarted, $csvFile, $csvOriginalSize);
            $conn = null;
            $csvFile = false;

            if ((int) $exception->getCode() === 1062) {
                $errors[] = 'That student ID or email address is already registered.';
            } else {
                error_log('Student registration database error: ' . $exception->getMessage());
                $errors[] = 'Registration could not be saved. Please try again later.';
            }
        } catch (RuntimeException $exception) {
            if ($statement instanceof mysqli_stmt) {
                $statement->close();
            }
            error_log('Student registration CSV error: ' . $exception->getMessage());
            rollbackRegistration($conn, $transactionStarted, $csvFile, $csvOriginalSize);
            $conn = null;
            $csvFile = false;
            $errors[] = 'Registration could not be saved to both the database and CSV. Check that the data folder is writable and that registrations.csv has its original header.';
        } catch (Throwable $exception) {
            if ($statement instanceof mysqli_stmt) {
                $statement->close();
            }
            error_log('Unexpected student registration error: ' . $exception->getMessage());
            rollbackRegistration($conn, $transactionStarted, $csvFile, $csvOriginalSize);
            $conn = null;
            $csvFile = false;
            $errors[] = 'Registration could not be saved. Please try again later.';
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