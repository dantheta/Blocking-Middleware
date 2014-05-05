<?php

$REPORTS = array(
	'networks' => array(
		'title' => 'Networks',
		'description' => 'List of networks for which we have probe results',
		'sql' => 'select network_name, max(created) as last_result, count(*) as result_count 
			from results group by network_name order by network_name',
	),
	'result_summary' => array(
		'title' => "Summary of results",
		'description' => "Totals for each result status, grouped by network",
		'sql' => "select network_name,
			sum(case when status = 'ok' then 1 else 0 end) ok,
			sum(case when status = 'error' then 1 else 0 end) error,
			sum(case when status = 'dnsfail' then 1 else 0 end) dnsfail,
			sum(case when status = 'timeout' then 1 else 0 end) timeout,
			sum(case when status = 'blocked' then 1 else 0 end) blocked
			from results group by network_name order by network_name",
	),
	'alexa_url_status' => array(
		'title' => 'Alexa 10k URL Status',
		'description' => 'Status of URLs',
		'sql' => "select url, network_name, created probed_at, status
			from results inner join urls using(urlid)
			where source = 'alexa'
			order by url, network_name",
		'paged' => true,
	),
	'all_blocked' => array(
		'title' => 'All blocked sites',
		'description' => "List of all blocked sites, most recent first",
		'sql' => "select url, network_name, created probed_at, status
		from results inner join urls using(urlid)
		where status = 'blocked' order by created desc",
	),
);
