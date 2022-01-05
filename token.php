<?php
/**
  * PHP authenticated token generator
  * Michele Albrigo - 2021
  * v0.1 - module import
  * v0.0 - initial structure
 */

/**
  * Defaults setting
 */
$yaml_cfg_docs=1;
$yaml_cfg_file='../token-cfg.yaml';
$private_key_file='../key-private.pem';
$public_key_file='../key-public.pem';
$system_seed='AAAAAAAAAA';
$ldaps=1;
$ldap_server_port='636'

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
$cfg_array=yaml_parse_file($yaml_cfg_file,0,$yaml_cfg_docs);

// if private_key_file and public_key_file both exist, overwrite defaults
if (($cfg_array['private_key'] != '') && ($cfg_array['public_key'] != '')) {
  $private_key_file = $cfg_array['private_key'];
  $public_key_file = $cfg_array['public_key'];
}

// if system_seed exist and length is 10, overwrite defaults
if (($cfg_array['system_seed'] != '') && 
    (strlen($cfg_array['system_seed']==10))) {
  $system_seed = $cfg_array['system_seed'];
}

// if ldap_server, ldap_serverport, ldaps, ldap_baseDN do not exist or aren't well formed, throw an exception

/**
  * Read request parameters
  * service: page function selection (default = synopsis)
  *     possible values: synopsis, keypair_generation, token_generation
  * opcode: an operation code provided by the user, for inclusion in the encrypted token
  * username: the user's name on the LDAP server
  * password: the user's password on the LDAP server
 */

// read http request parameters
// check if service is valid

/**
  * Page header
 */


/**
  * Service: synopsis
  * User input: none
  * System data: none
  * Description: general help about the page
 */

// print synopsis

/**
  * Service: keypair_generation
  * User input: none
  * System data: none
  * Description: utility function, to generate a key pair to be used for token creation and verification
  * Output: a valid key pair
 */

// generate key pair
// print key pair

/**
  * Service: token_generation
  * User input: username, password, opcode
  * System data: ldap_server, ldap_serverport, ldaps, ldap_baseDN, private_key, public_key, system_seed, timestamp
  * Description: authenticated token generation, including opcode and username, encrypted with the system's private key
  * Output: a valid token and a public key to validate it
 */

// if user and password are empty, print login page
// else if user and password are both present
//    check user and password validity and size
//    if ldap bind succeeds
$ldapconn=ldap_connect('ldaps://oig-ud-srv.univr.it',636);
$ldapbind=ldap_bind($ldapconn, 'uid=lbrmhl33,cn=CID-UTE-PER-GEN,dc=univr,dc=it', $ldappassword);
//      create token string
//      if opcode is empty pad string with zeroes
//      encrypt token string with private_key
//      display encrypted token
//      display public_key
//    if ldap bind fails
//      display error
// else
//      display error

/**
  * Service: token_decryption
  * User input: token
  * System data: public_key
  * Description: decrypts a token and displays its content
  * Output: username, opcode, system_seed, token creation timestamp, human readable token creation timestamp, token age
 */

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

/**
  * Page footer
 */

?>