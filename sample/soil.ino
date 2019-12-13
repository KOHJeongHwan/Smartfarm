
void setup() {
  Serial.begin(9600); //통신 속도 설정

}

void loop() {

  int err;
  float temp;

  temp = analogRead(0);
  if(temp != NULL) {
    Serial.print("temp = ");
    Serial.print(temp);
    Serial.println();
  }
  delay(1000);
}
