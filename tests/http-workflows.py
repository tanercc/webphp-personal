"""Exercise the real copied panel and public theme against a disposable SQLite or MySQL DB.
Usage: python tests/http-workflows.py [--driver sqlite|mysql]  (mysql reads PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD)"""
from pathlib import Path
import os,sys,subprocess,tempfile,socket,time,urllib.request,urllib.error,urllib.parse,http.cookiejar,json,re,hashlib,base64,secrets,contextlib
ROOT=Path(__file__).resolve().parent.parent
PHP=subprocess.check_output(['php','-r','echo PHP_BINARY;'],text=True).strip()
count=0
args=sys.argv[1:]
DRIVER=args[1] if len(args)==2 and args[0]=='--driver' else args[0][9:] if len(args)==1 and args[0].startswith('--driver=') else 'sqlite' if not args else None
if DRIVER not in ('sqlite','mysql'):print('Usage: python tests/http-workflows.py [--driver sqlite|mysql]',file=sys.stderr);sys.exit(2)
MYSQL_KEYS=['PANEL_TEST_MYSQL_'+k for k in ('HOST','PORT','USER','PASSWORD')]
if DRIVER=='mysql' and any(k not in os.environ or (os.environ[k]=='' and not k.endswith('PASSWORD')) for k in MYSQL_KEYS):
 print('MySQL test credentials missing (PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD)');sys.exit(2)
SECRETS=[os.environ[k] for k in MYSQL_KEYS if len(os.environ.get(k,''))>3] if DRIVER=='mysql' else []
def scrub(text):
 for value in SECRETS:text=text.replace(value,'***')
 return text
DB_ENV={}
def db(sql,params=()):
 r=subprocess.run([PHP,str(ROOT/'tests/db-query.php'),'--sql='+sql,'--params='+json.dumps(list(params))],cwd=ROOT,env=DB_ENV,capture_output=True)
 assert r.returncode==0,scrub(r.stderr.decode('utf-8','replace'))
 return json.loads(r.stdout.decode('utf-8'))
def scratch_call(flag,name):
 r=subprocess.run([PHP,str(ROOT/'tests/db-query.php'),flag+'='+name],cwd=ROOT,capture_output=True)
 assert r.returncode==0,scrub(r.stderr.decode('utf-8','replace'))
@contextlib.contextmanager
def scratch_db():
 """Yield a fresh MySQL database name (None for SQLite); always dropped afterwards."""
 if DRIVER=='sqlite':yield None;return
 name='cms_test_'+secrets.token_hex(6)
 try:scratch_call('--create-database',name);yield name
 finally:scratch_call('--drop-database',name)
def db_env(base,sqlite_file,name):
 e=dict(base)
 if DRIVER=='sqlite':e.update(PANEL_DB_DRIVER='sqlite',PANEL_DB_FILE=str(sqlite_file))
 else:e.update(PANEL_DB_DRIVER='mysql',PANEL_DB_HOST=os.environ[MYSQL_KEYS[0]],PANEL_DB_PORT=os.environ[MYSQL_KEYS[1]],PANEL_DB_NAME=name,PANEL_DB_USER=os.environ[MYSQL_KEYS[2]],PANEL_DB_PASSWORD=os.environ[MYSQL_KEYS[3]])
 return e
def snapshot(sqlite_file):
 """Fingerprint of the installed database, used to prove a refused install changed nothing."""
 if DRIVER=='sqlite':return hashlib.sha256(Path(sqlite_file).read_bytes()).hexdigest()
 return [db('SELECT COUNT(*) AS c FROM '+t)[0]['c'] for t in ('pages','users','datas','logs','page_lang')]
def contract(drivers):
 for d in drivers:
  r=subprocess.run([PHP,str(ROOT/'tests/db-contract.php'),'--driver='+d],cwd=ROOT,capture_output=True)
  assert r.returncode==0,(d,scrub((r.stdout+r.stderr).decode('utf-8','replace'))[-1500:])
