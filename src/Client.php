<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\WebSocketClient;

use Hyperf\WebSocketClient\Exception\ConnectException;
use Psr\Http\Message\UriInterface;
use Swoole\Coroutine;
use Swoole\WebSocket\Frame as SwFrame;

class Client
{
    /**
     * @var UriInterface
     */
    protected $uri;

    /**
     * @var Coroutine\Http\Client
     */
    protected $client;

    public function __construct(UriInterface $uri, bool $autoUpgrade = false)
    {
        $this->uri = $uri;
        $host = $uri->getHost();
        $ssl = $uri->getScheme() === 'wss';
        if ($ssl) {
            $port = $uri->getPort() ?: 443;
        } else {
            $port = $uri->getPort() ?: 80;
        }

        $this->client = new Coroutine\Http\Client($host, $port, $ssl);

        if ($autoUpgrade) {
            $this->upgrade();
        }
    }

    public function upgrade(?string $path = null)
    {
        if (null === $path) {
            parse_str($this->uri->getQuery(), $query);

            $query = http_build_query($query);
            $path = $this->uri->getPath() ?: '/';
            $path = empty($query) ? $path : $path . '?' . $query;
        }
        $ret = $this->client->upgrade($path);
        if (!$ret) {
            $errCode = $this->client->errCode;
            throw new ConnectException(sprintf('Websocket upgrade failed by [%s] [%s].', $errCode, swoole_strerror($errCode)));
        }
    }

    public function set(array $setting)
    {
        $this->client->set($setting);
    }

    public function setHeaders(array $headers)
    {
        $this->client->setHeaders($headers);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function recv(float $timeout = -1)
    {
        $ret = $this->client->recv($timeout);
        if ($ret instanceof SwFrame) {
            return new Frame($ret);
        }

        return $ret;
    }

    public function push(string $data, int $opcode = WEBSOCKET_OPCODE_TEXT, bool $finish = true): bool
    {
        return $this->client->push($data, $opcode, $finish);
    }

    public function close(): bool
    {
        return $this->client->close();
    }
}
