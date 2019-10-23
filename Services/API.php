<?php

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/Error.php';

class API extends SessionManager
 {
	protected $INVITE_USER_BONUS = 1; //Бонус за приглашенного друга в монетах
	
    const MAX_PROJECTS = 16; //Максимальное количество проектов для одного пользователя ( Для не PRO пользователей )
	const MAX_PROJECTS_PER_DAY = 10; //Максимальное количество миксов которое может создавать пользователь в день
 
	const secret = "umFAlFfSC6htif9qGuXX";
	
	const project_fields = 'id,UNIX_TIMESTAMP( updated ) as updated,UNIX_TIMESTAMP( created ) as created,owner,name,genre,userGenre,tempo,duration,description,access,readonly';
	const user_fields = 'id, UNIX_TIMESTAMP( registered ) as registered, UNIX_TIMESTAMP( loged_in ) as loged_in, net_user_id, money, UNIX_TIMESTAMP( pro_expired ) as pro_expired, pro_expired as pro_expired_timestamp,pro';
	const public_user_fields = 'id, UNIX_TIMESTAMP( registered ) as registered, UNIX_TIMESTAMP( loged_in ) as loged_in, net_user_id';
	
	private $numbers = array( 'первый', 'второй', 'третий', 'четвертый', 'пятый', 'шестой', 'седьмой', 'восьмой', 'девятый', 'десятый', 'одиннадцатый', 'двенадцатый', 'тринадцатый', 'четырнадцатый', 'пятнадцатый', 'шестнадцатый', 'семнадцатый', 'восемнадцатый', 'девятнадцатый', 'двадцатый' );
	
	private $PRO_PRICE = array(  '0' => array( 'days' => 1 , 'price' => 6  ),
	                             '1' => array( 'days' => 7 , 'price' => 18 ),
								 '2' => array( 'days' => 14, 'price' => 30 ),
								 '3' => array( 'days' => 28, 'price' => 50 )
								);
    
	//Секунд в одном дне							
	private $SECONDS_IN_DAY = 86400;							
	
	//Возвращает идентификатор пользователя в системе
	public function connect( $net, $netUserID, $secret )
	{ 
	  if ( $secret != md5( $net . $netUserID . self::secret ) )
	   return Error::$ERROR;
	   
	  //Проверяем зарегистрирован ли уже такой пользователь
	  $userInfo = $this->getNetUserInfo( $netUserID, self::user_fields );
	  
	  if ( $userInfo == null )
	   return Error::$USER_NOT_REGISTERED_ERROR; 
	 
	   //Обновляем информацию о времени логина
	   $r = mysql_query( "update " . $this->users_table . " set loged_in=NOW() where id={$userInfo['id']}" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	   //Если во время отсутствия пользователя, истекло время действия его аккаунта, то проталкиваем клиенту сообщение
	   $pro = (int) ! $this->timeExpired( $userInfo[ 'pro_expired' ] );
	   
	   if ( $userInfo[ 'pro' ] != $pro )
	   {
	     $userInfo[ 'pro' ] = $pro;
		 $this->setUserProMode( $userInfo[ 'id' ], $pro );
		 
		 $this->addCommand( $userInfo[ 'id' ], $this->COMMAND_UPDATE_DATA, 'users:pro' ); 
	   }
	   
	   $userInfo[ "session_id" ] = $this->startSession( $userInfo );
	   
	   return $this->addStaticParams( $userInfo ); 
	}
	
	//Бонус, вновь зарегистрированные пользователи получают 25 монет на которые можно подключить режим PRO, на 14 дней
	private $BONUS_COINS = 12; //секунды
	
	//Регистрирует нового пользователя
	public function register( $net, $netUserID, $secret )
	{
	  if ( $secret != md5( $net . $netUserID . self::secret ) )
	   return Error::$ERROR;
	      
	  //Проверяем зарегистрирован ли уже такой пользователь
	  $userInfo = $this->getNetUserInfo( $netUserID );
	  
	  if ( $userInfo != null )
	   return Error::$USER_ALREADY_REGISTERED_ERROR;
	  
	    $r = mysql_query( "insert into " . $this->users_table . " ( registered, loged_in, net_user_id, money, pro_expired, pro, time ) values ( NOW(),NOW(),'{$netUserID}', {$this->BONUS_COINS}, FROM_UNIXTIME( 0 ), 0, 0 )" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  //Получаем информацию о вновь зарегистрированном пользователе
	  $userInfo = $this->getNetUserInfo( $netUserID, self::user_fields );
      
	  if ( $userInfo == null )
	   return Error::$ERROR;
		
	   $userInfo[ "session_id" ] = $this->startSession( $userInfo );
	   
	   return $this->addStaticParams( $userInfo );
	}
	
	private function addStaticParams( $info )
	{
	  $info[ 'inviteUserBonus' ] = $this->INVITE_USER_BONUS;
	  unset( $info[ 'pro_expired_timestamp' ] );
	  return $info;
	}
	
	protected function getUserInfoById( $user_id, $fields = 'id' )
	{
	  $r = mysql_query( "select {$fields} from {$this->users_table} where id={$user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	  
	  if ( mysql_num_rows( $r ) > 0 )	
	   return mysql_fetch_assoc( $r );
	   
	  return null; 
	}
	
	/*
	Получение информации о любом из пользователей по его id
	*/
	public function getUserInfo( $session_id, $user_id )
	{
	  if ( ! $this->sessionExists( $session_id ) )
	  {
		return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	  
	  $r = mysql_query( "select " . self::public_user_fields . " from {$this->users_table} where id={$user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	  
	  if ( mysql_num_rows( $r ) == 0 )	
	   return Error::$ERROR; 
	   
	  $userInfo = mysql_fetch_assoc( $r );
	   
	  return $userInfo;
	}
	
	protected function getNetUserInfo( $net_user_id, $fields = 'id' )
	{
	  $r = mysql_query( "select {$fields} from {$this->users_table} where net_user_id={$net_user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	  
	  if ( mysql_num_rows( $r ) > 0 )	
	   return mysql_fetch_assoc( $r );	
		
	  return null;
	}
	
	protected function netUserExists( $net_user_id )
	{
	  $r = mysql_query( "select id from {$this->users_table} where net_user_id = {$net_user_id}" );
	  
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_num_rows( $r ) > 0;	
	}
	
	//Сортирует список пользователей по дате последнего обновления последнего микса
	//Возвращает массив отсортированных идентификаторов с дополнительной информацией
	public function orderUserList( $session_id, $net_user_ids )
	{
	  if ( ! $this->sessionExists( $session_id ) )
	   {
		  return Error::$SESSION_NOT_FOUND_ERROR;
	   }   
	  
	  $uids   = implode( ',', $net_user_ids );
	  
	  $r = mysql_query( "select net_user_id as uid, loged_in as updated from {$this->users_table} where net_user_id in ( {$uids} ) order by loged_in desc" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $data = array(); 
		
	  while ( $row = mysql_fetch_assoc( $r ) )
	  {
	    $data[] = $row;
	  }	
	  
	  return $data;
	}
	
	public function browseProjectsByNetUserID( $session_id, $net_user_id, $offset, $limit )
	{
	  $userInfo = $this->getNetUserInfo( $net_user_id );
	  
	  if ( $userInfo )
	  {
	    return $this->browseProjects( $session_id, $userInfo[ 'id'], $offset, $limit );
	  }
	  
	  $result = array();
	  $result[ 'count' ] = 0;
	  $result[ 'data' ] = array();
	  
	  return $result;
	}
	
	//Возвращает список проектов сохраненных пользователем
	public function browseProjects( $session_id, $user_id, $offset, $limit )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	  
	  if ( $user_id == null )
	  {
	    $user_id = $sessionData[ 'user_id' ];
	  }
	   
	  $query = "select " . self::project_fields . " from {$this->projects_table} where";
	  
	  $where = " (owner={$user_id})";
	  
	  //Запрашиваем список миксов другого пользователя
	  if ( $user_id != $sessionData[ 'user_id' ] )
	  {
	    $where .= " and ((access='friends') or (access='all'))";
	  }
	  
	  $query .= $where . " order by updated desc";
	  
	  if ( $limit > 0 )
	  {
	    $query .= ' limit ' . $offset . ',' . $limit;
	  }
		//echo( $query );
	  $r = mysql_query( $query );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $data = array();
	  
	  while ( $row = mysql_fetch_assoc( $r ) )
	  {
	    $data[] = $row;
	  }
	  
	  $r = mysql_query( 'select count(*) as count from ' . $this->projects_table . " where {$where}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $result = array();
	  $result[ 'count' ] = mysql_fetch_assoc( $r );
	  $result[ 'data' ] = $data;
		
	  return $result;
	}
	
	public function browseExamples( $session_id, $offset, $limit )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	  
	  $query = "select " . self::project_fields . " from " . $this->examples_table . " order by updated desc";
	  
	  if ( $limit > 0 )
	  {
	    $query .= ' limit ' . $offset . ',' . $limit;
	  }
	 	
	  $r = mysql_query( $query );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  $data = array();
	  
	  while ( $row = mysql_fetch_assoc( $r ) )
	  {
	    $data[] = $row;
	  }
	  
	  $r = mysql_query( 'select count(*) as count from ' . $this->examples_table );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  
	  $result = mysql_fetch_assoc( $r );
	  $result[ 'data' ] = $data;
		
	  
	  return $result;
	}
	
	private function getProjectInfoByName( $user_id, $projectName )
	{
	  /*$sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }*/
	
	  $r = mysql_query( "select " . self::project_fields . " from " . $this->projects_table . " where ( owner = " . $user_id . " ) and ( UPPER( name ) = UPPER( '" . mysql_real_escape_string( $projectName ) . "' ) ) " );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_fetch_assoc( $r );	
	}
	
	public function getProjectInfo( $session_id, $projectID )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	  if ( $sessionData === false )
	  {
	    return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	   
	  $r = mysql_query( "select " . self::project_fields . " from " . $this->projects_table . " where id = " . $projectID );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  if ( mysql_num_rows( $r ) == 0 )
	  {
	    return Error::$ERROR;
	  }
	  
	  $projectInfo = mysql_fetch_assoc( $r );
	  
	  /*
	  if ( $projectInfo[ 'access' ] == 'nobody' )
	  {
	    if ( $sessionData[ 'user_id' ] != $projectInfo[ 'owner' ] )
		{
		  return Error::$ERROR;
		}
	  }
	  */
	  
	  return $projectInfo;
	}
	
	private function getProjectInfoByID( $projectID )
	{
	  /*if ( ! $this->sessionExists( $session_id ) )
	   {
		  return Error::$SESSION_NOT_FOUND_ERROR;
	   } */  
	
	  $r = mysql_query( "select " . self::project_fields . " from " . $this->projects_table . " where id = " . $projectID );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	  return mysql_fetch_assoc( $r );
	}
	
	public function removeProject( $session_id, $projectID )
	{
	   if ( ! $this->sessionExists( $session_id ) )
	   {
		  return Error::$SESSION_NOT_FOUND_ERROR;
	   }
		
	   $projectInfo = $this->getProjectInfoByID( $projectID );
	   
	   if ( ! $projectInfo )
	    throw new Exception( 'Internal error', 100 );
	   
	   $r = mysql_query( "delete from " . $this->projects_table . " where id = " . $projectID );
	}
	
	public function resolveName( $session_id, $projectName )
	{
	  $sessionData = $this->getSessionData( $session_id );
	  
	  if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	  
	  $i = 0;
	  $newProjectName = $projectName;
	  
	  do
		{
		  $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $newProjectName );
		  $i ++;
		  
		  if ( $projectInfo )
		  {
		    $newProjectName = $projectName . ' ' . $i;
		  } 
		}
		while ( $projectInfo );
		
		return $newProjectName;
	}
	
	//Возвращает доступное название проекта по умолчанию
	public function getDefaultProjectName( $session_id )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id, pro' );
	  
	  if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
		
	     //Проверяем не исчерпал ли этот пользователь лимит на миксы
	     $code = $this->getProjectsLimitations( $sessionData[ 'user_id' ], $sessionData[ 'pro' ] );
		 
		 if ( $code != Error::$OK )
		 {
		   return $code;
		 }
		
		$i = 0;
		
		do
		{
		  if  ( count( $this->numbers ) > $i )
		   {
		     $projectName = 'Мой ' . $this->numbers[ $i ] . ' микс';
		   }
		   else
		   {
		     $projectName = 'Мой микс #' . ( $i + 1 ); 
		   }
		
		  $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $projectName );
		  $i ++;
		}
		while( $projectInfo );
		
		
		return $projectName;
	}
	
	//Проверяет корректность XML данных проекта
	private function isValidProjectData( $data )
	{
	  $dom = new DOMDocument('1.0', 'utf-8');
	  return @$dom->loadXML( $data );
	}
	
	//Обновляет информацию о проекте
	//Доступные поля
	//$info->id
	//$info->name
	//$info->tempo
	//$info->genre
	//$info->duration
	//$info->description
	//$info->access
	//$info->readonly
	public function updateProject( $session_id, $info, $data )
	{
	   $sessionData = $this->getSessionData( $session_id );
	  
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
		
		$projectInfo = $this->getProjectInfoByID( $info->id );
		
		if ( ! $projectInfo  )
		 throw new Exception( 'Internal error', 100 );
		
		if ( strtoupper( $projectInfo[ "name" ] ) != strtoupper( $info->name ) )
		{
		  //Проверяем есть ли проект с таким именем
		  $projectInfo2 = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
		  
		  if ( $projectInfo2 ) //Если есть, то ничего больше не делаем и возвращаем false
		   {
		     return Error::$PROJECT_WITH_THIS_NAME_ALREADY_EXISTS;
		   }
		}
		
		$info->userGenre = (int)$info->userGenre;
		$info->readonly = (int)$info->readonly;
		$info = $this->mysql_escape_object( $info );
		
		$query = "update {$this->projects_table} set updated=NOW(),name='{$info->name}',tempo={$info->tempo},genre='{$info->genre}',userGenre={$info->userGenre},duration={$info->duration},description='{$info->description}',access='{$info->access}',readonly={$info->readonly}";
		
		if ( isset( $data ) )
		{
		  if ( $this->isValidProjectData($data) === false )
		  {
		    return Error::$NOT_CORRECT_PROJECT_DATA;
		  }
		
		  $data = mysql_real_escape_string($data);
		  $query .= ",data='{$data}'";
		}
		
		$query .= " where id={$info->id}";
		
		$r = mysql_query( $query  );
		
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		return $projectInfo[ 'id' ];  
	}
	
	//Количество секунд в одном дне
	const SECONDS_IN_DAY = 86400;
	
	//Возвращает количество миксов созданных пользователем в течении одного дня
	private function getNumProjectsCreatedToday( $user_id )
	{
	  $r = mysql_query( "select count( * ) as count from {$this->projects_table} where (owner={$user_id}) and (UNIX_TIMESTAMP() - UNIX_TIMESTAMP( created ) < " . self::SECONDS_IN_DAY . ")" );
	  
	  if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
	  $row = mysql_fetch_assoc( $r );
	  
	  return $row[ 'count' ];
	}
	
	//Возвращает общее количество миксов созданных пользователем
	private function getUserProjectsCount( $user_id )
	{
	  $r = mysql_query( 'select count( * ) as count from ' . $this->projects_table . ' where owner=' . $user_id );
	  
	  if ( ! $r )
	  {
	    throw new Exception( mysql_error(), mysql_errno() ); 
	  }
	  
	  $row = mysql_fetch_assoc( $r );
	  
	  return $row[ 'count' ];
	}
	
	//Проверяет может ли пользователь создавать новые миксы
	//Возвращает MAX_PROJECTS_FOR_BASIC_MODE_EXCEEDED_ERROR = 5, если Максимальное количество миксов, для базового аккаунта исчерпано
	//Возвращает MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR = 10, если Маскимальное количество проектов в день превышено
	// OK - если никаких ограничений нет
	private function getProjectsLimitations( $user_id, $pro )
	{
	  //Ограничение на максимальное количество создаваемых проектов в день
	  if ( $this->getNumProjectsCreatedToday( $user_id ) >= self::MAX_PROJECTS_PER_DAY )
	  {
		    return Error::$MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR;
	  }
	   
	  //Ограничение на максимально количество миксов для базового режима
	  if ( $pro == 0 )
	  {
		$numProjects = $this->getUserProjectsCount( $user_id );
		  
		if ( $numProjects >= self::MAX_PROJECTS )
		{
		  return Error::$MAX_PROJECTS_FOR_BASIC_MODE_EXCEEDED_ERROR;
		}
	   }
		  
		return Error::$OK;  
	}
	
	//Проверяет не исчерпал ли пользователь
	//1. Максимальное количество миксов
	public function getLimitations( $session_id )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id, pro' );
	  
	  if ( $sessionData === false )
	  {
	    return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	  
	  $result = new stdClass();
	  $result->projects = $this->getProjectsLimitations( $sessionData[ 'user_id' ], $sessionData[ 'pro' ] );
	  
	  return $result;
	}

	//Сохраняет проект первый раз, возвращает идентификатор проекта
	//Доступные поля
	//$info->name
	//$info->tempo
	//$info->genre
	//$info->duration
	//$info->description
	public function saveProject( $session_id, $info, $data  )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id, pro' );
	  
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
       
	   $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
	   
	   if ( $projectInfo == null ) //Проект ранее не сохранялся
	   {
		 //Проверяем не исчерпал ли этот пользователь лимит на миксы
	     $code = $this->getProjectsLimitations( $sessionData[ 'user_id' ], $sessionData[ 'pro' ] );
		 
		 if ( $code != Error::$OK )
		 {
		   return $code;
		 }
		 
		 if ( $this->isValidProjectData($data) === false )
		 {
		  return Error::$NOT_CORRECT_PROJECT_DATA;
		 }
		 
		  $info->userGenre = (int)$info->userGenre;
		  $info->readonly = (int)$info->readonly;
	      $info = $this->mysql_escape_object( $info );
		  
		  $data = mysql_real_escape_string( $data );
		
	     $r = mysql_query( "insert into {$this->projects_table} (updated,created,owner,name,genre,userGenre,tempo,duration,description,data,access,readonly) values (NOW(),NOW(),{$sessionData[ 'user_id' ]},'{$info->name}','{$info->genre}',{$info->userGenre},{$info->tempo},{$info->duration},'{$info->description}','{$data}','{$info->access}',{$info->readonly})" );
		 
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		 $projectInfo = $this->getProjectInfoByName( $sessionData[ 'user_id' ], $info->name );
	   }
	   else return Error::$PROJECT_WITH_THIS_NAME_ALREADY_EXISTS;
	   
	   return $projectInfo[ 'id' ];
	}
	
	//Загружает проект
	public function getProject( $session_id, $projectID, $source )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }  
	
	  if ( $source == 0 ) //Открываем пример
	  {
	    $table = $this->examples_table;
	  }
	  else //Открываем проект пользователя
	  { 
		 $table = $this->projects_table;
		 
		 $projectInfo = $this->getProjectInfoByID( $projectID );
		 
		 //Проверяем принадлежит ли этот микс пользователю
		 if ( $projectInfo[ 'access' ] == 'nobody' )
		 {
		   if ( $projectInfo[ 'owner' ] != $sessionData[ 'user_id' ] )
		   {
		     return Error::$PROJECT_ACCESS_DENIED;
		   }
		 }
	  }
	
	   $r = mysql_query( "select data from " . $table . " where id=" . $projectID );
	   
	   if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
	   
	   if ( mysql_num_rows( $r ) === 0 )
	   {
		 return Error::$PROJECT_NOT_FOUND;
	   }
	
	   $row = mysql_fetch_assoc( $r );	
		
	   return $row[ 'data' ];	
	}
	
	//Подключает или продлевает режим PRO
	public function switchOnProMode( $session_id, $priceIndex )
	{
	   $sessionData = $this->getSessionData( $session_id );
	  
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Проверяем корректность $priceIndex
	   if ( ( $priceIndex < 0 ) || ( $priceIndex > ( count( $this->PRO_PRICE ) - 1 ) ) )
	   {
	     return Error::$PRICE_INDEX_NOT_EXISTS;
	   }
	   
	   //Проверяем достаточно ли у пользователя монет
	   $userInfo = $this->getUserInfoById( $sessionData[ 'user_id' ], 'net_user_id,money,pro,UNIX_TIMESTAMP( pro_expired ) as pro_expired' );
	   
	   if ( $userInfo[ 'money' ] < $this->PRO_PRICE[ $priceIndex ][ 'price' ] )
	   {
	     return Error::$NOT_ENOUGH_MONEY_ERROR;
	   }
	   
	   //Подключаем PRO 
	   if ( $userInfo[ 'pro' ] == 0 )
	   {
	     $proTill = time() + $this->SECONDS_IN_DAY * $this->PRO_PRICE[ $priceIndex ][ 'days' ];
	   }
	   else //Продлеваем PRO
	   {
	     $proTill = $userInfo[ 'pro_expired' ] + $this->SECONDS_IN_DAY * $this->PRO_PRICE[ $priceIndex ][ 'days' ];
	   }
	   
	   //Обновляем информацию пользователя
	   //Снимаем монеты, продлеваем время действия режима PRO
	   $r = mysql_query( "update {$this->users_table} set money=money-{$this->PRO_PRICE[ $priceIndex ][ 'price' ]},pro_expired=FROM_UNIXTIME({$proTill}),pro=1 where id={$sessionData['user_id']}" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	   //Логируем
	   $r = mysql_query( "insert into {$this->pro_activations_table} (user_id,net_user_id,days,coins) values({$sessionData[ 'user_id' ]},{$userInfo['net_user_id']},{$this->PRO_PRICE[ $priceIndex ][ 'days' ]},{$this->PRO_PRICE[ $priceIndex ][ 'price' ]})" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	   //Обновляем информацию в сессии
	   $r = mysql_query( "update {$this->session_table} set pro=1,pro_expired=FROM_UNIXTIME({$proTill}) where user_id={$sessionData['user_id']}" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
		
	   //Проталкиваем сообщения пользователю
	   $this->addCommand( $sessionData['user_id'], $this->COMMAND_UPDATE_DATA, $this->users_table . ':money,pro,UNIX_TIMESTAMP( pro_expired ) as pro_expired' );
	   
	   return Error::$OK;
	}
	
	private function addServerUpdateInfo( $object )
	{
	      //Проверяем на наличие обновлений
		  $object->update = 0;
		  
		  $r = mysql_query( "select id, enabled from " . $this->updates_table . " order by id desc limit 0,1" );
		  
		  if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
		  if ( mysql_num_rows( $r ) > 0 )
		   {
		     $row = mysql_fetch_assoc( $r );
			 
			 if ( $row[ 'enabled' ] == 1 )
			  {
			    $r = mysql_query( "select UNIX_TIMESTAMP( end ) as end, reason from " . $this->updates_table . " where id={$row['id']}" );
				
				if ( ! $r )
		         throw new Exception( mysql_error(), mysql_errno() );
				
				$row = mysql_fetch_assoc( $r );
				
				$object->end    = $row[ 'end' ];
				$object->reason = $row[ 'reason' ];
				$object->update = 1;
			  }
		   }
	}
	
	//Заносит в таблицу users запись, о изменении значения режима pro
	// $pro = 0 или 1
	private function setUserProMode( $user_id, $pro )
	{
	   $r = mysql_query( "update {$this->users_table} set pro={$pro} where id={$user_id}" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	}
	
	//Заносит в таблицу sessions запись, о изменении значения режима pro
	// $pro = 0 или 1
	private function setUserSessionProMode( $user_id, $pro )
	{
	  $r = mysql_query( "update {$this->session_table} set pro={$pro} where user_id={$user_id}" );
	  
	  if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	}
		
	public function touch( $session_id, $time )
	{
	  $sessionData = $this->getSessionData( $session_id, 'UNIX_TIMESTAMP( pro_expired ) as pro_expired, pro, commands, user_id' );
	  
	  if ( $sessionData === false )
	  {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	      
	      $result = new stdClass();
		  
		  //Проверяем не истек ли про аккаунт
		  if ( $sessionData[ 'pro' ] == 1 )
		  {
		    $pro = (int) ! $this->timeExpired( $sessionData[ 'pro_expired' ] );
			
			//Если истек, то сообщаем клиенту об этом
			if ( $pro != $sessionData[ 'pro' ] )
			{
			   $result->{ $this->COMMAND_UPDATE_DATA } = new stdClass();
			   $result->{ $this->COMMAND_UPDATE_DATA }->{ $this->users_table } = new stdClass();
			   $result->{ $this->COMMAND_UPDATE_DATA }->{ $this->users_table }->pro = $pro;
			   
			   $this->setUserProMode( $sessionData[ 'user_id' ], $pro );
			   $this->setUserSessionProMode( $sessionData[ 'user_id' ], $pro );
			} 
		  }
		  
		  //Если есть данные, то проталкиваем данные клиенту
		  if ( $sessionData[ 'commands' ] == 1 )
		  {
		    $this->pushCommands( $sessionData[ 'user_id' ], $result );
		  }
		  
		  //Добавляем информацию по поводу обновления сервера
		  $this->addServerUpdateInfo( $result );
		  //Обновляем время клиента 
		  $this->updateSession( $session_id, $time );
		   
		  return $result;	
	}
	
	//Возвращает серверное время в формате unix_timestamp
	public function getProTariffs( $session_id )
	{
	  if ( ! $this->sessionExists( $session_id ) )
	   {
		  return Error::$SESSION_NOT_FOUND_ERROR;
	   }   
	
	  $result = new stdClass();
	  $result->time = time();
	  $result->tariffs = $this->PRO_PRICE;
	  
	  return $result;
	}
	
	//Проверяет есть ли запись с приглашением этого пользователя
	//Возвращает 0 - если такой записи нету
	//Возвращает 1 - если такая запись есть и она не подтверждена
	//Возвращает 2 - если такая запись есть и она подтверждена
	private function invitationExists( $uid, $inviter_id )
	{
	   $r = mysql_query( "select confirmed from {$this->invitations_table} where (uid='{$uid}')and(user_id={$inviter_id})" );
	   
	   if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
	   
	   $confirmed = mysql_num_rows( $r ) == 0 ? 0 : 1;	   
		   
	   while( $row = mysql_fetch_assoc( $r ) )
	   { 
	     if ( $row[ 'confirmed' ] == 1 )
		 {
		   $confirmed = 2;
		   break;
		 }
	   }
		   
	   return $confirmed;
	}
	
	//Приглашает пользователей в друзья
	//uids - список идентификаторов приглашенных пользователей (OK)
	public function inviteFriends( $session_id, $uids )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	  
	  if ( $sessionData === false )
	  {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	  
	  foreach( $uids as $uid )
	  {
	    if ( $this->invitationExists( $uid, $sessionData[ 'user_id' ] ) == 0 )
		{
		   $r = mysql_query( "insert into {$this->invitations_table} (date,user_id,uid,confirmed) values(NOW(),{$sessionData[ 'user_id' ]},'{$uid}',0)" );
		
		   if ( ! $r )
		    throw new Exception( mysql_error(), mysql_errno() ); 
		} 
	  }
	  
	  return Error::$OK;
	}
	
	//начисляет бонус пригласившему этого пользователя
	//uid - идентификатор приглашенного пользователя (OK)
	//inviter_id - идентификатор пригласившего пользователя
	public function doUserInvitedAction( $session_id, $uid, $inviter_id )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	 
	  if ( $sessionData === false )
	  {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	  }
	  
	  if ( $this->invitationExists( $uid, $inviter_id ) == 1 )
	  {
	    //Устанавливаем статус подтверждения
		$r = mysql_query( "update {$this->invitations_table} set confirmed=1 where (uid='{$uid}')and(user_id={$inviter_id})" );
		
		if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
	  
	    //Добавляем монеты на счёт пригласившего
		$r = mysql_query( "update {$this->users_table} set money=money+{$this->INVITE_USER_BONUS} where id={$inviter_id}" );
		
		if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
		//Проталкиваем сообщения
		$this->addCommand( $inviter_id, $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );
		$this->addCommand( $inviter_id, $this->COMMAND_SHOW_MESSAGE, "Ты получил бонус за приглашенного друга.::money={$this->INVITE_USER_BONUS};type={$this->MESSAGE_TYPE_FRIEND_INVITED};" ); 
	  
	    return Error::$OK;
	  }
	  
	  return Error::$ERROR;
	}
	
	//Добавляет сэмпл с идентификатором $hash, библиотеки $library в избранные сэмплы
	// $source - идентификатор библиотеки сэмплов
	// $hash    - идентификатор сэмпла 
	public function addToFavorite( $session_id, $source, $hash )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Проверяем добавлен ли этот сэмпл в избранные
	   if ( $this->_favoriteExists( $sessionData[ 'user_id' ], $source, $hash ) == true )
	   {
	     return Error::$SAMPLE_ALREADY_IN_FAVORITE;
	   }
	   
	   //Добавляем сэмпл в список избранных
	   $r = mysql_query( "insert into {$this->favorites_table} (date,owner,source,hash) values(NOW(),{$sessionData[ 'user_id' ]},'{$source}','{$hash}')" );
	   
	   if ( ! $r )
		throw new Exception( mysql_error(), mysql_errno() );
	   
	   return Error::$OK; 	
	}
	
	//Удаляет сэмпл из ибранных
	// $library - идентификатор библиотеки сэмплов
	// $hash    - идентификатор сэмпла  
	public function removeFromFavorite( $session_id, $source, $hash )
	{
	  $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Проверяем добавлен ли этот сэмпл в избранные
	   if ( $this->_favoriteExists( $sessionData[ 'user_id' ], $source, $hash ) == false )
	   {
	     return Error::$SAMPLE_NOT_FOUND_IN_FAVORITE;
	   }
	   
	   $r = mysql_query( "delete from {$this->favorites_table} where owner={$sessionData['user_id']} and source='{$source}' and hash='{$hash}'" );
	   
	   if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
	   return Error::$OK;	   
	}
	
	//Проверяет присутствует ли сэмпл в списке избранных
	// $library - идентификатор библиотеки сэмплов
	// $hash    - идентификатор сэмпла  
	public function favoriteExists( $session_id, $source, $hash )
	{
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	     return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   return $this->_favoriteExists( $sessionData[ 'user_id' ], $source, $hash ); 
	}
	
	protected function _favoriteExists( $user_id, $source, $hash )
	{
	  $r = mysql_query( "select id from {$this->favorites_table} where owner={$user_id} and source='{$source}' and hash='{$hash}'" );
	  
	  if ( ! $r )
		   throw new Exception( mysql_error(), mysql_errno() );
		   
	  return mysql_num_rows( $r ) > 0; 	   
	}
 }
 /*
 $z = new API();
 $t->name = '123';
 $t->genre = 'na';
 $t->tempo = 90;
 $t->duration = 0;
 $t->id = 65;
 $t->description = '';
 
 $z->removeProject( 2, 304 );
 */
?>