<?php
declare(strict_types=1);
session_start();
function db(): PDO {
 static $pdo=null;
 if($pdo instanceof PDO)return $pdo;
 $host=getenv('DB_HOST') ?: 'localhost';
 $port=getenv('DB_PORT') ?: '3306';
 $name=getenv('DB_NAME') ?: '';
 $user=getenv('DB_USER') ?: '';
 $pass=getenv('DB_PASSWORD') ?: '';
 if(!$name||!$user)throw new RuntimeException('Database connection is not configured.');
 $dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
 $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipient VARCHAR(190) NOT NULL, subject VARCHAR(255) NOT NULL, status VARCHAR(30) NOT NULL, detail VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
 $pdo->exec("CREATE TABLE IF NOT EXISTS apc_payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, payment_type VARCHAR(50) NOT NULL, amount DECIMAL(12,2) NULL, reference_no VARCHAR(190) NULL, status VARCHAR(40) NOT NULL DEFAULT 'Pending verification', evidence_note TEXT NULL, verified_by BIGINT UNSIGNED NULL, verified_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(submission_id))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS payment_evidence (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, payment_id BIGINT UNSIGNED NOT NULL UNIQUE, submitted_by BIGINT UNSIGNED NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size BIGINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(submitted_by))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS submission_editors (submission_id BIGINT UNSIGNED PRIMARY KEY, editor_id BIGINT UNSIGNED NOT NULL, assigned_by BIGINT UNSIGNED NULL, assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(editor_id))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS submission_authors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, sort_order INT NOT NULL DEFAULT 1, name VARCHAR(190) NOT NULL, affiliation VARCHAR(255) NULL, orcid VARCHAR(40) NULL, scopus_id VARCHAR(40) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(submission_id), INDEX(submission_id,sort_order))");
 seed_inaugural_article_authors($pdo);
 return $pdo;
}
function seed_inaugural_article_authors(PDO $pdo): void {
 $sets=[
  ['%Teachers as Communicators of Ethical Values in Higher Education%',[['Peter Eshioke Egielewa','Department of Mass Communication, Edo State University, Iyamho, Edo State, Nigeria']]],
  ['%Christian Persecution in Nigeria%',[['Samuel Sunday Alamu','Department of Religious Studies, University of Lagos'],['Victor Adetona','Department of Theology, Wesley University, Ondo']]],
  ['%Media Campaigns as Determinants of Market Women%',[['Lukman Adegboyega Abioye','Department of Communication and Media Technology, Lead City University, Ibadan, Oyo State'],['Adebisi Kazeem Aro','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State'],['Olusegun Abimbola Odunlami','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State'],['Oluwatosin Samuel Adesola','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State']]],
  ['%Preservation and Revitalisation of Nigerian Indigenous Languages%',[['Abidemi Opeyemi Omotayo','Department of English, Sikiru Adetona College of Education, Science and Technology, Omu-Ajose, Ogun State'],['Nureni Abolanle Dairo','Department of English, Sikiru Adetona College of Education, Science and Technology, Omu-Ajose, Ogun State']]],
  ['%Igbo-Language Radio Programmes as Tools for Indigenous Language Preservation%',[['Chukwuebuka Sebastine Okafor','Enugu State University of Science and Technology'],['Joel Asogwa','Department of Mass Communication, Enugu State University of Science and Technology'],['Samuel Elom Chukwudi',null],['Emmanuel Kenechukwu Agbo','Enugu State University of Science and Technology']]],
  ['%Deciphering the Cosmopolitan Characters of Some Selected Ilorin%',[['Omotosho Ishola','Department of History and Diplomatic Studies, Kwara State University, Malete'],['Wasiu Olayimika Kewulere','Department of History and Diplomatic Studies, Kwara State University, Malete']]],
  ['%Politics and Evolution of the Nigeria Governors%',[['Mojeed Oyetunji Oyedokun','Department of History and International Studies, Edo State University, Iyamho, Edo State'],['Shola Ahmed Akanbi','Department of History and International Relations, Muhammad Kamalud-deen University, Ilorin']]]
 ];
 foreach($sets as [$pattern,$authors]){
  $q=$pdo->prepare("SELECT id FROM submissions WHERE status='Published' AND title LIKE ? ORDER BY id LIMIT 1");$q->execute([$pattern]);$sid=(int)($q->fetchColumn()?:0);if(!$sid)continue;
  $check=$pdo->prepare('SELECT COUNT(*) FROM submission_authors WHERE submission_id=?');$check->execute([$sid]);if((int)$check->fetchColumn()>0)continue;
  $ins=$pdo->prepare('INSERT INTO submission_authors(submission_id,sort_order,name,affiliation,orcid,scopus_id) VALUES(?,?,?,?,NULL,NULL)');
  foreach($authors as $i=>$a)$ins->execute([$sid,$i+1,$a[0],$a[1]]);
 }
}
function setting(string $key,string $default=''): string {static $cache=[];if(array_key_exists($key,$cache))return $cache[$key];try{$q=db()->prepare('SELECT setting_value FROM journal_settings WHERE setting_key=? LIMIT 1');$q->execute([$key]);$v=$q->fetchColumn();return $cache[$key]=$v===false?$default:(string)$v;}catch(Throwable $e){return $cache[$key]=$default;}}
function setting_bool(string $key,bool $default=true): bool {return in_array(strtolower(setting($key,$default?'1':'0')),['1','true','yes','on'],true);}
function e(string $v): string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function current_user(): ?array {
 if(empty($_SESSION['uid']))return null;
 $q=db()->prepare('SELECT id,email,username,given_name,family_name,affiliation,country,orcid,roles,verified FROM users WHERE id=?');
 $q->execute([$_SESSION['uid']]);
 return $q->fetch() ?: null;
}
function require_login(): array {$u=current_user();if(!$u){header('Location: ?page=login');exit;}return $u;}
function has_role(array $u,string $role): bool{return in_array($role,array_filter(explode(',',$u['roles']??'')),true);}
function csrf(): string {if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function check_csrf(): void {if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Invalid request token.');}}

function send_mail(string $to,string $subject,string $html): bool {
 $key=getenv('RESEND_API_KEY') ?: '';
 if($key===''){db()->prepare('INSERT INTO email_log(recipient,subject,status,detail) VALUES(?,?,?,?)')->execute([$to,$subject,'Not configured','RESEND_API_KEY is not configured']);return false;}
 $payload=json_encode(['from'=>getenv('MAIL_FROM') ?: 'Nigerian Affairs <no-reply@nigeriaaffairs.com>','to'=>[$to],'subject'=>$subject,'html'=>$html]);
 $ch=curl_init('https://api.resend.com/emails');
 curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_HTTPHEADER,['Authorization: Bearer '.$key,'Content-Type: application/json']);curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);curl_setopt($ch,CURLOPT_TIMEOUT,15);
 $result=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$ok=$code>=200&&$code<300;
 db()->prepare('INSERT INTO email_log(recipient,subject,status,detail) VALUES(?,?,?,?)')->execute([$to,$subject,$ok?'Sent':'Failed',substr((string)$result,0,250)]);
 return $ok;
}
