CREATE DATABASE IF NOT EXISTS studenthub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(254) NOT NULL,
    program VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL,
    year TINYINT UNSIGNED NOT NULL,
    country VARCHAR(80) NOT NULL,
    state VARCHAR(80) NOT NULL,
    city VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_email (email),
    CONSTRAINT chk_students_year CHECK (year BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(120) NOT NULL,
    category VARCHAR(60) NOT NULL,
    event_date DATE NOT NULL,
    location VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    poster_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_events_title_date (title, event_date),
    KEY idx_events_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registration_student_event (student_id, event_id),
    KEY idx_registrations_event (event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(80) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    program VARCHAR(120) NOT NULL,
    year TINYINT UNSIGNED NOT NULL,
    gender VARCHAR(30) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_role CHECK (role IN ('student', 'admin')),
    CONSTRAINT chk_users_year CHECK (year BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    selector CHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_tokens_selector (selector),
    KEY idx_remember_tokens_user (user_id),
    KEY idx_remember_tokens_expiry (expires_at),
    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_audit (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_registration_audit_user (user_id),
    CONSTRAINT fk_registration_audit_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NULL,
    details VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_actor (actor_id),
    CONSTRAINT fk_audit_logs_actor
        FOREIGN KEY (actor_id) REFERENCES users (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO students
    (name, email, program, department, year, country, state, city)
VALUES
    ('Aarav Mehta', 'aarav.mehta@example.edu', 'BSc Computer Science', 'Computing', 1, 'India', 'Gujarat', 'Ahmedabad'),
    ('Amara Johnson', 'amara.johnson@example.edu', 'BA Environmental Studies', 'Environment', 2, 'United States', 'California', 'Oakland'),
    ('Chen Wei', 'chen.wei@example.edu', 'BEng Mechanical Engineering', 'Engineering', 3, 'Canada', 'Ontario', 'Toronto'),
    ('Student Demo 04', 'student04@example.edu', 'BSc Computer Science', 'Computing', 1, 'India', 'Gujarat', 'Vadodara'),
    ('Student Demo 05', 'student05@example.edu', 'BSc Information Technology', 'Computing', 2, 'India', 'Gujarat', 'Rajkot'),
    ('Student Demo 06', 'student06@example.edu', 'BEng Mechanical Engineering', 'Engineering', 3, 'Canada', 'Ontario', 'Ottawa'),
    ('Student Demo 07', 'student07@example.edu', 'BA Environmental Studies', 'Environment', 2, 'United States', 'California', 'San Diego'),
    ('Student Demo 08', 'student08@example.edu', 'BSc Biology', 'Science', 1, 'India', 'Maharashtra', 'Nagpur'),
    ('Student Demo 09', 'student09@example.edu', 'BA Graphic Design', 'Arts', 3, 'United Kingdom', 'England', 'Manchester'),
    ('Student Demo 10', 'student10@example.edu', 'BBA Marketing', 'Business', 2, 'Italy', 'Lombardy', 'Bergamo'),
    ('Student Demo 11', 'student11@example.edu', 'BSc Data Science', 'Computing', 4, 'India', 'Gujarat', 'Bhavnagar'),
    ('Student Demo 12', 'student12@example.edu', 'BA Psychology', 'Social Sciences', 1, 'Canada', 'British Columbia', 'Victoria'),
    ('Student Demo 13', 'student13@example.edu', 'BEng Civil Engineering', 'Engineering', 2, 'United States', 'Texas', 'Dallas'),
    ('Student Demo 14', 'student14@example.edu', 'BSc Chemistry', 'Science', 3, 'Sweden', 'Stockholm County', 'Uppsala'),
    ('Student Demo 15', 'student15@example.edu', 'BA English Literature', 'Arts', 1, 'United Kingdom', 'England', 'Leeds'),
    ('Student Demo 16', 'student16@example.edu', 'BBA Finance', 'Business', 4, 'India', 'Maharashtra', 'Nashik'),
    ('Student Demo 17', 'student17@example.edu', 'BSc Information Systems', 'Computing', 2, 'Spain', 'Community of Madrid', 'Alcala'),
    ('Student Demo 18', 'student18@example.edu', 'BA Sociology', 'Social Sciences', 3, 'United States', 'New York', 'Rochester'),
    ('Student Demo 19', 'student19@example.edu', 'BSc Physics', 'Science', 2, 'Pakistan', 'Punjab', 'Islamabad'),
    ('Student Demo 20', 'student20@example.edu', 'BSc Computer Science', 'Computing', 4, 'India', 'Gujarat', 'Anand');

INSERT IGNORE INTO events (title, category, event_date, location, description)
VALUES
    ('Community Garden Day', 'Community', '2026-10-20', 'East Garden', 'Help prepare the community garden for the new growing season.'),
    ('Women in Technology Panel', 'Career', '2026-10-23', 'Innovation Centre', 'Hear career stories and advice from technology professionals.'),
    ('Robotics Club Demonstration', 'Technology', '2026-11-17', 'Engineering Lab 3', 'See student-built robots in action and meet the robotics club.');

INSERT IGNORE INTO registrations (student_id, event_id)
SELECT students.id, events.id
FROM students
CROSS JOIN events
WHERE students.email = 'aarav.mehta@example.edu'
  AND events.title = 'Community Garden Day'
  AND events.event_date = '2026-10-20';

INSERT IGNORE INTO registrations (student_id, event_id)
SELECT students.id, events.id
FROM students
CROSS JOIN events
WHERE students.email = 'amara.johnson@example.edu'
  AND events.title = 'Women in Technology Panel'
  AND events.event_date = '2026-10-23';
