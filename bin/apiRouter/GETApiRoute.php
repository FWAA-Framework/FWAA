<?php
	declare(strict_types=1);

	namespace Fwaa\apiRouter;

	/**
	 * An interface for API routes that use the GET HTTP method.
	 */
	interface GETApiRoute
	{
		/**
		 * @param string $path
		 * @param array $params
		 * @return string
		 */
		public static function getResult(string $path, array $params): string;
	}