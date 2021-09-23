<?php
/**
 * Copyright 2017 Facebook, Inc.
 *
 * You are hereby granted a non-exclusive, worldwide, royalty-free license to
 * use, copy, modify, and distribute this software in source code or binary
 * form for use in connection with the web services and APIs provided by
 * Facebook.
 *
 * As with any software that integrates with the Facebook platform, your use
 * of this software is subject to the Facebook Developer Principles and
 * Policies [http://developers.facebook.com/policy/]. This copyright notice
 * shall be included in all copies or substantial portions of the software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 */
namespace Facebook\Tests\HttpClients;

use Mockery as m;
use Facebook\HttpClients\FacebookGuzzleHttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Exception\RequestException;

class FacebookGuzzleHttpClientTest extends AbstractTestHttpClient
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $guzzleMock;

    /**
     * @var FacebookGuzzleHttpClient
     */
    protected $guzzleClient;

    protected function setUp() : void
    {
        $this->guzzleMock = m::mock('GuzzleHttp\Client');
        $this->guzzleClient = new FacebookGuzzleHttpClient($this->guzzleMock);
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testCanSendNormalRequest()
    {
        $response = new Response(200, $this->fakeHeadersAsArray(), $this->fakeRawBody);
        $timeOut = 123;

        $this->guzzleMock
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($request, $options) {
                if (get_class($request) !== 'GuzzleHttp\Psr7\Request') {
                    return false;
                } elseif (!is_array($options)) {
                    return false;
                }
                return true;
            })
            ->andReturn($response);
/*

*/
        $response = $this->guzzleClient->send('http://foo.com/', 'GET', 'foo_body', ['X-foo' => 'bar'], $timeOut);

        $this->assertInstanceOf('Facebook\Http\GraphRawResponse', $response);
        $this->assertEquals($this->fakeRawBody, $response->getBody());
        $this->assertEquals($this->fakeHeadersAsArray(), $response->getHeaders());
        $this->assertEquals(200, $response->getHttpResponseCode());
    }

    /**
     * @expectedException \Facebook\Exceptions\FacebookSDKException
     */
    public function testThrowsExceptionOnClientError()
    {
        $this->expectException('\Facebook\Exceptions\FacebookSDKException');

        $request = new Request('GET', 'http://foo.com');
        $timeOut = 123;

        $this->guzzleMock
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($request, $options) {
                if (get_class($request) !== 'GuzzleHttp\Psr7\Request') {
                    return false;
                } elseif (!is_array($options)) {
                    return false;
                }
                return true;
            })
            ->andThrow(new RequestException('Foo', $request));

        $this->guzzleClient->send('http://foo.com/', 'GET', 'foo_body', [], $timeOut);
    }
}
