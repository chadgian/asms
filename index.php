<?php

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/helpers.php';

$page = $_GET['page'] ?? 'home';
$pdo = db();

function render_header(string $title): void
{
    $u = current_user();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= h($title) ?></title>
        <link rel="stylesheet" href="public/style.css">
    </head>
    <body>
    <header class="topbar">
        <div class="brand">ASMS</div>
        <nav>
            <?php if ($u): ?>
                <a href="index.php">Dashboard</a>
                <?php if ($u['role'] === 'agency'): ?>
                    <a href="index.php?page=notifications">Notifications</a>
                    <a href="index.php?page=my_documents">Submitted Documents</a>
                    <a href="index.php?page=report_problem">Report Problem</a>
                <?php endif; ?>
                <?php if ($u['role'] === 'admin'): ?>
                    <a href="index.php?page=admin_notifications">Notifications</a>
                    <a href="index.php?page=admin_users">Manage Accounts</a>
                <?php endif; ?>
                <?php if ($u['role'] === 'viewer'): ?>
                    <a href="index.php?page=viewer_submissions">Submissions</a>
                <?php endif; ?>
                <a href="index.php?page=logout">Logout</a>
            <?php else: ?>
                <a href="index.php?page=login">Login</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="container fade-in">
    <?php
    $f = flash();
    if ($f) {
        echo '<div class="alert ' . h($f['type']) . '">' . h($f['message']) . '</div>';
    }
}

function render_footer(): void
{
    echo '</main></body></html>';
}

function adminIds(PDO $pdo): array {
    return array_map('intval', array_column($pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(), 'id'));
}

if ($page === 'logout') {
    logout();
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (attempt_login(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
            header('Location: index.php');
            exit;
        }
        flash('error', 'Invalid username/password.');
        header('Location: index.php?page=login');
        exit;
    }
    render_header('Login'); ?>
    <section class="card auth-card">
        <h1>Agency Submission Management System</h1>
        <p class="muted">Sign in using your username.</p>
        <form method="post" class="form-grid">
            <label>Username<input type="text" name="username" required></label>
            <label>Password<input type="password" name="password" required></label>
            <button type="submit">Sign In</button>
        </form>
    </section>
    <?php render_footer(); exit;
}

require_login();
$user = current_user();

