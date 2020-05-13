<?php
use Github\Client;
use Github\ResultPager;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/globals.php';

if (!file_exists('token.txt')) {
    exit("Token file not found." . PHP_EOL);
}
$token = file_get_contents('token.txt');
$report_directory = __DIR__ . '/reports/';

$client = new Client();
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);
$paginator = new ResultPager($client);

if (getenv('START_DATE') === false) {
    throw new Exception("START_DATE var is mandatory.");
}

//start_date stuff
$start_date = strtotime(getenv('START_DATE'));
if (date('Y-m-d', $start_date) != getenv('START_DATE')) {
    throw new Exception("Unrecognizable START_DATE format : ".getenv('START_DATE').PHP_EOL);
}

//end_date stuff
if (getenv('END_DATE') === false) {
    $end_date = strtotime("+28 day", $start_date);
    echo "Notice: END_DATE var not found, calculating to " . date('Y-m-d', $end_date) . PHP_EOL;
} else {
    $end_date = strtotime(getenv('END_DATE'));
    if (date('Y-m-d', $end_date) != getenv('END_DATE')) {
        throw new Exception("Unrecognizable END_DATE format : ".getenv('END_DATE').PHP_EOL);
    }
}
$start_date_formatted = date('Y-m-d', $start_date);
$end_date_formatted = date('Y-m-d', $end_date);

//report file
$report_filename = 'Report-'.$start_date_formatted.'-'.$end_date_formatted.'.md';
$report_path = $report_directory.$report_filename;

//get all issues between these dates
$parameters = [
    'type:issue created:'.$start_date_formatted.'..'.$end_date_formatted.' sort:created-desc repo:prestashop/prestashop'
];
$issues = $paginator->fetchAll($client->api('search'), 'issues', $parameters);

echo "Found " . count($issues) . " issues !" . PHP_EOL;

$results = [
    'open' => 0,
    'closed' => 0,
    'regressions_TE' => 0,
    'regressions' => [],
    'duplicates' => []
];

if (count($issues) > 0) {
    foreach($issues as $issue) {
        if ($issue['state'] == 'open') {
            $results['open'] ++;
        } else {
            $results['closed'] ++;
        }
        $reg = false;
        $te = false;
        foreach($issue['labels'] as $label) {
            if ($label['name'] == 'Regression') {
                $reg = true;
            }
            if ($label['name'] == 'Detected by TE') {
                $te = true;
            }
            if ($label['name'] == 'Duplicate') {
                $results['duplicates'][] = $issue;
            }
        }
        if ($reg) {
            $results['regressions'][] = $issue;
        }
        if ($te) {
            $results['regressions_TE'] ++;
        }
    }
}

//let's get all source regressions
echo "Retrieving duplicates original data...".PHP_EOL;
$duplicates = $results['duplicates'];
$pattern = '/Duplicates? of #(\d+)/i';
$original_issues = [];
foreach($duplicates as $duplicate) {
    //get all comments
    $comments = $client->api('issue')->comments()->all('PrestaShop', 'PrestaShop', $duplicate['number']);
    foreach($comments as $comment) {
        preg_match($pattern, $comment['body'], $matches);
        if (isset($matches[1])) {
            //we got the original issue number
            $original_issue_number = $matches[1];
            $original_issue = $client->api('issue')->show('PrestaShop', 'PrestaShop', $original_issue_number);

            if (!isset($original_issues[$original_issue_number])) {
                $original_issues[$original_issue_number] = $original_issue;
            }
            $original_issues[$original_issue_number]['duplicates'][] = $duplicate;
            //no need to continue looking into comments
            break;
        }
    }
}
$results['duplicates'] = $original_issues;

echo "Writing report...".PHP_EOL;

$template_content = file_get_contents('template.md');
$replacement_data = [
    'start-date' => $start_date_formatted,
    'end-date' => $end_date_formatted,
    'period' => ceil(($end_date - $start_date)/(24*3600))+1,
    'issues-created' => count($issues),
    'issues-open' => $results['open'],
    'issues-closed' => $results['closed'],
    'issues-duplicates' => count($results['duplicates']),
    'issues-duplicates-percentage' => round((count($results['duplicates']) / count($issues) * 100), 1),
    'issues-regressions' => count($results['regressions']),
    'issues-regressions-percentage' => round((count($results['regressions']) / count($issues) * 100), 1),
    'issues-detected-by-te' => $results['regressions_TE'],
    'issues-detected-by-te-percentage' => round(($results['regressions_TE'] / count($issues) * 100), 1),
    'creation-date' => date('Y-m-d H:i')
];
$duplicate_table = '';
foreach($results['duplicates'] as $origin) {
    $priority = '-';
    $bo_fo = '-';
    $state = '-';
    foreach($origin['labels'] as $label) {
        if (in_array($label['name'], ['Trivial', 'Minor', 'Major', 'Critical'])) {
            $priority = $label['name'];
        }
        if ($label['name'] == 'Improvement') {
            $priority = 'Improvement';
        }
        if ($label['name'] == 'FO') {
            $bo_fo = 'FO';
        }
        if ($label['name'] == 'BO') {
            $bo_fo = 'BO';
        }
        if (in_array($label['name'], ['Refactoring', 'TBS', 'TBR'])) {
            $state = $label['name'];
        }
    }
    if ($origin['state'] == 'closed') {
        $state = 'Closed';
    }
    if ($state == '') {
        $state = '**To do**';
    }
    foreach($origin['duplicates'] as $duplicate) {
        $duplicate_data = [
            '['.$duplicate['number'].']('.$duplicate['html_url'].')',
            excerpt($duplicate['title']),
            date('Y-m-d', strtotime($duplicate['created_at'])),
            $bo_fo,
            '['.$origin['number'].']('.$origin['html_url'].')',
            $state,
            $priority,
        ];
        $duplicate_table .= implode('|', $duplicate_data).PHP_EOL;
    }
}

$replacement_data['duplicate-table'] = $duplicate_table;

foreach($replacement_data as $k => $v) {
    $template_content = str_replace("%$k%", $v, $template_content);
}

$handle = fopen($report_path, 'w');
fwrite($handle, $template_content);
echo "Report $report_filename written.".PHP_EOL;

function excerpt($string, $length=50) {
    if (mb_strlen($string) > $length) {
        return mb_substr($string, 0, $length).'...';
    }
    return $string;
}
