<?php
// Security headers for every page (#50). includes/auth.php sends them, and every page loads auth.php first.
//
// Content-Security-Policy tells the browser what a page may load and run:
//  - scripts only from our own site (assets/js/app.js), so a script someone manages to put
//    into a title or a comment cannot run, even if escaping were ever missed
//  - style sheets from our site and Google Fonts, the font files from Google's font server
//  - style="width: 40%" attributes are allowed: the progress bars use them
//  - forms may only send data to our own site
//  - no other site may show Teamsync inside a frame (clickjacking)

function security_headers(): array
{
    $policy = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' https://fonts.googleapis.com",
        "style-src-attr 'unsafe-inline'",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "object-src 'none'",
    ];

    return [
        'Content-Security-Policy' => implode('; ', $policy),
        'X-Frame-Options'         => 'DENY',      // the same frame rule for older browsers
        'X-Content-Type-Options'  => 'nosniff',   // a file is only used as the type the server says
        'Referrer-Policy'         => 'same-origin',   // links to other sites never get our page address
        'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
    ];
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    foreach (security_headers() as $name => $value) {
        header($name . ': ' . $value);
    }
}
