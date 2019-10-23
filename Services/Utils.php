<?php

require_once __DIR__ . '/BaseDataService.php';

class Utils extends BaseDataService
{
  public function isAppUsers( $userIds )
  {
     $result = array();    
  
	  $count = count( $userIds );
	  
	  for ( $i = 0; $i < $count; $i++ )
	  { 
		 $r = mysql_query( 'select net_user_id from ' . $this->users_table . ' where net_user_id="' . $userIds[ $i ] . '"' );
	     
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		 
		  
		 if ( mysql_fetch_assoc( $r ) != null )
		 {
		   $result[] = $userIds[ $i ];
		 }
	  }
	   
     return $result;
  }
}

?>