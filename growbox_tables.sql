-- Database DB/GrowBox.db

BEGIN TRANSACTION;

----------------------------------------------
-- Air Sensors, HT22 - GPIO
----------------------------------------------
DROP TABLE IF EXISTS AirSensors;
CREATE TABLE AirSensors(enabled INT NOT NULL, name TEXT NOT NULL, gpio INT NOT NULL, h_offset INT NOT NULL, t_offset INT NOT NULL);
INSERT INTO AirSensors VALUES (0, '', 0, 0, 0);
INSERT INTO AirSensors VALUES (0, '', 0, 0, 0);
INSERT INTO AirSensors VALUES (0, '', 0, 0, 0);
INSERT INTO AirSensors VALUES (0, '', 0, 0, 0);				
					
----------------------------------------------
-- Air Sensor Readings, Inside the Box, T°C, H%
-- DROP TABLE also drops the indexes or triggers
----------------------------------------------
DROP TABLE IF EXISTS AirSensorData;
CREATE TABLE AirSensorData(dt DATETIME NOT NULL, id INT NOT NULL, temperature REAL NOT NULL, humidity REAL NOT NULL);
CREATE INDEX IDX_AirSen ON AirSensorData (dt, id);

DROP VIEW IF EXISTS v_AirSensorData;
CREATE VIEW v_AirSensorData
AS 
	SELECT datetime(dt, 'localtime') AS dt, id, humidity, temperature,
		CASE CAST(strftime('%m', datetime(dt, 'localtime')) as integer)
			WHEN 1 then 'Jan'
			WHEN 2 then 'Feb' 
			WHEN 3 then 'Mar' 
			WHEN 4 then 'Apr' 
			WHEN 5 then 'May' 
			WHEN 6 then 'Jun' 
			WHEN 7 then 'Jul' 
			WHEN 8 then 'Aug' 
			WHEN 9 then 'Sep' 
			WHEN 10 then 'Oct' 
			WHEN 11 then 'Nov' 
			WHEN 12 then 'Dec' end AS s_mon,
		CASE CAST(strftime('%w', datetime(dt, 'localtime')) as integer)
			WHEN 0 then 'Sun'
			WHEN 1 then 'Mon' 
			WHEN 2 then 'Tue' 
			WHEN 3 then 'Wed' 
			WHEN 4 then 'Thu' 
			WHEN 5 then 'Fri' 
			WHEN 6 then 'Sat' end AS s_day,
		strftime('%H:%M', datetime(dt, 'localtime')) AS s_time,
		strftime('%d', datetime(dt, 'localtime')) AS num_day
	FROM AirSensorData;


DROP TABLE IF EXISTS AirSensorDataLog;
CREATE TABLE AirSensorDataLog(dt DATETIME NOT NULL, id INT NOT NULL, temperature REAL NOT NULL, humidity REAL NOT NULL);

----------------------------------------------
-- Weight Sensors, data: data gpio, clk: clock
----------------------------------------------
DROP TABLE IF EXISTS WeightSensors;
CREATE TABLE WeightSensors(enabled INT NOT NULL, name TEXT NOT NULL, data INT NOT NULL, clk INT NOT NULL, cal FLOAT NOT NULL, offset INT NOT NULL);
INSERT INTO WeightSensors VALUES (0, '', 0, 0, 0, 0);
INSERT INTO WeightSensors VALUES (0, '', 0, 0, 0, 0);
INSERT INTO WeightSensors VALUES (0, '', 0, 0, 0, 0);
INSERT INTO WeightSensors VALUES (0, '', 0, 0, 0, 0);

DROP TABLE IF EXISTS WeightSensorData;
CREATE TABLE WeightSensorData(dt DATETIME NOT NULL, id INT NOT NULL, weight INTEGER NOT NULL);
CREATE INDEX IDX_WeightSen ON WeightSensorData (dt, id);