if ($user['role'] === 'agency') {
    if ($page === 'agency_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $asbId = (int)($_POST['agency_submission_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $pdo->prepare('SELECT a.*, s.name FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');
        $stmt->execute(['id'=>$asbId,'aid'=>$user['id']]);
        $asb = $stmt->fetch();
        if (!$asb || $asb['status']==='approved') {
            flash('error','Upload not allowed.'); header('Location: index.php'); exit;
        }
        if (!isset($_FILES['document']) || $_FILES['document']['error']!==UPLOAD_ERR_OK) {
            flash('error','Please attach a file.'); header('Location: index.php?page=agency_submission&id='.$asbId); exit;
        }
        $orig = basename($_FILES['document']['name']);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/','_', $orig);
        $stored = uniqid('doc_', true) . '_' . $safe;
        move_uploaded_file($_FILES['document']['tmp_name'], UPLOAD_DIR . '/' . $stored);

        $pdo->prepare('INSERT INTO uploaded_documents (agency_submission_id,file_name,file_path,remarks,uploaded_at) VALUES (:a,:f,:p,:r,NOW())')
            ->execute(['a'=>$asbId,'f'=>$orig,'p'=>$stored,'r'=>$remarks]);
        $pdo->prepare("UPDATE agency_submissions SET status='for_review', submitted_at=NOW(), updated_at=NOW() WHERE id=:id")
            ->execute(['id'=>$asbId]);

        $msg = $user['name'] . ' uploaded new documents for ' . $asb['name'] . '.';
        $ins = $pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:uid,:asid,:msg,NOW())');
        foreach (adminIds($pdo) as $aid) {
            $ins->execute(['uid'=>$aid,'asid'=>$asbId,'msg'=>$msg]);
        }

        flash('success','Document uploaded.'); header('Location: index.php?page=agency_submission&id='.$asbId); exit;
    }

    if ($page === 'notifications') {
        render_header('Notifications');
        $stmt=$pdo->prepare('SELECT * FROM notifications WHERE recipient_user_id=:id ORDER BY created_at DESC');
        $stmt->execute(['id'=>$user['id']]); $rows=$stmt->fetchAll();
        echo '<section class="card"><h2>Notifications</h2>';
        foreach($rows as $r){ echo '<div class="list-item"><p>'.h($r['message']).'</p><span class="muted">'.h(format_datetime($r['created_at'])).'</span></div>'; }
        if(!$rows){echo '<p class="muted">No notifications.</p>';}
        echo '</section>'; render_footer(); exit;
    }

    if ($page === 'my_documents') {
        render_header('Submitted Documents');
        $stmt=$pdo->prepare('SELECT d.*, s.name as submission_name, a.status, a.id as agency_submission_id FROM uploaded_documents d JOIN agency_submissions a ON a.id=d.agency_submission_id JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:aid ORDER BY d.uploaded_at DESC');
        $stmt->execute(['aid'=>$user['id']]); $rows=$stmt->fetchAll();
        ?>
        <section class="card"><h2>Submitted Documents</h2>
            <table><tr><th>Submission</th><th>File</th><th>Document Status</th><th>Uploaded</th><th>Actions</th></tr>
            <?php foreach($rows as $r): ?>
                <tr>
                    <td><?= h($r['submission_name']) ?></td>
                    <td><?= h($r['file_name']) ?></td>
                    <td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                    <td><?= h(format_datetime($r['uploaded_at'])) ?></td>
                    <td>
                        <a class="btn ghost" href="uploads/<?= h($r['file_path']) ?>" download="<?= h($r['file_name']) ?>">Download</a>
                        <a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['agency_submission_id'] ?>">Go to Submission</a>
                    </td>
                </tr>
            <?php endforeach; ?></table>
        </section>
        <?php render_footer(); exit;
    }

    if ($page === 'report_problem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare('INSERT INTO problem_reports (agency_id,subject,message,created_at) VALUES (:a,:s,:m,NOW())')
            ->execute(['a'=>$user['id'],'s'=>trim($_POST['subject'] ?? ''),'m'=>trim($_POST['message'] ?? '')]);
        flash('success','Problem submitted.'); header('Location: index.php?page=report_problem'); exit;
    }

    if ($page === 'report_problem') {
        render_header('Report Problem'); ?>
        <section class="card"><h2>Report Problem</h2><form method="post" class="form-grid">
            <label>Subject<input type="text" name="subject" required></label>
            <label>Message<textarea name="message" rows="5" required></textarea></label>
            <button type="submit">Submit</button>
        </form></section>
        <?php render_footer(); exit;
    }

    if ($page === 'agency_submission') {
        $id=(int)($_GET['id']??0);
        $stmt=$pdo->prepare('SELECT a.*, s.name,s.deadline,s.details,s.file_template_path,s.file_template_name FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');
        $stmt->execute(['id'=>$id,'aid'=>$user['id']]); $r=$stmt->fetch();
        if(!$r){ flash('error','Not found'); header('Location: index.php'); exit; }
        $d=$pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:id ORDER BY uploaded_at DESC');$d->execute(['id'=>$id]);$docs=$d->fetchAll();
        render_header('Submission'); ?>
        <section class="card"><h2><?= h($r['name']) ?></h2>
            <p><?= nl2br(h($r['details'] ?? '')) ?></p>
            <p><strong>Deadline:</strong> <?= h(format_datetime($r['deadline'])) ?></p>
            <p><strong>Document Status:</strong> <span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></p>
            <?php if($r['file_template_path']): ?><a class="btn" href="uploads/<?= h($r['file_template_path']) ?>" download="<?= h($r['file_template_name'] ?: $r['file_template_path']) ?>">Download Template</a><?php endif; ?>
        </section>
        <section class="card">
            <h3><?= $docs ? 'Upload New Documents' : 'Upload Document' ?></h3>
            <?php if($r['status']==='approved'): ?><p class="muted">Approved documents can no longer be modified.</p>
            <?php else: ?>
            <form method="post" action="index.php?page=agency_upload" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="agency_submission_id" value="<?= (int)$r['id'] ?>">
                <label>Document<input type="file" name="document" required></label>
                <label>Remarks<textarea name="remarks" rows="4"></textarea></label>
                <button type="submit">Upload <?= $docs ? 'New' : '' ?> Document</button>
            </form>
            <?php endif; ?>
        </section>
        <section class="card"><h3>Uploaded Documents</h3>
            <table><tr><th>File</th><th>Remarks</th><th>Document Status</th><th>Timestamp</th><th>Action</th></tr>
            <?php foreach($docs as $doc): ?><tr>
                <td><?= h($doc['file_name']) ?></td>
                <td><?= h($doc['remarks'] ?? '-') ?></td>
                <td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td>
                <td><?= h(format_datetime($doc['uploaded_at'])) ?></td>
                <td><a class="btn ghost" href="uploads/<?= h($doc['file_path']) ?>" download="<?= h($doc['file_name']) ?>">Download</a></td>
            </tr><?php endforeach; ?></table>
        </section>
        <?php render_footer(); exit;
    }

    render_header('Agency Dashboard');
    $st=$pdo->prepare('SELECT a.id,a.status,a.submitted_at,s.name,s.deadline FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:id ORDER BY s.deadline');
    $st->execute(['id'=>$user['id']]); $rows=$st->fetchAll();
    $pending=array_filter($rows,fn($x)=>$x['status']==='not_submitted');
    $done=array_filter($rows,fn($x)=>$x['status']!=='not_submitted');
    ?>
    <section class="card"><h2>Hello, <?= h($user['name']) ?></h2><p class="muted">Manage your assigned submissions.</p></section>
    <section class="card"><h3>Not Yet Submitted</h3><table><tr><th>Submission</th><th>Deadline</th><th>Action</th></tr>
    <?php foreach($pending as $r): ?><tr><td><?= h($r['name']) ?></td><td><?= h(format_datetime($r['deadline'])) ?></td><td><a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table></section>
    <section class="card"><h3>Already Submitted</h3><table><tr><th>Submission</th><th>Document Status</th><th>Timestamp</th><th>Action</th></tr>
    <?php foreach($done as $r): ?><tr><td><?= h($r['name']) ?></td><td><span class="badge <?= h(status_badge_class($r['status'])) ?>"><?= h(status_label($r['status'])) ?></span></td><td><?= h(format_datetime($r['submitted_at'])) ?></td><td><a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table></section>
    <?php render_footer(); exit;
}

