<?php

require_once __DIR__ . '/../Services/API.php';

class VKPaymentsCallback extends API
{
  const BASE_ERROR          = 1; //общая ошибка. 
  const TEMPORARY_DB_ERROR  = 2; //временная ошибка базы данных. 
  const INVALID_SIGNATURE   = 10; //несовпадение вычисленной и переданной подписи. 
  const REQUEST_NOT_CORRECT = 11; //параметры запроса не соответствуют спецификации;
                                  //в запросе нет необходимых полей;   
                                  //другие ошибки целостности запроса.
  const ITEM_NOT_EXISTS = 20; //товара не существует. 								  
  const ITEM_TEMPORARY_UNAVAILABLE = 21; //товара нет в наличии. 
  const USER_NOT_EXISTS = 22; //пользователя не существует.
  const OTHER_ERROR = 100; //Любая другая ошибка

  // Защищенный ключ приложения
  private $APPLICATION_SECRET_KEY =  'umFAlFfSC6htif9qGuXX'; 
  //Идентификатор приложэния
  private $APPLICATION_ID = '3395763';
  
  //Префикс идентификатора товара монет ( coins_1000 ) и т.д.
  private $ITEM_ID_PREFIX = 'coins';
  
  //Запрос
  private $input;
  
  //Отправляемый ответ
  private $response;
  
  //Тип запроса
  public $notification_type;
  
  private function oopsError( $code, $msg, $critical = true )
  {
    $this->response[ 'error' ] = array(
                                  'error_code' => $code,
                                  'error_msg' => $msg,
                                  'critical' => $critical
                                );
  }
  
  
  //Проверяет подпись запроса, анализирует пришедшие данные
  //Возвращает true  - если все ok
  //           false - ошибка
  public function load() 
  {
    $this->input = $_POST;
    
    // Проверка подписи
    $sig = $this->input[ 'sig' ];
    unset( $this->input[ 'sig'] );
    ksort( $this->input );
    $str = '';
    foreach ( $this->input as $k => $v )
	{
      $str .= $k . '=' . $v;
    }

    if ( $sig != md5( $str . $this->APPLICATION_SECRET_KEY ) )
	{
	  $this->oopsError( self::INVALID_SIGNATURE, 'Несовпадение вычисленной и переданной подписи запроса.' );
	
      return false;								
    }
	
	if ( $this->input[ 'app_id' ] != $this->APPLICATION_ID )
	{
	  $this->oopsError( self::REQUEST_NOT_CORRECT, 'Передан неверный идентификатор приложения' );
	  
	  return false;
	}
	
	try
	{
	  //Проверяем зарегистрирован ли такой пользователь
	  if ( ! $this->netUserExists( $this->input[ 'user_id' ] ) )
	  {
	    $this->oopsError( self::USER_NOT_EXISTS, "Пользователь с идентификатором {$this->input[ 'user_id ' ]} не зарегистрирован в приложении"  );
	  
	    return false;
	   }
	}
	catch ( Exception $e )
	{
	  $this->oopsError( self::TEMPORARY_DB_ERROR, 'Ошибка доступа к БД', false );
	  return false;
	}
	
	$this->notification_type = $this->input[ "notification_type" ];
	
	return true;
  }
  
  //Автоматически обрабатывает запрос
  public function process() 
  {
    if ( $this->load() )
	{
	  switch( $this->notification_type )
	   {
	     case 'get_item'                 : $this->processGetItem(); break;
		 case 'get_item_test'            : $this->processGetItem( true ); break;
		 case 'order_status_change'      : $this->processOrderStatusChange(); break;
		 case 'order_status_change_test' : $this->processOrderStatusChange( true ); break;
	   }
	}
	 
	$this->flush(); 
  }
  
  private function formatCoins( $numCoins ) 
  {
    if ( ( $numCoins > 10 ) && ( $numCoins < 15 ) )
	 return 'монет';
  
    if ( strlen( $numCoins ) > 1 )
	{
	  $numCoins = (int) substr( $numCoins, strlen( $numCoins ) - 1 );
	}
  
    if ( $numCoins == 1 ) return 'монета';
	if ( ( $numCoins > 1 ) && ( $numCoins < 5 ) ) return 'монеты';
	
	return 'монет';
  }
  
