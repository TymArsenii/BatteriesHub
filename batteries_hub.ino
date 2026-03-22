/*
  1 - 0x596ADB5509646128;
  2 - 0xED81C95509646128;
  3 - 0x5BBFA55509646128;
*/

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

uint32_t request_timer=660000;
uint32_t wifi_connection_timer, wifi_check_timer, wifi_request_timer;
bool wifi_connected;
uint32_t mqtt_reconnect_timer;


double temps[5];
double temp_delta=0;

int16_t send_counter=120;
#define NORMAL_TEMP_PUSH_FREQ 1000 //ms
#define MIN_TEMP_PUSH_FREQ 1000 //ms

void setup()
{
  Serial.begin(115200);

  ds.requestTemp();

  WiFi.mode(WIFI_STA);
  WiFi.begin(SSID, PASS);

  client.setInsecure();

  mqtt.setServer(mqtt_server, 1883);
  mqtt.setCallback(callback);
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

  core0();
}

class Collector : public ghttp::HeadersCollector
{
 public:
  void header(Text &name, Text &value)
  {
    Serial.print(name);
    Serial.print(": ");
    Serial.println(value);
  }
};


void core0()
{
  if(WiFi.status()==WL_CONNECTED)
  {
    if (!wifi_connected)
    {
      if(millis()-wifi_check_timer>=2000)
      {
        Serial.print("Local IP: ");
        Serial.println(WiFi.localIP());

        wifi_connected=true;
      }
    }
  }
  else
  {
    if (millis()-wifi_connection_timer>=20000) //30 sec before trying to connect
    {
      wifi_connection_timer=millis();
      wifi_connected=false;
      WiFi.begin(SSID, PASS);
      wifi_check_timer=millis();
    }    
  } 
  if(wifi_connected)
  {
    if (http.available())
    {
      Collector collector;
      ghttp::Client::Response resp=http.getResponse(&collector);
      if (resp)
      {
        //Serial.println(resp.type());
        //Serial.println(resp.body().length());
        //resp.body().writeTo(Serial);
        Serial.println(resp.body());
      }
      else
      {
        Serial.println("response error");
      }
    }
    
    if(!mqtt.connected() && WiFi.status()==WL_CONNECTED)
    {
      reconnect();
    }
    if(mqtt.connected())
    {
      mqtt.loop();
      if(millis()-wifi_request_timer>=700)
      {
        wifi_request_timer=millis();
        
        char send_build[35];
        strcat(send_build, "&status=online-ok");
        mqtt.publish("batts_data", send_build);
      }
    }

    if(millis()-request_timer>=NORMAL_TEMP_PUSH_FREQ || (temp_delta>=0.5 && millis()-request_timer>=MIN_TEMP_PUSH_FREQ))
    {
      request_timer=millis();
      
      String post_data="t1=";
      post_data+=temps[1];
      post_data+="&t2=";
      post_data+=temps[2];
      post_data+="&t3=";
      post_data+=temps[3];

      char send_build[100];
      sprintf(send_build, "t1=%.2f&t2=%.2f&t3=%.2f", temps[1], temps[2], temps[3]);
      Serial.println(send_build);
      mqtt.publish("batts_data", send_build);

      if(send_counter>=60) //once a minute log to a server
      {
        send_counter=0;
        //Text post_data="t1=16&t2=32&t3=64";
        Text headers="Content-Type: application/x-www-form-urlencoded\r\n";

        http.request("/batteries_hub_temps.php", "POST", headers, send_build);
      }

      send_counter++;
    }
  }
}

void callback(char *topic, byte *message, unsigned int length)
{
  Serial.print("Message arrived on topic: ");
  Serial.print(topic);
  Serial.print(". Message: ");
  String message_tmp;

  for(int i=0; i<length; i++)
  {
    Serial.print((char)message[i]);
    message_tmp+=(char)message[i];
  }
  Serial.println();

  if(String(topic)=="ars-top-rx-strip_control-esp-alc")
  {
    if(mqtt.connected() && WiFi.status()==WL_CONNECTED && wifi_connected)
    {
      char send_build[35];
      strcat(send_build, "&status=received");
      mqtt.publish("strip_local_Ars", send_build);
    }

    gson::Parser p(20);
    int checksum=0;
    if (p.parse(message_tmp))
    {
      String msg_type=p["type"];
    }
  }
}

String make_client_id()
{
  uint32_t r = esp_random();
  char buf[32];
  snprintf(buf, sizeof(buf), "esp32_%08X", r);
  return String(buf);
}

void reconnect()
{
  // Loop until we're reconnected
  if(millis()-mqtt_reconnect_timer>=5000 && WiFi.status()==WL_CONNECTED && wifi_connected)
  {
    mqtt_reconnect_timer=millis();
    Serial.print("Attempting MQTT connection...");
    // Attempt to connect
    String client_id=make_client_id();
    if(mqtt.connect(client_id.c_str(), mqtt_user, mqtt_pass))
    {
      Serial.println("connected");
      Serial.print(mqtt.state());
      // Subscribe
      mqtt.subscribe("ars-top-rx-strip_control-esp-alc");
    }
    else
    {
      Serial.print("failed, rc=");
      Serial.print(mqtt.state());
      Serial.println(" try again in 5 seconds");
    }
  }
}
