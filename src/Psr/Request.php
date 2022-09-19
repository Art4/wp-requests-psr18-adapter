<?php
/**
 * PSR-7 RequestInterface implementation
 *
 * @package Requests\Psr
 */

namespace WpOrg\Requests\Psr;

use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use WpOrg\Requests\Exception\InvalidArgument;

/**
 * PSR-7 RequestInterface implementation
 *
 * @package Requests\Psr
 *
 * Representation of an outgoing, client-side request.
 *
 * Per the HTTP specification, this interface includes properties for
 * each of the following:
 *
 * - Protocol version
 * - HTTP method
 * - URI
 * - Headers
 * - Message body
 *
 * During construction, implementations MUST attempt to set the Host header from
 * a provided URI if no Host header is provided.
 *
 * Requests are considered immutable; all methods that might change state MUST
 * be implemented such that they retain the internal state of the current
 * message and return an instance that contains the changed state.
 */
final class Request implements RequestInterface {

	/**
	 * create Request with method and uri
	 *
	 * @param string|Stringable $method
	 * @param UriInterface $uri
	 *
	 * @return Request
	 */
	public static function withMethodAndUri($method, UriInterface $uri) {
		if (!is_string($method)) {
			throw InvalidArgument::create(1, '$method', 'string', gettype($method));
		}

		return new self((string) $method, $uri);
	}

	/**
	 * @var string
	 */
	private $method;

	/**
	 * @var UriInterface
	 */
	private $uri;

	/**
	 * @var string
	 */
	private $requestTarget = '';

	/**
	 * @var string
	 */
	private $protocolVersion = '1.1';

	/**
	 * @var array
	 */
	private $headers = [];

	/**
	 * @var array
	 */
	private $headerNames = [];

	/**
	 * Constructor
	 *
	 * @param string $method
	 * @param UriInterface $uri
	 *
	 * @return Request
	 */
	private function __construct($method, UriInterface $uri) {
		$this->method = $method;
		$this->setUri($uri);
	}

	/**
	 * Retrieves the message's request target.
	 *
	 * Retrieves the message's request-target either as it will appear (for
	 * clients), as it appeared at request (for servers), or as it was
	 * specified for the instance (see withRequestTarget()).
	 *
	 * In most cases, this will be the origin-form of the composed URI,
	 * unless a value was provided to the concrete implementation (see
	 * withRequestTarget() below).
	 *
	 * If no URI is available, and no request-target has been specifically
	 * provided, this method MUST return the string "/".
	 *
	 * @return string
	 */
	public function getRequestTarget() {
		if ($this->requestTarget !== '') {
			return $this->requestTarget;
		}

		$target = $this->uri->getPath();

		if ($target === '') {
			$target = '/';
		}

		$query = $this->uri->getQuery();

		if ($query !== '') {
			$target .= '?' . $query;
		}

		return $target;
	}

	/**
	 * Return an instance with the specific request-target.
	 *
	 * If the request needs a non-origin-form request-target — e.g., for
	 * specifying an absolute-form, authority-form, or asterisk-form —
	 * this method may be used to create an instance with the specified
	 * request-target, verbatim.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request target.
	 *
	 * @see http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
	 *     request-target forms allowed in request messages)
	 * @param mixed $requestTarget
	 * @return static
	 */
	public function withRequestTarget($requestTarget) {
		// $requestTarget accepts only string
		// @see https://github.com/php-fig/http-message/pull/78
		if (!is_string($requestTarget)) {
			throw InvalidArgument::create(1, '$requestTarget', 'string', gettype($requestTarget));
		}

		if ($requestTarget === '') {
			$requestTarget = '/';
		}

		$request = clone($this);
		$request->requestTarget = $requestTarget;

		return $request;
	}

	/**
	 * Retrieves the HTTP method of the request.
	 *
	 * @return string Returns the request method.
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * Return an instance with the provided HTTP method.
	 *
	 * While HTTP method names are typically all uppercase characters, HTTP
	 * method names are case-sensitive and thus implementations SHOULD NOT
	 * modify the given string.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request method.
	 *
	 * @param string $method Case-sensitive method.
	 * @return static
	 * @throws \InvalidArgumentException for invalid HTTP methods.
	 */
	public function withMethod($method) {
		if (!is_string($method)) {
			throw InvalidArgument::create(1, '$method', 'string', gettype($method));
		}

		$request = clone($this);
		$request->method = $method;

		return $request;
	}

