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
//需先至google cloud platform申請服務帳戶,並將其加入google search console的資源中
$agent = new \Exinfinite\GSCA\Agent("path of credentials.json", "path of cache dir");
$analysis = new Analysis($agent, "site_url");
$start_date = new \DateTime('first day of this month');
$end_date = new \DateTime('last day of this month');
```

### get original data

```php
$analysis->baseData($start_date, $end_date);
```

### 成效分析

```php
//group by keyword
$analysis->searchWords($start_date, $end_date);

//group by page
$analysis->pages($start_date, $end_date);

//最高曝光的頁面-關鍵字組
$analysis->highImpressionPages($start_date, $end_date, $take = 10);

//(高曝光-高點閱率)的頁面-關鍵字組
$analysis->highCtrPages($start_date, $end_date, $take = 10);

//(高曝光-低點閱率)的頁面-關鍵字組
$analysis->lowCtrPages($start_date, $end_date, $take = 10);
```
