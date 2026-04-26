<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php
$siteTitle = (string) $this->options->title;
$docTitle = $siteTitle;

if ($this->is('post') || $this->is('page')) {
    ob_start();
    $this->title();
    $currentTitle = trim(strip_tags(ob_get_clean()));
    if ($currentTitle !== '') {
        $docTitle = $currentTitle . ' - ' . $siteTitle;
    }
} elseif (!$this->is('index')) {
    $archiveTitle = fluxgrid_archive_heading($this);
    if ($archiveTitle !== '') {
        $docTitle = $archiveTitle . ' - ' . $siteTitle;
    }
}

$logoText = fluxgrid_option($this->options, 'logoText', $siteTitle);
$pageDescription = $this->is('post') || $this->is('page')
    ? fluxgrid_excerpt($this, 120)
    : fluxgrid_option($this->options, 'footerText', (string) $this->options->description);

$navCategories = array();
$navCategoryChildren = array();
$this->widget('Widget_Metas_Category_List@fluxgridHeaderCategories')->to($categoryList);
while ($categoryList->next()) {
    $categoryName = trim(strip_tags(fluxgrid_widget_text($categoryList, 'name', 'name')));
    $categoryPermalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($categoryList, 'permalink', 'permalink'));
    $categoryMid = isset($categoryList->mid) ? (int) $categoryList->mid : 0;
    $categoryParent = isset($categoryList->parent) ? (int) $categoryList->parent : 0;
    $categorySlug = isset($categoryList->slug) ? fluxgrid_string_value($categoryList->slug) : '';

    if ($categoryName === '' || $categoryPermalink === '' || $categoryMid <= 0) {
        continue;
    }

    if ($categoryName === '默认分类' || strtolower($categorySlug) === 'default') {
        continue;
    }

    $item = array(
        'mid' => $categoryMid,
        'parent' => $categoryParent,
        'name' => $categoryName,
        'permalink' => $categoryPermalink,
    );

    if ($categoryParent > 0) {
        if (!isset($navCategoryChildren[$categoryParent])) {
            $navCategoryChildren[$categoryParent] = array();
        }
        $navCategoryChildren[$categoryParent][] = $item;
        continue;
    }

    $navCategories[] = $item;
}

$navPages = array();
$this->widget('Widget_Contents_Page_List@fluxgridHeaderPages')->to($pageList);
while ($pageList->next()) {
    $pageName = trim(strip_tags(fluxgrid_widget_text($pageList, 'title', 'title')));
    $pagePermalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($pageList, 'permalink', 'permalink'));
    if ($pageName === '' || $pagePermalink === '') {
        continue;
    }
    $navPages[] = array(
        'name' => $pageName,
        'permalink' => $pagePermalink,
    );
}

$navItems = array();
foreach ($navCategories as $cat) {
    $childrenRaw = isset($navCategoryChildren[$cat['mid']]) ? $navCategoryChildren[$cat['mid']] : array();
    $navItems[] = array(
        'type' => 'category',
        'name' => $cat['name'],
        'permalink' => $cat['permalink'],
        'mid' => $cat['mid'],
        'children' => $childrenRaw,
    );
}
foreach ($navPages as $page) {
    $navItems[] = array(
        'type' => 'page',
        'name' => $page['name'],
        'permalink' => $page['permalink'],
        'mid' => 0,
        'children' => array(),
    );
}

