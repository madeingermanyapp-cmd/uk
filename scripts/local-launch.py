#!/usr/bin/env python3
"""Portable launcher; creates local-only random credentials once, never resets data."""
import os, pathlib, secrets, shutil, socket, subprocess, sys, webbrowser
root=pathlib.Path(__file__).resolve().parent.parent
os.chdir(root)
if not shutil.which('docker'):
    sys.exit('Docker is required. Install and start Docker Desktop (macOS/Windows) or Docker Engine with Compose (Linux). Then rerun this launcher.')
def run(*args):
    subprocess.run(args,check=True)
try:
    run('docker','info','--format','{{.ServerVersion}}')
    run('docker','compose','version')
    local=root/'.local'; local.mkdir(exist_ok=True)
    config=local/'compose.env'
    if not config.exists():
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
        config.write_text(f'APP_ENV=local\nLOCAL_PORT={port}\nDB_PASSWORD={secrets.token_hex(24)}\nDB_ROOT_PASSWORD={secrets.token_hex(24)}\nADMIN_EMAIL=admin@wonham.test\nADMIN_PASSWORD={secrets.token_urlsafe(24)}\n')
        try: config.chmod(0o600)
        except OSError: pass
    values=dict(line.split('=',1) for line in config.read_text().splitlines() if '=' in line)
    compose=['docker','compose','--project-name','wonham-local','--env-file',str(config)]
    run(*compose,'up','-d','--build','--wait','--wait-timeout','240')
    run(*compose,'exec','-T','--user','www-data','app','php','scripts/manor-init.php')
    url=f'http://localhost:{values["LOCAL_PORT"]}'
    print(f'Website: {url}\nAdmin: {url}/admin/manor\nLocal admin: {values["ADMIN_EMAIL"]}\nPassword is stored privately in {config} (ADMIN_PASSWORD).\nRepeated starts preserve your database.')
    webbrowser.open(url)
except (subprocess.CalledProcessError,OSError) as exc:
    sys.exit(f'Launch failed: {exc}. Check Docker is running, the selected port is free and Docker Compose logs. Existing data has not been reset.')
