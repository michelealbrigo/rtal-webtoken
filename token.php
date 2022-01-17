<?php
/**
  * PHP authenticated token generator
  * Michele Albrigo - 2021
  * v0.2 - config file and request parameters parsing
  * v0.1 - module import
  * v0.0 - initial structure
 */

// enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
  * Defaults setting
 */
$yaml_cfg_docs=1;
$yaml_cfg_file='/etc/rtal-webtoken/token-cfg.yaml';
$private_key_file='/etc/rtal-webtoken/key-private.pem';
$public_key_file='/etc/rtal-webtoken/key-public.pem';
$system_seed='AAAAAAAAAA';
$ldaps=1;
$ldap_server_port='636';

/**
  * Page header
 */

echo "
<!DOCTYPE html>
<!--[if IE 8]><html class=\"no-js ie89 ie8\" lang=\"it\"><![endif]-->
<!--[if IE 9]><html class=\"no-js ie89 ie9\" lang=\"it\"><![endif]-->
<!--[if (gte IE 9)|!(IE)]><!-->
<html class=\"no-js\" lang=\"it\">
<!--<![endif]-->

<head>
  <meta charset=\"utf-8\">
  <meta http-equiv=\"x-ua-compatible\" content=\"ie=edge\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">

  <!-- <link rel=\"preload\" href=\"./iwt/IWT.min.js\" as=\"script\"> -->

  <!-- include HTML5shim per Explorer 8 -->
  <script src=\"./iwt/vendor/modernizr.js\"></script>

  <link media=\"all\" rel=\"stylesheet\" href=\"./iwt/build.css\">

  <script src=\"./iwt/vendor/jquery.min.js\"></script>

  <title>Preview Layout</title>
</head>
";

echo "
<body class=\"t-Pac\">

  <!--[if IE 8]>
  <script src=\"./iwt/vendor/respond.min.js\"></script>
  <script src=\"./iwt/vendor/rem.min.js\"></script>
  <script src=\"./iwt/vendor/selectivizr.js\"></script>
  <script src=\"./iwt/vendor/slice.js\"></script>
  <![endif]-->

  <!--[if lte IE 9]>
  <script src=\"./iwt/vendor/polyfill.min.js\"></script>
  <![endif]-->

  
  <script>__PUBLIC_PATH__ = './iwt/'</script>

  <script src=\"./iwt/IWT.min.js\"></script>
";

/**
  * Parse configuration file
  * ldap_server: ip or fqdn of an LDAP server
  * ldap_serverport: a valid port number for the ldap_server to be contacted on
  * ldaps: boolean, whether to use SSL or not
  * ldap_baseDN: LDAP base DN for our bind attempt
  * private_key: the private key used to generate tokens
  * public_key: the public key to display on a token page
  * system_seed: a string identifying this server
 */

// open config file and read it
try {
  $cfg_array=yaml_parse_file($yaml_cfg_file,0,$yaml_cfg_docs);
} catch (Exception $e) {
  echo "<b>Warning:</b> Configuration file missing or not in YAML format, using defaults<br>";
}

// if private_key_file and public_key_file both exist, overwrite defaults
if (($cfg_array['private_key'] != '') && ($cfg_array['public_key'] != '')) {
  $private_key_file = $cfg_array['private_key'];
  $public_key_file = $cfg_array['public_key'];
} else {
  echo "<b>Warning:</b> Key-pair missing, using defaults<br>";
}

// if system_seed exist and length is 10, overwrite defaults
if (($cfg_array['system_seed'] != '') && 
    (strlen($cfg_array['system_seed'])==10)) {
  $system_seed = $cfg_array['system_seed'];
} else {
  echo "<b>Warning:</b> System seed missing or wrong length, using default (AAAAAAAAAA)<br>";
}

/**
  * Read request parameters
  * service: page function selection (default = synopsis)
  *     possible values: synopsis, keypair_generation, token_generation, token_decryption
  * opcode: an operation code provided by the user, for inclusion in the encrypted token
  * username: the user's name on the LDAP server
  * password: the user's password on the LDAP server
 */

// Check if request parameters are valid and assign them to work variables
if (isset($_REQUEST['service'])) {
  $service = htmlspecialchars($_REQUEST['service']);
  if ($service == 'token_generation') {
    // Some additional controls for token_generation service
    if (isset($_REQUEST['opcode'])) {
      $opcode = htmlspecialchars($_REQUEST['opcode']);
    }
    if ((isset($_REQUEST['username'])) && (isset($_REQUEST['password']))) {
      $username = htmlspecialchars($_REQUEST['username']);
      $password = htmlspecialchars($_REQUEST['password']);
    } 
  } elseif ($service == 'token_decryption') {
    // Some additional controls for token_decryption service
    if (isset($_REQUEST['token'])) {
      $token = htmlspecialchars($_REQUEST['token']);
    }
  } elseif (($service != 'keypair_generation') && ($service != 'synopsis')) {
    echo "<b>Warning:</b> Unrecognized service<br>";
  }
} else {
  $service = 'synopsis';
}

/**
  * Service: synopsis
  * User input: none
  * System data: none
  * Description: general help about the page
 */

