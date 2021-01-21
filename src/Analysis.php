<?php
namespace Exinfinite\GSCA;

class Analysis {
    const _DIMEN_QUERY = "query";
    const _DIMEN_PAGE = "page";
    const _DIMEN_DEVICE = "device";
    const _DIMEN_COUNTRY = "country";
    const _DIMEN_APPEARANCE = "searchAppearance";
    const _DIMEN_DATE = "date";
    const _IMPRESSIONS = "impressions";
    const _CTR = "ctr";
    const _CLICKS = "clicks";
    private $high_ctr_base = 0.1; //高點閱率基準:10%
    private $high_impress_base = 10; //高曝光率基準:10次
    private $low_ctr_base = 0.03; //低點閱率基準:3%
    private $cached = true;
    public function __construct(\Exinfinite\GSCA\Agent $agent, $site_url) {
        $this->agent = $agent;
        $this->site_url = $site_url;
        $this->cache = $agent->getCache();
    }
    public function setCache($bool = true) {
        $this->cached = (bool) $bool;
    }
    public function setCtrBase($num) {
        $this->high_ctr_base = (float) $num;
    }
    public function setImpressBase($num) {
        $this->high_impress_base = (int) $num;
    }
    public function getCtrBase() {
        return $this->high_ctr_base;
    }
    public function getImpressBase() {
        return $this->high_impress_base;
    }
    public function setLowCtrBase($num) {
        $this->low_ctr_base = (float) $num;
    }
    public function getLowCtrBase() {
        return $this->low_ctr_base;
    }
    protected function memoArray($var_name, $key, callable $data_source) {
        if (!is_string($var_name) || trim($var_name) == '' || !isset($key) || trim($key) == '') {
            return;
        }
        $this->{$var_name} = isset($this->{$var_name}) ? $this->{$var_name} : [];
        if (!array_key_exists($key, $this->{$var_name})) {
            $this->{$var_name}[$key] = call_user_func($data_source);
        }
        return $this->{$var_name}[$key];
    }
    protected function memoIdendity($var_name, callable $data_source) {
        return $this->memoArray('_idendity_memo', $var_name, $data_source);
    }
    //查詢欄位
    protected function dftDimensions($none_kw_include = false) {
        return $none_kw_include === true ? [self::_DIMEN_PAGE] : [self::_DIMEN_QUERY, self::_DIMEN_PAGE];
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function baseData(\DateTime $start, \DateTime $end, Array $dimensions = []) {
        if (!(count($dimensions) > 0)) {
            $dimensions = $this->dftDimensions();
        }
        sort($dimensions);
        return $this->memoIdendity('_base_memo' . md5(serialize($dimensions)), function () use ($start, $end, $dimensions) {
            $rst = $this->agent->performance($this->site_url, [
                "startDate" => $start->format('Y-m-d'),
                "endDate" => $end->format('Y-m-d'),
                "dimensions" => $dimensions,
                "searchType" => "web",
                "aggregationType" => "auto",
            ]);
            if (array_key_exists('error', $rst)) {
                return collect([]);
            }
            $flatmap = function ($item) use ($dimensions) {
                $flat = [];
                foreach ($item['keys'] as $idx => $v) {
                    $flat[$dimensions[$idx]] = $v;
                }
                return array_merge($flat, [
                    "clicks" => $item['clicks'],
                    "impressions" => $item['impressions'],
                    "ctr" => number_format($item['ctr'], 4),
                    "position" => number_format($item['position'], 2),
                ]);
            };
            return collect($rst['rows'])
                ->map($flatmap);
        });
    }
    private function useCache(callable $func, $key, $prefix = '') {
        if ($this->cached !== true) {
            return call_user_func($func);
        }
        return $this->cache->hit(
            $this->cache->mapKey($key, $prefix),
            $func
        );
    }
    /**
     * 由自然搜尋而來的總點擊數
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Boolean $none_kw_include 是否包含無關鍵字流量
     * @return Integer
     */
    public function totalClicks(\DateTime $start, \DateTime $end, $none_kw_include = false) {
        $dimensions = $this->dftDimensions($none_kw_include);
        return $this->baseData($start, $end, $dimensions)->sum(self::_CLICKS);
    }
    /**
     * 由自然搜尋而來的總曝光數
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Boolean $none_kw_include 是否包含無關鍵字流量
     * @return Integer
     */
    public function totalImpressions(\DateTime $start, \DateTime $end, $none_kw_include = false) {
        $dimensions = $this->dftDimensions($none_kw_include);
        return $this->baseData($start, $end, $dimensions)->sum(self::_IMPRESSIONS);
    }
    /**
     * 依關鍵字列出曝光頁成效
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Json
     */
    public function searchWords(\DateTime $start, \DateTime $end) {
        $cache_key_factors = [$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')];
        $cache_prefix = "words_";
        return $this->useCache(function () use ($start, $end) {
            return $this->baseData($start, $end)
                ->groupBy(function ($item) {
                    return $item[self::_DIMEN_QUERY];
                })
                ->sortByDesc(function ($items) {
                    return $items->sum(self::_CLICKS);
                })
                ->map(function ($items) {
                    return [
                        'data' => $items,
                        'meta' => [
                            strtolower(self::_CLICKS) => $items->sum(self::_CLICKS),
                        ],
                    ];
                })
                ->toJson();
        }, $cache_key_factors, $cache_prefix);
    }
    /**
     * 依曝光頁列出相關關鍵字
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Json
     */
    public function pages(\DateTime $start, \DateTime $end) {
        $cache_key_factors = [$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')];
        $cache_prefix = "pages_";
        return $this->useCache(function () use ($start, $end) {
            return $this->baseData($start, $end)
                ->groupBy(function ($item) {
                    return $item[self::_DIMEN_PAGE];
                })
                ->sortByDesc(function ($items) {
                    return $items->sum(self::_IMPRESSIONS);
                })
                ->map(function ($items) {
                    return [
                        'data' => $items,
                        'meta' => [
                            strtolower(self::_IMPRESSIONS) => $items->sum(self::_IMPRESSIONS),
                        ],
                    ];
                })
                ->toJson();
        }, $cache_key_factors, $cache_prefix);
    }
    /**
     * 最高曝光的頁面-關鍵字組
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function highImpressionPages(\DateTime $start, \DateTime $end, $take = 10) {
        $take = abs((int) $take);
        $cache_key_factors = [$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')];
        $cache_prefix = "high_impress_{$take}_";
        return $this->useCache(function () use ($start, $end, $take) {
            return $this->baseData($start, $end)
                ->sortByDesc(self::_IMPRESSIONS)
                ->take($take)
                ->toJson();
        }, $cache_key_factors, $cache_prefix);
    }
    /**
     * (高曝光-高點閱率)的頁面-關鍵字組
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function highCtrPages(\DateTime $start, \DateTime $end, $take = 10) {
        $take = abs((int) $take);
        $cache_key_factors = [$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d'), $this->high_ctr_base, $this->high_impress_base];
        $cache_prefix = "high_impress_ctr_{$take}_";
        return $this->useCache(function () use ($start, $end, $take) {
            return $this->baseData($start, $end)
                ->filter(function ($item) {
                    return $item[self::_CTR] >= $this->high_ctr_base && $item[self::_IMPRESSIONS] >= $this->high_impress_base;
                })
                ->sortByDesc(self::_CTR)
                ->take($take)
                ->toJson();
        }, $cache_key_factors, $cache_prefix);
    }
    /**
     * (高曝光-低點閱率)的頁面-關鍵字組
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function lowCtrPages(\DateTime $start, \DateTime $end, $take = 10) {
        $take = abs((int) $take);
        $cache_key_factors = [$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d'), $this->low_ctr_base, $this->high_impress_base];
        $cache_prefix = "high_impress_low_ctr_{$take}_";
        return $this->useCache(function () use ($start, $end, $take) {
            return $this->baseData($start, $end)
                ->filter(function ($item) {
                    return $item[self::_CTR] < $this->low_ctr_base && $item[self::_IMPRESSIONS] >= $this->high_impress_base;
                })
                ->sortByDesc(self::_IMPRESSIONS)
                ->take($take)
                ->toJson();
        }, $cache_key_factors, $cache_prefix);
    }
}