
################################################	
# Call every 10 minutes, checks schedule and sets Relay accordingly
# Pump is executed on hour check in case script misses iteration on full hour
# but this will execute the pump is updated right after execution with same time, e.g. change the amount
# maybe set DaysCnt to 1 after update?
# Growbox uses UTC Time, to avoid Winter Summer Time Switch
################################################

import config

import growbox

import os

import signal

import datetime

import math

from time import sleep

from shutil import copyfile

from threading import Thread

from crontab import CronTab

# Define exeint, execute intervall in minutes !!!! Important for Vent and Fan !!!!
exeint = config.ExeInt

# date time
#now = datetime.datetime.now()
#now = datetime.now(timezone.utc)
now_local = datetime.datetime.now()
now = datetime.datetime.utcnow()
dt = now.strftime("%Y-%m-%d %H:%M")
# 2015 5 6 8 53 40
#print (now.year, now.month, now.day, now.hour, now.minute, now.second)

################################################	
# HW Clock, update every hour
# not need currently
################################################
#if (now.minute == 0):
	#Update fake hardware clock
	#os.system("sudo fake-hwclock")

################################################	
# Connect To SQL Server
################################################
growbox_db = growbox.db()
growbox_db.connect()

################################################	
# Get Config
################################################
grwconfig = growbox_db.get_config()

################################################	
# Exit if Main Switch is off
if (grwconfig["Main"] == 0):
	growbox_db.disconnect()
	exit()

################################################
# Calc Days since last reset
# StartDate: year*1000 + day_number of year
start_date = grwconfig['StartDate']
if (start_date == 0):
	start_date = (now.year * 1000) + now.timetuple().tm_yday -1
	growbox_db.set_config("StartDate", start_date)

start_year = math.floor(start_date/1000)
start_days = start_date - (start_year * 1000)
start_d = datetime.date.fromordinal(datetime.date(start_year, 1, 1).toordinal() + start_days)
start_date = datetime.datetime(start_d.year, start_d.month, start_d.day)
start_since_days = (now - start_date).days
	
################################################	
# Check PID
if (grwconfig["PID"] != 0):
	pid = grwconfig["PID"]
	print("PID: Kill Old PID:", pid)
	try:
		os.kill(pid, signal.SIGTERM)
	except:
		print("PID: Cannot kill:", pid)

pid = os.getpid()
growbox_db.set_config("PID", pid)
	
################################################	
# Reboot
if (grwconfig["SetReboot"] == 1):
	reboot = grwconfig["Reboot"]

	growbox_db.set_config("SetReboot", 0)

	my_cron = CronTab(user='root')
	for job in my_cron:
		if job.comment == 'growbox_reboot':
			job.hour.on(reboot)
			#job.minute.on(5)
			my_cron.write()
			
################################################	
# New Day
################################################
newday = None
tt = now.timetuple()
jday = tt.tm_year * 1000 + tt.tm_yday
if (jday != grwconfig['JulienDay']):
	newday = True
	
	growbox_db.set_config("JulienDay", jday)

	# SQL Cleanup, Remove old data
	growbox_db.clean()

################################################	
# Air Sensors
################################################

temperature1 = 0.0
humidity1 = 0.0

# get sensors
air_sensors = growbox_db.get_airsensors()

for v in air_sensors:
	id = v[0]
	if (id <= grwconfig['NumAirSensors']):
		enabled = v[1]
		name = v[2]
		gpio = v[3]
		h_offset = v[4]
		t_offset = v[5]
		if (enabled == 1):
			humidity, temperature = growbox.airsensor_read(config.AirSensorType, gpio, h_offset, t_offset)
			if humidity is None:
				humidity, temperature = 0.0, 0.0
			growbox_db.insert_airsensordata(dt, id, temperature, humidity)
			if (id == 1):
				temperature1 = temperature
				humidity1 = humidity
	

