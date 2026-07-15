<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

install_head('系统已安装', 0);
?>
<h2>系统已安装</h2>
<div class="alert alert-warn">检测到 <code>install.lock</code> 已存在，安装向导已被禁用，拒绝重新执行安装流程。</div>
<p>如需重新安装，请在服务器上手动删除 <code>install/install.lock</code> 文件（会导致现有数据无法通过向导重建，需谨慎操作），然后重新访问本页面。</p>
<p><a class="btn" href="/admin/login.php">前往后台登录</a></p>
<?php
install_foot();
