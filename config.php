<?php
declare(strict_types=1);
session_start();
function db(): PDO {
 static $pdo=null;
 if($pdo instanceof PDO)return $pdo;
 $url=parse_url(getenv('DATABASE_URL') ?: '');
 if(!$url)throw new RuntimeException('DATABASE_URL is not configured.');
 $dsn='mysql:host='.($url['host']??'localhost').';port='.($url['port']??3306).';dbname='.ltrim($url['path']??'','/').';charset=utf8mb4';
 $pdo=new PDO($dsn,urldecode($url['user']??''),urldecode($url['pass']??''),[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
 ]);
 return $pdo;
}
function e(string $v): string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function current_user(): ?array {
 if(empty($_SESSION['uid']))return null;
 $q=db()->prepare('SELECT id,email,username,given_name,family_name,affiliation,country,orcid,roles,verified FROM users WHERE id=?');
 $q->execute([$_SESSION['uid']]);
 return $q->fetch() ?: null;
}
function require_login(): array {
 $u=current_user();
 if(!$u){header('Location: ?page=login');exit;}
 return $u;
}
function has_role(array $u,string $role): bool{return in_array($role,array_filter(explode(',',$u['roles']??'')),true);}
function csrf(): string {
 if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
 return $_SESSION['csrf'];
}
function check_csrf(): void {
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Invalid request token.');}
}