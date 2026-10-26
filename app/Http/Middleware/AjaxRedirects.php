<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms marked data-ajax send themselves in the background (public/js/form-ajax.js) so a mistake is shown in place,
 * without loading the whole page again. A background request that would have been redirected gets the address back
 * as JSON instead, and the script opens it. Anything the controller put in the session (a toast, a modal) is still
 * there for the next page.
 */
class AjaxRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->get('X-Bayline-Ajax') === '1' && $response instanceof RedirectResponse) {
            return response()->json(['redirect' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