NON_PORTABLE_SQL=[r'SQL_CALC_FOUND_ROWS',r'FOUND_ROWS',r'SHOW\s+COLUMNS',r'sqlite_master',r'PRAGMA',r'ON\s+CONFLICT',r'ON\s+DUPLICATE',r'INSERT\s+INTO\s+`[a-z_]+`\s+SET',r'''LIMIT\s+['"]\s*\.[^;\n]*,''']

def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,method,path,fields=None,token=None,status=200):
 global count
 data=urllib.parse.urlencode(fields).encode() if fields is not None else None
 headers={'Content-Type':'application/x-www-form-urlencoded'}
 if token:headers['X-CSRF-Token']=token
 try:r=c.open(urllib.request.Request(base+path,data=data,headers=headers,method=method),timeout=15)
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode('utf-8');count+=1
 assert r.code==status,(method,path,r.code,status,body[:250])
 assert not any(t in body for t in ['Fatal error','Warning:','An error occurred.']),(path,body[:300])
 return body

def js(*args,**kwargs):return json.loads(request(*args,**kwargs))
def csrf(html):
 m=re.search(r'name="csrf_token" value="([^"]+)"',html) or re.search(r'name="csrf-token" content="([^"]+)"',html)
 assert m,'Missing CSRF';return m[1]

