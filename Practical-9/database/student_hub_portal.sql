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

CREATE TABLE IF NOT EXISTS user_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    CONSTRAINT fk_user_accounts_student
        FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

DELIMITER //
DROP PROCEDURE IF EXISTS migrate_student_hub_user_accounts//
CREATE PROCEDURE migrate_student_hub_user_accounts()
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'user_accounts'
          AND COLUMN_NAME = 'username'
    ) THEN
        ALTER TABLE user_accounts DROP INDEX username, DROP COLUMN username;
    END IF;
END//
CALL migrate_student_hub_user_accounts()//
DROP PROCEDURE migrate_student_hub_user_accounts//
DELIMITER ;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL UNIQUE,
    event_date DATE NOT NULL,
    location VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS event_registrations (
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
);

DELIMITER //
DROP PROCEDURE IF EXISTS migrate_student_hub_registrations//
CREATE PROCEDURE migrate_student_hub_registrations()
BEGIN
    DECLARE missing_event_links INT DEFAULT 0;

    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'registrations'
          AND COLUMN_NAME = 'event_id'
    ) THEN
        INSERT IGNORE INTO event_registrations (student_id, event_id)
        SELECT student_id, event_id FROM registrations;

        SELECT COUNT(*)
        INTO missing_event_links
        FROM registrations AS old_registration
        LEFT JOIN event_registrations AS new_registration
            ON new_registration.student_id = old_registration.student_id
           AND new_registration.event_id = old_registration.event_id
        WHERE new_registration.student_id IS NULL;

        IF missing_event_links > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Some event registration links could not be migrated; the original table was preserved.';
        END IF;

        DROP TABLE registrations;
    END IF;
END//
CALL migrate_student_hub_registrations()//
DROP PROCEDURE migrate_student_hub_registrations//
DELIMITER ;

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    form_student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(200) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    mobile VARCHAR(25) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    address VARCHAR(1000) NOT NULL,
    course VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    division VARCHAR(20) NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
);

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
