<?php
/**
 *
 */
class ClassUserAuth extends ClassAbstractAuth
{
    static function authByCredentials(string $email, string $pass)
    {
        ClassF::sleepLong();

        // Validate input

        $mUsers = ModelUsers::inst();

        $email          = $mUsers->filterOne('email', $email, false);
        $pass           = Vars::filter($pass, Vars::STR, [Config::get('user.auth.pass.lenMin'), Config::get('user.auth.pass.lenMax')], null, null);
        $reqIp          = self::_getReqIp(false);
        $reqBrowserStr  = self::_getReqBrowserStr(false);

        if (!$email || !$pass) {
            Verbose::echo1("Bad email or password");
            throw new UserErr(_t('Wrong credentials'));
        }
        if (!$reqIp || !$reqBrowserStr) {
            Verbose::echo1("Bad ip or browser string");
            throw new UserErr(_t('Wrong credentials'));
        }

        // Init auth attempt

        $mUsersAuthsAttempts = ModelUsersAuthsAttempts::inst();

        $attemptHash = HashHmac::sha256($email . $reqIp . $reqBrowserStr, '__attemptHash__');
        $authAttemptId = $mUsersAuthsAttempts->insert($attemptHash, $email, $reqIp, $reqBrowserStr);

        // Check auth attempts

        $banLogName = Config::get('user.auth.limits.banLogName');
        $banHoldSec = Config::get('user.auth.limits.banHoldSec');
        if (($attemptsByHash = $mUsersAuthsAttempts->countActiveAttemptsByHash($attemptHash)) > Config::get('user.auth.limits.attemptsPerHash')) {
            Log::write($banLogName, ['by_hash', $attemptsByHash, $attemptHash, $email]);
            Verbose::echo1("Ban by hash");
            sleep($banHoldSec);
        }
        elseif (($attemptsByIp = $mUsersAuthsAttempts->countActiveAttemptsByIp($reqIp)) > Config::get('user.auth.limits.attemptsPerIp')) {
            Log::write($banLogName, ['by_ip', $attemptsByIp, $attemptHash, $email]);
            Verbose::echo1("Ban by ip");
            sleep($banHoldSec);
        }
        elseif (($attemptsTotal = $mUsersAuthsAttempts->countActiveAttemptsTotal()) > Config::get('user.auth.limits.attemptsTotal')) {
            Log::write($banLogName, ['total', $attemptsTotal, $attemptHash, $email]);
            Verbose::echo1("Ban total");
            Mailer::notify(
                "WARNING: User auth attempts [$attemptsTotal] exceeded [total] threshold",
                "See details in $banLogName.log and in MySQL " . $mUsersAuthsAttempts->table()
            );
            sleep($banHoldSec);
        }

        // Get by credentials

        $user = $mUsers->getActiveUserByCredentials($email, $pass);
        if (!$user) {
            Verbose::echo1("Wrong credentials or user is inactive");
            throw new UserErr(_t('Wrong credentials'));
        }
        if ($user['role'] == User::ROLE_USER_BOT) {
            throw new UserErr(_t('Wrong credentials'));
        }

        // Authenticate

        self::_authenticate($user['id'], $authAttemptId);

        // Deactivate auth attempts

        if ($attemptsByHash) {
            $mUsersAuthsAttempts->disableByHash($attemptHash);
        }

        // Email notify

        Mailer::sendHtml(
            _t('%s: Authentication in the system', Config::get('project.siteName')),
            _t('mailAuthenticationEvent', $reqIp, $reqBrowserStr),
            [$email]
        );
        Mailer::notify(
            "User logged in ($email)", "IP: $reqIp, Browser: $reqBrowserStr"
        );
    }

    // Authenticate by auth-session cookie
    static function authByCookie()
    {
        $activeAuth = self::_getActiveAuthByAuthCookie();
        if (!$activeAuth) {
            return;
        }

        $userId = $activeAuth['userId'];
        $user = ModelUsers::inst()->getActiveUserById($userId);
        if (!$user) {
            Verbose::echo1("User is inactive");
            self::unsetAuth($userId);
            return;
        }

        User::set($userId, $user['role'], $user);

        Verbose::echo1("Time left before auth session expire: " . (strtotime($activeAuth['validTill']) - time()));

        // Re-issue auth session if need

        $reissue = Config::get('user.auth.session.reissueAfterMinutes') * 60;
        $createdTs = strtotime($activeAuth['created']);
        $timeLeftBeforeReissue = ($createdTs + $reissue) - time();

        Verbose::echo1("Time left before auth session re-issue: $timeLeftBeforeReissue");

        if ($timeLeftBeforeReissue > 0) {
            return;
        }

        Verbose::echo1("Reissuing auth session");

        $user = ModelUsers::inst()->getActiveUserById($userId);
        if (!$user) {
            Verbose::echo1("User is inactive");
            self::unsetAuth($userId);
            return;
        }

        self::_authenticate($userId, $activeAuth['authAttemptId']);
    }

