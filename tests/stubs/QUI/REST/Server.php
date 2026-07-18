<?php

namespace QUI\REST;

use Slim\App;

if (!class_exists(Server::class)) {
    class Server
    {
        private App $Slim;

        public function __construct()
        {
            $this->Slim = new App();
        }

        public function getSlim(): App
        {
            return $this->Slim;
        }
    }
}
