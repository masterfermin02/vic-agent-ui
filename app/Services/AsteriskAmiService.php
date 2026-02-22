<?php

namespace App\Services;

class AsteriskAmiService
{
    /** @var resource|false */
    private mixed $socket = false;

    public function connect(): void
    {
        $host = config('vicidial.ami.host');
        $port = config('vicidial.ami.port');

        $this->socket = fsockopen($host, $port, $errorCode, $errorMessage, 10);

        if ($this->socket === false) {
            throw new \RuntimeException("AMI connect failed [{$errorCode}]: {$errorMessage}");
        }

        // Read the greeting line
        fgets($this->socket, 1024);
    }

    public function login(): void
    {
        $user = config('vicidial.ami.user');
        $secret = config('vicidial.ami.secret');

        $this->sendAction([
            'Action' => 'Login',
            'Username' => $user,
            'Secret' => $secret,
        ]);

        $response = $this->readPacket();

        if (($response['Response'] ?? '') !== 'Success') {
            throw new \RuntimeException('AMI login failed: '.($response['Message'] ?? 'Unknown error'));
        }
    }

    public function listen(callable $handler): void
    {
        while (is_resource($this->socket) && ! feof($this->socket)) {
            $packet = $this->readPacket();

            if (! empty($packet)) {
                $handler($packet);
            }
        }
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            $this->sendAction(['Action' => 'Logoff']);
            fclose($this->socket);
            $this->socket = false;
        }
    }

    /** @param array<string, string> $action */
    private function sendAction(array $action): void
    {
        $payload = '';
        foreach ($action as $key => $value) {
            $payload .= "{$key}: {$value}\r\n";
        }
        $payload .= "\r\n";

        fwrite($this->socket, $payload);
    }

    /** @return array<string, string> */
    private function readPacket(): array
    {
        $packet = [];

        while (is_resource($this->socket) && ! feof($this->socket)) {
            $line = fgets($this->socket, 4096);

            if ($line === false || rtrim($line) === '') {
                break;
            }

            $parts = explode(': ', rtrim($line), 2);

            if (count($parts) === 2) {
                $packet[$parts[0]] = $parts[1];
            }
        }

        return $packet;
    }
}
