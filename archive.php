<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
<?php $this->need('header.php'); ?>
<?php
$archiveHeading = fluxgrid_archive_heading($this);

$currentCategoryMid = 0;
if ($this->is('category') && isset($this->_currentCategory) && isset($this->_currentCategory['mid'])) {
    $currentCategoryMid = (int) $this->_currentCategory['mid'];
}

if ($this->is('category')) {
    $archiveEyebrow = 'CATEGORY';
    $archiveDescription = '这个分类下的所有文章，按时间倒序排列。';
} elseif ($this->is('tag')) {
    $archiveEyebrow = 'TAG';
    $archiveDescription = '包含该标签的所有文章。';
} elseif ($this->is('search')) {
    $archiveEyebrow = 'SEARCH';
    $archiveDescription = '与关键词相关的文章检索结果。';
} elseif ($this->is('author')) {
    $archiveEyebrow = 'AUTHOR';
    $archiveDescription = '该作者发布的全部文章。';
} elseif ($this->is('date')) {
    $archiveEyebrow = 'DATE';
    $archiveDescription = '按时间归档的历史文章。';
} else {
    $archiveEyebrow = 'ARCHIVE';
    $archiveDescription = '按主题和时间整理的全部内容。';
}

if ($currentCategoryMid > 0) {
    $bannerImage = fluxgrid_fallback_image($this->options, $currentCategoryMid);
} elseif ($archiveHeading !== '') {
    $bannerImage = fluxgrid_fallback_image($this->options, abs(crc32($archiveHeading)) % 99999);
} else {
    $bannerImage = fluxgrid_fallback_image($this->options);
}

$this->widget('Widget_Stat')->to($siteStat);
$statPosts = isset($siteStat->publishedPostsNum) ? (int) $siteStat->publishedPostsNum : 0;
$statComments = isset($siteStat->publishedCommentsNum) ? (int) $siteStat->publishedCommentsNum : 0;
$statCategories = isset($siteStat->categoriesNum) ? (int) $siteStat->categoriesNum : 0;

