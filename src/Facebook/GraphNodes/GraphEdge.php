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
namespace Facebook\GraphNodes;

use Facebook\FacebookRequest;
use Facebook\Url\FacebookUrlManipulator;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Class GraphEdge
 *
 * @package Facebook
 */
class GraphEdge implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var FacebookRequest The original request that generated this data.
     */
    protected FacebookRequest $request;

    /**
     * @var array An array of Graph meta data like pagination, etc.
     */
    protected array $metaData = [];

    /**
     * @var string|null The parent Graph edge endpoint that generated the list.
     */
    protected ?string $parentEdgeEndpoint;

    /**
     * @var string|null The subclass of the child GraphNode's.
     */
    protected ?string $subclassName;

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected array $items = [];

    /**
     * Init this collection of GraphNode's.
     *
     * @param FacebookRequest $request            The original request that generated this data.
     * @param array           $data               An array of GraphNode's.
     * @param array           $metaData           An array of Graph meta data like pagination, etc.
     * @param string|null     $parentEdgeEndpoint The parent Graph edge endpoint that generated the list.
     * @param string|null     $subclassName       The subclass of the child GraphNode's.
     */
    public function __construct(FacebookRequest $request, array $data = [], array $metaData = [], ?string $parentEdgeEndpoint = null, ?string $subclassName = null)
    {
        $this->request = $request;
        $this->metaData = $metaData;
        $this->parentEdgeEndpoint = $parentEdgeEndpoint;
        $this->subclassName = $subclassName;
        $this->items = $data;
    }

    /**
     * Gets the value of a field from the Graph node.
     *
     * @param string $name    the field to retrieve
     * @param mixed  $default the default to return if the field doesn't exist
     *
     * @return mixed
     */
    public function getField(string $name, $default = null)
    {
        if (isset($this->items[$name])) {
            return $this->items[$name];
        }

        return $default;
    }

    /**
     * Returns a list of all fields set on the object.
     *
     * @return array
     */
    public function getFieldNames() : array
    {
        return array_keys($this->items);
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array
     */
    public function all() : array
    {
        return $this->items;
    }

    /**
     * Get the collection of items as a plain array.
     *
     * @return array
     */
    public function asArray() : array
    {
        return array_map(function ($value) {
            if ($value instanceof GraphNode || $value instanceof GraphEdge) {
                return $value->asArray();
            }

            return $value;
        }, $this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function map(\Closure $callback)
    {
        return new static(
            $this->request,
            array_map($callback, $this->items, array_keys($this->items)),
            $this->metaData,
            $this->parentEdgeEndpoint,
            $this->subclassName
        );
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function asJson($options = 0) : string
    {
        return json_encode($this->asArray(), $options);
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->items);
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator() : \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Get an item at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Convert the collection to its string representation.
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->asJson();
    }

    /**
     * Gets the parent Graph edge endpoint that generated the list.
     *
     * @return string|null
     */
    public function getParentGraphEdge() : ?string
    {
        return $this->parentEdgeEndpoint;
    }

    /**
     * Gets the subclass name that the child GraphNode's are cast as.
     *
     * @return string|null
     */
    public function getSubClassName() : ?string
    {
        return $this->subclassName;
    }

    /**
     * Returns the raw meta data associated with this GraphEdge.
     *
     * @return array
     */
    public function getMetaData() : array
    {
        return $this->metaData;
    }

    /**
     * Returns the next cursor if it exists.
     *
     * @return string|null
     */
    public function getNextCursor() : ?string
    {
        return $this->getCursor('after');
    }

    /**
     * Returns the previous cursor if it exists.
     *
     * @return string|null
     */
    public function getPreviousCursor() : ?string
    {
        return $this->getCursor('before');
    }

    /**
     * Returns the cursor for a specific direction if it exists.
     *
     * @param string $direction The direction of the page: after|before
     *
     * @return string|null
     */
    public function getCursor(string $direction) : ?string
    {
        if (isset($this->metaData['paging']['cursors'][$direction])) {
            return $this->metaData['paging']['cursors'][$direction];
        }

        return null;
    }

    /**
     * Generates a pagination URL based on a cursor.
     *
     * @param string $direction The direction of the page: next|previous
     *
     * @return string|null
     *
     * @throws FacebookSDKException
     */
    public function getPaginationUrl(string $direction) : ?string
    {
        $this->validateForPagination();

        // Do we have a paging URL?
        if (!isset($this->metaData['paging'][$direction])) {
            return null;
        }

        $pageUrl = $this->metaData['paging'][$direction];

        return FacebookUrlManipulator::baseGraphUrlEndpoint($pageUrl);
    }

    /**
     * Validates whether or not we can paginate on this request.
     *
     * @throws FacebookSDKException
     */
    public function validateForPagination() : void
    {
        if ($this->request->getMethod() !== 'GET') {
            throw new FacebookSDKException('You can only paginate on a GET request.', 720);
        }
    }

    /**
     * Gets the request object needed to make a next|previous page request.
     *
     * @param string $direction The direction of the page: next|previous
     *
     * @return FacebookRequest|null
     *
     * @throws FacebookSDKException
     */
    public function getPaginationRequest($direction)
    {
        $pageUrl = $this->getPaginationUrl($direction);
        if (!$pageUrl) {
            return null;
        }

        $newRequest = clone $this->request;
        $newRequest->setEndpoint($pageUrl);

        return $newRequest;
    }

    /**
     * Gets the request object needed to make a "next" page request.
     *
     * @return FacebookRequest|null
     *
     * @throws FacebookSDKException
     */
    public function getNextPageRequest()
    {
        return $this->getPaginationRequest('next');
    }

    /**
     * Gets the request object needed to make a "previous" page request.
     *
     * @return FacebookRequest|null
     *
     * @throws FacebookSDKException
     */
    public function getPreviousPageRequest()
    {
        return $this->getPaginationRequest('previous');
    }

    /**
     * The total number of results according to Graph if it exists.
     *
     * This will be returned if the summary=true modifier is present in the request.
     *
     * @return int|null
     */
    public function getTotalCount()
    {
        if (isset($this->metaData['summary']['total_count'])) {
            return $this->metaData['summary']['total_count'];
        }

        return null;
    }
}
