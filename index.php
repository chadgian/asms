<?php

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/helpers.php';

$page = $_GET['page'] ?? 'home';
$pdo = db();

function provinces(): array { return ['Aklan','Antique','Capiz','Guimaras','Iloilo','Negros Occidental']; }
function sectors(): array { return ['NGA','LGU','GOCC','SUC/LUC']; }

function render_header(string $title): void
{
    $u = current_user();
    ?>
    <!doctype html><html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($title) ?></title><link rel="stylesheet" href="public/style.css"></head><body>
    <header class="topbar"><div class="brand">ASMS</div><nav>
        <?php if ($u): ?><a href="index.php">Dashboard</a>
            <?php if ($u['role'] === 'agency'): ?>
                <a href="index.php?page=notifications">Notifications</a>
                <a href="index.php?page=my_documents">Submitted Documents</a>
                <a href="index.php?page=report_problem">Report Problem</a>
            <?php endif; ?>
            <?php if ($u['role'] === 'admin'): ?>
                <a href="index.php?page=admin_notifications">Notifications</a>
                <a href="index.php?page=admin_users">Accounts</a>
            <?php endif; ?>
            <?php if ($u['role'] === 'viewer'): ?>
                <a href="index.php?page=viewer_submissions">Submissions</a>
            <?php endif; ?>
            <a href="index.php?page=logout">Logout</a>
        <?php else: ?><a href="index.php?page=login">Login</a><?php endif; ?>
    </nav></header><main class="container fade-in">
    <?php $f = flash(); if ($f) echo '<div class="alert '.h($f['type']).'">'.h($f['message']).'</div>'; ?>
    <?php
}
function render_footer(): void { echo '</main></body></html>'; }

function notify_admins(PDO $pdo, int $asid, string $message): void {
    $admins = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
    $ins = $pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:u,:a,:m,NOW())');
    foreach ($admins as $a) $ins->execute(['u'=>(int)$a['id'],'a'=>$asid,'m'=>$message]);
}

function upload_files(PDO $pdo, int $agencySubmissionId, int $uploaderId, string $uploaderRole, string $remarks, array $files): int {
    if (!isset($files['name']) || !is_array($files['name'])) return 0;
    $count = 0;
    $ins = $pdo->prepare('INSERT INTO uploaded_documents (agency_submission_id,uploader_user_id,uploader_role,file_name,file_path,remarks,uploaded_at) VALUES (:a,:u,:r,:f,:p,:m,NOW())');
    foreach ($files['name'] as $i => $name) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $orig = basename((string)$name);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
        $stored = uniqid('doc_', true) . '_' . $safe;
        if (move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . '/' . $stored)) {
            $ins->execute(['a'=>$agencySubmissionId,'u'=>$uploaderId,'r'=>$uploaderRole,'f'=>$orig,'p'=>$stored,'m'=>$remarks]);
            $count++;
        }
    }
    return $count;
}

if ($page === 'logout') { logout(); header('Location: index.php?page=login'); exit; }

if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (attempt_login(trim($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) { header('Location: index.php'); exit; }
        flash('error', 'Invalid username/password.'); header('Location: index.php?page=login'); exit;
    }
    render_header('Login'); ?>
    <section class="card auth-card"><h1>Agency Submission Management System</h1><p class="muted">Sign in using your username.</p>
    <form method="post" class="form-grid"><label>Username<input type="text" name="username" required></label><label>Password<input type="password" name="password" required></label><button type="submit">Sign In</button></form></section>
    <?php render_footer(); exit;
}

require_login();
$user = current_user();
$isViewer = $user['role'] === 'viewer';