if ($service == 'synopsis') {
  // we force https since we are in 21st century
  $base_url = "https://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
  // print synopsis
  echo "
  Valid services are:
  <ul>
  <li><a href='".$base_url."?service=synopsis'><b>synopsis</b></a> = print this help (default service)</li>
  <li><a href='".$base_url."?service=keypair_generation'><b>keypair_generation</b></a> = generates a valid keypair for token operations, no authentication required</li>
  <li><a href='".$base_url."?service=token_generation'><b>token_generation</b></a> = generates a token, authentication required</li>
  <li><a href='".$base_url."?service=token_decryption'><b>token_decryption</b></a> = decrypts a token with server's current public key, no authentication required, foreign key decryption not supported</li>
  </ul>
  ";
}

/**
  * Service: keypair_generation
  * User input: none
  * System data: none
  * Description: utility function, to generate a key pair to be used for token creation and verification
  * Output: a valid key pair
 */

if ($service == 'keypair_generation') {
  // generate key pair
  $fullkey = openssl_pkey_new(array("private_key_bits" => 2048));
  $pubkey = openssl_pkey_get_details($fullkey)['key'];
  openssl_pkey_export($fullkey, $privkey);
  echo "
  <table>
  <tr>
  <td><pre>".$pubkey."</pre></td><td><pre>".$privkey."</pre></td>
  </tr>
  </table>";
  // print key pair
}

/**
  * Service: token_generation
  * User input: username, password, opcode
  * System data: ldap_server, ldap_serverport, ldaps, ldap_baseDN, private_key, public_key, system_seed, timestamp
  * Description: authenticated token generation, including opcode and username, encrypted with the system's private key
  * Output: a valid token and a public key to validate it
 */

if ($service == 'token_generation') {

  // -------- CAUTION --------
  // temporary dummy values
  $username = 'username';
  $password = 'password';
  $opcode = 'OP202201';
  // -------- CAUTION --------

  // if we know username, password and opcode, we attempt authentication and generate a token, otherwise we present an authentication form
  if (( $username != '' ) && ( $password != '' ) && ( $opcode != '' )) {
    /*
    if ((filter_var($cfg_array['ldap_server'],FILTER_VALIDATE_DOMAIN)) &&
      (filter_var($cfg_array['ldap_serverport'],FILTER_VALIDATE_INT)) &&
      (($cfg_array['ldaps'] == 0) || ($cfg_array['ldaps'] == 1)) &&
      (filter_var($cfg_array['ldap_baseDN'] != ''))) {

      } else {
        echo "<b>Warning:</b> Authentication server configuration invalid<br>";
      }
    */
    echo $private_key_file."<br>";
    echo $public_key_file."<br>";
    $privkey = openssl_pkey_get_private(file_get_contents($private_key_file));
    $pubkey = openssl_pkey_get_public(file_get_contents($public_key_file));
    $original = $system_seed.":".$opcode.":".$username.":"."timestamp";
    openssl_private_encrypt($original, $bintoken, $privkey);
    echo "<table>
    <tr><td><pre>".openssl_pkey_get_details($pubkey)['key']."</pre></td><td>TOKEN:".base64_encode($token)."</td><td>Original:".$original."</td></tr>
    </table>
    ";

  } else {
    echo "
    Authentication form goes here<br>
    ";
  }

  // if ldap_server, ldap_serverport, ldaps, ldap_baseDN do not exist or aren't well formed, throw an error
  if ((filter_var($cfg_array['ldap_server'],FILTER_VALIDATE_DOMAIN)) &&
      (filter_var($cfg_array['ldap_serverport'],FILTER_VALIDATE_INT)) &&
      (($cfg_array['ldaps'] == 0) || ($cfg_array['ldaps'] == 1)) &&
      (filter_var($cfg_array['ldap_baseDN'] != ''))) {

      } else {
        echo "<b>Warning:</b> Authentication server configuration invalid<br>";
      }

  // if user and password are empty, print login page
  // else if user and password are both present
  //    check user and password validity and size
  //    if ldap bind succeeds
  //$ldapconn=ldap_connect('ldaps://server.domain.tld',636);
  //$ldapbind=ldap_bind($ldapconn, 'uid=username,cn=CN,dc=domain,dc=country', $ldappassword);
  //      create token string
  //      if opcode is empty pad string with zeroes
  //      encrypt token string with private_key
  //      display encrypted token
  //      display public_key
  //    if ldap bind fails
  //      display error
  // else
  //      display error

}

/**
  * Service: token_decryption
  * User input: token
  * System data: public_key
  * Description: decrypts a token and displays its content
  * Output: username, opcode, system_seed, token creation timestamp, human readable token creation timestamp, token age
 */

if ($service == 'token_decryption') {
  // if token is empty, print token input box
  // else if token length is ok and characters are allowed
  //    decrypt token with public key
  //    print username
  //    print opcode
  //    print system_seed
  //    print token creation timestamp
  //    print human readable token creation timestamp
  //    print token age
  //    print cleartext token
}

/**
  * Page footer
 */

echo "
</body>
</html>
";

?>
