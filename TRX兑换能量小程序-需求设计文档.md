# TRX兑换能量小程序 —— 需求设计文档（定稿版）

> 版本：v2.0（定稿）　　更新日期：2026-07-15　　用途：提交给开发（Claude Code）作为实现依据
>
> 本版为定稿版本：已整合此前讨论中的全部安全性与体验优化建议，不再保留"待确认"事项；仅"钱包一键跳转（深度链接）"因不同钱包协议不统一，列入后续可扩展项，不作为本期必做。

---

## 一、项目概述

### 1.1 项目背景
面向 TRON（波场）用户的"以TRX换能量"服务站点：客户向平台指定的 TRON 地址转账 **1 TRX**，转账来源地址即可获得一笔"能量"，用于后续转账免手续费。

### 1.2 项目边界（重要）
经与需求方确认，**能量的下发/委托机制不属于本系统开发范围**：收款地址已经绑定了第三方能量托管平台，转账后能量由第三方自动完成分配。本系统只负责：

1. 前台展示收款地址、复制引导、规则说明；
2. 后台可配置站点信息（Logo、标题、客服链接、收款地址、规则说明等）；
3. 后台统计"复制次数"与"转账次数"两项数据，并计算转化率。

其中"转账次数"通过**只读**方式调用 TronGrid 公开 API 查询链上转入记录得到，不涉及任何私钥操作、能量委托或资金处理。

### 1.3 技术选型
| 类别 | 选型 | 说明 |
|---|---|---|
| 后端 | PHP 8.x（原生，无框架） | 项目体量小，原生 PHP 便于维护、部署门槛低 |
| 数据存储 | MySQL 5.7+ / 8.0 | 存放配置、账号、复制事件、转账记录，通过 PDO + 预处理语句读写 |
| 前端 | 原生 HTML5 + CSS3 + JS | 移动端优先的响应式布局 |
| 富文本编辑器 | Quill.js | 用于"规则说明"编辑，免费开源、无需 API Key |
| 图表（可选） | Chart.js | 数据面板趋势图 |
| 链上数据源 | TronGrid 公开 API | 只读查询收款地址交易记录，无需私钥 |
| 定时任务 | Linux crontab + PHP CLI 脚本 | 定期拉取链上转账记录 |
| 安装方式 | 网页版分步安装向导 | 首次部署时引导完成数据库配置、建表、创建管理员账号 |
| 传输安全 | 强制 HTTPS | 生产环境必须启用，防止收款地址被篡改 |
| 部署环境 | Linux + Nginx/Apache + PHP-FPM + MySQL | |

---

## 二、系统数据流说明

1. 用户访问首页 → 看到收款地址 → 点击"复制"按钮 → 前端 JS 写入剪贴板，同时调用 `POST /api/copy.php` 记录一次复制事件到 `copy_events` 表 → 弹出提示框。
2. 用户使用钱包 App 向该地址转账 1 TRX（此步骤在链上完成，与本系统无直接交互）。
3. 该地址已绑定第三方能量平台的自动委托服务（**不在本系统开发范围**），第三方监测到转账后自动为转出地址下发能量。
4. 后台定时任务（cron）每隔数分钟调用 TronGrid API，拉取当前收款地址最新交易记录，筛选出金额为 1 TRX（含以上）的转入交易，按交易哈希去重后写入 `transfer_records` 表，用于统计"转账次数"。
5. 管理员登录后台后，数据面板通过 SQL 聚合查询 `copy_events` 与 `transfer_records` 表，得出复制次数、转账次数、转化率等指标。

---

## 三、前台页面设计

### 3.1 页面布局（自上而下）

1. **顶部 Logo**：居中展示，可在后台上传替换。
2. **右上角悬浮"在线客服"按钮**：点击在新标签页打开后台配置的第三方客服链接（`target="_blank" rel="noopener"`）。
3. **Logo 下方：网站标题**：文本内容后台可配置。
4. **标题下方：收款地址展示区**：
   - 使用等宽字体（如 `'Courier New', monospace`）完整展示 TRON 地址，避免字符混淆；
   - 一键复制按钮；
   - 点击复制后：`navigator.clipboard.writeText()` 写入剪贴板 + 弹出提示框，文案读取后台配置（4.3 节 2.6），默认文案为：

     > 地址已复制，请使用你的钱包向该地址转账1TRX，系统将自动为转账地址分配转账能量，如未及时收到能量，请联系在线客服

