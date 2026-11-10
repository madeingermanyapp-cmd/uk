import http.cookiejar, os, re, time, urllib.error, urllib.parse, urllib.request, uuid
class Client:
    def __init__(self,base):
        self.base=base
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def req(self,path,data=None,file=None):
        headers={}
        if file:
            boundary='manor'+uuid.uuid4().hex;parts=[]
            for k,v in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            key,name,mime,body=file;parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+body+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode())
            payload=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
        else:payload=urllib.parse.urlencode(data or {},doseq=True).encode() if data is not None else None
        try:r=self.opener.open(urllib.request.Request(self.base+path,data=payload,headers=headers))
        except urllib.error.HTTPError as e:r=e
        body=r.read()
        if r.status==429 and not getattr(self,'retrying',False):
            self.retrying=True
            try:
                time.sleep(min(60,int(r.headers.get('Retry-After','60'))))
                return self.req(path,data,file)
            finally:self.retrying=False
        return r.status,body.decode('utf-8',errors='replace'),r.geturl(),r.headers,body
    @staticmethod
    def token(html):
        m=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html)
        if not m:raise AssertionError('No CSRF token found')
        return m.group(1)
