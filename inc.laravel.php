<?php
/* ###VERSIONSBLOCKINLCUDE### */
require_once(__DIR__.'/ebiz-kernel/bootstrap/autoload.php'); // Laravel autoloader

/** @var \Illuminate\Foundation\Application $app */
$app = require_once(__DIR__.'/ebiz-kernel/bootstrap/app.php'); // Laravel app init
/** @var \Illuminate\Http\Request $request */
$request = Illuminate\Http\Request::capture();
$request->setLocale($GLOBALS["s_lang"]);
$app->instance("request", $request);
/** @var \Illuminate\Foundation\Http\Kernel $kernel */
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$app->setLocale($GLOBALS["s_lang"]);
$urlBase = $GLOBALS["nar_systemsettings"]["SITE"]["SITEURL"]/*.$GLOBALS["nar_systemsettings"]["SITE"]["BASE_URL"]*/;
\Illuminate\Support\Facades\URL::forceRootUrl($urlBase);
\Illuminate\Support\Facades\URL::defaults(['locale' => $GLOBALS["s_lang"]]);
#\Illuminate\Support\Facades\Auth::setUser( \App\User::find(1) );

$request = \Illuminate\Http\Request::capture();
$request->setMethod("GET");
/** @var \App\Http\Middleware\EncryptCookies $encryptCookies */
$encryptCookies = app()->make(\App\Http\Middleware\EncryptCookies::class);
/** @var \Illuminate\Http\Response $response */
$response = $encryptCookies->handle($request, function($request) {
    /** @var \Illuminate\Session\Middleware\StartSession $startSession */
    $startSession = app()->make(\Illuminate\Session\Middleware\StartSession::class);
    return $startSession->handle($request, function($request) {
        /** @var \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken $verifyCsrfSession */
        $verifyCsrfSession = app()->make(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);     
        return $verifyCsrfSession->handle($request, function($request) {
            return new \Illuminate\Http\Response();
        });
    });
});

#error_reporting(E_ERROR | E_WARNING | E_PARSE ^ E_NOTICE);
set_error_handler(null);
set_exception_handler(null);
#dd($app);
#dd( App::getLocale() );

$GLOBALS["laravel"] = [
    "app" => $app,
    "kernel" => $kernel
];

?>