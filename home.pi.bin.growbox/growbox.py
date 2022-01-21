########################################################################################
# growbox.py
# Growbox Interfacing Functions
########################################################################################

#import adafruit_dht
# Adafruit old DHT library
import Adafruit_DHT

import sqlite3 as sqlite

import RPi.GPIO as GPIO

from picamera import PiCamera, Color

from time import sleep

import os

import sys

import config

from hx711 import HX711

############################################
# Database Functions
############################################
class db:

	connection = None
	cursor = None

	# connect to the database
	def connect(self):
		self.connection = sqlite.connect(config.DB)
		self.cursor = self.connection.cursor()

	# disconnect the database
	def disconnect(self):
		if (self.connection is not None):
			self.connection.close()
		self.connection = None
		self.cursor = None

	# growbox config
	def get_config(self):
		self.cursor.execute('SELECT * FROM Config')
		grwconfig = dict()
		for row in self.cursor:
			grwconfig[row[0]] = row[1]
		return grwconfig
		
	def get_config_val(self, name):
		self.cursor.execute("SELECT val FROM Config WHERE name='{0}'".format(name))
		return self.cursor.fetchone()[0]

	def get_main(self):
		return self.get_config_val("Main")
		
	# growbox config set (key, value)
	def set_config(self, key, value):
		self.cursor.execute("UPDATE Config SET val={0} WHERE name='{1}'".format(value, key))
		self.connection.commit()

	# get airsensors
	def get_airsensors(self):
		self.cursor.execute('SELECT rowid, enabled, name, gpio, h_offset, t_offset FROM AirSensors')
		return self.cursor.fetchall()
		
	# insert airsensordata
	def insert_airsensordata(self, dt, id, temperature, humidity):
		self.cursor.execute('INSERT INTO AirSensorData(dt, id, temperature, humidity) VALUES (\'{0}\', {1}, {2:0.1f}, {3:0.1f})'.format(dt, id, temperature, humidity))
		self.connection.commit()

	# get weightsensors
	def get_weightsensors(self):
		self.cursor.execute('SELECT rowid, enabled, name, data, clk, cal, offset FROM WeightSensors')
		return self.cursor.fetchall()
		
	# insert weightsensordata
	def insert_weightsensordata(self, dt, id, weight):
		self.cursor.execute('INSERT INTO WeightSensorData(dt, id, weight) VALUES (\'{0}\', {1}, {2})'.format(dt, id, weight))
		self.connection.commit()
		
	# get cameras
	def get_cameras(self):
		self.cursor.execute('SELECT rowid, enabled, usb, hres, vres, rotation, fps, brightness, contrast, awb FROM Cameras')
		return self.cursor.fetchall()

	# insert image
	def insert_image(self, dt, id, filename):
		self.cursor.execute('INSERT INTO Images(dt, id, filename) VALUES (\'{0}\', {1}, \'{2}\')'.format(dt, id, filename))
		self.connection.commit()
		
	# get_sockets()
	def get_sockets(self):
		def dict_factory(cursor, row):
			d = {}
			for idx, col in enumerate(cursor.description):
				d[col[0]] = row[idx]
			return d

		#connection.row_factory = sqlite3.Row
		self.connection.row_factory = dict_factory
		self.cursor = self.connection.cursor()
		self.cursor.execute('SELECT rowid,* FROM Sockets')
		sockets = self.cursor.fetchall()
		self.connection.row_factory = None
		self.cursor = self.connection.cursor()
		return sockets
		
	# set_socket
	# handler only sets socket number types at the moment
	def set_socket(self, rowid, name, value):
		self.cursor.execute("UPDATE Sockets SET {1}={2} WHERE rowid={0}".format(rowid, name, value))
		self.connection.commit()
		
	# insert_power
	def insert_power(self, dt, p1, p2, p3, p4, p5, p6, p7, p8):
		self.cursor.execute("INSERT INTO PowerMeter VALUES ('{0}',{1},{2},{3},{4},{5},{6},{7},{8})".format(dt,
			p1, p2, p3, p4, p5, p6, p7, p8))
		self.connection.commit()
		
	# insert_water
	def insert_water(self, dt, id, ml):
		self.cursor.execute("INSERT INTO Water (dt, id, ml) VALUES ('{0}', {1}, {2})".format(dt, id, ml))
		self.connection.commit()

	# database clean, remove old data
	def clean(self):
		#delta = "-6 months"
		delta = "-1 year"
		self.cursor.execute("SELECT dt, id, filename FROM Images WHERE DATE(dt) < DATE('now', '{0}')".format(delta))
		imgs = self.cursor.fetchall()
		for i,v in imgs:
			os.remove(config.images_path + v[2])
		self.cursor.execute("DELETE FROM Images WHERE DATE(dt) < DATE('now', '{0}')".format(delta))

		self.cursor.execute("DELETE FROM AirSensorData WHERE DATE(dt) < DATE('now', '{0}')".format(delta))
		self.cursor.execute("DELETE FROM WeightSensorData WHERE DATE(dt) < DATE('now', '{0}')".format(delta))
		self.cursor.execute("DELETE FROM PowerMeter WHERE DATE(dt) < DATE('now', '{0}')".format(delta))
		self.cursor.execute("DELETE FROM Water WHERE DATE(dt) < DATE('now', '{0}')".format(delta))

		self.connection.commit()