	/**
	 * Retrieves the URI instance.
	 *
	 * This method MUST return a UriInterface instance.
	 *
	 * @see http://tools.ietf.org/html/rfc3986#section-4.3
	 * @return UriInterface Returns a UriInterface instance
	 *     representing the URI of the request.
	 */
	public function getUri() {
		return $this->uri;
	}

	/**
	 * Returns an instance with the provided URI.
	 *
	 * This method MUST update the Host header of the returned request by
	 * default if the URI contains a host component. If the URI does not
	 * contain a host component, any pre-existing Host header MUST be carried
	 * over to the returned request.
	 *
	 * You can opt-in to preserving the original state of the Host header by
	 * setting `$preserveHost` to `true`. When `$preserveHost` is set to
	 * `true`, this method interacts with the Host header in the following ways:
	 *
	 * - If the Host header is missing or empty, and the new URI contains
	 *   a host component, this method MUST update the Host header in the returned
	 *   request.
	 * - If the Host header is missing or empty, and the new URI does not contain a
	 *   host component, this method MUST NOT update the Host header in the returned
	 *   request.
	 * - If a Host header is present and non-empty, this method MUST NOT update
	 *   the Host header in the returned request.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new UriInterface instance.
	 *
	 * @see http://tools.ietf.org/html/rfc3986#section-4.3
	 * @param UriInterface $uri New request URI to use.
	 * @param bool $preserveHost Preserve the original state of the Host header.
	 * @return static
	 */
	public function withUri(UriInterface $uri, $preserveHost = false) {
		$request = clone($this);
		$request->setUri($uri);

		return $request;
	}

	/**
	 * Retrieves the HTTP protocol version as a string.
	 *
	 * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
	 *
	 * @return string HTTP protocol version.
	 */
	public function getProtocolVersion() {
		return $this->protocolVersion;
	}

	/**
	 * Return an instance with the specified HTTP protocol version.
	 *
	 * The version string MUST contain only the HTTP version number (e.g.,
	 * "1.1", "1.0").
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new protocol version.
	 *
	 * @param string $version HTTP protocol version
	 * @return static
	 */
	public function withProtocolVersion($version) {
		if (!is_string($version)) {
			throw InvalidArgument::create(1, '$version', 'string', gettype($version));
		}

		$request = clone($this);
		$request->protocolVersion = $version;

		return $request;
	}