  //Парсит идентификатор товара и определяет его стоимость
  //количество монет - если все ok
  //false - если идентификатор некорректный
  private function extractNumCoins( $item )
  {
     $item_id = explode( '_', $item );
	
	//Проверяем корректность идентификатора товара
	if ( count( $item_id ) < 2 )
	{
	   $this->oopsError( self::ITEM_NOT_EXISTS, 'Неверный идентификатор товара' );
	   return false;
	}
	
	if ( $item_id[ 0 ] != $this->ITEM_ID_PREFIX )
	{
	  $this->oopsError( self::ITEM_NOT_EXISTS, 'Неверный идентификатор товара' );
	   return false;
	}
	
	if ( ! ctype_digit( $item_id[ 1 ] ) )
	{
	  $this->oopsError( self::ITEM_NOT_EXISTS, 'Неверный идентификатор товара' );
	  return false;
	}
	
	return (int) $item_id[ 1 ]; 
  }
  
  //Возвращает данные о товаре
  public function processGetItem( $test = false )
  {
    $numCoins = $this->extractNumCoins( $this->input['item'] );
	
	if ( $numCoins === false )
	 {
	   return false;
	 }
  
    $this->response[ 'response' ] = array(
          'item_id' => $this->input[ 'item' ],
          'title' => $numCoins . " " . $this->formatCoins( $numCoins ) . ( $test ? ' тестовый режим ' : '' ),
          'photo_url' => "http://{$_SERVER[ 'HTTP_HOST' ]}/payments/coin.jpg",
          'price' => $numCoins,
		  'expiration' => 86400, //Один день
        );
		
	return true;	
  }
  
  public function processOrderStatusChange( $test = false )
  {
    if ( $this->input['status'] != 'chargeable' )
	 {
	   $this->oopsError( self::OTHER_ERROR, 'status != chargeable' );
	   return false;
	 }
  
    try
	 {
	   $userInfo = $this->getNetUserInfo( $this->input[ 'user_id' ] );
	 
    //Залогировать заказ
	if ( $test )
	 {
	   $this->input['item'] .=  '_test';
	 }
	
	$r = mysql_query( "insert into {$this->payments_table} ( date, user_id, order_id, net_user_id, item, item_id, price ) values( FROM_UNIXTIME({$this->input['date']}),'{$userInfo['id']}','{$this->input['order_id']}','{$this->input['user_id']}','{$this->input['item']}','{$this->input['item_id']}','{$this->input['item_price']}')" );
	if ( ! $r )
	{
	  throw new Exception( mysql_error(), mysql_errno() );
	}
	
	//Получить идентификатор заказа в приложении
	$r = mysql_query( "select id from {$this->payments_table} where order_id={$this->input['order_id']}" );
	
	if ( ! $r )
	{
	  throw new Exception( mysql_error(), mysql_errno() );
	}
	
	$order_id = mysql_fetch_assoc( $r );
	$order_id = $order_id[ 'id' ];
	
	//Зачислить монеты на счет пользователя
	$r = mysql_query( "update {$this->users_table} set money=money+{$this->input['item_price']} where id={$userInfo['id']}" );
	
	if ( ! $r )
	{
	  throw new Exception( mysql_error(), mysql_errno() );
	}
	
	//Послать команду, для обновления данных клиенту
	$this->addCommand( $userInfo['id'], $this->COMMAND_UPDATE_DATA, $this->users_table . ':money' );
	
	}
	catch ( Exception $e )
	{
	   $this->oopsError( self::TEMPORARY_DB_ERROR, 'Ошибка доступа к БД', false );
	   return false;
	}
	
	//Оформить ответ
	$this->response['response'] = array(
                    'order_id' => $this->input['order_id'],
                    'app_order_id' => $order_id,
        );
	
	return true;
  }
  
  //Посылает ответ серверу Вконтакте
  public function flush() 
  {
    header("Content-Type: application/json; encoding=utf-8");
	echo json_encode( $this->response );
  }
}

$p = new VKPaymentsCallback();
$p->process();
?> 