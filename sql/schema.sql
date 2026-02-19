-- ASMS schema for phpMyAdmin / XAMPP / InfinityFree
-- Import this file into your selected database (do NOT include CREATE DATABASE for shared hosting compatibility)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS uploaded_documents;
DROP TABLE IF EXISTS agency_submissions;
DROP TABLE IF EXISTS problem_reports;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','agency','viewer') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    deadline DATETIME NOT NULL,
    details TEXT NULL,
    file_template_path VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE agency_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    agency_id INT NOT NULL,
    status ENUM('not_submitted','for_review','approved','for_compliance') NOT NULL DEFAULT 'not_submitted',
    submitted_at DATETIME NULL,
    admin_remarks TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_submission_agency (submission_id, agency_id),
    CONSTRAINT fk_as_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE uploaded_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_submission_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    remarks TEXT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    agency_submission_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE problem_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_problem_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default accounts (password: password123)
-- admin / password123
-- agency_a / password123
-- agency_b / password123
-- viewer / password123
INSERT INTO users (name, username, password_hash, role) VALUES
('System Admin', 'admin', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'admin'),
('Agency A', 'agency_a', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency'),
('Agency B', 'agency_b', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency'),
('Read Only Viewer', 'viewer', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer');
