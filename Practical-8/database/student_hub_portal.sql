CREATE DATABASE IF NOT EXISTS student_hub_portal
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_hub_portal;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    mobile VARCHAR(25) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    address VARCHAR(1000) NOT NULL,
    course VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    division VARCHAR(20) NOT NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL UNIQUE,
    event_date DATE NOT NULL,
    location VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    form_student_id VARCHAR(50) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    mobile VARCHAR(25) NOT NULL,
    email VARCHAR(254) NOT NULL,
    address VARCHAR(1000) NOT NULL,
    course VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    division VARCHAR(20) NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

DELIMITER //
DROP PROCEDURE IF EXISTS upgrade_student_hub_registrations//
CREATE PROCEDURE upgrade_student_hub_registrations()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'registrations'
          AND COLUMN_NAME = 'registration_id'
    ) OR EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'registrations'
          AND COLUMN_NAME = 'event_id'
    ) THEN
        DROP TABLE IF EXISTS registrations_upgrade;

        CREATE TABLE registrations_upgrade (
            registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            form_student_id VARCHAR(50) NOT NULL,
            full_name VARCHAR(200) NOT NULL,
            date_of_birth DATE NOT NULL,
            gender ENUM('Male', 'Female') NOT NULL,
            mobile VARCHAR(25) NOT NULL,
            email VARCHAR(254) NOT NULL,
            address VARCHAR(1000) NOT NULL,
            course VARCHAR(100) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            division VARCHAR(20) NOT NULL,
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_registrations_upgrade_student
                FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
        );

        INSERT INTO registrations_upgrade
            (student_id, form_student_id, full_name, date_of_birth, gender, mobile, email, address, course, academic_year, division)
        SELECT
            old_registration.student_id,
            student.student_id,
            student.full_name,
            student.date_of_birth,
            student.gender,
            student.mobile,
            student.email,
            student.address,
            student.course,
            student.academic_year,
            student.division
        FROM registrations AS old_registration
        JOIN students AS student ON student.id = old_registration.student_id;

        DROP TABLE registrations;
        RENAME TABLE registrations_upgrade TO registrations;
    END IF;
END//
CALL upgrade_student_hub_registrations()//
DROP PROCEDURE upgrade_student_hub_registrations//
DELIMITER ;

INSERT IGNORE INTO students
    (student_id, full_name, date_of_birth, gender, mobile, email, address, course, academic_year, division)
VALUES
    ('STU1001', 'Aarav Shah', '2005-04-12', 'Male', '9876501001', 'aarav.shah@example.com', 'Mumbai', 'Computer Engineering', 'Second Year', 'A'),
    ('STU1002', 'Anaya Patel', '2006-08-23', 'Female', '9876501002', 'anaya.patel@example.com', 'Pune', 'Information Technology', 'First Year', 'B'),
    ('STU1003', 'Kabir Desai', '2004-11-05', 'Male', '9876501003', 'kabir.desai@example.com', 'Nashik', 'Mechanical Engineering', 'Third Year', 'A'),
    ('STU1004', 'Ira Kulkarni', '2005-02-17', 'Female', '9876501004', 'ira.kulkarni@example.com', 'Nagpur', 'Computer Engineering', 'Second Year', 'C');

INSERT IGNORE INTO events (title, event_date, location)
VALUES
    ('Barracks', '2026-11-07', 'Campus Grounds'),
    ('National Hackathon', '2026-11-21', 'Innovation Centre'),
    ('Tech Fest', '2026-12-05', 'Main Auditorium'),
    ('Career Guidance Workshop', '2026-12-12', 'Seminar Hall');
