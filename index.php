<?php
declare(strict_types=1);
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/helpers.php';
$page = $_GET['page'] ?? 'home';
$pdo = db();

function provinces(): array { return ['Aklan','Antique','Capiz','Guimaras','Iloilo','Negros Occidental']; }
function sectors(): array { return ['NGA','LGU','GOCC','SUC/LUC']; }
function badge(string $s): string { return '<span class="badge '.h(status_badge_class($s)).' nowrap">'.h(status_label($s)).'</span>'; }
function notify_admins(PDO $pdo, int $asid, string $msg): void {
  $ids=array_column($pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(),'id');
  $ins=$pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:u,:a,:m,NOW())');
  foreach($ids as $id){$ins->execute(['u'=>$id,'a'=>$asid,'m'=>$msg]);}
}
function submission_full_name(array $sub): string {
  $parts=[];
  if(!empty($sub['hierarchy_path'])){$parts=array_filter(array_map('trim',explode('/',(string)$sub['hierarchy_path'])));}
  $parts[]=(string)($sub['name']??'');
  return implode(' / ',$parts);
}
function render_header(string $title): void {
  $u=current_user(); ?>
  <!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title><link rel="stylesheet" href="public/style.css"></head><body>
  <header class="topbar"><div class="brand">ASMS</div><nav>
  <?php if($u): ?><a href="index.php">Dashboard</a>
    <?php if($u['role']==='agency'): ?><a href="index.php?page=notifications">Notifications</a><a href="index.php?page=my_documents">Submitted Documents</a><?php endif; ?>
    <?php if($u['role']==='admin'): ?><a href="index.php?page=admin_notifications">Notifications</a><a href="index.php?page=admin_users">Accounts</a><a href="index.php?page=statistics">Statistics</a><?php endif; ?>
    <a href="index.php?page=logout">Logout</a>
  <?php else: ?><a href="index.php?page=login">Login</a><?php endif; ?>
  </nav></header><main class="container fade-in">
  <?php $f=flash(); if($f) echo '<div class="alert '.h($f['type']).'">'.h($f['message']).'</div>'; ?>
<?php }
function render_footer(): void { ?>
<script>
function filterTable(input, tableSel){const q=input.value.toLowerCase();document.querySelectorAll(tableSel+' tbody tr').forEach(r=>{r.style.display=r.innerText.toLowerCase().includes(q)?'':'none';});}
document.querySelectorAll('[data-search-target]').forEach(i=>{i.addEventListener('input',()=>filterTable(i,i.dataset.searchTarget));});
const scopeSel=document.getElementById('scope');const agencyModal=document.getElementById('agency-modal');if(scopeSel&&agencyModal){scopeSel.addEventListener('change',()=>{if(scopeSel.value==='selected'){agencyModal.showModal();}else{document.querySelectorAll('.agency-opt input[type=checkbox]').forEach(c=>c.checked=false);syncSelectedAgencies();}});if(scopeSel.value==='selected'){setTimeout(()=>agencyModal.showModal(),0);}}
const openAgencyEdit=document.getElementById('open-agency-edit'); if(openAgencyEdit&&agencyModal){openAgencyEdit.onclick=()=>agencyModal.showModal();}
const closeAgencyModal=document.getElementById('close-agency-modal'); if(closeAgencyModal&&agencyModal){closeAgencyModal.onclick=()=>agencyModal.close();}
const agencySearch=document.getElementById('agency-search'); if(agencySearch){agencySearch.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('.agency-opt').forEach(x=>x.style.display=x.innerText.toLowerCase().includes(q)?'flex':'none');});}
function syncSelectedAgencies(){
  const out=document.getElementById('selected-agencies-list'); const count=document.getElementById('selected-count'); if(!out||!count)return;
  const selected=[...document.querySelectorAll('.agency-opt input:checked')].map(i=>i.dataset.label);
  count.textContent='Selected: '+selected.length;
  out.innerHTML=selected.length?selected.map(v=>`<li>${v}</li>`).join(''):'<li class="muted">No agency selected.</li>';
}
document.querySelectorAll('.agency-opt input').forEach(c=>c.addEventListener('change',syncSelectedAgencies)); syncSelectedAgencies();

const useHierarchy=document.getElementById('use_hierarchy');
const hierarchyConfig=document.getElementById('hierarchy-config');
const parentSubmission=document.getElementById('parent_submission_id');
const hierarchyLevels=document.getElementById('hierarchy-levels');
const addHierarchyLevel=document.getElementById('add-hierarchy-level');
const hierarchyPreview=document.getElementById('hierarchy-preview');
function hierarchyInput(value=''){
  return `<div class="hierarchy-level"><input name="hierarchy_levels[]" placeholder="Category / Folder name" value="${value.replaceAll('"','&quot;')}"><button type="button" class="btn btn-edit remove-hierarchy-level">Remove</button></div>`;
}
function syncHierarchyVisibility(){
  if(!useHierarchy||!hierarchyConfig)return;
  hierarchyConfig.style.display=useHierarchy.checked?'grid':'none';
}
function syncHierarchyPreview(){
  if(!hierarchyPreview)return;
  if(useHierarchy && !useHierarchy.checked){hierarchyPreview.textContent='Path Preview: Top-level submission';return;}
  const parentLabel=parentSubmission&&parentSubmission.value?parentSubmission.options[parentSubmission.selectedIndex]?.textContent.trim():'';
  const levels=[...document.querySelectorAll('input[name="hierarchy_levels[]"]')].map(i=>i.value.trim()).filter(Boolean);
  const parts=[];
  if(parentLabel)parts.push(parentLabel);
  parts.push(...levels);
  hierarchyPreview.textContent=parts.length?`Path Preview: ${parts.join(' / ')}`:'Path Preview: Top-level submission';
}
if(useHierarchy){useHierarchy.addEventListener('change',()=>{syncHierarchyVisibility();syncHierarchyPreview();});syncHierarchyVisibility();}
if(addHierarchyLevel && hierarchyLevels){addHierarchyLevel.addEventListener('click',()=>{hierarchyLevels.insertAdjacentHTML('beforeend',hierarchyInput(''));syncHierarchyPreview();});}
if(hierarchyLevels){
  hierarchyLevels.addEventListener('click',e=>{if(e.target.classList.contains('remove-hierarchy-level')){e.target.parentElement.remove();syncHierarchyPreview();}});
  hierarchyLevels.addEventListener('input',e=>{if(e.target.name==='hierarchy_levels[]')syncHierarchyPreview();});
}
if(parentSubmission){parentSubmission.addEventListener('change',syncHierarchyPreview);}
syncHierarchyPreview();

