"""Exercise the real copied panel and public theme against a disposable SQLite DB."""
from pathlib import Path
import os,subprocess,tempfile,socket,time,urllib.request,urllib.error,urllib.parse,http.cookiejar,json,re,sqlite3,hashlib,base64
ROOT=Path(__file__).resolve().parent.parent
PHP=subprocess.check_output(['php','-r','echo PHP_BINARY;'],text=True).strip()
count=0

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

with tempfile.TemporaryDirectory(prefix='personal-sqlite-test-') as tmp:
 tmp=Path(tmp);(tmp/'sessions').mkdir();env=os.environ.copy();env.update(PERSONAL_DB_FILE=str(tmp/'test.sqlite'),PERSONAL_SESSION_PATH=str(tmp/'sessions'),PERSONAL_RATE_FILE=str(tmp/'rate.json'))
 subprocess.run([PHP,str(ROOT/'tools/install.php')],env=env,check=True,capture_output=True)
 before=hashlib.sha256((tmp/'test.sqlite').read_bytes()).hexdigest()
 refuse=subprocess.run([PHP,str(ROOT/'tools/install.php')],env=env,capture_output=True)
 assert refuse.returncode==1 and hashlib.sha256((tmp/'test.sqlite').read_bytes()).hexdigest()==before
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
   for path in ['/storage/personal.sqlite','/database/schema.sql','/database/seed.sql','/config/config.php','/app/bootstrap.php','/templates/pages/home.php','/tools/install.php','/.git/config','/panel/include/sqlite.php','/panel/plugins/util/pass.php','/panel/temp/Untitled-1.js','/panel/plugins/ckeditor/samples/index.html','/panel/plugins/filemanager/../../../storage/personal.sqlite']:
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
   js(admin,'PUT','/panel/api/pages/'+str(rid),{'name':'Updated','is_active':'0'},token=token)
   js(admin,'DELETE','/panel/api/pages/'+str(rid),token=token)
   conn=sqlite3.connect(tmp/'test.sqlite');assert conn.execute('SELECT deleted FROM pages WHERE id=?',(rid,)).fetchone()[0]==1
   # Translation updates must appear in the real rendered website, with sanitized HTML.
   lid=conn.execute('SELECT id FROM page_lang WHERE row_id=1 AND lang_id=1').fetchone()[0]
   js(admin,'PUT','/panel/api/lang',{'lang_table':'page_lang','id':lid,'title':'Changed from panel','content':'<p>Allowed</p><script>alert(1)</script>'},token=token)
   html=request(guest,'GET','/');assert 'Changed from panel' in html
   lid=conn.execute('SELECT id FROM data_lang WHERE row_id=1 AND lang_id=1').fetchone()[0]
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
   assert conn.execute('SELECT count(*) FROM datas WHERE type_id=6').fetchone()[0]==5
   conn.close()
   editor=client()
   conn=sqlite3.connect(tmp/'test.sqlite')
   conn.execute("INSERT INTO users(name,email,password,type_id,is_active,deleted) SELECT 'Fixture editor','editor@example.test',password,2,1,0 FROM users WHERE id=1")
   conn.commit();conn.close()
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
   print('PASS:',count,'real HTTP requests; SQLite install, panel login/list/forms/CRUD/translations, public rendering, SRI, contact, CSRF, rate limit and private-file denial')
  finally:
   server.terminate();server.wait(timeout=10)
  text=(tmp/'server.log').read_text(errors='replace')
  assert not re.search(r'PHP (?:Fatal|Warning|Deprecated)|CMS database operation failed',text),text[-2000:]
print('PASS: disposable SQLite database, sessions and test server removed; demo content unchanged')