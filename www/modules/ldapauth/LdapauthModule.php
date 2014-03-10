<?php

namespace GO\Ldapauth;

use GO;

class LdapauthModule extends \GO\Base\Module{
	
	public static function initListeners() {		
		\GO::session()->addListener('beforelogin', 'GO\Ldapauth\LdapauthModule', 'beforeLogin');
	}
	
	
	public static function beforeLogin($username, $password){
		

		if(empty(GO::config()->ldap_peopledn))
			return true;
		GO::debug("LDAPAUTH: Active");

		try{
			$lh = new Authenticator();
			return $lh->authenticate($username, $password);
		} catch(Exception $e) { //When LDAP binding fail continue with GroupOffice Login
			return isset(GO::config()->ldap_login_on_exception) ? GO::config()->ldap_login_on_exception : true; 
		}

	}
	
	/**
	 * Save the Person attributes from LDAP with the given username
	 */
	public static function submitSettings(&$settingsController, &$params, &$response, $user) {
		//save what is loaded
		try{
			$person = \GO\Ldapauth\Model\Person::findByUsername($user->username);
			
			if(!$person){
				return true;
			}
			
			$person->setAttributes($params);

			$response['success'] = $response['success'] && $person->save();	
			if(!empty($_POST["current_password"]) || !empty($_POST["password"]) )
				$response['success'] = $response['success'] && $person->changePassword($_POST["current_password"],$_POST["password"]);
			$response['feedback'] = 'Save failed: LDAP '. $person->getError();
		} catch(Exception $e) {
			$response['success'] = false;
			$response['feedback'] = 'Exception duration LDAP save';
		}
	}

	/**
	 * Load the Person attributes from LDAP with the given username
	 */
	public static function loadSettings(&$settingsController, &$params, &$response, $user){	
		try{
			$person = \GO\Ldapauth\Model\Person::findByUsername($user->username);
			if($person) {
				$response['data']=array_merge($response['data'], $person->getAttributes());
				$response['data']['ldap_fields']=$person->getExtraVars();
			}
		} catch (Exception $e) {
			//LDAP record not available
		}
	}
	
	
	public static function getPeopleDn($username=null){
		
		if(empty(GO::config()->ldap_peopledn)){
			return "";
		}
		
		$hasVDomain = strpos(GO::config()->ldap_peopledn, '{VDOMAIN}');
		
		if($hasVDomain && !isset($username)){
			throw new Exception("You can't use this function with a {VDOMAIN} configured.");
		}
		
		if(isset($username) && $hasVDomain){
			
			$parts = explode('@', $username);
			
			if(!isset($parts[1])){
				throw new Exception("You can only use {VDOMAIN} when you login with an e-mail address");
			}
			
			return str_replace('{VDOMAIN}', $parts[1],GO::config()->ldap_peopledn);
		}  else {
			return GO::config()->ldap_peopledn;
		}
		
		
	}
	
}