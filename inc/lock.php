<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

function fluxgrid_lock_secret($options)
{
    static $secret = null;
    if ($secret === null) {
        $siteUrl = '';
        if (is_object($options) && isset($options->siteUrl)) { $siteUrl = (string) $options->siteUrl; }
        $secret = hash('sha256', $siteUrl . '|fluxgrid-lock|v1');
    }
    return $secret;
}

function fluxgrid_lock_token($password, $options)
{
    return hash_hmac('sha256', (string) $password, fluxgrid_lock_secret($options));
}

function fluxgrid_lock_set_cookie($name, $password, $options)
{
    $token = fluxgrid_lock_token($password, $options);
    $ttl   = 2592000; // 30 天
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $token, array(
            'expires'  => time() + $ttl,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    } else {
        setcookie($name, $token, time() + $ttl, '/', '', $secure, true);
    }
    $_COOKIE[$name] = $token;
}

function fluxgrid_lock_check_cookie($name, $password, $options)
{
    if (empty($_COOKIE[$name]) || !is_string($_COOKIE[$name])) { return false; }
    $expected = fluxgrid_lock_token($password, $options);
    return hash_equals($expected, $_COOKIE[$name]);
}

/* ---------- 全站加密 ---------- */

function fluxgrid_lock_site_password($options)
{
    if (!is_object($options) || !isset($options->sitePassword)) { return ''; }
    return trim((string) $options->sitePassword);
}

function fluxgrid_lock_site_unlocked($options)
{
    $pwd = fluxgrid_lock_site_password($options);
    if ($pwd === '') { return true; }
    return fluxgrid_lock_check_cookie('fluxgrid_site_auth', $pwd, $options);
}

function fluxgrid_lock_guard_site($options, $archive)
{
    if (fluxgrid_lock_site_unlocked($options)) { return; }
    fluxgrid_lock_render_page('site', array(), $options);
}

/* ---------- 分类加密 ---------- */

function fluxgrid_lock_parse_category_desc($desc)
{
    if (!is_string($desc)) { return null; }
    $desc = trim($desc);
    if ($desc === '' || $desc[0] !== '{') { return null; }
    $parsed = json_decode($desc, true);
    if (!is_array($parsed) || empty($parsed['lock']) || empty($parsed['password'])) { return null; }
    return array(
        'password' => (string) $parsed['password'],
        'desc'     => isset($parsed['desc'])  ? (string) $parsed['desc']  : '',
        'img'      => isset($parsed['img'])   ? (string) $parsed['img']   : '',
        'start'    => isset($parsed['start']) ? (string) $parsed['start'] : '',
        'end'      => isset($parsed['end'])   ? (string) $parsed['end']   : '',
    );
}

function fluxgrid_lock_category_by_mid($mid)
{
    static $cache = array();
    $mid = (int) $mid;
    if ($mid <= 0) { return null; }
    if (array_key_exists($mid, $cache)) { return $cache[$mid]; }

    $cache[$mid] = null;
    try {
        $db  = Typecho_Db::get();
        $row = $db->fetchRow($db->select()->from('table.metas')->where('mid = ?', $mid)->limit(1));
        if (!$row || empty($row['description'])) { return null; }
        $info = fluxgrid_lock_parse_category_desc($row['description']);
        if ($info) {
            $info['mid']  = $mid;
            $info['name'] = isset($row['name']) ? (string) $row['name'] : '';
            $info['slug'] = isset($row['slug']) ? (string) $row['slug'] : '';
            $cache[$mid]  = $info;
        }
    } catch (Exception $e) {
        // ignore
    }
    return $cache[$mid];
}

function fluxgrid_lock_category_applies($info, $postCreated)
{
    $start = !empty($info['start']) ? @strtotime($info['start']) : null;
    $end   = !empty($info['end'])   ? @strtotime($info['end'])   : null;
    $postCreated = (int) $postCreated;
    if ($start && $postCreated < $start) { return false; }
    if ($end   && $postCreated > $end)   { return false; }
    return true;
}

function fluxgrid_lock_category_unlocked($mid, $password, $options)
{
    return fluxgrid_lock_check_cookie('fluxgrid_cat_' . (int) $mid, $password, $options);
}

/**
 * 返回某个 post/archive 所在的加密分类信息(仅单分类文章)或 null。
 */