if ($user['role'] === 'admin') {
    if ($page === 'admin_notifications') {
        render_header('Admin Notifications');
        $st=$pdo->prepare('SELECT * FROM notifications WHERE recipient_user_id=:id ORDER BY created_at DESC');$st->execute(['id'=>$user['id']]);$rows=$st->fetchAll();
        echo '<section class="card"><h2>Latest Agency Updates</h2>';
        foreach($rows as $r){echo '<div class="list-item"><p>'.h($r['message']).'</p><span class="muted">'.h(format_datetime($r['created_at'])).'</span></div>';}
        if(!$rows) echo '<p class="muted">No notifications yet.</p>';
        echo '</section>'; render_footer(); exit;
    }

    if ($page === 'admin_user_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $role=$_POST['role'] ?? 'agency';
        $name=trim($_POST['name']??'');$username=trim($_POST['username']??'');$password=(string)($_POST['password']??'');
        $province=$_POST['province'] ?: null; $sector=$_POST['sector'] ?: null;
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (name,username,password_hash,role,province,sector,created_at) VALUES (:n,:u,:p,:r,:pr,:s,NOW())')
            ->execute(['n'=>$name,'u'=>$username,'p'=>$hash,'r'=>$role,'pr'=>$province,'s'=>$role==='agency'?$sector:null]);
        flash('success','Account created.'); header('Location: index.php?page=admin_users'); exit;
    }

    if ($page === 'admin_users') {
        render_header('Manage Accounts');
        $users=$pdo->query("SELECT id,name,username,role,province,sector FROM users WHERE role IN ('agency','viewer') ORDER BY role,name")->fetchAll();
        ?>
        <section class="card"><h2>Add Agency / Viewer</h2><form method="post" action="index.php?page=admin_user_save" class="form-grid two-col">
            <label>Account Type<select name="role"><option value="agency">Agency</option><option value="viewer">Viewer</option></select></label>
            <label>Name<input name="name" required></label>
            <label>Username<input name="username" required></label>
            <label>Password<input type="password" name="password" required></label>
            <label>Province<select name="province"><option value="">Select</option><option>Aklan</option><option>Antique</option><option>Capiz</option><option>Guimaras</option><option>Iloilo</option><option>Negros Occidental</option></select></label>
            <label>Sector (for agency)<select name="sector"><option value="">Select</option><option>NGA</option><option>LGU</option><option>GOCC</option><option>SUC/LUC</option></select></label>
            <button type="submit">Create Account</button>
        </form></section>
        <section class="card"><h3>Existing Accounts</h3><table><tr><th>Name</th><th>Username</th><th>Role</th><th>Province</th><th>Sector</th></tr><?php foreach($users as $u): ?><tr><td><?=h($u['name'])?></td><td><?=h($u['username'])?></td><td><?=h($u['role'])?></td><td><?=h($u['province']??'-')?></td><td><?=h($u['sector']??'-')?></td></tr><?php endforeach; ?></table></section>
        <?php render_footer(); exit;
    }

    if ($page==='admin_submission_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');$deadline=trim($_POST['deadline']??'');$details=trim($_POST['details']??'');
        $scope=$_POST['scope'] ?? 'all'; $agencyIds=$_POST['agency_ids'] ?? [];
        $tplPath=null; $tplName=null;
        if(isset($_FILES['file_template']) && $_FILES['file_template']['error']===UPLOAD_ERR_OK){
            $tplName=basename($_FILES['file_template']['name']);
            $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',$tplName);
            $tplPath=uniqid('tpl_',true).'_'.$safe;
            move_uploaded_file($_FILES['file_template']['tmp_name'],UPLOAD_DIR.'/'.$tplPath);
        }
        if($id>0){
            $sql='UPDATE submissions SET name=:n,deadline=:d,details=:x,updated_at=NOW()';
            $params=['n'=>$name,'d'=>$deadline,'x'=>$details,'id'=>$id];
            if($tplPath){$sql.=',file_template_path=:p,file_template_name=:fn';$params['p']=$tplPath;$params['fn']=$tplName;}
            $sql.=' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);
            $pdo->prepare('DELETE FROM agency_submissions WHERE submission_id=:id')->execute(['id'=>$id]);
        } else {
            $pdo->prepare('INSERT INTO submissions (name,deadline,details,file_template_path,file_template_name,created_by,created_at,updated_at) VALUES (:n,:d,:x,:p,:fn,:c,NOW(),NOW())')
                ->execute(['n'=>$name,'d'=>$deadline,'x'=>$details,'p'=>$tplPath,'fn'=>$tplName,'c'=>$user['id']]);
            $id=(int)$pdo->lastInsertId();
        }
        if($scope==='all') $agencyIds=array_column($pdo->query("SELECT id FROM users WHERE role='agency'")->fetchAll(),'id');
        $ins=$pdo->prepare("INSERT INTO agency_submissions (submission_id,agency_id,status,updated_at) VALUES (:s,:a,'not_submitted',NOW())");
        foreach($agencyIds as $aid){$ins->execute(['s'=>$id,'a'=>(int)$aid]);}
        flash('success','Submission saved.'); header('Location: index.php'); exit;
    }

    if ($page==='admin_update_status' && $_SERVER['REQUEST_METHOD']==='POST') {
        $asid=(int)($_POST['agency_submission_id']??0); $status=$_POST['status']??'for_review'; $remarks=trim($_POST['admin_remarks']??'');
        $pdo->prepare('UPDATE agency_submissions SET status=:s,admin_remarks=:r,updated_at=NOW() WHERE id=:id')->execute(['s'=>$status,'r'=>$remarks,'id'=>$asid]);
        $x=$pdo->prepare('SELECT agency_id FROM agency_submissions WHERE id=:id');$x->execute(['id'=>$asid]);$aid=(int)($x->fetch()['agency_id']??0);
        if($aid){$pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:u,:a,:m,NOW())')->execute(['u'=>$aid,'a'=>$asid,'m'=>'Document status updated to '.status_label($status).'. '.($remarks?:'')]);}
        flash('success','Status updated.'); header('Location: index.php?page=admin_agency_view&id='.$asid); exit;
    }

    if ($page==='admin_agency_view') {
        $id=(int)($_GET['id']??0);
        $st=$pdo->prepare('SELECT a.*,u.name agency_name,u.province,u.sector,s.name submission_name FROM agency_submissions a JOIN users u ON u.id=a.agency_id JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id');
        $st->execute(['id'=>$id]);$r=$st->fetch(); if(!$r){flash('error','Not found');header('Location: index.php');exit;}
        $h=$pdo->prepare('SELECT d.*, a.status, a.admin_remarks FROM uploaded_documents d JOIN agency_submissions a ON a.id=d.agency_submission_id WHERE d.agency_submission_id=:id ORDER BY d.uploaded_at DESC');$h->execute(['id'=>$id]);$docs=$h->fetchAll();
        render_header('Agency View'); ?>
        <section class="card"><h2><?=h($r['agency_name'])?> Dashboard</h2><p class="muted"><?=h($r['province']??'-')?> • <?=h($r['sector']??'-')?></p>
            <p><strong>Submission:</strong> <?=h($r['submission_name'])?></p>
            <p><strong>Document Status:</strong> <span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></p>
            <p><strong>Latest Timestamp:</strong> <?=h(format_datetime($r['submitted_at']))?></p>
            <p><strong>Admin Remarks:</strong> <?=h($r['admin_remarks']?:'-')?></p>
        </section>
        <section class="card"><h3>Submission History / Documents</h3><table><tr><th>Document</th><th>Remarks</th><th>Timestamp</th><th>Status at Review</th><th>Download</th></tr>
        <?php foreach($docs as $d): ?><tr><td><?=h($d['file_name'])?></td><td><?=h($d['remarks']?:'-')?></td><td><?=h(format_datetime($d['uploaded_at']))?></td><td><?=h(status_label($r['status']))?></td><td><a class="btn ghost" href="uploads/<?=h($d['file_path'])?>" download="<?=h($d['file_name'])?>">Download</a></td></tr><?php endforeach; ?></table></section>
        <section class="card"><h3>Update Document Status</h3><form method="post" action="index.php?page=admin_update_status" class="form-grid">
            <input type="hidden" name="agency_submission_id" value="<?= (int)$id ?>">
            <label>Status<select name="status"><option value="for_review">For Review</option><option value="for_compliance">For Compliance</option><option value="approved">Approved</option><option value="not_submitted">Not Yet Submitted</option></select></label>
            <label>Remarks<textarea name="admin_remarks" rows="4"><?=h($r['admin_remarks']??'')?></textarea></label>
            <button type="submit">Save</button>
        </form></section>
        <?php render_footer(); exit;
    }

    if ($page==='admin_submission_form') {
        $id=(int)($_GET['id']??0);$s=['id'=>0,'name'=>'','deadline'=>'','details'=>''];$sel=[];
        if($id){$q=$pdo->prepare('SELECT * FROM submissions WHERE id=:id');$q->execute(['id'=>$id]);$s=$q->fetch()?:$s; $a=$pdo->prepare('SELECT agency_id FROM agency_submissions WHERE submission_id=:id');$a->execute(['id'=>$id]);$sel=array_column($a->fetchAll(),'agency_id');}
        $ags=$pdo->query("SELECT id,name,province,sector FROM users WHERE role='agency' ORDER BY name")->fetchAll();
        render_header('Submission Form'); ?>
        <section class="card"><h2><?= $id?'Edit':'Add' ?> Submission</h2><form method="post" enctype="multipart/form-data" action="index.php?page=admin_submission_save" class="form-grid two-col">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <label>Name of Submission<input name="name" value="<?=h($s['name'])?>" required></label>
            <label>Deadline<input type="datetime-local" name="deadline" value="<?= $s['deadline']?date('Y-m-d\TH:i',strtotime($s['deadline'])):'' ?>" required></label>
            <label class="span-2">Details/Instruction<textarea name="details" rows="4"><?=h($s['details']??'')?></textarea></label>
            <label>File Template<input type="file" name="file_template"></label>
            <label>Scope<select name="scope" id="scope"><option value="all">All Agencies</option><option value="selected">Selected Agencies</option></select></label>
            <div class="span-2" id="agency-box"><p class="muted">Select Agencies</p><?php foreach($ags as $ag): ?><label class="check"><input type="checkbox" name="agency_ids[]" value="<?= (int)$ag['id'] ?>" <?= in_array($ag['id'],$sel)?'checked':'' ?>> <?=h($ag['name'])?> (<?=h($ag['province']??'-')?>)</label><?php endforeach; ?></div>
            <button type="submit">Save Submission</button>
        </form></section>
        <script>
        const s=document.getElementById('scope');const box=document.getElementById('agency-box');const t=()=>box.style.display=s.value==='selected'?'block':'none';s.addEventListener('change',t);t();
        </script>
        <?php render_footer(); exit;
    }

    render_header('Admin Dashboard');
    $subs=$pdo->query('SELECT * FROM submissions ORDER BY deadline ASC')->fetchAll(); ?>
    <section class="card"><div class="row"><h2>Submissions</h2><a class="btn" href="index.php?page=admin_submission_form">Add Submission</a></div>
        <table><tr><th>Name</th><th>Deadline</th><th>Action</th></tr>
        <?php foreach($subs as $s): ?><tr><td><?=h($s['name'])?></td><td><?=h(format_datetime($s['deadline']))?></td><td><a class="btn" href="index.php?page=admin_submission_form&id=<?= (int)$s['id'] ?>">Edit</a></td></tr><?php endforeach; ?>
        </table>
    </section>
    <section class="card"><h3>Agency Document Status Overview</h3>
        <?php $rows=$pdo->query('SELECT a.id,u.name agency_name,u.province,s.name submission_name,a.status,a.submitted_at FROM agency_submissions a JOIN users u ON u.id=a.agency_id JOIN submissions s ON s.id=a.submission_id ORDER BY a.updated_at DESC')->fetchAll(); ?>
        <table><tr><th>Agency</th><th>Province</th><th>Submission</th><th>Latest Document Status</th><th>Timestamp</th><th>View</th></tr>
        <?php foreach($rows as $r): ?><tr><td><?=h($r['agency_name'])?></td><td><?=h($r['province']??'-')?></td><td><?=h($r['submission_name'])?></td><td><span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></td><td><?=h(format_datetime($r['submitted_at']))?></td><td><a class="btn ghost" href="index.php?page=admin_agency_view&id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table>
    </section>
    <?php render_footer(); exit;
}

