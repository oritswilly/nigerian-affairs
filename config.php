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
 seed_home_announcements($pdo);
 seed_inaugural_issue($pdo);
 seed_inaugural_article_authors($pdo);
 seed_june_2026_issue($pdo);
 return $pdo;
}
function seed_home_announcements(PDO $pdo): void {
 $pdo->exec("DELETE FROM announcements WHERE title IN ('Volume 1, Number 1 (2025) published online','Volume 1, Number 2 (2026) published online')");
 $items=[
  ['Complete Nigerian Affairs digital archive is now available','Volume 1, Number 1 (2025) has moved to the archives and remains available for browsing by issue, article and keyword.','2026-09-24 21:58:02'],
  ['Volume 1, Number 2 (2026) published online','The June 2026 current issue contains 5 peer-reviewed articles in communication, journalism, music education and Nigerian history.','2026-09-24 21:58:01']
 ];
 foreach($items as [$title,$body,$date]){
  $q=$pdo->prepare('SELECT id FROM announcements WHERE title=? ORDER BY id LIMIT 1');$q->execute([$title]);$id=(int)($q->fetchColumn()?:0);
  if($id)$pdo->prepare('UPDATE announcements SET body=?,published_at=? WHERE id=?')->execute([$body,$date,$id]);
  else $pdo->prepare('INSERT INTO announcements(title,body,published_at) VALUES(?,?,?)')->execute([$title,$body,$date]);
 }
}
function seed_inaugural_issue(PDO $pdo): void {
 $email='publication-import@nigeriaaffairs.com';$username='publication_import';
 $q=$pdo->prepare('SELECT id FROM users WHERE email=? OR username=? LIMIT 1');$q->execute([$email,$username]);$importer=(int)($q->fetchColumn()?:0);
 if(!$importer){$pdo->prepare('INSERT INTO users(email,username,password_hash,given_name,family_name,affiliation,country,orcid,reviewing_interests,roles,verified) VALUES(?,?,?,?,?,?,?,?,?,?,1)')->execute([$email,$username,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'Publication','Import','Nigerian Affairs Editorial Office','Nigeria','','','Author,Reader']);$importer=(int)$pdo->lastInsertId();}
 $pdo->prepare("INSERT IGNORE INTO issues(volume,number,year,title,description,status,published_at,created_at) VALUES(1,1,2025,?,?, 'Published','2025-12-01 00:00:00','2025-12-01 00:00:00')")
     ->execute(['Nigerian Affairs','Volume 1, Issue 1, December 2025']);
 $issueLabel='Vol. 1 No. 1 (2025)';
 $articles=[
  [
   'Teachers as Communicators of Ethical Values in Higher Education: An Evaluation of Lecturers’ Perspectives in Nigeria',
   'Peter Eshioke Egielewa',
   'This study examined lecturers’ perspectives on the communication of ethical values in higher education institutions in Nigeria, focusing on Edo State University, Iyamho and the Federal Polytechnic Auchi. Using a mixed-methods approach that combined quantitative and qualitative data, the study explored the extent, types, and challenges of communicating ethical values in classroom settings. Data were collected from 100 lecturers using questionnaires containing both open and closed-ended questions. Findings underscore the importance of educators as moral agents and role models in shaping students’ ethical behaviour and character and recommend structured inclusion of ethical education in higher education curricula.',
   'Ethical values, higher education, lecturers, Nigeria, moral education, communication, Transposition Theory',
   '1-12'
  ],
  [
   'Christian Persecution in Nigeria: An Exegetical and Contextual Analysis of John 16:33',
   'Samuel Sunday Alamu; Victor Adetona',
   'This study addresses the critical reality of Christian persecution in Nigeria through an exegetical and contextual analysis of John 16:33. It applies exegetical insights from John 16:33 to the Nigerian Church’s environment of hostility and violence, integrating biblical education with perspectives from liberation theology, human rights discourse and peace-building studies. The study argues that Jesus’ assurance to take courage functions not simply as comfort but as a directive grounded in his definitive triumph over hostile powers and provides theological resources for resilience, pastoral care, advocacy and interfaith engagement.',
   'Nigerian Christianity, John 16:33, Johannine theology, Persecution, Religion',
   '13-28'
  ],
  [
   'Media Campaigns as Determinants of Market Women’s Awareness and Compliance Towards COVID-19 Vaccination in Selected States in Southwest Nigeria',
   'Lukman Adegboyega Abioye; Adebisi Kazeem Aro; Olusegun Abimbola Odunlami; Oluwatosin Samuel Adesola',
   'The COVID-19 pandemic highlighted the critical role of media campaigns in curbing the spread of the virus, particularly through vaccination campaigns. This study examined the influence of media campaigns on market women’s awareness and compliance with COVID-19 vaccination regulations in selected states in Southwest Nigeria. Employing a correlational research design, data were collected from 200 market women and two health officials using questionnaires and interviews. Findings revealed low exposure to media campaigns, limited awareness of vaccination regulations and low compliance, with misinformation, cultural mistrust and fear of vaccine side effects constraining behaviour change.',
   'Awareness, Compliance, Media campaign, Vaccination regulations, COVID-19',
   '29-43'
  ],
  [
   'Preservation and Revitalisation of Nigerian Indigenous Languages Through Digital Media in South-West Nigeria',
   'Abidemi Opeyemi Omotayo; Nureni Abolanle Dairo',
   'This study explores how digital media contributes to the preservation and revitalisation of indigenous languages in South-West Nigeria. A descriptive survey combined quantitative and qualitative data to examine attitudes and practices related to digital media and language use among purposively sampled youths and content creators using platforms such as YouTube, TikTok and WhatsApp. Results show that digital media has significantly enhanced language visibility, intergenerational communication and creative usage, although challenges such as digital literacy and platform bias remain.',
   'Indigenous languages, digital media, language preservation, South-West Nigeria, revitalisation',
   '44-54'
  ],
  [
   'Igbo-Language Radio Programmes as Tools for Indigenous Language Preservation in Enugu State',
   'Chukwuebuka Sebastine Okafor; Joel Asogwa; Samuel Elom Chukwudi; Emmanuel Kenechukwu Agbo',
   'The accelerating decline of indigenous languages poses a critical threat to cultural heritage and linguistic diversity in Nigeria. This study examines the role of Igbo-language radio broadcasting in preserving and revitalising the Igbo language within Enugu State. Findings identify cultural transmission, intergenerational language transfer, dialect standardisation and vocabulary enrichment, and community identity reinforcement as major preservation functions. The study recommends minimum indigenous-language content quotas, youth-oriented programming, broadcaster training and partnerships between radio stations and cultural organisations.',
   'Igbo language, radio broadcasting, language preservation, indigenous media, Enugu State, cultural heritage, ethnolinguistic vitality',
   '55-71'
  ],
  [
   'Deciphering the Cosmopolitan Characters of Some Selected Ilorin’s Compound Names: An Assessment of the Cultural and Linguistic Continuity, 1807-1900',
   'Omotosho Ishola; Wasiu Olayimika Kewulere',
   'This study examines linguistic and cultural continuities replicated in existing compound names and the cultural and linguistic diversity in Ilorin’s compound naming system. Using historical research based on primary and secondary sources, it argues that compound names found across Ilorin between 1807 and 1900 are important cultural and linguistic signposts revealing the city’s long-standing cosmopolitan character. The analysis identifies evidence of intercultural exchange, hybrid identities and the continuity of Ilorin’s multicultural heritage during the nineteenth century.',
   'Ilorin, Compound, Cosmopolitanism, Culture, Linguistic Continuity',
   '72-84'
  ],
  [
   'The Politics and Evolution of the Nigeria Governors’ Forum (NGF) in the Fourth Republic: Issues and Controversies',
   'Mojeed Oyetunji Oyedokun; Shola Ahmed Akanbi',
   'The Nigeria Governors’ Forum has emerged as a central actor in Nigeria’s Fourth Republic, operating as both a cooperative platform for intergovernmental relations and a politically influential elite bloc. This study examines the Forum’s evolution, institutional development and controversies within Nigerian federalism. It argues that the NGF strengthens intergovernmental collaboration and policy innovation while also exposing tensions around elite competition, institutional ambiguity and accountability.',
   'Nigeria Governors’ Forum, Fourth Republic, federalism, intergovernmental relations, democratic consolidation',
   '85-93'
  ]
 ];
 foreach($articles as [$title,$authors,$abstract,$keywords,$pages]){
  $q=$pdo->prepare('SELECT id FROM submissions WHERE title=? LIMIT 1');$q->execute([$title]);$sid=(int)($q->fetchColumn()?:0);
  if(!$sid){$pdo->prepare("INSERT INTO submissions(author_id,title,authors,abstract,keywords,section,language,references_text,stage,status,created_at,updated_at) VALUES(?,?,?,?,?,'Research Article','English','', 'Publication','Published','2025-12-01 00:00:00','2025-12-01 00:00:00')")
      ->execute([$importer,$title,$authors,$abstract,$keywords]);$sid=(int)$pdo->lastInsertId();}
  else{$pdo->prepare("UPDATE submissions SET authors=?,abstract=?,keywords=?,section='Research Article',language='English',stage='Publication',status='Published' WHERE id=?")->execute([$authors,$abstract,$keywords,$sid]);}
  $pdo->prepare("INSERT INTO production_items(submission_id,copyediting_status,production_status,issue_label,pages,doi,published_at,created_at,updated_at) VALUES(?,'Complete','Published',?,?,NULL,'2025-12-01 00:00:00','2025-12-01 00:00:00','2025-12-01 00:00:00') ON DUPLICATE KEY UPDATE copyediting_status='Complete',production_status='Published',issue_label=VALUES(issue_label),pages=VALUES(pages),published_at='2025-12-01 00:00:00'")
      ->execute([$sid,$issueLabel,$pages]);
 }
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
function seed_june_2026_issue(PDO $pdo): void {
 $pdo->exec("UPDATE production_items SET issue_label='Vol. 1 No. 2 (2026)' WHERE issue_label='Vol. 2 No. 2 (2026)'");
 $pdo->exec("DELETE FROM issues WHERE volume=2 AND number=2 AND year=2026");
 $email='publication-import@nigeriaaffairs.com';$username='publication_import';
 $q=$pdo->prepare('SELECT id FROM users WHERE email=? OR username=? LIMIT 1');$q->execute([$email,$username]);$importer=(int)($q->fetchColumn()?:0);
 if(!$importer){$pdo->prepare('INSERT INTO users(email,username,password_hash,given_name,family_name,affiliation,country,orcid,reviewing_interests,roles,verified) VALUES(?,?,?,?,?,?,?,?,?,?,1)')->execute([$email,$username,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'Publication','Import','Nigerian Affairs Editorial Office','Nigeria','','','Author,Reader']);$importer=(int)$pdo->lastInsertId();}
 $pdo->prepare("INSERT INTO issues(volume,number,year,title,description,status,published_at,created_at) VALUES(1,2,2026,?,?, 'Published','2026-06-01 00:00:00','2026-06-01 00:00:00') ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),status='Published',published_at='2026-06-01 00:00:00'")
     ->execute(['Nigerian Affairs','Volume 1, Issue 2, June 2026']);
 $issueLabel='Vol. 1 No. 2 (2026)';
 $articles=[
  [
   'Effectiveness of Broadcast Media in Promoting Skill Acquisition Awareness Among Mass Communication Students at Auchi Polytechnic',
   'Patrick Afam Anikwe; Otono Momodu; Kelly Odaro-Ekhaguebor',
   'Skill acquisition among students has become increasingly important amid high unemployment in Nigeria. This survey examined the effectiveness of broadcast media in promoting awareness of skill acquisition among Mass Communication students at Auchi Polytechnic. Questionnaire data were analysed within the Uses and Gratification theoretical framework. Findings indicate that broadcast media are viable and effective channels for awareness, while inadequate funding, low student engagement and limited airtime constrain their effectiveness. The study recommends adequate funding and more engaging skill-acquisition programmes.',
   'Broadcast Media, Effectiveness, Evaluation, Promotion, Skill Acquisition',
   '1-14',
   [
    ['Patrick Afam Anikwe','Department of Mass Communication, Auchi Polytechnic, Auchi, Edo State'],
    ['Otono Momodu','Department of Mass Communication, Auchi Polytechnic, Auchi, Edo State'],
    ['Kelly Odaro-Ekhaguebor','Department of Mass Communication, Auchi Polytechnic, Auchi, Edo State']
   ]
  ],
  [
   'Audience Awareness and Perception of Femicide Reporting among Trinity University Undergraduates',
   'Olanrewaju Amos Arisoyin; Odunayo Elizabeth Olajuwon; Monisola Aribigbela',
   'Femicide remains a persistent phenomenon in Nigeria and media reports play an important role in shaping public awareness, perception and societal responses. Anchored on Perception Theory, this quantitative study used questionnaires to examine audience awareness and perception of femicide reporting among Trinity University undergraduates. Findings indicate varied levels of media coverage and influence and identify concerns about sensationalism, graphic details and victim-blaming. The study recommends broader audience engagement and coordinated media responses to gender-based violence.',
   'Femicide Reporting, Media, Public Perception, Audience Responses',
   '15-27',
   [
    ['Olanrewaju Amos Arisoyin','Department of Mass Communication, Trinity University, Yaba, Lagos'],
    ['Odunayo Elizabeth Olajuwon','Department of Mass Communication, Ladoke Akintola University of Technology, Ogbomoso, Oyo State'],
    ['Monisola Aribigbela','Department of Mass Communication, Trinity University, Yaba, Lagos']
   ]
  ],
  [
   'Cybercrime Law, Digital Journalism and Press Freedom in Nigeria: A Legal Appraisal of Section 24 of the Cybercrimes Act',
   'Edetalehn Oaihimire Idemudia; Wilfred Oritsesan Olley',
   'The expansion of digital journalism in Nigeria has created new opportunities for public-interest reporting while also increasing exposure to cyberstalking, harassment, threats, impersonation, fraud and harmful falsehoods. This doctrinal legal study examines section 24 of the Cybercrimes Act 2015 and its 2024 amendment, with particular attention to digital journalism and press freedom. Drawing on legislation, constitutional provisions, judicial decisions, regional and international human-rights instruments and documented enforcement practices, the article argues for a restrained, rights-sensitive approach grounded in legality, legitimate aim, necessity, proportionality and protection of bona fide public-interest journalism.',
   'Cybercrime, digital journalism, freedom of expression, press freedom, section 24, Cybercrimes Act, Nigeria, online speech',
   '28-40',
   [
    ['Edetalehn Oaihimire Idemudia','Faculty of Law, Edo State University, Iyamho, Nigeria'],
    ['Wilfred Oritsesan Olley','Department of Mass Communication, Edo State University Iyamho, Nigeria']
   ]
  ],
  [
   'Music Education as a Gateway to Entrepreneurship and Economic Growth in Nigeria: A Qualitative Synthesis of Secondary Evidence',
   'Dora I. Okunbor',
   'This qualitative study synthesises secondary evidence on music education as a pathway to entrepreneurship and economic growth in Nigeria. Peer-reviewed articles, books, industry reports and statistical releases were reviewed using thematic analysis. The synthesis identifies competencies in composition, production, marketing, performance, technology, sound design and engineering, management and therapy, and links them with portfolio careers, freelance and gig work, digital entrepreneurship, intellectual-property exploitation and creative self-employment. It recommends stronger practical facilities, improved creative-enterprise data management and greater integration of music business and media practices into curricula.',
   'Entrepreneurship, Music Education, Entrepreneurial Pathways, Economic Growth, Creative Economy',
   '41-49',
   [
    ['Dora I. Okunbor','Department of Music, University of Delta, Agbor, Delta State, Nigeria']
   ]
  ],
  [
   'An Appraisal of Struggle for Power in the Damaturu Area of Borno: A Case of Competition Between Kanuri and Fulani Groups Over Political Offices in the Pre-colonial Period up to 1960',
   'Abubakar Umar; Umar Inuwa Musa',
   'This study appraises the struggle for political power between Kanuri and Fulani groups in the Damaturu area of Borno from the pre-colonial period to independence. Using a qualitative descriptive and analytical approach based on oral tradition, archival sources and library materials, it traces relations between the groups from early socio-economic interdependence through rivalry, conflict, reconciliation and political incorporation. The study finds that competition over political offices persisted despite cooperation and recommends stronger integration of minority groups into political and socio-economic life.',
   'Colonial, Competition, Power, Pre-colonial Struggle',
   '50-59',
   [
    ['Abubakar Umar','Department of History and International Studies, Yobe State University, Damaturu, Yobe State'],
    ['Umar Inuwa Musa','Department of History and International Studies, Yobe State University, Damaturu, Yobe State']
   ]
  ]
 ];
 foreach($articles as $idx=>[$title,$authors,$abstract,$keywords,$pages,$structured]){
  $q=$pdo->prepare('SELECT id FROM submissions WHERE title=? LIMIT 1');$q->execute([$title]);$sid=(int)($q->fetchColumn()?:0);
  $stamp='2026-06-01 00:00:'.str_pad((string)($idx+1),2,'0',STR_PAD_LEFT);
  if(!$sid){
   $pdo->prepare("INSERT INTO submissions(author_id,title,authors,abstract,keywords,section,language,references_text,stage,status,created_at,updated_at) VALUES(?,?,?,?,?,'Research Article','English','', 'Publication','Published',?,?)")
       ->execute([$importer,$title,$authors,$abstract,$keywords,$stamp,$stamp]);$sid=(int)$pdo->lastInsertId();
  }else{
   $pdo->prepare("UPDATE submissions SET authors=?,abstract=?,keywords=?,section='Research Article',language='English',stage='Publication',status='Published' WHERE id=?")->execute([$authors,$abstract,$keywords,$sid]);
  }
  $pdo->prepare("INSERT INTO production_items(submission_id,copyediting_status,production_status,issue_label,pages,doi,published_at,created_at,updated_at) VALUES(?,'Complete','Published',?,?,NULL,?,?,?) ON DUPLICATE KEY UPDATE copyediting_status='Complete',production_status='Published',issue_label=VALUES(issue_label),pages=VALUES(pages),published_at=VALUES(published_at)")
      ->execute([$sid,$issueLabel,$pages,$stamp,$stamp,$stamp]);
  $check=$pdo->prepare('SELECT COUNT(*) FROM submission_authors WHERE submission_id=?');$check->execute([$sid]);
  if((int)$check->fetchColumn()===0){
   $ins=$pdo->prepare('INSERT INTO submission_authors(submission_id,sort_order,name,affiliation,orcid,scopus_id) VALUES(?,?,?,?,NULL,NULL)');
   foreach($structured as $i=>$au)$ins->execute([$sid,$i+1,$au[0],$au[1]]);
  }
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
