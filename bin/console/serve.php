<?php
	namespace Fwaa\console;

	use JetBrains\PhpStorm\NoReturn;
	use Socket;

	/**
	 * This class gets called from the FWAA console tool and starts the FWAA server
	 */
	class serve
	{
		/**
		 * The variable holding the socket server
		 * @var false|Socket
		 */
		private readonly false|Socket $server;

		/**
		 * The variable holding the current socket client
		 * @var false|Socket
		 */
		private false|Socket $client;

		/**
		 * @param $address string The address to listen on
		 * @param $port int The port to listen on
		 */
		#[NoReturn]
		function __construct(string $address, int $port)
		{
			// Create a server socket
			$this->server = @socket_create(AF_INET, SOCK_STREAM, 0);

			$this->processSocketEstablishingError($this->server);

			// Bind the socket to the address and port
			$this->processSocketEstablishingError(@socket_bind($this->server, $address, $port));

			// Start listening for incoming connections
			$this->processSocketEstablishingError(@socket_listen($this->server));

			echo "Server started on $address:$port\n";

			while(true){
				// Accept an incoming connection
				$this->client = socket_accept($this->server);

				// Read the data from the client
				socket_recv($this->client, $request, 1024,0);

				if($request == null){
					socket_close($this->client);
					continue;
				}

				// Test if the HTTP version is valid
				$HTTPVersion = $this->getHTTPVersion($request);
				if($HTTPVersion == null){
					continue;
				}
				if(!$this->isHTTPVersionSupported($HTTPVersion)){
					socket_write($this->client, "HTTP/1.1 505 HTTP Version Not Supported");
					socket_close($this->client);
					continue;
				}

				// Parse the request using the parse_url function
				$resource = $this->getRequestedLocation($request);

				// Strip search params
				$resource = explode("?", $resource)[0];
				if($resource == "/" || $resource == null){
					$resource = "/index.php";
				}

				$pathToPublicResources = "src/public";

				// Get the file extension and corresponding mime type
				$extension = pathinfo($pathToPublicResources.$resource, PATHINFO_EXTENSION);
				$mime = $this->getMimeTypeFromExtension($extension);

				$isPHP = false;

				if($mime == "application/php"){
					$mime = "text/html";
					$isPHP = true;
				}

				$response = "HTTP/1.1 200 OK\r\n";
				$response .= "Content-Type: ".$mime."\r\n";
				$response .= "Connection: keep-alive\r\n";

				socket_write($this->client, $response);

				if (preg_match('/Content-Length: (\d+)/', $request, $matches)) {
					if($matches[1] > 0){
						$requestBody = preg_match('/\r\n\r\n(.*)/s', $request, $matches) ? $matches[1] : "";
					}else{
						$requestBody = "";
					}
				}

				if(file_exists($pathToPublicResources.$resource)){
					if ($isPHP){
						// Run the PHP file and get the output

						if($this->getRequestMethod($request) == "GET"){
							$_GET = $this->getSearchParameters($request);
						}else if($this->getRequestMethod($request) == "POST"){
							$_POST = $this->getPostParameters($requestBody ?? "");
						}

						ob_start();
						include $pathToPublicResources.$resource;
						$fileContents  = ob_get_clean();
					}else{
						$fileContents = file_get_contents($pathToPublicResources.$resource);
					}

					$response = $fileContents;
					socket_write($this->client, "Content-Length: ".strlen($response)."\r\n\r\n");
				}else{
					$response = "HTTP/1.1 404 Not Found\r\n";
					$response .= "Content-Type: text/text\r\n";
					$response .= "Connection: close\r\n\r\n";
					$response .= "404 Not Found\nThe requested resource was not found on this server.";
				}


				// Send a response back to the client
				socket_write($this->client, $response);

				// Close the client connection
				socket_close($this->client);
			}
		}

		/**
		 * Returns the requested location from the request
		 * @param string $HTTPRequest The full HTTP request
		 * @return string
		 */
		function getRequestedLocation(string $HTTPRequest): string{
			preg_match("/[A-Z]{3,}\s(\/.+?)\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			return $matches[1]??"/";
		}

		/**
		 * Returns the get parameters as an array
		 * The request must be a GET request
		 * @param string $HTTPRequest The full HTTP request
		 * @return array
		 */
		private function getSearchParameters(string $HTTPRequest): array{
			preg_match("/\?(.+?)\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			if(!isset($matches[1])){
				return [];
			}
			$parameters = explode("&", $matches[1]);
			$parametersArray = [];
			foreach($parameters as $parameter){
				$parameter = explode("=", $parameter);
				$parametersArray[$parameter[0]] = $parameter[1];
			}
			return $parametersArray;
		}

		/**
		 * Returns the post parameters as an array
		 * The request must be a POST request
		 * @param string $HTTPBody The body of the HTTP request
		 * @return array
		 */
		private function getPostParameters(string $HTTPBody): array{
			$HTTPBody = str_replace("\r", "", $HTTPBody);
			$HTTPBody = str_replace("\n", "", $HTTPBody);
			$params = array();
			$pairs = explode('&', $HTTPBody);
			foreach ($pairs as $pair) {
				list($key, $value) = explode('=', $pair);
				$params[urldecode($key)] = urldecode($value);
			}
			return $params;
		}

		/**
		 * Returns the request method of the HTTP request
		 * @param string $HTTPRequest The full HTTP request
		 * @return string|null
		 */
		private function getRequestMethod(string $HTTPRequest): string|null{
			preg_match("/([A-Z]{3,})\s.+?\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			return $matches[1];
		}

		/**
		 * Returns the HTTP version of the request
		 * @param string $HTTPRequest The full HTTP request
		 * @return string|null
		 */
		private function getHTTPVersion(string $HTTPRequest): string|null{
			preg_match("/[A-Z]{3,}\s.+?\sHTTP\/(\d\.\d)/", $HTTPRequest, $matches);
			return $matches[1];
		}

		/**
		 * Returns a mime type corresponding to the given file extension
		 * If the extension is not found, it returns "text/plain"
		 * @param string $extension The file extension without the dot
		 * @example getMimeTypeFromExtension("html") // returns "text/html"
		 * @return string The mime type
		 */
		private function getMimeTypeFromExtension(string $extension): string{
			return match ($extension){
				"html", "htm" => "text/html",
				"css" => "text/css",
				"js" => "application/javascript",
				"png" => "image/png",
				"jpg", "jpeg" => "image/jpeg",
				"gif" => "image/gif",
				"svg" => "image/svg+xml",
				"ico" => "image/x-icon",
				"json" => "application/json",
				"xml" => "application/xml",
				"php", "php3", "php4", "php5", "php7", "inc" => "application/php",
				default => "text/plain"
			};
		}

		/**
		 * @param mixed $errorNotPresent If this variable is false an error will be thrown
		 * @return void
		 */
		private function processSocketEstablishingError(mixed $errorNotPresent): void
		{
			if($errorNotPresent === false){
				$errorCode = socket_last_error();
				$errorMessage = socket_strerror($errorCode);

				die("Couldn't bind socket: [$errorCode] $errorMessage");
			}
		}

		/**
		 * @param string $HTTPVersion A string containing the HTTP version
		 * @example isHTTPVersionValid("1.1") // true
		 * @return bool
		 */
		private function isHTTPVersionSupported(string $HTTPVersion): bool{
			return $HTTPVersion == "1.1" || $HTTPVersion == "1.0";
		}
	}