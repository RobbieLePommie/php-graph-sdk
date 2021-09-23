<?php
declare(strict_types=1);

/* Adapted from:
 - FacebookGuzzleHttpClient in 5.x
 - https://www.sammyk.me/how-to-inject-your-own-http-client-in-the-facebook-php-sdk-v5#writing-a-guzzle-6-http-client-implementation-from-scratch
*/
namespace Facebook\HttpClients;

use Facebook\Http\GraphRawResponse;
use Facebook\Exceptions\FacebookSDKException;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Ring\Exception\RingException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;

class FacebookGuzzleHttpClient implements FacebookHttpClientInterface
{
    private Client $guzzleClient;

    public function __construct(?Client $client = null)
    {
        $this->guzzleClient = ($client ?? new Client());
    }

    public function send(string $url, string $method, string $body, array $headers, int $timeOut) : GraphRawResponse
    {

        $request = new Request($method, $url, $headers, $body);
        try {
            $options = [
                'timeout' => $timeOut,
                'connect_timeout' => 10,
                'http_errors' => false
            ];
            $response = $this->guzzleClient->send($request, $options);
        } catch (TransferException $e) {
            throw new FacebookSDKException($e->getMessage(), $e->getCode());
        } catch (RequestException $e) {
            throw new FacebookSDKException($e->getMessage(), $e->getCode());
        }
        $responseHeaders = $this->getHeadersAsString($response);
        $responseBody = $response->getBody()->getContents();
        $httpStatusCode = $response->getStatusCode();

        return new GraphRawResponse(
            $responseHeaders,
            $responseBody,
            $httpStatusCode
        );
    }


    /**
     * Returns the Guzzle array of headers as a string.
     *
     * @param ResponseInterface $response The Guzzle response.
     *
     * @return string
     */
    public function getHeadersAsString(Response $response) : string
    {
        $rawHeaders = $this->getHeadersAsArray($response);
        return implode("\r\n", $rawHeaders);
    }

    /**
     * Returns the Guzzle array of headers as an array.
     *
     * @param ResponseInterface $response The Guzzle response.
     *
     * @return array
     */
    public function getHeadersAsArray(Response $response) : array
    {
        $headers = $response->getHeaders();
        $rawHeaders = [];
        foreach ($headers as $name => $values) {
            $rawHeaders[] = $name . ": " . implode(", ", $values);
        }
        return $rawHeaders;
    }
}