5. **规则说明区块**：渲染后台富文本编辑器保存的 HTML 内容，支持图文、列表等常见格式。


### 3.2 视觉与配色方案（基于 Logo 拆解）

Logo 为圆形徽章：浅蓝色径向光晕背景，主体为深藏青蓝色的"K9"与"TRX"字样，中间贯穿一条绿色过渡到金黄色的飘带装饰。据此拆解出以下配色变量，建议写入全局 CSS 变量：

```css
:root {
  --color-primary-navy: #1B3A6B;   /* 主色：深藏青蓝，用于标题、正文、主按钮底色 */
  --color-navy-dark:    #12294D;   /* 深色变体，用于hover/active状态 */
  --color-green:        #2FA84F;   /* 飘带渐变起点，用于强调色 */
  --color-gold:         #D9B23C;   /* 飘带渐变终点，用于强调色/点缀 */
  --color-bg-light:     #EAF4FC;   /* 背景浅蓝，用于页面背景渐变 */
  --color-white:        #FFFFFF;
  --gradient-accent: linear-gradient(90deg, var(--color-green) 0%, var(--color-gold) 100%);
}
```

设计建议：
- **页面背景**：`#EAF4FC` 到 `#FFFFFF` 的浅蓝径向/线性渐变，呼应 Logo 的光晕背景；
- **标题/正文文字**：深藏青 `#1B3A6B`，传达金融/区块链场景应有的稳重与信任感；
- **复制按钮**：藏青底 + 白字为主，hover 状态可叠加绿金渐变边框或底色，呼应 Logo 飘带；
- **地址展示卡片**：白色卡片 + 浅蓝色描边 + 圆角（12–16px）+ 轻微阴影，居中展示；
- **整体风格**：卡片式布局、圆角、轻阴影，符合 Logo 圆润、渐变的现代质感；
- **响应式**：移动端优先（大部分用户会通过手机钱包 App 操作），断点建议 `≤600px` 单栏堆叠布局。

### 3.3 交互细节
- 复制按钮点击反馈：图标由"复制"切换为"已复制"（✓），1.5 秒后恢复原状；
- 提示框（Toast/居中弹窗均可）文案读取后台"系统配置"中的复制提示文案字段，未配置时使用 3.1 节所述默认文案；
- 客服跳转链接需做基础 URL 格式校验，防止后台误填导致跳转异常。

---

## 四、后台管理系统设计

### 4.1 登录
用户名 + 密码登录；密码使用 `password_hash()` / `password_verify()` 存储与校验；基于 PHP Session 维持登录态；建议对连续登录失败增加简单的次数限制（如 5 次失败锁定 10 分钟）。

### 4.2 模块一：数据面板（登录后默认进入）

展示以下指标卡片（均针对**当前生效收款地址**）：

| 指标 | 说明 |
|---|---|
| 地址复制次数（累计） | 自当前地址启用以来的复制事件总数 |
| 昨日复制次数 | 前一自然日的复制次数 |
| 今日复制次数 | 当日实时复制次数 |
| 地址转账次数（累计） | 自当前地址启用以来，链上实际转入 1 TRX 的次数 |
| 昨日转账次数 | 前一自然日转账次数 |
| 今日转账次数 | 当日转账次数 |
| 转账转化率 | `转账次数 / 复制次数 × 100%`，建议同时展示"累计转化率"与"今日转化率" |

> 说明：转化率为**参考性指标**。复制事件与链上转账之间无法建立精确的一一对应关系（同一访客可能多次复制、不点复制直接转账、或复制后并未转账），该数字只反映"复制行为总量"与"实际转账总量"两者的比例关系，不代表逐笔追踪的转化路径，前端展示时建议附加此说明文字。

