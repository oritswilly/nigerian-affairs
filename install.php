<?php
require __DIR__.'/config.php';
try{
 db()->exec(file_get_contents(__DIR__.'/schema.sql'));
 $email='oritswill@gmail.com';
 $q=db()->prepare('SELECT id FROM users WHERE email=?');$q->execute([$email]);
 if($q->fetch()){echo '<h2>Nigerian Affairs is already installed.</h2>';exit;}
 $password=bin2hex(random_bytes(8));
 $s=db()->prepare('INSERT INTO users(email,username,password_hash,given_name,family_name,roles,verified) VALUES(?,?,?,?,?,?,1)');
 $s->execute([$email,'oritswill',password_hash($password,PASSWORD_DEFAULT),'Wilfred','Olley','Site Administrator,Journal Manager,Editor,Author,Reader']);
 echo '<h2>Nigerian Affairs installed</h2><p>Administrator: '.e($email).'</p><p>Temporary password: <strong>'.e($password).'</strong></p><p>Sign in and change this password immediately.</p>';
}catch(Throwable $ex){http_response_code(500);echo '<h2>Installation failed</h2><pre>'.e($ex->getMessage()).'</pre>';}