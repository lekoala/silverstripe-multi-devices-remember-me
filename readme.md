SilverStripe Multi devices remember me module
==================

Backports RememberLoginHash from SS4 to SS3 to allow multi devices remember me token

Code changes
==================

You need to manually edit the following lines in Member.php

Comment the part that sets alc_enc cookies in the logIn function, it will be set by RememberMeExtension::setDeviceCookie

```php
public function logIn($remember = false) {
		...

		// Only set the cookie if autologin is enabled
        RememberMeExtension::setDeviceCookie($this, $remember);
		// if($remember && Security::config()->autologin_enabled) {
		// 	// Store the hash and give the client the cookie with the token.
		// 	$generator = new RandomGenerator();
		// 	$token = $generator->randomToken('sha1');
		// 	$hash = $this->encryptWithUserSettings($token);
		// 	$this->RememberLoginToken = $hash;
		// 	Cookie::set('alc_enc', $this->ID . ':' . $token, 90, null, null, null, true);
		// } else {
		// 	$this->RememberLoginToken = null;
		// 	Cookie::force_expiry('alc_enc');
		// }

        ...
```

Edit the currentUserID function like this

```php
public static function currentUserID() {
    $id = Session::get("loggedInAs");
    if(!$id && !self::$_already_tried_to_auto_log_in) {
        RememberMeExtension::autoLoginFromDevice();
        $id = Session::get("loggedInAs");
    }

    return is_numeric($id) ? (int) $id : 0;
}
```

Compatibility
==================
Tested with 3.7+

Maintainer
==================
LeKoala - thomas@lekoala.be
