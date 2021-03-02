<?php

class RememberMeExtension extends DataExtension
{
    private static $has_many = array(
        'RememberLoginHashes' => 'RememberLoginHash'
    );
    private static $_already_tried_to_auto_log_in = false;

    /**
     * You also need to comment lines 543>553 in Member.php
     *
     * @return void
     */
    public static function setDeviceCookie(Member $member, $remember)
    {
        // Cleans up any potential previous hash for this member on this device
        if ($alcDevice = Cookie::get('alc_device')) {
            RememberLoginHash::get()->filter('DeviceID', $alcDevice)->removeAll();
        }

        if ($remember && Security::config()->autologin_enabled) {
            $rememberLoginHash = RememberLoginHash::generate($member);
            $tokenExpiryDays = Config::inst()->get('RememberLoginHash', 'token_expiry_days');
            $deviceExpiryDays = Config::inst()->get('RememberLoginHash', 'device_expiry_days');
            Cookie::set(
                'alc_enc',
                $member->ID . ':' . $rememberLoginHash->getToken(),
                $tokenExpiryDays,
                null,
                null,
                null,
                true
            );
            Cookie::set('alc_device', $rememberLoginHash->DeviceID, $deviceExpiryDays, null, null, null, true);
        } else {
            Cookie::set('alc_enc', null);
            Cookie::set('alc_device', null);
            Cookie::force_expiry('alc_enc_device');
            Cookie::force_expiry('alc_device');
        }
    }
    
    /**
     * Ensure user is properly logged out
     *
     * @return void
     */
    public function memberLoggedOut()
    {
        Cookie::force_expiry('alc_enc_device');
        Cookie::force_expiry('alc_device');
        RememberLoginHash::clear($this->owner);
    }
    
    /**
     * Log the user in if the "remember login" cookie is set
     *
     * The <i>remember login token</i> will be changed on every successful
     * auto-login.
     *
     * You need to replace call to self::autoLogin in Member:893
     */
    public static function autoLoginFromDevice()
    {
        // Don't bother trying this multiple times
        if (self::$_already_tried_to_auto_log_in) {
            return;
        }
        self::$_already_tried_to_auto_log_in = true;

        // Return early
        if (strpos(Cookie::get('alc_enc'), ':') === false) {
            return;
        }
        if (!Cookie::get("alc_device")) {
            return;
        }
        if (Session::get("loggedInAs")) {
            return;
        }
        if (!Security::database_is_ready()) {
            return;
        }

        list($uid, $token) = explode(':', Cookie::get('alc_enc'), 2);
        $deviceID = Cookie::get('alc_device');

        $member = Member::get()->byId($uid);

        $rememberLoginHash = null;

        // check if autologin token matches
        if ($member) {
            $hash = $member->encryptWithUserSettings($token);
            $rememberLoginHash = RememberLoginHash::get()
                ->filter(array(
                    'MemberID' => $member->ID,
                    'DeviceID' => $deviceID,
                    'Hash' => $hash
                ))->First();
            if (!$rememberLoginHash) {
                $member = null;
            } else {
                // Check for expired token
                $expiryDate = new DateTime($rememberLoginHash->ExpiryDate);
                $now = SS_Datetime::now();
                $now = new DateTime($now->Rfc2822());
                if ($now > $expiryDate) {
                    $member = null;
                }
            }
        }

        if ($member) {
            Member::session_regenerate_id();
            Session::set("loggedInAs", $member->ID);

            // This lets apache rules detect whether the user has logged in
            if (Member::config()->login_marker_cookie) {
                Cookie::set(Member::config()->login_marker_cookie, 1, 0, null, null, false, true);
            }

            if ($rememberLoginHash) {
                $rememberLoginHash->renew();
                $tokenExpiryDays = Config::inst()->get('RememberLoginHash', 'token_expiry_days');
                Cookie::set(
                    'alc_enc',
                    $member->ID . ':' . $rememberLoginHash->getToken(),
                    $tokenExpiryDays,
                    null,
                    null,
                    false,
                    true
                );
            }

            $member->write();

            // Audit logging hook
            $member->extend('memberAutoLoggedIn');
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName("RememberLoginHashes");
    }
}
