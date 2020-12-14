# Google search console agent

![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/exinfinite/gsca)
![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/exinfinite/GSCA)
![Packagist Version](https://img.shields.io/packagist/v/exinfinite/gsca)
![Packagist Downloads](https://img.shields.io/packagist/dt/exinfinite/gsca)
![GitHub](https://img.shields.io/github/license/exinfinite/GSCA)

## 安裝

```php
composer require exinfinite/gsca
```

## 使用

### 初始化

```php
$agent = new \Exinfinite\GSCA\Agent("path of credentials.json", "path of cache dir");
```

### 關鍵字成效分析

```php
$analysis = new Analysis($agent, "site_url of google search console");
$first = new \DateTime('first day of this month');
$last = new \DateTime('last day of this month');
$result = $analysis->searchWords($first->format('Y-m-d'), $last->format('Y-m-d'));
```

### 原始資料

```php
$result = $agent->performance("site_url of google search console", [
    "startDate" => new \DateTime('first day of this month'),
    "endDate" => new \DateTime('last day of this month'),
    "dimensions" => ['query'],
    "searchType" => "web",
    "aggregationType" => "auto"
]);
```