const roleSel=document.getElementById('role');const sec=document.getElementById('sector-wrap');if(roleSel&&sec){const s=()=>sec.style.display=roleSel.value==='viewer'?'none':'grid';roleSel.addEventListener('change',s);s();}
const accRole=document.getElementById('acc-role');const accSector=document.getElementById('acc-sector-wrap');if(accRole&&accSector){const t=()=>accSector.style.display=accRole.value==='viewer'?'none':'grid';accRole.addEventListener('change',t);t();}
const selectedBox=document.getElementById('selected-agencies-box');if(scopeSel&&selectedBox){const v=()=>selectedBox.style.display=scopeSel.value==='selected'?'block':'none';scopeSel.addEventListener('change',v);v();}

const accountModal=document.getElementById('account-edit-modal');
document.querySelectorAll('.open-account-edit').forEach(btn=>btn.addEventListener('click',()=>{
  document.getElementById('acc-id').value=btn.dataset.id;
  document.getElementById('acc-role').value=btn.dataset.role;
  document.getElementById('acc-name').value=btn.dataset.name;
  document.getElementById('acc-username').value=btn.dataset.username;
  document.getElementById('acc-province').value=btn.dataset.province;
  document.getElementById('acc-sector').value=btn.dataset.sector;
  document.getElementById('acc-password').value='';
  if(accountModal) accountModal.showModal();
}));
const closeAccountModal=document.getElementById('close-account-modal'); if(closeAccountModal&&accountModal){closeAccountModal.onclick=()=>accountModal.close();}

const updateModal=document.getElementById('doc-update-modal');
document.querySelectorAll('.open-doc-update').forEach(btn=>btn.addEventListener('click',()=>{
  document.getElementById('doc-id').value=btn.dataset.docId;
  document.getElementById('doc-submission-id').value=btn.dataset.submissionId;
  document.getElementById('doc-file').textContent=btn.dataset.file;
  document.getElementById('doc-status').value=btn.dataset.status;
  document.getElementById('doc-remarks').value=btn.dataset.remarks;
  if(updateModal) updateModal.showModal();
}));
const closeDocModal=document.getElementById('close-doc-modal'); if(closeDocModal&&updateModal){closeDocModal.onclick=()=>updateModal.close();}
</script></main></body></html>
<?php }

if($page==='logout'){logout();header('Location:index.php?page=login');exit;}
if($page==='login'){
  if($_SERVER['REQUEST_METHOD']==='POST'){ if(attempt_login(trim($_POST['username']??''),(string)($_POST['password']??''))){header('Location:index.php');exit;} flash('error','Invalid credentials'); header('Location:index.php?page=login'); exit; }
  render_header('Login'); ?>
  <section class="card auth-card"><h1>ASMS Login</h1><form method="post" class="form-grid"><label>Username<input name="username" required></label><label>Password<input type="password" name="password" required></label><button>Sign In</button></form></section>
  <?php render_footer(); exit;
}

require_login(); $user=current_user(); $isViewer=$user['role']==='viewer';

