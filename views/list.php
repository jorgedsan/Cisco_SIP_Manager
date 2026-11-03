<?php include __DIR__ . '/_nav.php'; ?>

<div class="row" style="margin-bottom:10px">
	<div class="col-sm-5">
		<span class="label label-success">LAN <?= $h($settings['lan_cidr']) ?></span>
		<span class="label label-info">VPN <?= $h($settings['vpn_cidr']) ?></span>
		<span class="label label-warning">Otra red</span>
		<span class="label label-default">Offline</span>
		&nbsp;<small class="text-muted" id="csm-updated"></small>
	</div>
	<div class="col-sm-7 text-right">
		<form method="post" style="display:inline">
			<input type="hidden" name="csm_action" value="regen_all">
			<button class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Regenerar todos los SEP</button>
		</form>
		<button class="btn btn-warning btn-sm csm-all" data-act="restart" title="Reinicia el software del teléfono (restart): rápido, sin apagar el equipo"><i class="fa fa-repeat"></i> Restart todos</button>
		<button class="btn btn-danger btn-sm csm-all" data-act="reset" title="Reinicio completo del equipo (reboot)"><i class="fa fa-power-off"></i> Reboot todos</button>
		<label style="font-weight:normal;margin:0 6px" title="Espera que cada teléfono vuelva a registrarse antes de reiniciar el siguiente">
			<input type="checkbox" id="csm-all-stagger" checked> de a uno</label>
		<button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#csm-msg-modal" data-id="all"><i class="fa fa-bullhorn"></i> Mensaje a todos</button>
	</div>
</div>

<div id="csm-bulk" class="well well-sm" style="display:none;margin-bottom:8px">
	<b><span id="csm-bulk-count">0</span> seleccionado(s):</b>
	<button class="btn btn-default btn-xs csm-bulk" data-act="regen">Regenerar SEP</button>
	<button class="btn btn-default btn-xs csm-bulk" data-act="checkcfg">Recargar config</button>
	<button class="btn btn-default btn-xs csm-bulk" data-act="restart">Reiniciar</button>
	<button class="btn btn-default btn-xs csm-bulk" data-act="reset">Reset completo</button>
	<button class="btn btn-default btn-xs csm-bulk" data-act="message">Mensaje…</button>
	<label style="font-weight:normal;margin-left:12px" title="Para reinicios: espera que cada teléfono vuelva a registrarse antes de seguir con el próximo">
		<input type="checkbox" id="csm-bulk-stagger" checked> de a uno, esperando que vuelva a registrar</label>
</div>

<table class="table table-striped table-condensed" id="csm-table">
	<thead>
		<tr>
			<th style="width:1%"><input type="checkbox" id="csm-sel-all" title="Seleccionar todos"></th><th>Ext.</th><th>Nombre</th><th>Modelo</th><th>MAC</th><th>Red</th><th>IP</th>
			<th>Latencia</th><th>Firmware</th><th>VPN</th><th>SEP</th><th style="width:1%">Acciones</th>
		</tr>
	</thead>
	<tbody>
	<?php if (!$phones): ?>
		<tr><td colspan="12" class="text-center text-muted">No hay teléfonos. <a href="?display=ciscosipmanager&amp;view=form">Agregá uno</a> o <a href="?display=ciscosipmanager&amp;view=import">importá tus SEP existentes</a>.</td></tr>
	<?php endif; ?>
	<?php foreach ($phones as $p): ?>
		<tr data-id="<?= (int)$p['id'] ?>" data-ext="<?= $h($p['extension']) ?>" data-label="<?= $h($p['label']) ?>" data-pending="<?= $p['last_generated'] ? 0 : 1 ?>">
			<td><input type="checkbox" class="csm-sel"></td>
			<td><strong><?= $h($p['extension']) ?></strong></td>
			<td><?= $h($p['label']) ?></td>
			<td><?= $h($models[$p['model']][0] ?? $p['model']) ?></td>
			<td><code>SEP<?= $h($p['mac']) ?></code></td>
			<td class="csm-net"><span class="label label-default">…</span>
				<?php if ($p['network'] !== 'auto'): ?><small class="text-muted">(fijo: <?= $h($p['network']) ?>)</small><?php endif; ?></td>
			<td class="csm-ip"></td>
			<td class="csm-lat"></td>
			<td class="csm-ua"><small></small></td>
			<td><?= $p['vpn_enabled'] ? '<i class="fa fa-lock text-info" title="Perfil VPN incluido"></i>' : '' ?></td>
			<td><?php if ($p['last_generated']): ?><small class="text-muted"><?= date('d/m H:i', $p['last_generated']) ?></small>
				<?php else: ?><span class="label label-warning" title="Importado: el archivo es el original. Compará antes de regenerar.">importado</span><?php endif; ?></td>
			<td style="white-space:nowrap">
				<div class="btn-group">
					<button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown">Acciones <span class="caret"></span></button>
					<ul class="dropdown-menu dropdown-menu-right">
						<li><a href="#" class="csm-diffgen">Comparar con lo que se generaría</a></li>
						<li><a href="#" class="csm-act" data-act="regen"<?= $p['last_generated'] ? '' : ' data-confirm-import="1"' ?>>Regenerar SEP</a></li>
						<li><a href="#" class="csm-act" data-act="checkcfg">Recargar config (check-sync)</a></li>
						<li><a href="#" class="csm-act" data-act="restart">Reiniciar (restart)</a></li>
						<li><a href="#" class="csm-act" data-act="reset" data-confirm="1">Reset completo (reboot)</a></li>
						<li class="divider"></li>
						<li><a href="#" data-toggle="modal" data-target="#csm-msg-modal" data-id="<?= (int)$p['id'] ?>">Enviar mensaje…</a></li>
						<li><a href="#" class="csm-screen">Ver pantalla y control remoto</a></li>
						<li><a href="#" class="csm-call">Llamar desde este teléfono…</a></li>
						<li><a href="#" class="csm-preview">Ver XML generado</a></li>
						<li><a href="#" class="csm-history">Historial de versiones…</a></li>
						<li class="divider"></li>
						<li><a href="?display=ciscosipmanager&amp;view=form&amp;id=<?= (int)$p['id'] ?>" class="csm-edit">Editar…</a></li>
						<li><a href="#" class="csm-del text-danger">Eliminar</a></li>
					</ul>
				</div>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<form method="post" id="csm-del-form">
	<input type="hidden" name="csm_action" value="delete_phone">
	<input type="hidden" name="id" value="">
