<?php
namespace Exinfinite\GSCA;

class Analysis {
    const _DIMEN_QUERY = "query";
    const _DIMEN_PAGE = "page";
    const _DIMEN_DEVICE = "device";
    const _IMPRESSIONS = "impressions";
    const _CTR = "ctr";
    const _CLICKS = "clicks";
    public function __construct(\Exinfinite\GSCA\Agent $agent, $site_url) {
        $this->agent = $agent;
        $this->site_url = $site_url;
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
    /**
     * @return \Illuminate\Support\Collection
     */
    protected function baseData(\DateTime $start, \DateTime $end) {
        return $this->memoIdendity('_base_memo', function () use ($start, $end) {
            $dimensions = [self::_DIMEN_QUERY, self::_DIMEN_PAGE];
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
     * 最高數量的項目
     *
     * @param String $target
     * @param \DateTime $start
     * @param \DateTime $end
     * @param Integer $take
     * @return Json
     */
    public function highest($target, \DateTime $start, \DateTime $end, $take = 5) {
        return $this->baseData($start, $end)
            ->sortByDesc($target)
            ->take((int) $take)
            ->toJson();
    }
    /**
     * 依關鍵字列出曝光頁成效
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Json
     */
    public function searchWords(\DateTime $start, \DateTime $end) {
        return $this->baseData($start, $end)
            ->sortByDesc('clicks')
            ->groupBy(function ($item) {
                return $item[self::_DIMEN_QUERY];
            })
            ->toJson();
    }
    /**
     * 依曝光頁列出相關關鍵字
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return Json
     */
    public function pages(\DateTime $start, \DateTime $end) {
        return $this->baseData($start, $end)
            ->sortByDesc('clicks')
            ->groupBy(function ($item) {
                return $item[self::_DIMEN_PAGE];
            })
            ->toJson();
    }
}