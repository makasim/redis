<?php

namespace Enqueue\Redis;

class PhpRedis implements Redis
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var array
     */
    private $config;

    /**
     * @see https://github.com/phpredis/phpredis#parameters
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function lpush(string $key, string $value): int
    {
        try {
            return $this->redis->lPush($key, $value);
        } catch (\RedisException $e) {
            throw new ServerException('lpush command has failed', null, $e);
        }
    }

    public function brpop(array $keys, int $timeout): ?RedisResult
    {
        try {
            if ($result = $this->redis->brPop($keys, $timeout)) {
                return new RedisResult($result[0], $result[1]);
            }

            return null;
        } catch (\RedisException $e) {
            throw new ServerException('brpop command has failed', null, $e);
        }
    }

    public function rpop(string $key): ?RedisResult
    {
        try {
            if ($message = $this->redis->rPop($key)) {
                return new RedisResult($key, $message);
            }

            return null;
        } catch (\RedisException $e) {
            throw new ServerException('rpop command has failed', null, $e);
        }
    }

    public function connect(): void
    {
        if ($this->redis) {
            return;
        }

        $supportedSchemes = ['redis', 'tcp', 'unix'];
        if (false == in_array($this->config['scheme'], $supportedSchemes, true)) {
            throw new \LogicException(sprintf(
                'The given scheme protocol "%s" is not supported by php extension. It must be one of "%s"',
                $this->config['scheme'],
                implode('", "', $supportedSchemes)
            ));
        }

        $this->redis = new \Redis();

        $connectionMethod = $this->config['persistent'] ? 'pconnect' : 'connect';

        $result = call_user_func(
            [$this->redis, $connectionMethod],
            'unix' === $this->config['scheme'] ? $this->config['path'] : $this->config['host'],
            $this->config['port'],
            $this->config['timeout'],
            $this->config['persistent'] ? ($this->config['phpredis_persistent_id'] ?? null) : null,
            $this->config['phpredis_retry_interval'] ?? null,
            $this->config['read_write_timeout']
        );

        if (false == $result) {
            throw new ServerException('Failed to connect.');
        }

        if ($this->config['password']) {
            $this->redis->auth($this->config['password']);
        }

        if (null !== $this->config['database']) {
            $this->redis->select($this->config['database']);
        }
    }

    public function disconnect(): void
    {
        if ($this->redis) {
            $this->redis->close();
        }
    }

    public function del(string $key): void
    {
        $this->redis->del($key);
    }
}
