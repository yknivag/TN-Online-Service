<?php
namespace transloadit;

class CurlRequest {
  public $method = 'GET';
  public $url = null;
  public $headers = array();
  public $fields = array();
  public $files = array();
  public $curlOptions = array();

  // Apply all passed attributes to the instance
  public function __construct($attributes = array()) {
    foreach ($attributes as $key => $val) {
      $this->{$key} = $val;
    }
  }

  public function getCurlOptions() {
    $url = $this->url;

    $hasBody = ($this->method === 'PUT' || $this->method === 'POST');
    if (!$hasBody) {
      $url .= '?'.http_build_query($this->fields);
    }

    if(!is_array($this->curlOptions)){
        $this->curlOptions = array($this->curlOptions);
    }

    $options = $this->curlOptions + array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $this->method,
      CURLOPT_URL => $url,
      CURLOPT_HTTPHEADER => $this->headers,
    );

    if ($hasBody) {
      $fields = $this->fields;
      foreach ($this->files as $field => $file) {
        if (!file_exists($file)) {
          trigger_error('File ' . $file . ' does not exist', E_USER_ERROR);
          return false;
        }
        if (is_int($field)) {
          $field = 'file_'.($field+1);
        }
        
        // -- Start edit --
        // Edit by Aart Berkhout involving issue #8: CURL depricated functions (PHP 5.5)
        // https://github.com/transloadit/php-sdk/issues/8
        if (function_exists('curl_file_create')) {
          // For >= PHP 5.5 use curl_file_create
          $fields[$field] = curl_file_create($file);
        }else{
          // For < PHP 5.5 use @filename API
          $fields[$field] = '@'.$file;
        }
        // -- End edit --
        
      }
      $options[CURLOPT_POSTFIELDS] = $fields;
    }

    return $options;
  }

  public function execute($response = null) {
    $curl = curl_init();
    
    // -- Start edit --
    // For PHP 5.6 Safe Upload is required to upload files using curl in PHP 5.5, add the CURLOPT_SAFE_UPLOAD = true option
    if (defined('CURLOPT_SAFE_UPLOAD')) {
          curl_setopt($curl, CURLOPT_SAFE_UPLOAD, function_exists('curl_file_create') ? true : false);  
    }
    // -- End edit --

    curl_setopt_array($curl, $this->getCurlOptions());

    if (!$response) {
      $response = new CurlResponse();
    }
    $response->data = curl_exec($curl);
    $response->curlInfo = curl_getinfo($curl);
    $response->curlErrorNumber= curl_errno($curl);
    $response->curlErrorMessage = curl_error($curl);

    curl_close($curl);

    return $response;
  }

}
