<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/trust.php';

$me = require_login();
$meId = (int)$me['id'];
$convId = (int)($_GET['c'] ?? 0);
$conv = $convId ? chat_get($convId, $meId) : null;
if ($convId && !$conv) { http_response_code(404); exit('Conversation not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)($_POST['c'] ?? 0);
    $target = chat_get($cid, $meId);
    if (!$target) { http_response_code(403); exit('Conversation not found.'); }
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'send') {
        $r = chat_send($cid, $meId, clean('body'), $_FILES['image'] ?? null);
        flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Message sent.' : $r['error']);
        redirect('messages.php?c=' . $cid);
    }
    if ($action === 'block' || $action === 'unblock') {
        $other = (int)$target['buyer_id'] === $meId ? (int)$target['seller_id'] : (int)$target['buyer_id'];
        if ($action === 'block') chat_block($meId, $other); else chat_unblock($meId, $other);
        flash('success', $action === 'block' ? 'You blocked this user.' : 'You unblocked this user.');
        redirect('messages.php?c=' . $cid);
    }
}

if ($conv) chat_mark_read((int)$conv['id'], $meId);

$conversations = chat_list($meId);
$totalConversations = count($conversations);
$unreadMessages = 0;
foreach ($conversations as $c) $unreadMessages += (int)$c['unread'];

$messages = [];
$other = null;
$otherUser = null;
$otherBadges = [];
$primaryBadge = null;
$nameVerified = false;
$subtitle = 'CampusFind member';
$blocked = false;
$tx = null;
$readUpto = 0;

if ($conv) {
    $messages = chat_messages((int)$conv['id']);
    $otherId = (int)$conv['buyer_id'] === $meId ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
    $otherName = (int)$conv['buyer_id'] === $meId ? $conv['seller_name'] : $conv['buyer_name'];
    $other = ['id' => $otherId, 'name' => $otherName];
    $otherUser = seller_public($otherId);
    $otherBadges = $otherUser ? seller_badges($otherUser) : [];
    foreach (['student', 'trusted', 'email', 'phone'] as $key) {
        foreach ($otherBadges as $b) {
            if ($b['key'] === $key) { $primaryBadge = $b; break 2; }
        }
    }
    $nameVerified = $primaryBadge && in_array($primaryBadge['key'], ['student', 'trusted'], true);
    $role = $otherId === (int)$conv['seller_id'] ? 'Seller' : 'Buyer';
    $memberSince = !empty($otherUser['created_at']) ? date('M Y', strtotime($otherUser['created_at'])) : '';
    if ($otherUser && $otherUser['status'] !== 'active') {
        $subtitle = 'Account unavailable';
    } elseif ($primaryBadge) {
        $subtitle = $primaryBadge['label'] . ' · ' . $role;
    } elseif ($memberSince) {
        $subtitle = $role . ' · Member since ' . $memberSince;
    }
    $blocked = chat_blocked($meId, $otherId);
    $tx = q('SELECT id, status FROM transactions WHERE listing_id = ? AND buyer_id = ? AND seller_id = ? ORDER BY id DESC LIMIT 1',
            [(int)$conv['listing_id'], (int)$conv['buyer_id'], (int)$conv['seller_id']])->fetch();
    $readUpto = (int)q('SELECT COALESCE(MAX(id), 0) FROM messages
                        WHERE conversation_id = ? AND sender_id = ? AND read_at IS NOT NULL',
                       [(int)$conv['id'], $meId])->fetchColumn();
}

