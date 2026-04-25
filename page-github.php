<?php
/**
 * GitHub 项目
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
?>
<?php $this->need('header.php'); ?>
<?php
$githubUser = trim(fluxgrid_post_field($this, 'github'));
$repos = array();
$fetchError = '';
$lastUpdated = '';

if ($githubUser === '') {
    $fetchError = 'missing-field';
} else {
    $result = fluxgrid_fetch_github_repos($githubUser);
    if ($result === false) {
        $fetchError = 'fetch-failed';
    } elseif (isset($result['repos']) && is_array($result['repos'])) {
        $repos = $result['repos'];
        $lastUpdated = isset($result['updated_at']) ? $result['updated_at'] : '';
    }
}

$pageCid = isset($this->cid) ? (int) $this->cid : 0;
$pageBannerImage = fluxgrid_safe_image_url(fluxgrid_post_cover($this), $this->options);
if ($pageBannerImage === '' && $pageCid > 0) {
    $pageBannerImage = fluxgrid_fallback_image($this->options, $pageCid);
}
?>
<main class="site-main">
    <article class="article-shell article-page">
        <header class="page-banner">
            <div class="page-banner-media">
                <img
                    src="<?php echo fluxgrid_escape($pageBannerImage); ?>"
                    alt=""
                    loading="eager"
                    onerror="<?php echo fluxgrid_escape(fluxgrid_image_fallback_script($this->options)); ?>"
                >
            </div>
            <div class="page-banner-surface"></div>

            <div class="flux-container page-banner-inner">
                <span class="eyebrow">// GITHUB</span>
                <h1><?php $this->title(); ?></h1>
                <?php if ($githubUser !== ''): ?>
                    <p>
                        <a href="https://github.com/<?php echo fluxgrid_escape($githubUser); ?>" target="_blank" rel="noopener noreferrer" data-no-swup>@<?php echo fluxgrid_escape($githubUser); ?></a> 的开源项目（最近更新 30 个，每小时缓存一次）
                    </p>
                <?php endif; ?>
            </div>
        </header>

        <div class="flux-container article-container">
            <?php if ($fetchError === 'missing-field'): ?>
                <div class="empty-panel" style="max-width: 720px; margin: 0 auto;">
                    <h3>未配置 GitHub 用户名</h3>
                    <p>请到后台编辑本页 →「自定义字段」新增一个字段：名称 <code>github</code>，值为你的 GitHub 用户名，例如 <code>almightyyantao</code>。</p>
                </div>
            <?php elseif ($fetchError === 'fetch-failed'): ?>
                <div class="empty-panel" style="max-width: 720px; margin: 0 auto;">
                    <h3>无法加载</h3>
                    <p>GitHub API 暂时拉不到 <code>@<?php echo fluxgrid_escape($githubUser); ?></code> 的仓库，请稍后刷新。</p>
                </div>
            <?php elseif (empty($repos)): ?>
                <div class="empty-panel" style="max-width: 720px; margin: 0 auto;">
                    <h3>没有公开项目</h3>
                    <p><code>@<?php echo fluxgrid_escape($githubUser); ?></code> 目前没有公开的仓库。</p>
                </div>
            <?php else: ?>
                <div class="github-repo-grid">
                    <?php foreach ($repos as $repo): ?>
                        <a class="github-repo-card" href="<?php echo fluxgrid_escape($repo['html_url']); ?>" target="_blank" rel="noopener noreferrer" data-no-swup>
                            <div class="github-repo-head">
                                <svg class="github-repo-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M2 2.5A2.5 2.5 0 014.5 0h8.75a.75.75 0 01.75.75v12.5a.75.75 0 01-.75.75h-2.5a.75.75 0 010-1.5h1.75v-2h-8a1 1 0 00-.7 1.715.75.75 0 01-1.06 1.06A2.5 2.5 0 012 11.5v-9zm10.5-1V9h-8c-.356 0-.694.074-1 .208V2.5a1 1 0 011-1h8zM5 12.25v3.25a.25.25 0 00.4.2l1.45-1.087a.25.25 0 01.3 0L8.6 15.7a.25.25 0 00.4-.2v-3.25a.25.25 0 00-.25-.25h-3.5a.25.25 0 00-.25.25z"/></svg>
                                <span class="github-repo-name"><?php echo fluxgrid_escape($repo['name']); ?></span>
                                <?php if (!empty($repo['fork'])): ?>
                                    <span class="github-repo-badge github-repo-badge--fork">Fork</span>
                                <?php endif; ?>
                                <?php if (!empty($repo['archived'])): ?>
                                    <span class="github-repo-badge github-repo-badge--archived">Archived</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($repo['description'])): ?>
                                <p class="github-repo-desc"><?php echo fluxgrid_escape($repo['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($repo['topics'])): ?>
                                <div class="github-repo-topics">
                                    <?php foreach (array_slice($repo['topics'], 0, 5) as $topic): ?>
                                        <span class="github-repo-topic"><?php echo fluxgrid_escape($topic); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="github-repo-meta">
                                <?php if (!empty($repo['language'])): ?>
                                    <span class="github-repo-lang">
                                        <span class="github-repo-lang-dot" style="background: <?php echo fluxgrid_escape(fluxgrid_github_lang_color($repo['language'])); ?>"></span>
                                        <?php echo fluxgrid_escape($repo['language']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ((int) $repo['stargazers_count'] > 0): ?>
                                    <span title="Stars">★ <?php echo number_format((int) $repo['stargazers_count']); ?></span>
                                <?php endif; ?>
                                <?php if ((int) $repo['forks_count'] > 0): ?>
                                    <span title="Forks">⑂ <?php echo number_format((int) $repo['forks_count']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($repo['updated_at'])): ?>
                                    <span class="github-repo-updated" title="最近更新"><?php echo fluxgrid_escape(fluxgrid_github_time_ago($repo['updated_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($lastUpdated !== ''): ?>
                    <p class="github-repo-footer">
                        <span>数据缓存于 <?php echo fluxgrid_escape(date('Y.m.d H:i', $lastUpdated)); ?></span>
                        <span>· 来源 <a href="https://api.github.com/users/<?php echo fluxgrid_escape($githubUser); ?>/repos" target="_blank" rel="noopener noreferrer" data-no-swup>GitHub REST API</a></span>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            ob_start();
            $this->content();
            $pageBody = trim(ob_get_clean());
            ?>
            <?php if ($pageBody !== '' && strip_tags($pageBody) !== ''): ?>
                <div class="article-content" style="margin-top: 40px;">
                    <?php echo fluxgrid_parse_shortcodes($pageBody); ?>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>
</main>
<?php $this->need('footer.php'); ?>
