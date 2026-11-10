#!/usr/bin/env python3
"""Synthetic local-only complete commercial and guest workflow; cancels its booking."""
import os,pathlib,re,uuid,urllib.parse
from client import Client
base=os.environ.get('MANOR_TEST_URL','http://127.0.0.1:18780')
if urllib.parse.urlparse(base).hostname not in ('localhost','127.0.0.1'):raise SystemExit('Local-only verification')
root=pathlib.Path(__file__).resolve().parents[2]
config=dict(line.split('=',1) for line in pathlib.Path(os.environ.get('MANOR_TEST_CONFIG',str(root/'.local/native.env'))).read_text().splitlines() if '=' in line)
a=Client(base);c=Client(base);outsider=Client(base);count=0
suffix=uuid.uuid4().hex[:12]
def check(ok,label):
 global count
 if not ok:raise AssertionError(label)
 count+=1;print('PASS',label)
def get(client,path):
 s,h,u,headers,b=client.req(path);check(s==200,'GET '+path.split('?')[0]);return h
login=get(a,'/admin/login?lang=en');s,h,*_=a.req('/admin/login',{'_csrf_token':a.token(login),'email':config['ADMIN_EMAIL'],'password':config['ADMIN_PASSWORD']});check(s==200,'operator login')
h=get(a,'/admin/manor/settings/features?lang=en');csrf=a.token(h)
# Preserve every feature flag and enable only invitation/attendance for this fixture.
flags={m.group(1):'1' for m in re.finditer(r'<input[^>]+name="(flag_[^"]+)"[^>]*checked',h)}
try:
 s,h,*_=a.req('/admin/manor/settings/features',{'_csrf_token':csrf,'action':'features',**flags,'flag_invitations':'1','flag_qr_attendance':'1'});check(s==200,'enable invitation and QR modules')
 h=get(c,'/manor/enquire?lang=en');unit=re.search(r'<option value="([A-Z0-9]{26})" data-modes="[^"]*hour',h).group(1)
 s,h,u,_,_=c.req('/manor/enquire',{'_csrf_token':c.token(h),'consent_version':re.search(r'name="consent_version" value="([^"]+)"',h).group(1),'unit_id':unit,'duration_mode':'hour','start':'2027-09-15T10:00','end':'2027-09-15T12:00','guests':'4','name':'Synthetic full workflow','email':suffix+'@wonham.test','details':'Automated local-only acceptance fixture','consent':'1'})
 check(s==200 and '/manor/request/' in u,'create enquiry and secure portal');portal=urllib.parse.urlsplit(u).path
 ref=re.search(r'name="id" value="([A-Z0-9]{26})"',h).group(1)
 s,h,*_=c.req(portal,{'_csrf_token':c.token(h),'action':'submit'});check(s==200 and 'Submitted' in h,'customer submits enquiry')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'transition','status':'under_review'});check(s==200,'management reviews enquiry')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'correspondence','body':'Synthetic management message '+suffix});check(s==200,'management sends private message')
 h=get(c,portal);check(suffix in h,'customer receives private message')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'quote_form','expires':'2027-09-01T12:00','item_name[]':['Synthetic base','Synthetic deposit'],'item_description[]':['Local QA','Refundable test deposit'],'item_amount[]':['100.00','20.00'],'item_quantity[]':['1','1'],'item_tax[]':['0','0'],'item_mandatory[0]':'1','item_mandatory[1]':'1','item_base[0]':'1','item_refundable[1]':'1','item_visible[0]':'1','item_visible[1]':'1','terms':'Synthetic quotation for local QA only'})
 check(s==200 and 'quotation' in h.lower(),'issue quotation through normal form')
 h=get(c,portal);quote=re.search(r'/manor/quotation/([A-Z0-9]{26})/pdf',h).group(1)
 for lang in ['en']:
  s,_,_,headers,body=c.req('/manor/quotation/'+quote+'/pdf?token='+portal.rsplit('/',1)[1]+'&lang='+lang)
  check(s==200 and body.startswith(b'%PDF-') and len(body)>5000,'download '+lang+' PDF quotation')
 s,*_=outsider.req('/manor/quotation/'+quote+'/pdf');check(s==404,'quotation PDF rejects unrelated session')
 h=get(c,portal+'?lang=en');s,h,*_=c.req(portal,{'_csrf_token':c.token(h),'action':'accept','quote_id':quote});check(s==200,'customer accepts immutable quotation')
 for state in ['awaiting_payment','payment_verification']:
  s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'transition','status':state});check(s==200,'transition '+state)
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'payment','kind':'payment','amount':'120.00','reference':'SYNTHETIC-'+suffix,'status':'verified'});check(s==200,'record verified synthetic receipt')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'transition','status':'confirmed'});check(s==200 and 'Confirmed' in h,'confirm booking after funds and inventory checks')
 h=get(a,'/admin/manor/guests?request_id='+ref);check(not re.search(r'>manor\.[^<]+<',h),'guest management translation keys resolve');template=re.search(r'<option value="([A-Z0-9]{26})"',h[h.index('name="template_id"'):]).group(1)
 s,h,*_=a.req('/admin/manor/guests',{'_csrf_token':csrf,'request_id':ref,'action':'invite','template_id':template,'guest_name':'Synthetic invited guest','event_name':'Synthetic QA event','host_name':'QA host'});check(s==200,'create guest invitation')
 invitation=re.search(r'https?[^" ]+/manor/invitation/([a-f0-9]{64})',h).group(1);invite='/manor/invitation/'+invitation
 h=get(c,invite);check('Synthetic invited guest' in h,'private invitation renders template and guest')
 s,h,*_=c.req(invite,{'_csrf_token':c.token(h),'rsvp':'accepted'});check(s==200 and 'Attending' in h,'guest RSVP accepted')
 s,_,_,_,qr=c.req(invite+'/qr');check(s==200 and qr.startswith(b'\x89PNG'),'real QR PNG downloaded')
 h=get(a,'/admin/manor/scanner');s,h,u,_,_=a.req('/admin/manor/scanner',{'_csrf_token':a.token(h)},('photo','qr.png','image/png',qr));check(s==200 and '/manor/check-in/' in u,'staff scanner decodes uploaded QR photograph')
 scan=urllib.parse.urlsplit(u).path
 s,h,u,_,_=outsider.req(scan);check('/admin/login' in u,'guest QR does not grant scanner authorization')
 s,h,*_=a.req(scan,{'_csrf_token':csrf});check(s==200 and 'Attendance recorded.' in h,'staff confirms attendance')
 s,h,*_=a.req(scan,{'_csrf_token':csrf});check(s==200 and 'already checked in' in h.lower(),'duplicate scan detected (HTTP '+str(s)+')')
 s,_,_,headers,body=a.req('/admin/manor/attendance.csv?request_id='+ref);check(s==200 and b'Synthetic invited guest' in body,'attendance report exports real guest data')
finally:
 if 'ref' in globals():a.req('/admin/manor',{'_csrf_token':csrf,'id':ref,'action':'transition','status':'cancelled'})
 a.req('/admin/manor/settings/features',{'_csrf_token':csrf,'action':'features',**flags})
print(count,'complete workflow HTTP assertions passed.')
