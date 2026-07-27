<?php

/**
 * Serves viewer.html while explicitly allowing it to be displayed in an iframe.
 *
 * The web server may set a global `X-Frame-Options: SAMEORIGIN`, which would break the
 * viewer as soon as the wiki is itself embedded in a third-party site: browsers evaluate
 * the whole ancestor chain, not just the direct parent. `frame-ancestors` takes
 * precedence over `X-Frame-Options`, so emitting it is enough.
 *
 * This file must stay next to viewer.html: the latter's paths are relative to the document.
 */
define('VIEWER_PATH', __DIR__ . '/viewer.html');

if (file_exists(VIEWER_PATH)) {
    // allow local ('self') and everyone (*)
    header("Content-Security-Policy: frame-ancestors 'self' *;");
    header('Content-Type: text/html');
    readfile(VIEWER_PATH);
} else {
    header('HTTP/1.0 404 Not found');
    header('Content-Type: text/html');
    echo <<<HTML
    <!DOCTYPE html>
    <html>
        <head></head>
        <body>
            <h1>Error 404 Not found</h1>
        </body>
    </html>
    HTML;
    exit();
}