contract(['sqlite'] if DRIVER=='sqlite' else ['mysql','both'])
with tempfile.TemporaryDirectory(prefix='personal-'+DRIVER+'-test-') as tmp, scratch_db() as scratch:
 tmp=Path(tmp);(tmp/'sessions').mkdir();env=db_env(os.environ,tmp/'test.sqlite',scratch);env.update(PERSONAL_DB_FILE=str(tmp/'test.sqlite'),PERSONAL_SESSION_PATH=str(tmp/'sessions'),PERSONAL_RATE_FILE=str(tmp/'rate.json'));DB_ENV=env
 install=subprocess.run([PHP,str(ROOT/'tools/install.php')],env=env,capture_output=True);assert install.returncode==0,scrub((install.stdout+install.stderr).decode('utf-8','replace'))
 before=snapshot(tmp/'test.sqlite')
 refuse=subprocess.run([PHP,str(ROOT/'tools/install.php')],env=env,capture_output=True)
 assert refuse.returncode==1 and snapshot(tmp/'test.sqlite')==before
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 base='http://127.0.0.1:'+str(port)
 with open(tmp/'server.log','wb') as log:
  server=subprocess.Popen([PHP,'-d','display_errors=0','-S','127.0.0.1:'+str(port),'-t',str(ROOT),str(ROOT/'router.php')],cwd=ROOT,env=env,stdout=log,stderr=log)
  try:
   for attempt in range(60):
    try:urllib.request.urlopen(base,timeout=1).close();break
    except OSError:time.sleep(.1)
   else:raise AssertionError('Server failed to start')
   guest=client();admin=client()
   for path in ['/','/resume','/projects','/projects/atolye','/contact','/privacy','/terms','/index.html','/resume.html']:
    html=request(guest,'GET',path);assert 'Deniz' in html
    for url,sri in re.findall(r'(?:href|src)="([^\"]+)" integrity="(sha384-[^\"]+)"',html):
     asset=ROOT/url.split('?')[0].lstrip('/');assert sri=='sha384-'+base64.b64encode(hashlib.sha384(asset.read_bytes()).digest()).decode()
   request(guest,'GET','/missing-page',status=404)
   for path in ['/storage/personal.sqlite','/database/schema.php','/database/seed.php','/database/seed-base.php','/config/config.php','/app/bootstrap.php','/templates/pages/home.php','/tools/install.php','/.git/config','/panel/include/database.php','/panel/include/db_dialect.php','/panel/include/db_install.php','/tests/db-query.php','/panel/plugins/util/pass.php','/panel/temp/Untitled-1.js','/panel/plugins/ckeditor/samples/index.html','/panel/plugins/filemanager/../../../storage/personal.sqlite']:
    request(guest,'GET',path,status=403)
   js(guest,'GET','/panel/api/pages',status=401)
   request(guest,'GET','/panel/plugins/filemanager/dialog.php?type=1',status=403)
   login=request(admin,'GET','/panel/');token=csrf(login);assert 'data-callback' not in login and 'g-recaptcha' not in login
   request(admin,'POST','/panel/',{'email':'admin@personal.test','password':'PersonalDemo!2026','csrf_token':'wrong'},status=403)
   login=request(admin,'GET','/panel/');token=csrf(login)
   dashboard=request(admin,'POST','/panel/',{'email':'admin@personal.test','password':'PersonalDemo!2026','csrf_token':token});assert 'İçerikleri düzenleyin' in dashboard
   token=csrf(dashboard)
   for resource in ['pages','pagetypes','datas','datatypes','cats','tags','templates','menus','menutypes','sliders','slidertypes','variables','captions','users']:
    data=js(admin,'GET','/panel/api/'+resource+'?pageIndex=1&pageSize=10&sortField=id&sortOrder=ASC');assert 'data' in data and 'itemsCount' in data
    request(admin,'GET','/panel/'+resource)
   for path in ['/panel/pages/1','/panel/pages/1/tr','/panel/datas/1','/panel/datas/1/tr','/panel/cats/new','/panel/datas/new','/panel/users/1','/panel/variables/1','/panel/messages']:
    request(admin,'GET',path)
   request(admin,'GET','/panel/datas/1')
   request(admin,'GET','/panel/plugins/filemanager/dialog.php?type=1&field_id=image&fldr=/')
   assert 'name="column_1"' in request(admin,'GET','/panel/datas/3')
   js(admin,'PUT','/panel/api/datas/3',{'column_1':'2030 - Present'},token=token)
   assert '2030 - Present' in request(guest,'GET','/resume')
   assert 'password' not in js(admin,'GET','/panel/api/users')['data'][0]
   js(admin,'POST','/panel/api/pages',{'name':'CSRF should fail'},status=403)
   js(admin,'PUT','/panel/api/pages/1',{'unexpected':'field'},token=token,status=422)
   fields={'name':"SQLite O'Reilly \\ demo",'slug':'sqlite-test','type_id':'1'}
   rid=js(admin,'POST','/panel/api/pages',fields,token=token)['id']
   assert js(admin,'GET','/panel/api/pages/'+str(rid))['name']==fields['name']
   uni={'name':'Çağrı 😀 ışık','slug':'unicode-test','type_id':'1'}
   uid=js(admin,'POST','/panel/api/pages',uni,token=token)['id']
   assert js(admin,'GET','/panel/api/pages/'+str(uid))['name']==uni['name']
   assert db('SELECT name FROM pages WHERE slug=?',('unicode-test',))[0]['name']==uni['name']
   assert all(isinstance(row['id'],str) for row in js(admin,'GET','/panel/api/pages')['data']) and isinstance(js(admin,'GET','/panel/api/pages/1')['id'],str)
   js(admin,'PUT','/panel/api/pages/'+str(rid),{'name':'Updated','is_active':'0'},token=token)
   js(admin,'DELETE','/panel/api/pages/'+str(rid),token=token)
   guarded=[f for d in ['app','panel/include','panel/api','templates'] for f in (ROOT/d).rglob('*.php') if f.name!='db_dialect.php' or d=='panel/api']+[ROOT/'index.php']
   for f in guarded:
    text=f.read_text(encoding='utf-8')
    for pattern in NON_PORTABLE_SQL:assert not re.search(pattern,text,re.I),(f.name,pattern)
   like=js(admin,'POST','/panel/api/pages',{'name':'100%_off!','slug':'like-test','type_id':'1'},token=token)['id']
   assert js(admin,'GET','/panel/api/pages?q=100%25_')['itemsCount']==1
   assert js(admin,'GET','/panel/api/pages?q=1000')['itemsCount']==0
   assert js(admin,'GET','/panel/api/pages?q=100!')['itemsCount']==0
   assert js(admin,'GET','/panel/api/pages?q=100%25_off!')['itemsCount']==1
   assert int(db('SELECT deleted FROM pages WHERE id=?',(rid,))[0]['deleted'])==1
   # Translation updates must appear in the real rendered website, with sanitized HTML.
   lid=db('SELECT id FROM page_lang WHERE row_id=1 AND lang_id=1')[0]['id']
   js(admin,'PUT','/panel/api/lang',{'lang_table':'page_lang','id':lid,'title':'Changed from panel','content':'<p>Allowed</p><script>alert(1)</script>'},token=token)
   html=request(guest,'GET','/');assert 'Changed from panel' in html
   lid=db('SELECT id FROM data_lang WHERE row_id=1 AND lang_id=1')[0]['id']
   js(admin,'PUT','/panel/api/lang',{'lang_table':'data_lang','id':lid,'content':'<p>Safe project</p><script>alert(1)</script>'},token=token)
   html=request(guest,'GET','/projects/atolye');assert '<p>Safe project</p>' in html and '<script>alert(1)</script>' not in html
   js(admin,'PUT','/panel/api/cats/999',{'template_id':'999'},token=token,status=422)
   html=request(guest,'GET','/contact');contact_token=csrf(html)
   request(guest,'POST','/contact',{'name':'Test visitor','email':'visitor@example.test','message':'Synthetic contact message','csrf_token':'wrong'},status=403)
   request(guest,'POST','/contact',{'name':'Test','email':'bad','message':'short','csrf_token':contact_token},status=422)
   fields={'name':'Demo visitor','email':'visitor@example.test','phone':'','message':'<b>Literal message</b> saved in SQLite.','website':'','csrf_token':contact_token}
   result=request(guest,'POST','/contact',fields);assert 'Mesajınız kaydedildi' in result
   html=request(admin,'GET','/panel/messages');assert '&lt;b&gt;Literal message&lt;/b&gt;' in html
   for _ in range(4):request(guest,'POST','/contact',fields)
   request(guest,'POST','/contact',fields,status=429)
   assert int(db('SELECT count(*) AS c FROM datas WHERE type_id=6')[0]['c'])==5
   editor=client()
   db("INSERT INTO users(name,email,password,type_id,is_active,deleted) SELECT 'Fixture editor','editor@example.test',password,2,1,0 FROM users WHERE id=1")
   editor_login=request(editor,'GET','/panel/');editor_token=csrf(editor_login)
   editor_home=request(editor,'POST','/panel/',{'email':'editor@example.test','password':'PersonalDemo!2026','csrf_token':editor_token})
   assert 'Fixture editor' in editor_home
   js(editor,'GET','/panel/api/datas')
   js(editor,'GET','/panel/api/users',status=403)
   js(editor,'GET','/panel/api/templates',status=403)
   # Logout stays a CSRF protected POST and invalidates API access.
   request(admin,'GET','/panel/logout',status=403)
   request(admin,'POST','/panel/logout',{'csrf_token':token})
   js(admin,'GET','/panel/api/pages',status=401)
   print('PASS:',count,'real HTTP requests; '+DRIVER+' install, panel login/list/forms/CRUD/translations, public rendering, SRI, contact, CSRF, rate limit and private-file denial')
  finally:
   server.terminate();server.wait(timeout=10)
  text=(tmp/'server.log').read_text(errors='replace')
  assert not re.search(r'PHP (?:Fatal|Warning|Deprecated)|CMS database operation failed',text),scrub(text[-2000:])
