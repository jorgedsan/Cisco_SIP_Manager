<?php
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$cur = $_GET['view'] ?? 'list';
?>
<div class="container-fluid csm">
	<h1>Cisco SIP Manager</h1>
	<ul class="nav nav-tabs" style="margin-bottom:15px">
		<li class="<?= $cur === 'list' ? 'active' : '' ?>"><a href="?display=ciscosipmanager">Teléfonos</a></li>
		<li class="<?= $cur === 'form' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=form">Agregar teléfono</a></li>
		<li class="<?= $cur === 'import' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=import">Importar SEP existentes</a></li>
		<li class="<?= $cur === 'inventory' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=inventory">Inventario</a></li>
		<li class="<?= $cur === 'vpn' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=vpn">Sesiones VPN</a></li>
		<li class="<?= $cur === 'media' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=media">Fondos y tonos</a></li>
		<li class="<?= $cur === 'settings' ? 'active' : '' ?>"><a href="?display=ciscosipmanager&amp;view=settings">Ajustes</a></li>
	</ul>
	<?php foreach ($flash as $f): ?>
		<div class="alert alert-<?= $h($f[0]) ?>"><?= nl2br($h($f[1])) ?></div>
	<?php endforeach; ?>
