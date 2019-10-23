<?php
  require_once __DIR__ . '/BaseDataService.php';
  
  class Pusher extends BaseDataService
  {
    protected $COMMAND_UPDATE_DATA = 'data';
	protected $COMMAND_SHOW_MESSAGE = 'message';
	
	protected $MESSAGE_TYPE_BONUS = 'bonus';
	protected $MESSAGE_TYPE_FRIEND_INVITED = 'friend_invited';
  
  //Добавляет определенную команду для проталкивания клиенту
  protected function addCommand( $user_id, $name, $params )
  {
    $r = mysql_query( "insert into {$this->commands_table} ( user_id, name, params ) values( {$user_id}, '{$name}', '{$params}' )"  );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	$this->setUserUpdateStatus( $user_id, 1 );
  }
  
  //Проверяет есть ли у пользователя новые команды
  //true - есть
  //false - нет
  protected function commandsExists( $user_id )
  {
    $r = mysql_query( "select count( * ) as count from {$this->commands_table} where user_id={$user_id}" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	$row = mysql_fetch_assoc( $r );
	
	return $row[ 'count' ] > 0;
  }
  
  //Очищает список комманд сессии
  protected function clearCommands( $user_id )
  {
    $r = mysql_query( "delete from {$this->commands_table} where user_id={$user_id}" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	$this->setUserUpdateStatus( $user_id, 0 );
  }
  
  //Если пользователь online, устанавливает значение "есть новые команды" в 0 или 1
  private function setUserUpdateStatus( $user_id, $status )
  {
    $r = mysql_query( "update {$this->session_table} set commands={$status} where user_id={$user_id}" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() ); 
  }
  
  //Обрабатывает список команд и очищает его	
  protected function pushCommands( $user_id, $object )
  {
	  $r = mysql_query( "select name, params from {$this->commands_table} where user_id={$user_id}" );
	  
	  if ( ! $r )
	   throw new Exception( mysql_error(), mysql_errno() );
	  
	  while ( $command = mysql_fetch_assoc( $r ) )
	   {
	     switch( $command[ 'name' ] )
		  {
		    case $this->COMMAND_UPDATE_DATA : {
			  $params = $this->parseUpdateParams( $command[ 'params' ] );
			  
			  if ( count( $params ) > 0 )
			   {
			      $g = mysql_query( "select {$params[ $this->users_table ]} from {$this->users_table} where id={$user_id}" );
		   
		          if ( ! $g )
		           throw new Exception( mysql_error(), mysql_errno() );
			
		          if ( ! isset( $object->{ $this->COMMAND_UPDATE_DATA } ) ) 
				  {
				    $object->{ $this->COMMAND_UPDATE_DATA } = new stdClass();
				  }
				  
				  $object->{ $this->COMMAND_UPDATE_DATA }->{ $this->users_table } = mysql_fetch_assoc( $g );
			   }		 
			  break;
			}
			
			case $this->COMMAND_SHOW_MESSAGE : {
			  $params = $this->parseMessageParams( $command[ 'params' ] );
			  
			  if ( ! isset( $object->{ $this->COMMAND_SHOW_MESSAGE } ) )
			  {
			    $object->{ $this->COMMAND_SHOW_MESSAGE } = array();
			  }
			  
			  $object->{ $this->COMMAND_SHOW_MESSAGE }[] = $params;
			  break;
			}
		  }
		  
	   }
	   	 
	   $this->clearCommands( $user_id );
	}
  
  //Парсит команду типа сообщение
  //message::type=bonus;money=10;diamonds=15;
  private function parseMessageParams( $str )
  {
    $message = explode( "::", $str );
	$data = array();
	$data[ 'message' ] = $message[ 0 ];
	$params = explode( ";", substr( $message[ 1 ], 0, strlen( $message[ 1 ] ) - 1 ) );
	
	foreach ( $params as $value )
	{
	  $s = explode( "=", $value );
	  $data[ $s[ 0 ] ] = $s[ 1 ];
	}
	
	return $data;
  }	
	
  //Парсит список команд сообщающих какие данные необходимо протолкнуть на клиент
  //table_name:field1,field2;table_name:field1,field2,field3;
  private function parseUpdateParams( $str )
  {
    $commands = explode( ";", $str );
    $data = array();

    foreach ( $commands as $value )
    {
      $table  = explode( ":", $value );
	
	  if ( count( $table ) > 1 )
	  {
	   $fields = explode( ",", $table[ 1 ] );
	  
	   foreach ( $fields as $subValue )
	   {
	    $data[ $table[ 0 ] ][ $subValue ] = $subValue;
	   }
	 }
   }
   
   foreach( $data as $key => $value )
   {
	$data[ $key ] = implode( ",", $value );
   }
   
   return $data;
  }
  }
?>