function fluxgrid_lock_row_category_info($widget)
{
    if (!is_object($widget)) { return null; }
    $cats = isset($widget->categories) ? $widget->categories : null;
    if (!is_array($cats) || count($cats) !== 1) { return null; }
    $mid = isset($cats[0]['mid']) ? (int) $cats[0]['mid'] : 0;
    $info = fluxgrid_lock_category_by_mid($mid);
    if (!$info) { return null; }
    $created = isset($widget->created) ? (int) $widget->created : 0;
    if (!fluxgrid_lock_category_applies($info, $created)) { return null; }
    return $info;
}

function fluxgrid_lock_guard_category($options, $archive)
{
    if (!is_object($archive) || !method_exists($archive, 'is')) { return; }

    // 单文章
    if ($archive->is('single', 'post')) {
        $info = fluxgrid_lock_row_category_info($archive);
        if ($info && !fluxgrid_lock_category_unlocked($info['mid'], $info['password'], $options)) {
            fluxgrid_lock_render_page('cat', $info, $options);
        }
        return;
    }

    // 分类归档
    if ($archive->is('category')) {
        $mid = isset($archive->mid) ? (int) $archive->mid : 0;
        $info = fluxgrid_lock_category_by_mid($mid);
        if ($info && !fluxgrid_lock_category_unlocked($info['mid'], $info['password'], $options)) {
            fluxgrid_lock_render_page('cat', $info, $options);
        }
    }
}

/* ---------- 独立页面加密(含自定义字段) ---------- */

function fluxgrid_lock_page_password($archive)
{
    if (!is_object($archive)) { return ''; }
    if (isset($archive->fields) && isset($archive->fields->password)) {
        $v = trim((string) $archive->fields->password);
        if ($v !== '') { return $v; }
    }
    return '';
}

function fluxgrid_lock_guard_page($options, $archive)
{
    if (!is_object($archive) || !method_exists($archive, 'is')) { return; }
    if (!$archive->is('page')) { return; }

    $pwd = fluxgrid_lock_page_password($archive);
    if ($pwd === '') { return; }

    $cid = isset($archive->cid) ? (int) $archive->cid : 0;
    if ($cid <= 0) { return; }

    if (!fluxgrid_lock_check_cookie('fluxgrid_page_' . $cid, $pwd, $options)) {
        fluxgrid_lock_render_page('page', array(
            'cid'   => $cid,
            'title' => isset($archive->title) ? (string) $archive->title : '',
        ), $options);
    }
}

/* ---------- 解锁 POST 处理 ---------- */

function fluxgrid_lock_handle_unlock_post($options)
{
    if (empty($_POST['_fluxgrid_unlock_action'])) { return; }

    $action   = (string) $_POST['_fluxgrid_unlock_action'];
    $input    = isset($_POST['_fluxgrid_unlock_password']) ? (string) $_POST['_fluxgrid_unlock_password'] : '';
    $redirect = isset($_POST['_fluxgrid_unlock_redirect']) ? (string) $_POST['_fluxgrid_unlock_redirect'] : '/';

    // redirect 安全校验:必须是同源相对路径或同站 URL
    $redirect = fluxgrid_lock_sanitize_redirect($redirect, $options);

    $success = false;
    $errMsg  = '密码错误';

    if ($action === 'site') {
        $expected = fluxgrid_lock_site_password($options);
        if ($expected !== '' && hash_equals($expected, $input)) {
            fluxgrid_lock_set_cookie('fluxgrid_site_auth', $expected, $options);
            $success = true;
        }
    } elseif (strpos($action, 'cat:') === 0) {
        $mid  = (int) substr($action, 4);
        $info = fluxgrid_lock_category_by_mid($mid);
        if ($info && hash_equals((string) $info['password'], $input)) {
            fluxgrid_lock_set_cookie('fluxgrid_cat_' . $mid, $info['password'], $options);
            $success = true;
        }
    } elseif (strpos($action, 'page:') === 0) {
        $cid = (int) substr($action, 5);
        $pwd = fluxgrid_lock_fetch_page_password($cid);
        if ($pwd !== '' && hash_equals($pwd, $input)) {
            fluxgrid_lock_set_cookie('fluxgrid_page_' . $cid, $pwd, $options);
            $success = true;
        }
    }

    if (headers_sent()) { return; }

    if (!$success) {
        $sep = (strpos($redirect, '?') === false) ? '?' : '&';
        $redirect .= $sep . 'fluxgrid_lock_error=' . rawurlencode($errMsg);
        header('Location: ' . $redirect);
        exit;
    }

    // 成功:不依赖 302,直接吐一张中转页用 JS 强制跳转并刷新,
    // 避免部分环境下 Location 头不触发完整重新加载。
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $attr = fluxgrid_escape($redirect);
    $js   = json_encode($redirect, JSON_UNESCAPED_SLASHES);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
       . '<meta http-equiv="refresh" content="0;url=' . $attr . '">'
       . '<title>解锁成功</title>'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;'
       . 'background:#0b1220;color:#e5eaf3;}'
       . '.box{text-align:center;padding:28px 36px;border:1px solid rgba(147,197,253,0.18);border-radius:12px;}</style>'
       . '</head><body>'
       . '<div class="box"><p>✓ 解锁成功,正在跳转…</p></div>'
       . '<script>window.location.replace(' . $js . ');</script>'
       . '</body></html>';
    exit;
}

