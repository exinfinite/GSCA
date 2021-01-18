<?php
namespace Exinfinite\GSCA;
use phpFastCache\CacheManager;

class Cache {
    protected $expire; //有效期限
    public function __construct($path = '') {
        CacheManager::setDefaultConfig([
            "path" => $path,
        ]);
        $this->expire = (new \DateTime(date('Y-m-d')))->modify('+1 day');
        $this->pool = CacheManager::getInstance('Sqlite');
    }
    /**
     * make cache key
     *
     * @param mixed $value can be serialized
     * @return string
     */
    public function mapKey($value, $prefix = '') {
        return $prefix . md5(serialize($value));
    }
    private function getItem($identity) {
        return $this->pool->getItem($identity);
    }

    private function getData($identity) {
        return $this->getItem($identity)->get();
    }
    private function force($identity, $data = null) {
        if (is_null($data)) {
            return false;
        }
        $item = $this->getItem($identity)->set($data)->expiresAt($this->expire);
        $this->pool->save($item);
        return true;
    }
    //overwrite defalut ttl
    public function setExpire(\DateTime $datetime) {
        $this->expire = $datetime;
        return $this;
    }
    //hit cache or write cache
    public function hit($identity, callable $data_source) {
        $item = $this->getItem($identity);
        if ($item->isHit()) {
            return $this->getData($identity);
        }
        $data = call_user_func($data_source);
        $this->force($identity, $data);
        return $data;
    }
}