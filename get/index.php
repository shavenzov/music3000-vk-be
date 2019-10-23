<?php
  
  require_once __DIR__ . '/../Services/Publisher.php';
  
  class DownloadAudio extends Publisher
  {
    public function go()
	{
	  if ( ! isset( $_REQUEST[ 'id' ] ) || ! isset( $_REQUEST[ 'format' ] ) )
	  {
	     $this->file_not_found();
		 return;
	  }
	
	  $publisher_id     = $_REQUEST[ 'id' ];
	  $format           = $_REQUEST[ 'format' ];
	  
	  //Проверяем, указан ли поддерживаемый формат
	  if ( ! in_array( $format, $this->SUPPORTED_FORMATS ) )
	  {
	    $this->file_not_found();
		return;
	  } 
	  
	  //проверяем есть ли паблишер с указанным идентификатором
	  $publisherData = $this->getPublisherData( $publisher_id, 'project_id,status' );
	  
	  if ( $publisherData === false )
	  {
	    $this->file_not_found();
		echo( 'publisher not found<br/>' . $publisher_id . '<br/>' );
		return;
	  }
	   
	   //Проверяем, паблишер был полностью опубликован или нет
	   if ( $publisherData[ 'status' ] != $this->PUBLISHER_STATUS_DONE )
	   {
	     echo( 'publisher not completed' );
	     $this->file_not_found();
		 return;
	   }
	   
	   $file = $this->getOutputFile( $publisher_id, $format );
	   
	   //Существует ли файл
	   if ( ! file_exists( $file ) )
	   {
	     $this->file_not_found();
		 return;
	   }
	   
	   //Запрашиваем название проекта связанного с файлом
	   $r = mysql_query( "select name from {$this->projects_table} where id={$publisherData['project_id']}" );
	   
	   if ( ! $r )
	   {
	     $this->file_not_found();
		 return;
	   }
	   
	   if ( mysql_num_rows( $r ) == 0 )
	   {
	     $this->file_not_found();
		 return;
	   }
	   
	   $row = mysql_fetch_assoc( $r );
	   
	   //Все OK, отдаем файл
	   /*
	   необходимо удалить запрещенные символы в имени файла на знак подчеркивания
	   */
	   
	   $deniedSymbols   = array( "\\", "/", ":", "*", "?", '"', "<", ">", "|" );
	   $escapedFileName = str_replace( $deniedSymbols, "_", $row[ 'name' ] );
	   
	   $this->file_force_download( $file, $escapedFileName . '.' . $this->getFileExtension( $format ) );
	}
    
	private function file_not_found()
	{
	  header( $_SERVER['SERVER_PROTOCOL']." 404 Not Found" );
	  echo( $_SERVER['REQUEST_URI']." 404 Not Found" );
	}
	
	private function isIE()
	{
	  return preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) || strpos($_SERVER['HTTP_USER_AGENT'], 'Trident/7.0; rv:11.0') !== false; 
	}
	
    private function file_force_download( $file, $name )
	{
     // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
     // если этого не сделать файл будет читаться в память полностью!
     if (ob_get_level()) {
      ob_end_clean();
     }
     
	 //Поддержка кирилицы для IE
	 if ( $this->isIE() )
	 {
	   $name = rawurlencode( $name );
	 }
	 
	 // заставляем браузер показать окно сохранения файла
     header('Content-Description: File Transfer');
     header('Content-Type: application/octet-stream');
     header('Content-Disposition: attachment; filename="' . $name . '"' );
     header('Content-Transfer-Encoding: binary');
     header('Expires: 0');
     header('Cache-Control: must-revalidate');
     header('Pragma: public');
     header('Content-Length: ' . filesize($file));
     // читаем файл и отправляем его пользователю
     readfile($file);
     }
    
  }
  
  $da = new DownloadAudio();
  $da->go();
  
  exit();
?>