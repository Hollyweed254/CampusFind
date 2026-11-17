<?php /* expects $fallbackCategory and $fallbackTone (1-4) */ ?>
<?php $fallbackTone = max(1, min(4, (int)($fallbackTone ?? 1))); $fallbackIcon = cat_icon($fallbackCategory); ?>
<div class="art art-tone-<?= $fallbackTone ?>" aria-hidden="true">
  <i class="bi <?= e($fallbackIcon) ?> art-ghost"></i>
  <span class="art-icon"><i class="bi <?= e($fallbackIcon) ?>"></i></span>
</div>
