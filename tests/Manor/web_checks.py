#!/usr/bin/env python3
"""Local-only HTTP checks. Uses generated preview credentials, never production."""
import struct, zlib, base64, http.cookiejar, json, os, pathlib, re, sys, urllib.error, urllib.parse, urllib.request, uuid
base=os.environ.get('MANOR_TEST_URL','http://127.0.0.1:18780')
if urllib.parse.urlparse(base).hostname not in ('127.0.0.1','localhost'):sys.exit('HTTP test requires a local disposable/preview instance.')
root=pathlib.Path(__file__).resolve().parents[2]
config=dict(line.split('=',1) for line in pathlib.Path(os.environ.get('MANOR_TEST_CONFIG',str(root/'.local/native.env'))).read_text().splitlines() if '=' in line)
count=0
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def req(self,path,data=None,multipart=None):
  headers={}
  if multipart:
   boundary='manor'+uuid.uuid4().hex; chunks=[]
   for key,value in data.items():chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
   key,name,mime,content=multipart
   chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
   chunks.append(f'--{boundary}--\r\n'.encode());payload=b''.join(chunks);headers['Content-Type']='multipart/form-data; boundary='+boundary
  else:payload=urllib.parse.urlencode(data).encode() if data is not None else None
  try:r=self.opener.open(urllib.request.Request(base+path,data=payload,headers=headers))
  except urllib.error.HTTPError as e:r=e
  body=r.read();return r.status,body.decode('utf-8',errors='replace'),r.geturl(),r.headers

def check(value,name):
 global count
 if not value:raise AssertionError(name)
 count+=1;print('PASS',name)
def token(html):return re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
anon=Client();admin=Client();other=Client()
s,html,_,_=anon.req('/');check(s==200 and 'Discover a setting for your next occasion.' in html,'public homepage renders')
check('manor.make_an_enquiry' not in html,'translation keys resolve')
s,html,_,_=anon.req('/manor/enquire');check(s==200 and 'Create draft enquiry' in html,'enquiry form renders')
unit=re.search(r'<option value="([A-Z0-9]{26})"',html).group(1)
s,_,_,_=anon.req('/manor/enquire',{'name':'QA'});check(s==403,'CSRF required for enquiry')
# Reset public POST rate budget only through regular limited requests; this suite stays under ten writes.
s,portal,url,_=anon.req('/manor/enquire',{'_csrf_token':token(html),'unit_id':unit,'consent_version':re.search(r'name="consent_version" value="([^"]+)"',html).group(1),'start':'2027-07-01T10:00','end':'2027-07-01T12:00','guests':'2','name':'Synthetic HTTP QA','email':uuid.uuid4().hex+'@wonham.test','details':'Automated local-only HTTP fixture','consent':'1'})
check(s==200 and '/manor/request/' in url and 'Save this private page address' in portal,'draft creates private portal and reference')
path=urllib.parse.urlsplit(url).path
s,_,redirect_url,_=anon.req('/admin/manor');check(s==200 and redirect_url.endswith('/admin/login'),'admin redirects anonymous to login')
s,login,login_url,_=admin.req('/admin/login');check(s==200 and 'password' in login,'admin login renders')
s,dashboard,_,_=admin.req('/admin/login',{'_csrf_token':token(login),'email':config['ADMIN_EMAIL'],'password':config['ADMIN_PASSWORD']})
s,dashboard,_,_=admin.req('/admin/manor');check(s==200 and 'Wonham console.' in dashboard,'authenticated management console renders')
csrf=token(portal)
pdf=b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n'
s,portal,_,_=anon.req(path,{'_csrf_token':csrf,'action':'upload','type':'supporting'},('document','qa.pdf','application/pdf',pdf))
check(s==200 and 'qa.pdf' in portal,'valid private PDF upload accepted')
doc=re.search(r'/manor/document/([A-Z0-9]+)\?token=',portal).group(1)
s,_,_,_=other.req('/manor/document/'+doc);check(s==404,'unrelated session cannot download document')
s,_,_,headers=anon.req('/manor/document/'+doc+'?token='+path.rsplit('/',1)[1]);check(s==200 and headers['Content-Disposition'].startswith('attachment;'),'customer capability permits safe attachment download')
s,_,_,_=admin.req('/manor/document/'+doc);check(s==200,'operator can inspect protected document')
s,_,_,_=anon.req(path,{'_csrf_token':csrf,'action':'upload','type':'supporting'},('document','evil.php','application/pdf',b'<?php echo 1; ?>'));check(s==422,'spoofed document content rejected')
s,_,_,_=anon.req('/storage/private/manor/'+doc);check(s==404,'private storage cannot be fetched from web root')
s,_,_,_=anon.req('/install');check(s==404,'installer closed after initialization')
s,_,_,_=admin.req('/admin/manor',{'_csrf_token':token(dashboard),'action':'media','section':'hero','alt':'QA'},('media','evil.svg','image/svg+xml',b'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));check(s==422,'active SVG media rejected')
def chunk(t,d):return struct.pack('!I',len(d))+t+d+struct.pack('!I',zlib.crc32(t+d)&0xffffffff)
png=b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xff\x00\x00'))+chunk(b'IEND',b'')
s,media_before,_,_=admin.req('/admin/manor/settings/media');media_ids=set(re.findall(r'name=\"media_id\" value=\"([a-f0-9]{48})\"',media_before))
s,_,_,_=admin.req('/admin/manor',{'_csrf_token':token(dashboard),'action':'media','section':'qa-media','alt':'Synthetic QA pixel'},('media','pixel.png','image/png',png));check(s==200,'valid media upload re-encoded into a protected draft')
s,media_after,_,_=admin.req('/admin/manor/settings/media');created_media=set(re.findall(r'name=\"media_id\" value=\"([a-f0-9]{48})\"',media_after))-media_ids
for asset in created_media:
 s,_,_,_=admin.req('/admin/manor/settings/media',{'_csrf_token':token(dashboard),'action':'media_remove','media_id':asset});check(s==200,'synthetic media removed from subsequent publication drafts')
s,_,_,_=anon.req(path,{'_csrf_token':csrf,'action':'submit'});check(s==200,'customer submits request for review')
s,_,_,_=anon.req(path,{'_csrf_token':csrf,'action':'transition','status':'confirmed'});check(s==422,'customer cannot invoke management action')
s,_,_,_=anon.req('/api/agent/v2/public/session',{});check(s==403,'legacy write surface cannot bypass inventory')
request_id=re.search(r'name=\"id\" value=\"([A-Z0-9]{26})\"',portal).group(1)
admin.req('/admin/manor',{'_csrf_token':token(dashboard),'action':'transition','id':request_id,'status':'cancelled'})
print(f'{count} HTTP assertions passed.')
