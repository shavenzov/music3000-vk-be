<?php
  require_once __DIR__ . '/../Services/BaseDataService.php';
  
  class SessionCleaner extends BaseDataService
  {
    //Жизнь сессии в секундах
    const session_max_live = 300; //5 минут  
  
    public function clean()
	{
	  //Определяем какие сессии истекли
	  $r = mysql_query( "select user_id, time from {$this->session_table} where UNIX_TIMESTAMP() - UNIX_TIMESTAMP( touched ) > " . self::session_max_live );
	  
	  if ( ! $r )
	   {
	     die ( mysql_error() . ' ' . mysql_errno() );
	   }
	   
	   //Переносим эти значения в основную таблицу
	   while ( $row = mysql_fetch_assoc( $r ) )
	   {
	     $r = mysql_query( "update {$this->users_table} set time = time + {$row['time']} where id={$row['user_id']}" );
		 
		 if ( ! $r )
	     {
	       die( mysql_error() . ' ' . mysql_errno() );
	     }
	   }
	   
	   //Удаляем все сессии с истекшим сроком
	   $r = mysql_query( "delete from {$this->session_table} where UNIX_TIMESTAMP() - UNIX_TIMESTAMP( touched ) > " . self::session_max_live );
	   
	   if ( ! $r )
	   {
	     die ( mysql_error() . ' ' . mysql_errno() );
	   }
	}
  }
  
  $cleaner = new SessionCleaner();
  $cleaner->clean();
?>