################################################	
# Weight Sensors
################################################
weight_sensors = growbox_db.get_weightsensors()
weight_sensor_data = dict()

for v in weight_sensors:
	id = v[0]
	if (id <= grwconfig['NumWeightSensors']):
		enabled = v[1]
		name = v[2]
		data = v[3]
		clk = v[4]
		cal = v[5]
		offset = v[6]
		if (enabled == 1):
			weight = growbox.get_weight(data, clk, cal, offset)
			growbox_db.insert_weightsensordata(dt, id, weight)
			
			weight_sensor_data[id] = weight

################################################	
# LOG Image before running Sockets when <log_image> == "OFF" else after
################################################
log_image = growbox.log_image(grwconfig['LightOn'], grwconfig['LightOff'], now.hour, now.minute)

cameras = growbox_db.get_cameras()

def take_image():
	# save image text with local time
	#s_info = " " + now_local.strftime("%Y-%m-%d %H:%M, %a, W%W") + ", H: {0:0.1f}%, T: {1:0.1f}C ".format(humidity1,temperature1)
	s_info = " " + now_local.strftime("%Y-%m-%d %H:%M, %a") + ", Day{0}, H:{1:0.1f}%, T:{2:0.1f}C ".format(start_since_days,humidity1,temperature1)

	for v in cameras:
		id = v[0]
		if (id <= grwconfig['NumCameras']):
			enabled = v[1]
			usb_device = v[2]
			hres = v[3]
			vres = v[4]
			rotation = v[5]
			fps = v[6]
			brightness = v[7]
			contrast = v[8]
			awb = v[9]
			#print("Cam:", id, enabled, usb_device, hres, vres, rotation, fps, brightness, contrast, awb)
			if (enabled == 1):
				# capture image
				img_file = '/var/www/growbox/tmp/image-{0}.jpg'.format(id)
				if (usb_device == ''):
					growbox.camera_capture(img_file, hres, vres, rotation, s_info, fps, brightness, contrast, awb)
				else:
					growbox.camera_capture_usb(usb_device, img_file, hres, vres, rotation, s_info, fps, brightness, contrast)


def save_image(log_image):
	if (grwconfig["Light"] != 0) and (log_image != ""):
	
		for v in cameras:
			id = v[0]
			enabled = v[1]
			if (enabled == 1):
				# save image
				src_file = '/var/www/growbox/tmp/image-{0}.jpg'.format(id)
				filename = now.strftime("%Y-%m-%d_%H-%M_{0}.jpg".format(id))
				dest_file = '/var/www/growbox/cam/' + filename
				copyfile(src_file, dest_file)
				growbox_db.insert_image(dt, id, filename)

if (log_image == "OFF"):
	take_image()
	save_image(log_image)
	
################################################	
# Get Sockets
################################################
sockets = growbox_db.get_sockets()
sockets_load = dict()
water_plants = []

################################################	
# Manage Sockets
################################################

