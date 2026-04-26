<?php if (!defined('__TYPECHO_ROOT_DIR__')) { exit; } ?>
    <footer class="site-footer">
        <div class="flux-container footer-inner">
            <div class="footer-copy">
                <span class="footer-title">© <?php echo date('Y'); ?> <?php echo fluxgrid_escape(fluxgrid_option($this->options, 'logoText', (string) $this->options->title)); ?>.</span>
                <span><?php echo fluxgrid_escape(fluxgrid_option($this->options, 'footerText', (string) $this->options->description)); ?></span>
            </div>
            <div class="footer-meta">
                <a href="<?php $this->options->siteUrl(); ?>">首页</a>
                <a href="<?php $this->options->feedUrl(); ?>">RSS</a>
            </div>
        </div>
    </footer>
</div>
<div class="pjax-progress-bar" id="pjax-progress" aria-hidden="true"></div>
<button type="button" class="back-to-top" id="back-to-top" aria-label="回到顶部" data-no-swup>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
</button>
<div class="link-redirect-modal" id="link-redirect-modal" role="dialog" aria-modal="true" aria-labelledby="link-redirect-title" hidden>
    <div class="link-redirect-dialog">
        <div class="link-redirect-head">
            <span class="link-redirect-icon" aria-hidden="true">↗</span>
            <h3 id="link-redirect-title">即将打开外部链接</h3>
        </div>
        <div class="link-redirect-body">
            <p>你正在离开本站访问外部网站，请确认链接安全：</p>
            <div class="link-redirect-url" id="link-redirect-url"></div>
        </div>
        <div class="link-redirect-actions">
            <button type="button" class="link-redirect-cancel" data-redirect-cancel>取消</button>
            <a class="link-redirect-continue" id="link-redirect-continue" target="_blank" rel="noopener noreferrer">继续打开 →</a>
        </div>
        <div class="link-redirect-countdown"><span id="link-redirect-seconds">5</span> 秒后自动打开新窗口</div>
    </div>
</div>
<div class="stats-modal" id="stats-modal" role="dialog" aria-modal="true" aria-labelledby="stats-modal-title" hidden>
    <div class="stats-modal-backdrop" data-stats-close></div>
    <div class="stats-modal-dialog">
        <div class="stats-modal-header">
            <h2 id="stats-modal-title">站点统计</h2>
            <button type="button" class="stats-modal-close" data-stats-close aria-label="关闭">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="stats-modal-body">
            <div class="stats-card stats-card--full">
                <h3>动态日历 — 近 10 个月活动</h3>
                <div class="stats-chart" id="stats-chart-heatmap"></div>
            </div>
            <div class="stats-card">
                <h3>分类雷达图</h3>
                <div class="stats-chart" id="stats-chart-radar"></div>
            </div>
            <div class="stats-card">
                <h3>发布统计 — 月度</h3>
                <div class="stats-chart" id="stats-chart-monthly"></div>
            </div>
            <div class="stats-card">
                <h3>分类统计</h3>
                <div class="stats-chart" id="stats-chart-categories"></div>
            </div>
            <div class="stats-card">
                <h3>标签 TOP 20</h3>
                <div class="stats-chart" id="stats-chart-tags"></div>
            </div>
        </div>
    </div>
