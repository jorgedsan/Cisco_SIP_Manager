<?php
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$j = function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); };
$cur = $_GET['view'] ?? 'list';
?>
<script>window.csmI18n = <?= $j(\FreePBX\modules\Ciscosipmanager\JsStrings::get()) ?>;</script>
<div class="container-fluid csm">
	<h1>Cisco SIP Manager</h1>
	<ul class="nav nav-tabs" style="margin-bottom:15px">
		<li class="<?= $cur === 'list' ? 'active' : '' ?>"><a href="?display=ciscosipmanager"><?= _('Phones') ?></a></li>
		<li class="<?= $cur === 'form' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=form"><?= _('Add phone') ?></a></li>
		<li class="<?= $cur === 'import' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=import"><?= _('Import existing SEP files') ?></a></li>
		<li class="<?= $cur === 'inventory' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=inventory"><?= _('Inventory') ?></a></li>
		<li class="<?= $cur === 'vpn' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=vpn"><?= _('VPN sessions') ?></a></li>
		<li class="<?= $cur === 'media' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=media"><?= _('Backgrounds and ringtones') ?></a></li>
		<li class="<?= $cur === 'settings' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=settings"><?= _('Settings') ?></a></li>
	</ul>
	<?php foreach ($flash as $f): ?>
		<div class="alert alert-<?= $h($f[0]) ?>"><?= nl2br($h($f[1])) ?></div>
	<?php endforeach; ?>