for socket in sockets:

	rowid = socket["rowid"]
	
	sockets_load[rowid] = 0
	
	#print(socket)

	# Active
	gpio = socket['GPIO']
	if (socket['Active'] == 1) and (gpio > 0) and (rowid <= grwconfig["NumSockets"]):
	
		name = socket['Name']
		load = socket['Load']
		switch_on = 0
		
		################################################
		# Switch, Timer, Intervall work together
		################################################
		################################################
		# Switch
		################################################
		if ((socket["Switch"] == 1) and (socket["State"] == 1)):
			switch_on = 1

		################################################
		# Timer
		################################################
		if ((socket["Timer"] == 1) and growbox.switch_on(socket['HOn'], socket['HOff'], now.hour)):
			switch_on = 1
		
		################################################
		# Interval, works with timer if activated
		################################################
		if (socket["Interval"] == 1):
			if (socket['PowerCnt'] > 0):
				if (socket['Pause'] > 0):
					socket['PowerCnt'] -= exeint
					if (socket["PowerCnt"] <= 0):
						socket['PowerCnt'] = 0
						growbox_db.set_socket(rowid, "PauseCnt", socket["Pause"])
					growbox_db.set_socket(rowid, "PowerCnt", socket["PowerCnt"])
				switch_on = 1
			
			elif (socket['PauseCnt'] > 0):
				socket['PauseCnt'] -= exeint
				switch_on = 0
				if (socket["PauseCnt"] <= 0):
					socket['PauseCnt'] = 0
					growbox_db.set_socket(rowid, "PowerCnt", socket["Power"])
				growbox_db.set_socket(rowid, "PauseCnt", socket["PauseCnt"])

				
		################################################
		# Temperature / Humidity, reset thpwr
		################################################
		thpwr = 0
				
		################################################
		# Temperature
		################################################
		t_lower = socket["TLower"]
		t_higher = socket["THigher"]
		if (socket["Temperature"] == 1) and ((t_lower > 0) or (t_higher > 0)):
			thpwr = 1
			if (t_lower > 0 and temperature1 < t_lower) or (t_higher > 0 and temperature1 > t_higher):
				socket["THPowerCnt"] = socket["THPower"]
				
		################################################
		# Humidity
		################################################
		h_lower = socket["HLower"]
		h_higher = socket["HHigher"]
		if (socket["Humidity"] == 1) and ((h_lower > 0) or (h_higher > 0)):
			thpwr = 1
			if (h_lower > 0 and humidity1 < h_lower) or (h_higher > 0 and humidity1 > h_higher):
				socket["THPowerCnt"] = socket["THPower"]
				
		################################################
		# TH Power
		################################################
		if ((socket["THPowerCnt"] > 0) and (thpwr == 1)):
			socket["THPowerCnt"] -= exeint
			if (socket["THPowerCnt"] <= 0):
				socket["THPowerCnt"] = 0
			growbox_db.set_socket(rowid, "THPowerCnt", socket["THPowerCnt"])
			switch_on = 1
			
				
		################################################
		# Pump Handle
		# Pump 200ml with 30m pause, should have a positive effect on how the earth absorbes the water
		# Note: Watering using the scale does not work when your pump's flowrate is high => don't do it, use the flowrate
		################################################
		ml = socket['MilliLiters']
		fr = socket['FlowRate']
		wsid = socket['WSensorID']
		minw = socket['MinWeight']
		maxw = socket['MaxWeight']
		ispumping = socket['IsPumping']
		topump = socket['ToPump']
		pumped = socket['Pumped']
		water_manual = 0
		weight = weight_sensor_data.get(wsid)
		
		if (socket["Pump"] == 1):
		
			# Check for MaxWeight, then stop pumping
			# Check is pump started via weight but config switched to no weight
			if (ispumping == 1) and (((weight is not None) and (weight >= maxw)) or ((weight is None) and (topump == -1))):
				ispumping = 0
				growbox_db.set_socket(rowid, "IsPumping", ispumping)
				growbox_db.insert_water(dt, rowid, pumped)
				topump = 0
				growbox_db.set_socket(rowid, "ToPump", topump)
				
			# decrement days
			if (socket['DaysCnt'] > 0) and (newday):
				socket['DaysCnt'] -= 1
				growbox_db.set_socket(rowid, "DaysCnt", socket["DaysCnt"])
			
			if (ispumping == 0):
				# is it time to water?
				if (now.hour == socket["Time"]) and (now.minute == 0):
					# using load cell
					if (weight is not None) and (minw > 0) and (minw > weight):
							growbox_db.set_socket(rowid, "IsPumping", 1)
							ispumping = 1
							topump = -1
							growbox_db.set_socket(rowid, "ToPump", topump)
							pumped = 0
							growbox_db.set_socket(rowid, "Pumped", pumped)
					
					# not using load cell
					elif (socket['Days'] > 0) and (socket['DaysCnt'] == 0):
						growbox_db.set_socket(rowid, "DaysCnt", socket['Days'])
						growbox_db.set_socket(rowid, "IsPumping", 1)
						ispumping = 1
						topump = ml
						growbox_db.set_socket(rowid, "ToPump", topump)
						pumped = 0
						growbox_db.set_socket(rowid, "Pumped", pumped)
						
					# water once
					elif (socket['Days'] == -1):
						growbox_db.set_socket(rowid, "Days", 0)
						growbox_db.set_socket(rowid, "IsPumping", 1)
						ispumping = 1
						topump = ml
						growbox_db.set_socket(rowid, "ToPump", topump)
						pumped = 0
						growbox_db.set_socket(rowid, "Pumped", pumped)
						
				# start manual watering, without pausing
				if (socket["Time"] == -2):
					growbox_db.set_socket(rowid, "Time", -1)
					growbox_db.set_socket(rowid, "IsPumping", 1)
					ispumping = 1
					topump = ml
					if weight is not None:
						topump = -1
					growbox_db.set_socket(rowid, "ToPump", topump)
					pumped = 0
					growbox_db.set_socket(rowid, "Pumped", pumped)
					water_manual = 1
				
			# water plants every 0 and 10,20,30 of the hour (config.PumpPause)
			if (ispumping == 1) and (fr > 0) and (((now.minute % config.PumpPause) == 0) or (water_manual == 1)):
			
				water_plants.append([ rowid, gpio, topump, pumped, fr, water_manual, name ])
				sockets_load[rowid] = load
						
	
		################################################
		# Switch Socket On or Off, except for Pump
		################################################
		if switch_on:
			if (growbox_db.get_main() == 1):
				growbox.relay_set(gpio, 1)
				sockets_load[rowid] = load
		elif (socket['IsPumping'] == 0):
			growbox.relay_set(gpio, 0)

