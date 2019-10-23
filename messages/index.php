<?php

/*** CONFIG ***/
$auth = true;		// Set to false to disable authentication
$user = "snowbird";
$pw = "zzF67K5Lhvnjls4";

// Inline media
if (isset($_GET['img']) && $_GET['img']) {
    $img = strtolower($_GET['img']);
    $imgs['dnarr'][0] = 199;
    $imgs['dnarr'][1] = 'H4sIAAAAAAAAA3P3dLOwTORlEGBoZ2BYsP3Y0t0nlu85ueHQ2U1Hzu86efnguetHL968cPPBtbuPbzx4+vTV24+fv3768u3nr9+/f//59+/f////GUbBKBgWQPEnCzMDgyCDDogDyhMMHP4MyhwyHhsWHGzmENaKOSHAyMDAKMWTI/BAkYmDTU6oQuAhY2M7m4JLgcGDh40c7HJ8BQaBBw4z8bMaaOx4sPAsK7voDZ8GAadTzEqSXLJWBgoM1gBhknrUcgMAAA==';
    $imgs['uparr'][0] = 201;
    $imgs['uparr'][1] = 'H4sIAAAAAAAAA3P3dLOwTORlEGBoZ2BYsP3Y0t0nlu85ueHQ2U1Hzu86efnguetHL968cPPBtbuPbzx4+vTV24+fv3768u3nr9+/f//59+/f////GUbBKBgWQPEnCzMDgyCDDogDyhMMHIEMyhwyHhsWHGzmENaKOTFBoYWZgc/BYQVDw1EWdvGIOzsWJDAzinFHiBxIWNDMKMbv0sCR0NDMIcATofJB4RAzkxivg0OCoUNzIy9ThMuFDRqHGxisAZtUvS50AwAA';

    if (!$imgs[$img] || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === false) 
        exit();

    header("Expires: ".gmdate("D, d M Y H:i:s", time()+(86400*30))." GMT");
    header("Last-Modified: ".gmdate("D, d M Y H:i:s", time())." GMT");
    header('Content-Length: '.$imgs[$img][0]);
    header('Content-Type: image/gif');
    header('Content-Encoding: gzip');

    echo base64_decode($imgs[$img][1]);
    exit();
}

// Authenticate before proceeding
if ($auth && (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
        $_SERVER['PHP_AUTH_USER'] != $user || $_SERVER['PHP_AUTH_PW'] != $pw)) {
    header('WWW-Authenticate: Basic realm="eAccelerator control panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
} 

$sec = isset($_GET['sec']) ? (int)$_GET['sec'] : 0;

// No-cache headers
header('Content-Type: text/html; charset=utf-8');
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$state = null;

if ( ! isset( $_REQUEST[ 'message' ] ) || ! isset( $_REQUEST[ 'max_days' ] ) )
 {
   $state = 'enterText';
 }
 else
 {
    try
				 {
				   $message  = $_REQUEST[ 'message' ];
				   $max_days = $_REQUEST[ 'max_days' ];
				   $offset   = isset( $_REQUEST[ 'offset' ] ) ? $_REQUEST[ 'offset' ] : 0;
				   $token    = isset( $_REQUEST[ 'token' ] )  ? $_REQUEST[ 'token' ] : null;
				   
				   require 'VKNotifier.php';
				   
				   $notifier = new VKNotifier();
				   $total = $notifier->getTotalUsers( $max_days );
				   $count = $notifier->send( $message, $offset, $max_days, $token );
				   $token = $notifier->token;
				   
				   if ( $count == 0 )
				   {
				     $state = 'done';
				   }
				   else
				   {
				     $state = 'processing';
					 $offset += $count;
					 $encodedMessage = rawurlencode( $message );
					 header( "refresh:1;url=?message={$encodedMessage}&max_days={$max_days}&offset={$offset}&token={$token}" );
				   }
				 }
				 catch ( Exception $e )
	             {
				    $errorText = $e->getMessage();
					$state = 'error';
				 }
 }
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>Форма отправки сообщений пользователя приложения Музыкальный Конструктор ВКонтакте</title>
    <style>
	body {
     font-family: tahoma,arial,verdana,sans-serif,Lucida Sans;
     font-size: 11px;
	 margin: 0; padding: 0;
	 }
    </style>
  </head>
  <body>
    <table style='width:100%;height:100%;'>
		<tr>
			<td style='text-align:center;vertical-align:middle;'>
				<?php
				 if ( $state == 'enterText' )
				 {
				?>
				<form method="GET">
					<table>
						<tr>
							<td><h3>Текст сообщения</h3></td>
						</tr>
						<tr>
							<td><textarea name="message" cols="64" rows="16"></textarea></td>
						</tr>
						<tr>
							<td><h3>Максимальное количество дней с последнего посещения пользователем приложения</h3></td>
						</tr>
						<tr>
							<td>
								<select name="max_days">
						<?php
						 for( $i = 1; $i <= 31; $i ++ )
						  {
						     echo "<option value='{$i}'";
							 
							 if ( $i == 31 )
							  echo " selected";
							 
							 echo ">{$i}</option>";
						  }
						?>
					</select>
							</td>
						</tr>
						<tr>
							<td align="right"><button type="submit">Отправить</button></td>
						</tr>
					</table>
				</form>
				 <?php 
				 exit();
				 }
				 if ( $state == 'processing' )
				 {
				   echo "<h1>Отправлено {$offset} сообщений из {$total}...</h1>";
				 }
				 else
				 if ( $state == 'done' )
				 {
				   echo "<h1>Рассылка сообщений успешно завершена!!!</h1>";
				   echo "<h3>Сообщение отправлено {$total} пользователям</h3>";
				 }
				 else
				 if ( $state == 'error' )
				 {
				   echo "<h1>{$errorText}</h1>";
				 }
				 ?>
			</td>
		</tr>
	</table>
  </body>
</html>

