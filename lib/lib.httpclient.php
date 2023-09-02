<?php
if (!function_exists('curl_init')) {
  echo "PHP does not have the curl extension installed<br>\n";
  exit(1);
}

define('DEV_MODE', false);

//open handle
$ch = curl_init();

$curl_headers = array();

function request($url, $options = array()) {
  if (!$url) {
    echo gettrace(), " no url given to curlHelper<br>\n";
    exit(1);
  }

  $defaultOptions = array(
    'method' => 'AUTO',
    'headers' => array(),
    'user' => false,
    'pass' => false,
    'body' => false,
    'devData' => false,
    'multipart' => 'auto',
    // timeout?
  );
  foreach($defaultOptions as $k => $v) {
    if (!isset($options[$k])) $options[$k] = $v;
  }
  extract($options);
  $header = '';

  if ($multipart !== 'auto') {
    if ($multipart) {
      // $body needs to be an array
      if (!is_array($body)) {
        // convert string to array?
      }
    } else {
      // $body needs to be a string
      if (is_array($body)) {
        // convert to string
        $list = array();
        foreach($body as $key => $value) {
          $list[] = $key . '=' . urlencode($value);
        }
        $body = join('&', $list);
      }
    }
  }

  // workaround curlHelper compatibility (in at least consume_beRsrc)
  if ($headers === '') $headers = array();
  if (count($headers)) {
    $header = $headers;
  }

  global $ch;
  if (DEV_MODE) {
    $start = microtime(true);
  }
  $fields = $body;

  // maybe only do this if AUTO or POST?
  //if (is_array($fields) && ($method === 'AUTO' || $method === 'POST')) {
  $hasFields = (is_array($fields) ? count($fields) : $fields) ? true : false;

  curl_reset($ch); // php 5.5+
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

  //set the url, number of POST vars, POST data
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, $hasFields);

  // can be an urlencoded string or an array
  // an array will set "Content-type to multipart/form-data"
  // if you send files, this has to be an array
  // https://stackoverflow.com/a/15200804
  //echo 'post', print_r($fields, 1), "\n";
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  if ($header) {
    $headers = array();
    foreach($header as $k => $v) {
      $headers[] = $k . ': ' . $v;
    }
    //echo 'headers', print_r($headers, 1), "\n";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }
  if ($user && $pass) {
    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  }
  if ($method === 'AUTO') {
    $method = 'POST'; // for logging
    if (!$hasFields) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
      $method = 'GET'; // for logging
    } else {
      //echo "fields[$fields_string]<br>\n";
    }
  } else
  if ($method ===' PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
  } else
  if ($method === 'GET') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
  } else
  if ($method === 'DELETE') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  } else
  if ($method === 'HEAD') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    // apache writes all this stuff to the error_log too
    //curl_setopt($ch, CURLOPT_VERBOSE, 1);
  }
  // to get the request header, but we have those...
  // but maybe we need to see what's actuall sent on the wire?
  //curl_setopt($ch, CURLINFO_HEADER_OUT, 1); // this makes curl_getinfo($ch, CURLINFO_HEADER_OUT) work

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // we need this for everything
  //if (DEV_MODE) {
    curl_setopt($ch, CURLOPT_HEADER, true);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //}

  //curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_TIMEOUT, 45);

  //execute post
  $txt = curl_exec($ch);
  //echo "txt[$txt]\n";

  $infos = curl_getinfo($ch); // curl_info
  //print_r($infos);
  $header_size = $infos['header_size'];
  $respHeader = substr($txt, 0, $header_size);
  global $curl_headers;
  $curl_headers[] = $respHeader;
  if ($method === 'HEAD') {
    $result = $respHeader;
  } else {
    $result = substr($txt, $header_size);
  }

  if (DEV_MODE) {
    global $curlLog;
    $curlLog[] = array(
      'method' => $method,
      'url' => $url,
      'trace' => gettrace(),
      'postData' => $fields,
      'took' => (microtime(true) - $start) * 1000,
      'requestHeaders' => $header,
      // gets the out header...
      //'responseHeaders' => curl_getinfo($ch, CURLINFO_HEADER_OUT),
      'responseHeaders' => $respHeader,
      'result' => $result,
      'curlInfo' => $infos,
      'devData' => $devData,
      // $l['curlInfo']['http_code']
      //'statusCode' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    );
  }

  return $result;
}

?>