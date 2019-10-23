<?php

  require_once __DIR__ . '/../Services/Publisher.php';

  class PlayList extends Publisher
  {
    public function show()
	{
	  $files = glob( $this->download_dir . "*.mp3" );
	  
	  foreach( $files as $filename )
	  {
	    echo 'file ' . $filename . '</br>';
	  }
	}
  }
  
  $p = new PlayList();
  $p->show();
?>