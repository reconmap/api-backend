<?php

declare(strict_types=1);

namespace Reconmap;

use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ResponseFactory;
use League\Container\Container;
use League\Route\RouteGroup;
use League\Route\Router;
use League\Route\Strategy\JsonStrategy;
use Monolog\Logger;
use Reconmap\Controllers\AuditLog\AuditLogRouter;
use Reconmap\Controllers\Clients\ClientsRouter;
use Reconmap\Controllers\Organisations\OrganisationsRouter;
use Reconmap\Controllers\Projects\ProjectsRouter;
use Reconmap\Controllers\Reports\ReportsRouter;
use Reconmap\Controllers\System\SystemRouter;
use Reconmap\Controllers\Targets\TargetsRouter;
use Reconmap\Controllers\Tasks\TasksRouter;
use Reconmap\Controllers\Users\UsersLoginController;
use Reconmap\Controllers\Users\UsersRouter;
use Reconmap\Controllers\Vulnerabilities\VulnerabilitiesRouter;

class ApiRouter extends Router
{
    private const ROUTER_CLASSES = [
        TasksRouter::class,
        ClientsRouter::class,
        UsersRouter::class,
        ReportsRouter::class,
        VulnerabilitiesRouter::class,
        AuditLogRouter::class,
        ProjectsRouter::class,
        TargetsRouter::class,
        SystemRouter::class,
        OrganisationsRouter::class
    ];

    private Logger $logger;

    private AuthMiddleware $authMiddleware;

    private CorsMiddleware $corsMiddleware;

    public function mapRoutes(Container $container, Logger $logger): void
    {
        $this->setupStrategy($container, $logger);
        $this->map('OPTIONS', '/{any:.*}', function (): Response {
            return $this->getResponse();
        });

        $this->map('POST', '/users/login', UsersLoginController::class)
            ->middleware($this->corsMiddleware);
        $this->group('', function (RouteGroup $router): void {
            foreach (self::ROUTER_CLASSES as $mappable) {
                (new $mappable)->mapRoutes($router);
            }
        })->middlewares([$this->corsMiddleware, $this->authMiddleware]);
    }

    /**
     * @param Container $container
     * @param Logger $logger
     */
    private function setupStrategy(Container $container, Logger $logger)
    {
        $responseFactory = new ResponseFactory;
        $strategy = new JsonStrategy($responseFactory);
        $strategy->setContainer($container);
        $this->setStrategy($strategy);
        $this->logger = $logger;
        $this->authMiddleware = new AuthMiddleware($logger);
        $this->corsMiddleware = new CorsMiddleware($logger);
    }

    private function getResponse(): Response
    {
        return (new Response)
            ->withStatus(200)
            ->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,PATCH')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization,Bulk-Operation,Content-Type')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }
}