if ($user['role'] === 'agency') {
    if ($page === 'agency_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $asbId = (int)($_POST['agency_submission_id'] ?? 0); $remarks = trim($_POST['remarks'] ?? '');
        $stmt = $pdo->prepare('SELECT a.*, s.name FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');
        $stmt->execute(['id'=>$asbId,'aid'=>$user['id']]); $asb = $stmt->fetch();
        if (!$asb || $asb['status'] === 'approved') { flash('error', 'Upload not allowed.'); header('Location: index.php'); exit; }
        $added = upload_files($pdo, $asbId, $user['id'], 'agency', $remarks, $_FILES['documents'] ?? []);
        if ($added === 0) { flash('error', 'Please attach at least one valid file.'); header('Location: index.php?page=agency_submission&id='.$asbId); exit; }
        $pdo->prepare("UPDATE agency_submissions SET status='for_review', submitted_at=NOW(), updated_at=NOW() WHERE id=:id")->execute(['id'=>$asbId]);
        notify_admins($pdo, $asbId, $user['name'] . ' uploaded ' . $added . ' document(s).');
        flash('success', $added . ' document(s) uploaded.'); header('Location: index.php?page=agency_submission&id='.$asbId); exit;
    }

    if ($page === 'notifications') {
        render_header('Notifications');
        $st = $pdo->prepare('SELECT * FROM notifications WHERE recipient_user_id=:u ORDER BY created_at DESC'); $st->execute(['u'=>$user['id']]); $rows = $st->fetchAll();
        echo '<section class="card"><h2>Notifications</h2>'; foreach($rows as $r){ echo '<div class="list-item"><p>'.h($r['message']).'</p><span class="muted">'.h(format_datetime($r['created_at'])).'</span></div>'; }
        if(!$rows) echo '<p class="muted">No notifications.</p>'; echo '</section>'; render_footer(); exit;
    }

    if ($page === 'my_documents') {
        render_header('Submitted Documents');
        $st = $pdo->prepare('SELECT d.*, s.name submission_name, a.status, a.id as agency_submission_id FROM uploaded_documents d JOIN agency_submissions a ON a.id=d.agency_submission_id JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:a ORDER BY d.uploaded_at DESC');
        $st->execute(['a'=>$user['id']]); $rows = $st->fetchAll(); ?>
        <section class="card"><h2>Submitted Documents</h2><table><tr><th>Submission</th><th>File</th><th>Document Status</th><th>Uploaded</th><th>Actions</th></tr>
        <?php foreach($rows as $r): ?><tr><td><?=h($r['submission_name'])?></td><td><?=h($r['file_name'])?></td><td><span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></td><td><?=h(format_datetime($r['uploaded_at']))?></td><td><a class="btn ghost" href="uploads/<?=h($r['file_path'])?>" download="<?=h($r['file_name'])?>">Download</a> <a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['agency_submission_id'] ?>">Go to Submission</a></td></tr><?php endforeach; ?>
        </table></section>
        <?php render_footer(); exit;
    }

    if ($page === 'report_problem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare('INSERT INTO problem_reports (agency_id,subject,message,created_at) VALUES (:a,:s,:m,NOW())')->execute(['a'=>$user['id'],'s'=>trim($_POST['subject'] ?? ''),'m'=>trim($_POST['message'] ?? '')]);
        flash('success', 'Problem submitted.'); header('Location: index.php?page=report_problem'); exit;
    }

    if ($page === 'report_problem') {
        render_header('Report Problem'); ?>
        <section class="card"><h2>Report Problem</h2><form method="post" class="form-grid"><label>Subject<input name="subject" required></label><label>Message<textarea name="message" rows="5" required></textarea></label><button type="submit">Submit</button></form></section>
        <?php render_footer(); exit;
    }

    if ($page === 'agency_submission') {
        $id=(int)($_GET['id']??0);
        $st=$pdo->prepare('SELECT a.*, s.name,s.details,s.deadline,s.file_template_path,s.file_template_name FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');
        $st->execute(['id'=>$id,'aid'=>$user['id']]); $row=$st->fetch(); if(!$row){ flash('error','Not found.'); header('Location: index.php'); exit; }
        $d=$pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:a ORDER BY uploaded_at DESC'); $d->execute(['a'=>$id]); $docs=$d->fetchAll();
        render_header('Submission'); ?>
        <section class="card"><h2><?=h($row['name'])?></h2><p><?=nl2br(h($row['details'] ?? ''))?></p><p><strong>Deadline:</strong> <?=h(format_datetime($row['deadline']))?></p><p><strong>Document Status:</strong> <span class="badge <?=h(status_badge_class($row['status']))?>"><?=h(status_label($row['status']))?></span></p><?php if($row['file_template_path']): ?><a class="btn" href="uploads/<?=h($row['file_template_path'])?>" download="<?=h($row['file_template_name'] ?: $row['file_template_path'])?>">Download Template</a><?php endif; ?></section>
        <section class="card"><h3><?= $docs ? 'Upload New Documents' : 'Upload Documents' ?></h3><?php if($row['status']==='approved'): ?><p class="muted">Approved records no longer accept uploads.</p><?php else: ?><form method="post" action="index.php?page=agency_upload" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="agency_submission_id" value="<?= (int)$row['id'] ?>"><label>Files<input type="file" name="documents[]" multiple required></label><label>Remarks<textarea name="remarks" rows="4"></textarea></label><button type="submit">Upload</button></form><?php endif; ?></section>
        <section class="card"><h3>Uploaded Documents</h3><table><tr><th>File</th><th>Remarks</th><th>Uploaded By</th><th>Status</th><th>Timestamp</th><th>Action</th></tr><?php foreach($docs as $doc): ?><tr><td><?=h($doc['file_name'])?></td><td><?=h($doc['remarks']?:'-')?></td><td><?=h(ucfirst($doc['uploader_role']))?></td><td><span class="badge <?=h(status_badge_class($row['status']))?>"><?=h(status_label($row['status']))?></span></td><td><?=h(format_datetime($doc['uploaded_at']))?></td><td><a class="btn ghost" href="uploads/<?=h($doc['file_path'])?>" download="<?=h($doc['file_name'])?>">Download</a></td></tr><?php endforeach; ?></table></section>
        <?php render_footer(); exit;
    }

    render_header('Agency Dashboard');
    $st=$pdo->prepare('SELECT a.id,a.status,a.submitted_at,s.name,s.deadline FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:a ORDER BY s.deadline'); $st->execute(['a'=>$user['id']]); $rows=$st->fetchAll();
    $pending=array_filter($rows, fn($x)=>$x['status']==='not_submitted'); $done=array_filter($rows, fn($x)=>$x['status']!=='not_submitted'); ?>
    <section class="card"><h2>Hello, <?=h($user['name'])?></h2><p class="muted">Manage your assigned submissions.</p></section>
    <section class="card"><h3>Not Yet Submitted</h3><table><tr><th>Submission</th><th>Deadline</th><th>Action</th></tr><?php foreach($pending as $r): ?><tr><td><?=h($r['name'])?></td><td><?=h(format_datetime($r['deadline']))?></td><td><a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table></section>
    <section class="card"><h3>Already Submitted</h3><table><tr><th>Submission</th><th>Document Status</th><th>Timestamp</th><th>Action</th></tr><?php foreach($done as $r): ?><tr><td><?=h($r['name'])?></td><td><span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></td><td><?=h(format_datetime($r['submitted_at']))?></td><td><a class="btn" href="index.php?page=agency_submission&id=<?= (int)$r['id'] ?>">Open</a></td></tr><?php endforeach; ?></table></section>
    <?php render_footer(); exit;
}

