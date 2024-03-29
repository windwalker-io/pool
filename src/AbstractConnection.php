<?php

declare(strict_types=1);

namespace Windwalker\Pool;

use Windwalker\Pool\Exception\ConnectionPoolException;

/**
 * The AbstractConnection class.
 */
abstract class AbstractConnection implements ConnectionInterface
{
    protected int $id = 0;

    protected bool $active = false;

    protected int $lastTime = 0;

    /**
     * The connection pool instance must store in WeakReference to prevent circular reference
     * and memory leak.
     *
     * @var \WeakReference<AbstractPool>|null
     */
    protected ?\WeakReference $pool = null;

    /**
     * Set this to TRUE, if a connection not released back to pool but destructed, will throw an exception.
     *
     * @var bool
     */
    public bool $leakProtect = true;

    /**
     * @inheritDoc
     */
    public function setPool(?PoolInterface $pool): void
    {
        $this->pool = $pool ? \WeakReference::create($pool) : null;

        if ($pool !== null) {
            $this->id = $pool->getSerial();
        }
    }

    /**
     * @inheritDoc
     */
    public function reconnect(): mixed
    {
        $this->disconnect();

        return $this->connect();
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function release(bool $force = false): void
    {
        if ($this->pool === null) {
            throw new ConnectionPoolException('No assigned pool of this connection.');
        }

        if ($this->active || $force) {
            $this->pool?->get()?->release($this);
        }
    }

    /**
     * @inheritDoc
     */
    public function getLastTime(): int
    {
        return $this->lastTime;
    }

    /**
     * @inheritDoc
     */
    public function updateLastTime(): void
    {
        $this->lastTime = time();
    }

    /**
     * @inheritDoc
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * Is connection active.
     *
     * @return  bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    public function __destruct()
    {
        if ($this->active && $this->pool && $this->leakProtect) {
            trigger_error(
                sprintf(
                    'Connection ID: %s in pool: %s was not released but destruct.',
                    $this->getId(),
                    $this->pool?->get()::class
                ),
                E_USER_WARNING
            );
        }
    }
}
