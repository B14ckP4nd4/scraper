<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class torController extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tor:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $host , $port , $controller_port ;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->host = env("TOR_HOST");
        $this->port = env("TOR_PORT");
        $this->controller_port = env("TOR_CONTROLLER_PORT");
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (socket_connect($socket, $this->host, $this->controller_port))
        {
            socket_send($socket, "AUTHENTICATE\r\n", 100, MSG_EOF);

            $response = '';
            socket_recv($socket, $response, 20, MSG_PEEK);

            if (substr($response, 0, 3) == "250")
            {
                socket_send($socket, "SIGNAL NEWNYM\r\n", 100, MSG_EOF);
                socket_close($socket);

                self::torDisconnection();
                self::torConnection();
                return 0;
            }

            return 1;
        } else
            return 1;
        return 0;
    }
}
