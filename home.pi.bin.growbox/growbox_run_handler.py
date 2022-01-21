#############################################
# Log Sensor Value to SQL Database V1
#############################################

# Debug (0=OFF, 1=ON)
_DEBUG = 1

import os

import sqlite3

import config

import datetime

import growbox

now = datetime.datetime.now()

################################################	
# Get Config
################################################
growbox_db = growbox.db()
growbox_db.connect()

grwconfig = growbox_db.get_config()

if (grwconfig["RunHandler"]==1):
	growbox_db.set_config("RunHandler", 0)

growbox_db.disconnect()

if (grwconfig["RunHandler"]==1) or ((now.minute % 10) == 0):
	os.system('sudo python3 /home/pi/bin/growbox/growbox_handler.py >> /var/log/growbox.log 2>&1')
	
	if _DEBUG == 1:
		now2 = datetime.datetime.now()
		delta = now2 - now
		print(now.strftime("%Y/%m/%d, %H:%M:%S"), delta.total_seconds(), "RunHandler:", grwconfig["RunHandler"])

