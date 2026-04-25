<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

function fluxgrid_fetch_github_repos($username)
{
    $username = trim((string) $username);
    if ($username === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,38}$/', $username)) {
        return false;
    }

    $cacheFile = sys_get_temp_dir() . '/fluxgrid-gh-' . md5($username) . '.json';
    $cacheTtl = 3600;

    $cached = null;
    if (is_file($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        if ($raw !== false) {
            $cached = json_decode($raw, true);
        }
        if (is_array($cached) && isset($cached['updated_at']) && (time() - (int) $cached['updated_at']) < $cacheTtl) {
            return $cached;
        }
    }

    $url = 'https://api.github.com/users/' . rawurlencode($username) . '/repos?per_page=30&sort=updated&type=owner';
    $rawJson = null;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'YTBlog-Typecho-Theme');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/vnd.github+json'));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200 && is_string($response) && $response !== '') {
            $rawJson = $response;
        }
    }

    if ($rawJson === null && function_exists('ini_get') && ini_get('allow_url_fopen')) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'user_agent' => 'YTBlog-Typecho-Theme',
                'header' => "Accept: application/vnd.github+json\r\n",
            ),
        ));
        $response = @file_get_contents($url, false, $context);
        if (is_string($response) && $response !== '') {
            $rawJson = $response;
        }
    }

    if ($rawJson === null) {
        return is_array($cached) ? $cached : false;
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return is_array($cached) ? $cached : false;
    }

    $simplified = array();
    foreach ($decoded as $repo) {
        if (!is_array($repo) || empty($repo['name'])) { continue; }
        $simplified[] = array(
            'name' => (string) $repo['name'],
            'full_name' => isset($repo['full_name']) ? (string) $repo['full_name'] : '',
            'html_url' => isset($repo['html_url']) ? (string) $repo['html_url'] : '',
            'description' => isset($repo['description']) ? (string) $repo['description'] : '',
            'language' => isset($repo['language']) ? (string) $repo['language'] : '',
            'stargazers_count' => isset($repo['stargazers_count']) ? (int) $repo['stargazers_count'] : 0,
            'forks_count' => isset($repo['forks_count']) ? (int) $repo['forks_count'] : 0,
            'fork' => !empty($repo['fork']),
            'archived' => !empty($repo['archived']),
            'updated_at' => isset($repo['updated_at']) ? (string) $repo['updated_at'] : '',
            'topics' => isset($repo['topics']) && is_array($repo['topics']) ? array_values($repo['topics']) : array(),
        );
    }

    $payload = array(
        'updated_at' => time(),
        'repos' => $simplified,
    );

    @file_put_contents($cacheFile, json_encode($payload));
    return $payload;
}

function fluxgrid_github_lang_color($lang)
{
    static $map = array(
        'JavaScript' => '#f1e05a',
        'TypeScript' => '#3178c6',
        'Python' => '#3572A5',
        'Java' => '#b07219',
        'Go' => '#00ADD8',
        'Rust' => '#dea584',
        'C' => '#555555',
        'C++' => '#f34b7d',
        'C#' => '#178600',
        'PHP' => '#4F5D95',
        'Ruby' => '#701516',
        'Shell' => '#89e051',
        'HTML' => '#e34c26',
        'CSS' => '#563d7c',
        'SCSS' => '#c6538c',
        'Vue' => '#41b883',
        'Svelte' => '#ff3e00',
        'Dart' => '#00B4AB',
        'Swift' => '#F05138',
        'Kotlin' => '#A97BFF',
        'Lua' => '#000080',
        'Perl' => '#0298c3',
        'Elixir' => '#6e4a7e',
        'Haskell' => '#5e5086',
        'Scala' => '#c22d40',
        'R' => '#198CE7',
        'MATLAB' => '#e16737',
        'Julia' => '#a270ba',
        'Clojure' => '#db5855',
        'Erlang' => '#B83998',
        'Nim' => '#ffc200',
        'F#' => '#b845fc',
        'OCaml' => '#3be133',
        'Assembly' => '#6E4C13',
        'Makefile' => '#427819',
        'Dockerfile' => '#384d54',
        'TeX' => '#3D6117',
        'Vim script' => '#199f4b',
        'Jupyter Notebook' => '#DA5B0B',
        'PowerShell' => '#012456',
        'Zig' => '#ec915c',
        'Solidity' => '#AA6746',
    );
    $lang = (string) $lang;
    return isset($map[$lang]) ? $map[$lang] : '#858585';
}

function fluxgrid_github_time_ago($isoTime)
{
    $timestamp = strtotime($isoTime);
    if ($timestamp === false || $timestamp <= 0) { return ''; }
    $diff = time() - $timestamp;
    if ($diff < 60) { return '刚刚'; }
    if ($diff < 3600) { return floor($diff / 60) . ' 分钟前'; }
    if ($diff < 86400) { return floor($diff / 3600) . ' 小时前'; }
    if ($diff < 2592000) { return floor($diff / 86400) . ' 天前'; }
    if ($diff < 31536000) { return floor($diff / 2592000) . ' 个月前'; }
    return floor($diff / 31536000) . ' 年前';
}
