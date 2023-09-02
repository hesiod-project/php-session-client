<?php

include_once 'lib.jsonrpc.php';
//include_once 'sodium-compat-1.19.0.phar';
include_once 'sodium_compat/autoload.php';

$seedNodeList = array(
  array(
    'ip_url' => 'http://116.203.53.213/',
    'url'    => 'https://storage.seed1.loki.network/json_rpc'
  ),
  // this one borked?
  array(
    'ip_url' => 'http://212.199.114.66/',
    'url'    => 'https://storage.seed3.loki.network/json_rpc'
  ),
  array(
    'ip_url' => 'http://144.76.164.202/',
    'url'    => 'https://public.loki.foundation/json_rpc'
  )
);

class addressBook {
  function address() {
  }
}

class serviceNode {
  var $url;
  var $edKey;
}

// do it once and cache the results
$seedSSCache = false;
function getStorageServersFromSeed() {
  global $seedSSCache, $seedNodeList;
  if ($seedSSCache !== false) {
    return $seedSSCache;
  }
  $seedIdx = array_rand($seedNodeList);
  $seed = $seedNodeList[$seedIdx];
  $params = array(
    'active_only' => true,
    'fields' => array(
      // I don't think we use this...
      //'pubkey_ed25519' => true,
      //'pubkey_x25519' => false,
      //'service_node_pubkey' => false, // same as ed25519 above
      'public_ip' => true,
      'storage_port' => true,
    ),
    'service_node_pubkeys' => array(),
    // be nice to seed nodes
    'limit' => 5,
  );
  //print_r($seed);
  //print_r($params);
  $res = jsonrpc($seed['url'], 'get_service_nodes', $params);
  //print_r($res);
  if (!isset($res['result']['service_node_states']) || !count($res['result']['service_node_states'])) {
    echo "getStorageServersFromSeed - no results\n";
    return false;
  }
  $list = array();
  foreach($res['result']['service_node_states'] as $r) {
    $list[]= $r['public_ip'] . ':' . $r['storage_port'];
  }
  $seedSSCache = $list;
  return $list;
}

function getRetrieveSignatureParams($identity, $method, $params) {
  if (!$identity) {
    echo "getRetrieveSignatureParams - identity missing\n";
    return;
  }
  // so it's not a string...
  if (!$identity['esk']) {
    echo "getRetrieveSignatureParams - identity private missing (esk)\n";
    return;
  }
  $sigTs = (time() * 1000) . '';
  //echo "sigTs[$sigTs]\n";
  $hasNamespace = !empty($params['namespace']);
  $verificationData = $method . ($hasNamespace ? ($params['namespace'] . $sigTs) : $sigTs);
  // argument 1 must be a string
  //echo "verificationData[$verificationData] sigTs[$sigTs]\n";
  $signature = sodium_crypto_sign_detached($verificationData, $identity['esk']);
  //echo "signature[$signature]\n";
  // convert binary to base64

  $retval = array(
    'timestamp' => $sigTs,
    'signature' => base64_encode($signature),
    //'pubkey_ed25519' => bin2hex($identity['epk']),
  );
  if ($hasNamespace) {
    // probably not right
    $retval['namespace'] = $params['namespace'];
  }
  return $retval;
}

function signed_jsonrpc($url, $method, $params, $identity, $options) {
  if (empty($identity['esk']) || empty($identity['epk'])) {
    echo "signed_jsonrpc - edKey missing\n";
    return;
  }
  if (!$url) {
    echo "signed_jsonrpc - url missing\n";
    return;
  }
  // options.snode allows self-signed certs
  $signatureParams = getRetrieveSignatureParams($identity, $method, $params);
  // is optional, should be signed?
  //$params['namespace'] = 0; // needs to be a number
  $signedParams = array_merge($signatureParams, $params);
  $signedParams['timestamp'] = (int)$signatureParams['timestamp']; // always use the ms ts (stomp)
  $signedParams['pubkey_ed25519'] = bin2hex($identity['epk']);
  //echo "signed_jsonrpc [$url][$method][", print_r($signedParams, 1), "]\n";
  $json = jsonrpc($url, $method, $signedParams);
  //echo "signed_jsonrpc - json[$json]\n";
  return $json;
}

