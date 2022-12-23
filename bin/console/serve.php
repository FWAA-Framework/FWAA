<?php

	// Certain variables in this script use the prefix "FWAA_". This is to avoid conflicts with other variables in the global scope when including PHP files.

	namespace Fwaa\console;

	use JetBrains\PhpStorm\NoReturn;

	class serve
	{
		#[NoReturn]
		function __construct($address, $port)
		{
			// Create a server socket
			$FWAA_Server = @socket_create(AF_INET, SOCK_STREAM, 0);

			$this->processSocketEstablishingError($FWAA_Server);

			// Bind the socket to the address and port
			$this->processSocketEstablishingError(@socket_bind($FWAA_Server, $address, $port));

			// Start listening for incoming connections
			$this->processSocketEstablishingError(@socket_listen($FWAA_Server));

			echo "Server started on $address:$port\n";

			// Loop indefinitely
			while(true){
				// Accept an incoming connection
				$FWAA_Client = socket_accept($FWAA_Server);

				// Read the data from the client
				socket_recv($FWAA_Client, $request, 1024,0);

				if($request == null){
					socket_close($FWAA_Client);
					continue;
				}

				if($this->getHTTPVersion($request) != "1.0" && $this->getHTTPVersion($request) != "1.1"){
					socket_write($FWAA_Client, "HTTP/1.0 505 HTTP Version Not Supported");
					socket_close($FWAA_Client);
					continue;
				}


				// Parse the request using the parse_url function
				$resource = $this->getRequestedLocation($request);

				//strip search params
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

				socket_write($FWAA_Client, $response);

				if (preg_match('/Content-Length: (\d+)/', $request, $matches)) {
					$requestBody = preg_match('/\r\n\r\n(.*)/s', $request, $matches) ? $matches[1] : '';
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
					socket_write($FWAA_Client, "Content-Length: ".strlen($response)."\r\n\r\n");
				}else{
					$response = "HTTP/1.1 404 Not Found\r\n";
					$response .= "Content-Type: text/text\r\n";
					$response .= "Connection: close\r\n\r\n";
					$response .= "404 Not Found\nThe requested resource was not found on this server.";
				}


				// Send a response back to the client
				socket_write($FWAA_Client, $response);

				// Close the client connection
				socket_close($FWAA_Client);
			}
		}

		function getRequestedLocation(string $HTTPRequest): string|null{
			preg_match("/[A-Z]{3,}\s(\/.+?)\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			return $matches[1];
		}

		private function getSearchParameters(string $HTTPRequest): array{
			preg_match("/\?(.+?)\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			$parameters = explode("&", $matches[1]);
			$parametersArray = [];
			foreach($parameters as $parameter){
				$parameter = explode("=", $parameter);
				$parametersArray[$parameter[0]] = $parameter[1];
			}
			return $parametersArray;
		}

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

		private function getRequestMethod(string $HTTPRequest): string|null{
			preg_match("/([A-Z]{3,})\s\/.+?\sHTTP\/\d\.\d/", $HTTPRequest, $matches);
			return $matches[1];
		}

		private function getHTTPVersion(string $HTTPRequest): string|null{
			preg_match("/[A-Z]{3,}\s\/.+?\sHTTP\/(\d\.\d)/", $HTTPRequest, $matches);
			return $matches[1];
		}

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

		private function processSocketEstablishingError(mixed $errorNotPresent): void
		{
			if($errorNotPresent === false){
				$errorCode = socket_last_error();
				$errorMessage = socket_strerror($errorCode);

				die("Couldn't bind socket: [$errorCode] $errorMessage");
			}
		}
	}