<?php

	namespace Fwaa\apiRouter;

	interface GETApiRoute
	{
		/**
		 * @param string $path
		 * @param array $params
		 * @return string
		 */
		public static function getResult(string $path, array $params): string;
	}