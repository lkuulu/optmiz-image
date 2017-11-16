<?php

namespace STHCommon\Hooks;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsHook
{
    public function __invoke(Request $request, Response $response)
    {
        $responseHeaders = $response->headers;
        $responseHeaders->set('Access-Control-Allow-Headers', 'accept');
        $responseHeaders->set('Access-Control-Allow-Methods', 'GET');
    }
}
