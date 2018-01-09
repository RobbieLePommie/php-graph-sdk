<?php
declare(strict_types=1);
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
namespace Facebook;

use Facebook\GraphNodes\GraphNodeFactory;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Class FacebookResponse
 *
 * @package Facebook
 */
class FacebookResponse
{
    /**
     * @var int The HTTP status code response from Graph.
     */
    protected ?int $httpStatusCode;

    /**
     * @var array The headers returned from Graph.
     */
    protected array $headers;

    /**
     * @var string The raw body of the response from Graph.
     */
    protected ?string $body;

    /**
     * @var array The decoded body of the Graph response.
     */
    protected array $decodedBody = [];

    /**
     * @var FacebookRequest The original request that returned this response.
     */
    protected ?FacebookRequest $request;

    /**
     * @var FacebookSDKException The exception thrown by this request.
     */
    protected ?FacebookResponseException $thrownException = null;

    /**
     * Creates a new Response entity.
     *
     * @param FacebookRequest $request
     * @param string|null     $body
     * @param int|null        $httpStatusCode
     * @param array|null      $headers
     */
    public function __construct(?FacebookRequest $request, ?string $body = null, ?int $httpStatusCode = null, array $headers = [])
    {
        $this->request = $request;
        $this->body = $body;
        $this->httpStatusCode = $httpStatusCode;
        $this->headers = $headers;

        $this->decodeBody();
    }

    /**
     * Return the original request that returned this response.
     *
     * @return FacebookRequest
     */
    public function getRequest() : ?FacebookRequest
    {
        return $this->request;
    }

    /**
     * Return the FacebookApp entity used for this response.
     *
     * @return FacebookApp
     */
    public function getApp() : ?FacebookApp
    {
        return $this->request->getApp();
    }

    /**
     * Return the access token that was used for this response.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return $this->request->getAccessToken();
    }

    /**
     * Return the HTTP status code for this response.
     *
     * @return int
     */
    public function getHttpStatusCode() : ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Return the HTTP headers for this response.
     *
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * Return the raw body response.
     *
     * @return string
     */
    public function getBody() : ?string
    {
        return $this->body;
    }

    /**
     * Return the decoded body response.
     *
     * @return array
     */
    public function getDecodedBody() : array
    {
        return $this->decodedBody;
    }

    /**
     * Get the app secret proof that was used for this response.
     *
     * @return string|null
     */
    public function getAppSecretProof() : string
    {
        return $this->request->getAppSecretProof();
    }

    /**
     * Get the ETag associated with the response.
     *
     * @return string|null
     */
    public function getETag() : ?string
    {
        return $this->headers['ETag'] ?? null;
    }

    /**
     * Get the version of Graph that returned this response.
     *
     * @return string|null
     */
    public function getGraphVersion() : ?string
    {
        return $this->headers['Facebook-API-Version'] ?? null;
    }

    /**
     * Returns true if Graph returned an error message.
     *
     * @return boolean
     */
    public function isError() : bool
    {
        return isset($this->decodedBody['error']);
    }

    /**
     * Throws the exception.
     *
     * @throws FacebookResponseException
     */
    public function throwException() : ?FacebookResponseException
    {
        throw $this->thrownException;
    }

    /**
     * Instantiates an exception to be thrown later.
     */
    public function makeException() : void
    {
        $this->thrownException = FacebookResponseException::create($this);
    }

    /**
     * Returns the exception that was thrown for this request.
     *
     * @return FacebookResponseException|null
     */
    public function getThrownException() : ?FacebookResponseException
    {
        return $this->thrownException;
    }

