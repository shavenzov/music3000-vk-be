<?php

 require_once __DIR__ . '/../Services/Publisher.php';
 
 class PublisherCleaner extends Publisher
 {
   //Максимальное время после которого паблишер и ассоциированные с ним файлы перестают храниться на сервере ( в секундах )
   const publisher_max_live = 43200; //12 часов
   
   public function clean() 
   { 
	  //Удаляем все паблишеры с истекшим сроком
	  $r = mysql_query( "delete from {$this->publisher_table} where UNIX_TIMESTAMP() - UNIX_TIMESTAMP( touched ) > " . self::publisher_max_live );
	   
	  if ( ! $r )
	  {
	    die ( mysql_error() . ' ' . mysql_errno() );
	  }
	  
	  //Удаляем файлы с истекшим сроком жизни publisher_max_live
	  $this->removeExpiredFiles( $this->download_dir );
	  $this->removeExpiredFiles( $this->temp_dir );
   }
   
   /*
   Расширения искомых файлов для удаления
   */
   const FILE_EXTS = "*.{pcm,mp3,wav,ogg}";
   
   /*
   Удаляет все файлы в указанной директории которые существуют больше publisher_max_live
   */
   private function removeExpiredFiles( $directory )
   {
      $files = glob( $directory . self::FILE_EXTS, GLOB_BRACE );
	  
	  foreach( $files as $filename )
	  {
	    if ( ( time() - filemtime( $filename ) ) > self::publisher_max_live )
		{
		  unlink( $filename );
		}
	  }
   }
   
 }
 
 $cleaner = new PublisherCleaner();
 $cleaner->clean();
 
?>