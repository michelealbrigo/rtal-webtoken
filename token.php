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
  * Key Size = 512 to get short tokens
 */
$yaml_cfg_file='/etc/rtal-webtoken/token-cfg.yaml';
$yaml_cfg_docs=1;
$private_key_file='/etc/rtal-webtoken/key-private.pem';
$public_key_file='/etc/rtal-webtoken/key-public.pem';
$private_key_size=512;
$system_seed='AAAAAAAAAA';
$ldaps=1;
$ldap_server_port='636';

/**
 * Initializations
 */

// we only use https since we are in 21st century
$base_url = "https://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];


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

// if private_key_size exists overwrite default
if ($cfg_array['private_key_size'] != '') {
  $private_key_size = (int)$cfg_array['private_key_size'];
} else {
  echo "<b>Warning:</b> Key size missing, using default<br>";
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
  $reqservice = htmlspecialchars($_REQUEST['service']);
  if ($reqservice == 'token_generation') {
    // Some additional controls for token_generation service
    if (isset($_REQUEST['opcode'])) {
      $reqopcode = htmlspecialchars($_REQUEST['opcode']);
    }
    if ((isset($_REQUEST['username'])) && (isset($_REQUEST['password']))) {
      $requsername = htmlspecialchars($_REQUEST['username']);
      $reqpassword = htmlspecialchars($_REQUEST['password']);
    } 
  } elseif ($reqservice == 'token_decryption') {
    // Some additional controls for token_decryption service
    if (isset($_REQUEST['token'])) {
      $reqtoken = htmlspecialchars($_REQUEST['token']);
    }
  } elseif (($reqservice != 'keypair_generation') && ($reqservice != 'synopsis')) {
    echo "<b>Warning:</b> Unrecognized service<br>";
  }
} else {
  $reqservice = 'synopsis';
}

/**
  * Service: synopsis
  * User input: none
  * System data: none
  * Description: general help about the page
 */

if ($reqservice == 'synopsis') {
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

if ($reqservice == 'keypair_generation') {
  // generate key pair
  $fullkey = openssl_pkey_new(array("private_key_bits" => $private_key_size));
  $pubkey = openssl_pkey_get_details($fullkey)['key'];
  openssl_pkey_export($fullkey, $privkey);
  // print key pair for user consumption
  echo "
  <table>
  <tr><td colspan='2'>Copy these from page source if you intend to use them</td></tr>
  <tr>
  <td><pre>".$pubkey."</pre></td><td><pre>".$privkey."</pre></td>
  </tr>
  </table>";
}

/**
  * Service: token_generation
  * User input: username, password, opcode
  * System data: ldap_server, ldap_serverport, ldaps, ldap_baseDN, private_key, public_key, system_seed, timestamp
  * Description: authenticated token generation, including opcode and username, encrypted with the system's private key
  * Output: a valid token and a public key to validate it
 */

if ($reqservice == 'token_generation') {

  // -------- CAUTION --------
  // temporary dummy values
  $requsername = 'username';
  $reqpassword = 'password';
  $reqopcode = 'OP202201';
  // -------- CAUTION --------

  // if we know username, password and opcode, we attempt authentication and generate a token, otherwise we present an authentication form
  if (( $requsername != '' ) && ( $reqpassword != '' ) && ( $reqopcode != '' )) {
    /*
    if ((filter_var($cfg_array['ldap_server'],FILTER_VALIDATE_DOMAIN)) &&
      (filter_var($cfg_array['ldap_serverport'],FILTER_VALIDATE_INT)) &&
      (($cfg_array['ldaps'] == 0) || ($cfg_array['ldaps'] == 1)) &&
      (filter_var($cfg_array['ldap_baseDN'] != ''))) {

      } else {
        echo "<b>Warning:</b> Authentication server configuration invalid<br>";
      }
    */
    $privkey = openssl_pkey_get_private(file_get_contents($private_key_file));
    $pubkey = openssl_pkey_get_public(file_get_contents($public_key_file));
    $timestamp = date_timestamp_get(date_create());
    $original = $system_seed.":".$reqopcode.":".$requsername.":".$timestamp;
    openssl_private_encrypt($original, $bintoken, $privkey);
    $enctoken = base64_encode($bintoken);
    echo "<table>
    <tr><td>TOKEN:".$enctoken."</td></tr>
    <tr><td>Original:".$original."</td></tr>
    </table>
    ";
  } else {
    echo "
    Authentication form goes here<br>
    ";
  }

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

if ( $reqservice == 'token_decryption' ) {
  // -------- CAUTION --------
  // temporary dummy value
  // $reqtoken = 'ah9NNTOg7VymLn1K4bkgepy8ocBXgWXsi1pCSywVuZZthvWaBqgDvIWezHZZ62tdPitaoK8J5BvIaRQl/z6ttg==';
  // -------- CAUTION --------

  if ( isset($reqtoken) && ( $reqtoken != null ) ) {
    $pubkey = openssl_pkey_get_public(file_get_contents($public_key_file));
    openssl_public_decrypt(base64_decode($reqtoken), $dectoken, $pubkey);
    echo "
    <table>
    <tr><td>Encrypted:".$reqtoken."</td></tr>
    <tr><td>Decrypted:".$dectoken."</td></tr>
    </table>
    ";
  } else {
    echo "
    <form action='".$base_url."' method='get'>
    <table>
    <tr><td><label>Enter token to decode:</label></td><td><input type='text' id='token' name='token' maxlength='512' size='90'></td></tr>
    <tr><td>&nbsp;</td><td><input type='submit' value='Submit'></td></tr>
    </table>
    <input type='hidden' id='service' name='service' value='token_decryption'>
    </form>
    ";
  }
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
  //
  //openssl_public_decrypt($dectoken, $final, $pubkey);
}

/**
  * Page footer
 */

echo "
</body>
</html>
";

?>
