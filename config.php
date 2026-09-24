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
 return $pdo;
}
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
