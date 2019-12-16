
import RPi.GPIO as GPIO
import threading
import time
import serial
import socket
import sys
import pymysql as mydb
import datetime

#led : 1, pump : 2, fan : 3, heat : 4

led = 6
pump = 13
fan = 19
heat = 26

HIGH = False
LOW = True

ser = serial.Serial("/dev/ttyACM0", 9600)
#ser = serial.Serial("/dev/ttyACA0", 9600)

HOST = '13.209.3.92'

print("Start!")

d_conn = mydb.connect(host=HOST, port=3306, user='raspberry', password='haveagoodfarm', db='smart_farm')
cur = d_conn.cursor()
main_cur = d_conn.cursor()
print("Database connectd")

GPIO.setmode(GPIO.BCM)
GPIO.setup(led, GPIO.OUT)
GPIO.setup(pump, GPIO.OUT)
GPIO.setup(heat, GPIO.OUT)
GPIO.setup(fan, GPIO.OUT)

GPIO.output(led, LOW)
GPIO.output(pump, LOW)
GPIO.output(heat, LOW)
GPIO.output(fan, LOW)

tempStr = ""
data = []

co2_value = 0
cds_value = 0
soil_value = 0
humi_value = 0
temp_value = 0

# 0 : Auto
# 1 : User
# 2 : q
#

heat_state = 0
water_state = 0
led_state = 0
fan_state = 0
error_state = False

new_co2_value = 0
new_cds_value = 0
new_soil_value = 0
new_humi_value = 0
new_temp_value = 0

def w_state():
    global soil_value
    global new_soil_value
    global water_state
    global error_state
    while True:
        if error_state == True:
            break

        if water_state == '0':
            if int(soil_value) >= 500:
                GPIO.output(pump, HIGH)
            else :
                GPIO.output(pump, LOW)

        elif water_state == '1':
            if int(soil_value) >= int(new_soil_value):
                GPIO.output(pump, HIGH)
            else :
                GPIO.output(pump, LOW)

def l_state():
    global cds_value
    global new_cds_value
    global led_state
    global error_state

    while True:
        if error_state == True:
            break

        if led_state == '0':
            if int(cds_value) <= 170:
                #print("led Low")
                GPIO.output(led, LOW)
            else :
                #print("led HIGH")
                GPIO.output(led, HIGH)

        elif led_state == '1':
            if int(cds_value) <= int(new_cds_value):
                GPIO.output(led, LOW)
            else:
                GPIO.output(led, HIGH)
        time.sleep(0.5)

def f_state():
    global co2_value
    global new_co2_value
    global fan_state
    global error_state
    while True:
        if error_state == True:
           break

        if fan_state == '0':
            if int(co2_value) <= 47:
                GPIO.output(fan, LOW)
            else :
                GPIO.output(fan, HIGH)

        elif led_state == '1':
            if int(co2_value) <= int(new_co2_value):
                GPIO.output(fan, LOW)
            else :
                GPIO.output(fan, HIGH)

def h_state():
    global temp_value
    global new_temp_value
    global heat_state
    global error_state
    while True:
        if error_state == True:
            break

        if heat_state == '0':
            if int(temp_value) <= 10:
                GPIO.output(heat, HIGH)
            else :
                GPIO.output(heat, LOW)

        elif heat_state == '1':
            if int(temp_value) <= int(new_temp_value):
                GPIO.output(heat, HIGH)
            else:
                GPIO.outpu(heat, LOW)

water_thread = threading.Thread(target=w_state, args=())
water_thread.start()
led_thread = threading.Thread(target=l_state, args=())
led_thread.start()
fan_thread = threading.Thread(target=f_state, args=())
fan_thread.start()
heat_thread = threading.Thread(target=h_state, args=())
heat_thread.start()

def device_log_check():
    global water_state
    global led_state
    global fan_state
    global heat_state

    #query = "select * from device_log;"
    query = "select * from device_log order by time DESC limit 1;"
    cur.execute(query)
    result = cur.fetchall()
    i = result[0]

    fan_state = str(i[1])
    led_state = str(i[2])
    water_state = str(i[3])
    heat_state = str(i[4])

    new_co2_value = int(i[6])
    new_cds_value = int(i[7])
    new_soil_value = int(i[8])
    new_humi_value = int(i[9])
    new_temp_value = int(i[10])

    #print('device log :',i)

print("Thread Success")

while True:
    try:
         #if 'do not use' == 'now':
         if ser.readable():
             res = ser.readline()
             res = res.strip()
             res = res.decode()
             res = res.strip()
             data = res.split(':')
             if len(data) != 5:
                 print('error')
                 continue

             #data = [int(i) for i in data]

             co2_value = data[0]
             cds_value = data[1]
             soil_value = data[2]
             humi_value = data[3]
             temp_value = data[4]

             print('co2 :', co2_value, 'cds :', cds_value, 'soil :', soil_value, 'humi :', humi_value, 'temper :', temp_value)

             now = datetime.datetime.now()
             date = str(now.strftime('%y%m%d'))
             date_time = str(now.strftime('%H%M%S'))
             db_id = date+"_"+date_time

 query = "insert into sensor values('%s', NULL, %s, %s, %s, %s, %s, %s, %s)" % (db_id, co2_value, cds_value, soil_value, humi_value, temp_value, date, date_time)
             main_cur.execute(query)
             d_conn.commit()
             time.sleep(0.5)

         device_log_check()

    except KeyboardInterrupt:
        error_state = True
        break

GPIO.output(led, LOW)
GPIO.output(pump, LOW)
GPIO.output(heat, LOW)
GPIO.output(fan, LOW)

d_conn.close()

GPIO.cleanup()

