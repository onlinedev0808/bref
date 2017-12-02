<?php
declare(strict_types=1);

namespace PhpLambda\Bridge\Slim;

use PhpLambda\HttpApplication;
use PhpLambda\LambdaResponse;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

/**
 * Allows to use the Slim framework.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class SlimAdapter implements HttpApplication
{
    /**
     * @var App
     */
    private $slim;

    public function __construct(App $slim)
    {
        $this->slim = $slim;
    }

    public function process(array $event) : LambdaResponse
    {
        $request = (new RequestFactory)->createRequest($event);
        $response = $this->slim->getContainer()->get('response');

        /** @var ResponseInterface $response */
        $response = $this->slim->process($request, $response);

        return LambdaResponse::fromPsr7Response($response);
    }
}