</form>

<div class="modal fade" id="csm-msg-modal" tabindex="-1">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Enviar mensaje</h4></div>
		<div class="modal-body">
			<input type="hidden" id="csm-msg-id">
			<div class="form-group"><label>Título</label><input class="form-control" id="csm-msg-title" value="Aviso" maxlength="32"></div>
			<div class="form-group"><label>Texto</label><textarea class="form-control" id="csm-msg-text" rows="3" maxlength="400"></textarea></div>
			<div class="checkbox"><label><input type="checkbox" id="csm-msg-beep" checked> Sonar tono</label></div>
			<div id="csm-msg-result"></div>
		</div>
		<div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cerrar</button><button class="btn btn-primary" id="csm-msg-send">Enviar</button></div>
	</div></div>
</div>

<div class="modal fade" id="csm-hist-modal" tabindex="-1">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Historial de versiones <small id="csm-hist-title"></small></h4></div>
		<div class="modal-body">
			<div id="csm-hist-warn"></div>
			<table class="table table-condensed table-hover" style="margin-bottom:10px">
				<thead><tr><th>Fecha</th><th>Origen</th><th>Tamaño</th><th></th><th style="width:1%"></th></tr></thead>
				<tbody id="csm-hist-rows"></tbody>
			</table>
			<div id="csm-hist-view" style="display:none">
				<h5 id="csm-hist-view-title"></h5>
				<pre id="csm-hist-pre" style="max-height:45vh;overflow:auto;font-size:11px"></pre>
			</div>
			<p class="help-block"><small>Se guarda fuera del directorio de provisión: <code id="csm-hist-dir"></code>.
			Restaurar escribe esa versión en el teléfono, pero no cambia los datos del módulo: al regenerar se vuelve a armar desde los datos actuales.</small></p>
		</div>
	</div></div>
</div>

