<?php
namespace Exinfinite\GSCA;

class Analysis {
    const _DIMEN_QUERY = "query";
    const _DIMEN_PAGE = "page";
    const _DIMEN_DEVICE = "device";
    public function __construct(\Exinfinite\GSCA\Agent $agent, $site_url) {
        $this->agent = $agent;
        $this->site_url = $site_url;
    }
    public function searchWords($start, $end) {
        $dimensions = [self::_DIMEN_QUERY, self::_DIMEN_PAGE];
        $rst = $this->agent->performance($this->site_url, [
            "startDate" => $start,
            "endDate" => $end,
            "dimensions" => $dimensions,
            "searchType" => "web",
            "aggregationType" => "auto",
        ]);
        if (array_key_exists('error', $rst)) {
            exit($rst['error']['message']);
        }
        $flatmap = function ($item) use ($dimensions) {
            $flat = [];
            foreach ($item['keys'] as $idx => $v) {
                $flat[$dimensions[$idx]] = $v;
            }
            return array_merge($flat, [
                "clicks" => $item['clicks'],
                "impressions" => $item['impressions'],
                "ctr" => number_format($item['ctr'], 2),
                "position" => number_format($item['position'], 2),
            ]);
        };
        return collect($rst['rows'])
            ->map($flatmap)
            ->sortByDesc('clicks')
            ->groupBy(function ($item, $key) {
                return $item[self::_DIMEN_QUERY];
            })
            ->toJson();
    }
}