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
    @fwrite($f, 'strip:  '.date('Y-m-d H:i:s')."\n"); 
    @fclose($f);
  }
}
//Підключення до БД --<<

if(isset($_POST['t1']) && isset($_POST['t2']) && isset($_POST['t3']))
{
  $t1=floatval($_POST['t1']);
  $t2=floatval($_POST['t2']);
  $t3=floatval($_POST['t3']);

  $sql="INSERT IGNORE INTO `batteries_hub` 
        (`top`, `middle`, `bottom`, `date`)
        VALUES (?, ?, ?, ?)";

  $stmt=$db_connect->prepare($sql);

  $date=date('Y-m-d H:i:s');
  $stmt->bind_param
  (
    "iiis", 
    $t1, 
    $t2, 
    $t3, 
    $date
  );
  $stmt->execute();

  echo 'ok';
  exit;
}
else 
{
  echo 'no temperatures received';
  echo 'POST:'.print_r($_POST);
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batteries Hub</title>
  <script src="/libs/mqtt/mqtt.js"></script>
  <link rel="stylesheet" href="/general_style.css">
  <link rel="Website Icon" type="png" href="image/battery.png">
</head>
<body>
  <div class="opacity" id="opacity"></div>
    <div class="back" style="height:30px;">
      <a style="text-align:left;" href="/">&nbsp; ←Menu</a>
      <!--<button id="log_out" class="log_out">Log out</!button>-->
      <h1>Batteries Hub t°'s</h1>
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
  </div>

  <div class="footer">
    <a href="https://exch.com.ua/Bio.php" style="text-decoration:none; color:var(--blue_color);">Arsenii’s Technologies</a>
  </div>
</body>

<script>
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
});
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
      clearTimeout(offline_timer);
      offline_timer=setTimeout(() => 
      {
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
  }
});
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