print('PASS: disposable '+DRIVER+' database, sessions and test server removed; demo content unchanged')

# The same suite checks the reusable starter; no duplicate fixture or live-demo writes.
starter_checks_before=count
with tempfile.TemporaryDirectory(prefix='cms-starter-test-') as tmp, scratch_db() as scratch:
 tmp=Path(tmp);target=tmp/'new-site';email='admin@starter.test';password='FixtureStarter!2026'
 source_db_hash=hashlib.sha256((ROOT/'storage/personal.sqlite').read_bytes()).hexdigest()
 existing=tmp/'existing';existing.mkdir();(existing/'keep.txt').write_text('preserve')
 result=subprocess.run([PHP,str(ROOT/'tools/create-site.php'),'--target='+str(existing)],cwd=ROOT,capture_output=True)
 assert result.returncode==1 and (existing/'keep.txt').read_text()=='preserve'
 result=subprocess.run([PHP,str(ROOT/'tools/create-site.php'),'--target='+str(ROOT/'forbidden-nested-site')],cwd=ROOT,capture_output=True)
 assert result.returncode==1 and not (ROOT/'forbidden-nested-site').exists()
 create=subprocess.run([PHP,str(ROOT/'tools/create-site.php'),'--target='+str(target),'--name=Yeni Firma & Ortakları'],cwd=ROOT,capture_output=True)
 assert create.returncode==0,create.stderr.decode(errors='replace')
 for forbidden in ['.git','storage/personal.sqlite','storage/site.sqlite','config/config.runtime.php','assets/profile.png','database/seed.php','tools/seed-media.php','templates/pages','app/contact.php','panel/content-messages.php']:
  assert not (target/forbidden).exists(),forbidden
 assert not list((target/'uploads').rglob('*.png'))
 for required in ['database/schema.php','database/seed-base.php','panel/include/db_dialect.php','panel/include/db_install.php']:
  assert (target/required).is_file(),required
 assert "'starter'" in (target/'config/site.php').read_text(encoding='utf-8')
 assert 'PersonalDemo!2026' not in (target/'database/seed-base.php').read_text(encoding='utf-8')
 before=(target/'config/site.php').read_bytes()
 repeat=subprocess.run([PHP,str(ROOT/'tools/create-site.php'),'--target='+str(target)],cwd=ROOT,capture_output=True)
 assert repeat.returncode==1 and before==(target/'config/site.php').read_bytes()
 env=db_env({key:value for key,value in os.environ.items() if not key.startswith(('PANEL_','PERSONAL_'))},target/'storage/site.sqlite',scratch);DB_ENV=env
 def not_installed():return not (target/'storage/site.sqlite').exists() if DRIVER=='sqlite' else subprocess.run([PHP,str(ROOT/'tests/db-query.php'),'--table-exists=users'],cwd=ROOT,env=env,capture_output=True,text=True).stdout.strip()=='false'
 verify=subprocess.run([PHP,str(target/'tools/verify.php')],cwd=target,env=env,capture_output=True)
 assert verify.returncode==1 and not_installed()
 missing=subprocess.run([PHP,str(target/'tools/install.php'),'--admin-email='+email],cwd=target,env=env,capture_output=True)
 assert missing.returncode==1 and not_installed()
 env['PANEL_ADMIN_PASSWORD']=password
 install=subprocess.run([PHP,str(target/'tools/install.php'),'--admin-email='+email],cwd=target,env=env,capture_output=True)
 assert install.returncode==0,scrub(install.stderr.decode(errors='replace'))
 assert password.encode() not in install.stdout+install.stderr
 assert int(db('SELECT count(*) AS c FROM users')[0]['c'])==1
 assert db('SELECT email FROM users')[0]['email']==email
 assert int(db('SELECT count(*) AS c FROM datas')[0]['c'])==0
 assert int(db('SELECT count(*) AS c FROM pages')[0]['c'])==1
 assert int(db('SELECT count(*) AS c FROM logs')[0]['c'])==0
 exists=subprocess.run([PHP,str(ROOT/'tests/db-query.php'),'--table-exists=contact_limits'],cwd=ROOT,env=env,capture_output=True,text=True)
 assert exists.returncode==0 and exists.stdout.strip()=='true',exists.stderr
 assert not list((target/'uploads/files').rglob('*'))
 fingerprint=snapshot(target/'storage/site.sqlite')
 refuse=subprocess.run([PHP,str(target/'tools/install.php'),'--admin-email='+email],cwd=target,env=env,capture_output=True)
 assert refuse.returncode==1 and fingerprint==snapshot(target/'storage/site.sqlite')
 verify=subprocess.run([PHP,str(target/'tools/verify.php')],cwd=target,env=env,capture_output=True)
 assert verify.returncode==0,scrub((verify.stdout+verify.stderr).decode(errors='replace'))
 assert b'0 official theme assets' in verify.stdout
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 base='http://127.0.0.1:'+str(port)
 with open(tmp/'starter-server.log','wb') as log:
  server=subprocess.Popen([PHP,'-d','display_errors=0','-d','error_log='+str(tmp/'starter-errors.log'),'-S','127.0.0.1:'+str(port),'-t',str(target),str(target/'router.php')],cwd=target,env=env,stdout=log,stderr=log)
  try:
   for attempt in range(60):
    try:urllib.request.urlopen(base,timeout=1).close();break
    except OSError:time.sleep(.1)
   else:raise AssertionError('Starter server failed to start')
   guest=client();admin=client()
   html=request(guest,'GET','/');assert 'Yeni Firma &amp; Ortakları' in html and 'Deniz' not in html
   assert 'assets/profile.png' not in html
   for url,sri in re.findall(r'href="([^\"]+)" integrity="(sha384-[^\"]+)"',html):
    asset=target/url.split('?')[0].lstrip('/');assert sri=='sha384-'+base64.b64encode(hashlib.sha384(asset.read_bytes()).digest()).decode()
   for path in ['/storage/site.sqlite','/database/seed-base.php','/database/schema.php','/config/site.php','/starter/site/index.php','/starter/site/css/site.css','/tools/create-site.php']:
    request(guest,'GET',path,status=403)
   js(guest,'GET','/panel/api/pages',status=401)
   login=request(admin,'GET','/panel/');token=csrf(login)
   home=request(admin,'POST','/panel/',{'email':email,'password':password,'csrf_token':token});token=csrf(home)
   assert 'Yeni Firma &amp; Ortakları' in home and 'Personal demo' not in home and 'İletişim mesajları' not in home
   for path in ['/panel/pages','/panel/pages/1','/panel/pages/1/tr','/panel/datas','/panel/datas/new','/panel/variables','/panel/menus']:
    request(admin,'GET',path)
   assert js(admin,'GET','/panel/api/datas')['itemsCount']==0
   page=js(admin,'POST','/panel/api/pages',{'name':'Hizmetler','slug':'hizmetler','type_id':'1'},token=token)['id']
   js(admin,'POST','/panel/api/lang',{'lang_table':'page_lang','row_id':page,'lang_id':'1','title':'Yeni hizmet sayfası','content':'<p>Starter body</p><script>alert(1)</script>'},token=token)
   html=request(guest,'GET','/hizmetler');assert 'Yeni hizmet sayfası' in html and '<p>Starter body</p>' in html and '<script>alert(1)</script>' not in html
   js(admin,'POST','/panel/api/menus',{'name':'Hizmetler','type_id':'1','link':'/hizmetler','page_id':page},token=token)
   assert 'href="/hizmetler"' in request(guest,'GET','/')
   request(admin,'POST','/panel/logout',{'csrf_token':token})
   js(admin,'GET','/panel/api/pages',status=401)
  finally:
   server.terminate();server.wait(timeout=10)
  if (tmp/'starter-errors.log').exists():
   assert (tmp/'starter-errors.log').stat().st_size==0, (tmp/'starter-errors.log').read_text(errors='replace')[-2200:]
  log_text=(tmp/'starter-server.log').read_text(errors='replace')
  assert not re.search(r'PHP (?:Fatal|Warning|Deprecated)|CMS database operation failed',log_text),scrub(log_text[-2000:])
 assert source_db_hash==hashlib.sha256((ROOT/'storage/personal.sqlite').read_bytes()).hexdigest()
print('PASS:',count-starter_checks_before,'starter HTTP requests plus clean export/install/refusal/vendor checks; Personal DB unchanged')
