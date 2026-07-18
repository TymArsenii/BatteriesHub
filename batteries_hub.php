<?
//Підключення до БД -->>
define('DB_HOST', 'db23.freehost.com.ua');
define('DB_USER', 'lifelinem_exch');
define('DB_PASSWORD', 'dGjqFxa8p');
define('DB_DB', 'lifelinem_exch');
//--
$db_connect=@mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
if (!$db_connect) {echo 'DB connect ERROR'; exit;}
else
{
   //echo 'DB OK; ';
   //все ОК, підключились
   mysqli_select_db($db_connect, DB_DB) ;//or die(mysql_error());
   //--
   mysqli_query($db_connect, "SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'"); // ;//or die(mysql_error());
   mysqli_query($db_connect, "SET character_set_results='utf8', character_set_client='utf8', character_set_connection='utf8', character_set_database='utf8', 	character_set_server='utf8'"); // ;//or die(mysql_error());
   mysqli_query($db_connect, "SET names utf8"); // ;//or die(mysql_error());
   //mysql_set_charset('utf8');
   mb_internal_encoding('UTF-8');
   // PHP International Language and Locale codes demonstration
   setlocale(LC_ALL,'ru_RU.utf8'); //print_r(setlocale(LC_ALL, NULL)); //вивести інфу про локаль
}

require $_SERVER['DOCUMENT_ROOT'].'/hub/phpMQTT.php';

/*$server='test.mosquitto.org';
$port=1883;
$username='';
$password='';
$client_id=uniqid();*/

//this works ->
$server='mqtt.miracle.com.ua';
$port=1883;
$username='ars_mqtt';
$password='88mAqNtSt';
$client_id=uniqid();

$mqtt=new Bluerhinos\phpMQTT($server, $port, $client_id);

//V1
$log_file=$_SERVER['DOCUMENT_ROOT'].'/db_connect_log.txt';
//echo '<div>$log_file='.$log_file.'</div>';
//Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
if(1==1) {$f=@fopen($log_file,"a+");}
else {$f=@fopen($log_file,"w");} //Одразу очистити файл w
//--
if (!$f) {;} // Error
else
{
  if(flock($f,6))
  {
    @fwrite($f, 'batteries_hub:  '.date('Y-m-d H:i:s')."\n"); 
    @fclose($f);
  }
}
//Підключення до БД --<<

if(isset($_POST['t1']) && isset($_POST['t2']) && isset($_POST['t3']))
{
  $t1=floatval($_POST['t1']);
  $t2=floatval($_POST['t2']);
  $t3=floatval($_POST['t3']);

  $sql="INSERT IGNORE INTO `batteries_hub_log` 
        (`top`, `middle`, `bottom`, `date`)
        VALUES (?, ?, ?, ?)";

  $stmt=$db_connect->prepare($sql);

  $date=date('Y-m-d H:i:s');
  $stmt->bind_param
  (
    "ddds", 
    $t1, 
    $t2, 
    $t3, 
    $date
  );
  $stmt->execute();

  echo 'ok';
  exit;
}

if(isset($_POST['fan_speeds']) && $_POST['fan_speeds']=='1')
{
  //$auto_fans=intval($_POST['auto_fans']);
  $auto_fans=0;
  $fan1=intval($_POST['fan1']);
  $fan2=intval($_POST['fan2']);
  $power1=intval($_POST['power1']);
  $power2=intval($_POST['power2']);

  $sql="UPDATE `batteries_hub_settings` SET
        `auto_fans`=?, 
        `fan1`=?,
        `fan2`=?, 
        `power1`=?, 
        `power2`=?";

  $stmt=$db_connect->prepare($sql);

  // Bind parameters
  $stmt->bind_param
  (
    "iiiii",
    $auto_fans,
    $fan1,
    $fan2,
    $power1,
    $power2
  );

  $stmt->execute();

  if($power1==0) $fan1=0;
  if($power2==0) $fan2=0;

  if ($mqtt->connect(true, NULL, $username, $password)) 
  {
    $data_send=array
    (
      'auto_fans'=>$auto_fans,
      'fan1'=>$fan1,
      'fan2'=>$fan2
    );

    $mqtt->publish('batteries_hub_settings', json_encode($data_send), 0, false);
    $mqtt->close();
    //V1
    $log_file=$_SERVER['DOCUMENT_ROOT'].'/strip.txt';
    //echo '<div>$log_file='.$log_file.'</div>';
    //Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
    if(1==2) {$f=@fopen($log_file,"a+");}
    else {$f=@fopen($log_file,"a+");} //Одразу очистити файл w
    //--
    if (!$f) {;} // Error
    else
    {
      if(flock($f,6))
      {
        @fwrite($f, json_encode($data_send)); 
        @fclose($f);
      }
    }
    exit;
  } 
  else 
  {
    echo "Time out!\n";
  } 

  echo 'ok';
  exit;
}

