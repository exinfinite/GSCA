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
    public function __construct(\Exinfinite\GSCA\Agent $agent, $site_url) {
        $this->agent = $agent;
        $this->site_url = $site_url;
        $this->cache = $agent->getCache();
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
    protected function baseData(\DateTime $start, \DateTime $end, Array $dimensions = []) {
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
        return $this->cache->hit(
            $this->cache->mapKey([$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')], 'words_'),
            function () use ($start, $end) {
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
            });
    }
    /**
     * 依曝光頁列出相關關鍵字
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Json
     */
    public function pages(\DateTime $start, \DateTime $end) {
        return $this->cache->hit(
            $this->cache->mapKey([$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')], 'pages_'),
            function () use ($start, $end) {
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
            });
    }
    /**
     * 最高曝光的頁面-關鍵字組
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function hightImpressionPages(\DateTime $start, \DateTime $end, $take = 10) {
        $take = abs((int) $take);
        return $this->cache->hit(
            $this->cache->mapKey([$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')], "high_impress_{$take}_"),
            function () use ($start, $end, $take) {
                return $this->baseData($start, $end)
                    ->sortByDesc('impressions')
                    ->take($take)
                    ->toJson();
            });
    }
    /**
     * 最高點閱率的頁面-關鍵字組
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function hightCtrPages(\DateTime $start, \DateTime $end, $take = 10) {
        $take = abs((int) $take);
        return $this->cache->hit(
            $this->cache->mapKey([$this->site_url, $start->format('Y-m-d'), $end->format('Y-m-d')], "high_ctr_{$take}_"),
            function () use ($start, $end, $take) {
                return $this->baseData($start, $end)
                    ->sortByDesc('ctr')
                    ->take($take)
                    ->toJson();
            });
    }
}