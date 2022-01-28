#!/usr/bin/python
#--------------------------------------
#    ___  ___  _ ____
#   / _ \/ _ \(_) __/__  __ __
#  / , _/ ___/ /\ \/ _ \/ // /
# /_/|_/_/  /_/___/ .__/\_, /
#                /_/   /___/
#
#           bme280.py
#  Read data from a digital pressure sensor.
#
# Author : Matt Hawkins
# Date   : 21/01/2018
#
# https://www.raspberrypi-spy.co.uk/
#
# 2022-01-24:
#	Updated/Optimized for Growbox/Greenbox usage
#--------------------------------------

# maybe update to smbus2 -> https://pypi.org/project/smbus2/
import smbus
import time

DEFAULT_ADDR = 0x76 # Default device I2C address

def readBME280ID(addr=DEFAULT_ADDR):
	bus = smbus.SMBus(1) # Pi 3/4/Zero uses bus 1
	# Chip ID Register Address
	REG_ID     = 0xD0
	(chip_id, chip_version) = bus.read_i2c_block_data(addr, REG_ID, 2)
	return (chip_id, chip_version)

def readBME280(addr=DEFAULT_ADDR):

	bus = smbus.SMBus(1) # Pi 3/4/Zero uses bus 1
					 
	# Helper Functions
	def to_short(num):
		num = num & 0xFFFF
		if ((0x8000 & num) == 0):
			return num
		return (num - 0xFFFF)
		
	def to_char(num):
		num = num & 0xFF
		if ((0x80 & num) == 0):
			return num
		return (num - 0xFF)

	# Read blocks of calibration data from EEPROM
	# See Tabel 16, read minimum calls
	cal1 = bus.read_i2c_block_data(addr, 0x88, 26)
	cal3 = bus.read_i2c_block_data(addr, 0xE1, 7)

	# Convert byte data to word values
	# 0x88 - 0x89
	dig_T1 = ((cal1[1] << 8) | cal1[0])
	# 0x8A - 0x8B
	dig_T2 = to_short((cal1[3] << 8) | cal1[2])
	# 0x8C - 0x8D
	dig_T3 = to_short((cal1[5] << 8) | cal1[4])

	# 0xA1
	dig_H1 = cal1[25]
	
	# 0xE1 - 0xE2
	dig_H2 = to_short((cal3[1] << 8) | cal3[0])
	# 0xE3
	dig_H3 = cal3[2]
	# 0xE4 - 0xE5[3:0]
	dig_H4 = to_short((cal3[3] << 4) | (cal3[4] & 0x0F))
	# 0xE5[7:4] - 0xE6
	dig_H5 = to_short((cal3[5] << 4) | (cal3[4] >> 4))
	# 0xE7
	dig_H6 = to_char(cal3[6])
	
	
	# Register Addresses
	REG_DATA = 0xFA
	REG_CONTROL = 0xF4
	REG_CONFIG  = 0xF5
	REG_CONTROL_HUM = 0xF2
	
	# Measurement Mode (Forced) Table.25
	MODE = 1

	# Oversampling: 3.6 Noise
	# Humidity / Temperature: x2/x1 | 0.05(RMS Noise) | ca. 2.5µA
	# Temperature: x1 | 0.005(RMS Noise)

	# Oversample setting - 5.4.3 (osrs_h, osrs_t, osrs_p)
	OVERSAMPLE_H = 2
	OVERSAMPLE_T = 1
	# skip pressure measurement returns 0x80000
	OVERSAMPLE_P = 0

	control = OVERSAMPLE_T<<5 | OVERSAMPLE_P<<2 | MODE

	# Wait in ms (Datasheet Appendix B: Measurement time and current calculation)
	wait_time = 1.25 + (2.3 * OVERSAMPLE_T) + ((2.3 * OVERSAMPLE_P) + 0.575) + ((2.3 * OVERSAMPLE_H)+0.575)

	# Write and start measurement
	bus.write_byte_data(addr, REG_CONTROL_HUM, OVERSAMPLE_H)
	bus.write_byte_data(addr, REG_CONTROL, control)

	time.sleep(wait_time/1000)  # Wait the required time  

	# Read temperature/pressure/humidity
	data = bus.read_i2c_block_data(addr, REG_DATA, 5)

	temp_raw = (data[0] << 12) | (data[1] << 4) | (data[2] >> 4)
	hum_raw = (data[3] << 8) | data[4]
	
	# Calculate and Compensate temperature
	var1 = ((((temp_raw>>3)-(dig_T1<<1)))*(dig_T2)) >> 11
	var2 = (((((temp_raw>>4) - (dig_T1)) * ((temp_raw>>4) - (dig_T1))) >> 12) * (dig_T3)) >> 14
	t_fine = var1+var2
	temperature = float(((t_fine * 5) + 128) >> 8);

	# Calculate and Compensate  humidity
	humidity = t_fine - 76800.0
	humidity = (hum_raw - (dig_H4 * 64.0 + dig_H5 / 16384.0 * humidity)) * (dig_H2 / 65536.0 * (1.0 + dig_H6 / 67108864.0 * humidity * (1.0 + dig_H3 / 67108864.0 * humidity)))
	humidity = humidity * (1.0 - dig_H1 * humidity / 524288.0)
	if humidity > 100:
		humidity = 100
	elif humidity < 0:
		humidity = 0

	return round(temperature/100.0, 1), round(humidity, 1)