// viewer (admin-like but read-only + province filter)
if ($page==='viewer_submissions' || true) {
    render_header('Viewer Dashboard');
    $province=$user['province'] ?? null;
    $stmt=$pdo->prepare('SELECT a.id,u.name agency_name,u.province,s.name submission_name,s.deadline,a.status,a.submitted_at,a.admin_remarks FROM agency_submissions a JOIN users u ON u.id=a.agency_id JOIN submissions s ON s.id=a.submission_id WHERE (:p IS NULL OR u.province=:p) ORDER BY s.deadline ASC');
    $stmt->execute(['p'=>$province]);
    $rows=$stmt->fetchAll(); ?>
    <section class="card"><h2>Province Monitoring Dashboard</h2><p class="muted">Scope: <?=h($province ?: 'All Provinces')?></p>
    <table><tr><th>Agency</th><th>Province</th><th>Submission</th><th>Deadline</th><th>Latest Document Status</th><th>Timestamp</th><th>Remarks</th></tr>
    <?php foreach($rows as $r): ?><tr><td><?=h($r['agency_name'])?></td><td><?=h($r['province']??'-')?></td><td><?=h($r['submission_name'])?></td><td><?=h(format_datetime($r['deadline']))?></td><td><span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></td><td><?=h(format_datetime($r['submitted_at']))?></td><td><?=h($r['admin_remarks']?:'-')?></td></tr><?php endforeach; ?></table>
    </section>
    <?php render_footer(); exit;
}
