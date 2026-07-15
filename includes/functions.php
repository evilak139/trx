<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = [], string $message = 'ok'): void
{
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

function json_error(string $message, int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}

/** 获取客户端真实 IP（不信任可伪造的头，仅取 REMOTE_ADDR） */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function client_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** 校验客服链接：仅允许 http/https 协议的合法 URL */
function is_valid_service_url(string $url): bool
{
    if ($url === '') {
        return true; // 允许留空
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * 富文本白名单过滤，防止后台"规则说明"字段被用于 XSS 注入。
 * 基于 DOMDocument 实现的标签/属性白名单过滤器。
 */
function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote',
        'ul', 'ol', 'li', 'a', 'img', 'span', 'div',
        'h1', 'h2', 'h3', 'h4',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'code',
    ];
    $allowedAttrs = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        '*' => ['class'],
    ];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="__root__">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    $root = $doc->getElementById('__root__');
    if (!$root) {
        return '';
    }

    sanitize_html_node($root, $allowedTags, $allowedAttrs);

    $result = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $result .= $doc->saveHTML($child);
    }

    return $result;
}

function sanitize_html_node(DOMNode $node, array $allowedTags, array $allowedAttrs): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_COMMENT_NODE) {
            $node->removeChild($child);
            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = strtolower($child->nodeName);

        if (!in_array($tag, $allowedTags, true)) {
            // 不认识/不允许的标签：保留子节点文本，剥离标签本身（如 <script> 直接连内容一起丢弃）
            if (!in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                foreach (iterator_to_array($child->childNodes) as $grandChild) {
                    $node->insertBefore($grandChild, $child);
                }
            }
            $node->removeChild($child);
            continue;
        }

        if ($child->hasAttributes()) {
            foreach (iterator_to_array($child->attributes) as $attr) {
                $attrName = strtolower($attr->nodeName);
                $allowed = array_merge($allowedAttrs[$tag] ?? [], $allowedAttrs['*'] ?? []);

                if (!in_array($attrName, $allowed, true)) {
                    $child->removeAttribute($attr->nodeName);
                    continue;
                }

                if (in_array($attrName, ['href', 'src'], true)) {
                    $value = trim((string) $attr->nodeValue);
                    if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
                        $child->removeAttribute($attr->nodeName);
                        continue;
                    }
                }

                if ($attrName === 'class') {
                    $classes = array_filter(
                        preg_split('/\s+/', (string) $attr->nodeValue) ?: [],
                        static fn (string $c): bool => str_starts_with($c, 'ql-')
                    );
                    if (empty($classes)) {
                        $child->removeAttribute('class');
                    } else {
                        $child->setAttribute('class', implode(' ', $classes));
                    }
                }
            }
        }

        if ($tag === 'a') {
            $child->setAttribute('rel', 'noopener noreferrer');
            $child->setAttribute('target', '_blank');
        }

        sanitize_html_node($child, $allowedTags, $allowedAttrs);
    }
}
