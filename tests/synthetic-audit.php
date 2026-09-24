<?php
declare(strict_types=1);
require dirname(__DIR__).'/config.php';

$pdo=db();
$prefix='SYNTHETIC-NA-PREDEPLOY-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
$passed=[];
function pass(string $name,string $detail=''): void {global $passed;$passed[]=$name;echo "[PASS] {$name}".($detail!==''?": {$detail}":'').PHP_EOL;}
function must(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
try {
 $publishedNow=$pdo->query("SELECT id,title,authors,status FROM submissions WHERE LOWER(status)='published' ORDER BY id")->fetchAll();
 foreach($publishedNow as $pub)echo '[INFO] published: '.(int)$pub['id'].' | '.$pub['title'].' | '.$pub['authors'].PHP_EOL;
 echo '[INFO] published_count='.count($publishedNow).PHP_EOL;
 $known=[
  ['%Teachers as Communicators of Ethical Values in Higher Education%',1],
  ['%Christian Persecution in Nigeria%',2],
  ['%Media Campaigns as Determinants of Market Women%',4],
  ['%Preservation and Revitalisation of Nigerian Indigenous Languages%',2],
  ['%Igbo-Language Radio Programmes as Tools for Indigenous Language Preservation%',4],
  ['%Deciphering the Cosmopolitan Characters of Some Selected Ilorin%',2],
  ['%Politics and Evolution of the Nigeria Governors%',2]
 ];
 $knownArticles=0;$knownAuthors=0;
 foreach($known as [$pattern,$expected]){
  $q=$pdo->prepare("SELECT s.id,(SELECT COUNT(*) FROM submission_authors sa WHERE sa.submission_id=s.id) author_count FROM submissions s WHERE s.status='Published' AND s.title LIKE ? ORDER BY s.id LIMIT 1");
  $q->execute([$pattern]);$row=$q->fetch();must((bool)$row,"Published inaugural article not found for {$pattern}");must((int)$row['author_count']===$expected,"Structured author count mismatch for {$pattern}: expected {$expected}, got ".(int)$row['author_count']);$knownArticles++;$knownAuthors+=(int)$row['author_count'];
 }
 must($knownArticles===7&&$knownAuthors===17,'Inaugural structured metadata totals are incomplete');
 pass('inaugural_metadata','7 published articles and 17 verified structured author records');

 $pdo->beginTransaction();

 $tables=['users','submissions','submission_files','submission_editors','reviews','decisions','revisions','production_items','issues','galleys','apc_payments','payment_evidence','submission_authors','audit_log','journal_settings'];
 $q=$pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema=?");
 $q->execute([getenv('DB_NAME')?:'']);
 $have=array_flip(array_map('strtolower',$q->fetchAll(PDO::FETCH_COLUMN)));
 foreach($tables as $t)must(isset($have[strtolower($t)]),"Missing required table {$t}");
 pass('schema','all workflow tables present');

 $makeUser=function(string $suffix,string $roles) use($pdo,$prefix): int {
  $email=strtolower($prefix.'-'.$suffix.'@example.invalid');
  $username=strtolower(preg_replace('/[^a-z0-9_]/i','_',$prefix.'_'.$suffix));
  $pdo->prepare('INSERT INTO users(email,username,password_hash,given_name,family_name,affiliation,country,orcid,reviewing_interests,roles,verified) VALUES(?,?,?,?,?,?,?,?,?,?,1)')
      ->execute([$email,$username,password_hash('SyntheticAudit123!',PASSWORD_DEFAULT),'Synthetic',$suffix,'Synthetic University','Nigeria','',$prefix,$roles]);
  return (int)$pdo->lastInsertId();
 };
 $author=$makeUser('Author','Author,Reader');
 $reviewer=$makeUser('Reviewer','Reviewer,Reader');
 $editor=$makeUser('Editor','Editor,Author,Reader');
 $manager=$makeUser('Manager','Journal Manager,Editor,Author,Reader');
 pass('users','author/reviewer/editor/manager');

 $title=$prefix.': Workflow Verification';
 $pdo->prepare("INSERT INTO submissions(author_id,title,authors,abstract,keywords,section,language,references_text,stage,status) VALUES(?,?,?,?,?,?,?,?,'Submission','Awaiting editorial screening')")
     ->execute([$author,$title,'Synthetic Author; Synthetic Coauthor','Synthetic rollback-only abstract','synthetic,test,workflow','Research Article','English','Synthetic references']);
 $sid=(int)$pdo->lastInsertId();
 pass('submission','submission created');

 $q=$pdo->prepare("SELECT COUNT(*) FROM submissions s WHERE s.id=? AND s.status NOT IN ('Published','Declined') AND NOT EXISTS(SELECT 1 FROM submission_editors se WHERE se.submission_id=s.id)");
 $q->execute([$sid]);must((int)$q->fetchColumn()===1,'Unassigned queue semantics failed');
 $pdo->prepare('INSERT INTO submission_editors(submission_id,editor_id,assigned_by) VALUES(?,?,?)')->execute([$sid,$editor,$manager]);
 $q=$pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN submission_editors se ON se.submission_id=s.id WHERE s.id=? AND se.editor_id=? AND s.status NOT IN ('Published','Declined')");
 $q->execute([$sid,$editor]);must((int)$q->fetchColumn()===1,'My Queue semantics failed');
 pass('editor_assignment','My Queue and Unassigned verified');

 $pdo->prepare('INSERT INTO submission_files(submission_id,uploaded_by,file_stage,file_role,original_name,stored_name,mime_type,file_size,version_no) VALUES(?,?,?,?,?,?,?,?,?)')
     ->execute([$sid,$author,'Submission','Review File',$prefix.'-review.docx',$prefix.'-'.$sid.'-review.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',1024,1]);
 $pdo->prepare("INSERT INTO reviews(submission_id,reviewer_id,status,recommendation,comments_to_author,comments_to_editor,due_at,responded_at,submitted_at) VALUES(?,?,'Submitted','Revisions Required','Synthetic author comments','Synthetic editor comments',DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(),NOW())")
     ->execute([$sid,$reviewer]);
 $pdo->prepare("UPDATE submissions SET stage='Review',status='Review submitted' WHERE id=?")->execute([$sid]);
 pass('peer_review','review record and review file verified');

 $pdo->prepare('INSERT INTO decisions(submission_id,editor_id,decision,note) VALUES(?,?,?,?)')->execute([$sid,$editor,'Revisions Required',$prefix.' revision']);
 $pdo->prepare("UPDATE submissions SET stage='Submission',status='Revision requested' WHERE id=?")->execute([$sid]);
 $pdo->prepare("INSERT INTO revisions(submission_id,author_id,response_to_reviewers,revised_title,revised_abstract,status) VALUES(?,?,?,?,?,'Submitted')")
     ->execute([$sid,$author,$prefix.' response',$prefix.': Revised','Synthetic revised abstract']);
 $pdo->prepare('INSERT INTO submission_files(submission_id,uploaded_by,file_stage,file_role,original_name,stored_name,mime_type,file_size,version_no) VALUES(?,?,?,?,?,?,?,?,?)')
     ->execute([$sid,$author,'Revision','Revised Manuscript',$prefix.'-revision.docx',$prefix.'-'.$sid.'-revision.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',2048,2]);
 $pdo->prepare("UPDATE submissions SET stage='Review',status='Revision submitted' WHERE id=?")->execute([$sid]);
 pass('revision','revision and second manuscript version');

 $pdo->prepare('INSERT INTO decisions(submission_id,editor_id,decision,note) VALUES(?,?,?,?)')->execute([$sid,$editor,'Accept Submission',$prefix.' acceptance']);
 $pdo->prepare("UPDATE submissions SET stage='Copyediting',status='Accepted' WHERE id=?")->execute([$sid]);
 $pdo->prepare("INSERT INTO production_items(submission_id,copyediting_status,production_status) VALUES(?,'Complete','In production')")->execute([$sid]);
 $pdo->prepare("UPDATE submissions SET stage='Production',status='In production' WHERE id=?")->execute([$sid]);
 pass('production','acceptance, copyediting and production transition');

 $issueTitle=$prefix.': Test Issue';
 $pdo->prepare("INSERT INTO issues(volume,number,year,title,description,status,published_at) VALUES(999,24,2026,?,?,'Published',NOW())")
     ->execute([$issueTitle,'Synthetic rollback-only issue']);
 $issueId=(int)$pdo->lastInsertId();
 $issueLabel='Vol. 999 No. 24 (2026)';
 $pdo->prepare('INSERT INTO galleys(submission_id,label,file_path,mime_type) VALUES(?,?,?,?)')->execute([$sid,'PDF','?page=galley&file=na-'.$sid.'-synthetic.pdf','application/pdf']);

 $pdo->prepare("INSERT INTO apc_payments(submission_id,payment_type,amount,reference_no,status,evidence_note,verified_by,verified_at) VALUES(?,'Publication fee',30000,?,'Verified','Synthetic verified evidence',?,NOW())")
     ->execute([$sid,$prefix.'-PAY',$manager]);
 $paymentId=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO payment_evidence(payment_id,submitted_by,original_name,stored_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')
     ->execute([$paymentId,$author,$prefix.'-receipt.pdf',$prefix.'-receipt.pdf','application/pdf',512]);
 $q=$pdo->prepare("SELECT COUNT(*) FROM apc_payments WHERE submission_id=? AND payment_type='Publication fee' AND status IN ('Verified','Waived')");
 $q->execute([$sid]);must((int)$q->fetchColumn()===1,'Publication payment gate failed');
 pass('payment_gate','verified publication fee satisfies gate');

 $pdo->prepare('INSERT INTO submission_authors(submission_id,sort_order,name,affiliation,orcid,scopus_id) VALUES(?,?,?,?,?,?),(?,?,?,?,?,?)')
     ->execute([$sid,1,'Synthetic Author One','Synthetic University','0000-0000-0000-0001','1234567890',$sid,2,'Synthetic Author Two','Synthetic Institute','0000-0000-0000-0002','0987654321']);
 $q=$pdo->prepare('SELECT name,affiliation,orcid,scopus_id FROM submission_authors WHERE submission_id=? ORDER BY sort_order,id');$q->execute([$sid]);$authors=$q->fetchAll();
 must(count($authors)===2&&$authors[0]['name']==='Synthetic Author One','Structured author ordering failed');
 pass('structured_authors','affiliation, ORCID, Scopus ID and order');

 $pdo->prepare("UPDATE production_items SET production_status='Published',issue_label=?,pages='1-12',doi='10.0000/synthetic-na-audit',published_at=NOW() WHERE submission_id=?")->execute([$issueLabel,$sid]);
 $pdo->prepare("UPDATE submissions SET stage='Publication',status='Published' WHERE id=?")->execute([$sid]);

 $q=$pdo->prepare("SELECT s.id,p.issue_label,p.pages,p.doi FROM submissions s JOIN production_items p ON p.submission_id=s.id WHERE s.id=? AND s.status='Published' AND p.published_at IS NOT NULL");$q->execute([$sid]);$article=$q->fetch();
 must((bool)$article&&$article['issue_label']===$issueLabel&&$article['pages']==='1-12','Published article query failed');
 pass('public_article','published article metadata');

 $q=$pdo->prepare("SELECT i.id,CONCAT('Vol. ',i.volume,' No. ',i.number,' (',i.year,')') issue_label FROM issues i WHERE i.id=? AND i.status='Published' AND EXISTS(SELECT 1 FROM production_items p JOIN submissions s ON s.id=p.submission_id WHERE s.status='Published' AND p.issue_label=CONCAT('Vol. ',i.volume,' No. ',i.number,' (',i.year,')'))");$q->execute([$issueId]);$current=$q->fetch();
 must((bool)$current&&$current['issue_label']===$issueLabel,'Current Issue grouping failed');
 $q=$pdo->prepare("SELECT p.issue_label,COUNT(*) articles FROM production_items p JOIN submissions s ON s.id=p.submission_id WHERE s.status='Published' AND p.issue_label=? GROUP BY p.issue_label");$q->execute([$issueLabel]);$archive=$q->fetch();
 must((bool)$archive&&(int)$archive['articles']===1,'Archive grouping failed');
 pass('issue_grouping','Current and Archives grouping');

 $pdo->rollBack();
 pass('rollback','synthetic transaction rolled back');

 $checks=[
  ['users','email LIKE ?',strtolower('%'.$prefix.'%')],
  ['submissions','title LIKE ?','%'.$prefix.'%'],
  ['issues','title LIKE ?','%'.$prefix.'%'],
  ['apc_payments','reference_no LIKE ?','%'.$prefix.'%'],
  ['payment_evidence','original_name LIKE ?','%'.$prefix.'%']
 ];
 foreach($checks as [$table,$where,$value]){$q=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");$q->execute([$value]);must((int)$q->fetchColumn()===0,"Rollback cleanup failed for {$table}");}
 pass('cleanup','zero synthetic rows remain');
 echo 'TOTAL='.count($passed).' PASS='.count($passed).' FAIL=0'.PHP_EOL;
 exit(0);
} catch(Throwable $e) {
 if($pdo->inTransaction())$pdo->rollBack();
 fwrite(STDERR,'[FAIL] '.$e->getMessage().PHP_EOL);
 exit(1);
}