DROP VIEW IF EXISTS v_WeightSensorData;
CREATE VIEW v_WeightSensorData
AS 
	SELECT datetime(dt, 'localtime') AS dt, id, weight,
		CASE CAST(strftime('%m', datetime(dt, 'localtime')) as integer)
			WHEN 1 then 'Jan'
			WHEN 2 then 'Feb' 
			WHEN 3 then 'Mar' 
			WHEN 4 then 'Apr' 
			WHEN 5 then 'May' 
			WHEN 6 then 'Jun' 
			WHEN 7 then 'Jul' 
			WHEN 8 then 'Aug' 
			WHEN 9 then 'Sep' 
			WHEN 10 then 'Oct' 
			WHEN 11 then 'Nov' 
			WHEN 12 then 'Dec' end AS s_mon,
		CASE CAST(strftime('%w', datetime(dt, 'localtime')) as integer)
			WHEN 0 then 'Sun'
			WHEN 1 then 'Mon' 
			WHEN 2 then 'Tue' 
			WHEN 3 then 'Wed' 
			WHEN 4 then 'Thu' 
			WHEN 5 then 'Fri' 
			WHEN 6 then 'Sat' end AS s_day,
		strftime('%H:%M', datetime(dt, 'localtime')) AS s_time,
		strftime('%d', datetime(dt, 'localtime')) AS num_day
	FROM WeightSensorData;

----------------------------------------------
-- Sockets, Relay:
-- rowid is Sockets ID on Relay
-- Control : (0=Switch,1=Timer,2=Interval,3=T,4=H,5=Pump)
-- GPIO: 26, 19, 13, 06, 05, 21, 20, 16
----------------------------------------------
DROP TABLE IF EXISTS Sockets;
CREATE TABLE Sockets (Name TEXT NOT NULL, Active INTEGER NOT NULL, GPIO INTEGER NOT NULL, Load INTEGER NOT NULL, -- Socket
						-- Control
						Control INTEGER NOT NULL,
						
						-- Switch
						Switch INTEGER NOT NULL, State INTEGER NOT NULL,
						-- Timer
						Timer INTEGER NOT NULL, HOn INTEGER NOT NULL, HOff INTEGER NOT NULL,
						-- Interval
						Interval INTEGER NOT NULL, Power INTEGER NOT NULL, PowerCnt INTEGER NOT NULL, Pause INTEGER NOT NULL, PauseCnt INTEGER NOT NULL,
						-- Temperature
						Temperature INTEGER NOT NULL, TLower INTEGER NOT NULL, THigher INTEGER NOT NULL,
						-- Lower Humidity
						Humidity INTEGER NOT NULL, HLower INTEGER NOT NULL, HHigher INTEGER NOT NULL,
						-- Temp, Humidity Power
						THPower INTEGER NOT NULL, THPowerCnt INTEGER NOT NULL,
						-- Pump
						Pump INTEGER NOT NULL, Days INTEGER NOT NULL, Time INTEGER NOT NULL, MilliLiters INTEGER NOT NULL, FlowRate REAL NOT NULL, DaysCnt INTEGER NOT NULL,
						-- Pump using Weight Sensor
						WSensorID INTEGER NOT NULL, MinWeight INTEGER NOT NULL, MaxWeight INTEGER NOT NULL,
						-- IsPumping
						IsPumping INTEGER NOT NULL, ToPump INTEGER NOT NULL, Pumped INTEGER NOT NULL
						);
						
							--            #C #Swi  #Timer   #Interval      #Temp,   #Humi,   #THPw #Pump               #W,MinMax  #IsPumping #ToPump #Pumped
INSERT INTO Sockets VALUES ('', 0, 26, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0, 19, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0, 13, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0,  6, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);

INSERT INTO Sockets VALUES ('', 0,  5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0, 21, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0, 20, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);
INSERT INTO Sockets VALUES ('', 0, 16, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0.0, 0, 0, 0, 0,   0, 0, 0);

----------------------------------------------
-- Power Meter, load in W, l(x) x=Sockets.rowid
-- dt is in UTC time
----------------------------------------------
DROP TABLE IF EXISTS PowerMeter;
CREATE TABLE PowerMeter(dt DATETIME NOT NULL, l1 INTEGER NOT NULL, l2 INTEGER NOT NULL, l3 INTEGER NOT NULL,
	l4 INTEGER NOT NULL, l5 INTEGER NOT NULL, l6 INTEGER NOT NULL, l7 INTEGER NOT NULL, l8 INTEGER NOT NULL);