function fluxgrid_lock_fetch_page_password($cid)
{
    $cid = (int) $cid;
    if ($cid <= 0) { return ''; }
    try {
        $db  = Typecho_Db::get();
        $row = $db->fetchRow($db->select()->from('table.fields')
            ->where('cid = ?', $cid)->where('name = ?', 'password')->limit(1));
        if ($row && !empty($row['str_value'])) { return (string) $row['str_value']; }
    } catch (Exception $e) {}
    return '';
}

function fluxgrid_lock_sanitize_redirect($url, $options)
{
    $url = (string) $url;
    if ($url === '') { return '/'; }
    if ($url[0] === '/' && (strlen($url) < 2 || $url[1] !== '/')) { return $url; }
    $siteUrl = is_object($options) && isset($options->siteUrl) ? (string) $options->siteUrl : '';
    if ($siteUrl !== '') {
        $normalized = rtrim($siteUrl, '/');
        if ($url === $normalized
            || strpos($url, $normalized . '/') === 0
            || strpos($url, $normalized . '?') === 0
            || strpos($url, $normalized . '#') === 0) {
            return $url;
        }
    }
    return '/';
}

/* ---------- 列表页的加密占位信息 ---------- */

/**
 * 列表页/首页文章卡片在渲染时调用:返回 null 表示无加密,否则返回:
 *   array('hideTitle' => bool, 'excerpt' => string)
 */
function fluxgrid_lock_list_display($widget, $options = null)
{
    if ($options === null) {
        try { $options = Typecho_Widget::widget('Widget_Options'); }
        catch (Exception $e) { return null; }
    }
    $info = fluxgrid_lock_row_category_info($widget);
    if (!$info) { return null; }
    if (fluxgrid_lock_category_unlocked($info['mid'], $info['password'], $options)) { return null; }
    return array(
        'hideTitle' => !empty($options->lockHideTitle) && (string) $options->lockHideTitle === '1',
        'excerpt'   => '🔒 此文章属于加密分类,点击后输入密码查看',
    );
}

/* ---------- shortcode 渲染 ---------- */

function fluxgrid_lock_render_login_block($body)
{
    try {
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin()) { return $body; }
    } catch (Exception $e) {}
    $tip = '登录可见';
    try {
        $opt = Typecho_Widget::widget('Widget_Options');
        $tip = fluxgrid_option($opt, 'loginHideText', $tip);
    } catch (Exception $e) {}
    return '<div class="fx-hidden fx-hidden--login">'
        . '<span class="fx-hidden-icon">🔐</span>'
        . '<span class="fx-hidden-text">' . fluxgrid_escape($tip) . '</span>'
        . '</div>';
}

