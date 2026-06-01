<?php
/**
 * Markdown 解析器
 * 简单的 Markdown 转 HTML 转换
 */
class Markdown {
    /**
     * 将 Markdown 转换为 HTML
     */
    public static function parse($text) {
        if (empty($text)) {
            return '';
        }
        
        // 保护代码块
        $codeBlocks = [];
        $text = preg_replace_callback('/```([^\n]*)\n(.*?)\n```/su', function($matches) use (&$codeBlocks) {
            $lang = trim($matches[1]);
            $code = $matches[2];
            $placeholder = '___CODE_BLOCK_' . count($codeBlocks) . '___';
            $codeBlocks[$placeholder] = '<pre><code' . ($lang ? ' class="language-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '') . '>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</code></pre>';
            return $placeholder;
        }, $text);
        
        // 保护行内代码
        $inlineCode = [];
        $text = preg_replace_callback('/`([^`]+)`/u', function($matches) use (&$inlineCode) {
            $placeholder = '___INLINE_CODE_' . count($inlineCode) . '___';
            $inlineCode[$placeholder] = '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
            return $placeholder;
        }, $text);
        
        // 分行处理
        $lines = explode("\n", $text);
        $html = '';
        $inList = false;
        $listType = '';
        $inBlockquote = false;
        $inTable = false;
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);
            
            // 空行处理
            if (empty($trimmed)) {
                if ($inList) {
                    $html .= $listType === 'ul' ? '</ul>' : '</ol>';
                    $inList = false;
                }
                if ($inBlockquote) {
                    $html .= '</blockquote>';
                    $inBlockquote = false;
                }
                if ($inTable) {
                    $html .= '</table>';
                    $inTable = false;
                }
                $html .= "\n";
                continue;
            }
            
            // 标题
            if (preg_match('/^(#{1,6})\s+(.+)$/u', $trimmed, $matches)) {
                $level = strlen($matches[1]);
                $title = $matches[2];
                $html .= "<h{$level}>" . self::parseInline($title, $inlineCode, $codeBlocks) . "</h{$level}>\n";
                continue;
            }
            
            // 水平线
            if (preg_match('/^(---|\*\*\*|___)$/u', $trimmed)) {
                $html .= "<hr>\n";
                continue;
            }
            
            // 块引用
            if (preg_match('/^>\s+(.+)$/u', $trimmed, $matches)) {
                if (!$inBlockquote) {
                    $html .= '<blockquote>';
                    $inBlockquote = true;
                }
                $html .= '<p>' . self::parseInline($matches[1], $inlineCode, $codeBlocks) . '</p>';
                continue;
            }
            
            // 无序列表
            if (preg_match('/^[\*\-\+]\s+(.+)$/u', $trimmed, $matches)) {
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                    $listType = 'ul';
                } elseif ($listType !== 'ul') {
                    $html .= '</ol><ul>';
                    $listType = 'ul';
                }
                $html .= '<li>' . self::parseInline($matches[1], $inlineCode, $codeBlocks) . '</li>';
                continue;
            }
            
            // 有序列表
            if (preg_match('/^\d+\.\s+(.+)$/u', $trimmed, $matches)) {
                if (!$inList) {
                    $html .= '<ol>';
                    $inList = true;
                    $listType = 'ol';
                } elseif ($listType !== 'ol') {
                    $html .= '</ul><ol>';
                    $listType = 'ol';
                }
                $html .= '<li>' . self::parseInline($matches[1], $inlineCode, $codeBlocks) . '</li>';
                continue;
            }
            
