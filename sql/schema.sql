SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS uploaded_documents;
DROP TABLE IF EXISTS submission_templates;
DROP TABLE IF EXISTS agency_submissions;
DROP TABLE IF EXISTS problem_reports;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','agency','viewer') NOT NULL,
    province ENUM('Aklan','Antique','Capiz','Guimaras','Iloilo','Negros Occidental') NULL,
    sector ENUM('NGA','LGU','GOCC','SUC/LUC') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    deadline DATE NOT NULL,
    details TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_submission_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE submission_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tpl_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE agency_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    agency_id INT NOT NULL,
    latest_status ENUM('not_submitted','for_review','approved','for_compliance') NOT NULL DEFAULT 'not_submitted',
    submitted_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_submission_agency (submission_id, agency_id),
    CONSTRAINT fk_as_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE uploaded_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_submission_id INT NOT NULL,
    uploader_user_id INT NULL,
    uploader_role ENUM('agency','admin') NOT NULL DEFAULT 'agency',
    batch_token VARCHAR(60) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    user_remarks TEXT NULL,
    document_status ENUM('for_review','approved','for_compliance') NOT NULL DEFAULT 'for_review',
    admin_remarks TEXT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    CONSTRAINT fk_doc_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_uploader FOREIGN KEY (uploader_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    agency_submission_id INT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_notif_user FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_as FOREIGN KEY (agency_submission_id) REFERENCES agency_submissions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE problem_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_problem_agency FOREIGN KEY (agency_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, username, password_hash, role) VALUES
('System Admin', 'admin', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'admin');

-- Region VI sample government agencies (username = agency identifier, password = password123)
INSERT INTO users (name, username, password_hash, role, province, sector) VALUES
('Provincial Government of Aklan', 'pgo_aklan', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Aklan', 'LGU'),
('Aklan State University', 'aklan_state_university', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Aklan', 'SUC/LUC'),
('DSWD Field Office VI - Aklan', 'dswd_fo6_aklan', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Aklan', 'NGA'),

('Provincial Government of Antique', 'pgo_antique', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Antique', 'LGU'),
('University of Antique', 'university_of_antique', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Antique', 'SUC/LUC'),
('DOH CHD Western Visayas - Antique', 'doh_chd6_antique', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Antique', 'NGA'),

('Provincial Government of Capiz', 'pgo_capiz', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Capiz', 'LGU'),
('Capiz State University', 'capiz_state_university', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Capiz', 'SUC/LUC'),
('PhilHealth Capiz', 'philhealth_capiz', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Capiz', 'GOCC'),

('Provincial Government of Guimaras', 'pgo_guimaras', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Guimaras', 'LGU'),
('Guimaras State University', 'guimaras_state_university', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Guimaras', 'SUC/LUC'),
('NIA Guimaras', 'nia_guimaras', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Guimaras', 'NGA'),

('Provincial Government of Iloilo', 'pgo_iloilo', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Iloilo', 'LGU'),
('West Visayas State University', 'west_visayas_state_university', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Iloilo', 'SUC/LUC'),
('DOLE Region VI', 'dole_region6', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Iloilo', 'NGA'),
('LandBank Iloilo', 'landbank_iloilo', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Iloilo', 'GOCC'),

('Provincial Government of Negros Occidental', 'pgo_negocc', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Negros Occidental', 'LGU'),
('Central Philippines State University', 'cpsu_negocc', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Negros Occidental', 'SUC/LUC'),
('Sugar Regulatory Administration', 'sra_negocc', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Negros Occidental', 'GOCC'),
('DPWH Negros Occidental 1st DEO', 'dpwh_negocc_1deo', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'agency', 'Negros Occidental', 'NGA');

-- Viewer accounts per province
INSERT INTO users (name, username, password_hash, role, province) VALUES
('Viewer Aklan', 'viewer_aklan', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Aklan'),
('Viewer Antique', 'viewer_antique', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Antique'),
('Viewer Capiz', 'viewer_capiz', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Capiz'),
('Viewer Guimaras', 'viewer_guimaras', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Guimaras'),
('Viewer Iloilo', 'viewer_iloilo', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Iloilo'),
('Viewer Negros Occidental', 'viewer_negocc', '$2y$12$1lOFW5Q55J8TauHJSrumY.8rSPOs/bCAZlnDkdumVDb74tTbR26Ye', 'viewer', 'Negros Occidental');

-- Sample submissions for testing
INSERT INTO submissions (name, deadline, details, created_by, created_at, updated_at) VALUES
('Submission of HR-GAIns Users Enrollment Data', '2026-03-31', 'Submit updated enrollment data per agency.', (SELECT id FROM users WHERE username='admin' LIMIT 1), NOW(), NOW()),
('Quarterly Compliance Report', '2026-04-15', 'Submit quarterly compliance narrative and supporting documents.', (SELECT id FROM users WHERE username='admin' LIMIT 1), NOW(), NOW()),
('Inventory and Asset Utilization', '2026-05-10', 'Submit latest inventory and asset utilization templates.', (SELECT id FROM users WHERE username='admin' LIMIT 1), NOW(), NOW());

-- Assign all agencies to all sample submissions with varied sample statuses
INSERT INTO agency_submissions (submission_id, agency_id, latest_status, submitted_at, updated_at)
SELECT s.id, u.id,
CASE
  WHEN s.name = 'Submission of HR-GAIns Users Enrollment Data' AND u.province IN ('Aklan','Antique','Capiz','Guimaras') THEN 'approved'
  WHEN s.name = 'Submission of HR-GAIns Users Enrollment Data' AND u.province = 'Iloilo' THEN 'for_review'
  WHEN s.name = 'Submission of HR-GAIns Users Enrollment Data' AND u.province = 'Negros Occidental' THEN 'for_compliance'
  WHEN s.name = 'Quarterly Compliance Report' AND u.province IN ('Aklan','Capiz') THEN 'for_review'
  WHEN s.name = 'Quarterly Compliance Report' AND u.province IN ('Antique','Guimaras') THEN 'approved'
  WHEN s.name = 'Quarterly Compliance Report' AND u.province IN ('Iloilo','Negros Occidental') THEN 'for_compliance'
  ELSE 'not_submitted'
END,
CASE
  WHEN s.name = 'Inventory and Asset Utilization' THEN NULL
  ELSE NOW()
END,
NOW()
FROM submissions s
JOIN users u ON u.role='agency'
WHERE s.name IN ('Submission of HR-GAIns Users Enrollment Data','Quarterly Compliance Report','Inventory and Asset Utilization');
