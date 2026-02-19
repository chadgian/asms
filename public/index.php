<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';

$page = $_GET['page'] ?? 'home';
$user = current_user();

function render_header(string $title): void
{
    $user = current_user();
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= h($title) ?></title>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <div class="navbar">
            <div><strong><?= h(APP_NAME) ?></strong></div>
            <div>
                <?php if ($user): ?>
                    <a href="index.php">Dashboard</a>
                    <?php if ($user['role'] === 'agency'): ?>
                        <a href="index.php?page=notifications">Notifications</a>
                        <a href="index.php?page=my_documents">Submitted Documents</a>
                        <a href="index.php?page=report_problem">Report Problem</a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="index.php?page=admin_submissions">Manage Submissions</a>
                    <?php endif; ?>
                    <a href="index.php?page=logout">Logout</a>
                <?php else: ?>
                    <a href="index.php?page=login">Login</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="container">
    <?php
    $flash = flash();
    if ($flash): ?>
        <div class="alert <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif;
}

function render_footer(): void
{
    echo '</div></body></html>';
}

if ($page === 'logout') {
    logout();
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (attempt_login($username, $password)) {
            header('Location: index.php');
            exit;
        }
        flash('error', 'Invalid credentials.');
        header('Location: index.php?page=login');
        exit;
    }

    render_header('Login');
    ?>
    <div class="card" style="max-width: 420px; margin: 40px auto;">
        <h2>Login</h2>
        <form method="post">
            <label>Username</label>
            <input type="text" name="username" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
    <?php
    render_footer();
    exit;
}

require_login();
$user = current_user();
$pdo = db();

