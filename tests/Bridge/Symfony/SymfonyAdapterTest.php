<?php
declare(strict_types=1);

namespace Bref\Test\Bridge\Symfony;

use Bref\Bridge\Symfony\SymfonyAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Zend\Diactoros\ServerRequest;

class SymfonyAdapterTest extends TestCase
{
    private const ROUTE_WITHOUT_SESSION = '/';
    private const ROUTE_WITH_SESSION = '/session';
    private const ROUTE_NOT_FOUND = '/not-found';

    public function setUp()
    {
        parent::setUp();

        $fs = new Filesystem;
        $fs->remove([__DIR__ . '/cache', __DIR__ . '/logs']);
    }

    public function test Symfony applications are adapted()
    {
        $adapter = new SymfonyAdapter($this->createKernel());

        $response = $adapter->handle(new ServerRequest([], [], self::ROUTE_WITHOUT_SESSION));

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('Hello world!', (string) $response->getBody());
    }

    public function test 404 are PSR7 responses and not exceptions()
    {
        $adapter = new SymfonyAdapter($this->createKernel());

        $response = $adapter->handle(new ServerRequest([], [], self::ROUTE_NOT_FOUND));

        self::assertEquals(404, $response->getStatusCode());
        self::assertEquals('Not found', (string) $response->getBody());
    }

    public function test a session is not created when sessions not used()
    {
        $adapter = new SymfonyAdapter($this->createKernel());

        $response = $adapter->handle(new ServerRequest([], [], self::ROUTE_WITHOUT_SESSION));

        self::assertArrayNotHasKey('Set-Cookie', $response->getHeaders());
    }

    public function test an active session is created when sessions used()
    {
        $adapter = new SymfonyAdapter($kernel = $this->createKernel());

        $response = $adapter->handle(new ServerRequest([], [], self::ROUTE_WITH_SESSION));

        $symfonySessionId = $kernel->getContainer()->get('session')->getId();
        self::assertEquals($symfonySessionId, (string) $response->getBody());
        self::assertEquals(
            sprintf("%s=%s; path=/", \session_name(), $symfonySessionId),
            $response->getHeaders()['Set-Cookie'][0]
        );
    }

    public function test an existing session is used when session provided()
    {
        $adapter = new SymfonyAdapter($this->createKernel());

        $response = $adapter->handle(
            new ServerRequest(
                [],
                [],
                self::ROUTE_WITH_SESSION,
                null,
                'php://input',
                [],
                [\session_name() => 'SESSIONID']
            )
        );

        self::assertArrayNotHasKey('Set-Cookie', $response->getHeaders());
        self::assertEquals('SESSIONID', (string) $response->getBody());
    }

    private function createKernel(): HttpKernelInterface
    {
        $kernel = new class('dev', false) extends Kernel implements EventSubscriberInterface {
            use MicroKernelTrait;

            public function registerBundles()
            {
                return [new FrameworkBundle];
            }

            protected function configureContainer(ContainerBuilder $c)
            {
                $c->register('session_storage', MockArraySessionStorage::class);

                $c->loadFromExtension('framework', [
                    'secret'  => 'foo',
                    'session' => [
                        'storage_id' => 'session_storage'
                    ]
                ]);
            }

            protected function configureRoutes(RouteCollectionBuilder $routes)
            {
                $routes->add('/', 'kernel:testActionWithoutSession');
                $routes->add('/session', 'kernel:testActionWithSession');
            }

            public function testActionWithoutSession()
            {
                return new Response('Hello world!');
            }

            public function testActionWithSession(Session $session)
            {
                $session->set('ACTIVATE', 'SESSIONS'); // ensure that Symfony starts/uses sessions

                return new Response($session->getId());
            }

            public static function getSubscribedEvents()
            {
                return [KernelEvents::EXCEPTION => 'onKernelException'];
            }

            /**
             * We have to handle NotFound exceptions ourselves because they are not handled by the micro-kernel
             */
            public function onKernelException(GetResponseForExceptionEvent $event)
            {
                if ($event->getException() instanceof NotFoundHttpException) {
                    $event->setResponse(new Response('Not found', 404));
                }
            }
        };

        $kernel->boot();

        return $kernel;
    }
}
