<?php
/**
 * NOTE: Onchange sync with classes/ClassCliScript.php
 */

// Init

error_reporting(E_ALL);

require_once dirname(__FILE__) . '/autoload.php';

set_error_handler(function(int $code, string $message, string $file, int $line, array $context) {
    throw new Err("$message at $file:$line", (new ErrCode($code)));
}, E_ALL);

define('DIR_ROOT', dirname(__FILE__));
if (!DIR_ROOT) {
    throw new Err("Failed to set DIR_ROOT");
}

try
{
    Config::init([
        [DIR_ROOT . '/configs/config.php', true],
        [DIR_ROOT . '/configs/configKeys.local.php', true],
        [DIR_ROOT . '/configs/config.local.php', false],
    ]);
    ini_set('display_errors', Config::get('error.display'));
    ini_set('log_errors', Config::get('error.log'));
    ini_set('error_log', Config::get('error.logFile'));

    $dirs = [
        Config::get('log.dir'),
        Config::get('cache.dir'),
        Config::get('cache.dirPermanent'),
        Config::get('tmp.dir'),
        Config::get('cli.lockFileDir'),
        Config::get('translate.cacheDir'),
    ];
    foreach (array_unique($dirs) as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new Err("Failed to make dir [$dir]");
        }
        if (!is_writable($dir)) {
            throw new Err("Not a writable dir [$dir]");
        }
    }

    ErrHandler::init(
        Config::get('error.display'), Config::get('error.log'), Config::get('error.logFile')
    );
    Log::init(
        Config::get('log.dir')
    );
    Verbose::init(
        Config::get('verbose.level'), Config::get('verbose.display'), Config::get('verbose.log')
    );
    Request::init(
    );
    Cache::init(
        Config::get('cache.dir'), Config::get('cache.enabled')
    );
    View::init(
        Config::get('view.baseDir')
    );
    Mailer::init(
        Config::get('email.baseDir'), Config::get('email.sender.email'), Config::get('email.sender.name'),
        Config::get('email.recipients.sys'), Config::get('email.enabled')
    );
    CryptSym::init(
        Config::get('crypt.sym.cipherAlg')
    );
    CryptAsym::init(
        Config::get('crypt.asym')
    );

    // Check params

    if (array_sum(Config::get('mode')) != 1) {
        throw new Err("Bad mode: At least one and only one mode can be selected");
    }
    if (Config::get('mode.prod')) {
        if (ErrHandler::isDisplay()) {
            throw new Err("Prod mode error: Displaying of errors is not allowed");
        }
        if (Verbose::isDisplay()) {
            throw new Err("Prod mode error: Displaying of verbose info is not allowed");
        }
        if (!Config::get('user.cookie.denyJsAccess') || !Config::get('admin.cookie.denyJsAccess') || !Config::get('admin.session.cookie.denyJsAccess')) {
            throw new Err("Prod mode error: Access to cookies must be denied for JS");
        }
        if (!Config::get('user.mysql.pass') || !Config::get('admin.mysql.pass')) {
            throw new Err("Prod mode error: Mysql password must be not empty");
        }
        if (!Config::get('admin.host')) {
            throw new Err("Prod mode error: Empty admin host");
        }
    }

    // Run

    if (!ob_start(null, 1024 * 256)) {
        throw new Err("Failed to start output buffering");
    }

    Request::collectInfo();

    if (!Request::isHttps()) {
        throw new Err("Prod mode error: Request proto must be https");
    }

    // User section

    $userHosts = [];
    foreach (Config::get('user.hosts') as $host) {
        $userHosts[] = $host['host'];
    };
    if (!$userHosts) {
        throw new Err("No user hosts");
    }

    if (in_array(Request::host(), $userHosts, true)) {
        ini_set('memory_limit', Config::get('user.sys.memoryLimit'));
        ini_set('max_execution_time', Config::get('user.sys.timeLimitSec'));

        Model::init(
            Config::get('mysql.host'), Config::get('user.mysql.user'), Config::get('user.mysql.pass'), Config::get('mysql.database')
        );

        $hostToLangMap = [];
        foreach (Config::get('user.hosts') as $host) {
            $hostToLangMap[F::getDomainFromUrl($host['baseUrl'])] = $host['lang'];
        }
        Request::setLanguage($hostToLangMap);

        Language::init(
            Request::lang(), Config::get('translate.sourceDir'), Config::get('translate.cacheDir')
        );
        Language::makeTranslateCache(null, Config::get('translate.resetCache'));

        $baseUrl = '';
        foreach (Config::get('user.hosts') as $host) {
            if ($host['host'] == Request::host()) {
                $baseUrl = $host['baseUrl'];
                break;
            }
        }
        if (!$baseUrl) {
            throw new Err("Failed to set base url for host [%s]", Request::host());
        }
        Config::set('user.baseUrl', $baseUrl);
        Config::set('user.host', Request::host());

        Cookie::init(
            Config::get('user.cookie.expireSec'), Config::get('user.cookie.domain'), Config::get('user.cookie.httpsOnly'),
            Config::get('user.cookie.denyJsAccess')
        );
        HtmlSysHeader::init(
            Config::get('user.view.css.baseUrl'), Config::get('user.view.css.version'),
            Config::get('user.view.js.baseUrl'), Config::get('user.view.js.version')
        );

        Verbose::echo1("Section: user");
        Verbose::echo1("Language: " . Request::lang());

        ClassUserAuth::authByCookie();

        (new ControllerFront(
            Config::get('user.urlRoutes'),
            Request::url(),
            Request::queryStr(),
            Config::get('user.baseUrl'),
            'user/skeleton',
            'user/sysHeader',
            'user/header',
            'user/footer'
        ))->run();
    }

    // Admin section

    elseif (Request::host() === Config::get('admin.host')) {
        ini_set('memory_limit', Config::get('admin.sys.memoryLimit'));
        ini_set('max_execution_time', Config::get('admin.sys.timeLimitSec'));

        Model::init(
            Config::get('mysql.host'), Config::get('admin.mysql.user'), Config::get('admin.mysql.pass'), Config::get('mysql.database')
        );
        Request::setLanguage(
            [Config::get('admin.host') => Config::get('admin.lang')]
        );
        Language::init(
            Request::lang(), Config::get('translate.sourceDir'), Config::get('translate.cacheDir')
        );
        Language::makeTranslateCache(null, Config::get('translate.resetCache'));

        Cookie::init(
            Config::get('admin.cookie.expireSec'), Config::get('admin.cookie.domain'), Config::get('admin.cookie.httpsOnly'),
            Config::get('admin.cookie.denyJsAccess')
        );
        Session::init(
            Config::get('admin.session.cookie.name'), Config::get('admin.session.cookie.domain'), Config::get('admin.session.cookie.httpsOnly'),
            Config::get('admin.session.cookie.denyJsAccess')
        );

        Verbose::echo1("Section: admin");
        Verbose::echo1("Language: " . Request::lang());

        ClassAdminAuth::authByCookie();

        (new ControllerFront(
            Config::get('admin.urlRoutes'),
            Request::url(),
            Request::queryStr(),
            Config::get('admin.baseUrl'),
            'admin/skeleton',
            'admin/sysHeader',
            'admin/header',
            'admin/footer'
        ))->run();
    }
    else {
        throw new Err("Unknown host [%s]", Request::host());
    }
}
catch (Exception $e)
{
    ErrHandler::handle($e);
    exit(1);
}