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

/**
 * Class GraphNode
 *
 * @package Facebook
 */
class GraphNode implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var array Maps object key names to Graph object types.
     */
    protected static array $graphObjectMap = [];

    /**
     * The fields contained in the node.
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * Init this Graph object.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->fields = $this->castFields($data);
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
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
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
        return array_keys($this->fields);
    }

    /**
     * Get all of the fields in the node.
     *
     * @return array
     */
    public function all() : array
    {
        return $this->fields;
    }

    /**
     * Get all fields as a plain array.
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
        }, $this->fields);
    }

    /**
     * Run a map over each field.
     *
     * @param \Closure $callback
     *
     * @return static
     */
    public function map(\Closure $callback)
    {
        return new static(array_map($callback, $this->fields, array_keys($this->fields)));
    }

    /**
     * Get all fields as JSON.
     *
     * @param int $options
     *
     * @return string
     */
    public function asJson($options = 0) : string
    {
        return json_encode($this->uncastFields(), $options);
    }

    /**
     * Get an iterator for the fields.
     *
     * @return \ArrayIterator
     */
    public function getIterator() : \ArrayIterator
    {
        return new \ArrayIterator($this->fields);
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function offsetExists(string $key) : bool
    {
        return array_key_exists($key, $this->fields);
    }

    /**
     * Get an item at a given offset.
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function offsetGet(string $key)
    {
        return $this->fields[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet(string $key, $value) : void
    {
        if (is_null($key)) {
            $this->fields[] = $value;
        } else {
            $this->fields[$key] = $value;
        }
    }

    /**
     * Unset the item at a given offset.
     *
     * @param string $key
     *
     * @return void
     */
    public function offsetUnset(string $key) : void
    {
        unset($this->fields[$key]);
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
     * Iterates over an array and detects the types each node
     * should be cast to and returns all the fields as an array.
     *
     * @TODO Add auto-casting to AccessToken entities.
     *
     * @param array $data The array to iterate over.
     *
     * @return array
     */
    public function castFields(array $data) : array
    {
        $fields = [];

        foreach ($data as $k => $v) {
            if ($this->shouldCastAsDateTime($k)
                && (is_numeric($v)
                    || $this->isIso8601DateString($v))
            ) {
                $fields[$k] = $this->castToDateTime($v);
            } elseif ($k === 'birthday') {
                $fields[$k] = $this->castToBirthday($v);
            } else {
                $fields[$k] = $v;
            }
        }

        return $fields;
    }

    /**
     * Uncasts any auto-casted datatypes.
     * Basically the reverse of castFields().
     *
     * @return array
     */
    public function uncastFields() : array
    {
        $fields = $this->asArray();

        return array_map(function ($v) {
            if ($v instanceof \DateTime) {
                return $v->format(\DateTime::ISO8601);
            }

            return $v;
        }, $fields);
    }

    /**
     * Detects an ISO 8601 formatted string.
     *
     * @param string $string
     *
     * @return boolean
     *
     * @see https://developers.facebook.com/docs/graph-api/using-graph-api/#readmodifiers
     * @see http://www.cl.cam.ac.uk/~mgk25/iso-time.html
     * @see http://en.wikipedia.org/wiki/ISO_8601
     */
    public function isIso8601DateString(string $string) : bool
    {
        // This insane regex was yoinked from here:
        // http://www.pelagodesign.com/blog/2009/05/20/iso-8601-date-validation-that-doesnt-suck/
        // ...and I'm all like:
        // http://thecodinglove.com/post/95378251969/when-code-works-and-i-dont-know-why
        $crazyInsaneRegexThatSomehowDetectsIso8601 = '/^([\+-]?\d{4}(?!\d{2}\b))'
            . '((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?'
            . '|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d'
            . '|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])'
            . '((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d'
            . '([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/';

        return preg_match($crazyInsaneRegexThatSomehowDetectsIso8601, $string) === 1;
    }

    /**
     * Determines if a value from Graph should be cast to DateTime.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function shouldCastAsDateTime(string $key) : bool
    {
        return in_array($key, [
            'created_time',
            'updated_time',
            'start_time',
            'end_time',
            'backdated_time',
            'issued_at',
            'expires_at',
            'publish_time',
            'joined'
        ], true);
    }

    /**
     * Casts a date value from Graph to DateTime.
     *
     * @param int|string $value
     *
     * @return \DateTime
     */
    public function castToDateTime($value) : \DateTime
    {
        if (is_int($value)) {
            $dt = new \DateTime();
            $dt->setTimestamp($value);
        } else {
            $dt = new \DateTime($value);
        }

        return $dt;
    }

    /**
     * Casts a birthday value from Graph to Birthday
     *
     * @param string $value
     *
     * @return Birthday
     */
    public function castToBirthday(string $value) : Birthday
    {
        return new Birthday($value);
    }

    /**
     * Getter for $graphObjectMap.
     *
     * @return array
     */
    public static function getObjectMap()
    {
        return static::$graphObjectMap;
    }
}