<div class="modal fade" id="csm-screen-modal" tabindex="-1">
	<div class="modal-dialog" style="width:auto;max-width:1180px"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>
			<h4 class="modal-title">Pantalla <small id="csm-screen-title"></small></h4></div>
		<div class="modal-body">
			<div class="row">
				<div class="col-md-8 text-center">
					<div id="csm-screen-msg" class="text-muted"></div>
					<img id="csm-screen-img" alt="" style="max-width:100%;image-rendering:pixelated;border:1px solid #ccc;display:none">
					<div class="btn-group btn-group-sm" style="margin-top:6px;display:flex;justify-content:center;gap:3px">
						<?php for ($i = 1; $i <= 5; $i++): ?><button class="btn btn-default csm-key" data-key="Soft<?= $i ?>" style="min-width:70px">Soft <?= $i ?></button><?php endfor; ?>
					</div>
				</div>
				<div class="col-md-4 csm-keypad" style="text-align:center">
					<div style="margin-bottom:6px">
						<button class="btn btn-default btn-sm csm-key" data-key="NavUp"><i class="fa fa-chevron-up"></i></button><br>
						<button class="btn btn-default btn-sm csm-key" data-key="NavLeft"><i class="fa fa-chevron-left"></i></button>
						<button class="btn btn-primary btn-sm csm-key" data-key="NavSelect">OK</button>
						<button class="btn btn-default btn-sm csm-key" data-key="NavRight"><i class="fa fa-chevron-right"></i></button><br>
						<button class="btn btn-default btn-sm csm-key" data-key="NavDwn"><i class="fa fa-chevron-down"></i></button>
						<button class="btn btn-default btn-sm csm-key" data-key="NavBack" title="Atrás"><i class="fa fa-reply"></i></button>
					</div>
					<div style="display:grid;grid-template-columns:repeat(3,48px);gap:4px;justify-content:center;margin-bottom:6px">
						<?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', 'Star' => '*', '0', 'Pound' => '#'] as $k => $lbl):
							$key = is_int($k) ? 'KeyPad' . $lbl : 'KeyPad' . $k; ?>
							<button class="btn btn-default btn-sm csm-key" data-key="<?= $key ?>"><?= $h($lbl) ?></button>
						<?php endforeach; ?>
					</div>
					<div style="display:grid;grid-template-columns:repeat(3,auto);gap:4px;justify-content:center">
						<button class="btn btn-default btn-xs csm-key" data-key="Speaker"><i class="fa fa-volume-up"></i> Altavoz</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Headset"><i class="fa fa-headphones"></i> Auricular</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Mute"><i class="fa fa-microphone-slash"></i> Mute</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Hold"><i class="fa fa-pause"></i> Espera</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Transfer">Transferir</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Conference">Conferencia</button>
						<button class="btn btn-default btn-xs csm-key" data-key="VolDwn"><i class="fa fa-volume-down"></i> Vol −</button>
						<button class="btn btn-default btn-xs csm-key" data-key="VolUp"><i class="fa fa-volume-up"></i> Vol +</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Line1">Línea 1</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Messages"><i class="fa fa-envelope"></i> Mensajes</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Directories"><i class="fa fa-book"></i> Directorio</button>
						<button class="btn btn-default btn-xs csm-key" data-key="Settings"><i class="fa fa-cog"></i> Ajustes</button>
					</div>
					<div class="input-group input-group-sm" style="margin-top:10px">
						<input class="form-control" id="csm-screen-dial" placeholder="Número a marcar" maxlength="32">
						<span class="input-group-btn"><button class="btn btn-success" id="csm-screen-dial-btn"><i class="fa fa-phone"></i> Llamar</button></span>
					</div>
					<p class="help-block" style="margin-top:8px"><small>Las teclas se mandan por <code>/CGI/Execute</code>; la pantalla se actualiza sola después de cada una.
					Si un modelo no tiene una tecla, la ignora.</small></p>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<small class="text-muted pull-left" id="csm-screen-time" style="margin-top:7px"></small>
			<label style="font-weight:normal;margin-right:10px"><input type="checkbox" id="csm-screen-auto"> Actualizar cada 3 s</label>
			<button class="btn btn-default" id="csm-screen-refresh"><i class="fa fa-refresh"></i> Actualizar</button>
			<button class="btn btn-default" data-dismiss="modal">Cerrar</button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="csm-call-modal" tabindex="-1">
	<div class="modal-dialog modal-sm"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Llamar <small id="csm-call-title"></small></h4></div>
		<div class="modal-body">
			<input class="form-control" id="csm-call-number" placeholder="Número o extensión" maxlength="32">
			<p class="help-block"><small>El teléfono marca solo (<code>Dial:</code>) y la llamada sale por altavoz.</small></p>
			<div id="csm-call-result"></div>
		</div>
		<div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Cerrar</button><button class="btn btn-success" id="csm-call-go"><i class="fa fa-phone"></i> Llamar</button></div>
	</div></div>
</div>

<div class="modal fade" id="csm-bulk-modal" tabindex="-1" data-backdrop="static">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header"><h4 class="modal-title" id="csm-bulk-title">Acción masiva</h4></div>
		<div class="modal-body">
			<div class="progress"><div class="progress-bar" id="csm-bulk-bar" style="width:0%"></div></div>
			<div id="csm-bulk-log" style="max-height:45vh;overflow:auto;font-family:monospace;font-size:12px"></div>
		</div>
		<div class="modal-footer">
			<button class="btn btn-warning" id="csm-bulk-stop">Detener</button>
			<button class="btn btn-default" id="csm-bulk-close" data-dismiss="modal" disabled>Cerrar</button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="csm-edit-modal" tabindex="-1" data-backdrop="static">
	<div class="modal-dialog modal-lg" style="width:auto;max-width:1000px"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Editar teléfono <small id="csm-edit-title"></small></h4></div>
		<div class="modal-body">
			<div id="csm-edit-msg"></div>
			<div id="csm-edit-body"></div>
		</div>
	</div></div>
</div>

<div class="modal fade" id="csm-xml-modal" tabindex="-1">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">SEP generado</h4></div>
		<div class="modal-body"><pre id="csm-xml" style="max-height:60vh;overflow:auto"></pre></div>
	</div></div>
</div>
</div>
<!-- assets/js/ciscosipmanager.js lo carga FreePBX automáticamente -->
