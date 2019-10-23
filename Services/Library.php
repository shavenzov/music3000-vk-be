<?php

   require_once __DIR__ . '/SessionManager.php';
   
   class Library extends SessionManager
   {
      const MIN_TEMPO = 30;
	  const MAX_TEMPO = 350;
	  const LIBRARY_ID = 'MAIN';
	  
	  protected $library_table = 'looperman';
	  
	  private function correctParams( $params )
	  {
	    if ( $params === null )
		 {
		   $params = new stdClass();
		 }
	  
	     if ( isset( $params->name ) )
		 {
		   $params->name = trim( $params->name ); 
		 }
		 
		 if ( isset( $params->tempoFrom ) )
		 {
		   if ( $params->tempoFrom < self::MIN_TEMPO )
		    {
			  $params->tempoFrom = self::MIN_TEMPO;
			}
		 }
		 else
		 {
		   $params->tempoFrom = self::MIN_TEMPO;
		 }
		 
		 if ( isset( $params->tempoTo ) )
		 {
		   if ( $params->tempoTo > self::MAX_TEMPO )
		   {
		     $params->tempoTo = self::MAX_TEMPO;
		   }
		 }
		 else
		 {
		   $params->tempoTo = self::MAX_TEMPO;
		 }
		 
		 if ( ! isset( $params->showOnlyFavorite ) )
		 {
		    $params->showOnlyFavorite = false;
		 }
		 
		 return $params;
	  }
	  
	  private function getSearchQueryString( $params )
	  {
	    if ( $params == null ) return '';  
	    
		$params = $this->mysql_escape_object( $params );
		
		$queryParts = array();
		  
	    $count = 0;
		
	    //Поиск по названию
		if ( isset( $params->name ) && ( strlen( $params->name ) > 0 ) )
		{
		  $whereStr = "( t1.name like '%" . $params->name . "%' )";
		  $queryParts[] = $whereStr;
		}
		
		//Формируем список жанров
		$whereStr = ''; 
		
		 if ( isset( $params->genres ) )
		 {
		    $count = count( $params->genres );  
	  
		    if ( $count > 0 )
		    {
		     
		       $whereStr .= '('; 
		 
			   $i = 0;
			   foreach( $params->genres as $val )
		        {
		          $whereStr .= "( t1.genre = '" . $val . "' )";
			      if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
				  $i ++;
		        }
			
			  $whereStr.= ' )';
			  
			  $queryParts[] = $whereStr;
		    }
		 }
		
		//Формируем список категорий
		$whereStr = '';  
		 
	    if ( isset( $params->categories ) )
		{
		   $count = count( $params->categories );  
	  
		   if ( $count > 0 )
		   {
		     
		     $whereStr .= '('; 
		 
		     $i = 0;
		     foreach( $params->categories as $val )
		     {
		      $whereStr .= "( t1.category = '" . $val . "' )";
			  if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
			  $i ++;
		      }
			
			$whereStr .= ' )';
			$queryParts[] = $whereStr;
		   }
		 }
		 
		//Формируем список ключей
		$whereStr = ''; 
		
		if ( isset( $params->keys ) )
		 {
		   $count = count( $params->keys );  
	  
		   if ( $count > 0 )
		   {
		     
		    $whereStr .= '('; 
		 
		     $i = 0;
		     foreach( $params->keys as $val )
		   {
		      $whereStr .= "( t1.mkey = '" . $val . "' )";
			  if ( $i < ( $count - 1 ) ) $whereStr .= ' or ';
			  $i ++;
		    }
			
			$whereStr .= ' )';
			$queryParts[] = $whereStr;
		 }
		 
		 }
		
		//Устанавливаем диапазон темпа  
		$whereStr = ''; 
		
		if ( isset( $params->tempoFrom ) )
		 {
		   $whereStr .= "( t1.tempo >= " . $params->tempoFrom . " )";
		   $queryParts[] = $whereStr;
		 }
		 
		 $whereStr = '';
		 
		 if ( isset( $params->tempoTo ) )
		 {
		   $whereStr .= "( t1.tempo <= " . $params->tempoTo . " )";
		   $queryParts[] = $whereStr;
		 }
		
		//Объединяем все параметры 
		$whereStr = ''; 
		$i = 0; 
		$count = count( $queryParts );
		
		foreach( $queryParts as $val )
		{
		  $whereStr .= $val;
		  if ( $i < ( $count - 1 ) ) $whereStr .= ' and ';
		  $i ++;
		}
		
		return $whereStr;
	  }
	   
	  public function getSearchParams( $session_id, $params, $getGenres, $getCategories, $getTempos, $getKeys  ) 
	  {
	    $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	    if ( $sessionData === false )
	    {
	      return $this->SESSION_NOT_FOUND_ERROR;
	    }  
	  
	    $params = $this->correctParams( $params );
	    $whereStr = $this->getSearchQueryString( $params );   
	   
		$result = new stdClass();
		
		//Условия получения списка избранных
		$favoritesCondition = "";
		
		if ( $params->showOnlyFavorite )
		{
		  $favoritesCondition = "inner join {$this->favorites_table} as t2 on t1.hash=t2.hash and t2.source='" . self::LIBRARY_ID . "' and t2.owner={$sessionData['user_id']}";
		}
		
		if ( strlen( $whereStr ) > 0 )
		{
		  $whereStr = "where {$whereStr}";
		}
		
		if ( $getGenres )
		{
		   //list of genres
		   $r = mysql_query( "select t1.genre from {$this->library_table} as t1 {$favoritesCondition} {$whereStr} group by t1.genre" );
		    
		   if ( ! $r )
		    throw new Exception( mysql_error(), mysql_errno() );
		 
		   $result->genres = array();
		 
		   while ( $row = mysql_fetch_assoc( $r ) )
		    {
		      $result->genres[] = $row[ 'genre' ];
		    }
		}
		
	    if ( $getCategories )
	    {
		  //list of categorys
		  $r = mysql_query( "select t1.category from {$this->library_table} as t1 {$favoritesCondition} {$whereStr} group by t1.category" );
		  
		   if ( ! $r )
		    throw new Exception( mysql_error(), mysql_errno() );
		 
		   $result->categories = array();
		 
		   while ( $row = mysql_fetch_assoc( $r ) )
		   {
		    $result->categories[] = $row[ 'category' ];
		   }
		}
		
		if ( $getKeys )
	    {
		   //list of keys
		   $r = mysql_query( "select t1.mkey from {$this->library_table} as t1 {$favoritesCondition} {$whereStr} group by t1.mkey" );
		    
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		 
		 $result->keys = array();
		 
		 while ( $row = mysql_fetch_assoc( $r ) )
		 {
		   //Отфильтровываем ненужные результаты
		   if ( $this->isItValidKey( $row[ 'mkey' ] ) )  
		    {
			  $result->keys[] = $row[ 'mkey' ];
			}
		 }
		}
		
		if ( $getTempos )
		{
		  //Выборка по темпу, всегда среди общего количества сэмплов 
		  $r = mysql_query( "select t1.tempo from {$this->library_table} as t1 {$whereStr} group by t1.tempo" );
		
		if ( ! $r )
		 throw new Exception( mysql_error(), mysql_errno() );
		 
		$result->tempos = array();
		 
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $result->tempos[] = $row[ 'tempo' ];
		}
	   }
		
		 return $result;
	  }
	  
	  private function isItValidKey( $key )
	  {
	    return ( $key != 'None' ) && ( $key != 'Unkn' ) && ( $key != 'Unknown' );
	  }  
	  
	  private $AUDIO_HOST = 'musconstructor.com';
	  
	  /*
	    Возвращает ссылку на аудио файл сэмпла ( низкое качество )
	  */
	  private function getLQAudioLink( $hash )
	  {
	    return $this->getProtocol() . "{$this->AUDIO_HOST}/audio/samples/lq/{$hash}.mp3";
	  }
	  
	  /*
	    Возвращает ссылку на аудио файл сэмпла ( оригинальное качество )
	  */
	  private function getHQAudioLink( $hash, $type )
	  {
	    return $this->getProtocol() . "{$this->AUDIO_HOST}/audio/samples/hq/{$hash}.{$type}"; 
	  }
	  
	  //Возвращает информацию о семпле по его идентификатору
	  public function getInfo( $session_id, $ids )
	  {
	     $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	     if ( $sessionData === false )
	     {
	      return $this->SESSION_NOT_FOUND_ERROR;
	     }   
	     
		 $ids = $this->mysql_escape_object( $ids );
		 
	     $where = 't1.hash in(';
		 $i = 0;
		 
		 foreach( $ids as $val )
		 {
		    $where .= "'{$val}'";
			
		    if ( $i < ( count( $ids ) - 1 ) )
			{
			  $where .= ',';
			}
			
			$i ++;
		 }
		 
		 $where .= ')';
		 
		 $favoritesCondition = "left join {$this->favorites_table} as t2 on t1.hash=t2.hash and t2.source='" . self::LIBRARY_ID . "' and t2.owner={$sessionData['user_id']}";
		 
		 $r = mysql_query( "select {$this->result_fields}, t2.owner from {$this->library_table} as t1 {$favoritesCondition} where {$where}" );
		 
		 if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		
		$result = new stdClass();
		$result->data = array();
		$result->count = mysql_num_rows( $r );
		
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $row[ 'lqurl' ] = $this->getLQAudioLink( $row[ 'hash' ] );
		  $row[ 'hqurl' ] = $this->getHQAudioLink( $row[ 'hash' ], $row[ 'type' ] );
		  
		  if ( ! $this->isItValidKey( $row[ 'mkey' ] ) )
		  {
		    $row[ 'mkey'] = null;
		  }
		  
		  //Добавляем идентификатор библиотеки
		  $row[ 'source_id' ] = self::LIBRARY_ID;
		  
		  //Присутствует ли этот сэмпл в избранном
		  $row[ 'favorite' ] = isset( $row[ 'owner' ] );
		  
		  unset( $row[ 'owner' ] );
		  
		  $result->data[] = $row;
		}
		
		return $result;
	  }
	  
	  private $result_fields = "t1.hash, t1.name, t1.author, t1.type, t1.tempo, t1.duration, t1.genre, t1.category, t1.mkey"; 
	  
	  //Возвращает результаты поиска
	  public function search( $session_id, $params )
	  {
        $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	    if ( $sessionData === false )
	    {
	      return $this->SESSION_NOT_FOUND_ERROR;
	    }
	  
	    $params = $this->correctParams( $params );   
	   
		$whereStr = $this->getSearchQueryString( $params );
		
		if ( ! isset( $params->offset ) )
		 $params->offset = 0;
		 
		if ( ! isset( $params->limit ) )
		 $params->limit = 100;
		 
		if ( ! isset( $params->orderBy ) )
		 $params->orderBy = 'name';
		 
		if ( ! isset( $params->order ) ) //Параметр не установлен
		{
		  $params->order = 'asc';
		}
		else
		{
		  $o = strtolower( $params->order ); //Параметр имеет недопустимое значение
		  
		  if ( ( $o != 'desc' ) && ( $o != 'asc' ) )
		  {
		    $params->order = 'asc';
		  }
		}
		
		if ( strlen( $whereStr ) > 0 )
		{
		  $whereStr = "where {$whereStr}";
		}
		
		//Условия получения списка избранных
		$favoritesCondition = "join {$this->favorites_table} as t2 on t1.hash=t2.hash and t2.source='" . self::LIBRARY_ID . "' and t2.owner={$sessionData['user_id']}";
		$joinCondition = "";
		
		if ( $params->showOnlyFavorite )
		{
		  $joinCondition = "inner {$favoritesCondition}";
		}
		
		//total results
		$r = mysql_query( "select count(*) as count from {$this->library_table} as t1 {$joinCondition} {$whereStr}" ); 
		 
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		  
		$row = mysql_fetch_assoc( $r );
		$result = new stdClass();
		$result->count = $row[ 'count' ];
		
		if ( $params->showOnlyFavorite )
		{
		  $joinCondition = "inner {$favoritesCondition}";
		}
		else
		{
		  $joinCondition = "left {$favoritesCondition}";
		}
		
		//select t1.*, t2.owner from looperman as t1 left join favorites as t2 on t1.hash=t2.hash and t2.source='MAIN' and t2.owner=16 where t1.category='Synth Loops' and t1.genre='Deep House' order by t1.id desc limit 0,100
		
		//data
		$r = mysql_query( "select {$this->result_fields}, t2.owner from {$this->library_table} as t1 {$joinCondition} {$whereStr} order by t1.{$params->orderBy} {$params->order} limit {$params->offset},{$params->limit}" );
		 
		if ( ! $r )
		  throw new Exception( mysql_error(), mysql_errno() );
		
		$result->data = array();
		//$result->q = $whereStr;
		  
		while ( $row = mysql_fetch_assoc( $r ) )
		{
		  $row[ 'lqurl' ] = $this->getLQAudioLink( $row[ 'hash' ] );
		  $row[ 'hqurl' ] = $this->getHQAudioLink( $row[ 'hash' ], $row[ 'type' ] );
		  
		  if ( ! $this->isItValidKey( $row[ 'mkey' ] ) )
		  {
		    $row[ 'mkey'] = null;
		  }
		  
		  //Добавляем идентификатор библиотеки
		  $row[ 'source_id' ] = self::LIBRARY_ID;
		  
		  //Присутствует ли этот сэмпл в избранном
		  $row[ 'favorite' ] = isset( $row[ 'owner' ] );
		  
		  unset( $row[ 'owner' ] );
		  
		  $result->data[] = $row;
		}
		
		return $result;
	  }
	  
   }
   
   //$z = new MainAPI();
   //echo 'http://' . $_SERVER[ 'HTTP_HOST' ] .  '/mp3/' . 'filename' . '.mp3';
   /*
   $g->categories = array(  );
   $g->genres = array( 'Dubstep' );
   $g->tempoFrom = 0;
   $g->tempoTo = 255;
   $g->limit = 20;
   //print_r( $z->getGenresAndCategories( null ) );
   print_r( $z->search( $g ) ); 
   */
  
?>