    /**
     * Convert the raw response into an array if possible.
     *
     * Graph will return 2 types of responses:
     * - JSON(P)
     *    Most responses from Graph are JSON(P)
     * - application/x-www-form-urlencoded key/value pairs
     *    Happens on the `/oauth/access_token` endpoint when exchanging
     *    a short-lived access token for a long-lived access token
     * - And sometimes nothing :/ but that'd be a bug.
     */
    public function decodeBody() : void
    {
        if (empty($this->body)) {
            $this->decodedBody = [];
        } else {
            $decodedBody = json_decode($this->body, true);

            if ($decodedBody === null) {
                $this->decodedBody = [];
                parse_str($this->body, $this->decodedBody);
            } elseif (is_bool($decodedBody)) {
                // Backwards compatibility for Graph < 2.1.
                // Mimics 2.1 responses.
                // @TODO Remove this after Graph 2.0 is no longer supported
                $this->decodedBody = [
                    'success' => $decodedBody
                ];
            } elseif (is_numeric($decodedBody)) {
                $this->decodedBody = [
                    'id' => $decodedBody
                ];
            } else {
                $this->decodedBody = $decodedBody;
            }
        }

        if ($this->isError()) {
            $this->makeException();
        }
    }

    /**
     * Instantiate a new GraphNode from response.
     *
     * @param string|null $subclassName The GraphNode subclass to cast to.
     *
     * @return \Facebook\GraphNodes\GraphNode
     *
     * @throws FacebookSDKException
     */
    public function getGraphNode($subclassName = null)
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphNode($subclassName);
    }

    /**
     * Convenience method for creating a GraphAlbum collection.
     *
     * @return \Facebook\GraphNodes\GraphAlbum
     *
     * @throws FacebookSDKException
     */
    public function getGraphAlbum()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphAlbum();
    }

    /**
     * Convenience method for creating a GraphPage collection.
     *
     * @return \Facebook\GraphNodes\GraphPage
     *
     * @throws FacebookSDKException
     */
    public function getGraphPage()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphPage();
    }

    /**
     * Convenience method for creating a GraphSessionInfo collection.
     *
     * @return \Facebook\GraphNodes\GraphSessionInfo
     *
     * @throws FacebookSDKException
     */
    public function getGraphSessionInfo()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphSessionInfo();
    }

    /**
     * Convenience method for creating a GraphUser collection.
     *
     * @return \Facebook\GraphNodes\GraphUser
     *
     * @throws FacebookSDKException
     */
    public function getGraphUser()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphUser();
    }

    /**
     * Convenience method for creating a GraphEvent collection.
     *
     * @return \Facebook\GraphNodes\GraphEvent
     *
     * @throws FacebookSDKException
     */
    public function getGraphEvent()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphEvent();
    }

    /**
     * Convenience method for creating a GraphGroup collection.
     *
     * @return \Facebook\GraphNodes\GraphGroup
     *
     * @throws FacebookSDKException
     */
    public function getGraphGroup()
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphGroup();
    }

    /**
     * Instantiate a new GraphList from response.
     *
     * @param string|null $subclassName The GraphNode subclass to cast list items to.
     * @param boolean     $auto_prefix  Toggle to auto-prefix the subclass name.
     *
     * @return \Facebook\GraphNodes\GraphList
     *
     * @throws FacebookSDKException
     *
     * @deprecated 5.0.0 getGraphList() has been renamed to getGraphEdge()
     * @todo v6: Remove this method
     */
    public function getGraphList($subclassName = null, $auto_prefix = true)
    {
        return $this->getGraphEdge($subclassName, $auto_prefix);
    }

    /**
     * Instantiate a new GraphEdge from response.
     *
     * @param string|null $subclassName The GraphNode subclass to cast list items to.
     * @param boolean     $auto_prefix  Toggle to auto-prefix the subclass name.
     *
     * @return \Facebook\GraphNodes\GraphEdge
     *
     * @throws FacebookSDKException
     */
    public function getGraphEdge($subclassName = null, $auto_prefix = true)
    {
        $factory = new GraphNodeFactory($this);

        return $factory->makeGraphEdge($subclassName, $auto_prefix);
    }
}