// SS only talk ipv4
function getStorageServersFromSS() {
  //echo "getStorageServersFromSS\n";
  // prevent infinite loop by always relying on our (cached) seed list
  $node = getRandomSnode();
  if (!$node) {
    echo "getStorageServersFromSS - getRandomSnode failed\n";
    return;
  }
  //echo "getStorageServersFromSS node[", print_r($node, 1),"]\n";
  // https://github.com/oxen-io/oxen-core/blob/7bda65c5d2f615c317682ee82cd8251d35a60b69/src/rpc/core_rpc_server_commands_defs.h#L1911
  $params = array(
    // same as get_n_service_nodes/get_all_service_nodes
    'endpoint' => 'get_service_nodes',
    'params' => array(
      'active_only' => true,
      'service_node_pubkeys' => array(),
      'fields' => array(
        'pubkey_ed25519' => true,
        'pubkey_x25519' => true,
        'pubkey_legacy' => false,
        'address' => true, // .snode address
        //'service_node_pubkey' => false, // same as ed25519 above
        'public_ip' => true,
        'storage_port' => true,
      ),
    ),
  );
  $ssUrl = 'https://' . $node . '/storage_rpc/v1';
  // get_swarm or oxend_request
  //print_r($json);

  // I don't think it needs to be signed tbh

  // snode option should turn off ssl checking
  $res = jsonrpc($ssUrl, 'oxend_request', $params);
  /*
  $res = signed_jsonrpc($ssUrl, 'oxend_request', $params, $identity, array(
    'snode' => true,
  ));
  */
  if (!isset($res['result']['service_node_states']) || !count($res['result']['service_node_states'])) {
    echo "getStorageServersFromSS - no results\n";
    return false;
  }
  // filter out where public_ip is 0.0.0.0
  $list = array();
  foreach($res['result']['service_node_states'] as $s) {
    $list[]= $s['public_ip'] . ':' . $s['storage_port'];
  }
  //echo "getStorageServersFromSS - result", print_r($res, 1), "\n";
  return $list;
}

function getRandomSnode() {
  //echo "getRandomSnode\n";
  global $seedSSCache;
  if ($seedSSCache === false) {
    $servers = getStorageServersFromSeed();
  } else {
    $servers = getStorageServersFromSS();
  }
  //echo "getRandomSnode - [", print_r($servers, 1), "]\n";
  if (!is_array($servers)) {
    echo "getRandomSnode - FAILED\n";
    // retry
    return getRandomSnode();
  }
  // process servers
  //print_r($servers);
  $s = $servers[array_rand($servers)];
  return $s;
}

$lastXMessages = array();

function pubKeyAsk($pubKey, $method, $param, $identity) {
  if (!$identity) {
    echo "pubKeyAsk - no identity\n";
    return;
  }
  if ($identity['ttl']) {
    echo "pubKeyAsk - identity has ttl\n";
    return;
  }
  $snode = getRandomSwarmNode($pubKey);
  //echo "pubKeyAsk", print_r($snode, 1), "\n";
  if (!$snode['ip']) {
    echo "pubKeyAsk - getRandomSwarmNode no public_ip\n";
    return;
  }
  $ssUrl = 'https://' . $snode['ip'] . ':' . $snode['port_https'] . '/storage_rpc/v1';
  // HF19 requires timestamp, signature, pubkey_ed25519
  $params['pubKey'] = $pubKey;
  $res = signed_jsonrpc($ssUrl, $method, $params, $identity, array(
    'snode' => true,
  ));
  //echo "pubKeyAsk - res [", print_r($res, 1), "]\n";
  // swarm update?
  if ($res['snodes']) {
    // check for reorgs if not asking for the snodes
    if ($method !== 'get_snodes_for_pubkey' && $method !== 'get_swarm') {
      // retry request with new URL
      return pubKeyAsk($pubKey, $method, $param, $identity);
    }
  }
  if ($method === 'retrieve') {
    global $lastXMessages;
    // reprocess messages and filter out non-new message keyed on expiration
    $newMsgs = array();
    foreach($res['messages'] as $msg) {
      if (!isset($lastXMessages[$msg['expiration']])) {
        $lastXMessages[$msg['expiration']] - true;
        $newMsgs[]= $msg;
      } else {
        echo "Filter out duplicate[", $msg['expiration'], "]\n";
      }
    }
    $res['messages'] = $newMsgs;
  }
  return $res;
}

$swarmMap = array();

// how do I message pubkey
function getRandomSwarmNode($pubkey) {
  if (!$pubkey || strlen($pubkey) < 66) {
    echo "getSwarmsnodeUrl - no pubkey of length 66 bytes given\n";
    return;
  }
  global $swarmMap;
  // do we have a recent idea which swarm
  // I don't think we need to take recent into accont
  // if it updates, it'll redirect
  // || (microtime(true) - $swarmMap[$pubkey]['updated_at']) > 3600
  if (!$swarmMap[$pubkey]) {
    // we don't know
    $snode = getRandomSnode();
    $ssUrl = 'https://' . $snode . '/storage_rpc/v1';
    $params = array('pubKey' => $pubkey);
    $res = jsonrpc($ssUrl, 'get_swarm', $params);
    if (empty($res['snodes'])) {
      echo "getRandomSwarmNode - failed, retrying";
      return getRandomSwarmNode($pubkey);
    }
    // if this fails retry
    //print_r($res);
    // update swarmMap
    $swarmMap[$pubkey] = array(
      'snodes' => $res['snodes'],
      'swarm' => $res['swarm'],
      //'updated_at' => $res['t'] / 1000,
    );
  }
  $s = $swarmMap[$pubkey]['snodes'][array_rand($swarmMap[$pubkey]['snodes'])];
  $s['swarm'] = $swarmMap[$pubkey]['swarm'];
  return $s;
}

?>