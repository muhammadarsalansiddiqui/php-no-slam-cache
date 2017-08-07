<?php
/////////////////////////
// Classloader
spl_autoload_register( function ($className) {

  // Próba wg. namespace
  $className = ltrim($className, '\\');
  $fileName  = '';
  $namespace = '';
  if ($lastNsPos = strripos($className, '\\')) {
      $namespace = substr($className, 0, $lastNsPos);
      $className = substr($className, $lastNsPos + 1);
      $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
  }
  $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
  
  $tmp =  __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$fileName;
  
  
  if (file_exists( $tmp )) {
      require_once $tmp;
  }

} );
