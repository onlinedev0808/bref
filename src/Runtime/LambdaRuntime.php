<?php declare(strict_types=1);

namespace Bref\Runtime;

/**
 * Client for the AWS Lambda runtime API.
 *
 * This allows to interact with the API and:
 *
 * - fetch events to process
 * - signal errors
 * - send invocation responses
 *
 * It is intentionally dependency-free to keep cold starts as low as possible.
 *
 * Usage example:
 *
 *     $lambdaRuntime = LambdaRuntime::fromEnvironmentVariable();
 *     $invocation = $lambdaRuntime->waitNextInvocation();
 *     $event = $invocation->getEvent();
 *     // [...] process the event
 *     $invocation->success($responseData);
 */
class LambdaRuntime
{
    /** @var string */
    private $apiUrl;

    public static function fromEnvironmentVariable(): self
    {
        return new self(getenv('AWS_LAMBDA_RUNTIME_API'));
    }

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * Wait for the next lambda invocation and retrieve its data.
     *
     * This call is blocking because the Lambda runtime API is blocking.
     *
     * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-api.html#runtimes-api-next
     */
    public function waitNextInvocation(): Invocation
    {
        $handler = curl_init("http://{$this->apiUrl}/2018-06-01/runtime/invocation/next");
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handler, CURLOPT_FAILONERROR, true);

        // Retrieve invocation ID
        $invocationId = '';
        curl_setopt($handler, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$invocationId) {
            if (! preg_match('/:\s*/', $header)) {
                return strlen($header);
            }
            [$name, $value] = preg_split('/:\s*/', $header, 2);
            if (strtolower($name) === 'lambda-runtime-aws-request-id') {
                $invocationId = trim($value);
            }

            return strlen($header);
        });

        // Retrieve body
        $body = '';
        curl_setopt($handler, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$body) {
            $body .= $chunk;

            return strlen($chunk);
        });

        curl_exec($handler);
        if (curl_error($handler)) {
            throw new \Exception('Failed to fetch next Lambda invocation: ' . curl_error($handler));
        }
        if ($invocationId === '') {
            throw new \Exception('Failed to determine the Lambda invocation ID');
        }
        if ($body === '') {
            throw new \Exception('Empty Lambda runtime API response');
        }
        curl_close($handler);

        $event = json_decode($body, true);

        return new Invocation($this, $invocationId, $event);
    }

    /**
     * @param mixed $responseData
     *
     * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-api.html#runtimes-api-response
     */
    public function sendResponse(string $invocationId, $responseData): void
    {
        $url = "http://{$this->apiUrl}/2018-06-01/runtime/invocation/$invocationId/response";
        $this->postJson($url, $responseData);
    }

    /**
     * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-api.html#runtimes-api-invokeerror
     */
    public function signalFailure(string $invocationId, \Throwable $error): void
    {
        $url = "http://{$this->apiUrl}/2018-06-01/runtime/invocation/$invocationId/error";
        $this->postJson($url, [
            'errorMessage' => $error->getMessage(),
            'errorType' => get_class($error),
        ]);
    }

    /**
     * @param mixed $data
     */
    private function postJson(string $url, $data): void
    {
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            throw new \Exception('Failed encoding Lambda JSON response: ' . json_last_error_msg());
        }

        $handler = curl_init($url);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($handler, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
        ]);
        curl_exec($handler);
        if (curl_error($handler)) {
            $errorMessage = curl_error($handler);
            throw new \Exception('Error while calling the Lambda runtime API: ' . $errorMessage);
        }
        curl_close($handler);
    }
}
