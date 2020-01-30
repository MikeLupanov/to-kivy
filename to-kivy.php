<?php
# GAE-hosted webproxy server. V.2
# License: CC0 1.0

$host        = "kivy.org"; # translated host
$host_scheme = "http";          # protocol type: "http" or "https"

# banning bots
if (strpos($_SERVER['HTTP_USER_AGENT'], 'http://')) die();

# decode name of subdomain
$proxy_host = $_SERVER['DEFAULT_VERSION_HOSTNAME'];
$request    = rawurldecode($_SERVER['REQUEST_URI']);

$a = explode('.', $_SERVER['HTTP_HOST']);
$l = count($a) - 4;

if ($l >=0 && preg_match('~^\d+$~', $a[$l])) {
	
	$proxy_host = $a[$l] . '.' . $proxy_host;
	$l = $l - 1;
}
$subdomain = implode('.', array_slice($a, 0 , $l + 1));

if ($subdomain) $subdomain .= '.';

# no https
if ($_SERVER['HTTPS'] == 'on') {
	http_response_code(302);
	header("Location: http://{$subdomain}{$proxy_host}{$request}");
	die();
}

# translate browser headers
$headers = '';
foreach ($_SERVER as $name => $value) {
	
    $a = explode('_', $name);
    
    if (count($a) < 2 || $a[0] != 'HTTP' || $a[1] == 'X') continue;
    
    array_shift($a);
    $name = strtolower(implode('-', $a));
    $headers .= $name . ': ' . str_replace($proxy_host, $host, $value) . "\r\n";
}

# send req to host
$context = stream_context_create([
    'http' => [
        'ignore_errors'   => true,
        'follow_location' => false,
        'method'          => $_SERVER['REQUEST_METHOD'],
        'header'          => $headers,
        'timeout'         => 60,
        'content'         => http_build_query($_POST)
    ]
]);

$url = "{$host_scheme}://{$subdomain}{$host}{$request}";
$result = @file_get_contents($url, false, $context);

if (!isset($http_response_header) || !is_array($http_response_header)) {
    
    die('Proxy error');
}
$re = '~(?<=[^-a-z]|^)(' . preg_quote($host) . ')(?=[^-\.a-z]|$)~i';

# respond headers
foreach ($http_response_header as $h_line) {
	
	$h_line = preg_replace($re, $proxy_host, $h_line);
	header($h_line, false);

	if (0 === strpos(strtolower($h_line), 'content-type:')) {
		
		$c_type = preg_split('~[:; /]+~', strtolower($h_line));
	}
}

# update text content
if (isset($c_type) && in_array($c_type[1], ['text', 'application'])){
 
 	if (in_array($c_type[2], ['html', 'xml', 'xhtml+xml'])) {
	
		$result = preg_replace(
			'~(\<[^\>]+)(' . preg_quote($host) . ')(?=[^-\.a-z]|$)~is', 
			'${1}' . $proxy_host, 
			$result);
	} else if (in_array($c_type[2], ['css', 'javascript'])){
	
		$result = preg_replace($re, $proxy_host, $result);
	}
}
echo $result;
# end of file