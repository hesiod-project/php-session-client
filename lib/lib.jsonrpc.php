<?php

include_once 'lib.httpclient.php';

function jsonrpc($url, $method, $params, $options = false) {
  if (!$url) {
    echo "jsonrpc - no url passed\n";
    return false;
  }
  $body = array(
    'jsonrpc' => '2.0',
    'id' => '0',
    'method' => $method,
    'params' => $params,
  );
  echo "jsonrpc - [$url][$method]\n";
  $jsonRes = request($url, array(
    'method' => 'POST',
    'body' => json_encode($body),
    'headers' => array(
      'Content-Type' => 'application/json',
    ),
  ));
  $data = json_decode($jsonRes, true);
  if ($data === null) {
    echo "jsonrpc - not json? [$jsonRes]<br>\n";
    return $jsonRes;
  }
  return $data;
}

?>