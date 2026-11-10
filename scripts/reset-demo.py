#!/usr/bin/env python3
import pathlib, subprocess, sys
root=pathlib.Path(__file__).resolve().parents[1]
if input('Delete ALL local Wonham data? Type DELETE LOCAL DEMO: ')!='DELETE LOCAL DEMO':sys.exit('Cancelled.')
subprocess.run(['docker','compose','--project-name','wonham-local','--env-file',str(root/'.local/compose.env'),'down','--volumes'],cwd=root,check=True)
print('Local-only data removed. Run START_LOCAL again.')