CREATE INDEX IDX_PowerMeter ON PowerMeter (dt);

DROP VIEW IF EXISTS v_PowerMeter;
CREATE VIEW v_PowerMeter
AS 
	SELECT datetime(dt, 'localtime') AS dt, l1, l2, l3, l4, l5, l6, l7, l8,
		CASE CAST(strftime('%m', datetime(dt, 'localtime')) as integer)
			WHEN 1 then 'Jan'
			WHEN 2 then 'Feb' 
			WHEN 3 then 'Mar' 
			WHEN 4 then 'Apr' 
			WHEN 5 then 'May' 
			WHEN 6 then 'Jun' 
			WHEN 7 then 'Jul' 
			WHEN 8 then 'Aug' 
			WHEN 9 then 'Sep' 
			WHEN 10 then 'Oct' 
			WHEN 11 then 'Nov' 
			WHEN 12 then 'Dec' end AS s_mon,
		CASE CAST(strftime('%w', datetime(dt, 'localtime')) as integer)
			WHEN 0 then 'Sun'
			WHEN 1 then 'Mon' 
			WHEN 2 then 'Tue' 
			WHEN 3 then 'Wed' 
			WHEN 4 then 'Thu' 
			WHEN 5 then 'Fri' 
			WHEN 6 then 'Sat' end AS s_day,
		strftime('%H:%M', datetime(dt, 'localtime')) AS s_time,
		strftime('%d', datetime(dt, 'localtime')) AS num_day
	FROM PowerMeter;

----------------------------------------------
-- Watering, id = Sockets.rowid,
----------------------------------------------
DROP TABLE IF EXISTS Water;
CREATE TABLE Water(dt DATETIME NOT NULL, id INT NOT NULL, ml INTEGER NOT NULL);
CREATE INDEX IDX_Water ON Water (dt, id);

DROP VIEW IF EXISTS v_Water;
CREATE VIEW v_Water
AS 
	SELECT datetime(dt, 'localtime') AS dt, id, ml,
		CASE CAST(strftime('%m', datetime(dt, 'localtime')) as integer)
			WHEN 1 then 'Jan'
			WHEN 2 then 'Feb' 
			WHEN 3 then 'Mar' 
			WHEN 4 then 'Apr' 
			WHEN 5 then 'May' 
			WHEN 6 then 'Jun' 
			WHEN 7 then 'Jul' 
			WHEN 8 then 'Aug' 
			WHEN 9 then 'Sep' 
			WHEN 10 then 'Oct' 
			WHEN 11 then 'Nov' 
			WHEN 12 then 'Dec' end AS s_mon,
		CASE CAST(strftime('%w', datetime(dt, 'localtime')) as integer)
			WHEN 0 then 'Sun'
			WHEN 1 then 'Mon' 
			WHEN 2 then 'Tue' 
			WHEN 3 then 'Wed' 
			WHEN 4 then 'Thu' 
			WHEN 5 then 'Fri' 
			WHEN 6 then 'Sat' end AS s_day,
		strftime('%H:%M', datetime(dt, 'localtime')) AS s_time,
		strftime('%d', datetime(dt, 'localtime')) AS num_day
	FROM Water;

----------------------------------------------
-- Cameras (Resolution: 640x480)
-- RaspiCam (default)[range]: fps (30)[1,60], brightness (50)[0,100], contrast (0)[0,100],
--	awb ('auto')['auto','sunlight','cloudy','shade','tungsten','fluorescent','incandescent','flash','horizon']
-- RaspiCam settings: (2, 35, 0, 'fluorescent') or (60, 50, 0, 'fluorescent')
-- USB Cam usb: 'v4l2:/dev/video0' 2nd 'v4l2:/dev/video1' ... or '/dev/video0'
-- USB Cam (default): fps (0)[?], brightness (50)[0,100], contrast (0)[0,100]
-- USB Came Note: awb mode has no effect, set to ''
----------------------------------------------
DROP TABLE IF EXISTS Cameras;
CREATE TABLE Cameras(enabled INT NOT NULL, usb TEXT NOT NULL,
	hres int NOT NULL, vres int NOT NULL, rotation int NOT NULL,
	fps int NOT NULL, brightness int NOT NULL, contrast int NOT NULL, awb TEXT NOT NULL);
