/*
  1 - 0x596ADB5509646128;
  2 - 0xED81C95509646128;
  3 - 0x5BBFA55509646128;
*/

TaskHandle_t WiFi_task;

#include <GyverDS18Array.h>

uint64_t addr[]= 
{
  0x596ADB5509646128,
  0xED81C95509646128,
  0x5BBFA55509646128,
};
GyverDS18Array ds(4, addr, 3);

#include <GyverHTTP.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <GSON.h>

#include <PubSubClient.h>
const char *mqtt_server="mqtt.miracle.com.ua";
WiFiClient espClient;
PubSubClient mqtt(espClient);
const char *mqtt_user="ars_mqtt";
const char *mqtt_pass="88mAqNtSt";

#define SSID "i20"
#define PASS "yanatarsnazsof5"
WiFiClientSecure client;
ghttp::Client http(client, "exch.com.ua", 443);

#include <ArduinoOTA.h>
bool ota_initialized=false;
const char *ota_password="0609";

uint32_t request_timer=660000;
uint32_t wifi_connection_timer, wifi_check_timer, wifi_request_timer;
bool wifi_connected;
uint32_t mqtt_reconnect_timer;

bool auto_fans;
uint8_t fan1=0;
uint8_t fan2=0;

const uint8_t fan1_pin=15;
const uint8_t fan2_pin=16;

double temps[5];
double temp_delta=0;

bool settings_present=false;
uint32_t initial_settings_timer;

int16_t send_counter=120;
#define NORMAL_TEMP_PUSH_FREQ 1000 //ms
#define MIN_TEMP_PUSH_FREQ 1000 //ms

/*volatile uint32_t tach_pulses=0;

void IRAM_ATTR tach_isr() 
{ 
  tach_pulses++; 
}*/

void setup()
{
  Serial.begin(115200);

  pinMode(fan1_pin, INPUT_PULLUP);
  //attachInterrupt(digitalPinToInterrupt(fan1_pin), tach_isr, FALLING);
  pinMode(fan2_pin, INPUT_PULLUP);
  //attachInterrupt(digitalPinToInterrupt(fan2_pin), tach_isr, FALLING);

  ledcAttach(fan1_pin, 25000, 8);
  ledcAttach(fan2_pin, 25000, 8);

  ds.requestTemp();

  WiFi.mode(WIFI_STA);
  WiFi.begin(SSID, PASS);

  //---------------------------------------OTA--------------------------------------->

  ArduinoOTA.setHostname("batteries_hub");
  ArduinoOTA.setPassword(ota_password);

  ArduinoOTA.onStart([]()
  {
    Serial.println("BatteriesHub OTA started");
  });

  ArduinoOTA.onEnd([]()
  {
    Serial.println("\nBatteriesHub OTA completed");
  });

  ArduinoOTA.onProgress([](unsigned int progress, unsigned int total)
  {
    if(total>0)
    {
      Serial.printf("BatteriesHub OTA: %u%%\r", (unsigned int)((uint64_t)progress*100/total));
    }
  });

  ArduinoOTA.onError([](ota_error_t error)
  {
    Serial.printf("BatteriesHub OTA error: %u\n", error);
  });

  //<---------------------------------------OTA---------------------------------------

  client.setInsecure();

  mqtt.setServer(mqtt_server, 1883);
  mqtt.setCallback(callback);

  xTaskCreatePinnedToCore(core0, "WiFi_task", 8192, NULL, 1, &WiFi_task, 0);
}
void loop()
{
  if (ds.ready())
  {
    for (int i = 0; i < ds.amount(); i++)
    {
      //Serial.print("#");
      //Serial.print(i);
      //Serial.print(": ");

      if (ds.readTemp(i))
      {
        temps[i+1]=ds.getTemp();
        //Serial.print(temps[i+1]);
      }
      else
      {
        Serial.print("temp error");
      }
      //Serial.print(", ");
    }
    //Serial.println();

    ds.requestTemp();
  }

  if(ota_initialized && WiFi.status()==WL_CONNECTED)
  {
    ArduinoOTA.handle();
  }

  fans_processing();
}

void fans_processing()
{
  if(!auto_fans)
  {
    ledcWrite(fan1_pin, fan1);
    ledcWrite(fan2_pin, fan2);

    //Serial.print("fan1=");
    //Serial.println(fan1);
  }
  else if(auto_fans)
  {
    ;
  }
}
