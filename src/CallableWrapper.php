<?php

declare(strict_types=1);

namespace Yiisoft\RequestModel;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Yiisoft\Injector\Injector;

final class CallableWrapper implements MiddlewareInterface
{
    private ContainerInterface $container;
    private RequestModelFactory $factory;

    /**
     * @var callable
     */
    private $callback;

    public function __construct(ContainerInterface $container, RequestModelFactory $factory, callable $callback)
    {
        $this->container = $container;
        $this->factory = $factory;
        $this->callback = $callback;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = array_merge(
            [$request, $handler],
            $this->factory->createInstances($request, $this->getHandlerParams())
        );
        $response = (new Injector($this->container))->invoke($this->callback, $params);
        return $response instanceof MiddlewareInterface ? $response->process($request, $handler) : $response;
    }

    private function getHandlerParams(): array
    {
        return $this
            ->getReflector()
            ->getParameters();
    }

    /**
     * @throws \ReflectionException
     *
     * @return ReflectionFunction|ReflectionFunctionAbstract|ReflectionMethod
     */
    private function getReflector(): ReflectionFunctionAbstract
    {
        if (is_object($this->callback)) {
            $this->callback = [$this->callback, '__invoke'];
        }

        if (is_array($this->callback)) {
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        }

        return new ReflectionFunction($this->callback);
    }
}