if($user['role']==='agency'){
  if($page==='agency_upload' && $_SERVER['REQUEST_METHOD']==='POST'){
    $asid=(int)($_POST['agency_submission_id']??0); $remarks=trim($_POST['remarks']??'');
    $a=$pdo->prepare('SELECT a.*,s.name submission_name,s.hierarchy_path FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id AND a.agency_id=:aid');$a->execute(['id'=>$asid,'aid'=>$user['id']]);$as=$a->fetch();
    if(!$as){flash('error','Invalid.');header('Location:index.php');exit;}
    $files=$_FILES['documents']??[]; $batch=uniqid('batch_',true); $count=0;
    $ins=$pdo->prepare('INSERT INTO uploaded_documents (agency_submission_id,uploader_user_id,uploader_role,batch_token,file_name,file_path,user_remarks,document_status,uploaded_at) VALUES (:a,:u,"agency",:b,:f,:p,:r,"for_review",NOW())');
    if(isset($files['name']) && is_array($files['name'])) foreach($files['name'] as $i=>$n){ if(($files['error'][$i]??1)!==UPLOAD_ERR_OK) continue; $orig=basename((string)$n); $stored=uniqid('doc_',true).'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig); if(move_uploaded_file($files['tmp_name'][$i],UPLOAD_DIR.'/'.$stored)){ $ins->execute(['a'=>$asid,'u'=>$user['id'],'b'=>$batch,'f'=>$orig,'p'=>$stored,'r'=>$remarks]); $count++; }}
    if(!$count){flash('error','No files uploaded');header('Location:index.php?page=agency_submission&id='.$asid);exit;}
    $pdo->prepare("UPDATE agency_submissions SET latest_status='for_review', submitted_at=NOW(), updated_at=NOW() WHERE id=:id")->execute(['id'=>$asid]);
    notify_admins($pdo,$asid,$user['name'].' uploaded '.$count.' document(s) for '.$as['submission_name']);
    flash('success','Uploaded '.$count.' file(s).'); header('Location:index.php?page=agency_submission&id='.$asid); exit;
  }
  if($page==='notifications'){
    render_header('Notifications');
    $st=$pdo->prepare('SELECT n.*,s.name submission_name,s.hierarchy_path FROM notifications n LEFT JOIN agency_submissions a ON a.id=n.agency_submission_id LEFT JOIN submissions s ON s.id=a.submission_id WHERE n.recipient_user_id=:u ORDER BY n.created_at DESC');$st->execute(['u'=>$user['id']]);$rows=$st->fetchAll(); ?>
    <section class="card"><div class="row"><h2>Notifications</h2><input data-search-target="#notif-table" placeholder="Search..."></div><?php if(!$rows): ?><p class="muted">No notifications yet.</p><?php else: ?><table id="notif-table"><thead><tr><th>Submission</th><th>Message</th><th>Date</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=h($r['submission_name']?:'-')?></td><td><?=h($r['message'])?></td><td><?=h(format_datetime($r['created_at']))?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
    <?php render_footer(); exit;
  }
  if($page==='my_documents'){
    render_header('Submitted Documents');
    $st=$pdo->prepare('SELECT d.*,a.id agency_submission_id,s.name submission_name,s.hierarchy_path FROM uploaded_documents d JOIN agency_submissions a ON a.id=d.agency_submission_id JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:a ORDER BY d.uploaded_at DESC');$st->execute(['a'=>$user['id']]);$rows=$st->fetchAll(); ?>
    <section class="card"><div class="row"><h2>Submitted Documents</h2><input data-search-target="#doc-table" placeholder="Search..."></div><?php if(!$rows): ?><p class="muted">No submissions yet.</p><a class="btn btn-edit" href="index.php">Back</a><?php else: ?><table id="doc-table"><thead><tr><th>Submission</th><th>File</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=h($r['submission_name'])?></td><td><?=h($r['file_name'])?></td><td><?=badge($r['document_status'])?></td><td><?=h(format_datetime($r['uploaded_at']))?></td><td class="actions compact"><a class="btn btn-view" href="uploads/<?=h($r['file_path'])?>" download="<?=h($r['file_name'])?>">Download</a><a class="btn btn-edit" href="index.php?page=agency_submission&id=<?= (int)$r['agency_submission_id'] ?>">Open</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
    <?php render_footer(); exit;
  }
  if($page==='agency_submission'){
    $id=(int)($_GET['id']??0);$s=$pdo->prepare('SELECT a.*,s.name,s.details,s.deadline FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:i AND a.agency_id=:a');$s->execute(['i'=>$id,'a'=>$user['id']]);$sub=$s->fetch(); if(!$sub){flash('error','Not found');header('Location:index.php');exit;}
    $t=$pdo->prepare('SELECT * FROM submission_templates WHERE submission_id=:s ORDER BY uploaded_at DESC');$t->execute(['s'=>$sub['submission_id']]);$tpls=$t->fetchAll();
    $d=$pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:a ORDER BY uploaded_at DESC');$d->execute(['a'=>$id]);$docs=$d->fetchAll(); $groups=[]; foreach($docs as $doc){$groups[$doc['batch_token']][]=$doc;}
    render_header('Submission'); ?>
    <section class="card submission-hero"><h2><?=h(submission_full_name($sub))?></h2><p><?=nl2br(h($sub['details']??''))?></p><p><strong>Deadline:</strong> <?=h(date('M d, Y',strtotime($sub['deadline'])))?></p><p><strong>Latest Status:</strong> <?=badge($sub['latest_status'])?></p><div class="row-wrap"><?php foreach($tpls as $tp): ?><div class="template-item"><span><?=h($tp['file_name'])?></span><a class="btn btn-view" href="uploads/<?=h($tp['file_path'])?>" download="<?=h($tp['file_name'])?>">Download</a></div><?php endforeach; ?></div></section>
    <section class="card"><h3><?= $docs?'Upload New Documents':'Upload Documents' ?></h3><form method="post" action="index.php?page=agency_upload" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="agency_submission_id" value="<?= (int)$sub['id'] ?>"><label>Files<input type="file" name="documents[]" multiple required></label><label>Remarks<textarea name="remarks"></textarea></label><button>Upload</button></form></section>
    <section class="card"><h3>Uploaded Batches</h3><?php foreach($groups as $batch=>$items): $ts=$items[0]['uploaded_at']??null; ?><details open><summary><?=h(format_datetime($ts))?> (<?=count($items)?> files)</summary><table><thead><tr><th>File</th><th>Status</th><th>Remarks</th><th>Date</th><th>Actions</th></tr></thead><tbody><?php foreach($items as $x): ?><tr><td><?=h($x['file_name'])?></td><td><?=badge($x['document_status'])?></td><td><?=h($x['admin_remarks']?:($x['user_remarks']?:'-'))?></td><td><?=h(format_datetime($x['uploaded_at']))?></td><td class="actions compact"><a class="btn btn-view" href="uploads/<?=h($x['file_path'])?>" download="<?=h($x['file_name'])?>">Download</a></td></tr><?php endforeach; ?></tbody></table></details><?php endforeach; ?></section>
    <?php render_footer(); exit;
  }
  render_header('Agency Dashboard');
  $st=$pdo->prepare('SELECT a.id,a.latest_status,a.submitted_at,s.name,s.hierarchy_path,s.deadline FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.agency_id=:a ORDER BY s.deadline');$st->execute(['a'=>$user['id']]);$rows=$st->fetchAll(); ?>
  <section class="card"><h2>My Submissions</h2><?php if(!$rows): ?><p class="muted">No submission yet.</p><a class="btn btn-edit" href="index.php">Back</a><?php else: ?><table><thead><tr><th>Name</th><th>Deadline</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=h(submission_full_name(['name'=>$r['name'],'hierarchy_path'=>$r['hierarchy_path']??'']))?></td><td><?=h(date('M d, Y',strtotime($r['deadline'])))?></td><td><?=badge($r['latest_status'])?></td><td class="actions compact"><a class="btn btn-view" href="index.php?page=agency_submission&id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
  <?php render_footer(); exit;
}

if($user['role']==='admin' || $isViewer){
  $scope=$isViewer ? ($user['province']??null) : null;
  if(!$isViewer && $page==='admin_notifications'){
    render_header('Notifications');
    $st=$pdo->prepare('SELECT n.*,u.name agency_name,s.name submission_name,s.hierarchy_path FROM notifications n LEFT JOIN agency_submissions a ON a.id=n.agency_submission_id LEFT JOIN users u ON u.id=a.agency_id LEFT JOIN submissions s ON s.id=a.submission_id WHERE n.recipient_user_id=:u ORDER BY n.created_at DESC');$st->execute(['u'=>$user['id']]);$rows=$st->fetchAll(); ?>
    <section class="card"><div class="row"><h2>Notifications</h2><input data-search-target="#an-table" placeholder="Search notifications..."></div><?php if(!$rows): ?><p class="muted">No notifications yet.</p><?php else: ?><table id="an-table"><thead><tr><th>Agency</th><th>Submission</th><th>Message</th><th>Date</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=h($r['agency_name']?:'-')?></td><td><?=h($r['submission_name']?:'-')?></td><td><?=h($r['message'])?></td><td><?=h(format_datetime($r['created_at']))?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
    <?php render_footer(); exit;
  }

  if(!$isViewer && $page==='admin_user_save' && $_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);$role=$_POST['role']??'agency';$data=['n'=>trim($_POST['name']??''),'u'=>trim($_POST['username']??''),'r'=>$role,'p'=>($_POST['province']?:null),'s'=>$role==='agency'?($_POST['sector']?:null):null];
    if($id>0){$sql='UPDATE users SET name=:n,username=:u,role=:r,province=:p,sector=:s';if(!empty($_POST['password'])){$sql.=',password_hash=:ph';$data['ph']=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);} $sql.=' WHERE id=:id';$data['id']=$id;$pdo->prepare($sql)->execute($data);} else {$data['ph']=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);$pdo->prepare('INSERT INTO users (name,username,password_hash,role,province,sector,created_at) VALUES (:n,:u,:ph,:r,:p,:s,NOW())')->execute($data);} flash('success','Account saved.'); header('Location:index.php?page=admin_users'); exit;
  }
  if(!$isViewer && $page==='admin_users'){
    $u=$pdo->query("SELECT id,name,username,role,province,sector FROM users WHERE role IN ('agency','viewer') ORDER BY role,name")->fetchAll();
    render_header('Accounts'); ?>
    <section class="card"><h2>Add Agency / Viewer</h2><form method="post" action="index.php?page=admin_user_save" class="form-grid two-col"><input type="hidden" name="id" value="0"><label>Type<select id="role" name="role"><option value="agency">Agency</option><option value="viewer">Viewer</option></select></label><label>Name<input name="name" required></label><label>Username<input name="username" required></label><label>Password<input type="password" name="password" required></label><label>Province<select name="province"><option value="">Select</option><?php foreach(provinces() as $p): ?><option value="<?=h($p)?>"><?=h($p)?></option><?php endforeach; ?></select></label><label id="sector-wrap">Sector<select name="sector"><option value="">Select</option><?php foreach(sectors() as $s): ?><option value="<?=h($s)?>"><?=h($s)?></option><?php endforeach; ?></select></label><button>Save</button></form></section>
    <section class="card"><div class="row"><h3>Existing</h3><input data-search-target="#users-table" placeholder="Type to search..."></div><table id="users-table"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Province</th><th>Sector</th><th>Actions</th></tr></thead><tbody><?php foreach($u as $r): ?><tr><td><?=h($r['name'])?></td><td><?=h($r['username'])?></td><td><?=h($r['role'])?></td><td><?=h($r['province']??'-')?></td><td><?=h($r['sector']??'-')?></td><td class="actions compact"><button type="button" class="btn btn-edit open-account-edit" data-id="<?= (int)$r['id'] ?>" data-role="<?=h($r['role'])?>" data-name="<?=h($r['name'])?>" data-username="<?=h($r['username'])?>" data-province="<?=h($r['province']??'')?>" data-sector="<?=h($r['sector']??'')?>">Edit</button></td></tr><?php endforeach; ?></tbody></table></section>
    <dialog id="account-edit-modal" class="agency-modal"><div class="row"><h3>Edit Account</h3><button type="button" class="btn btn-edit" id="close-account-modal">Close</button></div><form method="post" action="index.php?page=admin_user_save" class="form-grid two-col"><input type="hidden" name="id" id="acc-id"><label>Type<select name="role" id="acc-role"><option value="agency">Agency</option><option value="viewer">Viewer</option></select></label><label>Name<input name="name" id="acc-name" required></label><label>Username<input name="username" id="acc-username" required></label><label>Password (optional)<input type="password" name="password" id="acc-password"></label><label>Province<select name="province" id="acc-province"><option value="">Select</option><?php foreach(provinces() as $p): ?><option value="<?=h($p)?>"><?=h($p)?></option><?php endforeach; ?></select></label><label id="acc-sector-wrap">Sector<select name="sector" id="acc-sector"><option value="">Select</option><?php foreach(sectors() as $s): ?><option value="<?=h($s)?>"><?=h($s)?></option><?php endforeach; ?></select></label><button>Update Account</button></form></dialog>
    <?php render_footer(); exit;
  }

  if(!$isViewer && $page==='admin_submission_save' && $_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$deadline=trim($_POST['deadline']??'');$details=trim($_POST['details']??'');$scopeType=$_POST['scope']??'all';$agencyIds=$_POST['agency_ids']??[];
    $useHierarchy=isset($_POST['use_hierarchy']) && $_POST['use_hierarchy']==='1';
    $parentId=$useHierarchy?(int)($_POST['parent_submission_id']??0):0;
    $levelsRaw=$_POST['hierarchy_levels']??[]; if(!is_array($levelsRaw)){$levelsRaw=[];}
    $levels=[]; foreach($levelsRaw as $lv){$clean=trim((string)$lv); if($clean!==''){$levels[]=$clean;}}
    $hierarchyPath=null;
    if($useHierarchy){
      if($parentId>0){$chk=$pdo->prepare('SELECT id,name,hierarchy_path FROM submissions WHERE id=:id');$chk->execute(['id'=>$parentId]);$parent=$chk->fetch();if(!$parent || ($id>0 && (int)$parent['id']===$id)){flash('error','Invalid parent submission selected.');header('Location:index.php?page=admin_submission_form'.($id>0?'&id='.$id:''));exit;}$parentPathParts=[];if(!empty($parent['hierarchy_path'])){$parentPathParts=array_filter(array_map('trim',explode('/',(string)$parent['hierarchy_path'])));} $parentPathParts[]=(string)$parent['name'];$hierarchyPath=implode('/',$parentPathParts);} elseif($levels){$hierarchyPath=implode('/',$levels);}
    } else {$parentId=0;}
    if($id>0){$pdo->prepare('UPDATE submissions SET name=:n,deadline=:d,details=:x,parent_submission_id=:p,hierarchy_path=:h,updated_at=NOW() WHERE id=:i')->execute(['n'=>$name,'d'=>$deadline,'x'=>$details,'p'=>$parentId?:null,'h'=>$hierarchyPath,'i'=>$id]);$pdo->prepare('DELETE FROM agency_submissions WHERE submission_id=:i')->execute(['i'=>$id]);}
    else {$pdo->prepare('INSERT INTO submissions (name,parent_submission_id,hierarchy_path,deadline,details,created_by,created_at,updated_at) VALUES (:n,:p,:h,:d,:x,:c,NOW(),NOW())')->execute(['n'=>$name,'p'=>$parentId?:null,'h'=>$hierarchyPath,'d'=>$deadline,'x'=>$details,'c'=>$user['id']]);$id=(int)$pdo->lastInsertId();}
    if(isset($_FILES['file_templates']['name']) && is_array($_FILES['file_templates']['name'])){ $ins=$pdo->prepare('INSERT INTO submission_templates (submission_id,file_name,file_path,uploaded_at) VALUES (:s,:f,:p,NOW())'); foreach($_FILES['file_templates']['name'] as $i=>$n){ if(($_FILES['file_templates']['error'][$i]??1)!==UPLOAD_ERR_OK) continue; $orig=basename((string)$n); $stored=uniqid('tpl_',true).'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig); if(move_uploaded_file($_FILES['file_templates']['tmp_name'][$i],UPLOAD_DIR.'/'.$stored)) $ins->execute(['s'=>$id,'f'=>$orig,'p'=>$stored]); }}
    if($scopeType==='all'){ $sql="SELECT id FROM users WHERE role='agency'"; if($scope){$st=$pdo->prepare($sql.' AND province=:p');$st->execute(['p'=>$scope]);$agencyIds=array_column($st->fetchAll(),'id');} else $agencyIds=array_column($pdo->query($sql)->fetchAll(),'id'); }
    $ins=$pdo->prepare("INSERT INTO agency_submissions (submission_id,agency_id,latest_status,updated_at) VALUES (:s,:a,'not_submitted',NOW())"); foreach($agencyIds as $aid)$ins->execute(['s'=>$id,'a'=>(int)$aid]);
    flash('success','Submission saved.'); header('Location:index.php'); exit;
  }

  if(!$isViewer && $page==='admin_update_document' && $_SERVER['REQUEST_METHOD']==='POST'){
    $doc=(int)($_POST['document_id']??0);$sid=(int)($_POST['submission_id']??0);$status=$_POST['document_status']??'for_review';$remarks=trim($_POST['admin_remarks']??'');
    $pdo->prepare('UPDATE uploaded_documents SET document_status=:s,admin_remarks=:r,reviewed_at=NOW() WHERE id=:id')->execute(['s'=>$status,'r'=>$remarks,'id'=>$doc]);
    $row=$pdo->prepare('SELECT agency_submission_id FROM uploaded_documents WHERE id=:id');$row->execute(['id'=>$doc]);$asid=(int)($row->fetch()['agency_submission_id']??0);
    if($asid){$pdo->prepare('UPDATE agency_submissions SET latest_status=:s,updated_at=NOW() WHERE id=:id')->execute(['s'=>$status,'id'=>$asid]);$z=$pdo->prepare('SELECT a.agency_id,s.name submission_name,s.hierarchy_path FROM agency_submissions a JOIN submissions s ON s.id=a.submission_id WHERE a.id=:id');$z->execute(['id'=>$asid]);$zr=$z->fetch();if($zr){$pdo->prepare('INSERT INTO notifications (recipient_user_id,agency_submission_id,message,created_at) VALUES (:u,:a,:m,NOW())')->execute(['u'=>$zr['agency_id'],'a'=>$asid,'m'=>'Status updated to '.status_label($status).' for '.$zr['submission_name']]);}}
    flash('success','Document updated.'); header('Location:index.php?page=agency_documents&asid='.$asid.'&sid='.$sid); exit;
  }

  if(!$isViewer && $page==='admin_submission_form'){
    $id=(int)($_GET['id']??0);$s=['id'=>0,'name'=>'','deadline'=>'','details'=>'','parent_submission_id'=>null,'hierarchy_path'=>''];$selected=[]; if($id){$x=$pdo->prepare('SELECT * FROM submissions WHERE id=:i');$x->execute(['i'=>$id]);$s=$x->fetch()?:$s;$y=$pdo->prepare('SELECT agency_id FROM agency_submissions WHERE submission_id=:i');$y->execute(['i'=>$id]);$selected=array_column($y->fetchAll(),'agency_id');}
    $sql="SELECT id,name,province,sector FROM users WHERE role='agency'"; if($scope){$sql.=' AND province=:p';$st=$pdo->prepare($sql.' ORDER BY name');$st->execute(['p'=>$scope]);} else {$st=$pdo->prepare($sql.' ORDER BY name');$st->execute();} $agencies=$st->fetchAll();
    $parents=$pdo->query('SELECT id,name,hierarchy_path FROM submissions ORDER BY name')->fetchAll();
    $selectedScope=$selected?'selected':'all';
    $hierarchyLevels=[]; if(!empty($s['hierarchy_path']) && empty($s['parent_submission_id'])){$hierarchyLevels=array_filter(array_map('trim',explode('/',(string)$s['hierarchy_path'])));}
    $useHierarchyDefault=!empty($s['parent_submission_id']) || !empty($s['hierarchy_path']);
    render_header('Submission Form'); ?>
    <section class="card"><h2><?= $id?'Edit':'Add' ?> Submission</h2><form method="post" action="index.php?page=admin_submission_save" enctype="multipart/form-data" class="form-grid two-col"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><label>Name<input name="name" value="<?=h($s['name'])?>" required></label><label>Deadline<input type="date" name="deadline" value="<?=h($s['deadline'])?>" required></label><label class="span-2"><input type="checkbox" id="use_hierarchy" name="use_hierarchy" value="1" <?= $useHierarchyDefault?'checked':'' ?>> Enable hierarchy organization (optional)</label><div class="span-2 form-grid two-col" id="hierarchy-config"><label>Parent Submission<select name="parent_submission_id" id="parent_submission_id"><option value="0">None (Start fresh)</option><?php foreach($parents as $parent): if((int)$parent['id']===(int)$s['id']) continue; $parts=[]; if(!empty($parent['hierarchy_path'])){$parts=array_filter(array_map('trim',explode('/',(string)$parent['hierarchy_path'])));} $parts[]=(string)$parent['name']; $full=implode(' / ',$parts); ?><option value="<?= (int)$parent['id'] ?>" <?= (int)($s['parent_submission_id']??0)===(int)$parent['id']?'selected':'' ?>><?=h($full)?></option><?php endforeach; ?></select></label><div><label>Hierarchy Levels (multiple)</label><div id="hierarchy-levels" class="form-grid"><?php if($hierarchyLevels): foreach($hierarchyLevels as $lv): ?><div class="hierarchy-level"><input name="hierarchy_levels[]" value="<?=h($lv)?>" placeholder="Category / Folder name"><button type="button" class="btn btn-edit remove-hierarchy-level">Remove</button></div><?php endforeach; else: ?><div class="hierarchy-level"><input name="hierarchy_levels[]" placeholder="Category / Folder name"><button type="button" class="btn btn-edit remove-hierarchy-level">Remove</button></div><?php endif; ?></div><button type="button" class="btn btn-edit" id="add-hierarchy-level">+ Add Another Level</button></div></div><div class="span-2 muted" id="hierarchy-preview">Path Preview: Top-level submission</div><label class="span-2">Details<textarea name="details" rows="4"><?=h($s['details']??'')?></textarea></label><label class="span-2">File Templates (multiple)<input type="file" name="file_templates[]" multiple></label><label>Scope<select name="scope" id="scope"><option value="all" <?= $selectedScope==='all'?'selected':'' ?>>All Agencies</option><option value="selected" <?= $selectedScope==='selected'?'selected':'' ?>>Selected Agencies</option></select></label><div id="selected-count" class="muted">Selected: <?=count($selected)?></div><div class="span-2 selected-box" id="selected-agencies-box"><h4>Selected Agencies</h4><ul id="selected-agencies-list"></ul><button type="button" class="btn btn-edit" id="open-agency-edit">Edit Selected Agency</button></div><button>Save Submission</button>
    <dialog id="agency-modal" class="agency-modal"><div class="row"><h3>Select Agencies</h3><button type="button" class="btn btn-edit" id="close-agency-modal">Close</button></div><input id="agency-search" placeholder="Search agency name/province"><div class="agency-list line-list"><?php foreach($agencies as $a): ?><label class="agency-opt"><input type="checkbox" name="agency_ids[]" value="<?= (int)$a['id'] ?>" <?= in_array((int)$a['id'],array_map('intval',$selected),true)?'checked':'' ?> data-label="<?=h($a['name'])?> (<?=h($a['province']?:'-')?>)"> <span><strong><?=h($a['name'])?></strong><small><?=h($a['province']?:'-')?> • <?=h($a['sector']?:'-')?></small></span></label><?php endforeach; ?></div></dialog>
    </form></section>
    <?php render_footer(); exit;
  }

  if($page==='submission_dashboard'){
    $sid=(int)($_GET['id']??0);$x=$pdo->prepare('SELECT * FROM submissions WHERE id=:i');$x->execute(['i'=>$sid]);$sub=$x->fetch();if(!$sub){flash('error','Not found');header('Location:index.php');exit;}
    $sql='SELECT a.id as agency_submission_id,a.latest_status,a.submitted_at,u.name agency_name,u.province,u.sector FROM agency_submissions a JOIN users u ON u.id=a.agency_id WHERE a.submission_id=:s';$params=['s'=>$sid]; if($scope){$sql.=' AND u.province=:p';$params['p']=$scope;} $sql.=' ORDER BY u.name'; $a=$pdo->prepare($sql);$a->execute($params);$agencies=$a->fetchAll();
    render_header('Submission Dashboard'); ?>
    <section class="card submission-hero"><h2><?=h(submission_full_name($sub))?></h2><p class="muted">Deadline: <?=h(date('M d, Y',strtotime($sub['deadline'])))?></p><?php if(!$isViewer): ?><a class="btn btn-edit" href="index.php?page=admin_submission_form&id=<?= (int)$sid ?>">Edit</a><?php endif; ?></section>
    <section class="card agency-list-card"><div class="row"><h3>Agencies</h3><input data-search-target="#ag-table" placeholder="Type to search agencies..."></div><table id="ag-table"><thead><tr><th>Agency</th><th>Province</th><th>Sector</th><th>Latest Status</th><th>Date</th><th>Actions</th></tr></thead><tbody><?php foreach($agencies as $ag): ?><tr><td><?=h($ag['agency_name'])?></td><td><?=h($ag['province']?:'-')?></td><td><?=h($ag['sector']?:'-')?></td><td><?=badge($ag['latest_status'])?></td><td><?=h(format_datetime($ag['submitted_at']))?></td><td class="actions compact"><a class="btn btn-view" href="index.php?page=agency_documents&asid=<?= (int)$ag['agency_submission_id'] ?>&sid=<?= (int)$sid ?>"><?= $isViewer ? 'View' : 'View' ?></a></td></tr><?php endforeach; ?></tbody></table></section>
    <?php render_footer(); exit;
  }

  if($page==='agency_documents'){
    $asid=(int)($_GET['asid']??0);$sid=(int)($_GET['sid']??0);
    $sql='SELECT a.*,u.name agency_name,u.province,u.sector,s.name submission_name,s.hierarchy_path FROM agency_submissions a JOIN users u ON u.id=a.agency_id JOIN submissions s ON s.id=a.submission_id WHERE a.id=:a';$params=['a'=>$asid]; if($scope){$sql.=' AND u.province=:p';$params['p']=$scope;}
    $x=$pdo->prepare($sql);$x->execute($params);$head=$x->fetch(); if(!$head){flash('error','Not allowed');header('Location:index.php?page=submission_dashboard&id='.$sid);exit;}
    $d=$pdo->prepare('SELECT * FROM uploaded_documents WHERE agency_submission_id=:a ORDER BY uploaded_at DESC');$d->execute(['a'=>$asid]);$docs=$d->fetchAll(); $groups=[]; foreach($docs as $doc){$groups[$doc['batch_token']][]=$doc;}
    render_header('Agency Documents'); ?>
    <section class="card submission-hero"><h2><?=h($head['agency_name'])?> - <?=h(submission_full_name(['name'=>$head['submission_name'],'hierarchy_path'=>$head['hierarchy_path']??'']))?></h2><p class="muted"><?=h($head['province']?:'-')?> • <?=h($head['sector']?:'-')?></p></section>
    <?php $i=0; foreach($groups as $batch=>$items): $ts=$items[0]['uploaded_at']??null; ?><section class="card"><details <?= $i===0?'open':'' ?>><summary><?=h(format_datetime($ts))?> (<?=count($items)?> files)</summary><table><thead><tr><th>File</th><th>Status</th><th>User Remarks</th><th>Admin Remarks</th><th>Date</th><th>Actions</th></tr></thead><tbody><?php foreach($items as $it): ?><tr><td><?=h($it['file_name'])?></td><td><?=badge($it['document_status'])?></td><td><?=h($it['user_remarks']?:'-')?></td><td><?=h($it['admin_remarks']?:'-')?></td><td><?=h(format_datetime($it['uploaded_at']))?></td><td class="actions compact-inline"><a class="btn btn-view" href="uploads/<?=h($it['file_path'])?>" download="<?=h($it['file_name'])?>">Download</a><?php if(!$isViewer): ?><button type="button" class="btn btn-edit open-doc-update" data-doc-id="<?= (int)$it['id'] ?>" data-submission-id="<?= (int)$sid ?>" data-file="<?=h($it['file_name'])?>" data-status="<?=h($it['document_status'])?>" data-remarks="<?=h($it['admin_remarks']??'')?>">Update</button><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></details></section><?php $i++; endforeach; ?>
    <?php if(!$isViewer): ?><dialog id="doc-update-modal" class="agency-modal"><div class="row"><h3>Update Document Status</h3><button type="button" class="btn btn-edit" id="close-doc-modal">Close</button></div><p class="muted" id="doc-file"></p><form method="post" action="index.php?page=admin_update_document" class="form-grid"><input type="hidden" name="document_id" id="doc-id"><input type="hidden" name="submission_id" id="doc-submission-id"><label>Status<select name="document_status" id="doc-status"><option value="for_review">For Review</option><option value="for_compliance">For Compliance</option><option value="approved">Approved</option></select></label><label>Remarks<textarea name="admin_remarks" id="doc-remarks" rows="6"></textarea></label><button>Save Update</button></form></dialog><?php endif; ?>
    <?php render_footer(); exit;
  }

  if($page==='statistics'){
    $provinces = provinces();

    // Base totals per submission+province (all assigned agencies).
    $totalsSql = 'SELECT s.id, s.name, s.hierarchy_path, s.created_at, u.province, COUNT(a.id) total_assigned '
      . 'FROM submissions s '
      . 'LEFT JOIN agency_submissions a ON a.submission_id=s.id '
      . 'LEFT JOIN users u ON u.id=a.agency_id '
      . 'GROUP BY s.id, s.name, s.hierarchy_path, s.created_at, u.province '
      . 'ORDER BY s.created_at DESC, s.id DESC';
    $rows = $pdo->query($totalsSql)->fetchAll();

    // Compliance counts: only agencies whose LATEST uploaded batch has ALL docs approved.
    $approvedSql = 'SELECT s.id submission_id, u.province, COUNT(*) approved_count '
      . 'FROM agency_submissions a '
      . 'JOIN submissions s ON s.id=a.submission_id '
      . 'JOIN users u ON u.id=a.agency_id '
      . 'JOIN ( '
      . '  SELECT d.agency_submission_id, MAX(d.uploaded_at) max_uploaded_at '
      . '  FROM uploaded_documents d '
      . '  GROUP BY d.agency_submission_id '
      . ') latest_time ON latest_time.agency_submission_id=a.id '
      . 'JOIN uploaded_documents latest_doc ON latest_doc.agency_submission_id=a.id '
      . '  AND latest_doc.uploaded_at=latest_time.max_uploaded_at '
      . 'GROUP BY s.id, u.province, a.id '
      . 'HAVING SUM(CASE WHEN latest_doc.document_status <> "approved" THEN 1 ELSE 0 END)=0';
    $approvedRows = $pdo->query($approvedSql)->fetchAll();

    $approvedMap = [];
    foreach($approvedRows as $ar){
      $sid=(int)$ar['submission_id'];
      $prov=(string)$ar['province'];
      $approvedMap[$sid][$prov] = ($approvedMap[$sid][$prov] ?? 0) + 1;
    }

    $group=[];
    foreach($rows as $r){
      if(!isset($group[$r['id']])){
        $group[$r['id']] = ['name'=>submission_full_name(['name'=>$r['name'],'hierarchy_path'=>$r['hierarchy_path']??'']),'created_at'=>$r['created_at'],'data'=>[],'total_submitted'=>0,'total_assigned'=>0];
      }
      if($r['province']){
        $tot=(int)$r['total_assigned'];
        $subm=(int)($approvedMap[(int)$r['id']][(string)$r['province']] ?? 0);
        $group[$r['id']]['data'][$r['province']] = ['submitted'=>$subm,'total'=>$tot,'pct'=>$tot?round(($subm/$tot)*100,2):0];
        $group[$r['id']]['total_submitted'] += $subm;
        $group[$r['id']]['total_assigned'] += $tot;
      }
    }

    render_header('Statistics'); ?>
    <section class="card"><h2>Statistics by Submission</h2><?php if(!$group): ?><p class="muted">no submissions yet</p><?php else: ?>
      <?php foreach($group as $g): $overall=$g['total_assigned']?round(($g['total_submitted']/$g['total_assigned'])*100,2):0; ?>
        <div class="stats-board">
          <div class="stats-title"><?= h($g['name']) ?><br><small>As of <?= h(date('F d, Y', strtotime($g['created_at'] ?: 'now'))) ?></small></div>
          <table class="stats-grid"><thead><tr><?php foreach($provinces as $p): ?><th><?= h($p==='Negros Occidental'?'Neg. Occ.':$p) ?></th><?php endforeach; ?></tr></thead>
            <tbody><tr><?php foreach($provinces as $p): $d=$g['data'][$p] ?? ['submitted'=>0,'total'=>0,'pct'=>0]; ?><td><div class="big"><?= $d['submitted'] ?>/<?= $d['total'] ?></div><div class="small"><?= $d['pct'] ?>%</div></td><?php endforeach; ?></tr></tbody>
          </table>
          <div class="stats-footer">Total Percentage of Compliance: <strong><?= $overall ?>%</strong></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?></section>
    <?php render_footer(); exit;
  }

  $s=$pdo->query('SELECT * FROM submissions ORDER BY deadline ASC'); $subs=$s->fetchAll();
  render_header($isViewer?'Viewer Dashboard':'Admin Dashboard'); ?>
  <section class="card"><div class="row"><h2>Submissions</h2><input data-search-target="#sub-table" placeholder="Search submissions..."><div class="row-wrap"><a class="btn btn-edit" href="index.php?page=statistics">Statistics</a><?php if(!$isViewer): ?><a class="btn btn-view" href="index.php?page=admin_submission_form">Add Submission</a><?php endif; ?></div></div><table id="sub-table"><thead><tr><th>Name</th><th>Deadline</th><th>Actions</th></tr></thead><tbody><?php foreach($subs as $sub): ?><tr><td><?=h(submission_full_name($sub))?></td><td><?=h(date('M d, Y',strtotime($sub['deadline'])))?></td><td class="actions compact-inline"><a class="btn btn-view" href="index.php?page=submission_dashboard&id=<?= (int)$sub['id'] ?>"><?= $isViewer ? 'View' : 'View' ?></a><?php if(!$isViewer): ?><a class="btn btn-edit" href="index.php?page=admin_submission_form&id=<?= (int)$sub['id'] ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section>

  <?php render_footer(); exit;
}
