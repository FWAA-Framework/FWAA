<?php
	declare(strict_types=1);

	namespace Fwaa\apiRouter;

	/**
	 * An enum containing the HTTP methods
	 */
	enum HTTPMethod
	{
		case GET;
		case POST;
		case PUT;
		case DELETE;
		case PATCH;
		case HEAD;
		case OPTIONS;
		case TRACE;
		case CONNECT;
	}