数据来源说明：
- **复制次数**：前台点击实时上报，写入 `copy_events` 表，面板通过 `SELECT COUNT(*) ... WHERE address=? AND DATE(created_at)=?` 聚合得出；
- **转账次数**：后台定时任务通过 TronGrid API 拉取当前收款地址的入账记录，写入 `transfer_records` 表（`tx_hash` 唯一索引保证去重），面板通过 `SELECT COUNT(*) ... WHERE address=? AND DATE(tx_timestamp)=?` 聚合得出；
- 若管理员在"系统配置"中更换了收款地址，数据面板默认统计"当前生效地址"自启用之日起的数据；历史地址数据仍完整保留在数据库中，本期不做历史地址数据的前端切换查看（列入后续扩展项）；
- 可选增强：近 7/30 天复制数 vs 转账数趋势折线图（Chart.js）。

### 4.3 模块二：系统配置

| 配置项 | 说明 |
|---|---|
| 2.1 Logo | 图片上传（jpg/png/svg），限制文件大小与类型，保存后前台立即生效 |
| 2.2 网站标题 | 文本输入框 |
| 2.3 客服链接 | URL 输入框，保存时做格式校验 |
| 2.4 收款地址 | TRON 地址输入框，保存时做格式校验（`T` 开头、34 位 Base58Check）；**保存前需重新输入当前登录密码进行二次确认**，通过后才允许写入，防止误操作或账号被盗导致收款地址被恶意篡改；每次变更写入 `address_history` 表（时间、操作人、旧地址、新地址），便于审计与历史数据关联 |
| 2.5 规则说明 | 富文本编辑器（Quill.js），支持加粗、列表、链接、图片等常见格式；保存为 HTML 存入 `config` 表，前台渲染前需做 XSS 过滤（后端白名单标签过滤，或引入 DOMPurify 做二次防护） |
| 2.6 复制提示文案 | 文本域，前台点击复制按钮后弹出的提示文案，默认值为 3.1 节所述文案，管理员可自行修改措辞 |
| 2.7 TronGrid API Key | 文本输入框（选填）；配置后，`cron/fetch_transfers.php` 请求 TronGrid 时在请求头中携带 `TRON-PRO-API-KEY`，可大幅提高查询频率上限，避免免费额度限流导致转账数据统计延迟或丢失 |

### 4.4 模块三：管理设置 — 账号管理
- 管理员账号列表：用户名 / 创建时间 / 最后登录时间；
- 新增 / 编辑 / 删除管理员账号；
- 修改密码：需校验当前密码，并对新密码做最基础的强度要求（长度 ≥ 8 位）；
- 建议（可作为后续增强项）：关键操作日志（改地址、改配置、账号增删）记录操作时间与操作人，便于追溯。

---

## 五、系统安装向导设计

为降低部署门槛，参考常见开源 PHP 程序（如 WordPress/Discuz）的做法，提供网页版分步安装向导，路径建议为 `/install/`。首次访问站点时，若检测到尚未安装，自动跳转至该向导。

### 5.1 安装流程

1. **环境检测**：检测 PHP 版本（≥8.0）、必需扩展（`pdo_mysql`、`curl`、`json`、`gd` 或 `fileinfo`）、关键目录写权限（`uploads/`、`logs/`、`config/`）。全部通过后才允许进入下一步，未通过则明确提示具体缺失项。
2. **数据库配置**：填写数据库主机、端口、库名、用户名、密码（表前缀可选）；提供"测试连接"按钮，后端通过 PDO 尝试连接并即时反馈成功/失败原因。
3. **自动建表**：连接测试成功后，一键执行内置 SQL 脚本，自动创建 `config`、`admins`、`address_history`、`copy_events`、`transfer_records` 等数据表（建表语句见第六章）。
4. **创建管理员账号**：填写超级管理员用户名、密码（二次输入确认），写入 `admins` 表。
5. **基础站点信息（可选）**：填写网站标题、初始收款地址；也可留空，后续在后台"系统配置"中补充。
6. **完成安装**：将数据库连接信息写入 `config/database.php`；同时在 `install/` 目录下生成 `install.lock` 锁文件，标记安装已完成，并跳转到后台登录页。

