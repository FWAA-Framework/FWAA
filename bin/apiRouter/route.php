<?php

	namespace Fwaa\apiRouter;
	include_once "../../vendor/autoload.php";
	include_once "../../config.inc.php";
	class route
	{
		public static function executeApiRoute(string $path, HTTPMethod $HTTPMethod): false|string
		{
			global $FWAASettings;
			if(!isset($FWAASettings["API"]["Path"]))
			{
				return false;
			}
			$requested_path = str_replace($FWAASettings["API"]["Path"], '', $path);
			$requested_path = explode('?', $requested_path)[0];

			// Split the path into segments
			$path_segments = explode('/', $requested_path);

			// Initialize the arguments array
			$arguments = array();

			// Check if the requested path exists
			$api_path = $FWAASettings["API"]["Path"];

			$num_segments = count($path_segments);
			for ($i = 1; $i < $num_segments; $i++) {
				$segment = $path_segments[$i];
				if (preg_match('/\((.+)\)/', $segment, $matches)) {
					// If the segment is in parentheses, add it to the arguments array
					$arguments[$matches[1]] = $path_segments[$i + 1];
					$i++;
				} else {
					// Otherwise, add the segment to the API path
					$api_path .= '/' . $segment;
				}
			}

			$api_path = preg_replace('/\(.+\)/', '*', $api_path);

			$dir = dirname($api_path);
			$files = glob("$dir/*.php");
			$found = false;
			foreach ($files as $file) {
				if (preg_match("/GET.php$/", $file)) {
					$api_path = $file;
					$found = true;
					break;
				}
			}

			switch ($HTTPMethod) {
				case HTTPMethod::GET:
					ob_start();
					include $api_path . '/GET.php';
					$GET::getResult($path, $arguments);
					return ob_get_clean();
			}

			if (!file_exists($api_path)) {
				return false;
			}
		}
	}

	route::executeApiRoute("api/user/a", HTTPMethod::GET);