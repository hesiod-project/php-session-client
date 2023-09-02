<?php

include_once 'lib.httpclient.php';

function jsonAsk($url, $options = false) {
  if (!$url) {
    echo "jsonAsk no url<br>\n";
    return false;
  }
  $json = request($url, $options);
  if (!$json) {
    echo "jsonAsk no json<br>\n";
    // obvious invalid
    return;
  }
  if ($json === 'Not Found') {
    // likely forgot /json_rpc
    return;
  }
  // not in any swarm; not done syncing;
  if (strpos($json, 'Service node is not ready:') !== false) {
    return;
  }
  // we don't need to handle invalid hash length
  return json_decode($json, true);
}

?>