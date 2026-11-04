<?php include __DIR__ . '/_nav.php'; ?>

<p>Archivos <code>SEP*.cnf.xml</code> en <code><?= $h($settings['tftp_dir']) ?></code> que todavía no están en el módulo.
Importar <b>no modifica los archivos</b>: guarda una copia en el historial y da de alta el teléfono.
Lo que el módulo no genera (servicios, teclas extra, etc.) pasa al XML extra y las líneas 2 en adelante a "Líneas adicionales".</p>

<div class="alert alert-warning">Antes de regenerar los importados, completá en <a href="?display=ciscosipmanager&amp;view=settings">Ajustes</a>
la URL y el certificado de la VPN, el firmware por modelo y las URLs de servicios: al regenerar, <code>vpnGroup</code>,
<code>loadInformation</code>, <code>capfAuthMode</code> y los demás elementos que genera el módulo salen de Ajustes, no del archivo original.</div>

<?php if (!$scan): ?>
	<div class="alert alert-info">No hay archivos SEP sin importar en ese directorio.</div>
<?php else: ?>
<form method="post">
	<input type="hidden" name="csm_action" value="import_selected">
	<table class="table table-condensed">
		<thead><tr><th style="width:1%"><input type="checkbox" id="csm-imp-all"></th><th>Archivo</th><th>Ext.</th><th>Etiqueta</th><th>Modelo</th><th>Ubicación</th><th>Se conserva</th><th>Avisos</th></tr></thead>
		<tbody>
		<?php foreach ($scan as $r): $bad = (bool)$r['errors']; ?>
			<tr class="<?= $bad ? 'danger' : ($r['warnings'] ? 'warning' : '') ?>">
				<td><input type="checkbox" class="csm-imp" name="import[]" value="<?= $h($r['mac']) ?>" <?= $bad ? 'disabled' : '' ?>></td>
				<td><code><?= $h($r['file']) ?></code></td>
				<td><?= $h($r['extension']) ?></td>
				<td><?= $h($r['label']) ?></td>
				<td>
					<select class="form-control input-sm" name="model[<?= $h($r['mac']) ?>]" <?= $bad ? 'disabled' : '' ?>>
						<?php if (!$r['model']): ?><option value="">— elegir —</option><?php endif; ?>
						<?php foreach ($models as $k => $m): ?>
							<option value="<?= $h($k) ?>" <?= $r['model'] === (string)$k ? 'selected' : '' ?>><?= $h($m[0]) ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><?= $h(['auto' => 'Auto', 'lan' => 'LAN', 'vpn' => 'Siempre VPN'][$r['network'] ?? ''] ?? '') ?><?= $r['vpn'] ? ' <i class="fa fa-lock text-info" title="Con VPN"></i>' : '' ?></td>
				<td><small>
					<?php if ($r['extra_lines']): ?><?= (int)$r['extra_lines'] ?> línea(s) adicional(es)<br><?php endif; ?>
					<?php if ($r['extra_names']): ?><?= $h(implode(', ', $r['extra_names'])) ?><?php endif; ?>
				</small></td>
				<td><small><?= nl2br($h(implode("\n", array_merge($r['errors'], $r['warnings'])))) ?></small></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<button class="btn btn-primary">Importar seleccionados</button>
	<a class="btn btn-default" href="?display=ciscosipmanager">Cancelar</a>
</form>
<p class="help-block" style="margin-top:15px">Después de importar, en cada teléfono usá <i>Acciones → Comparar con lo que se generaría</i>.
Recién cuando el diff te convenza, <i>Regenerar SEP</i>. "Regenerar todos" no toca los importados hasta que los regeneres una vez.</p>
<script>
document.getElementById('csm-imp-all').addEventListener('change', function () {
	var on = this.checked;
	document.querySelectorAll('.csm-imp:not([disabled])').forEach(function (c) { c.checked = on; });
});
</script>
<?php endif; ?>
</div>
