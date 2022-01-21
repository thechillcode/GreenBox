#!/bin/bash -x

# Optimized for Raspberry4 / Zero, Lite Image

echo "### Installing Software ###"

# Save Install Dir
instDir=$(pwd)

# Update RPI
sudo apt-get update
sudo apt-get upgrade -y

# Git
sudo apt install git -y

# USB Camera
sudo apt-get install fswebcam -y

# Apache2 + PHP + sqlite3
sudo apt-get install apache2 -y
sudo apt-get install sqlite3 -y
sudo apt-get install php libapache2-mod-php php-sqlite3 php-zip -y

# Enable Rewrite in Apache2
sudo a2enmod rewrite

# Python3 and dependencies
sudo apt-get install python3-pip -y
sudo pip3 install --upgrade setuptools

sudo apt-get install python3-crontab -y
sudo pip3 install RPI.GPIO

sudo apt-get install python3-picamera -y

# Adafruit Library, DHT22

# Adafruit DEPRECATED Library, DHT22
# https://learn.adafruit.com/dht-humidity-sensing-on-raspberry-pi-with-gdocs-logging/python-setup
# common_dht_read.h, line 36, AM2302 & DHT22 are the same
#git clone https://github.com/adafruit/Adafruit_Python_DHT.git && cd Adafruit_Python_DHT
cd Adafruit_Python_DHT
sudo python3 setup.py install
cd $instDir

# https://learn.adafruit.com/dht-humidity-sensing-on-raspberry-pi-with-gdocs-logging/python-setup
#sudo pip3 install adafruit-blinka
#sudo pip3 install adafruit-circuitpython-dht
#sudo apt-get install libgpiod2
#sudo pip3 uninstall adafruit-circuitpython-dht
#sudo apt-get --purge remove libgpiod2

# optionally compile libgpiod2 manually
#sudo apt install libgpiod-dev git build-essential
#git clone https://github.com/adafruit/libgpiod_pulsein.git
#sudo cd libgpiod_pulsein/src
#sudo make
#sudo cp libgpiod_pulsein /usr/local/lib/python3.5/dist-packages/adafruit_blinka/microcontroller/bcm283x/pulseio/libgpiod_pulsein

# Raspberry Pi 4, Wiring PI fix
# Currently wiring pi for rpi4 needs to be updated
# git clone https://github.com/WiringPi/WiringPi.git
#git clone https://github.com/WiringPi/WiringPi.git && cd WiringPi
cd WiringPi
sudo ./build
cd $instDir

# Raspberry Pi 3
#sudo apt-get install wiringpi

echo "### Installing Software - DONE ###"