if ($user['role'] === 'agency') {
    if ($page === 'report_problem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare('INSERT INTO problem_reports (agency_id, subject, message, created_at) VALUES (:aid, :subject, :message, NOW())');
        $stmt->execute([
            'aid' => $user['id'],
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
        ]);
        flash('success', 'Problem reported successfully.');
        header('Location: index.php?page=report_problem');
        exit;
    }

    if ($page === 'agency_submission_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $agencySubmissionId = (int) ($_POST['agency_submission_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        $stmt = $pdo->prepare('SELECT asb.*, s.name FROM agency_submissions asb JOIN submissions s ON s.id=asb.submission_id WHERE asb.id=:id AND asb.agency_id=:aid');
        $stmt->execute(['id' => $agencySubmissionId, 'aid' => $user['id']]);
        $asb = $stmt->fetch();

        if (!$asb) {
            flash('error', 'Invalid submission.');
            header('Location: index.php');
            exit;
        }

        if ($asb['status'] === 'approved') {
            flash('error', 'Submission is already approved. Upload is disabled.');
            header('Location: index.php?page=agency_submission&id=' . $agencySubmissionId);
            exit;
        }

        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please attach a valid file.');
            header('Location: index.php?page=agency_submission&id=' . $agencySubmissionId);
            exit;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['document']['name']));
        $targetName = time() . '_' . $safeName;
        $targetPath = UPLOAD_DIR . '/' . $targetName;

        if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            flash('error', 'Failed to save uploaded file.');
            header('Location: index.php?page=agency_submission&id=' . $agencySubmissionId);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO uploaded_documents (agency_submission_id, file_name, file_path, remarks, uploaded_at) VALUES (:asb, :name, :path, :remarks, NOW())');
        $stmt->execute([
            'asb' => $agencySubmissionId,
            'name' => $_FILES['document']['name'],
            'path' => $targetName,
            'remarks' => $remarks,
        ]);

        $newStatus = $asb['status'] === 'for_compliance' ? 'for_review' : 'for_review';
        $stmt = $pdo->prepare('UPDATE agency_submissions SET status=:status, updated_at=NOW(), submitted_at=NOW() WHERE id=:id');
        $stmt->execute(['status' => $newStatus, 'id' => $agencySubmissionId]);

        flash('success', 'Document uploaded successfully.');
        header('Location: index.php?page=agency_submission&id=' . $agencySubmissionId);
        exit;
    }

    if ($page === 'notifications') {
        render_header('Notifications');
        $stmt = $pdo->prepare('SELECT n.*, s.name AS submission_name FROM notifications n JOIN agency_submissions a ON a.id=n.agency_submission_id JOIN submissions s ON s.id=a.submission_id WHERE n.agency_id=:aid ORDER BY n.created_at DESC');
        $stmt->execute(['aid' => $user['id']]);
        $rows = $stmt->fetchAll();
        echo '<div class="card"><h2>Notifications</h2>';
        if (!$rows) {
            echo '<p>No notifications yet.</p>';
        } else {
            foreach ($rows as $r) {
                echo '<div><strong>' . h($r['submission_name']) . '</strong><br>' . h($r['message']) . '<div class="small">' . h(format_datetime($r['created_at'])) . '</div></div><hr>';
            }
        }
        echo '</div>';
        render_footer();
        exit;
    }

    if ($page === 'my_documents') {
        render_header('Submitted Documents');
        $stmt = $pdo->prepare('SELECT s.name AS submission_name, d.file_name, d.remarks, d.uploaded_at FROM uploaded_documents d JOIN agency_submissions a ON a.id=d.agency_submission_id JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:aid ORDER BY s.name, d.uploaded_at DESC');
        $stmt->execute(['aid' => $user['id']]);
        $rows = $stmt->fetchAll();
        echo '<div class="card"><h2>Submitted Documents</h2>';
        if (!$rows) {
            echo '<p>No uploaded documents yet.</p>';
        } else {
            echo '<table><tr><th>Submission</th><th>File</th><th>Remarks</th><th>Date</th></tr>';
            foreach ($rows as $r) {
                echo '<tr><td>' . h($r['submission_name']) . '</td><td>' . h($r['file_name']) . '</td><td>' . h($r['remarks'] ?? '') . '</td><td>' . h(format_datetime($r['uploaded_at'])) . '</td></tr>';
            }
            echo '</table>';
        }
        echo '</div>';
        render_footer();
        exit;
    }

    if ($page === 'report_problem') {
        render_header('Report Problem');
        ?>
        <div class="card" style="max-width: 700px;">
            <h2>Report Problem</h2>
            <form method="post">
                <label>Subject</label>
                <input type="text" name="subject" required>
                <label>Message</label>
                <textarea name="message" rows="6" required></textarea>
                <button type="submit">Submit Report</button>
            </form>
        </div>
        <?php
        render_footer();
        exit;
    }

    if ($page === 'agency_submission') {
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT a.*, s.name, s.deadline, s.details, s.file_template_path FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');
        $stmt->execute(['id' => $id, 'aid' => $user['id']]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('error', 'Submission not found.');
            header('Location: index.php');
            exit;
        }

        $docsStmt = $pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:id ORDER BY uploaded_at DESC');
        $docsStmt->execute(['id' => $id]);
        $docs = $docsStmt->fetchAll();

        render_header('Submission Details');
        ?>
        <div class="card">
            <h2><?= h($row['name']) ?></h2>
            <p><strong>Deadline:</strong> <?= h(format_datetime($row['deadline'])) ?></p>
            <p><?= nl2br(h($row['details'] ?? '')) ?></p>
            <p><strong>Status:</strong> <span class="badge <?= h(status_badge_class($row['status'])) ?>"><?= h(status_label($row['status'])) ?></span></p>
            <?php if ($row['file_template_path']): ?>
                <p><a class="btn btn-secondary" href="../uploads/<?= h($row['file_template_path']) ?>" target="_blank">Download Template</a></p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>Upload Document</h3>
            <?php if ($row['status'] === 'approved'): ?>
                <p>This submission is approved. Uploads are disabled.</p>
            <?php else: ?>
                <form method="post" action="index.php?page=agency_submission_upload" enctype="multipart/form-data">
                    <input type="hidden" name="agency_submission_id" value="<?= (int) $row['id'] ?>">
                    <label>Document</label>
                    <input type="file" name="document" required>
                    <label>Remarks</label>
                    <textarea name="remarks" rows="4"></textarea>
                    <button type="submit">Upload</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>Uploaded Documents</h3>
            <?php if (!$docs): ?>
                <p>No documents uploaded yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>File</th><th>Remarks</th><th>Uploaded At</th><th>View</th></tr>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><?= h($d['file_name']) ?></td>
                            <td><?= h($d['remarks'] ?? '') ?></td>
                            <td><?= h(format_datetime($d['uploaded_at'])) ?></td>
                            <td><a class="btn btn-secondary" href="../uploads/<?= h($d['file_path']) ?>" target="_blank">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        <?php
        render_footer();
        exit;
    }

    render_header('Agency Dashboard');
    $stmt = $pdo->prepare('SELECT a.id, a.status, a.submitted_at, s.name, s.deadline FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:aid ORDER BY s.deadline ASC');
    $stmt->execute(['aid' => $user['id']]);
    $rows = $stmt->fetchAll();

    $pending = [];
    $submitted = [];
    foreach ($rows as $r) {
        if ($r['status'] === 'not_submitted') {
            $pending[] = $r;
        } else {
            $submitted[] = $r;
        }
    }
    ?>
    <div class="card">
        <h2>Welcome, <?= h($user['name']) ?></h2>
        <p>Top section: no submissions yet. Bottom section: already submitted.</p>
    </div>
    <div class="card">
        <h3>Not Yet Submitted</h3>
        <?php if (!$pending): ?><p>No pending submissions.</p><?php else: ?>
        <table>
            <tr><th>Submission</th><th>Deadline</th><th>Status</th><th>Action</th></tr>
            <?php foreach ($pending as $r): ?>
                <tr>
                    <td><?= h($r['name']) ?></td>
                    <td><?= h(format_datetime($r['deadline'])) ?></td>
                    <td><span class="badge muted">Not Yet Submitted</span></td>
                    <td><a class="btn" href="index.php?page=agency_submission&id=<?= (int) $r['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Already Submitted</h3>
        <?php if (!$submitted): ?><p>No submitted records yet.</p><?php else: ?>
        <table>
            <tr><th>Submission</th><th>Status</th><th>Last Submitted</th><th>Action</th></tr>
            <?php foreach ($submitted as $r): ?>
                <tr>
                    <td><?= h($r['name']) ?></td>
                    <td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                    <td><?= h(format_datetime($r['submitted_at'])) ?></td>
                    <td><a class="btn" href="index.php?page=agency_submission&id=<?= (int) $r['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>
    <?php
    render_footer();
    exit;
}