if ($user['role'] === 'admin' || $isViewer) {
    $viewerProvince = $isViewer ? ($user['province'] ?? null) : null;

    if (!$isViewer && $page === 'admin_notifications') {
        render_header('Notifications');
        $st=$pdo->prepare('SELECT * FROM notifications WHERE recipient_user_id=:u ORDER BY created_at DESC'); $st->execute(['u'=>$user['id']]); $rows=$st->fetchAll();
        echo '<section class="card"><h2>Latest Agency Updates</h2>'; foreach($rows as $r){echo '<div class="list-item"><p>'.h($r['message']).'</p><span class="muted">'.h(format_datetime($r['created_at'])).'</span></div>';} if(!$rows) echo '<p class="muted">No notifications yet.</p>'; echo '</section>';
        render_footer(); exit;
    }

    if (!$isViewer && $page === 'admin_user_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id=(int)($_POST['id'] ?? 0); $role=$_POST['role'] ?? 'agency';
        $data=['n'=>trim($_POST['name']??''),'u'=>trim($_POST['username']??''),'r'=>$role,'p'=>($_POST['province']?:null),'s'=>($role==='agency' ? ($_POST['sector']?:null) : null)];
        if($id>0){
            $sql='UPDATE users SET name=:n,username=:u,role=:r,province=:p,sector=:s';
            if(!empty($_POST['password'])){$sql.=',password_hash=:ph';$data['ph']=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);} $sql.=' WHERE id=:id'; $data['id']=$id;
            $pdo->prepare($sql)->execute($data); flash('success','Account updated.');
        } else {
            $data['ph']=password_hash((string)($_POST['password']??''),PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (name,username,password_hash,role,province,sector,created_at) VALUES (:n,:u,:ph,:r,:p,:s,NOW())')->execute($data); flash('success','Account created.');
        }
        header('Location: index.php?page=admin_users'); exit;
    }

    if (!$isViewer && $page === 'admin_users') {
        $editId=(int)($_GET['edit']??0); $edit=['id'=>0,'name'=>'','username'=>'','role'=>'agency','province'=>'','sector'=>''];
        if($editId){$q=$pdo->prepare("SELECT id,name,username,role,province,sector FROM users WHERE id=:id AND role IN ('agency','viewer')");$q->execute(['id'=>$editId]);$edit=$q->fetch()?:$edit;}
        $search=trim($_GET['q'] ?? ''); $like='%'.$search.'%';
        $q=$pdo->prepare("SELECT id,name,username,role,province,sector FROM users WHERE role IN ('agency','viewer') AND (name LIKE :q OR username LIKE :q OR province LIKE :q OR sector LIKE :q) ORDER BY role,name");
        $q->execute(['q'=>$like]); $users=$q->fetchAll();
        render_header('Accounts'); ?>
        <section class="card"><h2><?= $editId ? 'Edit' : 'Add' ?> Agency / Viewer</h2><form method="post" action="index.php?page=admin_user_save" class="form-grid two-col" id="account-form"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <label>Account Type<select name="role" id="role"><option value="agency" <?= $edit['role']==='agency'?'selected':'' ?>>Agency</option><option value="viewer" <?= $edit['role']==='viewer'?'selected':'' ?>>Viewer</option></select></label>
        <label>Name<input name="name" value="<?=h($edit['name'])?>" required></label>
        <label>Username<input name="username" value="<?=h($edit['username'])?>" required></label>
        <label>Password <?= $editId? '(leave blank to keep current)' : '' ?><input type="password" name="password" <?= $editId?'':'required' ?>></label>
        <label>Province<select name="province"><option value="">Select</option><?php foreach(provinces() as $p): ?><option value="<?=h($p)?>" <?= $edit['province']===$p?'selected':'' ?>><?=h($p)?></option><?php endforeach; ?></select></label>
        <label id="sector-field">Sector<select name="sector"><option value="">Select</option><?php foreach(sectors() as $s): ?><option value="<?=h($s)?>" <?= $edit['sector']===$s?'selected':'' ?>><?=h($s)?></option><?php endforeach; ?></select></label>
        <button type="submit"><?= $editId ? 'Update Account' : 'Create Account' ?></button></form></section>
        <section class="card"><div class="row"><h3>Existing Accounts</h3><form method="get" class="inline-search"><input type="hidden" name="page" value="admin_users"><input type="text" name="q" value="<?=h($search)?>" placeholder="Search name, username, province, sector..."><button type="submit">Search</button></form></div>
        <table><tr><th>Name</th><th>Username</th><th>Role</th><th>Province</th><th>Sector</th><th>Action</th></tr><?php foreach($users as $u): ?><tr><td><?=h($u['name'])?></td><td><?=h($u['username'])?></td><td><?=h($u['role'])?></td><td><?=h($u['province']??'-')?></td><td><?=h($u['sector']??'-')?></td><td><a class="btn" href="index.php?page=admin_users&edit=<?= (int)$u['id'] ?>">Edit</a></td></tr><?php endforeach; ?></table></section>
        <script>const role=document.getElementById('role');const sf=document.getElementById('sector-field');const sync=()=>sf.style.display=role.value==='viewer'?'none':'grid';role.addEventListener('change',sync);sync();</script>
        <?php render_footer(); exit;
    }

    if (!$isViewer && $page==='admin_submission_save' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $deadline=trim($_POST['deadline']??''); $details=trim($_POST['details']??'');
        $scope=$_POST['scope'] ?? 'all'; $agencyIds=$_POST['agency_ids'] ?? [];
        $tplPath=null;$tplName=null;
        if(isset($_FILES['file_template']) && $_FILES['file_template']['error']===UPLOAD_ERR_OK){$tplName=basename($_FILES['file_template']['name']);$safe=preg_replace('/[^a-zA-Z0-9._-]/','_',$tplName);$tplPath=uniqid('tpl_',true).'_'.$safe;move_uploaded_file($_FILES['file_template']['tmp_name'],UPLOAD_DIR.'/'.$tplPath);}        
        if($id>0){
            $sql='UPDATE submissions SET name=:n,deadline=:d,details=:x,updated_at=NOW()';$p=['n'=>$name,'d'=>$deadline,'x'=>$details,'id'=>$id];
            if($tplPath){$sql.=',file_template_path=:p,file_template_name=:fn';$p['p']=$tplPath;$p['fn']=$tplName;} $sql.=' WHERE id=:id';
            $pdo->prepare($sql)->execute($p); $pdo->prepare('DELETE FROM agency_submissions WHERE submission_id=:id')->execute(['id'=>$id]);
        } else {
            $pdo->prepare('INSERT INTO submissions (name,deadline,details,file_template_path,file_template_name,created_by,created_at,updated_at) VALUES (:n,:d,:x,:p,:fn,:c,NOW(),NOW())')->execute(['n'=>$name,'d'=>$deadline,'x'=>$details,'p'=>$tplPath,'fn'=>$tplName,'c'=>$user['id']]);
            $id=(int)$pdo->lastInsertId();
        }
        if($scope==='all'){
            $sql="SELECT id FROM users WHERE role='agency'";
            if($viewerProvince){$st=$pdo->prepare($sql.' AND province=:p');$st->execute(['p'=>$viewerProvince]);$agencyIds=array_column($st->fetchAll(),'id');}
            else{$agencyIds=array_column($pdo->query($sql)->fetchAll(),'id');}
        }
        $ins=$pdo->prepare("INSERT INTO agency_submissions (submission_id,agency_id,status,updated_at) VALUES (:s,:a,'not_submitted',NOW())");
        foreach($agencyIds as $aid){$ins->execute(['s'=>$id,'a'=>(int)$aid]);}
        flash('success','Submission saved.'); header('Location: index.php'); exit;
    }

    if (!$isViewer && $page==='admin_update_status' && $_SERVER['REQUEST_METHOD']==='POST') {
        $asid=(int)($_POST['agency_submission_id']??0); $sid=(int)($_POST['submission_id']??0);
        $status=$_POST['status']??'for_review'; $remarks=trim($_POST['admin_remarks']??'');
        $pdo->prepare('UPDATE agency_submissions SET status=:s,admin_remarks=:r,updated_at=NOW() WHERE id=:id')->execute(['s'=>$status,'r'=>$remarks,'id'=>$asid]);
        $x=$pdo->prepare('SELECT agency_id FROM agency_submissions WHERE id=:id'); $x->execute(['id'=>$asid]); $agency=(int)($x->fetch()['agency_id']??0);
        if($agency){$pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:u,:a,:m,NOW())')->execute(['u'=>$agency,'a'=>$asid,'m'=>'Document status updated to '.status_label($status).'. '.($remarks?:'')]);}
        flash('success','Document status updated.'); header('Location: index.php?page=submission_dashboard&id='.$sid); exit;
    }

    if (!$isViewer && $page==='admin_upload_files' && $_SERVER['REQUEST_METHOD']==='POST') {
        $asid=(int)($_POST['agency_submission_id']??0); $sid=(int)($_POST['submission_id']??0); $remarks=trim($_POST['remarks']??'');
        $added = upload_files($pdo, $asid, $user['id'], 'admin', $remarks, $_FILES['documents'] ?? []);
        flash($added ? 'success' : 'error', $added ? ($added.' file(s) uploaded by admin.') : 'No valid files uploaded.');
        header('Location: index.php?page=submission_dashboard&id='.$sid); exit;
    }

    if ($page==='submission_dashboard') {
        $sid=(int)($_GET['id']??0); $s=$pdo->prepare('SELECT * FROM submissions WHERE id=:id');$s->execute(['id'=>$sid]);$sub=$s->fetch(); if(!$sub){flash('error','Submission not found.');header('Location: index.php');exit;}
        $sql='SELECT a.*,u.name agency_name,u.province,u.sector FROM agency_submissions a JOIN users u ON u.id=a.agency_id WHERE a.submission_id=:sid';
        if($viewerProvince){$sql.=' AND u.province=:p';}
        $sql.=' ORDER BY u.name';
        $q=$pdo->prepare($sql); $params=['sid'=>$sid]; if($viewerProvince)$params['p']=$viewerProvince; $q->execute($params); $rows=$q->fetchAll();
        $docsByAs=[]; $d=$pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:a ORDER BY uploaded_at DESC');
        render_header('Submission Dashboard'); ?>
        <section class="card"><div class="row"><h2><?=h($sub['name'])?></h2><?php if(!$isViewer): ?><a class="btn" href="index.php?page=admin_submission_form&id=<?= (int)$sid ?>">Edit</a><?php endif; ?></div><p class="muted">Deadline: <?=h(format_datetime($sub['deadline']))?></p><p><?=nl2br(h($sub['details']??''))?></p></section>
        <?php foreach($rows as $r): $d->execute(['a'=>$r['id']]); $docs=$d->fetchAll(); ?>
            <section class="card"><div class="row"><h3><?=h($r['agency_name'])?> <span class="muted">(<?=h($r['province']?:'-')?>, <?=h($r['sector']?:'-')?>)</span></h3><span class="badge <?=h(status_badge_class($r['status']))?>"><?=h(status_label($r['status']))?></span></div>
            <p class="muted">Latest timestamp: <?=h(format_datetime($r['submitted_at']))?></p>
            <table><tr><th>File</th><th>By</th><th>Remarks</th><th>Timestamp</th><th>Download</th></tr><?php foreach($docs as $doc): ?><tr><td><?=h($doc['file_name'])?></td><td><?=h(ucfirst($doc['uploader_role']))?></td><td><?=h($doc['remarks']?:'-')?></td><td><?=h(format_datetime($doc['uploaded_at']))?></td><td><a class="btn ghost" href="uploads/<?=h($doc['file_path'])?>" download="<?=h($doc['file_name'])?>">Download</a></td></tr><?php endforeach; ?></table>
            <?php if(!$isViewer): ?>
                <form method="post" action="index.php?page=admin_update_status" class="form-grid two-col top-gap"><input type="hidden" name="agency_submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="submission_id" value="<?= (int)$sid ?>">
                    <label>Update Document Status<select name="status"><option value="for_review" <?= $r['status']==='for_review'?'selected':'' ?>>For Review</option><option value="for_compliance" <?= $r['status']==='for_compliance'?'selected':'' ?>>For Compliance</option><option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>Approved</option><option value="not_submitted" <?= $r['status']==='not_submitted'?'selected':'' ?>>Not Yet Submitted</option></select></label>
                    <label>Remarks<textarea name="admin_remarks" rows="3"><?=h($r['admin_remarks']??'')?></textarea></label>
                    <button type="submit">Save Status</button>
                </form>
                <form method="post" action="index.php?page=admin_upload_files" enctype="multipart/form-data" class="form-grid top-gap"><input type="hidden" name="agency_submission_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="submission_id" value="<?= (int)$sid ?>"><label>Upload Additional Files (Admin)<input type="file" name="documents[]" multiple></label><label>Remarks<textarea name="remarks" rows="2"></textarea></label><button type="submit">Upload Files</button></form>
            <?php endif; ?>
            </section>
        <?php endforeach; render_footer(); exit;
    }

    if (!$isViewer && $page==='admin_submission_form') {
        $id=(int)($_GET['id']??0); $s=['id'=>0,'name'=>'','deadline'=>'','details'=>'']; $selected=[];
        if($id){$q=$pdo->prepare('SELECT * FROM submissions WHERE id=:id');$q->execute(['id'=>$id]);$s=$q->fetch()?:$s;$a=$pdo->prepare('SELECT agency_id FROM agency_submissions WHERE submission_id=:id');$a->execute(['id'=>$id]);$selected=array_column($a->fetchAll(),'agency_id');}
        $sql="SELECT id,name,province,sector FROM users WHERE role='agency'"; $params=[]; if($viewerProvince){$sql.=' AND province=:p';$params['p']=$viewerProvince;} $sql.=' ORDER BY name'; $ag=$pdo->prepare($sql); $ag->execute($params); $agencies=$ag->fetchAll();
        render_header('Submission Form'); ?>
        <section class="card"><h2><?= $id?'Edit':'Add' ?> Submission</h2><form method="post" action="index.php?page=admin_submission_save" enctype="multipart/form-data" class="form-grid two-col"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <label>Name of Submission<input name="name" value="<?=h($s['name'])?>" required></label><label>Deadline<input type="datetime-local" name="deadline" value="<?= $s['deadline']?date('Y-m-d\TH:i',strtotime($s['deadline'])):'' ?>" required></label>
        <label class="span-2">Details/Instruction<textarea name="details" rows="4"><?=h($s['details']??'')?></textarea></label>
        <label>File Template<input type="file" name="file_template"></label>
        <label>Scope<select name="scope" id="scope"><option value="all">All Agencies</option><option value="selected">Selected Agencies</option></select></label>
        <div id="agency-box" class="span-2"><p class="muted">Select Agencies</p><?php foreach($agencies as $a): ?><label class="check"><input type="checkbox" name="agency_ids[]" value="<?= (int)$a['id'] ?>" <?= in_array($a['id'],$selected)?'checked':'' ?>> <?=h($a['name'])?> (<?=h($a['province']?:'-')?>)</label><?php endforeach; ?></div>
        <button type="submit">Save Submission</button></form></section>
        <script>const scope=document.getElementById('scope');const box=document.getElementById('agency-box');const t=()=>box.style.display=scope.value==='selected'?'block':'none';scope.addEventListener('change',t);t();</script>
        <?php render_footer(); exit;
    }

    $search=trim($_GET['q'] ?? ''); $like='%'.$search.'%';
    $sql='SELECT * FROM submissions WHERE name LIKE :q OR details LIKE :q ORDER BY deadline ASC';
    $st=$pdo->prepare($sql); $st->execute(['q'=>$like]); $subs=$st->fetchAll();
    render_header($isViewer ? 'Viewer Dashboard' : 'Admin Dashboard'); ?>
    <section class="card"><div class="row"><h2>Submissions</h2><form method="get" class="inline-search"><input type="text" name="q" value="<?=h($search)?>" placeholder="Search submissions..."><button type="submit">Search</button></form><?php if(!$isViewer): ?><a class="btn" href="index.php?page=admin_submission_form">Add Submission</a><?php endif; ?></div>
    <table><tr><th>Name</th><th>Deadline</th><th>Action</th><?php if(!$isViewer): ?><th>Edit</th><?php endif; ?></tr><?php foreach($subs as $s): ?><tr><td><?=h($s['name'])?></td><td><?=h(format_datetime($s['deadline']))?></td><td><a class="btn ghost" href="index.php?page=submission_dashboard&id=<?= (int)$s['id'] ?>">View</a></td><?php if(!$isViewer): ?><td><a class="btn" href="index.php?page=admin_submission_form&id=<?= (int)$s['id'] ?>">Edit</a></td><?php endif; ?></tr><?php endforeach; ?></table></section>
    <?php render_footer(); exit;
}
