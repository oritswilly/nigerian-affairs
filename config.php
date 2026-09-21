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
 if($key==='')return false;
 $payload=json_encode(['from'=>getenv('MAIL_FROM') ?: 'Nigerian Affairs <no-reply@nigeriaaffairs.com>','to'=>[$to],'subject'=>$subject,'html'=>$html]);
 $ch=curl_init('https://api.resend.com/emails');
 curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_HTTPHEADER,['Authorization: Bearer '.$key,'Content-Type: application/json']);curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);curl_setopt($ch,CURLOPT_TIMEOUT,15);
 curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 return $code>=200&&$code<300;
}
