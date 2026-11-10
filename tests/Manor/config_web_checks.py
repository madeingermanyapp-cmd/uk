#!/usr/bin/env python3
import os,pathlib,re,struct,zlib,uuid,subprocess,atexit,json,urllib.parse
from client import Client
base=os.environ.get('MANOR_TEST_URL','http://127.0.0.1:18780')
if not base.startswith(('http://127.0.0.1:','http://localhost:')):raise SystemExit('Local-only verification')
root=pathlib.Path(__file__).resolve().parents[2]
config=dict(line.split('=',1) for line in pathlib.Path(os.environ.get('MANOR_TEST_CONFIG',str(root/'.local/native.env'))).read_text().splitlines() if '=' in line)
# Own a recoverable configuration snapshot. No production configuration is targeted.
fixture=json.loads(os.environ['MANOR_FIXTURE_COMMAND']) if os.environ.get('MANOR_FIXTURE_COMMAND') else [os.environ.get('MANOR_TEST_PHP','php'),str(root/'tests/Manor/config_fixture.php')]
identity=subprocess.check_output(fixture+['identity'],cwd=root).decode().strip()
expected=urllib.parse.urlsplit(base);actual=urllib.parse.urlsplit(identity)
if actual.hostname not in ('localhost','127.0.0.1') or actual.port!=expected.port:raise SystemExit('Fixture database does not identify the selected local preview URL')
subprocess.run(fixture+['save'],check=True,cwd=root)
atexit.register(lambda:subprocess.run(fixture+['restore'],check=True,cwd=root))
admin=Client(base);public=Client(base);count=0
def check(ok,name):
 global count
 if not ok:raise AssertionError(name)
 count+=1;print('PASS',name)
s,html,_,_,_=admin.req('/admin/login');s,_,_,_,_=admin.req('/admin/login',{'_csrf_token':admin.token(html),'email':config['ADMIN_EMAIL'],'password':config['ADMIN_PASSWORD']})
for section in ['account','general','features','content','pages','media','venues','documents','services','payments','email','notifications','readiness','setup']:
 s,html,_,_,_=admin.req('/admin/manor/settings/'+section)
 check(s==200 and '<h1>' in html,'configuration screen '+section+' renders')
 check(not re.search(r'manor\.(settings_|content_|feature_)',html),'configuration labels resolve '+section)
s,html,_,_,_=public.req('/?lang=ar');check(s==200 and 'dir="ltr"' in html and 'class="language-switch"' not in html,'English-only default rejects an unenabled Arabic query')
s,html,_,_,_=admin.req('/admin/manor/settings/general?lang=en')
# Enable Arabic explicitly through Admin for this test, then restore English only.
from html.parser import HTMLParser
class Fields(HTMLParser):
 def __init__(self):super().__init__();self.fields={}
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='input' and a.get('type')!='checkbox' and a.get('name'):self.fields[a['name']]=a.get('value','')
f=Fields();f.feed(html);general={**f.fields,'action':'general','currency':'GBP','public_url':base,'languages[]':['ar']}
s,html,_,_,_=admin.req('/admin/manor/settings/general',general);check(s==200,'administrator explicitly enables Arabic')
s,html,_,_,_=public.req('/?lang=ar');check(s==200 and 'dir="rtl"' in html and 'اكتشف' in html,'Arabic homepage uses translated content and RTL')
s,html,_,_,_=public.req('/manor/enquire?lang=ar');check(s==200 and 'البريد الإلكتروني' in html and 'طلب' in html,'Arabic enquiry fields render')
s,html,_,_,_=admin.req('/admin/manor/settings/general?lang=ar');check(s==200 and 'الإعدادات العامة والهوية' in html and 'dir="rtl"' in html,'Arabic configuration screen renders')
s,html,_,_,_=public.req('/manor/property.jpg');check(s==404,'unapproved reference photograph is absent from public assets')
s,html,_,_,_=public.req('/admin/manor/reference-media');check('/admin/login' in public.opener.open(base+'/admin/manor/reference-media').geturl(),'reference photograph requires authentication')
class ContentForm(HTMLParser):
 def __init__(self):super().__init__();self.fields={};self.active=False;self.done=False;self.textarea=None;self.value=''
 def add(self,name,value):
  if name.endswith('[]'):self.fields.setdefault(name,[]).append(value)
  else:self.fields[name]=value
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='form' and not self.done:self.active=True
  if not self.active:return
  if tag=='input' and a.get('name') and (a.get('type')!='checkbox' or 'checked' in a):self.add(a['name'],a.get('value',''))
  if tag=='textarea':self.textarea=a.get('name');self.value=''
 def handle_data(self,data):
  if self.textarea:self.value+=data
 def handle_endtag(self,tag):
  if tag=='textarea' and self.textarea:self.add(self.textarea,self.value);self.textarea=None
  if tag=='form' and self.active:self.active=False;self.done=True