if(isset($_POST['give_settings']) && $_POST['give_settings']=='1')
{
  $sql="SELECT * FROM `batteries_hub_settings`";
  $result=$db_connect->query($sql);
  if($result)
  {
    $row=$result->fetch_assoc();
    echo json_encode($row);
  }
  else
  {
    echo 'error';
  }
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batteries Hub</title>
  <meta name="theme-color" content="#a2deae">
  <script src="/libs/mqtt/mqtt.js"></script>
  <link rel="stylesheet" href="/general_style.css">
  <link rel="stylesheet" href="/apple_sliders/apple_sliders.css">
  <link rel="Website Icon" type="png" href="image/battery.png">
</head>
<body>
  <div class="opacity" id="opacity"></div>
    <div class="back" style="height:30px;">
      <a style="text-align:left;" href="/">&nbsp; ←Menu</a>
      <!--<button id="log_out" class="log_out">Log out</!button>-->
      <h1>Batteries Hub</h1>
    </div>
    <div style="padding-bottom:30px;"></div>

  <div id="main_div">
    <div class="dropdown_container" id="container" style="margin-top:20px; display:flex; flex-direction:column; justify-content:space-between;">
      <div style="display:flex; width:100%; flex-direction:row; flex-wrap:nowrap;"> <p>Top:</p>     <p id="id1" style="margin-left:auto;">loading...</p> </div>
      <hr class="hr_menu">
      <div style="display:flex; width:100%; flex-direction:row; flex-wrap:nowrap;"> <p>Middle:</p>  <p id="id2" style="margin-left:auto;">loading...</p> </div>
      <hr class="hr_menu">
      <div style="display:flex; width:100%; flex-direction:row; flex-wrap:nowrap;"> <p>Bottom:</p>  <p id="id3" style="margin-left:auto;">loading...</p> </div>
      <hr class="hr_menu">
      <div style="display:flex; width:100%; flex-direction:row; flex-wrap:nowrap;"> <p>Max ΔT:</p>  <p id="id4" style="margin-left:auto;">loading...</p> </div>
      <hr class="hr_menu">

      <img id="device_state_img" style="margin-top:10px; align-self:baseline; display:none; width:100px; height:auto;">
    </div>

    <div class="dropdown_container">
      <label style="color:var(--blue_color); text-align:center; margin:0px 0 10px;">Fan speeds</label>


      <div class="dropdown_container" style="width:calc(100% - 20px);">
        <label style="color:var(--blue_color); text-align:center; margin:0px 0 10px;">Fan 1</label>
        <div style="display:flex; justify-content: space-between; width: calc(100% - 5px); margin-bottom:5px;">
          <label class="label_for_checkbox" style="margin:0;">Power</label>
          <div>
            <label class="apple-switch" style="margin:-2px;">
              <input type="checkbox" id="power1" onchange="document.getElementById('fan1_slider_div').style.opacity=this.checked ? '1' : '0.4';" class="checkbox-custom">
              <span class="slider" style="margin:0; margin-top:0px;"></span>
            </label>
          </div>
        </div>

        <div class="slider_container" id="fan1_slider_div" style="margin:0; width:100%;">
          <div class="apple_range_slider_container_horizontal" style="height:45px; width:100%;"> 
            <div class="apple_range_slider_fill_horizontal" id="fan1_slider_fill"></div>
            <!--<div class="apple_range_slider_bottom_text_horizontal">Fan 1</div>-->
            <div class="apple_range_slider_top_text_horizontal" id="fan1_top_text">128</div>
            <input type="range" class="input_horizontal" min="90" max="255" value="128" id="fan1" oninput="slider_processing('fan1')" name='horizontal'>
          </div>
        </div>
      </div>
        
      <div class="dropdown_container" style="width:calc(100% - 20px);">
        <label style="color:var(--blue_color); text-align:center; margin:0px 0 10px;">Fan 2</label>
        <div style="display:flex; justify-content: space-between; width: calc(100% - 5px); margin-bottom:5px;">
          <label class="label_for_checkbox" style="margin:0;">Power</label>
          <div>
            <label class="apple-switch" style="margin:-2px;">
              <input type="checkbox" id="power2" onchange="document.getElementById('fan2_slider_div').style.opacity=this.checked ? '1' : '0.4';" class="checkbox-custom">
              <span class="slider" style="margin:0; margin-top:0px;"></span>
            </label>
          </div>
        </div>

        <div class="slider_container" id="fan2_slider_div" style="width:100%; margin:0;">
          <div class="apple_range_slider_container_horizontal" style="height:45px; width:100%;"> 
            <div class="apple_range_slider_fill_horizontal" id="fan2_slider_fill"></div>
            <!--<div class="apple_range_slider_bottom_text_horizontal">Fan 2</div>-->
            <div class="apple_range_slider_top_text_horizontal" id="fan2_top_text">128</div>
            <input type="range" class="input_horizontal" min="90" max="255" value="128" id="fan2" oninput="slider_processing('fan2')" name='horizontal'>
          </div>
        </div>
      </div>

      <button class="main_button" id="fans_apply" style="margin-bottom:0;">Apply</button>
    </div>
  </div>

  <div class="footer">
    <a href="https://exch.com.ua/Bio.php" style="text-decoration:none; color:var(--blue_color);">Arsenii’s Technologies</a>
  </div>
</body>

<script>
var device_curr_state=0;

document.addEventListener('DOMContentLoaded', () => 
{
  function is_ipados() 
  {
    const ua=navigator.userAgent;
    const is_ipad=ua.includes("iPad") || (ua.includes("Macintosh") && 'ontouchend' in document);
    if (!is_ipad) return false;
    else return true;
  }
  window.addEventListener('resize', set_width);
  set_width();
  function set_width()
  {
    let width=window.innerWidth;
    if(width>450) width=450;
    document.getElementById("main_div").style.width=width-25+"px";

    if (!is_ipados()) 
    {
      const back_a=document.querySelector('.back a');
      if(back_a) 
      {
        back_a.style.marginLeft='0';
        back_a.style.marginTop='0';
      }
    } 
    else 
    {
      let width=window.innerWidth;
      if(width>400)
      {
        const back_a=document.querySelector('.back a');
        const back=document.querySelector('.back');
        if(back_a) 
        {
          back.style.paddingTop='0px';
          back_a.style.marginLeft='45px';
          back_a.style.marginTop='0px';
        }
      }
      else 
      {
        const back_a=document.querySelector('.back a');
        const back=document.querySelector('.back');
        if(back_a) 
        {
          back.style.paddingTop='35px';
          back_a.style.marginLeft='0px';
        }
      }
    }
  }


  let send_build;
  send_build='give_settings=1';

  console.log(send_build);

  var url='batteries_hub_temps.php';

  fetch(url, 
  {
    method:'POST',
    headers:
    {
      'Content-Type':'application/x-www-form-urlencoded' //application/x-www-form-urlencoded
    },
    body:new URLSearchParams(send_build)
  })
  .then(response => response.text())
  .then(text => 
  {
    console.log('sent');
    console.log(text);

    try 
    {
      const settings=JSON.parse(text);
      document.getElementById("fan1").value=settings.fan1;
      document.getElementById("fan2").value=settings.fan2;

      document.getElementById("power1").checked=settings.power1==1;
      document.getElementById("power2").checked=settings.power2==1;

      document.getElementById('fan1_slider_div').style.opacity=document.getElementById("power1").checked ? '1' : '0.4';
      document.getElementById('fan2_slider_div').style.opacity=document.getElementById("power2").checked ? '1' : '0.4';

      slider_processing('fan1');
      slider_processing('fan2');
    } 
    catch (error) 
    {
      console.error('Error parsing JSON:', error);
    }
  })
  .catch(error => 
  {
    console.error('Error:', error);
  })
});


document.getElementById("fans_apply").addEventListener('click', function()
{
  if(device_curr_state==1) 
  {
    document.getElementById("fans_apply").innerHTML='Apply <div class="loader" style="clear:both; display:inline-block;"></div>';
    document.getElementById("fans_apply").style.backgroundColor='';
    document.getElementById("fans_apply").style.borderColor='';

    apply_timeout=setTimeout(timed_out, 4000);
  }
  else if(device_curr_state==0) 
  {
    document.getElementById("fans_apply").innerHTML='Device is offline';
    document.getElementById("fans_apply").style.backgroundColor='red';
    document.getElementById("fans_apply").style.borderColor='red';

    setTimeout(reset_button_color, 800);
  }


  let fan1_value=document.getElementById("fan1").value;
  let fan2_value=document.getElementById("fan2").value;
  let power1=document.getElementById("power1").checked ? 1 : 0;
  let power2=document.getElementById("power2").checked ? 1 : 0;

  let send_build;
  send_build='fan_speeds=1&fan1='+fan1_value+'&fan2='+fan2_value+'&power1='+power1+'&power2='+power2;

  console.log(send_build);

  var url='batteries_hub_temps.php';

  fetch(url, 
  {
    method:'POST',
    headers:
    {
      'Content-Type':'application/x-www-form-urlencoded' //application/x-www-form-urlencoded
    },
    body:new URLSearchParams(send_build)
  })
  .then(response => response.text())
  .then(text => 
  {
    console.log('sent');
    console.log(text);
  })
  .catch(error => 
  {
    console.error('Error:', error);
  })
});

function timed_out()
{
  document.getElementById("fans_apply").innerHTML='Timed out';
  document.getElementById("fans_apply").style.backgroundColor='#fcba03';
  document.getElementById("fans_apply").style.borderColor='#fcba03';

  setTimeout(reset_button_color, 800);
}
function reset_button_color()
{
  document.getElementById("fans_apply").innerHTML='Apply';
  document.getElementById("fans_apply").style.backgroundColor='';
  document.getElementById("fans_apply").style.borderColor='';
}


//MQTT ->
//const client=mqtt.connect('wss://test.mosquitto.org:8081/mqtt');
const client_id="web_client_" + Math.random().toString(16).substr(2, 8);
const client=mqtt.connect("wss://mqtt.miracle.com.ua:9001", 
{
  clientId: client_id,
  clean: true,
  username: "ars_mqtt",
  password: "88mAqNtSt",
  reconnectPeriod: 1000
});

client.on('connect', () => //When connected
{
  console.log('Connected to test.mosquitto.org');

  const topic='batts_data';
  client.subscribe(topic, (err) => 
  {
    if (!err) 
    {
      //console.log(`Subscribed to ${topic}`);
      console.log('subscribed');
    } 
    else 
    {
      //console.error('Subscription error:', err);
    }
  });
});

let offline_timer;
// When a message is received
client.on('message', (topic, message) => 
{
  //console.log(`Message received on ${topic}:${message.toString()}`);
  let incomming_message=message.toString();
  console.log(`Message received:${incomming_message}`);

  const queryString=incomming_message;
  const params=new URLSearchParams(queryString);

  if(params.has('t1') && params.has('t2') && params.has('t3'))
  {
    let t1=parseFloat(params.get('t1'));
    let t2=parseFloat(params.get('t2'));
    let t3=parseFloat(params.get('t3'));

    t1=t1.toFixed(1);
    t2=t2.toFixed(1);
    t3=t3.toFixed(1);

    document.getElementById('id1').textContent=`${t1}°C`;
    document.getElementById('id2').textContent=`${t2}°C`;
    document.getElementById('id3').textContent=`${t3}°C`;

    const max_delta=(a, b, c) => Math.abs(Math.max(a, b, c)-Math.min(a, b, c));
    let delta=max_delta(t1, t2, t3);
    delta=delta.toFixed(1);
    document.getElementById('id4').textContent=`${delta}°C`;
  }

  if(params.has('status'))
  {
    if(params.get("status")=="online-ok")
    {
      device_curr_state=1;

      clearTimeout(offline_timer);
      offline_timer=setTimeout(() => 
      {
        device_curr_state=0;

        const is_dark_mode=window.matchMedia('(prefers-color-scheme:dark)').matches;

        document.getElementById("device_state_img").style.display='flex';
        document.getElementById("device_state_img").src="/image/offline_indicator.svg"; 
        
        document.getElementById('container').style.backgroundColor=is_dark_mode?'rgba(244, 171, 159, 0.6)':'rgba(244, 171, 159, 0.4)';
      }, 3000);

      const is_dark_mode=window.matchMedia('(prefers-color-scheme:dark)').matches;

      document.getElementById("device_state_img").style.display='flex';
      document.getElementById("device_state_img").src="/image/online_indicator.svg"; 

      document.getElementById('container').style.backgroundColor=is_dark_mode?'rgba(123, 183, 129, 0.55)':'rgba(123, 183, 129, 0.2)';
    }
    if(params.get("status")=="received")
    {
      clearTimeout(apply_timeout);
      
      document.getElementById("fans_apply").innerHTML='Apply';
      document.getElementById("fans_apply").style.backgroundColor='green';
      document.getElementById("fans_apply").style.borderColor='green';

      setTimeout(function()
      {
        reset_button_color();
      }, 800);
    }
  }
});

function slider_processing(slider_id)
{
  const slider_max=document.getElementById(slider_id).max;
  const slider_min=document.getElementById(slider_id).min;

  let slider_raw_value=document.getElementById(slider_id).value;
  console.log('Ars\'sTechs ~ :277 ~ $slider_raw_value=', slider_raw_value);
    
  const slider_value=map(slider_raw_value, slider_min, slider_max, 0, 100);
  const slider_fill=document.getElementById(slider_id+'_slider_fill');

  const horizontal=document.getElementById(slider_id).classList.contains('input_horizontal');
  if(horizontal) 
  {
    slider_fill.style.width=slider_value+"%";
  } 
  else 
  {
    slider_fill.style.height=slider_value+"%";
  }

  const slider_top_text=document.getElementById(slider_id+'_top_text');
  let text_build=Math.round(map(slider_raw_value, slider_min, slider_max, 35, 100));
  text_build+='%';
  slider_top_text.innerHTML=text_build;
}
function map(x, in_min, in_max, out_min, out_max) 
{
  return (x - in_min)*(out_max - out_min)/(in_max - in_min) + out_min;
}

let keys_pressed={};
window.onkeydown=function(event) 
{
  keys_pressed[event.key]=true;
  if(event.key=="Alt") event.preventDefault();
};

window.onkeyup=function(event) 
{
  keys_pressed[event.key]=false;
};
const slider_ids=['fan1', 'fan2'];

slider_ids.forEach(slider_id => 
{
  const slider_container=document.getElementById(slider_id + '_slider_div');

  // Touch events
  slider_container.addEventListener('touchstart', (event) => hande_touch(event, slider_id));
  slider_container.addEventListener('touchmove', (event) => hande_touch(event, slider_id));
  slider_container.addEventListener('touchend', (event) => hande_touch(event, slider_id));

  // Mouse scroll events
  slider_container.addEventListener('wheel', (event) => 
  {
    if (event.deltaMode === 0 && Math.abs(event.deltaY) < 15) return;
    
    event.preventDefault();
    const slider=document.getElementById(slider_id);
    //console.log(slider.id);

    let step=1;
    if (!keys_pressed['Alt']) 
    {
      step=5;
    }
    if (event.deltaY < 0) 
    {
      slider.value=Math.min(parseInt(slider.value) + step, slider.max);
    } 
    else 
    {
      slider.value=Math.max(parseInt(slider.value) - step, slider.min);
    }
    slider_processing(slider_id);
  });
});

function hande_touch(event, slider_id) 
{
  event.preventDefault();

  const horizontal=document.getElementById(slider_id).classList.contains('input_horizontal');

  const slider_container=document.getElementById(slider_id).parentElement;
  const touch=event.touches[0] || event.changedTouches[0];
  const rect=slider_container.getBoundingClientRect();

  let percentage;
  if(!horizontal)
  {
    const touchY=touch.clientY;
    const relativeY=rect.bottom - touchY;
    
    percentage=Math.min(Math.max((relativeY/rect.height)*100, 0), 100);
  }
  else
  {
    const touchX=touch.clientX;
    const relativeX=touchX - rect.left;
    
    percentage=Math.min(Math.max((relativeX/rect.width)*100, 0), 100);
  }

  const slider_max=parseFloat(document.getElementById(slider_id).max);
  const slider_min=parseFloat(document.getElementById(slider_id).min);
  const sliderValue=Math.round(map(percentage, 0, 100, slider_min, slider_max));

  if (sliderValue >= slider_min && sliderValue <= slider_max) 
  {
    document.getElementById(slider_id).value=sliderValue;
    slider_processing(slider_id);
  }
}
</script>

<style>
  p
  {
    font-size:25px;
    align-self:baseline;
    margin:0;
    margin-bottom:10px;
  }
</style>
</html>
