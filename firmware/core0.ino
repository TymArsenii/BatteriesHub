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

char version[]="v0.1 — OTA test";

void core0(void *pvParameters)
{
  for(;;)
  {
    if(WiFi.status()==WL_CONNECTED)
    {
      if(!wifi_connected)
      {
        if(millis()-wifi_check_timer>=2000)
        {
          Serial.print("Local IP: ");
          Serial.println(WiFi.localIP());

          wifi_connected=true;
        }
      }

      if(!ota_initialized)
      {
        ArduinoOTA.begin();
        ota_initialized=true;

        Serial.println("Batteries Hub OTA ready");
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
      if(!settings_present)
      {
        if(millis()-initial_settings_timer>=8000)
        {
          initial_settings_timer=millis();
          if(mqtt.connected() && WiFi.status()==WL_CONNECTED)
          {
            Text headers="Content-Type: application/x-www-form-urlencoded\r\n";
            http.request("/batteries_hub_temps.php", "POST", headers, "get_data=1");
            Serial.println("http initial settings requested");
          }
        }
      }
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
          strcat(send_build, "&status=online-ok&version=");
          strcat(send_build, version);
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
    vTaskDelay(pdMS_TO_TICKS(10));
    yield();
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

  if(String(topic)=="batteries_hub_settings")
  {
    if(mqtt.connected() && WiFi.status()==WL_CONNECTED && wifi_connected)
    {
      char send_build[35];
      strcat(send_build, "status=received");
      mqtt.publish("batts_data", send_build);
    }

    gson::Parser p(20);
    int checksum=0;
    if (p.parse(message_tmp))
    {
      settings_present=true;

      uint8_t auto_fans_tmp;
      auto_fans_tmp=p["auto_fans"];
      auto_fans=auto_fans_tmp;
      fan1=constrain(p["fan1"], 0, 255);
      fan2=constrain(p["fan2"], 0, 255);

      Serial.print("auto_fans=");
      Serial.println(auto_fans);
      Serial.print("fan1=");
      Serial.println(fan1);
      Serial.print("fan2=");
      Serial.println(fan2);
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
      mqtt.subscribe("batteries_hub_settings");
    }
    else
    {
      Serial.print("failed, rc=");
      Serial.print(mqtt.state());
      Serial.println(" try again in 5 seconds");
    }
  }
}
