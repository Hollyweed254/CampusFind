<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$rows = q('SELECT a.*, u.full_name AS admin_name FROM admin_audit_logs a JOIN users u ON u.id = a.admin_id
           ORDER BY a.created_at DESC LIMIT 200')->fetchAll();
$pageTitle = 'Audit log'; require __DIR__ . '/../includes/header.php'; ?>
<div class="page-head">
  <div>
    <h1 class="page-title">Audit log</h1>
  </div>
</div>
<?php include __DIR__ . '/_nav.php'; ?>
<?php if ($rows): ?>
  <div class="table-responsive table-card"><table class="table align-middle mb-0">
    <thead><tr><th>Time</th><th>Administrator</th><th>Action</th><th>Entity</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="meta text-nowrap"><?= e(date('j M Y H:i', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['admin_name']) ?></td>
          <td><span class="chip"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
          <td class="small"><?= e($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></td>
          <td class="small"><?= e($r['notes'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
<?php else: ?>
  <div class="empty-state"><div class="empty-state-icon"><i class="bi bi-journal-text"></i></div><h2>No actions recorded</h2><p>Administrative actions appear here.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';