################################################
# Update Power Meter
################################################
growbox_db.insert_power(dt, sockets_load[1],sockets_load[2],sockets_load[3],sockets_load[4],sockets_load[5],sockets_load[6],sockets_load[7],sockets_load[8])

################################################
# Watering, Start Pumping
# Pump [config.PumpInc]ml with [config.PumpPause]s pause, should have a positive effect on how the earth absorbes the water
# Pumping is threaded so all pumps can start at the same time
################################################
def water_plant(socketid, gpio, topump, pumped, fr, water_manual):

	pump_now = config.PumpInc
	if (topump != -1):
		remaining = topump-pumped
		if (water_manual == 1):
			pump_now = topump
		elif remaining < pump_now:
			pump_now = remaining
		
	slp = int(pump_now/fr) + 1 # add one second for pump to start till water comes from the tubes

	growbox.relay_set(gpio, 1)
	sleep(slp)
	growbox.relay_set(gpio, 0)
			
	grow_db = growbox.db()
	grow_db.connect()
	
	pumped += pump_now
	# update watering
	grow_db.set_socket(socketid, "Pumped", pumped)

	# finished watering
	if (topump != -1) and (pumped >= topump):
		grow_db.set_socket(socketid, "IsPumping", 0)
		grow_db.insert_water(dt, socketid, topump)
		grow_db.set_socket(socketid, "ToPump", 0)

	grow_db.disconnect()
	
if (growbox_db.get_main() == 1):
	for row in water_plants:
		rowid = row[0]
		gpio = row[1]
		ml = row[2]
		towater = row[3]
		fr = row[4]
		water_manual = row[5]
		name = row[6]

		thread = Thread(target = water_plant, args = (rowid, gpio, ml, towater, fr, water_manual))
		thread.start()
		
################################################
# capture new image
# capture at the end, since there are still issues with this function and it can cause other functions to fail
################################################
take_image()
if (log_image != "OFF"):
	save_image(log_image)
	
################################################
# RESET PID
growbox_db.set_config("PID", 0)
		
################################################
# SQL Close
################################################
growbox_db.disconnect()

#print "Handler: End"