            // 表格
            if (strpos($trimmed, '|') !== false) {
                if (!$inTable) {
                    $html .= '<table style="border-collapse: collapse; width: 100%; margin: 15px 0;">';
                    $inTable = true;
                }
                
                $cells = array_map('trim', explode('|', $trimmed));
                $cells = array_filter($cells); // 移除空单元格
                
                // 检查是否是分隔符行（只包含 - 和 :）
                $isSeparator = false;
                if (count($cells) > 0) {
                    $isSeparator = true;
                    foreach ($cells as $cell) {
                        if (!preg_match('/^[\s\-:]+$/u', $cell)) {
                            $isSeparator = false;
                            break;
                        }
                    }
                }
                
                // 如果是分隔符行，跳过
                if ($isSeparator) {
                    continue;
                }
                
                // 检查是否是表头行（下一行是分隔符）
                $isHeader = false;
                if ($i + 1 < count($lines)) {
                    $nextLine = trim($lines[$i + 1]);
                    if (strpos($nextLine, '|') !== false) {
                        $nextCells = array_map('trim', explode('|', $nextLine));
                        $nextCells = array_filter($nextCells);
                        $isHeader = true;
                        foreach ($nextCells as $cell) {
                            if (!preg_match('/^[\s\-:]+$/u', $cell)) {
                                $isHeader = false;
                                break;
                            }
                        }
                    }
                }
                
                if ($isHeader) {
                    $html .= '<thead><tr>';
                    foreach ($cells as $cell) {
                        $html .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left; background: #f5f5f5;">' . self::parseInline($cell, $inlineCode, $codeBlocks) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                } else {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $html .= '<td style="border: 1px solid #ddd; padding: 8px;">' . self::parseInline($cell, $inlineCode, $codeBlocks) . '</td>';
                    }
                    $html .= '</tr>';
                }
                continue;
            }
            
            // 普通段落
            if ($inList) {
                $html .= $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            if ($inBlockquote) {
                $html .= '</blockquote>';
                $inBlockquote = false;
            }
           // 普通段落
            $html .= '<p>' . self::parseInline($trimmed, $inlineCode, $codeBlocks) . '</p>' . "\n";
        }
        
        // 关闭未关闭的标签
        if ($inList) {
            $html .= $listType === 'ul' ? '</ul>' : '</ol>';
        }
        if ($inBlockquote) {
            $html .= '</blockquote>';
        }
        if ($inTable) {
            $html .= '</table>';
        }
        
        // 恢复代码块
        foreach ($codeBlocks as $placeholder => $code) {
            $html = str_replace($placeholder, $code, $html);
        }
        
        // 恢复行内代码
        foreach ($inlineCode as $placeholder => $code) {
            $html = str_replace($placeholder, $code, $html);
        }
        
        return $html;
    }
    
    /**
     * 解析行内元素
     */
    private static function parseInline($text, &$inlineCode = [], &$codeBlocks = []) {
        // 保护占位符，不处理它们
        $placeholders = [];
        // 匹配多种占位符格式：___CODE_BLOCK_0___ 或 PLACEHOLDER0_ 或 INLINECODE0 等
        $text = preg_replace_callback('/(___[A-Z_]+_\d+___|PLACEHOLDER\d+_?|INLINECODE\d+|CODEBLOCK\d+)/', function($matches) use (&$placeholders) {
            $placeholder = $matches[1];
            $key = '___PLACEHOLDER_' . count($placeholders) . '___';
            $placeholders[$key] = $placeholder;
            return $key;
        }, $text);
        
        // 图片必须在链接之前处理，因为图片语法包含 ![]()
        // 图片: ![alt](url)
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/u', function($matches) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<img src="' . $src . '" alt="' . $alt . '" style="max-width: 100%; height: auto;">';
        }, $text);
        
        // 粗体
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $text);
        
        // 斜体
        $text = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/u', '<em>$1</em>', $text);
        
        // 删除线
        $text = preg_replace('/~~(.+?)~~/u', '<del>$1</del>', $text);
        
        // 链接: [text](url)
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/u', function($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
        }, $text);
        
        // 恢复占位符
        foreach ($placeholders as $key => $placeholder) {
            $text = str_replace($key, $placeholder, $text);
        }
        
        return $text;
    }
}
?>
