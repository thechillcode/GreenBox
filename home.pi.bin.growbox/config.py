# Growbox Config:

# Define Watering Increment in (ml), Water/Pump 200ml per serving
PumpInc = 200
# Define Time between incremental watering (minutes), to make sense should be = 10,20,30
PumpPause = 30

# Database Path
DB = '/home/pi/DB/Growbox.db'

# Adafuit AirSensorType
# DHT22 and AM2302 are the same, common.py line 37
#AirSensorType = "DHT11"
AirSensorType = "DHT22"

# Define exeint, execute intervall in minutes !!!! Do not change !!!!
ExeInt = 10

images_path = "/var/www/growbox/cam/"
