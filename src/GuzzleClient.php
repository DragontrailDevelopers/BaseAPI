<?php
namespace DragontrailDevelopers\BaseAPI;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleClient extends Client
{
    public function __construct(array $config = [])
    {
        if (!isset($config['handler'])) {
            $config['handler'] = HandlerStack::create();
            $config['handler']->push(self::reportMiddleware(), 'dt-api-report');
        } elseif (!is_callable($config['handler'])) {
            throw new \InvalidArgumentException('handler must be a callable');
        }

        parent::__construct($config);
    }

    public static function reportMiddleware()
    {
        return function (callable $handler){
            return function (RequestInterface $request, array $options) use ($handler) {
                $start = microtime(true);
                $stack = $handler($request, $options);
                $stack->then(function(ResponseInterface $response)use($start, $request){
                    $end = microtime(true);
                    self::report(
                        $request->getMethod(),
                        (string)$request->getUri(),
                        ($end - $start) * 1000,
                        $response->getStatusCode()
                    );
                });
                return $stack;
            };
        };
    }

    public static function report($method, $uri, $request_time, $status)
    {
        $reportUrl = 'http://api.dragontrail.test/api/report';
        $initiator = 'test';
        $time_iso = date('c');

        try{
            (new Client())->post($reportUrl, [
                'form_params' => compact('initiator', 'method', 'uri', 'time_iso', 'request_time', 'status'),
            ]);
        }catch (\Throwable $e){}
    }
}