<?php

namespace Satispay\Prestashop\Classes\Lock;

if (!defined('_PS_VERSION_')) {
    exit;
}

// Prestashop
use \Db;
use \PrestaShopDatabaseException;
use \PrestaShopException;

//
use Carbon\Carbon;

/**
 * Class for managing atomic locks in PrestaShop.
 *
 * This class allows for acquiring, releasing, and managing locks to prevent
 * race conditions, inspired by the Laravel atomic locks implementation.
 *
 * Locks are stored in the database to ensure consistency across distributed
 * systems and multiple processes. It includes support for blocking lock
 * acquisition, with optional callbacks for executing code within the lock's context.
 *
 * @see https://laravel.com/docs/11.x/cache#atomic-locks
 */
class Lock
{
    /**
     * The name of the lock.
     *
     * @var string
     */
    protected $name;

    /**
     * The number of seconds the lock should be maintained.
     *
     * @var int
     */
    protected $seconds;

    /**
     * The number of milliseconds to wait before re-attempting to acquire a lock while blocking.
     *
     * @var int
     */
    protected $sleepMilliseconds = 250;

    /**
     * Prestashop database instance.
     * 
     * @var Db
     */
    protected $db;

    /**
     * The name of the database table for storing locks
     * 
     * @var string 
     */
    protected $tableName;

    /**
     * AtomicLock constructor.
     *
     * @param string $name The name of the lock
     * @param int $seconds The expiration time for the lock (default: 10 seconds)
     */
    public function __construct($name, $seconds = 10)
    {
        // Lock system
        $this->name = pSQL($name);
        $this->seconds = $seconds;

        // Prstashop
        $this->db = Db::getInstance();
        $this->tableName = pSQL(_DB_PREFIX_ . 'satispay_locks');
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire()
    {
        $now = Carbon::now();

        $currentTime = pSQL(
            $now
                ->clone()
                ->format('Y-m-d H:i:s.u')
        );

        $expiresAt = pSQL(
            $now
                ->clone()
                ->addSeconds($this->seconds)
                ->format('Y-m-d H:i:s.u')
        );

        try {
            $this->db->execute(
                "INSERT INTO `{$this->tableName}` (`name`, `expires_at`)
                VALUES ('{$this->name}', '{$expiresAt}')"
            );
        
            $acquired = true;
        } catch (PrestaShopException | PrestaShopDatabaseException) {
            $sql = "UPDATE `{$this->tableName}` SET `expires_at` = '{$expiresAt}' 
                    WHERE `name` = '{$this->name}' AND `expires_at` <= '{$currentTime}'";

            $this->db->execute($sql);

            $acquired = $this->db->Affected_Rows() >= 1;
        }

        // clean up expired locks every once in a while
        if (rand(0, 100) < 25) {
            $this->db->execute(
                "DELETE FROM `{$this->tableName}` WHERE `expires_at` <= '{$currentTime}'"
            );
        }
        
        return $acquired;
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release()
    {
        return
            $this
                ->db
                ->execute(
                    "DELETE FROM `{$this->tableName}` WHERE `name` = '{$this->name}'"
                );
    }

    /**
     * Attempt to acquire the lock.
     *
     * @param callable|null $callback
     * 
     * @return mixed
     */
    public function get($callback = null)
    {
        $result = $this->acquire();

        if ($result && is_callable($callback)) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return $result;
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param int $seconds
     * @param callable|null $callback
     * 
     * @return mixed
     *
     * @throws LockTimeoutException
     */
    public function block($seconds, $callback = null)
    {
        $starting = Carbon::now()->getPreciseTimestamp(3) / 1000;

        $milliseconds = $seconds * 1000;

        while (!$this->acquire()) {
            $now = Carbon::now()->getPreciseTimestamp(3) / 1000;

            if (($now + $this->sleepMilliseconds - $milliseconds) >= $starting) {
                throw new LockTimeoutException;
            }

            usleep($this->sleepMilliseconds * 1000);
        }

        if (is_callable($callback)) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return true;
    }
}