INSERT INTO Cameras VALUES (0, '', 0, 0, 0, 0, 50, 0, '');
INSERT INTO Cameras VALUES (0, '', 0, 0, 0, 0, 50, 0, '');
INSERT INTO Cameras VALUES (0, '', 0, 0, 0, 0, 50, 0, '');
INSERT INTO Cameras VALUES (0, '', 0, 0, 0, 0, 50, 0, '');

----------------------------------------------
-- Images from Cameras, id = Cameras.rowid
----------------------------------------------
DROP TABLE IF EXISTS Images;
CREATE TABLE Images(dt DATETIME NOT NULL, id INT NOT NULL, filename TEXT NOT NULL);
CREATE INDEX IDX_Images ON Images (dt, id);

----------------------------------------------
-- Config
----------------------------------------------
DROP TABLE IF EXISTS Config;
CREATE TABLE Config(name TEXT NOT NULL, val INTEGER NOT NULL);
-- Light Config
INSERT INTO Config (name, val) VALUES ('Light', 0);
INSERT INTO Config (name, val) VALUES ('LightOn', 0);
INSERT INTO Config (name, val) VALUES ('LightOff', 0);

-- Julien Day Count
INSERT INTO Config (name, val) VALUES ('JulienDay', 0);

-- Archive
-- 0=NotCreating, 1=Creating,
INSERT INTO Config (name, val) VALUES ('Archive', 0);
INSERT INTO Config (name, val) VALUES ('ArchiveDate', 0);

-- Run Handler on next iteration
INSERT INTO Config (name, val) VALUES ('RunHandler', 0);

-- Main Switch
INSERT INTO Config (name, val) VALUES ('Main', 0);

-- Reboot Time
INSERT INTO Config (name, val) VALUES ('Reboot', 0);
INSERT INTO Config (name, val) VALUES ('SetReboot', 0);

-- Num Sockets
INSERT INTO Config (name, val) VALUES ('NumSockets', 0);
-- Num NumCameras
INSERT INTO Config (name, val) VALUES ('NumCameras', 0);
-- Num AirSensors
INSERT INTO Config (name, val) VALUES ('NumAirSensors', 0);
-- Num WeightSensors
INSERT INTO Config (name, val) VALUES ('NumWeightSensors', 0);

-- Reset Date format: 20210101
INSERT INTO Config (name, val) VALUES ('StartDate', 0);

-- Process ID
INSERT INTO Config (name, val) VALUES ('PID', 0);


-------------
-- Set GPIO's
-------------
-- GPIO: 26, 19, 13, 6,  5, 21, 20, 16
UPDATE Sockets SET GPIO=26 WHERE rowid=1;
UPDATE Sockets SET GPIO=19 WHERE rowid=2;
UPDATE Sockets SET GPIO=13 WHERE rowid=3;
UPDATE Sockets SET GPIO=6  WHERE rowid=4;
UPDATE Sockets SET GPIO=5  WHERE rowid=5;
UPDATE Sockets SET GPIO=21 WHERE rowid=6;
UPDATE Sockets SET GPIO=20 WHERE rowid=7;
UPDATE Sockets SET GPIO=16 WHERE rowid=8;

UPDATE AirSensors SET GPIO=17 WHERE rowid=1;
UPDATE AirSensors SET GPIO=27 WHERE rowid=2;

UPDATE WeightSensors SET data=22, clk=10 WHERE rowid=1;
UPDATE WeightSensors SET data=9,  clk=11 WHERE rowid=2;

-------------
-- Set Config
-------------
-- Max NumSockets 8
UPDATE Config SET val=8 WHERE name='NumSockets';
-- Max NumCameras 4
UPDATE Config SET val=2 WHERE name='NumCameras';
-- Max NumAirSensors 2
UPDATE Config SET val=2 WHERE name='NumAirSensors';
-- Max NumWeightSensors 2
UPDATE Config SET val=2 WHERE name='NumWeightSensors';

COMMIT;