### 5.2 安装安全约束
- 每个安装步骤脚本执行前都需检查 `install.lock` 是否已存在，若存在则直接跳转到"系统已安装"提示页，拒绝重新执行安装流程，防止已上线站点被恶意重装、篡改数据库配置或重建管理员账号；
- 安装完成后，部署文档中应提示用户手动删除或重命名 `install/` 目录作为二次防护（`install.lock` 是主要防线，删除目录是纵深防御的补充手段）；
- `config/database.php` 需通过服务器配置（Nginx/Apache 规则）禁止 HTTP 直接访问，防止数据库账号密码泄露。

---

## 六、数据库设计（MySQL）

统一使用 `utf8mb4` 字符集、`InnoDB` 存储引擎。

### 6.1 `config` 表 — 站点配置（单行表，`id` 固定为 1）
```sql
CREATE TABLE `config` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `site_title` VARCHAR(100) NOT NULL DEFAULT 'TRX能量兑换',
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `customer_service_url` VARCHAR(255) DEFAULT NULL,
  `receive_address` VARCHAR(64) NOT NULL,
  `rules_html` TEXT,
  `copy_tip_text` TEXT COMMENT '复制成功后弹窗提示文案，为空时前端使用默认文案',
  `trongrid_api_key` VARCHAR(255) DEFAULT NULL COMMENT 'TronGrid API Key，选填，用于提高查询额度',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.2 `admins` 表 — 管理员账号
```sql
CREATE TABLE `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.3 `address_history` 表 — 收款地址变更历史
```sql
CREATE TABLE `address_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `enabled_at` DATETIME NOT NULL,
  `disabled_at` DATETIME DEFAULT NULL,
  `operator` VARCHAR(50) DEFAULT NULL COMMENT '操作的管理员用户名',
  INDEX `idx_address` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.4 `copy_events` 表 — 复制事件明细
```sql
CREATE TABLE `copy_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_address_created` (`address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
今日 / 昨日 / 累计复制次数均通过 `SELECT COUNT(*) ... WHERE address = ? AND DATE(created_at) = ?` 聚合查询得到，无需额外维护聚合计数表，`(address, created_at)` 联合索引保证查询效率。

### 6.5 `transfer_records` 表 — 链上转账记录
```sql
CREATE TABLE `transfer_records` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `tx_hash` VARCHAR(80) NOT NULL,
  `from_address` VARCHAR(64) NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL,
  `tx_timestamp` DATETIME NOT NULL,
  `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_tx_hash` (`tx_hash`),
  INDEX `idx_address_tx_time` (`address`, `tx_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
`tx_hash` 唯一索引用于定时任务写入时天然去重（写入时捕获唯一键冲突异常并跳过即可，无需在业务代码中额外查重）；转账次数统计基于 `tx_timestamp`（链上实际发生时间）而非 `fetched_at`（抓取时间），保证"今日/昨日"统计口径准确。

### 6.6 读写方式
统一通过 PDO + 预处理语句（Prepared Statement）访问数据库，杜绝 SQL 注入风险；建议封装轻量的 `Database` 单例类管理连接，业务代码通过该类执行查询，不直接拼接 SQL 字符串。

---

## 七、接口（API）设计

### 安装向导（首次部署使用）
| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/install/` | 环境检测页 |
| POST | `/install/step2.php` | 保存并测试数据库连接 |
| POST | `/install/step3.php` | 执行建表 SQL |
| POST | `/install/step4.php` | 创建超级管理员账号 |
| POST | `/install/finish.php` | 写入 `config/database.php` 与 `install.lock`，完成安装 |

### 前台接口
| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/api/copy.php` | 记录一次复制事件 |

> 前台首页配置数据（Logo、标题、地址、规则说明）建议由 PHP 直接服务端渲染，无需单独暴露 GET 接口。

### 后台接口（均需登录态）
| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/admin/login.php` | 登录 |
| POST | `/admin/logout.php` | 退出登录 |
| GET | `/admin/dashboard.php` | 获取数据面板统计数据 |
| POST | `/admin/config/save.php` | 保存系统配置（标题/客服链接/收款地址/规则说明/复制提示文案/API Key）；收款地址变更需在请求体中附带当前登录密码用于二次确认 |
| POST | `/admin/config/upload_logo.php` | 上传 Logo |
| GET/POST/PUT/DELETE | `/admin/accounts.php` | 管理员账号 CRUD |

### 定时任务（CLI，非 HTTP 接口）
| 脚本 | 说明 |
|---|---|
| `cron/fetch_transfers.php` | 建议每 2–5 分钟执行一次：调用 `GET https://api.trongrid.io/v1/accounts/{address}/transactions`，若后台已配置 TronGrid API Key，则在请求头携带 `TRON-PRO-API-KEY` 以提高限额；筛选转入且金额 ≥1 TRX 的交易，写入 `transfer_records` 表（唯一索引自动去重） |

Crontab 示例：
```
*/3 * * * * php /path/to/project/cron/fetch_transfers.php >> /path/to/project/logs/cron.log 2>&1
```

### 应急命令行工具（CLI，仅限服务器本地/SSH 环境执行）
| 脚本 | 说明 |
|---|---|
| `cli/reset_password.php` | 管理员密码丢失时的应急恢复工具，用法：`php cli/reset_password.php --username=admin --password=NewPassw0rd`，执行后直接更新 `admins` 表对应账号的 `password_hash` 字段。该脚本不对外暴露 HTTP 入口，仅可通过服务器命令行执行 |

---

## 八、安全性与健壮性考虑

2. 后台登录：Session + CSRF Token，登录失败次数限制；
3. 后台修改收款地址时，需重新输入当前登录密码进行二次确认后才允许保存（见 4.3 节 2.4），二次密码写死在同目录下.env文件，不放在数据库
4. 数据库访问统一使用 PDO 预处理语句，杜绝 SQL 注入；`config/database.php` 禁止 HTTP 直接访问；
5. 安装向导：完成后写入 `install.lock`，所有安装脚本执行前校验该文件是否存在，防止重复安装/被恶意重装；
6. 富文本内容（规则说明）需做 XSS 过滤，避免管理员账号被盗后注入恶意脚本影响前台访客；
7. TRON 地址保存时做格式校验（正则 + Base58Check），防止误填导致客户转错地址；
8. Logo 上传需校验文件类型、大小，并重命名存储，避免任意文件上传漏洞；
9. TronGrid 免费额度存在速率限制，`fetch_transfers.php` 需做请求失败重试与限流处理；后台已提供 API Key 配置项（4.3 节 2.7），建议部署时尽早在 TronGrid 官网免费申请并填入；
10. 提供应急命令行密码重置脚本 `cli/reset_password.php`（用法见第七章），避免唯一管理员密码丢失后无法登录后台；该脚本仅限服务器本地/SSH 环境执行。

---

## 九、部署说明

### 建议目录结构
```
project/
├── public/          # 前台入口(index.php)与静态资源
├── admin/           # 后台管理页面与逻辑
├── api/             # 前台接口
├── install/         # 安装向导（安装完成后建议删除或重命名）
├── cron/            # 定时任务脚本
├── cli/             # 命令行应急工具（如密码重置），仅限服务器本地/SSH执行
├── includes/        # 公共类库(Database、Auth等)
├── config/          # database.php等配置文件(禁止外部直接访问)
├── uploads/         # Logo等上传文件
└── logs/            # 日志文件
```

### 环境要求
- PHP 8.0+，需开启 `pdo_mysql`、`curl`、`json` 扩展；
- MySQL 5.7+ / 8.0，需提前创建好空数据库供安装向导写入（或允许安装向导所填账号具备建库权限）；
- 生产环境必须启用 HTTPS，并配置 HTTP → HTTPS 强制跳转；
- `uploads/`、`logs/`、`config/` 目录需赋予 Web 服务写权限；
- 需配置 crontab 定时执行 `cron/fetch_transfers.php`。

---

## 十、本期不涉及范围（Out of Scope）

- 能量委托/下发的具体实现（已由第三方能量平台通过地址绑定自动完成）；
- 收到的 TRX 资金处理逻辑（退款、提现等）；
- 多语言支持；
- 短信/邮件通知。

---

