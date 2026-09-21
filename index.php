<?php
require __DIR__.'/config.php';
$page=$_GET['page']??'home';
$msg='';
if($page==='logout'){session_destroy();header('Location: ./');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 $action=$_POST['action']??'';
 try{
  if($action==='register'){
   $email=strtolower(trim($_POST['email']??''));$username=trim($_POST['username']??'');$pw=$_POST['password']??'';
   if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($username)<3||strlen($pw)<8)throw new RuntimeException('Enter a valid email, username and password of at least 8 characters.');
   $s=db()->prepare('INSERT INTO users(email,username,password_hash,given_name,family_name,affiliation,country,orcid,reviewing_interests,roles,verified) VALUES(?,?,?,?,?,?,?,?,?,?,1)');
   $s->execute([$email,$username,password_hash($pw,PASSWORD_DEFAULT),trim($_POST['given_name']??''),trim($_POST['family_name']??''),trim($_POST['affiliation']??''),trim($_POST['country']??''),trim($_POST['orcid']??''),trim($_POST['reviewing_interests']??''),'Author,Reader']);
   $uid=(int)db()->lastInsertId();db()->prepare('UPDATE users SET verified=0 WHERE id=?')->execute([$uid]);$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);db()->prepare('INSERT INTO email_verifications(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))')->execute([$uid,$hash]);$url=rtrim(getenv('APP_URL')?:'', '/').'/?page=verify-email&token='.urlencode($token);send_mail($email,'Verify your Nigerian Affairs account','<p>Welcome to Nigerian Affairs.</p><p><a href="'.e($url).'">Verify your email address</a></p><p>This link expires in 24 hours.</p>');
   $msg='Registration successful. Check your email to verify your account before signing in.';$page='login';
  }elseif($action==='login'){
   $id=trim($_POST['identity']??'');$s=db()->prepare('SELECT * FROM users WHERE email=? OR username=? LIMIT 1');$s->execute([strtolower($id),$id]);$x=$s->fetch();
   if(!$x||!password_verify($_POST['password']??'',$x['password_hash']))throw new RuntimeException('Invalid username/email or password.');if(!(int)$x['verified'])throw new RuntimeException('Please verify your email address before signing in.');
   session_regenerate_id(true);$_SESSION['uid']=$x['id'];header('Location: ?page=dashboard');exit;
  }elseif($action==='request-reset'){
   $email=strtolower(trim($_POST['email']??''));$q=db()->prepare('SELECT id,email,given_name FROM users WHERE email=? LIMIT 1');$q->execute([$email]);$account=$q->fetch();
   if($account){$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$account['id']]);db()->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))')->execute([$account['id'],$hash]);$url=rtrim(getenv('APP_URL')?:'', '/').'/?page=reset-password&token='.urlencode($token);send_mail($account['email'],'Nigerian Affairs password reset','<p>Hello '.e($account['given_name']).',</p><p>Use the link below to set a new Nigerian Affairs password. The link expires in 15 minutes.</p><p><a href="'.e($url).'">Reset password</a></p><p>If you did not request this, ignore this email.</p>');}
   $msg='If that email address is registered, a password reset link has been sent.';$page='forgot-password';
  }elseif($action==='confirm-reset'){
   $token=$_POST['token']??'';$pw=$_POST['password']??'';$confirm=$_POST['password_confirm']??'';if(strlen($pw)<8||$pw!==$confirm)throw new RuntimeException('Passwords must match and contain at least 8 characters.');$hash=hash('sha256',$token);$q=db()->prepare('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([$hash]);$reset=$q->fetch();if(!$reset)throw new RuntimeException('This reset link is invalid or has expired.');db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw,PASSWORD_DEFAULT),$reset['user_id']]);db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$reset['id']]);$msg='Password reset successful. You can now log in.';$page='login';
  }elseif($action==='editor-decision'){
   $editor=require_login();if(!(has_role($editor,'Editor')||has_role($editor,'Journal Manager')||has_role($editor,'Site Administrator')))throw new RuntimeException('Editorial access required.');
   $sid=(int)($_POST['submission_id']??0);$decision=trim($_POST['decision']??'');$note=trim($_POST['note']??'');$allowed=['Revisions Required','Accept Submission','Decline Submission'];if(!$sid||!in_array($decision,$allowed,true))throw new RuntimeException('Invalid editorial decision.');
   $q=db()->prepare('SELECT s.title,u.email,u.given_name FROM submissions s JOIN users u ON u.id=s.author_id WHERE s.id=?');$q->execute([$sid]);$paper=$q->fetch();if(!$paper)throw new RuntimeException('Submission not found.');
   db()->prepare('INSERT INTO decisions(submission_id,editor_id,decision,note) VALUES(?,?,?,?)')->execute([$sid,$editor['id'],$decision,$note]);$status=$decision==='Accept Submission'?'Accepted':($decision==='Decline Submission'?'Declined':'Revision requested');db()->prepare('UPDATE submissions SET status=? WHERE id=?')->execute([$status,$sid]);
   send_mail($paper['email'],'Nigerian Affairs editorial decision: '.$paper['title'],'<p>Dear '.e($paper['given_name']).',</p><p>An editorial decision has been recorded for <strong>'.e($paper['title']).'</strong>.</p><p><strong>'.e($decision).'</strong></p><p>'.nl2br(e($note)).'</p><p>Please sign in to your journal dashboard for the manuscript record.</p>');
   header('Location: ?page=editor');exit;
  }elseif($action==='submit'){
   $me=require_login();$s=db()->prepare('INSERT INTO submissions(author_id,title,authors,abstract,keywords,section,language,references_text) VALUES(?,?,?,?,?,?,?,?)');
   $title=trim($_POST['title']??'');$s->execute([$me['id'],$title,trim($_POST['authors']??''),trim($_POST['abstract']??''),trim($_POST['keywords']??''),$_POST['section']??'Research Article',$_POST['language']??'English',trim($_POST['references']??'')]);
   $sid=(int)db()->lastInsertId();db()->prepare('INSERT INTO audit_log(user_id,submission_id,action) VALUES(?,?,?)')->execute([$me['id'],$sid,'Submission received']);
   send_mail($me['email'],'Nigerian Affairs submission received','<p>Dear '.e($me['given_name']).',</p><p>Your manuscript <strong>'.e($title).'</strong> has been received by Nigerian Affairs.</p><p>Submission ID: NA-'.str_pad((string)$sid,6,'0',STR_PAD_LEFT).'</p><p>You can monitor its status from your journal dashboard.</p>');
   $editor=getenv('EDITOR_EMAIL')?:'';if($editor!=='')send_mail($editor,'New Nigerian Affairs submission: '.$title,'<p>A new manuscript has been submitted to Nigerian Affairs.</p><p><strong>'.e($title).'</strong></p><p>Submission ID: NA-'.str_pad((string)$sid,6,'0',STR_PAD_LEFT).'</p><p>Please sign in to the editorial dashboard for screening.</p>');
   header('Location: ?page=dashboard');exit;
  }
 }catch(Throwable $ex){$msg=$ex->getMessage();}
}
if($page==='verify-email'&&!empty($_GET['token'])){$hash=hash('sha256',$_GET['token']);$q=db()->prepare('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');$q->execute([$hash]);$v=$q->fetch();if($v){db()->prepare('UPDATE users SET verified=1 WHERE id=?')->execute([$v['user_id']]);db()->prepare('UPDATE email_verifications SET used_at=NOW() WHERE id=?')->execute([$v['id']]);$msg='Email verified successfully. You can now log in.';$page='login';}else{$msg='This verification link is invalid or has expired.';}}
$u=current_user();
?><!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(ucfirst($page))?> | Nigerian Affairs</title><link rel="stylesheet" href="assets/style.css"></head><body>
<div class="utility"><div class="wide"><span>English</span><span><?php if($u): ?><a href="?page=dashboard">Dashboard</a> · <a href="?page=logout">Logout</a><?php else: ?><a href="?page=register">Register</a> · <a href="?page=login">Login</a><?php endif; ?></span></div></div>
<header><div class="wide brand"><div class="logo">NA</div><div><h1>Nigerian Affairs</h1><small>ARTS · HUMANITIES · SOCIAL SCIENCES</small></div></div><nav class="wide"><a href="./">Home</a><a href="?page=about">About the journal</a><a href="?page=current">Current</a><a href="?page=policies">Journal Policies</a><a href="?page=editorial">Editorial Team</a><a href="?page=submissions">Instructions to Authors</a><a href="?page=archives">Archives</a><a href="?page=contact">Contact</a></nav></header>
<?php if($msg): ?><div class="wide alert"><?=e($msg)?></div><?php endif; ?>
<?php if($page==='login'): ?>
<main class="wide content narrow"><h2>Login</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="login"><label>Username or Email</label><input name="identity" required autofocus><label>Password</label><input type="password" name="password" required><button>Login</button></form><p><a href="?page=register">Not a user? Register with this site</a></p><p><a href="?page=forgot-password">Forgot your password?</a></p></main>
<?php elseif($page==='forgot-password'): ?>
<main class="wide content narrow"><h2>Reset Password</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="request-reset"><label>Email Address</label><input type="email" name="email" required autofocus><button>Send Reset Link</button></form><p><a href="?page=login">Back to login</a></p></main>
<?php elseif($page==='reset-password'): ?>
<main class="wide content narrow"><h2>Set New Password</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="confirm-reset"><input type="hidden" name="token" value="<?=e($_GET['token']??'')?>"><label>New Password</label><input type="password" name="password" minlength="8" required><label>Confirm New Password</label><input type="password" name="password_confirm" minlength="8" required><button>Set New Password</button></form></main>
<?php elseif($page==='register'): ?>
<main class="wide content narrow"><h2>Register</h2><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="register"><label>Given Name</label><input name="given_name" required><label>Family Name</label><input name="family_name" required><label>Affiliation</label><input name="affiliation"><label>Country</label><input name="country"><label>Email</label><input type="email" name="email" required><label>Username</label><input name="username" required><label>Password</label><input type="password" name="password" minlength="8" required><label>ORCID iD</label><input name="orcid"><label>Reviewing interests</label><textarea name="reviewing_interests"></textarea><button>Register</button></form></main>
<?php elseif($page==='dashboard'): $me=require_login();$q=db()->prepare('SELECT * FROM submissions WHERE author_id=? ORDER BY created_at DESC');$q->execute([$me['id']]);$subs=$q->fetchAll(); ?>
<main class="wide content"><h2>Dashboard</h2><p><?=e($me['given_name'].' '.$me['family_name'])?> · <?=e($me['roles'])?></p><a class="button" href="?page=submissions">New Submission</a><?php if(has_role($me,'Editor')||has_role($me,'Journal Manager')||has_role($me,'Site Administrator')): ?><a class="button" href="?page=editor">Editorial Workflow</a><?php endif; ?><h3>My Submissions</h3><?php if(!$subs): ?><p>No submissions.</p><?php endif; ?><?php foreach($subs as $s): ?><div class="paper"><b><?=e($s['title'])?></b><br><?=e($s['stage'])?> · <?=e($s['status'])?></div><?php endforeach; ?></main>
<?php elseif($page==='editor'): $editor=require_login(); if(!(has_role($editor,'Editor')||has_role($editor,'Journal Manager')||has_role($editor,'Site Administrator'))){http_response_code(403); ?>
<main class="wide content"><h2>Access denied</h2></main>
<?php }else{$items=db()->query('SELECT s.*,u.given_name,u.family_name FROM submissions s JOIN users u ON u.id=s.author_id ORDER BY s.created_at DESC')->fetchAll(); ?>
<main class="wide content"><h2>Editorial Workflow</h2><p>Submission · Review · Copyediting · Production</p><?php if(!$items): ?><p>No submissions awaiting editorial work.</p><?php endif; ?><?php foreach($items as $paper): ?><div class="paper"><h3><?=e($paper['title'])?></h3><p>Author: <?=e($paper['given_name'].' '.$paper['family_name'])?><br>Status: <?=e($paper['status'])?></p><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="editor-decision"><input type="hidden" name="submission_id" value="<?=(int)$paper['id']?>"><label>Editorial Decision</label><select name="decision" required><option value="">Select decision</option><option>Revisions Required</option><option>Accept Submission</option><option>Decline Submission</option></select><label>Decision Note</label><textarea name="note"></textarea><button>Record Decision & Notify Author</button></form></div><?php endforeach; ?></main>
<?php } ?>
<?php elseif($page==='submissions'): require_login(); ?>
<main class="wide content"><h2>New Submission</h2><div class="steps">1. Start　2. Upload Submission　3. Enter Metadata　4. Confirmation　5. Next Steps</div><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="submit"><label>Manuscript title</label><input name="title" required><label>Authors</label><input name="authors" required><label>Abstract</label><textarea name="abstract" required></textarea><label>Keywords</label><input name="keywords" required><label>Section</label><select name="section"><option>Research Article</option><option>Review Article</option><option>Commentary</option></select><label>Language</label><select name="language"><option>English</option></select><label>References</label><textarea name="references"></textarea><button>Save and Continue</button></form></main>
<?php elseif($page==='editorial'): ?>
<main class="wide content"><h2>Editorial Team</h2>
<h3>EDITOR-IN-CHIEF</h3><p><strong>Dr. Olukayode Faleye</strong><br>Associate Professor, Edo State University, Iyamho</p>
<h3>CONSULTING EDITOR</h3><p><strong>Professor G. A. Vaaseh</strong></p>
<h3>EDITOR</h3><p><strong>Prof. Solomon Awuzie</strong><br>Edo State University, Iyamho</p>
<h3>MANAGING EDITOR</h3><p><strong>Matthew Alugbin, PhD</strong></p>
<h3>EDITORIAL ADVISORY BOARD</h3>
<p>Professor A. M. Okhakhu <span>(University of Benin)</span></p>
<p>Professor Andrew A. Ate <span>(Edo State University Iyamho)</span></p>
<p>Professor Afutendem Lucas Nkwetta <span>(University of Dschang, Cameroon)</span></p>
<p>Professor Andrew Ogah Ijwo <span>(Benue State University, Makurdi)</span></p>
<p>Professor Ayo Osisanwo <span>(University of Ibadan)</span></p>
<p>Professor Christoph Schmidt <span>(University of Applied Sciences, Bonn, Germany)</span></p>
<p>Professor Isidore Diala <span>(Imo State University)</span></p>
<p>Professor J. A. Sambe <span>(University of Veritas)</span></p>
<p>Professor P. F. Adebayo <span>(University of Ilorin)</span></p>
<p>Professor P. O. Alokan <span>(Joseph Ayo Babalola University)</span></p>
<p>Prof. Blessed Frederick Ngonso <span>(Edo State University, Iyamho)</span></p>
<p>Dr. Wilfred O. Olley <span>(Associate Professor, Edo State University, Iyamho)</span></p>
<p>Rev. Fr. Dr. Peter E. Egielewa <span>(Associate Professor, Edo State University, Iyamho)</span></p>
</main>
<?php elseif($page==='home'): ?>
<div class="hero wide"></div><main class="wide layout"><section><h2>Announcements</h2><div class="announce"><article><a>Call for papers: Nigerian Affairs</a><small>2026-09-18</small><p>Nigerian Affairs welcomes original scholarship across the Arts, Humanities and Social Sciences.</p></article><article><a>Volume 2, Number 1 (2026) published online</a><small>2026-09-18</small><p>The current issue of Nigerian Affairs is now available.</p></article></div><hr><h2>Current issue</h2><p><b>Vol. 2 No. 1 (2026): Nigerian Affairs</b></p><p>Published: 2026-09-18</p></section><aside><h3>Information</h3><a>For Readers</a><a>For Authors</a><a>For Librarians</a><hr><a class="button" href="?page=submissions">Make a Submission</a><h3>Journal Details</h3><p>Frequency<br><b>Biannual</b></p><p>Review<br><b>Double-blind</b></p></aside></main>
<?php else: ?><main class="wide content"><h2><?=e(ucwords(str_replace('-',' ',$page)))?></h2><p>Nigerian Affairs is an interdisciplinary academic journal published by the Faculty of Arts and Communication, Edo State University Iyamho, Nigeria.</p></main><?php endif; ?>
<footer><div class="wide cols"><div><h3>Nigerian Affairs</h3><p>Faculty of Arts and Communication, Edo State University Iyamho, Nigeria.</p></div><div><h3>Journal Details</h3><p>Biannual · May and November<br>Double-blind peer review</p></div><div><h3>Information</h3><p>For Readers<br>For Authors<br>For Librarians</p></div><div><h3>Contact</h3><p>Edo State University Iyamho<br>Edo State, Nigeria</p></div></div></footer></body></html>