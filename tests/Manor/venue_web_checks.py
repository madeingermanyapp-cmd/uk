#!/usr/bin/env python3
"""Local-only Admin venue forms and public nightly-mode acceptance."""
import os,pathlib,re,uuid,urllib.parse
from client import Client
base=os.environ.get('MANOR_TEST_URL','http://127.0.0.1:18780')
if urllib.parse.urlsplit(base).hostname not in ('localhost','127.0.0.1'):raise SystemExit('Local-only verification')
root=pathlib.Path(__file__).resolve().parents[2]
cfg=dict(x.split('=',1) for x in pathlib.Path(os.environ.get('MANOR_TEST_CONFIG',str(root/'.local/native.env'))).read_text().splitlines() if '=' in x)
a=Client(base);c=Client(base);count=0;created=[];request_id=None;block_id=None
suffix=uuid.uuid4().hex[:8]
def check(ok,label):
 global count
 if not ok:raise AssertionError(label)
 count+=1;print('PASS',label)
s,h,*_=a.req('/admin/login?lang=en');s,h,*_=a.req('/admin/login',{'_csrf_token':a.token(h),'email':cfg['ADMIN_EMAIL'],'password':cfg['ADMIN_PASSWORD']});check(s==200,'operator login')
s,h,*_=a.req('/admin/manor/settings/features?lang=en');csrf=a.token(h);flags={m.group(1):'1' for m in re.finditer(r'<input[^>]+name="(flag_[^"]+)"[^>]*checked',h)}
def ids(h):return set(re.findall(r'name="unit_id" value="([A-Z0-9]{26})"',h))
def venue(name,parent,mode,modes,price):
 s,h,*_=a.req('/admin/manor/settings/venues');before=ids(h)
 data={'_csrf_token':csrf,'action':'venue','name':name,'parent_id':parent,'mode':mode,'enabled':'1','description':'Synthetic local QA venue','capacity':'8','min_minutes':'60','max_minutes':'43200','duration_modes[]':modes,'price':price,'hourly_rate':'40.00','daily_rate':'250.00','slot_minutes':'60','min_stay_nights':'1','max_stay_nights':'30','check_in':'15:00','check_out':'11:00','pricing_mode':'PRICE_ON_REQUEST','inherit_documents':'1'}
 s,h,*_=a.req('/admin/manor/settings/venues',data);check(s==200 and name in h,'normal Admin venue form creates '+mode)
 new=ids(h)-before;check(len(new)==1,'created venue receives one persistent identity');uid=new.pop();data['unit_id']=uid;created.append(data);return uid
try:
 s,h,*_=a.req('/admin/manor/settings/features',{'_csrf_token':csrf,'action':'features',**flags,'flag_hotel_mode':'1'});check(s==200,'Admin enables accommodation mode')
 prop=venue('Synthetic property '+suffix,'','ENTIRE_PROPERTY',['hour','day','night'],'400.00')
 suite=venue('Synthetic suite '+suffix,prop,'ACCOMMODATION_UNIT',['night'],'150.00')
 s,h,*_=c.req('/manor/enquire?unit='+suite+'&lang=en');check(s==200 and 'Synthetic suite '+suffix in h and 'data-modes="night"' in h,'public selector exposes enabled nightly accommodation')
 form={'_csrf_token':c.token(h),'consent_version':re.search(r'name="consent_version" value="([^"]+)"',h).group(1),'unit_id':suite,'duration_mode':'night','check_in_date':'2027-10-01','check_out_date':'2027-10-03','guests':'2','name':'Synthetic nightly HTTP','email':suffix+'@wonham.test','details':'Synthetic local-only nightly enquiry','consent':'1'}
 s,h,u,_,_=c.req('/manor/enquire',form);check(s==200 and '/manor/request/' in u and '15:00:00' in h and '11:00:00' in h,'customer creates a two-night enquiry through HTTP')
 request_id=re.search(r'name="id" value="([A-Z0-9]{26})"',h).group(1)
 customer_csrf=c.token(h);portal=urllib.parse.urlsplit(u).path
 s,h,*_=a.req('/admin/manor');check(s==200,'availability dashboard renders');before=set(re.findall(r'name="block_id" value="([A-Z0-9]{26})"',h))
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'action':'block','unit_id':suite,'start':'2027-10-03T00:00','end':'2027-10-06T00:00'});check(s==200,'Admin can create multi-day midnight maintenance outside guest rental rules')
 added=set(re.findall(r'name="block_id" value="([A-Z0-9]{26})"',h))-before;check(len(added)==1 and 'Synthetic suite '+suffix in h,'maintenance block identifies its physical resource');block_id=added.pop()
 s,h,*_=c.req(portal,{'_csrf_token':customer_csrf,'action':'submit'});check(s==200,'nightly request submits for review')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':request_id,'action':'transition','status':'under_review'});check(s==200,'nightly request enters management review')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':request_id,'action':'hold'});check(s==422,'maintenance closure prevents overlapping nightly hold')
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'action':'unblock','block_id':block_id});check(s==200,'Admin releases the maintenance block');block_id=None
 s,h,*_=a.req('/admin/manor',{'_csrf_token':csrf,'id':request_id,'action':'hold'});check(s==200,'nightly hold succeeds after maintenance is released')

 parent=created[0];parent['enabled']='';s,h,*_=a.req('/admin/manor/settings/venues',parent);check(s==200,'Admin deactivates the entire property')
 s,h,*_=c.req('/manor/enquire',form);check(s==422,'disabled parent prevents booking an enabled child on the backend')
 s,h,*_=c.req('/manor/enquire');check('Synthetic suite '+suffix not in h,'disabled parent removes child from public choices')
finally:
 if block_id:a.req('/admin/manor',{'_csrf_token':csrf,'action':'unblock','block_id':block_id})
 if request_id:a.req('/admin/manor',{'_csrf_token':csrf,'id':request_id,'action':'transition','status':'cancelled'})
 for data in reversed(created):data['enabled']='';a.req('/admin/manor/settings/venues',data)
 a.req('/admin/manor/settings/features',{'_csrf_token':csrf,'action':'features',**flags})
print(count,'Admin venue HTTP assertions passed.')
