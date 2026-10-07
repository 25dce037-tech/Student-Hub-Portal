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
        $csvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'registrations.csv';
        $csvFile = @fopen($csvPath, 'a+');

        if ($csvFile === false) {
            error_log('Registration CSV could not be opened for writing: ' . $csvPath);
            $errors[] = 'Registration could not be saved. Close registrations.csv if it is open in Excel or another program, then submit again. If it still fails, check that the project data folder is writable by Apache.';
        } elseif (!flock($csvFile, LOCK_EX)) {
            fclose($csvFile);
            error_log('Registration CSV could not be locked: ' . $csvPath);
            $errors[] = 'Registration could not be saved. Please try again later.';
        } else {
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
            $fileSize = fstat($csvFile)['size'] ?? 0;
            $headerMatches = true;

            if ($fileSize > 0) {
                rewind($csvFile);
                $existingHeader = fgetcsv($csvFile, null, ',', '"', '');
                $headerMatches = $existingHeader === $csvHeader;
            } else {
                $headerMatches = fputcsv($csvFile, $csvHeader, ',', '"', '') !== false;
            }

            if (!$headerMatches) {
                error_log('Registration CSV has an unexpected header and needs migration: ' . $csvPath);
                $errors[] = 'The registrations CSV format needs to be updated. Close it in Excel and contact the site administrator.';
            } else {
                fseek($csvFile, 0, SEEK_END);
                $recordWritten = fputcsv(
                    $csvFile,
                    [
                        gmdate('c'),
                        protectCsvCell($values['full_name']),
                        protectCsvCell($values['student_id']),
                        $values['date_of_birth'],
                        $values['gender'],
                        protectCsvCell($values['mobile']),
                        protectCsvCell($values['email']),
                        protectCsvCell($values['address']),
                        $values['course'],
                        $values['year'],
                        protectCsvCell($values['division']),
                        '',
                    ],
                    ',',
                    '"',
                    ''
                ) !== false;

                if (!$recordWritten) {
                    error_log('Registration CSV record could not be written: ' . $csvPath);
                    $errors[] = 'Registration could not be saved. Check that the project data folder is writable by Apache.';
                }
            }

            flock($csvFile, LOCK_UN);
            fclose($csvFile);

            if ($errors === []) {
                $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
                header('Location: ../pages/Register.html?registration=success', true, 303);
                exit;
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