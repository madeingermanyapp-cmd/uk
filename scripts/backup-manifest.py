#!/usr/bin/env python3
"""Integrity inventory for private backup artifacts; not a replacement for encryption."""
import hashlib,json,pathlib,sys
mode,directory=sys.argv[1:];root=pathlib.Path(directory).resolve();manifest=root/'manifest.json'
def digest(p):
 h=hashlib.sha256()
 with p.open('rb') as stream:
  for chunk in iter(lambda:stream.read(1024*1024),b''):h.update(chunk)
 return h.hexdigest()
files={}
for p in sorted(root.rglob('*')):
 if p.is_symlink():raise SystemExit('Backup symlinks are not allowed')
 if p.is_file() and p!=manifest:files[p.relative_to(root).as_posix()]=digest(p)
if mode=='write':
 manifest.write_text(json.dumps({'sha256':files},indent=2)+'\n');manifest.chmod(0o600)
elif mode=='verify':
 expected=json.loads(manifest.read_text())['sha256']
 if expected!=files:raise SystemExit('Backup integrity verification failed; no restoration performed')
 print('Backup integrity verified.')
else:raise SystemExit('Use write or verify')