if ($user['role'] === 'admin') {
    if ($page === 'admin_submission_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $deadline = trim($_POST['deadline'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $scopeType = $_POST['scope_type'] ?? 'all';
        $selectedAgencies = $_POST['agency_ids'] ?? [];

        $templatePath = null;
        if (isset($_FILES['file_template']) && $_FILES['file_template']['error'] === UPLOAD_ERR_OK) {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['file_template']['name']));
            $targetName = 'template_' . time() . '_' . $safeName;
            if (move_uploaded_file($_FILES['file_template']['tmp_name'], UPLOAD_DIR . '/' . $targetName)) {
                $templatePath = $targetName;
            }
        }

        if ($id > 0) {
            if ($templatePath) {
                $stmt = $pdo->prepare('UPDATE submissions SET name=:name, deadline=:deadline, details=:details, file_template_path=:tpl, updated_at=NOW() WHERE id=:id');
                $stmt->execute(['name' => $name, 'deadline' => $deadline, 'details' => $details, 'tpl' => $templatePath, 'id' => $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE submissions SET name=:name, deadline=:deadline, details=:details, updated_at=NOW() WHERE id=:id');
                $stmt->execute(['name' => $name, 'deadline' => $deadline, 'details' => $details, 'id' => $id]);
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO submissions (name, deadline, details, file_template_path, created_by, created_at, updated_at) VALUES (:name, :deadline, :details, :tpl, :uid, NOW(), NOW())');
            $stmt->execute(['name' => $name, 'deadline' => $deadline, 'details' => $details, 'tpl' => $templatePath, 'uid' => $user['id']]);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM agency_submissions WHERE submission_id=:sid')->execute(['sid' => $id]);

        if ($scopeType === 'all') {
            $agencyRows = $pdo->query("SELECT id FROM users WHERE role='agency'")->fetchAll();
            $selectedAgencies = array_column($agencyRows, 'id');
        }

        $insert = $pdo->prepare('INSERT INTO agency_submissions (submission_id, agency_id, status, submitted_at, admin_remarks, updated_at) VALUES (:sid, :aid, "not_submitted", NULL, NULL, NOW())');
        foreach ($selectedAgencies as $agencyId) {
            $insert->execute(['sid' => $id, 'aid' => (int) $agencyId]);
        }

        flash('success', 'Submission saved successfully.');
        header('Location: index.php?page=admin_submissions');
        exit;
    }

    if ($page === 'admin_update_agency_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $asbId = (int) ($_POST['agency_submission_id'] ?? 0);
        $status = $_POST['status'] ?? 'for_review';
        $remarks = trim($_POST['admin_remarks'] ?? '');

        $stmt = $pdo->prepare('UPDATE agency_submissions SET status=:status, admin_remarks=:remarks, updated_at=NOW() WHERE id=:id');
        $stmt->execute(['status' => $status, 'remarks' => $remarks, 'id' => $asbId]);

        $stmt = $pdo->prepare('SELECT a.agency_id, s.name FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id');
        $stmt->execute(['id' => $asbId]);
        $row = $stmt->fetch();

        if ($row) {
            $msg = 'Update on "' . $row['name'] . '": status set to ' . status_label($status) . '. ' . ($remarks ? ('Remarks: ' . $remarks) : '');
            $nstmt = $pdo->prepare('INSERT INTO notifications (agency_id, agency_submission_id, message, created_at) VALUES (:aid, :asb, :msg, NOW())');
            $nstmt->execute(['aid' => $row['agency_id'], 'asb' => $asbId, 'msg' => $msg]);
        }

        flash('success', 'Agency submission updated.');
        header('Location: index.php?page=admin_review&id=' . (int) ($_POST['submission_id'] ?? 0));
        exit;
    }

    if ($page === 'admin_review') {
        $submissionId = (int) ($_GET['id'] ?? 0);
        $sstmt = $pdo->prepare('SELECT * FROM submissions WHERE id=:id');
        $sstmt->execute(['id' => $submissionId]);
        $submission = $sstmt->fetch();

        if (!$submission) {
            flash('error', 'Submission not found.');
            header('Location: index.php?page=admin_submissions');
            exit;
        }

        render_header('Review Submission');
        $stmt = $pdo->prepare('SELECT a.*, u.name AS agency_name FROM agency_submissions a JOIN users u ON u.id=a.agency_id WHERE a.submission_id=:sid ORDER BY u.name');
        $stmt->execute(['sid' => $submissionId]);
        $rows = $stmt->fetchAll();
        ?>
        <div class="card">
            <h2><?= h($submission['name']) ?></h2>
            <p><?= nl2br(h($submission['details'] ?? '')) ?></p>
            <p><strong>Deadline:</strong> <?= h(format_datetime($submission['deadline'])) ?></p>
        </div>
        <div class="card">
            <h3>Agencies Included</h3>
            <table>
                <tr><th>Agency</th><th>Status</th><th>Submitted At</th><th>Action</th></tr>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= h($r['agency_name']) ?></td>
                        <td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                        <td><?= h(format_datetime($r['submitted_at'])) ?></td>
                        <td><a class="btn" href="index.php?page=admin_agency_view&id=<?= (int) $r['id'] ?>&submission_id=<?= (int) $submissionId ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
        render_footer();
        exit;
    }

    if ($page === 'admin_agency_view') {
        $asbId = (int) ($_GET['id'] ?? 0);
        $submissionId = (int) ($_GET['submission_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT a.*, u.name AS agency_name, s.name AS submission_name FROM agency_submissions a JOIN users u ON u.id=a.agency_id JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id');
        $stmt->execute(['id' => $asbId]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('error', 'Record not found.');
            header('Location: index.php?page=admin_review&id=' . $submissionId);
            exit;
        }

        $docsStmt = $pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:id ORDER BY uploaded_at DESC');
        $docsStmt->execute(['id' => $asbId]);
        $docs = $docsStmt->fetchAll();

        render_header('Agency Submission View');
        ?>
        <div class="card">
            <h2><?= h($row['agency_name']) ?> - <?= h($row['submission_name']) ?></h2>
            <p><strong>Status:</strong> <span class="badge <?= h(status_badge_class($row['status'])) ?>"><?= h(status_label($row['status'])) ?></span></p>
            <p><strong>Submitted At:</strong> <?= h(format_datetime($row['submitted_at'])) ?></p>
            <p><strong>Current Remarks:</strong> <?= h($row['admin_remarks'] ?? '-') ?></p>
        </div>
        <div class="card">
            <h3>Uploaded Documents</h3>
            <?php if (!$docs): ?><p>No documents yet.</p><?php else: ?>
                <table>
                    <tr><th>File</th><th>Remarks</th><th>Uploaded At</th><th>Download</th></tr>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><?= h($d['file_name']) ?></td>
                            <td><?= h($d['remarks'] ?? '') ?></td>
                            <td><?= h(format_datetime($d['uploaded_at'])) ?></td>
                            <td><a class="btn btn-secondary" href="../uploads/<?= h($d['file_path']) ?>" target="_blank">Download</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        <div class="card">
            <h3>Update Status</h3>
            <form method="post" action="index.php?page=admin_update_agency_status">
                <input type="hidden" name="agency_submission_id" value="<?= (int) $asbId ?>">
                <input type="hidden" name="submission_id" value="<?= (int) $submissionId ?>">
                <label>Status</label>
                <select name="status" required>
                    <option value="for_review" <?= $row['status'] === 'for_review' ? 'selected' : '' ?>>For Review</option>
                    <option value="for_compliance" <?= $row['status'] === 'for_compliance' ? 'selected' : '' ?>>For Compliance</option>
                    <option value="approved" <?= $row['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="not_submitted" <?= $row['status'] === 'not_submitted' ? 'selected' : '' ?>>Not Yet Submitted</option>
                </select>
                <label>Remarks</label>
                <textarea name="admin_remarks" rows="4"><?= h($row['admin_remarks'] ?? '') ?></textarea>
                <button type="submit">Save Update</button>
            </form>
        </div>
        <?php
        render_footer();
        exit;
    }

    if ($page === 'admin_submissions') {
        render_header('Manage Submissions');
        $rows = $pdo->query('SELECT * FROM submissions ORDER BY deadline ASC')->fetchAll();
        ?>
        <div class="card">
            <h2>Submission List</h2>
            <p><a class="btn" href="index.php?page=admin_submission_form">+ Add Submission</a></p>
            <table>
                <tr><th>Name</th><th>Deadline</th><th>Actions</th></tr>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= h($r['name']) ?></td>
                        <td><?= h(format_datetime($r['deadline'])) ?></td>
                        <td>
                            <a class="btn btn-secondary" href="index.php?page=admin_submission_form&id=<?= (int) $r['id'] ?>">Edit</a>
                            <a class="btn" href="index.php?page=admin_review&id=<?= (int) $r['id'] ?>">View Agencies</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
        render_footer();
        exit;
    }

    if ($page === 'admin_submission_form') {
        $id = (int) ($_GET['id'] ?? 0);
        $data = ['id' => 0, 'name' => '', 'deadline' => '', 'details' => '', 'file_template_path' => ''];
        $selected = [];
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM submissions WHERE id=:id');
            $stmt->execute(['id' => $id]);
            $found = $stmt->fetch();
            if ($found) {
                $data = $found;
            }
            $x = $pdo->prepare('SELECT agency_id FROM agency_submissions WHERE submission_id=:id');
            $x->execute(['id' => $id]);
            $selected = array_map('intval', array_column($x->fetchAll(), 'agency_id'));
        }

        $agencies = $pdo->query("SELECT id, name FROM users WHERE role='agency' ORDER BY name")->fetchAll();
        render_header('Submission Form');
        ?>
        <div class="card">
            <h2><?= $id > 0 ? 'Edit Submission' : 'Add Submission' ?></h2>
            <form method="post" action="index.php?page=admin_submission_save" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= (int) $data['id'] ?>">
                <label>Name of Submission</label>
                <input type="text" name="name" required value="<?= h($data['name']) ?>">
                <label>Date of Deadline</label>
                <input type="datetime-local" name="deadline" required value="<?= $data['deadline'] ? date('Y-m-d\TH:i', strtotime($data['deadline'])) : '' ?>">
                <label>Details / Instruction</label>
                <textarea name="details" rows="5"><?= h($data['details'] ?? '') ?></textarea>
                <label>File Template (optional)</label>
                <input type="file" name="file_template">
                <?php if (!empty($data['file_template_path'])): ?>
                    <p class="small">Current: <?= h($data['file_template_path']) ?></p>
                <?php endif; ?>
                <label>Agency Scope</label>
                <select id="scope_type" name="scope_type">
                    <option value="all">Include All Agencies</option>
                    <option value="selected">Only Selected Agencies</option>
                </select>
                <div id="agency_select_box">
                    <label>Select Agencies</label>
                    <?php foreach ($agencies as $a): ?>
                        <div>
                            <label>
                                <input type="checkbox" name="agency_ids[]" value="<?= (int) $a['id'] ?>" <?= in_array((int) $a['id'], $selected, true) ? 'checked' : '' ?> style="width:auto;"> <?= h($a['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit">Save Submission</button>
            </form>
        </div>
        <script>
            const scope = document.getElementById('scope_type');
            const box = document.getElementById('agency_select_box');
            function toggle() { box.style.display = scope.value === 'selected' ? 'block' : 'none'; }
            scope.addEventListener('change', toggle);
            toggle();
        </script>
        <?php
        render_footer();
        exit;
    }

    render_header('Admin Dashboard');
    $rows = $pdo->query('SELECT * FROM submissions ORDER BY deadline ASC')->fetchAll();
    ?>
    <div class="card">
        <h2>All Submissions</h2>
        <p><a class="btn" href="index.php?page=admin_submissions">Manage Submissions</a></p>
        <table>
            <tr><th>Name</th><th>Deadline</th><th>Action</th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['name']) ?></td>
                    <td><?= h(format_datetime($r['deadline'])) ?></td>
                    <td><a class="btn" href="index.php?page=admin_review&id=<?= (int) $r['id'] ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    render_footer();
    exit;
}

// Viewer role
render_header('Viewer Dashboard');
$rows = $pdo->query('SELECT s.name AS submission_name, s.deadline, u.name AS agency_name, a.status, a.submitted_at FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id JOIN users u ON u.id=a.agency_id ORDER BY s.deadline ASC, u.name')->fetchAll();
?>
<div class="card">
    <h2>Read-Only Submission Overview</h2>
    <table>
        <tr><th>Submission</th><th>Agency</th><th>Status</th><th>Submitted At</th><th>Deadline</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= h($r['submission_name']) ?></td>
                <td><?= h($r['agency_name']) ?></td>
                <td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                <td><?= h(format_datetime($r['submitted_at'])) ?></td>
                <td><?= h(format_datetime($r['deadline'])) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php
render_footer();