$composeListings = q("SELECT l.id, l.title, l.price, cat.listing_type, u.full_name AS seller,
                             (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.id LIMIT 1) AS image_path
                      FROM listings l
                      JOIN categories cat ON cat.id = l.category_id
                      JOIN users u ON u.id = l.seller_id
                      WHERE l.seller_id <> ? AND l.status = 'available' AND l.moderation_status = 'approved'
                        AND u.status = 'active'
                      ORDER BY l.created_at DESC, l.id DESC LIMIT 30", [$meId])->fetchAll();

$pageTitle = 'Messages';
require __DIR__ . '/includes/header.php';
?>
<div class="messenger<?= $conv ? ' is-open' : '' ?>" id="messenger" data-me="<?= $meId ?>">
  <aside class="msg-side" aria-label="Conversations">
    <div class="msg-side-head">
      <h1>Messages</h1>
      <button class="msg-compose-btn" type="button" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="bi bi-pencil-square"></i>Compose
      </button>
    </div>

    <label class="msg-search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="convSearch" placeholder="Search conversations" aria-label="Search conversations" autocomplete="off">
    </label>

    <div class="msg-filters" role="group" aria-label="Filter conversations">
      <button class="msg-filter is-active" type="button" data-filter="all">All<span><?= $totalConversations ?></span></button>
      <button class="msg-filter" type="button" data-filter="unread">Unread<span id="unreadTotal"><?= $unreadMessages ?></span></button>
    </div>

    <div class="conv-list" id="convList">
      <?php if ($conversations): ?>
        <?php foreach ($conversations as $c):
          $active = $conv && (int)$conv['id'] === (int)$c['id'];
          $rowVerified = !empty($c['other_student_verified']) || !empty($c['other_trusted_at']);
          $preview = trim((string)($c['last_body'] ?? ''));
          if ($preview === '' && !empty($c['last_attachment'])) $preview = 'Photo';
          if ($preview === '') $preview = 'New conversation';
          if ((int)$c['last_sender'] === $meId) $preview = 'You: ' . $preview;
          $rowTime = chat_time_label($c['last_message_at'] ?: $c['created_at'], true);
          $search = mb_strtolower($c['other_name'] . ' ' . $c['title'] . ' ' . $preview, 'UTF-8');
        ?>
          <a class="conv-row<?= $active ? ' is-active' : '' ?>" href="<?= url('messages.php?c=' . (int)$c['id']) ?>"
             data-unread="<?= (int)$c['unread'] ?>" data-search="<?= e($search) ?>">
            <span class="conv-avatar">
              <?= e(initials($c['other_name'])) ?>
              <?php if ($rowVerified): ?><span class="conv-verify" aria-hidden="true"><i class="bi bi-patch-check-fill"></i></span><?php endif; ?>
            </span>
            <span class="conv-body">
              <span class="conv-top">
                <strong><?= e($c['other_name']) ?></strong>
                <?php if ($rowVerified): ?><i class="bi bi-patch-check-fill conv-check" aria-label="Verified"></i><?php endif; ?>
                <time datetime="<?= e($c['last_message_at'] ?: $c['created_at']) ?>"><?= e($rowTime) ?></time>
              </span>
              <span class="conv-preview"><?= e($preview) ?></span>
            </span>
            <?php if ((int)$c['unread'] > 0): ?>
              <span class="conv-badge"><?= (int)$c['unread'] > 99 ? '99+' : (int)$c['unread'] ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="conv-list-empty">
          <i class="bi bi-chat-square-text" aria-hidden="true"></i>
          <p>No conversations yet.</p>
          <a class="msg-ghost-btn" href="<?= url('marketplace.php') ?>">Browse listings</a>
        </div>
      <?php endif; ?>
      <div class="conv-empty" id="convEmpty" hidden>
        <i class="bi bi-search" aria-hidden="true"></i>
        <p>No conversations match.</p>
      </div>
    </div>
  </aside>

  <section class="msg-main" aria-label="Conversation">
    <?php if (!$conv): ?>
      <div class="msg-main-empty">
        <span class="msg-empty-mark"><i class="bi bi-chat-heart" aria-hidden="true"></i></span>
        <h2>Your messages</h2>
        <p>Pick a conversation from the list, or start one from a listing you like.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
          <a class="msg-lime-btn" href="<?= url('marketplace.php') ?>">Browse listings</a>
          <button class="msg-ghost-btn" type="button" data-bs-toggle="modal" data-bs-target="#composeModal">New message</button>
        </div>
      </div>
    <?php else: ?>
      <header class="msg-head">
        <a class="msg-back" href="<?= url('messages.php') ?>" aria-label="Back to conversations"><i class="bi bi-arrow-left"></i></a>
        <div class="msg-person">
          <span class="msg-avatar-lg">
            <?= e(initials($other['name'])) ?>
            <?php if ($nameVerified): ?>
              <span class="msg-avatar-badge" title="<?= e($primaryBadge['hint']) ?>"><i class="bi bi-patch-check-fill"></i></span>
            <?php endif; ?>
          </span>
          <div class="msg-person-text">
            <a class="msg-name" href="<?= url('seller.php?id=' . $other['id']) ?>">
              <?= e($other['name']) ?>
              <?php if ($nameVerified): ?><i class="bi bi-patch-check-fill msg-name-check" aria-label="Verified"></i><?php endif; ?>
            </a>
            <span class="msg-sub"><?= e($subtitle) ?></span>
          </div>
        </div>
        <div class="msg-head-actions">
          <a class="msg-profile-btn" href="<?= url('seller.php?id=' . $other['id']) ?>">View profile</a>
          <div class="dropdown">
            <button class="msg-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Conversation options">
              <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= url('listing.php?id=' . (int)$conv['listing_id']) ?>"><i class="bi bi-box-arrow-up-right"></i>View listing</a></li>
              <?php if ($tx): ?>
                <li><a class="dropdown-item" href="<?= url('transaction.php?id=' . (int)$tx['id']) ?>"><i class="bi bi-receipt"></i>View transaction</a></li>
              <?php elseif ((int)$conv['buyer_id'] === $meId): ?>
                <li><a class="dropdown-item" href="<?= url('transaction-new.php?listing=' . (int)$conv['listing_id']) ?>"><i class="bi bi-receipt"></i>Record transaction</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('report-user.php?reported=' . $other['id'] . '&context=chat&conversation=' . (int)$conv['id']) ?>"><i class="bi bi-flag"></i>Report conversation</a></li>
              <li>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int)$conv['id'] ?>">
                  <input type="hidden" name="action" value="<?= $blocked ? 'unblock' : 'block' ?>">
                  <button class="dropdown-item" type="submit"><i class="bi bi-slash-circle"></i><?= $blocked ? 'Unblock user' : 'Block user' ?></button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </header>

      <?php
      $photo = listing_photo($conv['image_path'] ?? null);
      $isAvailable = $conv['listing_status'] === 'available';
      $typeLabel = ($conv['listing_type'] ?? '') === 'service' ? 'Service' : 'Item';
      ?>
      <div class="msg-listing">
        <?php if ($photo): ?>
          <img src="<?= e($photo) ?>" alt="">
        <?php else: ?>
          <span class="msg-listing-fallback"><i class="bi bi-<?= $typeLabel === 'Service' ? 'stars' : 'bag' ?>"></i></span>
        <?php endif; ?>
        <div class="msg-listing-text">
          <a class="msg-listing-title" href="<?= url('listing.php?id=' . (int)$conv['listing_id']) ?>"><?= e($conv['title']) ?></a>
          <span class="msg-listing-meta">
            <span class="msg-price"><?= e(format_price($conv['price'], $conv['listing_type'] ?? null)) ?></span>
            <span class="msg-chip<?= $isAvailable ? ' is-ok' : '' ?>"><?= $isAvailable ? 'Available' : 'Unavailable' ?></span>
            <span class="msg-type"><?= $typeLabel ?></span>
          </span>
        </div>
        <a class="msg-view-listing" href="<?= url('listing.php?id=' . (int)$conv['listing_id']) ?>">
          <span>View listing</span><i class="bi bi-arrow-up-right"></i>
        </a>
      </div>

      <div class="msg-thread" id="chatMessages" data-c="<?= (int)$conv['id'] ?>" data-last="<?= $messages ? (int)end($messages)['id'] : 0 ?>"
           aria-live="polite" aria-label="Messages">
        <?php foreach ($messages as $m): $mine = (int)$m['sender_id'] === $meId; $read = (int)$m['id'] <= $readUpto; ?>
          <div class="mm-msg <?= $mine ? 'mm-mine' : 'mm-theirs' ?>" data-id="<?= (int)$m['id'] ?>">
            <?php if (!$mine): ?><span class="mm-avatar" aria-hidden="true"><?= e(initials($other['name'])) ?></span><?php endif; ?>
            <div class="mm-wrap">
              <div class="mm-bubble">
                <?php if (!empty($m['attachment_id'])): ?>
                  <a class="mm-photo" href="<?= url('attachment.php?id=' . (int)$m['attachment_id']) ?>" target="_blank" rel="noopener">
                    <img src="<?= url('attachment.php?id=' . (int)$m['attachment_id']) ?>" alt="<?= e($m['original_name'] ?? 'Attachment') ?>" loading="lazy">
                  </a>
                <?php endif; ?>
                <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
                <?php if ($mine): ?>
                  <span class="mm-stamp"><?= e(chat_time_label($m['created_at'])) ?><i class="bi <?= $read ? 'bi-check2-all' : 'bi-check2' ?>" aria-hidden="true"></i></span>
                <?php endif; ?>
              </div>
              <?php if (!$mine): ?><span class="mm-time"><?= e(chat_time_label($m['created_at'])) ?></span><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
          <div class="mm-empty">
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
            <p>No messages yet.</p>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($blocked): ?>
        <div class="msg-blocked">
          <span><i class="bi bi-slash-circle" aria-hidden="true"></i> Messaging is blocked.</span>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="c" value="<?= (int)$conv['id'] ?>">
            <input type="hidden" name="action" value="unblock">
            <button class="msg-ghost-btn" type="submit">Unblock</button>
          </form>
        </div>
      <?php else: ?>
        <form class="msg-composer" method="post" enctype="multipart/form-data" id="chatForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="c" value="<?= (int)$conv['id'] ?>">
          <div class="msg-compose-pill">
            <label class="msg-tool" title="Attach a photo">
              <i class="bi bi-paperclip" aria-hidden="true"></i>
              <span class="visually-hidden">Attach a photo</span>
              <input type="file" name="image" accept="image/jpeg,image/png,image/webp" id="chatImage">
            </label>
            <textarea name="body" id="chatBody" rows="1" maxlength="2000" placeholder="Type a message..." aria-label="Message"></textarea>
            <div class="dropdown msg-emoji">
              <button class="msg-emoji-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Insert emoji">
                <i class="bi bi-emoji-smile" aria-hidden="true"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-end msg-emoji-menu">
                <?php foreach (['😀','😄','😊','😉','😍','🤔','😅','😢','😮','👍','🙏','🔥','✅','🎉','❤️','👋'] as $emoji): ?>
                  <button type="button" data-emoji="<?= e($emoji) ?>"><?= e($emoji) ?></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <button class="msg-send" type="submit" aria-label="Send message"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
          <div class="msg-attach-name" id="chatAttachName" hidden></div>
          <div class="msg-error" id="msgError" role="alert" hidden></div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<div class="modal fade msg-modal" id="composeModal" tabindex="-1" aria-labelledby="composeTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h2 id="composeTitle">New message</h2>
          <p>Choose a listing to start a conversation.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="compose-search">
          <i class="bi bi-search" aria-hidden="true"></i>
          <input type="search" id="composeSearch" placeholder="Search listings" aria-label="Search listings" autocomplete="off">
        </label>
        <div class="compose-list" id="composeList">
          <?php foreach ($composeListings as $l):
            $lp = listing_photo($l['image_path'] ?? null);
            $search = mb_strtolower($l['title'] . ' ' . $l['seller'], 'UTF-8');
          ?>
            <form class="compose-item" method="post" action="<?= url('chat-start.php') ?>" data-search="<?= e($search) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="listing" value="<?= (int)$l['id'] ?>">
              <?php if ($lp): ?>
                <img src="<?= e($lp) ?>" alt="">
              <?php else: ?>
                <span class="compose-fallback"><i class="bi bi-<?= ($l['listing_type'] ?? '') === 'service' ? 'stars' : 'bag' ?>"></i></span>
              <?php endif; ?>
              <span class="compose-text">
                <strong><?= e($l['title']) ?></strong>
                <small><?= e($l['seller']) ?> · <?= e(format_price($l['price'], $l['listing_type'] ?? null)) ?></small>
              </span>
              <button type="submit" aria-label="Message about <?= e($l['title']) ?>"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>
          <?php endforeach; ?>
          <?php if (!$composeListings): ?>
            <p class="compose-empty">There are no listings available to message right now.</p>
          <?php endif; ?>
        </div>
        <p class="compose-empty" id="composeEmpty" hidden>No listings match your search.</p>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const messenger = document.getElementById('messenger');

  // Conversation search + All/Unread filter
  const search = document.getElementById('convSearch');
  const filters = Array.from(document.querySelectorAll('.msg-filter'));
  const rows = Array.from(document.querySelectorAll('.conv-row'));
  const empty = document.getElementById('convEmpty');
  let filter = 'all';

  function applyConvFilter() {
    const q = (search.value || '').trim().toLowerCase();
    let shown = 0;
    rows.forEach(function (row) {
      const matchText = !q || (row.dataset.search || '').includes(q);
      const matchTab = filter === 'all' || Number(row.dataset.unread || 0) > 0;
      const show = matchText && matchTab;
      row.hidden = !show;
      if (show) shown++;
    });
    empty.hidden = shown > 0 || rows.length === 0;
  }
  if (search) search.addEventListener('input', applyConvFilter);
  filters.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filters.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
      filter = btn.dataset.filter;
      applyConvFilter();
    });
  });

  // Compose modal listing search
  const composeSearch = document.getElementById('composeSearch');
  const composeModal = document.getElementById('composeModal');
  const composeEmpty = document.getElementById('composeEmpty');
  const composeItems = Array.from(document.querySelectorAll('.compose-item'));
  function filterCompose() {
    const q = (composeSearch.value || '').trim().toLowerCase();
    let shown = 0;
    composeItems.forEach(function (item) {
      const show = !q || (item.dataset.search || '').includes(q);
      item.hidden = !show;
      if (show) shown++;
    });
    if (composeEmpty) composeEmpty.hidden = shown > 0 || !composeItems.length;
  }
  if (composeSearch) composeSearch.addEventListener('input', filterCompose);
  if (composeModal) composeModal.addEventListener('hidden.bs.modal', function () {
    composeSearch.value = '';
    filterCompose();
  });

  const thread = document.getElementById('chatMessages');
  const form = document.getElementById('chatForm');
  if (!thread || !form) return;

  const body = document.getElementById('chatBody');
  const image = document.getElementById('chatImage');
  const attachName = document.getElementById('chatAttachName');
  const errorBox = document.getElementById('msgError');
  const conv = thread.dataset.c;
  const meId = Number(messenger.dataset.me);
  const otherName = <?= json_encode($conv ? $other['name'] : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let last = parseInt(thread.dataset.last || '0', 10);
  let readUpto = <?= (int)$readUpto ?>;

  function nearBottom() { return thread.scrollHeight - thread.scrollTop - thread.clientHeight < 140; }
  function scroll() { thread.scrollTop = thread.scrollHeight; }
  scroll();

  function markRead(upTo) {
    if (!upTo || upTo <= readUpto) return;
    readUpto = upTo;
    thread.querySelectorAll('.mm-mine').forEach(function (el) {
      if (Number(el.dataset.id) <= upTo) {
        const icon = el.querySelector('.mm-stamp i');
        if (icon) icon.className = 'bi bi-check2-all';
      }
    });
  }

  function bubble(m) {
    const mine = Number(m.sender_id) === meId;
    const wrap = document.createElement('div');
    wrap.className = 'mm-msg ' + (mine ? 'mm-mine' : 'mm-theirs');
    wrap.dataset.id = m.id;

    if (!mine) {
      const av = document.createElement('span');
      av.className = 'mm-avatar';
      av.setAttribute('aria-hidden', 'true');
      av.textContent = otherName.split(/\s+/).filter(Boolean).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase();
      wrap.appendChild(av);
    }

    const inner = document.createElement('div');
    inner.className = 'mm-wrap';
    const b = document.createElement('div');
    b.className = 'mm-bubble';

    if (m.attachment_id) {
      const a = document.createElement('a');
      a.className = 'mm-photo'; a.href = m.attachment_url; a.target = '_blank'; a.rel = 'noopener';
      const img = document.createElement('img'); img.src = m.attachment_url; img.alt = m.attachment_name || 'Attachment'; img.loading = 'lazy';
      a.appendChild(img); b.appendChild(a);
    }
    if (m.body) {
      const p = document.createElement('p');
      m.body.split(/\n/).forEach(function (line, i) {
        if (i) p.appendChild(document.createElement('br'));
        p.appendChild(document.createTextNode(line));
      });
      b.appendChild(p);
    }

    const stamp = document.createElement('span');
    stamp.className = 'mm-stamp';
    stamp.textContent = m.time_label || m.time || '';
    if (mine) {
      const tick = document.createElement('i');
      tick.className = 'bi ' + (Number(m.id) <= readUpto ? 'bi-check2-all' : 'bi-check2');
      tick.setAttribute('aria-hidden', 'true');
      stamp.appendChild(tick);
      b.appendChild(stamp);
    }
    inner.appendChild(b);

    if (!mine) {
      const time = document.createElement('span');
      time.className = 'mm-time';
      time.textContent = m.time_label || m.time || '';
      inner.appendChild(time);
    }
    wrap.appendChild(inner);
    return wrap;
  }

  function append(m) {
    const emptyEl = thread.querySelector('.mm-empty');
    if (emptyEl) emptyEl.remove();
    thread.appendChild(bubble(m));
    last = Math.max(last, Number(m.id));
  }

  function showError(text) {
    if (!errorBox) return;
    errorBox.textContent = text || '';
    errorBox.hidden = !text;
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const r = await fetch('<?= url('api/chat.php') ?>?action=poll&c=' + encodeURIComponent(conv) + '&after=' + last,
                            {headers: {'Accept': 'application/json'}});
      if (!r.ok) return;
      const data = await r.json();
      if (!data.ok) return;
      if (Array.isArray(data.messages) && data.messages.length) {
        const stick = nearBottom();
        data.messages.forEach(append);
        if (stick) scroll();
      }
      if (data.read_upto) markRead(Number(data.read_upto));
    } catch (e) {}
  }
  setInterval(poll, 4000);

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const text = body.value.trim();
    if (!text && !image.files.length) { body.focus(); return; }
    const fd = new FormData(form);
    const btn = form.querySelector('.msg-send');
    btn.disabled = true;
    showError('');
    try {
      const r = await fetch('<?= url('api/chat.php') ?>?action=send', {
        method: 'POST', body: fd, headers: {'Accept': 'application/json'}
      });
      const data = await r.json();
      if (data.ok) {
        body.value = ''; image.value = '';
        if (attachName) { attachName.textContent = ''; attachName.hidden = true; }
        body.style.height = 'auto';
        if (data.message) { append(data.message); scroll(); }
        if (data.read_upto) markRead(Number(data.read_upto));
      } else {
        showError(data.error || 'Message could not be sent.');
      }
    } catch (e) {
      showError('Message could not be sent. Check your connection and try again.');
    }
    btn.disabled = false;
    body.focus();
  });

  body.addEventListener('input', function () {
    body.style.height = 'auto';
    body.style.height = Math.min(132, body.scrollHeight) + 'px';
  });
  body.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
  });
  image.addEventListener('change', function () {
    const name = image.files.length ? image.files[0].name : '';
    if (attachName) { attachName.textContent = name; attachName.hidden = !name; }
  });

  document.querySelectorAll('.msg-emoji-menu [data-emoji]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const emoji = btn.dataset.emoji || '';
      const start = body.selectionStart ?? body.value.length;
      const end = body.selectionEnd ?? body.value.length;
      body.value = body.value.slice(0, start) + emoji + body.value.slice(end);
      body.selectionStart = body.selectionEnd = start + emoji.length;
      body.focus();
      body.dispatchEvent(new Event('input'));
      const toggle = btn.closest('.msg-emoji')?.querySelector('[data-bs-toggle="dropdown"]');
      if (toggle && window.bootstrap) window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>


