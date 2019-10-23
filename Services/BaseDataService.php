<?php
class BaseDataService{
   
   protected $HOST     = 'localhost';//'mysql2.nthost.ru';
   protected $LOGIN    = 'tralala';//'approxi_tralala';
   protected $PASSWORD = '34Rtbb0kbGkkgy';//'hrr455_pogb';
   protected $DB       = 'tralala';//'approxi_tralala';
   
   protected $users_table    = 'users';
   protected $projects_table = 'projects';
   protected $examples_table = 'examples';
   protected $updates_table  = 'updates';
   protected $session_table  = 'sessions';
   protected $commands_table = 'commands';
   protected $payments_table = 'payments';
   protected $pro_activations_table = 'pro_activations';
   protected $invitations_table = 'invitations';
   protected $favorites_table = 'favorites';
   
   protected $ERROR = -1;
   protected $OK = 0;
   
   function __construct()
   {
      if ( ! mysql_connect( $this->HOST, $this->LOGIN, $this->PASSWORD ) )
       throw new Exception( mysql_error(), mysql_errno() );
	   
	  if ( ! mysql_set_charset( 'utf8' ) )
	   throw new Exception( mysql_error(), mysql_errno() );
	   
	  if ( ! mysql_select_db( $this->DB ) )
       throw new Exception( mysql_error(), mysql_errno() );
   }
   
   protected function mysql_escape_object( $obj )
   {
     if ( is_object( $obj ) )
	 {
	   foreach( $obj as $var => $value)
	   {
	     if ( is_string( $value ) )
	     {
		   $obj->{ $var } = mysql_real_escape_string( $value );
		 }
		 else
		 {
		   $this->mysql_escape_object( $value );
		 }
	   }
	 }
     else if ( is_array( $obj ) )
	 {
	   foreach( $obj as $var => $value)
	   {
	     if ( is_string( $value ) )
	     {
		   $obj[ $var ] = mysql_real_escape_string( $value );
		 }
		 else
		 {
		   $this->mysql_escape_object( $value );
		 }
	   }
	 }
	 else if ( is_string( $obj ) )
	 {
	   $obj = mysql_real_escape_string( $obj );
	 }
   
	 return $obj;
   }
   
   //Возвращает true  - дата истекла
   //Возвращает false - дата не истекла
   protected function timeExpired( $time )
   {
     return ( $time - time() ) < 0;
   }
   
   protected function getProtocol()
   {
     return /*isset( $_SERVER["HTTPS"] ) ? 'https://' : 'http://';*/ $_SERVER["HTTP_X_FORWARDED_PROTO"] . '://';
   }
   
}
?>