import RPi.GPIO as GPIO
import threading
import time
import serial

ser = serial.Serial("/dev/ttyACM0", 9600)

GPIO.setmode(GPIO.BCM)
GPIO.setup(6,GPIO.OUT)
GPIO.setup(7,GPIO.OUT)

tempStr = ""
data = []
co2Value, cdsValue, soilValue, humValue, temperValue = 0, 0, 0, 0, 0


def step(dir, steps):
    GPIO.output(6, dir)
    time.sleep(0.005)
    for i in range(0, steps):
        GPIO.output(7, True)
        time.sleep(0.0005)
        GPIO.output(7, False)
        time.sleep(0.0005)


t = threading.Thread(target=step, args=(True, 1600*2))
t.start()

while True:
    try:
         if ser.readable():
             res = ser.readline()
             res = res.strip()
             data = res.split(':')
             if len(data) != 5:
                print('error')
                continue

             data = [int(i) for i in data]

             co2Value = data[0]
             cdsValue = data[1]
             soilValue = data[2]
             humValue = data[3]
             temperValue = data[4]

             print('co2Value : ', co2Value)
             print('cdsValue : ', cdsValue)
             print('soilValue : ', soilValue)
             print('humValue : ', humValue)
             print('temperValue : ', temperValue)
             print('==========================')

    except KeyboardInterrupt:
        GPIO.cleanup()
        break;
