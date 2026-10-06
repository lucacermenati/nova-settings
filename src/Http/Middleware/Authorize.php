<?php

namespace LucaCermenati\NovaSettings\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use LucaCermenati\NovaSettings\NovaSettings;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tool = collect(Nova::registeredTools())->first($this->matchesTool(...));

        abort_if(is_null($tool), 404);
        abort_unless($tool->authorize($request), 403);

        return $next($request);
    }

    public function matchesTool(Tool $tool): bool
    {
        return $tool instanceof NovaSettings;
    }
}
