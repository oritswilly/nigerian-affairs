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
 seed_june_2026_galleys($pdo);
 seed_verified_author_ids($pdo);
 return $pdo;
}
function seed_home_announcements(PDO $pdo): void {
 $pdo->exec("DELETE FROM announcements WHERE title IN ('Volume 1, Number 1 (2025) published online','Volume 2, Number 2 (2026) published online')");
 $items=[
  ['Complete Nigerian Affairs digital archive is now available','Volume 1, Number 1 (2025) has moved to the archives and remains available for browsing by issue, article and keyword.','2026-09-24 21:58:02'],
  ['Volume 1, Number 2 (2026) published online','The June 2026 current issue contains 6 peer-reviewed articles in communication, journalism, music education and Nigerian history.','2026-09-24 21:58:01']
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
  ['%Teachers as Communicators of Ethical Values in Higher Education%',[['Peter Eshioke Egielewa','Department of Mass Communication, Edo State University, Iyamho, Edo State, Nigeria','0000-0002-3670-2835',null]]],
  ['%Christian Persecution in Nigeria%',[['Samuel Sunday Alamu','Department of Religious Studies, University of Lagos'],['Victor Adetona','Department of Theology, Wesley University, Ondo']]],
  ['%Media Campaigns as Determinants of Market Women%',[['Lukman Adegboyega Abioye','Department of Communication and Media Technology, Lead City University, Ibadan, Oyo State'],['Adebisi Kazeem Aro','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State','0009-0006-5671-7512',null],['Olusegun Abimbola Odunlami','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State','0000-0002-6305-8678',null],['Oluwatosin Samuel Adesola','Department of Mass Communication, Abraham Adesanya Polytechnic, Ijebu-Igbo, Ogun State']]],
  ['%Preservation and Revitalisation of Nigerian Indigenous Languages%',[['Abidemi Opeyemi Omotayo','Department of English, Sikiru Adetona College of Education, Science and Technology, Omu-Ajose, Ogun State'],['Nureni Abolanle Dairo','Department of English, Sikiru Adetona College of Education, Science and Technology, Omu-Ajose, Ogun State']]],
  ['%Igbo-Language Radio Programmes as Tools for Indigenous Language Preservation%',[['Chukwuebuka Sebastine Okafor','Enugu State University of Science and Technology'],['Joel Asogwa','Department of Mass Communication, Enugu State University of Science and Technology'],['Samuel Elom Chukwudi',null],['Emmanuel Kenechukwu Agbo','Enugu State University of Science and Technology']]],
  ['%Deciphering the Cosmopolitan Characters of Some Selected Ilorin%',[['Omotosho Ishola','Department of History and Diplomatic Studies, Kwara State University, Malete'],['Wasiu Olayimika Kewulere','Department of History and Diplomatic Studies, Kwara State University, Malete']]],
  ['%Politics and Evolution of the Nigeria Governors%',[['Mojeed Oyetunji Oyedokun','Department of History and International Studies, Edo State University, Iyamho, Edo State','0009-0004-6452-285X',null],['Shola Ahmed Akanbi','Department of History and International Relations, Muhammad Kamalud-deen University, Ilorin']]]
 ];
 foreach($sets as [$pattern,$authors]){
  $q=$pdo->prepare("SELECT id FROM submissions WHERE status='Published' AND title LIKE ? ORDER BY id LIMIT 1");$q->execute([$pattern]);$sid=(int)($q->fetchColumn()?:0);if(!$sid)continue;
  $check=$pdo->prepare('SELECT COUNT(*) FROM submission_authors WHERE submission_id=?');$check->execute([$sid]);if((int)$check->fetchColumn()>0)continue;
  $ins=$pdo->prepare('INSERT INTO submission_authors(submission_id,sort_order,name,affiliation,orcid,scopus_id) VALUES(?,?,?,?,?,?)');
  foreach($authors as $i=>$a)$ins->execute([$sid,$i+1,$a[0],$a[1],$a[2]??null,$a[3]??null]);
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
   'Skill acquisition among students has become very necessary given the high rate of unemployment in Nigeria and other African countries. This is because it helps to provide job opportunities for the teeming youths including Mass Communication students after their graduation. Meanwhile, some Mass Communication students are ignorant of the various opportunities that exist for them to acquire skills hence this study examined the effectiveness of broadcast media as potent channel for promoting awareness campaign for skills acquisition among Mass Communication students in Auchi Polytechnic. In the course of carrying out this study, the researchers adopted survey as the research method while questionnaire was used as the instrument of the study. The theoretical thrust of this study is Uses and Gratification theory. The main objective of this study is to determine the level of awareness promoted by broadcast media among Mass Communication Students in Auchi Polytechnic. Findings of the study revealed that the broadcast media is a viable and effective channel for promoting awareness campaign for the acquisition of skills by Mass Communication students in Auchi Polytechnic, Auchi. However, the study showed that there are certain challenges associated with using the broadcast media to promote skills acquisition in Auchi Polytechnic such as inadequate funding, low students’ engagement, limited broadcast airtime etc. As a result of the identified challenges, it was recommended among other things that sufficient funds should be earmarked for promoting skills acquisition and that broadcast media should develop and present engaging skills acquisition programmes capable of motivating Mass Communication students.',
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
   'Femicide has been a persistent phenomenon in Nigeria, which is basically against one of the Sustainable Development Goals. The frequent media reports of femicide tend to generate panic in society. Media reports on femicide play a crucial role in shaping public perception and influencing societal responses. How femicide is portrayed in the media can either reinforce harmful stereotypes or promote awareness and change. Studies indicate that media coverage often lacks depth and sensitivity, sometimes sensationalising the violence rather than addressing the underlying gender-based issues. Against this backdrop, this study investigates the relationship between media reports on the issue of femicide in Nigeria and the audience’s awareness and perception. Anchored on Perception theory, the study employed quantitative research, using questionnaires to explore the in-depth analysis of audience awareness and perception of femicide in Nigeria. The population was estimated at 1046 undergraduate students in the institution; while Taro Yamane’s formula was used to determine the sample size of 290. Purposive and probability sampling were used to sample the respondents, representing diverse demographics related to the study. The findings of this study indicate that 70% says have come across news which tells about femicide, 57.9% show that there is no platform which the media uses in creating awareness, and 57.9% agreed there is a high level of media coverage of femicide in Nigeria. These indicate that media coverage of femicide in Nigeria varies and may influence the audience’s awareness and perceptions of these instances. Also, the media frequently sensationalises stories of femicide, emphasising gory details and blaming victims for their own deaths. The study recommends that Nigerian media outlets should reach out to a variety of audiences, including women, men, youth, and marginalised groups. Interacting with various communities can support the development of a coordinated response to gender-based violence as well as a conversation on the prevention and treatment of femicide.',
   'Femicide Reporting, Media, Public Perception, Audience Responses',
   '15-27',
   [
    ['Olanrewaju Amos Arisoyin','Department of Mass Communication, Trinity University, Yaba, Lagos','0009-0003-9751-1236',null],
    ['Odunayo Elizabeth Olajuwon','Department of Mass Communication, Ladoke Akintola University of Technology, Ogbomoso, Oyo State'],
    ['Monisola Aribigbela','Department of Mass Communication, Trinity University, Yaba, Lagos']
   ]
  ],
  [
   'Cybercrime Law, Digital Journalism and Press Freedom in Nigeria: A Legal Appraisal of Section 24 of the Cybercrimes Act',
   'Idemudia Edetalehn Oaihimire; Wilfred Oritsesan Olley',
   'The rapid expansion of digital journalism has transformed the production, distribution and consumption of news in Nigeria. It has also created new opportunities for cyberstalking, online harassment, threats, impersonation, fraud and the deliberate dissemination of harmful falsehoods. The State therefore has a legitimate interest in regulating serious forms of online harm. The difficulty arises where criminal regulation of digital communication overlaps with constitutionally protected freedom of expression and freedom of the press. This article examines section 24 of the Cybercrimes (Prohibition, Prevention, etc.) Act 2015 and its amendment in 2024, with particular attention to its implications for digital journalism and press freedom. It adopts a doctrinal legal research methodology, supported by a focused case study of section 24 and the principal judicial decisions concerning its validity and application. The analysis draws on Nigerian legislation, constitutional provisions, judicial decisions, regional and international human rights instruments, scholarly literature and documented enforcement practices. The article argues that the original section 24 was problematic because it combined serious forms of online harm with expressions such as “annoyance”, “insult”, “ill will” and “needless anxiety”, thereby creating considerable uncertainty about the boundary between criminal conduct and legitimate expression. The Court of Appeal in Okedara v Attorney General of the Federation upheld the provision, while the ECOWAS Court of Justice subsequently found that Nigeria’s maintenance of the original provision violated its international human rights obligations and directed Nigeria to amend or repeal it. The 2024 amendment substantially narrowed subsection 24(1) by removing several subjective expressions and concentrating liability on pornographic content and knowingly false communications intended to cause a breakdown of law and order or pose a threat to life. Questions concerning interpretation, enforcement and adequate protection for public-interest journalism nonetheless remain. The article concludes that the legitimacy of section 24 cannot be assessed solely by reference to the wording of the statute; its constitutional and human rights acceptability also depends on how the provision is interpreted and enforced. The article accordingly advocates a restrained, rights-sensitive approach grounded in legality, legitimate aim, necessity, proportionality and the protection of bona fide public-interest journalism.',
   'Cybercrime, digital journalism, freedom of expression, press freedom, section 24, Cybercrimes Act, Nigeria, online speech',
   '28-40',
   [
    ['Idemudia Edetalehn Oaihimire','Faculty of Law, Edo State University, Iyamho, Nigeria'],
    ['Wilfred Oritsesan Olley','Department of Mass Communication, Edo State University Iyamho, Nigeria','0000-0001-5405-765X','57862966200']
   ]
  ],
  [
   'Music Education as a Gateway to Entrepreneurship and Economic Growth in Nigeria: A Qualitative Synthesis of Secondary Evidence',
   'Dora I. Okunbor',
   'Entrepreneurship has become an indispensable mechanism for addressing youth unemployment in Nigeria. While music education is often perceived primarily as an academic and artistic discipline, its potential as a practical pathway to entrepreneurship and economic growth has received limited consolidated attention in the Nigerian context. This study adopted a qualitative research design based on secondary sources of data. Peer-reviewed journal articles, books, industry reports, and statistical releases from the National Bureau of Statistics (NBS), as reported by Business Day (2024), were reviewed and subjected to thematic analysis following Braun and Clarke’s (2006) six-phase framework. The analysis focused on identifying recurring themes relating to music-related entrepreneurial pathways, competencies, and contributions to economic growth. The synthesis suggests that formal university music education in Nigeria potentially equips learners with a diverse range of entrepreneurial competencies, including music composition, music production, music marketing, music performance, music technology, sound design and engineering, music management, and music therapy. The literature indicates that these competencies align with contemporary forms of music entrepreneurship, including portfolio careers, freelance and gig work, digital entrepreneurship, intellectual property exploitation, production services, and creative self-employment. Despite acknowledged limitations in data availability, including poor record-keeping and a high rate of unregistered creative enterprises, NBS data reported by Business Day (2024) indicated that Nigeria’s movie, music and entertainment industries grew by 27.46% between 2020 and 2023, rising from N1.55 trillion to N1.97 trillion. The study concludes that music education should be recognised as a viable pathway to entrepreneurship and recommends strengthened investment in practical facilities, improved registration and data management of creative enterprises, and enhanced integration of music business and media practices into the curriculum.',
   'Entrepreneurship, Music Education, Entrepreneurial Pathways, Economic Growth, Creative Economy',
   '41-49',
   [
    ['Dora I. Okunbor','Department of Music, University of Delta, Agbor, Delta State, Nigeria']
   ]
  ],
  [
   'An Appraisal of Struggle for Power in the Damaturu Area of Borno: A Case of Competition Between Kanuri and Fulani Groups Over Political Offices in the Pre-colonial Period up to 1960',
   'Abubakar Umar; Umar Inuwa Musa',
   'This work is an appraisal of the struggle for Power between Kanuri and Fulani over political offices in the Pre-colonial colonial periods. The early contact of the two groups was traced to the 13th Century AD, during which time a socio-economic symbiotic relationship was the key component of their togetherness. The principal aim of the research was to explore the competition between the Kanuri and the Fulani over political power. Documenting the history of inter-group relationships can promote understanding and peaceful coexistence between the groups. It highlights their shared experiences, fostering a sense of unity and shared identity between them, building a stronger and harmonious community and perhaps informing policies that promote social cohesion. A qualitative approach using descriptive and analytical methods of research was adopted. The research therefore heavily relied on primary data that largely derived from oral tradition and archival sources, while library materials were also utilised. The work revealed that the group members are often friends, neighbours and partners who collaborate to develop many towns and villages. Wars were fought, rivalry developed and reconciliation and peace were reached. Consequently, Fulanis were co-opted into the mainstream of Kanuri political culture. Nevertheless, competition over political power continued to exist between them up to independence.',
   'Colonial, Competition, Power, Pre-colonial Struggle',
   '50-59',
   [
    ['Abubakar Umar','Department of History and International Studies, Yobe State University, Damaturu, Yobe State'],
    ['Umar Inuwa Musa','Department of History and International Studies, Yobe State University, Damaturu, Yobe State']
   ]
  ],
  [
   'Mother as a Superhero in Buchi Emecheta’s The Joys of Motherhood',
   'Irene Ejehiokhin Akhideno; Solomon Awuzie; Clement Michael Inobeme',
   'This article argues that Buchi Emecheta’s The Joys of Motherhood engages with mothers’ experiences in Africa. It reveals that a mother in Africa is always a victim of constant abuse, violence and neglect, despite her commitment to her family. Using Nnu Ego, the protagonist of the novel, as an example of a committed mother, the article engages with the sacrifices of mothers in families. It also shows how much suffering, abuse and violence a mother endures to hold on to the image of her undying love for her family. This article portrays a mother, such as Nnu Ego, who continues to endure an abusive marriage because of her irresponsible children, as a superhero, contrary to popular chauvinistic postulations that portray such a stay as a weakness. Even though this article adopts feminism as the literary theory to better understand this discourse on motherism, Buchi Emecheta\'s The Joys of Motherhood can be referred to as one of the canonical texts often deployed to better understand discourses on African feminism, where womanism and motherism are strongly featured as feminist ideologies. It uses the novel as a tool to emphasise the writer’s perception of mothers’ committed lifestyles and the patriarchal stereotypes that constantly try to pull them down. This article concludes that The Joys of Motherhood is a novel that is about how a mother, Nnu Ego, is able to recreate her personal life experience, which is characterised by tales of suffering, abuse and violence, for the benefit of her family.',
   'Mother, Feminism, Violence against women, Patriarchal Stereotype',
   '60-68',
   [
    ['Irene Ejehiokhin Akhideno','Department of English, Edo State University, Iyamho'],
    ['Solomon Awuzie','Department of English, Edo State University, Iyamho'],
    ['Clement Michael Inobeme','Department of English and Literary Studies, University of Ilorin']
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
   $ins=$pdo->prepare('INSERT INTO submission_authors(submission_id,sort_order,name,affiliation,orcid,scopus_id) VALUES(?,?,?,?,?,?)');
   foreach($structured as $i=>$au)$ins->execute([$sid,$i+1,$au[0],$au[1],$au[2]??null,$au[3]??null]);
  }
 }
 $pdo->prepare("UPDATE submission_authors sa JOIN submissions s ON s.id=sa.submission_id SET sa.name='Idemudia Edetalehn Oaihimire', sa.orcid='0009-0007-1507-2815' WHERE s.title='Cybercrime Law, Digital Journalism and Press Freedom in Nigeria: A Legal Appraisal of Section 24 of the Cybercrimes Act' AND sa.sort_order=1")->execute();
 $pdo->exec("UPDATE submission_authors SET orcid='0000-0001-5405-765X',scopus_id='57862966200' WHERE name='Wilfred Oritsesan Olley'");
}
function seed_june_2026_galleys(PDO $pdo): void {
 $dir=__DIR__.'/storage/uploads';
 $items=[
  ['Effectiveness of Broadcast Media in Promoting Skill Acquisition Awareness Among Mass Communication Students at Auchi Polytechnic','na-1-8a6efc3695bdd66a89f064ab.pdf','8a6efc3695bdd66a89f064abddaace4db3f4edf07779278111c16fd0ab63738a'],
  ['Audience Awareness and Perception of Femicide Reporting among Trinity University Undergraduates','na-2-aed91195bf0e5dd3d805273d.pdf','aed91195bf0e5dd3d805273d97a47fc84312b98f94da089135b78534cf43066a'],
  ['Cybercrime Law, Digital Journalism and Press Freedom in Nigeria: A Legal Appraisal of Section 24 of the Cybercrimes Act','na-3-86e434284a896d3305cb20d2.pdf','86e434284a896d3305cb20d23bf0690e88a9b690378dee140db079c7046af89c'],
  ['Music Education as a Gateway to Entrepreneurship and Economic Growth in Nigeria: A Qualitative Synthesis of Secondary Evidence','na-4-794d942ed538edc985f4ba2b.pdf','794d942ed538edc985f4ba2b85d314d2300f57dfa4ee29020e63679c62a935ca'],
  ['An Appraisal of Struggle for Power in the Damaturu Area of Borno: A Case of Competition Between Kanuri and Fulani Groups Over Political Offices in the Pre-colonial Period up to 1960','na-5-2b831478336108d42cf3da6a.pdf','2b831478336108d42cf3da6ad3aa8717178ca7fde1d47ced1bf754b635649d36'],
  ['Mother as a Superhero in Buchi Emecheta’s The Joys of Motherhood','na-6-17986a73ab8ec8f015e4ba46.pdf','17986a73ab8ec8f015e4ba46ce2d2b79be34fc6f89e716e84acdf307c4a9d5c4']
 ];
 foreach($items as [$title,$name,$sha]){
  $q=$pdo->prepare("SELECT s.id FROM submissions s JOIN production_items p ON p.submission_id=s.id WHERE s.title=? AND s.status='Published' AND p.issue_label='Vol. 1 No. 2 (2026)' LIMIT 1");
  $q->execute([$title]);$sid=(int)($q->fetchColumn()?:0);if(!$sid)continue;
  $path='?page=galley&file='.rawurlencode($name);
  $g=$pdo->prepare("SELECT id FROM galleys WHERE submission_id=? AND mime_type='application/pdf' ORDER BY id DESC LIMIT 1");$g->execute([$sid]);$gid=(int)($g->fetchColumn()?:0);
  if($gid)$pdo->prepare("UPDATE galleys SET label='PDF',file_path=?,mime_type='application/pdf' WHERE id=?")->execute([$path,$gid]);
  else $pdo->prepare("INSERT INTO galleys(submission_id,label,file_path,mime_type) VALUES(?,'PDF',?,'application/pdf')")->execute([$sid,$path]);
 }
}
function seed_verified_author_ids(PDO $pdo): void {
 $verified=[
  ['Peter Eshioke Egielewa','0000-0002-3670-2835',null],
  ['Adebisi Kazeem Aro','0009-0006-5671-7512',null],
  ['Olusegun Abimbola Odunlami','0000-0002-6305-8678',null],
  ['Mojeed Oyetunji Oyedokun','0009-0004-6452-285X',null],
  ['Olanrewaju Amos Arisoyin','0009-0003-9751-1236',null],
  ['Wilfred Oritsesan Olley','0000-0001-5405-765X','57862966200']
 ];
 $q=$pdo->prepare('UPDATE submission_authors SET orcid=?, scopus_id=COALESCE(?,scopus_id) WHERE name=?');
 foreach($verified as [$name,$orcid,$scopus])$q->execute([$orcid,$scopus,$name]);
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