############################################
############################################

# Helper to check if switch should be on
def switch_on(on, off, hour):
	if (on == off):
		return True
	if (on < off):
		if (hour >= on) and (hour < off):
			return True
	if (on > off):
		if not ((hour >= off) and (hour < on)):
			return True
	return False

# Helper to get log image on start, one hour before stop and in the middle, middle seems to be int by default
def log_image(on, off, hour, minute):
	if (minute == 0):
		if (on == hour):
			return "ON"
		
		if (off == hour):
			return "OFF"
			
		diff = off - on
		if (diff < 0):
			diff = 24 + diff
		middle = on + int(diff/2)
		if (middle > 23):
			middle = middle - 24
		if (middle == hour):
			return "MIDDLE"
	
	return ""
	
# Set Relay, (gpio, value 1:on, 0:off)
def relay_set(gpio, val):
	pin = gpio
	if pin is not None:
		GPIO.setwarnings(False)
		GPIO.setmode(GPIO.BCM)
		GPIO.setup(pin, GPIO.OUT)
		if val == 1:
			GPIO.output(pin, GPIO.LOW) # on
		else:
			GPIO.output(pin, GPIO.HIGH) # out
		
# Camera capture
#https://picamera.readthedocs.io/en/release-1.13/api_camera.html
def camera_capture(file, hres, vres, rotation, text=None, fps=None, brightness=None, contrast=None, awb=None):
	
	camera = None
	try:
		camera = PiCamera()

	except:
		print("camera_capture():create:", sys.exc_info()[0])
		#print("camera_capture(): reboot")
		#os.system('sudo reboot')
		return

	try:
		camera.resolution = (hres, vres)
		camera.rotation = rotation

		if text is not None:
			camera.annotate_text_size = 20
			camera.annotate_background = Color('black')
			camera.annotate_foreground = Color('white')
			camera.annotate_text = text
		if fps is not None:
			camera.framerate = fps
		if brightness is not None:
			camera.brightness = brightness
		if contrast is not None:
			camera.contrast = contrast
		if awb is not None:
			camera.awb_mode = awb

		camera.start_preview()
		sleep(10)
		camera.capture(file)
		camera.stop_preview()

	except:
		print("camera_capture():capture:", sys.exc_info()[0])
		return

	finally:
		camera.close()

		
# USB Camera capture function
# sudo apt-get install fswebcam
# http://manpages.ubuntu.com/manpages/trusty/man1/fswebcam.1.html
def camera_capture_usb(device, file, hres, vres, rotation, text=None, fps=None, brightness=None, contrast=None):
	cmd = "fswebcam -d \"" + device + "\" --quiet --frames 10 --delay 10 --top-banner -r " + str(hres) + "x" + str(vres) + " --rotate " + str(rotation)
	
	if text is not None:
		cmd = cmd +  " --title \"" + text + "\""
	if fps is not None:
		cmd = cmd + " --fps " + str(fps)
	if brightness is not None:
		cmd = cmd + " --set brightness=" + str(brightness) + "%"
	if contrast is not None:
		cmd = cmd + " --set contrast=" + str(contrast) + "%"
	
	#print("camera_capture_usb(): ", cmd + " " + file)
	os.system(cmd + " " + file)