    static function unsetAuth(int $userId)
    {
        ClassF::sleepShort();

        ModelUsersAuths::inst()->disable($userId);
        Cookie::delete(Config::get('user.auth.session.cookie.name'));
    }

    private static function _authenticate(int $userId, int $authAttemptId)
    {
        $authKey = self::_makeAuthKey($userId);
        ModelUsersAuths::inst()->insert($authKey, $userId, $authAttemptId);
        self::_setAuthCookie($authKey);
    }

    private static function _makeAuthKey(int $userId): string
    {
        $randHash   = Rand::bytes(HashHmac::SHA256_HASH_RAW_LEN);
        $deviceHash = self::_makeDeviceHash();
        $dataHash   = HashHmac::sha256('user' . $userId, Config::get('user.auth.session.hashKey'), true);

        return Base64::encode($randHash . $deviceHash . $dataHash);
    }

    private static function _makeDeviceHash(): string
    {
        return HashHmac::sha256(
            self::_getReqIp() . self::_getReqBrowserStr(), Config::get('user.auth.session.hashKey'), true
        );
    }

    private static function _setAuthCookie(string $authKey)
    {
        $authKeyEnc = CryptSym::encrypt(
            $authKey,
            Config::get('user.auth.session.cookie.cipherKey'),
            Config::get('user.auth.session.cookie.hmacKey'),
            Config::get('user.auth.session.expireMinutes') * 60
        );
        Cookie::set(
            Config::get('user.auth.session.cookie.name'),
            $authKeyEnc,
            Config::get('user.auth.session.expireMinutes') * 60,
            Config::get('user.host'),
            $httpsOnly = null, // Using default value
            $denyJs = true
        );
    }

    private static function _getActiveAuthByAuthCookie(): array
    {
        $authKeyEnc = Cookie::get(Config::get('user.auth.session.cookie.name'));
        if (!$authKeyEnc) {
            Verbose::echo1("Empty auth cookie");
            return [];
        }

        try {
            ClassF::sleepShort();

            $authKey = CryptSym::decrypt(
                $authKeyEnc,
                Config::get('user.auth.session.cookie.cipherKey'),
                Config::get('user.auth.session.cookie.hmacKey')
            );
            $authKeyRaw = Base64::decode($authKey);

            $deviceHash = substr($authKeyRaw, HashHmac::SHA256_HASH_RAW_LEN, HashHmac::SHA256_HASH_RAW_LEN);
            if (strlen($deviceHash) != HashHmac::SHA256_HASH_RAW_LEN) {
                throw new Err("Bad device hash");
            }

            $dataHash = substr($authKeyRaw, HashHmac::SHA256_HASH_RAW_LEN * 2, HashHmac::SHA256_HASH_RAW_LEN);
            if (strlen($dataHash) != HashHmac::SHA256_HASH_RAW_LEN) {
                throw new Err("Bad data hash");
            }

            $deviceHashCalc = self::_makeDeviceHash();
            if (!hash_equals($deviceHash, $deviceHashCalc)) {
                throw new Err("Device hashes are not equal");
            }

            $activeAuth = ModelUsersAuths::inst()->getActiveAuthByKey($authKey);
            if (!$activeAuth || !$activeAuth['userId']) {
                Cookie::delete(Config::get('user.auth.session.cookie.name'));
                throw new Err("Active auth row not found");
            }

            $dataHashCalc = HashHmac::sha256('user' . $activeAuth['userId'], Config::get('user.auth.session.hashKey'), true);
            if (!hash_equals($dataHash, $dataHashCalc)) {
                throw new Err("Data hashes are not equal");
            }

            return $activeAuth;
        }
        catch (Exception $e) {
            Verbose::echo1($e->getMessage());
            Cookie::delete(Config::get('user.auth.session.cookie.name'));
        }

        return [];
    }

    private static function _getReqIp(bool $exception = true): string
    {
        return ModelUsersAuthsAttempts::inst()->filterOne('ip', Request::ip(), $exception);
    }

    private static function _getReqBrowserStr(bool $exception = true): string
    {
        return ModelUsersAuthsAttempts::inst()->filterOne('browserStr', Request::browserStr(), $exception);
    }
}