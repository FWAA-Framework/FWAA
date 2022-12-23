<?php

	namespace FWAA;

	class route
	{
		public $path;
		public $controller;
		public $action;
		public $params;

		public function __construct($path, $controller, $action, $params)
		{
			$this->path = $path;
			$this->controller = $controller;
			$this->action = $action;
			$this->params = $params;
		}
	}