$latestComments = array();
try {
    $this->widget('Widget_Comments_Recent@fluxgridArchiveComments', 'pageSize=3')->to($recentComments);
    while ($recentComments->next()) {
        $commentAuthor = fluxgrid_string_value(isset($recentComments->author) ? $recentComments->author : '');
        $commentText = '';
        if (isset($recentComments->text)) {
            $commentText = trim(strip_tags(fluxgrid_string_value($recentComments->text)));
        }
        if ($commentText !== '' && function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($commentText, 'UTF-8') > 60) {
                $commentText = mb_substr($commentText, 0, 60, 'UTF-8') . '...';
            }
        }
        $commentPermalink = fluxgrid_normalize_url_like_value(fluxgrid_widget_text($recentComments, 'permalink', 'permalink'));
        $commentPostTitle = fluxgrid_string_value(isset($recentComments->title) ? $recentComments->title : '');
        $commentDate = fluxgrid_widget_output($recentComments, 'date', 'm.d');
        if ($commentAuthor === '' || $commentPermalink === '') {
            continue;
        }
        $latestComments[] = array(
            'author' => $commentAuthor,
            'text' => $commentText,
            'permalink' => $commentPermalink,
            'title' => $commentPostTitle,
            'date' => $commentDate,
        );
    }
} catch (Exception $e) {
    $latestComments = array();
} catch (Throwable $e) {
    $latestComments = array();
}
?>
<main class="site-main">
    <section class="page-banner">
        <div class="page-banner-media">
            <img
                src="<?php echo fluxgrid_escape($bannerImage); ?>"
                alt=""
                loading="eager"
                onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
            >
        </div>
        <div class="page-banner-surface"></div>

        <div class="flux-container page-banner-inner">
            <span class="eyebrow">// <?php echo fluxgrid_escape($archiveEyebrow); ?></span>
            <h1><?php echo fluxgrid_escape($archiveHeading); ?></h1>
            <p><?php echo fluxgrid_escape($archiveDescription); ?></p>
        </div>
    </section>

    <section class="stream-section">
        <div class="flux-container">
            <?php if (!empty($navCategories)): ?>
                <div class="filter-bar">
                    <div class="chip-group">
                        <a class="chip <?php echo $currentCategoryMid === 0 ? 'is-active' : ''; ?>" href="<?php $this->options->siteUrl(); ?>">全部</a>
                        <?php foreach ($navCategories as $navCatIndex => $navCat): ?>
                            <?php if ($navCatIndex >= 8) { break; } ?>
                            <a class="chip <?php echo $currentCategoryMid === $navCat['mid'] ? 'is-active' : ''; ?>"
                               href="<?php echo fluxgrid_escape($navCat['permalink']); ?>">
                                <?php echo fluxgrid_escape($navCat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="home-grid">
                <div class="home-main">
                    <?php if ($this->have()): ?>
                        <div class="post-stack">
                            <?php while ($this->next()): ?>
                                <?php $postCover = fluxgrid_safe_image_url(fluxgrid_post_cover($this), $this->options); ?>
                                <?php echo fluxgrid_cover_debug_comment($this, 'archive-stream'); ?>
                                <?php $lockInfo = fluxgrid_lock_list_display($this, $this->options); ?>
                                <article class="post-card<?php echo $lockInfo ? ' post-card--locked' : ''; ?>">
                                    <a class="post-card-media" href="<?php $this->permalink(); ?>">
                                        <img
                                            src="<?php echo fluxgrid_escape($postCover); ?>"
                                            alt="<?php if ($lockInfo && $lockInfo['hideTitle']) echo '加密内容'; else $this->title(); ?>"
                                            loading="lazy"
                                            onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                                        >
                                    </a>
                                    <div class="post-card-body">
                                        <h3><a href="<?php $this->permalink(); ?>"><?php
                                            if ($lockInfo && $lockInfo['hideTitle']) { echo '🔒 加密内容'; }
                                            else { $this->title(); }
                                        ?></a></h3>
                                        <p><?php
                                            if ($lockInfo) { echo fluxgrid_escape($lockInfo['excerpt']); }
                                            else { echo fluxgrid_escape(fluxgrid_excerpt($this, 110)); }
                                        ?></p>
                                        <div class="card-meta">
                                            <div class="tags-list">
                                                <?php $this->category(' ', false); ?>
                                            </div>
                                            <span><?php $this->date('Y.m.d'); ?> · <?php $this->commentsNum('0 评论', '1 评论', '%d 评论'); ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>

                        <div class="pagination-wrap">
                            <?php $this->pageNav('‹', '›', 1, '...'); ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-panel">
                            <h3>没有找到内容</h3>
                            <p>换个关键词，或者返回首页继续浏览。</p>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="sidebar">
                    <div class="side-card">
                        <span class="eyebrow" style="margin-bottom: 10px;">// <?php echo fluxgrid_escape($archiveEyebrow); ?></span>
                        <h3><?php echo fluxgrid_escape($archiveHeading); ?></h3>
                        <p><?php echo fluxgrid_escape($archiveDescription); ?></p>
                    </div>

                    <div class="side-card side-terminal">
                        <div class="terminal-bar">
                            <span class="code-dot"></span>
                            <span class="code-dot"></span>
                            <span class="code-dot"></span>
                            <span class="terminal-title">~/blog.stats</span>
                        </div>
<pre class="terminal-body"><span class="t-prompt">$</span> <span class="t-cmd">stat --short</span>
<span class="t-tree">├─</span> <span class="t-key">posts     </span> <span class="t-val"><?php echo $statPosts; ?></span>
<span class="t-tree">├─</span> <span class="t-key">categories</span> <span class="t-val"><?php echo $statCategories; ?></span>
<span class="t-tree">├─</span> <span class="t-key">comments  </span> <span class="t-val"><?php echo $statComments; ?></span>
<span class="t-tree">└─</span> <span class="t-key">since     </span> <span class="t-val"><?php echo date('Y'); ?></span>

<span class="t-prompt">$</span> <span class="t-cmd">_</span></pre>
                    </div>

                    <?php if (!empty($navCategories)): ?>
                        <div class="side-card">
                            <h3>所有分类</h3>
                            <ul class="side-list" style="margin-top: 14px;">
                                <li>
                                    <a href="<?php $this->options->siteUrl(); ?>">
                                        <strong>全部文章</strong>
                                        <span>→</span>
                                    </a>
                                </li>
                                <?php foreach ($navCategories as $navCatIndex => $navCat): ?>
                                    <?php if ($navCatIndex >= 8) { break; } ?>
                                    <li>
                                        <a href="<?php echo fluxgrid_escape($navCat['permalink']); ?>">
                                            <strong><?php echo fluxgrid_escape($navCat['name']); ?></strong>
                                            <span><?php echo $currentCategoryMid === $navCat['mid'] ? '●' : '→'; ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($latestComments)): ?>
                        <div class="side-card">
                            <h3>最新评论</h3>
                            <ul class="side-comments">
                                <?php foreach ($latestComments as $c): ?>
                                    <li>
                                        <div class="sc-head">
                                            <span class="sc-author"><?php echo fluxgrid_escape($c['author']); ?></span>
                                            <span class="sc-meta"><?php echo fluxgrid_escape($c['date']); ?></span>
                                        </div>
                                        <?php if ($c['text'] !== ''): ?>
                                            <p class="sc-text"><?php echo fluxgrid_escape($c['text']); ?></p>
                                        <?php endif; ?>
                                        <span class="sc-on">on <a href="<?php echo fluxgrid_escape($c['permalink']); ?>"><?php echo fluxgrid_escape($c['title']); ?></a></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php $this->need('footer.php'); ?>
