<?php
/**
 *
 */
class ClassAdminAuth extends ClassAbstractAuth
{
    static function authByCredentials(string $email, string $pass)
    {
        ClassF::sleepLong();

        // Validate input

        $mAdmins = ModelAdmins::inst();

        $email          = $mAdmins->filterOne('email', $email, false);
        $pass           = Vars::filter($pass, Vars::STR, [Config::get('admin.auth.pass.lenMin'), Config::get('admin.auth.pass.lenMax')], null, null);
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

        $mAdminsAuthsAttempts = ModelAdminsAuthsAttempts::inst();

        $attemptHash = HashHmac::sha256($email . $reqIp . $reqBrowserStr, '__attemptHash__');
        $authAttemptId = $mAdminsAuthsAttempts->insert($attemptHash, $email, $reqIp, $reqBrowserStr);

        // Check auth attempts

        $banLogName = Config::get('admin.auth.limits.banLogName');
        $banHoldSec = Config::get('admin.auth.limits.banHoldSec');
        if (($attemptsByHash = $mAdminsAuthsAttempts->countActiveAttemptsByHash($attemptHash)) > Config::get('admin.auth.limits.attemptsPerHash')) {
            Log::write($banLogName, ['by_hash', $attemptsByHash, $attemptHash, $email]);
            Verbose::echo1("Ban by hash");
            sleep($banHoldSec);
        }
        elseif (($attemptsByIp = $mAdminsAuthsAttempts->countActiveAttemptsByIp($reqIp)) > Config::get('admin.auth.limits.attemptsPerIp')) {
            Log::write($banLogName, ['by_ip', $attemptsByIp, $attemptHash, $email]);
            Verbose::echo1("Ban by ip");
            sleep($banHoldSec);
        }
        elseif (($attemptsTotal = $mAdminsAuthsAttempts->countActiveAttemptsTotal()) > Config::get('admin.auth.limits.attemptsTotal')) {
            Log::write($banLogName, ['total', $attemptsTotal, $attemptHash, $email]);
            Verbose::echo1("Ban total");
            Mailer::notify(
                "WARNING: Admin auth attempts [$attemptsTotal] exceeded [total] threshold",
                "See details in $banLogName.log and in MySQL " . $mAdminsAuthsAttempts->table()
            );
            sleep($banHoldSec);
        }

        // Get by credentials

        $admin = $mAdmins->getActiveAdminByCredentials($email, $pass);
        if (!$admin) {
            Verbose::echo1("Wrong credentials or admin is inactive");
            throw new UserErr(_t('Wrong credentials'));
        }
        if ($admin['role'] == User::ROLE_ADMIN_BOT) {
            throw new UserErr(_t('Wrong credentials'));
        }

        // Authenticate

        self::_authenticate($admin['id'], $authAttemptId);

        // Deactivate auth attempts

        if ($attemptsByHash) {
            $mAdminsAuthsAttempts->disableByHash($attemptHash);
        }

        // Email notify

        Mailer::sendHtml(
            _t('%s: Authentication in the system', Config::get('project.siteName')),
            _t('mailAuthenticationEvent', $reqIp, $reqBrowserStr),
            [$email]
        );
        Mailer::notify(
            "Admin logged in ($email)", "IP: $reqIp, Browser: $reqBrowserStr"
        );
    }

    // Authenticate by auth-session cookie
    static function authByCookie()
    {
        $activeAuth = self::_getActiveAuthByAuthCookie();
        if (!$activeAuth) {
            return;
        }

        $adminId = $activeAuth['adminId'];
        $admin = ModelAdmins::inst()->getActiveAdminById($adminId);
        if (!$admin) {
            Verbose::echo1("Admin is inactive");
            self::unsetAuth($adminId);
            return;
        }

        User::set($adminId, $admin['role'], $admin);

        Verbose::echo1("Time left before auth session expire: " . (strtotime($activeAuth['validTill']) - time()));

        // Re-issue auth session if need

        $reissue = Config::get('admin.auth.session.reissueAfterMinutes') * 60;
        $createdTs = strtotime($activeAuth['created']);
        $timeLeftBeforeReissue = ($createdTs + $reissue) - time();

        Verbose::echo1("Time left before auth session re-issue: $timeLeftBeforeReissue");

        if ($timeLeftBeforeReissue > 0) {
            return;
        }

        Verbose::echo1("Reissuing auth session");

        $admin = ModelAdmins::inst()->getActiveAdminById($adminId);
        if (!$admin) {
            Verbose::echo1("Admin is inactive");
            self::unsetAuth($adminId);
            return;
        }

        self::_authenticate($adminId, $activeAuth['authAttemptId']);
    }

