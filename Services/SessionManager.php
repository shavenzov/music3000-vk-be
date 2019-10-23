<?php

require_once __DIR__ . '/Pusher.php';

class SessionManager extends Pusher
{
  const secret = "muAflFfSC6xtir9qGuFF";
  
  protected $SESSION_NOT_FOUND_ERROR = -100;
  
  protected function startSession( $data )
  {
    //Если открыта предыдущая сессия, то сбрасываем её
    $this->flushSession( $data[ 'id' ] );
	
	//Открываем новую сессию
	$session_id = md5( $data[ 'id' ] . '/' . self::secret . '/' . $data[ 'net_user_id' ] . '/' . time() );
	$commands = $this->commandsExists( $data['id'] ) ? 1 : 0;
	
	$r = mysql_query( "insert into {$this->session_table} (session_id,user_id,pro_expired,pro,time,commands) values( '{$session_id}',{$data['id']},'{$data[ 'pro_expired_timestamp' ]}',{$data['pro']},0,{$commands} )" );
	
	if ( ! $r )
	{
	  throw new Exception( mysql_error(), mysql_errno() );
	}
	
	return $session_id;
  }
    
  protected function updateSession( $session_id, $time )
  {
    $r = mysql_query( "update {$this->session_table} set time={$time} where session_id='{$session_id}'" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
  }
  
  protected function getSessionData( $session_id, $fields = 'user_id' ) 
  {
    $r = mysql_query( "select {$fields} from {$this->session_table} where session_id='{$session_id}'" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	if ( mysql_num_rows( $r ) > 0 )
	 {
	   return mysql_fetch_assoc( $r );
	 }
	 
	return false; 
  }
  
  protected function sessionExists( $session_id )
  {
    $r = mysql_query( "select session_id from {$this->session_table} where session_id='{$session_id}'" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	return mysql_num_rows( $r ) > 0; 
  }
  
  private function flushSession( $user_id )
  {
    $r = mysql_query( "select session_id, time from {$this->session_table} where user_id='{$user_id}'" );
	
	if ( ! $r )
	 throw new Exception( mysql_error(), mysql_errno() );
	 
	if ( mysql_num_rows( $r ) > 0 )
	 {
	   $row = mysql_fetch_assoc( $r );
	   
	   $r = mysql_query( "update {$this->users_table} set time=time+{$row[ 'time' ]} where id='{$user_id}'" );
	   
	   if ( ! $r )
	    throw new Exception( mysql_error(), mysql_errno() );
		
	   $r = mysql_query( "delete from {$this->session_table} where user_id={$user_id}" );
	   
	   if ( ! $r )
	    throw new Exception( mysql_error(), mysql_errno() );
	 } 
  }
}

?>