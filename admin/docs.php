<?php
/**
 * 后台管理 - 文档管理
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/class.database.php';
require_once __DIR__ . '/../includes/class.docs.php';
require_once __DIR__ . '/../includes/class.markdown.php';
require_once __DIR__ . '/common.php';

checkAdminLogin();

$docs = new Docs();

// 创建文档
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'content' => $_POST['content'] ?? '',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? 'help',
        'sort' => intval($_POST['sort'] ?? 0),
        'status' => intval($_POST['status'] ?? 1)
    ];
    
    if (empty($data['title']) || empty($data['slug'])) {
        redirectAfterPost('docs.php', '标题和URL别名不能为空', 'danger');
    } else {
        if ($docs->createDoc($data)) {
            logOperation('创建文档', $data['title']);
            redirectAfterPost('docs.php', '文档创建成功', 'success');
        } else {
            redirectAfterPost('docs.php', '创建失败，URL别名可能已存在', 'danger');
        }
    }
}

// 更新文档
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['id']);
    $data = [
        'title' => $_POST['title'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'content' => $_POST['content'] ?? '',
        'description' => $_POST['description'] ?? '',
        'category' => $_POST['category'] ?? 'help',
        'sort' => intval($_POST['sort'] ?? 0),
        'status' => intval($_POST['status'] ?? 1)
    ];
    
    if (empty($data['title']) || empty($data['slug'])) {
        redirectAfterPost('docs.php', '标题和URL别名不能为空', 'danger');
    } else {
        if ($docs->updateDoc($id, $data)) {
            logOperation('更新文档', "ID: {$id}");
            redirectAfterPost('docs.php', '文档更新成功', 'success');
        } else {
            redirectAfterPost('docs.php', '更新失败，URL别名可能已被使用', 'danger');
        }
    }
}

// 删除文档
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($docs->deleteDoc($id)) {
        logOperation('删除文档', "ID: {$id}");
        redirectAfterPost('docs.php', '文档已删除', 'success');
    } else {
        redirectAfterPost('docs.php', '删除失败', 'danger');
    }
}

// 获取编辑的文档
$editDoc = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $editDoc = $docs->getDocById($id);
}

// 获取所有文档
$allDocs = $docs->getAllDocs(null, null);

renderHeader('文档管理', 'docs');
?>

<?php echo getPrgMessage(); ?>

<div class="panel">
    <div class="panel-header">
        <h2><?php echo $editDoc ? '编辑文档' : '创建文档'; ?></h2>
    </div>
    <div class="panel-body">
        <form method="POST" class="form">
            <input type="hidden" name="action" value="<?php echo $editDoc ? 'update' : 'create'; ?>">
            <?php if ($editDoc): ?>
                <input type="hidden" name="id" value="<?php echo $editDoc['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>文档标题 *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editDoc['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>URL别名 *</label>
                    <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($editDoc['slug'] ?? ''); ?>" placeholder="如: getting-started" required>
                    <small style="color: var(--gray-500);">用于生成链接，如: /docs/getting-started.html</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>分类</label>
                    <select name="category" class="form-control">
                        <option value="help" <?php echo ($editDoc['category'] ?? 'help') === 'help' ? 'selected' : ''; ?>>帮助</option>
                        <option value="docs" <?php echo ($editDoc['category'] ?? '') === 'docs' ? 'selected' : ''; ?>>文档</option>
                        <option value="guide" <?php echo ($editDoc['category'] ?? '') === 'guide' ? 'selected' : ''; ?>>指南</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>排序权重</label>
                    <input type="number" name="sort" class="form-control" value="<?php echo $editDoc['sort'] ?? 0; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?php echo ($editDoc['status'] ?? 1) == 1 ? 'selected' : ''; ?>>发布</option>
                        <option value="0" <?php echo ($editDoc['status'] ?? 1) == 0 ? 'selected' : ''; ?>>草稿</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>文档描述</label>
                <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($editDoc['description'] ?? ''); ?>" placeholder="简短描述，用于列表显示">
            </div>
            
            <div class="form-group">
                <label>文档内容 * 
                    <span style="font-size: 12px; color: var(--gray-500); margin-left: 10px;">
                        <input type="checkbox" id="markdown-mode" <?php echo ($editDoc && strpos($editDoc['content'], '#') === 0) ? 'checked' : ''; ?>> 
                        Markdown 模式
                    </span>
                </label>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleMarkdownHelp()">
                        <i class="fa fa-question-circle"></i> Markdown 帮助
                    </button>
                </div>
                <textarea name="content" id="content-editor" class="form-control" style="min-height: 300px; font-family: 'Courier New', monospace;" required><?php echo htmlspecialchars($editDoc['content'] ?? ''); ?></textarea>
                <small style="color: var(--gray-500);">支持 Markdown 和 HTML 标签</small>
            </div>
            
            <div id="markdown-help" style="display: none; background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 15px; font-size: 13px;">
                <h4 style="margin-top: 0;">Markdown 语法帮助</h4>
                <div style="columns: 2; gap: 20px;">
                    <div>
                        <strong># 标题</strong><br>
                        <code># H1</code>, <code>## H2</code>, <code>### H3</code> 等<br><br>
                        
                        <strong>文本格式</strong><br>
                        <code>**粗体**</code> 或 <code>__粗体__</code><br>
                        <code>*斜体*</code> 或 <code>_斜体_</code><br>
                        <code>~~删除线~~</code><br><br>
                        
                        <strong>列表</strong><br>
                        <code>- 项目</code> 或 <code>* 项目</code><br>
                        <code>1. 项目</code><br><br>
                    </div>
                    <div>
                        <strong>代码</strong><br>
                        <code>`行内代码`</code><br>
                        <code>```语言<br>代码块<br>```</code><br><br>
                        
                        <strong>链接和图片</strong><br>
                        <code>[文本](URL)</code><br>
                        <code>![alt](图片URL)</code><br><br>
                        
                        <strong>其他</strong><br>
                        <code>> 引用</code><br>
                        <code>---</code> 水平线<br>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?php echo $editDoc ? '更新文档' : '创建文档'; ?>
                </button>
                <?php if ($editDoc): ?>
                    <a href="docs.php" class="btn btn-secondary">
                        <i class="fa fa-times"></i> 取消编辑
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
function toggleMarkdownHelp() {
    const help = document.getElementById('markdown-help');
    help.style.display = help.style.display === 'none' ? 'block' : 'none';
}

// 编辑器快捷键
document.getElementById('content-editor').addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        const textarea = this;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.substring(start, end);
        
        if (e.key === 'b') {
            e.preventDefault();
            const wrapped = '**' + selected + '**';
            textarea.value = textarea.value.substring(0, start) + wrapped + textarea.value.substring(end);
            textarea.selectionStart = start + 2;
            textarea.selectionEnd = start + 2 + selected.length;
        } else if (e.key === 'i') {
            e.preventDefault();
            const wrapped = '*' + selected + '*';
            textarea.value = textarea.value.substring(0, start) + wrapped + textarea.value.substring(end);
            textarea.selectionStart = start + 1;
            textarea.selectionEnd = start + 1 + selected.length;
        }
    }
});
</script>

<div class="panel" style="margin-top: 30px;">
    <div class="panel-header">
        <h2>文档列表</h2>
    </div>
    <div class="panel-body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>URL别名</th>
                        <th>分类</th>
                        <th>状态</th>
                        <th>浏览</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allDocs as $doc): ?>
                    <tr>
                        <td><?php echo $doc['id']; ?></td>
                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                        <td><code><?php echo htmlspecialchars($doc['slug']); ?></code></td>
                        <td>
                            <?php 
                            $categoryMap = ['help' => '帮助', 'docs' => '文档', 'guide' => '指南'];
                            echo $categoryMap[$doc['category']] ?? $doc['category'];
                            ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $doc['status'] == 1 ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $doc['status'] == 1 ? '已发布' : '草稿'; ?>
                            </span>
                        </td>
                        <td><?php echo $doc['views']; ?></td>
                        <td><?php echo $doc['create_time']; ?></td>
                        <td>
                            <a href="docs.php?edit=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                            <a href="docs.php?delete=<?php echo $doc['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除吗？');">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