function fluxgrid_lock_render_reply_block($body, $archive)
{
    // 管理员 / 作者本人直接可见
    try {
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin()) { return $body; }
    } catch (Exception $e) {}

    $cid = 0;
    if (is_object($archive) && isset($archive->cid)) { $cid = (int) $archive->cid; }

    if ($cid > 0 && !empty($_COOKIE['__typecho_remember_mail'])) {
        $email = (string) $_COOKIE['__typecho_remember_mail'];
        if ($email !== '') {
            try {
                $db  = Typecho_Db::get();
                $row = $db->fetchRow($db->select(array('COUNT(coid)' => 'num'))->from('table.comments')
                    ->where('cid = ?', $cid)
                    ->where('mail = ?', $email)
                    ->where('status = ?', 'approved')
                    ->limit(1));
                $num = $row ? (int) $row['num'] : 0;
                if ($num > 0) { return $body; }
            } catch (Exception $e) {}
        }
    }

    $tip = '评论后可见(需审核通过)';
    try {
        $opt = Typecho_Widget::widget('Widget_Options');
        $tip = fluxgrid_option($opt, 'replyHideText', $tip);
    } catch (Exception $e) {}
    return '<div class="fx-hidden fx-hidden--reply">'
        . '<span class="fx-hidden-icon">💬</span>'
        . '<span class="fx-hidden-text">' . fluxgrid_escape($tip) . '</span>'
        . '</div>';
}

/* ---------- 加密锁定页 / 内嵌表单 ---------- */

function fluxgrid_lock_current_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST'])    ? $_SERVER['HTTP_HOST']    : '';
    $uri    = isset($_SERVER['REQUEST_URI'])  ? $_SERVER['REQUEST_URI']  : '/';
    // 去掉之前追加的错误参数,避免累积
    $uri = preg_replace('/([?&])fluxgrid_lock_error=[^&]*(&|$)/', '$1', $uri);
    $uri = rtrim($uri, '?&');
    return $scheme . '://' . $host . $uri;
}

