<?php
  require_once __DIR__ . '/SessionManager.php';
  require_once __DIR__ . '/Error.php';

   class Publisher extends SessionManager
   {
     /*
	 Вызван метод Begin, ничего не происходит, запись только что сгененрирована
	 */
     protected $PUBLISHER_STATUS_STARTING   = 'starting';
	 /*
	 Идет процесс загрузки данных на сервер
	 */
	 protected $PUBLISHER_STATUS_UPLOADING  = 'uploading';
	 /*
	 Процесс загрузки данных на сервер завершен
	 */
	 protected $PUBLISHER_STATUS_UPLOADED   = 'uploaded';
	 /*
	 Идет обработка данных
	 */
	 protected $PUBLISHER_STATUS_PROCESSING = 'processing';
	 /*
	 Данные обработаны
	 */
	 protected $PUBLISHER_STATUS_PROCESSED  = 'processed';
	 /*
	 Идет процесс публикации в соц. сети
	 */
	 protected $PUBLISHER_STATUS_PUBLISHING = 'publishing';
	 /*
	 Процесс публикации завершен
	 */
	 protected $PUBLISHER_STATUS_PUBLISHED  = 'published';
	 /*
	 Публикация завершена
	 */
	 protected $PUBLISHER_STATUS_DONE       = 'done';
   
     /*
	 Имя таблицы хранящей данные сессий публикации
	 */
     protected $publisher_table = 'psessions';
	 
	 /*
	 Имя временной директории в которую происходит загрузка данных
	 */
	 protected $temp_dir = '/temp/';
	 /*
	 Имя директории для хранения результирующего файла
	 */
	 protected $download_dir = '/download/';
   
     /*
	 Конструктор класса ( инициализация переменных )
	 */
	 function __construct()
	 {
	   parent::__construct();  
	   
	   $this->temp_dir     = $_SERVER[ 'DOCUMENT_ROOT' ] . $this->temp_dir;
	   $this->download_dir = $_SERVER[ 'DOCUMENT_ROOT' ] . $this->download_dir;
	   
	   $this->SUPPORTED_FORMATS      = array( Publisher::$FORMAT_WAVE, Publisher::$FORMAT_MP3 );
	   $this->SUPPORTED_WAVE_QUALITY = array( Publisher::$QUALITY_16_BIT_44100, Publisher::$QUALITY_24_BIT_44100, Publisher::$QUALITY_32_BIT_44100 );
	   $this->SUPPORTED_MP3_QUALITY  = array( Publisher::$QUALITY_128_K, Publisher::$QUALITY_192_K, Publisher::$QUALITY_320_K );
	 }
	 
	  /*
	 Создает директорию, если она ещё не создана
	 $directory - директория которую необходимо создать
	 @return    - ERROR или OK
	 */
	 private function createDirectory( $directory )
	 {
	   if ( ! file_exists(  $directory ) )
	   {
	     if ( ! mkdir( $directory, 0777 ) )
		 {
		   return Error::$ERROR;
		 }
	   }
	   
	   return Error::$OK;
	 }
	 
	 /*
	 Ищет информацию о зарегистрированном паблишире
	 @publisher_id - идентификатор паблишера
	 @fields       - список запрашиваемых полей связанных с паблишером
	 @return       - данные о паблишере или false, если информации не найдено
	 */
	 protected function getPublisherData( $publisher_id, $fields = 'session_id' )
	 {
        $r = mysql_query( "select {$fields} from {$this->publisher_table} where session_id='{$publisher_id}'" );
		
		if ( ! $r )
	    { 
		  throw new Exception( mysql_error(), mysql_errno() );
	    }
		
		if ( mysql_num_rows( $r ) > 0 )
	    {
	     return mysql_fetch_assoc( $r );
	    }
	 
	    return false; 
	 }
	 
	 /*
	 Изменяет статус паблишера
	 @publisher_id - идентификатор паблишера
	 @status       - новый статус паблишера
	 */
	 private function setPublisherStatus( $publisher_id, $status )
	 {
	   $r = mysql_query( "update {$this->publisher_table} set status='{$status}' where session_id='{$publisher_id}'" );
	   
	   if ( ! $r )
	   {
	     throw new Exception( mysql_error(), mysql_errno() );
	   }
	 }
	 
	 /*
	 Создает новую запись паблишера в таблице
	 @return идентификатор вновь созданного паблишера
	 */
	 private function createPublisher( $sessionData, $project_id ) 
	 {
	   //Генерируем идентификатор паблишера
	   $publisher_id = md5( $sessionData[ 'user_id' ] . time() );
	   
	   //Заносим запись в таблицу "паблишеров"
	   $r = mysql_query( "insert into {$this->publisher_table} (session_id,user_id,project_id,status) values('{$publisher_id}',{$sessionData[ 'user_id' ]},{$project_id},'{$this->PUBLISHER_STATUS_STARTING}')" );
	   
	   if ( ! $r )
	   {
	    throw new Exception( mysql_error(), mysql_errno() );
	   }
	   
	   return $publisher_id;
	 }
	 
	 /*
	 Инициирует процесс публикации микса
	 $session_id   - идентификатор сессии
	 $project_id   - идентификатор проекта
	 @return       - возвращает идентификатор вновь созданного паблишера (MD5 hash)
	 */
     public function begin( $session_id, $project_id )
	 {
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	      return $this->SESSION_NOT_FOUND_ERROR;
	   }
	 
	   //Создаем временную директорию
	   if ( $this->createDirectory( $this->temp_dir ) === Error::$ERROR )
	   {
	     return Error::$ERROR;
	   }
	   
	   //Создаем результирующую директорию для загрузки
	   if ( $this->createDirectory( $this->download_dir ) === Error::$ERROR )
	   {
	     return Error::$ERROR;
	   }
	   
	   return $this->createPublisher( $sessionData, $project_id );  
	 }
	 
	 protected function getTemporaryFile( $publisher_id )
	 {
	   return $this->temp_dir . $publisher_id . '.pcm';
	 }
	 
	 protected function getOutputFile( $publisher_id, $format )
	 {
	   return $this->download_dir . $publisher_id . '.' . $this->getFileExtension( $format );
	 }
	 
	 protected function getFileExtension( $format )
	 {
	   switch( $format )
	   {
	     case Publisher::$FORMAT_WAVE : return 'wav';
		 case Publisher::$FORMAT_MP3  : return 'mp3';
	   }
	   
	   return 'mp3';
	 }
	 
	 /*
	 Передает порцию данных для сохранения на сервере и последующей работе с данными
	 $session_id   - идентификатор сессии
	 $publisher_id - идентификатор паблишера, созданный ранее методом begin
	 $data         - данные которые необходимо сохранить
	 $size         - размер переданных данных
	 $total        - общий размер передаваемых данных
	 @return       -
	 */
	 public function upload( $session_id, $publisher_id, $data, $size, $total )
	 {
	   //Авторизован ли пользователь
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	      return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Инициирован ли паблишер
	   $publisherData = $this->getPublisherData( $publisher_id, 'user_id,status' );
	   
	   if ( $publisherData === false )
	   {
	      return Error::$PUBLISHER_NOT_FOUND_ERROR;
	   }
	   
	   if ( $publisherData[ 'status' ] !== $this->PUBLISHER_STATUS_UPLOADING )
	   {
	     $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_UPLOADING );
	   }
	   
	   $file_name = $this->getTemporaryFile( $publisher_id );
	   
	   $file = @fopen( $file_name, "ab" );
	   
	   if ( $file === false )
	   {
	     return Error::$ERROR;
	   }
	   
	   if ( flock( $file, LOCK_EX ) === false )
	   {
	     return Error::$ERROR;
	   }
	   
	   $wrote = fwrite( $file, $data->data );
	   
	   if ( $wrote === false )
	   {
	     return Error::$ERROR;
	   }
	           	   
	   if ( flock ( $file, LOCK_UN ) === false )
	   {
		  return Error::$ERROR;
	   }
		
		if ( fclose( $file ) === false )
		{
		  return Error::$ERROR;
		}
		
		//Фиксируем изменения статуса
		$result = new stdClass();
		$result->wrote    = $wrote;
		$result->size     = $size;
		$result->total    = filesize( $file_name );
		$result->done     = $total == $result->total;
		
		if ( $result->done )
		{
		  $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_UPLOADED );
		}
		
		return $result;
	 }
	 
	 /*
	 Список форматов в которые можно преобразовывать данные
	 */
	 public static $FORMAT_WAVE = 'wave';
	 public static $FORMAT_MP3  = 'mp3';
	 
	 /*
	 Список возможных вариантов настройки качества
	 */
	 public static $QUALITY_16_BIT_44100 = '16_44100';
	 public static $QUALITY_24_BIT_44100 = '24_44100';
	 public static $QUALITY_32_BIT_44100 = '32_44100';
	 
	 public static $QUALITY_128_K        = '128k';
	 public static $QUALITY_192_K        = '192k';
	 public static $QUALITY_320_K        = '320k';
	 
	 protected $SUPPORTED_FORMATS;
	 protected $SUPPORTED_WAVE_QUALITY;
	 protected $SUPPORTED_MP3_QUALITY;
	 
	 //public static $PROCESSOR_STRING = 'C:/ffmpeg/bin/ffmpeg.exe';
	 public static $PROCESSOR_STRING = 'ffmpeg';
	 
	 /*
	 Обрабатывает загруженные ранее методом upload данные
	 $session_id   - идентификатор сессии
	 $publisher_id - идентификатор паблишера, созданный ранее методом begin
	 $params       - параметры обработки 
	        ->tags - список тегов внедряемых в контейнер
			->format - в какой формат необходимо преобразовать даннные ( константы с префиксом FORMAT ) 
			->quality - качество сжатия ( для каждого формата свои настройки ) ( константы с префиксом QUALITY )
	 
	 */
	 public function process( $session_id, $publisher_id, $params )
	 {
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	      return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Инициирован ли паблишер
	   $publisherData = $this->getPublisherData( $publisher_id, 'user_id' );
	   
	   if ( $publisherData === false )
	   {
	      return Error::$PUBLISHER_NOT_FOUND_ERROR;
	   }
	   
	   //Проверяем переданы ли необходимые параметры в $params
	   if ( ! isset( $params->format ) )
	   {
	     return Error::$NOT_CORRECT_OUTPUT_PARAMS;
	   }
	   
	   if ( ! in_array( $params->format, $this->SUPPORTED_FORMATS ) )
	   {
	     return Error::$NOT_CORRECT_OUTPUT_PARAMS;
	   }
	   
	   if ( ! isset( $params->quality ) )
	   {
	     return Error::$NOT_CORRECT_OUTPUT_PARAMS;
	   }
	   
	   $tagsString = '';
	   
	   if ( isset( $params->tags ) )
	   {
	     foreach( $params->tags as $key => $value )
         {
           $value       = addslashes( $value );
		   $tagsString .= '-metadata ' .$key . '="' . $value . '" ';
         }
	     
	     $tagsString = trim( $tagsString );
	   }
	   
	   $srcFile    = $this->getTemporaryFile( $publisher_id );
	   $dstFile    = $this->getOutputFile( $publisher_id, $params->format );
	   
	   $commandLine = "-f f32be -ar 44.1k -ac 2 -i {$srcFile} ";
	   
	   //WAVE
	   if ( $params->format == Publisher::$FORMAT_WAVE )
	   {
	     if ( ! in_array( $params->quality, $this->SUPPORTED_WAVE_QUALITY ) )
		 {
		   return Error::$NOT_SUPPORTED_QUALITY;
		 }
		 
		 $commandLine .= "-acodec ";
		 
		 switch( $params->quality )
		 {
		   case Publisher::$QUALITY_16_BIT_44100 : $commandLine .= "pcm_s16le"; break;
		   case Publisher::$QUALITY_24_BIT_44100 : $commandLine .= "pcm_s24le"; break;
		   case Publisher::$QUALITY_32_BIT_44100 : $commandLine .= "pcm_f32le"; break;
		 }
		 
		 $commandLine .= " ";
		 
		 if ( strlen( $tagsString ) > 2 )
		 {
		   $commandLine .= "{$tagsString} ";
		 }
		 
		 $commandLine .= $dstFile;
	   }
	   else
	   //MP3
	   if ( $params->format == Publisher::$FORMAT_MP3 )
	   {
	     if ( ! in_array( $params->quality, $this->SUPPORTED_MP3_QUALITY ) )
		 {
		   return Error::$NOT_SUPPORTED_QUALITY;
		 }
		 
		 $commandLine .= "-acodec libmp3lame -ab  ";
		 
		 switch( $params->quality )
		 {
		   case Publisher::$QUALITY_128_K : $commandLine .= '128k'; break;
		   case Publisher::$QUALITY_192_K : $commandLine .= '192k'; break;
		   case Publisher::$QUALITY_320_K : $commandLine .= '320k'; break;
		 }
		 
		 $commandLine .= ' ';
		 
		 if ( strlen( $tagsString ) > 2 )
		 {
		   $commandLine .= "{$tagsString} -id3v2_version 3 -write_id3v1 1 ";
		 }
		 
		 $commandLine .= $dstFile;
	   }
	   
	   $commandLine = Publisher::$PROCESSOR_STRING . ' ' . $commandLine;
	   
	   $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_PROCESSING );
	  
	   exec( $commandLine, $output, $return_var );
	   
	   $result = new stdClass();
	   $result->output     = $output;
	   $result->return_var = $return_var;
	   $result->url        = $this->getDownloadURL( $publisher_id, $params->format );
	   //$result->commandLine = $commandLine; //Эту строчку необходимо закомментировать
	   
	   $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_PROCESSED );
	   
	   return $result;
	 }
	 
	 /*
	 Загружает обработанный mp3 файл на сервер "Вконтакте", "Публикация"
	 */
	 public function publish( $session_id, $publisher_id, $url )
	 {
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	      return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Инициирован ли паблишер
	   $publisherData = $this->getPublisherData( $publisher_id, 'user_id' );
	   
	   if ( $publisherData === false )
	   {
	      return Error::$PUBLISHER_NOT_FOUND_ERROR;
	   }
	   
	   $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_PUBLISHING );
	   
	   $upload   = $this->getOutputFile( $publisher_id, Publisher::$FORMAT_MP3 );
       $postdata = array( 'file' => "@{$upload}" );

       $ch = curl_init();
       curl_setopt($ch, CURLOPT_URL, $url);
	   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	   curl_setopt($ch, CURLOPT_POST, true); 
       curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
       curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
       $result = curl_exec($ch);
	   
       curl_close( $ch );
	   
	   $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_PUBLISHED );
	   
	   return json_decode( $result );
	 }
	 
	 //protected $DOWNLOAD_HOST = 'music3000/get/';
	 protected $DOWNLOAD_HOST = 'musconstructor.com/get/';
	 
	 private function getDownloadURL( $publisher_id, $format )
	 {
	   return $this->getProtocol() . $this->DOWNLOAD_HOST . '?id=' . $publisher_id . '&format=' . $format;
	 }
	 
	 /*
	 Завершает процесс публикации микса и высвобождает ресурсы
	 $session_id   - идентификатор сессии
	 $publisher_id - идентификатор паблишера, созданный ранее методом begin
	 */
	 public function end( $session_id, $publisher_id )
	 {
	   $sessionData = $this->getSessionData( $session_id, 'user_id' );
	   
	   if ( $sessionData === false )
	   {
	      return Error::$SESSION_NOT_FOUND_ERROR;
	   }
	   
	   //Инициирован ли паблишер
	   $publisherData = $this->getPublisherData( $publisher_id, 'user_id' );
	   
	   if ( $publisherData === false )
	   {
	      return Error::$PUBLISHER_NOT_FOUND_ERROR;
	   }
	   
	   //Удаляем загруженный файл
	   @unlink( $this->getTemporaryFile( $publisher_id ) );
	   
	   $this->setPublisherStatus( $publisher_id, $this->PUBLISHER_STATUS_DONE );
	   
	   return Error::$OK; 
	 }
   }
?>