$navVisibleLimit = 5;
$navVisible = $navItems;
$navOverflow = array();
if (count($navItems) > $navVisibleLimit) {
    $navVisible = array_slice($navItems, 0, $navVisibleLimit - 1);
    $navOverflow = array_slice($navItems, $navVisibleLimit - 1);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo fluxgrid_escape($docTitle); ?></title>
    <meta name="description" content="<?php echo fluxgrid_escape($pageDescription); ?>">
    <?php
    $configuredFavicon = fluxgrid_option($this->options, 'faviconUrl', '');
    $faviconHref = $configuredFavicon !== ''
        ? fluxgrid_normalize_url_like_value($configuredFavicon)
        : fluxgrid_asset_url($this->options, 'assets/images/favicon.svg');
    $faviconType = preg_match('/\.svg(?:$|\?)/i', $faviconHref) ? 'image/svg+xml' : '';
    ?>
    <link rel="icon" href="<?php echo fluxgrid_escape($faviconHref); ?>"<?php echo $faviconType !== '' ? ' type="' . fluxgrid_escape($faviconType) . '"' : ''; ?>>
    <link rel="shortcut icon" href="<?php echo fluxgrid_escape($faviconHref); ?>">
    <link rel="apple-touch-icon" href="<?php echo fluxgrid_escape($faviconHref); ?>">
    <link rel="preconnect" href="https://fonts.loli.net">
    <link rel="preconnect" href="https://gstatic.loli.net" crossorigin>
    <link href="https://fonts.loli.net/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&family=Noto+Sans+SC:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css">
    <?php
      $fluxCssFiles = array(
          'style.css',         // 变量 / 重置 / 卡片进入动画
          'layout.css',        // 顶栏 / Hero / 首页 / 侧栏 / 文章卡片
          'pages.css',         // 页面横幅 / 筛选 / 列表 / 分页
          'article.css',       // 文章详情 / 目录 / Lightbox / 评论
          'footer.css',        // 空态 / 页脚
          'responsive.css',    // 响应式断点
          'particle.css',      // 粒子背景
          'theme-light.css',   // 浅色主题覆盖
          'shortcodes.css',    // 所有短代码样式
          'github.css',        // GitHub 页
          'lock.css',          // 加密 UI
          'stats.css',         // 站点统计 modal
          'music.css',         // 全站迷你音乐播放器
          'sticky.css',        // 首页置顶文章区
      );
      foreach ($fluxCssFiles as $fluxCssFile) {
          echo '    <link rel="stylesheet" href="' . fluxgrid_escape(fluxgrid_asset_url($this->options, 'assets/css/' . $fluxCssFile)) . '">' . "\n";
      }
    ?>
    <script>
      (function () {
        var theme = 'light';
        try {
          var stored = localStorage.getItem('fluxgrid-theme');
          if (stored === 'dark' || stored === 'light') { theme = stored; }
        } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
      })();
    </script>
    <?php $this->header('generator=&template=&pingback=&xmlrpc=&wlw='); ?>
</head>
<body class="theme-fluxgrid <?php echo $this->is('index') ? 'is-home' : 'is-inner'; ?>">
<canvas id="particle-bg" aria-hidden="true"></canvas>
<div class="site-shell">
    <header class="site-header" id="site-header">
        <div class="flux-container header-inner">
            <a class="brand" href="<?php $this->options->siteUrl(); ?>" aria-label="<?php echo fluxgrid_escape($siteTitle); ?>">
                <span class="brand-mark"></span>
                <span class="brand-text"><?php echo fluxgrid_escape($logoText); ?></span>
            </a>

            <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false">
                <span></span>
                <span></span>
            </button>

            <nav class="site-nav" id="site-nav">
                <a class="<?php echo $this->is('index') ? 'is-active' : ''; ?>" href="<?php $this->options->siteUrl(); ?>">首页</a>
                <?php foreach ($navVisible as $navItem): ?>
                    <?php $hasChildren = !empty($navItem['children']); ?>
                    <div class="nav-item <?php echo $hasChildren ? 'has-children' : ''; ?>">
                        <a class="nav-link" href="<?php echo fluxgrid_escape($navItem['permalink']); ?>">
                            <?php echo fluxgrid_escape($navItem['name']); ?>
                        </a>
                        <?php if ($hasChildren): ?>
                            <div class="nav-dropdown">
                                <?php foreach ($navItem['children'] as $child): ?>
                                    <a href="<?php echo fluxgrid_escape($child['permalink']); ?>">
                                        <?php echo fluxgrid_escape($child['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($navOverflow)): ?>
                    <div class="nav-item has-children nav-more">
                        <span class="nav-link" tabindex="0" role="button" aria-haspopup="true">更多</span>
                        <div class="nav-dropdown">
                            <?php foreach ($navOverflow as $overflowItem): ?>
                                <a href="<?php echo fluxgrid_escape($overflowItem['permalink']); ?>">
                                    <?php echo fluxgrid_escape($overflowItem['name']); ?>
                                </a>
                                <?php if (!empty($overflowItem['children'])): ?>
                                    <?php foreach ($overflowItem['children'] as $child): ?>
                                        <a class="nav-dropdown-child" href="<?php echo fluxgrid_escape($child['permalink']); ?>">
                                            ↳ <?php echo fluxgrid_escape($child['name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a href="<?php $this->options->feedUrl(); ?>" data-no-swup>RSS</a>
            </nav>

            <?php $currentSearch = isset($_GET['s']) ? (string) $_GET['s'] : ''; ?>
            <form class="search-box" action="<?php $this->options->siteUrl(); ?>" method="get" role="search">
                <button type="submit" class="search-icon-btn" aria-label="搜索">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <input type="text" name="s" id="site-search-input" placeholder="搜索文章…" value="<?php echo fluxgrid_escape($currentSearch); ?>" autocomplete="off">
                <kbd class="search-hint" aria-hidden="true"><span class="search-hint-mac">⌘</span><span class="search-hint-win">Ctrl</span>K</kbd>
            </form>

            <div class="header-actions">
                <button type="button" class="header-action stats-toggle" id="stats-toggle" aria-label="站点统计" data-no-swup>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/><circle cx="7" cy="14" r="1.5" fill="currentColor"/><circle cx="11" cy="10" r="1.5" fill="currentColor"/><circle cx="15" cy="14" r="1.5" fill="currentColor"/><circle cx="20" cy="9" r="1.5" fill="currentColor"/></svg>
                </button>
                <button type="button" class="header-action theme-toggle" id="theme-toggle" aria-label="切换明暗主题" data-no-swup>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                </button>
            </div>
        </div>
    </header>
