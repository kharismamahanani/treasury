<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * '*' = percayai semua proxy (Nginx reverse proxy, load balancer, Cloudflare, dll.)
     * Wajib di-set agar Laravel bisa baca header X-Forwarded-Proto: https
     * yang dikirim Nginx, sehingga URL::forceScheme dan redirect HTTPS bekerja benar.
     */
    protected $proxies = '*';

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR    |
        Request::HEADER_X_FORWARDED_HOST   |
        Request::HEADER_X_FORWARDED_PORT   |
        Request::HEADER_X_FORWARDED_PROTO  |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
