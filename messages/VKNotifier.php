<?php
  
  require_once __DIR__ . '/../Services/BaseDataService.php';
  require_once 'VK.php';
  require_once 'VKException.php';
  
  class VKNotifier extends BaseDataService
  {
    //Максимальное количество дней которое пользователь должен не заходить в приложение, что-бы получить уведомление
	//По правилам VK
    const MAX_DAYS = 31;
	
	//Максимальное количество пользователей которым можно отправить сообщение за один вызов secure.sendNotification
	private $MAX_USERS_PER_MESSAGE = 100;
	
	//Максимальная длина сообщения
	const MAX_MESSAGE_LENGTH = 254;
	//Минимальная длина сообщения
	const MIN_MESSAGE_LENGTH = 16;
  
    const APP_ID     = '3395763';
	const APP_SECRET = 'umFAlFfSC6htif9qGuXX';
	
	private $vk;
	public $token;
	
	public function send( $message, $offset = 0, $max_days = -1, $token = null )
	{
	   $this->token = $token;    
	
	   $mlen = strlen( $message );
	   
	   if ( $mlen > self::MAX_MESSAGE_LENGTH )
	   {
	     throw new Exception( 'Сообщение слишком длинное. Максимальная длина сообщения ' . self::MAX_MESSAGE_LENGTH, 100 );
	   }
	   
	   if ( $mlen < self::MIN_MESSAGE_LENGTH )
	   {
	     throw new Exception( 'Сообщение слишком короткое. Минимальная длина сообщения ' . self::MIN_MESSAGE_LENGTH, 101 );
	   }
	   
	   if ( $max_days == -1 )
	   {
	     $max_days = self::MAX_DAYS;
	   }
	   
	   if ( $max_days > self::MAX_DAYS )
	   {
	     throw new Exception( 'Максимальное количество дней не должно быть больше ' . self::MAX_DAYS, 102 );
	   }
	   
	   $uids = $this->getUserIds( $offset, $max_days );
	   
	   if ( $uids !== null )
	   {
	     $this->authorizeToVKAPI();
		 $this->sendNotification( $message, $uids );
	   }
	   
	   return count( $uids );
	}
	
	private function sendNotification( $message, $uids )
	{
	  $users = $this->vk->api( 'secure.sendNotification', array( 'uids' => implode( ",", $uids ), 'message' => $message ) );
	}
	
	private function authorizeToVKAPI() 
	{
	  if ( $this->token )
	   {
	     $this->vk = new VK\VK( self::APP_ID, self::APP_SECRET, $this->token );
		 return;
	   }
	   
	   $this->vk = new VK\VK( self::APP_ID, self::APP_SECRET );
	   $this->token = $this->vk->getServerAccessToken();
	}
	
	private function getUserIds( $offset, $max_days )
	{
	  $r = mysql_query( "select net_user_id from {$this->users_table} where loged_in > DATE_SUB( NOW(),INTERVAL {$max_days} DAY) limit {$offset},{$this->MAX_USERS_PER_MESSAGE}" );
	  
	  if ( ! $r )
	  {
	    throw new Exception( mysql_error(), mysql_errno() );
	  }
	  
	  $uids = array();
	  
	  while( $row = mysql_fetch_assoc( $r ) )
	  {
	    $uids[] = $row[ 'net_user_id' ];
	  }
	  
	  if ( count( $uids ) == 0 )
	   return null;
	  
	  return $uids;
	}
	
	public function getTotalUsers( $max_days )
	{
	  $r = mysql_query( "select count(*) as count from {$this->users_table} where loged_in > DATE_SUB( NOW(),INTERVAL {$max_days} DAY)" );
	  
	  if ( ! $r )
	  {
	    throw new Exception( mysql_error(), mysql_errno() );
	  }
	  
	  $row = mysql_fetch_assoc( $r );
	  
	  return $row[ 'count' ];
	}
	
  }
  
?>