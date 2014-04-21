#!/usr/bin/php
<?php
namespace MyApp;

use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Server;

require 'vendor/autoload.php';

class SocketServer {

    protected $socketNumber   = 1337;

    protected $processes      = 0;

    protected $totalProcesses = 0;

    public function __construct(
        LoopInterface      $loop,
        Server             $socket,
        \React\Http\Server $http
    ) {

        // Make sure garbage collection is there, and enabled
        if (!function_exists('gc_enable')) {
            die("Please use PHP 5.3+ with Garbage Collection enabled");
        }

        $this->loop   = $loop;
        $this->socket = $socket;
        $this->http   = $http;
    }

    public function app($request, $response) {

        $free = shell_exec("free -mt | grep Mem | awk '{print $4}'");

        if($free > 256) {

        $data = NULL;

        $this->totalProcesses++;

        $process = new Process('php childProcess.php');

        $process->on('exit', function($exitCode, $termSignal) use ($response, &$data) {

            $headers = array('Content-Type' => 'text/plain');
            $response->writeHead(200, $headers);
            $response->end($data);

            $this->processes--;
        });

        $process->start($this->loop);

        $this->processes++;

        $process->stdout->on('data', function($output) use (&$data) {

            $data += $output;
        });
        } else {

            $headers = array('Content-Type' => 'text/plain');
            $response->writeHead(500, $headers);
            $response->end();
        }
    }

    public function run() {

        gc_enable();

        $this->http->on('request', array($this, 'app'));

        $this->socket->listen($this->socketNumber);

        echo date('H:i:s') . " : Server running at http://127.0.0.1:1337\n\n";

        $this->loop->addPeriodicTimer(1, function ($timer) {

            $stuff = sprintf("%s : Mem: %s, Processes: %d, %d ",
                date('H:i:s'),
                number_format(memory_get_usage()),
                $this->processes,
                $this->totalProcesses
            );

            fwrite(
                STDOUT,
                "\r\033[0K" . $stuff
            );
        });

        $this->loop->run();
    }

    public function setSocket($socket) {

        $this->socketNumber = $socket;
    }

    public function handleSigterm() {

        $this->socket->shutdown();
        $this->loop->stop();
    }
}

$loop   = Factory::create();
$socket = new Server($loop);
$http   = new \React\Http\Server($socket);

$server = new SocketServer($loop, $socket, $http);

$server->run();