"""
# AirSensor Read
# https://learn.adafruit.com/dht-humidity-sensing-on-raspberry-pi-with-gdocs-logging/python-setup
def airsensor_read2(sensor, gpio, h_offset, t_offset):
	dht_device = None
	
	# try 5 times
	for i in range(5):
		try:
			if (sensor == "DHT11"):
				dht_device = adafruit_dht.DHT11(gpio)
			if (sensor == "DHT22"):
				dht_device = adafruit_dht.DHT22(gpio)
				#dht_device = adafruit_dht.DHT22(gpio, use_pulseio=False)
			if dht_device is not None:
				break

		except RuntimeError as error:
			# Errors happen fairly often, just keep going
			print("airsensor_read():RuntimeError:", error.args[0])
			sleep(2.0)
			continue

		except Exception as error:
			print("airsensor_read():Exception:", error.args[0])
			return 0,0

	if dht_device is not None:
		# try 5 times
		for i in range(5):
			try:
				temperature = dht_device.temperature
				humidity = dht_device.humidity
				return humidity+h_offset, temperature+t_offset
				
			except RuntimeError as error:
				# Errors happen fairly often, DHT's are hard to read, just keep going
				print("airsensor_read():RuntimeError:", error.args[0])
				sleep(2.0)
				continue
			
			except Exception as error:
				print("airsensor_read():Exception:", error.args[0])
				dht_device.exit()
				return 0,0
		dht_device.exit()

	print("airsensor_read(): Error Max Tries")
	return 0,0
"""

def airsensor_read(sensor, gpio, h_offset, t_offset):
	# Sensor should be set to Adafruit_DHT.DHT11,
	# Adafruit_DHT.DHT22, or Adafruit_DHT.AM2302.
	# DHT22 and AM2302 are the same -> common.py line 37
	sensor = Adafruit_DHT.DHT22
	if (sensor == "DHT11"):
		sensor = Adafruit_DHT.DHT11
	if (sensor == "AM2302"):
		sensor = Adafruit_DHT.AM2302

	humidity, temperature = None, None
	# try 5 times until read returns logical values
	for i in range(5):
		# Try to grab a sensor reading.  Use the read_retry method which will retry up
		# to 15 times to get a sensor reading (waiting 2 seconds between each retry).
		humidity, temperature = Adafruit_DHT.read_retry(sensor, gpio)
		if (humidity is not None) and (humidity <= 100) and (humidity >= 0):
			# Note that sometimes you won't get a reading and
			# the results will be null or not plausible (because Linux can't
			# guarantee the timing of calls to read the sensor).
			# If this happens try again!
			#print('Temp={0:0.1f}*C  Humidity={1:0.1f}%'.format(temperature, humidity))
			return humidity+h_offset, temperature+t_offset
		print("airsensor_read(): Failure/False reading detected. Trying again!")
		sleep(2)

	print("airsensor_read(): Failed to get reading.")
	return None, None
	# return 0,0 to log false reading
	#return 0,0

# Get Weight from HX711
# returns 0 when scale is 0 and when there is an error
def get_weight(data, clk, cal, offset):
	GPIO.setwarnings(False)
	hx = 0
	try:
		hx = HX711(data, clk, 128)
	except Exception as error:
		print("get_weight():Exception:", error.args[0])
		return 0
		
	# HOW TO CALCULATE THE REFFERENCE UNIT
	# To set the reference unit to 1. Put 1kg on your sensor or anything you have and know exactly how much it weights.
	# In this case, 92 is 1 gram because, with 1 as a reference unit I got numbers near 0 without any weight
	# and I got numbers around 184000 when I added 2kg. So, according to the rule of thirds:
	# If 2000 grams is 184000 then 1000 grams is 184000 / 2000 = 92.
	#hx.set_reference_unit(113)
	hx.set_reference_unit(cal)
	
	hx.set_offset(offset)

	hx.reset()
	val = 0
	try:
		if (offset == 0):
			val = int(hx.get_weight(100))
		else:
			val = int(hx.get_weight(10))

	except Exception as error:
		print("get_weight():Exception:", error.args[0])
		hx.power_down()
		return 0

#		print(type(error))    # the exception instance
#		print(error.args)     # arguments stored in .args
#		print(error)          # __str__ allows args to be printed directly,
		
	hx.power_down()
	return val
	
	