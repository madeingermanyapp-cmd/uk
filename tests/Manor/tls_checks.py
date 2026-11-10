#!/usr/bin/env python3
# Requires the isolated localhost HTTPS deployment and its exported test CA.
import pathlib,urllib.request,urllib.error,http.cookiejar,re,ssl,json
v=dict(x.split('=',1) for x in pathlib.Path('.local/tls-check.env').read_text().splitlines() if '=' in x)
ctx=ssl.create_default_context(cafile='.local/tls-ca.crt');jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPSHandler(context=ctx),urllib.request.HTTPCookieProcessor(jar));base=v['APP_URL']
r=opener.open(base+'/');h=r.read().decode();assert r.status==200 and 'Discover a setting' in h and 'class="language-switch"' not in h;print('PASS production-mode HTTPS public homepage with verified local CA and English-only default')
r=opener.open(base+'/admin/login');h=r.read().decode();assert any(c.secure for c in jar);print('PASS HTTPS session cookie is Secure')
import urllib.parse
csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',h).group(1)
r=opener.open(base+'/admin/login',urllib.parse.urlencode({'_csrf_token':csrf,'email':v['ADMIN_EMAIL'],'password':v['ADMIN_PASSWORD']}).encode());assert r.status==200
r=opener.open(base+'/admin/manor/settings/readiness');h=r.read().decode();assert 'Current connection is HTTPS.' in h;print('PASS Admin readiness observes actual trusted-proxy HTTPS')
assert 'max-age=31536000' in r.headers.get('Strict-Transport-Security','');print('PASS production HSTS enabled')
try:opener.open(base+'/install');raise AssertionError('Installer remained accessible')
except urllib.error.HTTPError as e:assert e.code==404;print('PASS installer disabled after initialization')