s,html,_,_,_=admin.req('/admin/manor/settings/content?lang=en');form=ContentForm();form.feed(html)
form.fields['content[en][hero_title]']='Synthetic CMS draft '+uuid.uuid4().hex[:8]
form.fields['nav_labels[]']=['Contact','Our spaces','The manor'];form.fields['nav_urls[]']=['/#contact','/#venues','/#introduction']
s,html,_,_,_=admin.req('/admin/manor/settings/content',form.fields);check(s==200,'normal CMS content form saves a draft')
s,preview,_,_,_=admin.req('/admin/manor/preview');check(form.fields['content[en][hero_title]'] in preview,'operator preview renders the saved draft')
check(preview.index('>Contact</a>')<preview.index('>Our spaces</a>')<preview.index('>The manor</a>'),'Admin navigation ordering is preserved')
s,live,_,_,_=public.req('/?lang=en');check(form.fields['content[en][hero_title]'] not in live,'unpublished CMS draft does not alter public content')
s,html,_,_,_=admin.req('/admin/manor/settings/media?lang=en');csrf=admin.token(html)
def chunk(t,d):return struct.pack('!I',len(d))+t+d+struct.pack('!I',zlib.crc32(t+d)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x00\x00'))+chunk(b'IEND',b'')
s,html,_,_,_=admin.req('/admin/manor/settings/media',{'_csrf_token':csrf,'action':'media_upload','section':'qa-test','alt':'Synthetic media approval test'},('media','qa-approval.png','image/png',png));check(s==200,'media upload creates a protected draft')
asset=re.search(r'name="media_id" value="([a-f0-9]{48})"',html).group(1)
s,_,_,_,_=public.req('/manor/media/'+asset+'.png');check(s==404,'unapproved media cannot be downloaded publicly')
s,_,_,_,_=admin.req('/manor/media/'+asset+'.png');check(s==200,'operator can preview protected draft media')
s,html,_,_,_=admin.req('/admin/manor/settings/media',{'_csrf_token':csrf,'action':'media_review','media_id':asset,'alt':'Synthetic media approval test','rights':'Synthetic pixel created by automated test','approved':'1'});check(s==200,'media rights approval records successfully')
s,_,_,_,_=public.req('/manor/media/'+asset+'.png');check(s==404,'approval alone does not publish media')
s,html,_,_,_=admin.req('/admin/manor/settings/content');version=re.search(r'name="version_id" value="([A-Z0-9]{26})"',html).group(1)
s,_,_,_,_=admin.req('/admin/manor/settings/content',{'_csrf_token':admin.token(html),'action':'publish','version_id':version});check(s==422,'content publication requires explicit approval')
s,html,_,_,_=admin.req('/admin/manor/settings/content',{'_csrf_token':csrf,'action':'publish','version_id':version,'approved':'1'});check(s==200,'approved content draft publishes')
s,_,_,_,_=public.req('/manor/media/'+asset+'.png');check(s==200,'published approved media becomes accessible')
s,html,_,_,_=admin.req('/admin/manor/settings/media',{'_csrf_token':csrf,'action':'media_remove','media_id':asset});check(s==200,'media removal succeeds without deleting history')
s,_,_,_,_=public.req('/manor/media/'+asset+'.png');check(s==404,'removed media immediately stops public delivery')
s,html,_,_,_=admin.req('/admin/manor/settings/email');check('local-test-secret-not-a-production-credential' not in html,'saved SMTP password is never rendered')
s,html,_,_,_=public.req('/manor/recover?lang=en');check(s==200 and 'Recover secure access' in html,'customer recovery form works')
general.pop('languages[]',None)
s,html,_,_,_=admin.req('/admin/manor/settings/general',general);check(s==200,'administrator disables Arabic')
s,html,_,_,_=public.req('/?lang=ar');check('dir=\"ltr\"' in html and 'class=\"language-switch\"' not in html,'disabled language disappears and cannot be selected')
s,html,_,_,_=admin.req('/admin/manor/settings/account?lang=en')
s,_,_,_,_=admin.req('/admin/manor/settings/account',{'_csrf_token':admin.token(html),'action':'account','admin_name':'Synthetic QA','admin_email':config['ADMIN_EMAIL'],'current_password':'incorrect-synthetic-password','admin_password':''});check(s==422,'account changes require the current password')
s,html,_,_,_=admin.req('/admin/manor/settings/setup');f=Fields();f.feed(html)
setup={**f.fields,'action':'setup','public_url':base,'flag_entire_property':'1','flag_partial_venue':'1','admin_password':''}
s,html,url,_,_=admin.req('/admin/manor/settings/setup',setup);check(s==200 and url.endswith('/settings/readiness'),'first-run setup completes and opens readiness')
s,html,url,_,_=admin.req('/admin/manor/settings/setup');check(url.endswith('/settings/readiness'),'completed first-run wizard is locked')
s,_,_,_,_=admin.req('/admin/manor/settings/setup',setup);check(s==422,'completed setup cannot be submitted again')
s,_,_,_,_=admin.req('/admin/manor',{'_csrf_token':csrf,'action':'settings','settings':'{\"setup_complete\":false}'});check(s==422,'removed legacy settings write cannot reopen the wizard')
s,_,url,_,_=admin.req('/admin/settings');check(url.endswith('/admin/manor/settings/general'),'legacy settings screen redirects to the controlled dashboard')
s,_,_,_,_=admin.req('/admin/settings',{'_csrf_token':csrf});check(s==403,'legacy system settings writes are blocked')
print(count,'configuration HTTP assertions passed.')