    static function unsetAuth(int $adminId)
    {
        ClassF::sleepShort();

        ModelAdminsAuths::inst()->disable($adminId);
        Cookie::delete(Config::get('admin.auth.session.cookie.name'));
    }

    private static function _authenticate(int $adminId, int $authAttemptId)
    {
        $authKey = self::_makeAuthKey($adminId);
        ModelAdminsAuths::inst()->insert($authKey, $adminId, $authAttemptId);
        self::_setAuthCookie($authKey);
    }

    private static function _makeAuthKey(int $adminId): string
    {
        $randHash   = Rand::bytes(HashHmac::SHA256_HASH_RAW_LEN);
        $deviceHash = self::_makeDeviceHash();
        $dataHash   = HashHmac::sha256('admin' . $adminId, Config::get('admin.auth.session.hashKey'), true);

        return Base64::encode($randHash . $deviceHash . $dataHash);
    }

    private static function _makeDeviceHash(): string
    {
        return HashHmac::sha256(
            self::_getReqIp() . self::_getReqBrowserStr(), Config::get('admin.auth.session.hashKey'), true
        );
    }

    private static function _setAuthCookie(string $authKey)
    {
        $authKeyEnc = CryptSym::encrypt(
            $authKey,
            Config::get('admin.auth.session.cookie.cipherKey'),
            Config::get('admin.auth.session.cookie.hmacKey'),
            Config::get('admin.auth.session.expireMinutes') * 60
        );
        Cookie::set(
            Config::get('admin.auth.session.cookie.name'),
            $authKeyEnc,
            Config::get('admin.auth.session.expireMinutes') * 60,
            Config::get('admin.host'),
            $httpsOnly = null, // Using default value
            $denyJs = true
        );
    }

    private static function _getActiveAuthByAuthCookie(): array
    {
        $authKeyEnc = Cookie::get(Config::get('admin.auth.session.cookie.name'));
        if (!$authKeyEnc) {
            Verbose::echo1("Empty auth cookie");
            return [];
        }

        try {
            ClassF::sleepShort();

            $authKey = CryptSym::decrypt(
                $authKeyEnc,
                Config::get('admin.auth.session.cookie.cipherKey'),
                Config::get('admin.auth.session.cookie.hmacKey')
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

            $activeAuth = ModelAdminsAuths::inst()->getActiveAuthByKey($authKey);
            if (!$activeAuth || !$activeAuth['adminId']) {
                Cookie::delete(Config::get('admin.auth.session.cookie.name'));
                throw new Err("Active auth row not found");
            }

            $dataHashCalc = HashHmac::sha256('admin' . $activeAuth['adminId'], Config::get('admin.auth.session.hashKey'), true);
            if (!hash_equals($dataHash, $dataHashCalc)) {
                throw new Err("Data hashes are not equal");
            }

            return $activeAuth;
        }
        catch (Exception $e) {
            Verbose::echo1($e->getMessage());
            Cookie::delete(Config::get('admin.auth.session.cookie.name'));
        }

        return [];
    }

    private static function _getReqIp(bool $exception = true): string
    {
        return ModelAdminsAuthsAttempts::inst()->filterOne('ip', Request::ip(), $exception);
    }

    private static function _getReqBrowserStr(bool $exception = true): string
    {
        return ModelAdminsAuthsAttempts::inst()->filterOne('browserStr', Request::browserStr(), $exception);
    }
}