</div>
<script>window.fluxgridStats = <?php echo json_encode(fluxgrid_stats_data(), JSON_UNESCAPED_UNICODE); ?>;</script>
<?php
/* ── 全站迷你音乐播放器 ── */
$musicEnabled = fluxgrid_option($this->options, 'musicEnabled', 'off');
$musicId      = trim((string) fluxgrid_option($this->options, 'musicId', ''));
?>
<!-- FluxGrid Music: enabled=<?php echo fluxgrid_escape($musicEnabled); ?>, id_len=<?php echo strlen($musicId); ?> -->
<?php if ($musicEnabled === 'on' && $musicId !== ''):
    $musicServer     = fluxgrid_option($this->options, 'musicServer', 'netease');
    $musicType       = fluxgrid_option($this->options, 'musicType', 'playlist');
    $musicTheme      = fluxgrid_option($this->options, 'musicTheme', '#3b82f6');
    $musicAutoplay   = fluxgrid_option($this->options, 'musicAutoplay', 'off');
    $musicListFolded = fluxgrid_option($this->options, 'musicListFolded', 'on');
    $musicMobile     = fluxgrid_option($this->options, 'musicMobile', 'hide');
    $musicApi        = fluxgrid_option($this->options, 'musicApi', 'https://api.i-meto.com/meting/api');
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $musicTheme)) { $musicTheme = '#3b82f6'; }
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.css">
<script src="https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.js"></script>
<?php if ($musicMobile === 'hide'): ?>
<style>@media (max-width: 768px) { .aplayer.aplayer-fixed { display: none !important; } }</style>
<?php endif; ?>
<script>
/* 直接 Meting-API + APlayer,跳过 MetingJS 的 custom-element */
console.log('[FluxGrid Music] script block executed');
(function () {
  var apiBase = <?php echo json_encode(rtrim($musicApi, '?& ')); ?>;
  var server  = <?php echo json_encode($musicServer); ?>;
  var type    = <?php echo json_encode($musicType); ?>;
  var id      = <?php echo json_encode($musicId); ?>;
  var theme   = <?php echo json_encode($musicTheme); ?>;
  var autoplay   = <?php echo $musicAutoplay   === 'on' ? 'true' : 'false'; ?>;
  var listFolded = <?php echo $musicListFolded === 'on' ? 'true' : 'false'; ?>;
  console.log('[FluxGrid Music] config', { api: apiBase, server: server, type: type, id: id });

  function buildUrl() {
    var sep = apiBase.indexOf('?') === -1 ? '?' : '&';
    return apiBase + sep + 'server=' + encodeURIComponent(server)
      + '&type=' + encodeURIComponent(type)
      + '&id='   + encodeURIComponent(id);
  }

  var attempts = 0;
  function init() {
    if (typeof APlayer === 'undefined') {
      attempts++;
      if (attempts > 60) {
        console.error('[FluxGrid Music] APlayer 库未加载 — 检查 https://cdn.jsdelivr.net/npm/aplayer@1.10.1/dist/APlayer.min.js 是否被屏蔽');
        return;
      }
      return setTimeout(init, 100);
    }
    var url = buildUrl();
    console.log('[FluxGrid Music] fetching', url);
    fetch(url, { credentials: 'omit', mode: 'cors' })
      .then(function (r) {
        console.log('[FluxGrid Music] response', r.status, r.statusText);
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      })
      .then(function (audio) {
        console.log('[FluxGrid Music] got', audio);
        if (!audio || !audio.length) {
          console.warn('[FluxGrid Music] 空歌单 — 检查歌单 ID 是否对,或换 API:', apiBase);
          return;
        }
        var host = document.createElement('div');
        host.id = 'fluxgrid-aplayer';
        document.body.appendChild(host);
        var ap = new APlayer({
          container: host, audio: audio,
          fixed: true, listFolded: listFolded, listMaxHeight: '280px',
          autoplay: false, mutex: true, loop: 'all', order: 'list',
          preload: 'auto', volume: 0.7, theme: theme, lrcType: 3
        });
        console.log('[FluxGrid Music] APlayer initialized', ap);

        // 强制 APlayer 启动即 narrow 态 (左下角 66 封面 + > 切换条);
        // 部分 APlayer 版本不会自动加 aplayer-narrow, 这里显式加一次.
        host.classList.add('aplayer-narrow');

        // hover 替代 click 触发展开:进入立即移除 narrow → 展开,
        // 离开 600ms 防抖后回收为 narrow.
        var collapseTimer = null;
        host.addEventListener('mouseenter', function () {
          if (collapseTimer) { clearTimeout(collapseTimer); collapseTimer = null; }
          host.classList.remove('aplayer-narrow');
        });
        host.addEventListener('mouseleave', function () {
          if (collapseTimer) { clearTimeout(collapseTimer); }
          collapseTimer = setTimeout(function () {
            host.classList.add('aplayer-narrow');
          }, 600);
        });

        // 切换条 click 单独走我们的 toggle (capture-phase 抢在 APlayer 自己的
        // 内部 handler 前, 避免点一次后状态错位需要再点一下).
        requestAnimationFrame(function () {
          var sw = host.querySelector('.aplayer-miniswitcher');
          if (sw) {
            sw.addEventListener('click', function (e) {
              e.stopPropagation();
              e.preventDefault();
              host.classList.toggle('aplayer-narrow');
            }, true);
          }

        });

        // 浏览器策略:autoplay 时第一次出声前必须有用户交互.
        // 用户首次点击 / 触屏 / 按键 → 立即开始播放.
        if (autoplay) {
          var triggered = false;
          var firePlay = function () {
            if (triggered) return;
            triggered = true;
            try { ap.play(); } catch (e) { console.warn('[FluxGrid Music] play() rejected:', e); }
            document.removeEventListener('click',      firePlay, true);
            document.removeEventListener('keydown',    firePlay, true);
            document.removeEventListener('touchstart', firePlay, true);
            document.removeEventListener('scroll',     firePlay, true);
          };
          document.addEventListener('click',      firePlay, true);
          document.addEventListener('keydown',    firePlay, true);
          document.addEventListener('touchstart', firePlay, true);
          document.addEventListener('scroll',     firePlay, true);
        }
      })
      .catch(function (err) {
        console.error('[FluxGrid Music] 加载失败:', err.message || err,
          '— 后台外观→音乐播放器→Meting API 试试换成 https://api.injahow.cn/meting/');
      });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/DIYgod/OwO@master/dist/OwO.min.css">
<script src="https://cdn.jsdelivr.net/gh/DIYgod/OwO@master/dist/OwO.min.js"></script>
<script src="<?php echo fluxgrid_escape(fluxgrid_asset_url($this->options, 'assets/js/theme.js')); ?>"></script>
<?php $this->footer(); ?>
</body>
</html>
