CREATE DATABASE IF NOT EXISTS asms;
USE asms;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'agency', 'viewer') NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    deadline DATETIME NOT NULL,
    details TEXT,
    file_template_path VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_submission_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS agency_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    agency_id INT NOT NULL,
    status ENUM('not_submitted', 'for_review', 'approved', 'for_compliance') NOT NULL DEFAULT 'not_submitted',
    submitted_at DATETIME NULL,
    admin_remarks TEXT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_submission_agency (submission_id, agency_id),
    CONSTRAINT fk_as_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS uploaded_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_submission_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    remarks TEXT NULL,
    uploaded_at DATETIME NOT NULL,
    CONSTRAINT fk_doc_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    agency_submission_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_notif_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS problem_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_problem_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default users (password: password123)
INSERT INTO users (name, email, password_hash, role)
SELECT * FROM (
    SELECT 'System Admin', 'admin@asms.local', '$2y$10$wgG4QOH5Yb4ZxqjbvsYtAuc8MQfR2rM7NQYfUPP8KNw5iyIG8Wxri', 'admin'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@asms.local') LIMIT 1;

INSERT INTO users (name, email, password_hash, role)
SELECT * FROM (
    SELECT 'Agency A', 'agencya@asms.local', '$2y$10$wgG4QOH5Yb4ZxqjbvsYtAuc8MQfR2rM7NQYfUPP8KNw5iyIG8Wxri', 'agency'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'agencya@asms.local') LIMIT 1;

INSERT INTO users (name, email, password_hash, role)
SELECT * FROM (
    SELECT 'Agency B', 'agencyb@asms.local', '$2y$10$wgG4QOH5Yb4ZxqjbvsYtAuc8MQfR2rM7NQYfUPP8KNw5iyIG8Wxri', 'agency'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'agencyb@asms.local') LIMIT 1;

INSERT INTO users (name, email, password_hash, role)
SELECT * FROM (
    SELECT 'Read Only Viewer', 'viewer@asms.local', '$2y$10$wgG4QOH5Yb4ZxqjbvsYtAuc8MQfR2rM7NQYfUPP8KNw5iyIG8Wxri', 'viewer'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'viewer@asms.local') LIMIT 1;