	/**
	 * Retrieves all message header values.
	 *
	 * The keys represent the header name as it will be sent over the wire, and
	 * each value is an array of strings associated with the header.
	 *
	 *     // Represent the headers as a string
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         echo $name . ': ' . implode(', ', $values);
	 *     }
	 *
	 *     // Emit headers iteratively:
	 *     foreach ($message->getHeaders() as $name => $values) {
	 *         foreach ($values as $value) {
	 *             header(sprintf('%s: %s', $name, $value), false);
	 *         }
	 *     }
	 *
	 * While header names are not case-sensitive, getHeaders() will preserve the
	 * exact case in which headers were originally specified.
	 *
	 * @return string[][] Returns an associative array of the message's headers.
	 *     Each key MUST be a header name, and each value MUST be an array of
	 *     strings for that header.
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Checks if a header exists by the given case-insensitive name.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return bool Returns true if any header names match the given header
	 *     name using a case-insensitive string comparison. Returns false if
	 *     no matching header name is found in the message.
	 */
	public function hasHeader($name) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		return array_key_exists(strtolower($name), $this->headerNames);
	}

	/**
	 * Retrieves a message header value by the given case-insensitive name.
	 *
	 * This method returns an array of all the header values of the given
	 * case-insensitive header name.
	 *
	 * If the header does not appear in the message, this method MUST return an
	 * empty array.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string[] An array of string values as provided for the given
	 *    header. If the header does not appear in the message, this method MUST
	 *    return an empty array.
	 */
	public function getHeader($name) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		if (!array_key_exists(strtolower($name), $this->headers)) {
			return [];
		}

		return $this->headers[$this->headerNames[strtolower($name)]];
	}

	/**
	 * Retrieves a comma-separated string of the values for a single header.
	 *
	 * This method returns all of the header values of the given
	 * case-insensitive header name as a string concatenated together using
	 * a comma.
	 *
	 * NOTE: Not all header values may be appropriately represented using
	 * comma concatenation. For such headers, use getHeader() instead
	 * and supply your own delimiter when concatenating.
	 *
	 * If the header does not appear in the message, this method MUST return
	 * an empty string.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @return string A string of values as provided for the given header
	 *    concatenated together using a comma. If the header does not appear in
	 *    the message, this method MUST return an empty string.
	 */
	public function getHeaderLine($name) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		if (!array_key_exists(strtolower($name), $this->headers)) {
			return '';
		}

		return implode(',', $this->headers[$this->headerNames[strtolower($name)]]);
	}

	/**
	 * Return an instance with the provided value replacing the specified header.
	 *
	 * While header names are case-insensitive, the casing of the header will
	 * be preserved by this function, and returned from getHeaders().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new and/or updated header and value.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 * @throws \InvalidArgumentException for invalid header names or values.
	 */
	public function withHeader($name, $value) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		if (!is_string($value) && !is_array($value)) {
			throw InvalidArgument::create(2, '$value', 'string|array containing strings', gettype($value));
		}

		if (!is_array($value)) {
			$value = [$value];
		}

		foreach ($value as $line) {
			if (!is_string($line)) {
				throw InvalidArgument::create(2, '$value', 'string|array containing strings', gettype($value));
			}
		}

		$request = clone($this);
		$request->updateHeader($name, $value);

		return $request;
	}

	/**
	 * Return an instance with the specified header appended with the given value.
	 *
	 * Existing values for the specified header will be maintained. The new
	 * value(s) will be appended to the existing list. If the header did not
	 * exist previously, it will be added.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new header and/or value.
	 *
	 * @param string $name Case-insensitive header field name to add.
	 * @param string|string[] $value Header value(s).
	 * @return static
	 * @throws \InvalidArgumentException for invalid header names.
	 * @throws \InvalidArgumentException for invalid header values.
	 */
	public function withAddedHeader($name, $value) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		if (!is_string($value) && !is_array($value)) {
			throw InvalidArgument::create(2, '$value', 'string|array containing strings', gettype($value));
		}

		if (!is_array($value)) {
			$value = [$value];
		}

		foreach ($value as $line) {
			if (!is_string($line)) {
				throw InvalidArgument::create(2, '$value', 'string|array containing strings', gettype($value));
			}
		}

		$request = clone($this);

		$request->updateHeader($name, array_merge($request->getHeader($name), $value));

		return $request;
	}

	/**
	 * Return an instance without the specified header.
	 *
	 * Header resolution MUST be done without case-sensitivity.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the named header.
	 *
	 * @param string $name Case-insensitive header field name to remove.
	 * @return static
	 */
	public function withoutHeader($name) {
		if (!is_string($name)) {
			throw InvalidArgument::create(1, '$name', 'string', gettype($name));
		}

		$request = clone($this);
		$request->updateHeader($name, []);

		return $request;
	}

	/**
	 * Gets the body of the message.
	 *
	 * @return StreamInterface Returns the body as a stream.
	 */
	public function getBody() {
		throw new Exception('not implemented');
	}

	/**
	 * Return an instance with the specified message body.
	 *
	 * The body MUST be a StreamInterface object.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return a new instance that has the
	 * new body stream.
	 *
	 * @param StreamInterface $body Body.
	 * @return static
	 * @throws \InvalidArgumentException When the body is not valid.
	 */
	public function withBody(StreamInterface $body) {
		throw new Exception('not implemented');
	}

	/**
	 * Set the URI and update the Host header.
	 *
	 * @see http://tools.ietf.org/html/rfc3986#section-4.3
	 * @param UriInterface $uri New request URI to use.
	 * @param bool $preserveHost Preserve the original state of the Host header.
	 * @return void
	 */
	private function setUri(UriInterface $uri, $preserveHost = false) {
		$this->uri = $uri;

		$host = $uri->getHost();

		if ($host !== '') {
			$this->updateHeader('Host', [$host]);
		}
	}

	/**
	 * Set, update or remove a header.
	 *
	 * @param string $name Case-insensitive header field name.
	 * @param string[] $values Header value(s) or empty array to remove the header.
	 * @return void
	 */
	private function updateHeader($name, $values) {
		$headerName = strtolower($name);

		if (array_key_exists($headerName, $this->headerNames)) {
			unset($this->headers[$this->headerNames[$headerName]]);
			unset($this->headerNames[$headerName]);
		}

		if ($values === []) {
			return;
		}

		// Since the Host field-value is critical information for handling a
		// request, a user agent SHOULD generate Host as the first header field
		// following the request-line.
		// @see https://www.rfc-editor.org/rfc/rfc7230#section-5.4
		if ($headerName === 'host') {
			$this->headers = [$name => []] + $this->headers;
		}

		$this->headers[$name] = $values;
		$this->headerNames[$headerName] = $name;
	}
}