function fluxgrid_lock_render_inline_form($kind, $archive)
{
    $cid = 0; $title = '';
    if (is_object($archive)) {
        if (isset($archive->cid))   { $cid   = (int) $archive->cid; }
        if (isset($archive->title)) { $title = (string) $archive->title; }
    }
    $action = 'page:' . $cid;
    // native 单篇文章密码仍走 Typecho 原生 protectPassword 表单字段名
    if ($kind === 'native') {
        ob_start();
        ?>
        <div class="fx-lock-inline">
            <div class="fx-lock-inline-title">🔒 此文章已加密</div>
            <p class="fx-lock-inline-tip">输入密码阅读 <strong><?php echo fluxgrid_escape($title); ?></strong></p>
            <form method="post" class="fx-lock-form">
                <input type="password" name="protectPassword" placeholder="请输入文章密码" autofocus required>
                <button type="submit">解锁</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    // 兜底(不应到达)
    return '';
}

function fluxgrid_lock_render_page($type, $ctx, $options)
{
    if (headers_sent()) { return; }

    $siteTitle = '';
    if (is_object($options)) {
        if (!empty($options->title)) { $siteTitle = (string) $options->title; }
    }

    $titleText   = '内容加密';
    $descText    = '';
    $actionValue = '';
    $coverImg    = '';
    $favicon     = is_object($options) ? fluxgrid_option($options, 'faviconUrl', '') : '';

    if ($type === 'site') {
        $titleText   = $siteTitle !== '' ? $siteTitle : '站点已加密';
        $descText    = '此站点处于加密状态,输入密码后访问。';
        $actionValue = 'site';
    } elseif ($type === 'cat') {
        $titleText   = !empty($ctx['name']) ? $ctx['name'] : '加密分类';
        $descText    = !empty($ctx['desc']) ? (string) $ctx['desc'] : '该分类下的内容已加密,输入密码查看。';
        $coverImg    = !empty($ctx['img'])  ? (string) $ctx['img']  : '';
        $actionValue = 'cat:' . (int) $ctx['mid'];
    } elseif ($type === 'page') {
        $titleText   = !empty($ctx['title']) ? $ctx['title'] : '页面加密';
        $descText    = '此页面需要密码查看。';
        $actionValue = 'page:' . (int) $ctx['cid'];
    }

    // 全站加密解锁后回首页,分类/页面回原 URL
    // 注:siteUrl 末尾不带斜杠,由服务端规范化重定向补上 —
    // 直接跳带斜杠的 URL 在部分 Typecho 环境下会白屏(缓存/Cookie 路径相关)。
    if ($type === 'site') {
        $redirect = is_object($options) && !empty($options->siteUrl)
            ? rtrim((string) $options->siteUrl, '/')
            : '/';
    } else {
        $redirect = fluxgrid_lock_current_url();
    }

    $errorMsg = '';
    if (!empty($_GET['fluxgrid_lock_error'])) {
        $errorMsg = (string) $_GET['fluxgrid_lock_error'];
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo fluxgrid_escape('🔒 ' . $titleText); ?></title>
<meta name="robots" content="noindex">
<?php if ($favicon !== ''): ?>
<link rel="icon" href="<?php echo fluxgrid_escape($favicon); ?>">
<?php endif; ?>
<style>
:root { color-scheme: dark; }
*, *::before, *::after { box-sizing: border-box; }
body {
    margin: 0;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
    background: radial-gradient(1200px 800px at 20% 10%, rgba(37,99,235,0.18), transparent 60%),
                radial-gradient(1000px 700px at 80% 90%, rgba(59,130,246,0.12), transparent 55%),
                #0b1220;
    color: #e5eaf3;
    padding: 40px 20px;
}
.fx-lock-card {
    background: rgba(14, 22, 39, 0.85);
    border: 1px solid rgba(147, 197, 253, 0.15);
    border-radius: 16px;
    padding: 36px 32px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.45);
    backdrop-filter: blur(8px);
}
.fx-lock-eyebrow {
    color: #93c5fd;
    font-size: 12px;
    letter-spacing: 0.12em;
    margin: 0 0 8px;
}
.fx-lock-title {
    margin: 0 0 10px;
    font-size: 22px;
    color: #f3f6fb;
}
.fx-lock-desc {
    margin: 0 0 20px;
    color: #9fb2cf;
    line-height: 1.6;
    font-size: 14px;
}
.fx-lock-cover {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 18px;
    border: 1px solid rgba(147, 197, 253, 0.12);
}
.fx-lock-form { display: flex; gap: 10px; margin-top: 4px; }
.fx-lock-form input[type="password"] {
    flex: 1;
    background: rgba(7, 12, 24, 0.75);
    border: 1px solid rgba(147, 197, 253, 0.25);
    color: #e5eaf3;
    padding: 11px 14px;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}
.fx-lock-form input[type="password"]:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
}
.fx-lock-form button {
    background: linear-gradient(135deg, #2563eb, #60a5fa);
    color: #fff;
    border: 0;
    padding: 0 22px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    letter-spacing: 0.05em;
    transition: transform 0.1s, box-shadow 0.2s;
}
.fx-lock-form button:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(37,99,235,0.4); }
.fx-lock-error {
    margin-top: 14px;
    color: #fca5a5;
    font-size: 13px;
    background: rgba(220, 38, 38, 0.08);
    border: 1px solid rgba(220, 38, 38, 0.22);
    padding: 9px 12px;
    border-radius: 8px;
}
.fx-lock-footer {
    margin-top: 22px;
    font-size: 12px;
    color: #64748b;
    text-align: center;
}
.fx-lock-footer a { color: #93c5fd; text-decoration: none; }
</style>
</head>
<body>
<main class="fx-lock-card">
    <p class="fx-lock-eyebrow">// FLUXGRID · LOCK</p>
    <h1 class="fx-lock-title">🔒 <?php echo fluxgrid_escape($titleText); ?></h1>
    <p class="fx-lock-desc"><?php echo fluxgrid_escape($descText); ?></p>
    <?php if ($coverImg !== ''): ?>
    <img class="fx-lock-cover" src="<?php echo fluxgrid_escape($coverImg); ?>" alt="">
    <?php endif; ?>
    <form method="post" class="fx-lock-form">
        <input type="hidden" name="_fluxgrid_unlock_action"   value="<?php echo fluxgrid_escape($actionValue); ?>">
        <input type="hidden" name="_fluxgrid_unlock_redirect" value="<?php echo fluxgrid_escape($redirect); ?>">
        <input type="password" name="_fluxgrid_unlock_password" placeholder="请输入密码" autofocus required autocomplete="off">
        <button type="submit">解锁</button>
    </form>
    <?php if ($errorMsg !== ''): ?>
    <div class="fx-lock-error"><?php echo fluxgrid_escape($errorMsg); ?></div>
    <?php endif; ?>
    <p class="fx-lock-footer">
        <?php if ($siteTitle !== ''): ?>
            <a href="/"><?php echo fluxgrid_escape($siteTitle); ?></a>
        <?php else: ?>
            <a href="/">返回首页</a>
        <?php endif; ?>
    </p>
</main>
</body>
</html><?php
    exit;
}

