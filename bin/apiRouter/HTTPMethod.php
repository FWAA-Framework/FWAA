<?php

	namespace Fwaa\apiRouter;

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
