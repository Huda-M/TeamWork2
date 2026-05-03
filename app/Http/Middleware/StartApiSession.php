<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

class StartApiSession
{
    protected $startSession;

    public function __construct(StartSession $startSession)
    {
        $this->startSession = $startSession;
    }

    public function handle(Request $request, Closure $next)
    {
        // تطبيق middleware الجلسة الأصلي
        $this->startSession->handle($request, function ($req) use ($next) {
            return $next($req);
        });

        return $next($request);
    }
}
