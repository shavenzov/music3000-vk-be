<?php

 class Error
 {
   public static $OK    = 0;
 
   public static $ERROR = -1;
  
   public static $PROJECT_WITH_THIS_NAME_ALREADY_EXISTS = -2; //Проект с таким именем уже существует
   public static $NOT_CORRECT_PROJECT_DATA = -3; //Данные проекта не корректные или содержат ошибку
 
   public static $MAX_PROJECTS_FOR_BASIC_MODE_EXCEEDED_ERROR = -5; //Максимальное количество миксов, для базового аккаунта исчерпано
   public static $MAX_PROJECTS_PER_DAY_EXCEEDED_ERROR        = -10; //Маскимальное количество проектов в день превышено
   public static $NOT_ENOUGH_MONEY_ERROR = -15; //Недостаточно монет для выполнения операции
   
   public static $PRICE_INDEX_NOT_EXISTS = -20; //Указан неверный индекс операции
   public static $SAMPLE_ALREADY_IN_FAVORITE = -25; //Сэмпл уже в списке избранных
   public static $SAMPLE_NOT_FOUND_IN_FAVORITE = -26; //Сэмпл не найден в списке избранных
    
   public static $USER_NOT_REGISTERED_ERROR = - 98; //Пользователь не зарегистрирован
   public static $USER_ALREADY_REGISTERED_ERROR = - 99; //Пользователь уже зарегистрирован
   
   
   public static $SESSION_NOT_FOUND_ERROR = -100;
   
   /*
	 Паблишер с указанным идентификатором не найден
   */
   public static $PUBLISHER_NOT_FOUND_ERROR   = -300;
   
   /*
     Неправильно указаны данные для преобразования
   */
   public static $NOT_CORRECT_OUTPUT_PARAMS   = -310;
   
   /*
     Неподдерживаемый настройки качества кодирования
   */
   public static $NOT_SUPPORTED_QUALITY       = -320;
   
   /*
   Доступ к проекту закрыт
   */
   public static $PROJECT_ACCESS_DENIED = -500;
   
   /*
   Проект не найден
   */
   public static $PROJECT_NOT_FOUND = -510;
 }

?>