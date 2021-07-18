<?php
error_reporting(E_ALL ^ E_NOTICE);
require_once '../config.php';
require_once '../phplib/NagiosApi.php';
require_once '../phplib/NagiosLivestatus.php';
require_once '../phplib/utils.php';

$supported_methods = ["ack", "downtime", "enable", "disable"];

function nagdash_get_user() {
  $username = '';
  // Try to get the login name from the $_SERVER variable.
  if (isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authorization_header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
      $authorization_header = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // If using CGI on Apache with mod_rewrite, the forwarded HTTP header appears in the redirected HTTP headers.
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
      $authorization_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // Resemble PHP_AUTH_USER and PHP_AUTH_PW for a Basic authentication from
    // the HTTP_AUTHORIZATION header. See http://www.php.net/manual/features.http-auth.php
    if (!empty($authorization_header)) {
      list($username_temp, $userpass_temp) = explode(':', base64_decode(substr($authorization_header, 6)));
      $username = $username_temp;
    }
  }
  // Check other possible values in different keys of the $_SERVER superglobal
  elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
    $username = $_SERVER['REDIRECT_REMOTE_USER'];
  }
  elseif (isset($_SERVER['REMOTE_USER'])) {
    $username = $_SERVER['REMOTE_USER'];
  }
  elseif (isset($_SERVER['REDIRECT_PHP_AUTH_USER'])) {
    $username = $_SERVER['REDIRECT_PHP_AUTH_USER'];
  }
  elseif (isset($_SERVER['PHP_AUTH_USER'])) {
    $username = $_SERVER['PHP_AUTH_USER'];
  }
echo $username;
  return $username;
}



if (!isset($_POST['nag_host'])) {
    echo "Are you calling this manually? This should be called by Nagdash only.";
} else {
    $nagios_instance = $_POST['nag_host'];
    $action = $_POST['action'];
    $details = [
            "host" => $_POST['hostname'],
            "service" => ($_POST['service']) ? $_POST['service'] : null,
            "author" => function_exists("nagdash_get_user") ? nagdash_get_user() : "Nagdash",
            "duration" => ($_POST['duration']) ? ($_POST['duration'] * 60) : null,
            "comment" => "{$action} from Nagdash"
            ];


    if (!in_array($action, $supported_methods)) {
        echo "Nagios-api does not support this action ({$action}) yet. ";
    } else {

        foreach ($nagios_hosts as $host) {
            if ($host['tag'] == $nagios_instance) {
                $nagios_api = NagdashHelpers::get_nagios_api_object($api_type,
                    $host["hostname"], $host["port"], $host["protocol"], $host["url"]);
            }
        }

        switch ($action) {
        case "ack":
            $ret = $nagios_api->acknowledge($details);
            break;
        case "downtime":
            $ret =  $nagios_api->setDowntime($details);
            break;
        case "enable":
            $ret = $nagios_api->enableNotifications($details);
            break;
        case "disable":
            $ret = $nagios_api->disableNotifications($details);
            break;
        }

        echo $ret["details"];

    }
}


