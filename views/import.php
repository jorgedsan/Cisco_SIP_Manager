<?php include __DIR__ . '/_nav.php'; ?>

<p><?= sprintf(_('<code>SEP*.cnf.xml</code> files in %s that are not in the module yet. Importing <b>does not modify the files</b>: it saves a copy in the history and adds the phone. Whatever the module does not generate (services, extra keys, etc.) goes to the extra XML, and lines 2 onwards go to "Additional lines".'), '<code>' . $h($settings['tftp_dir']) . '</code>') ?></p>

<div class="alert alert-warning"><?= sprintf(_('Before regenerating the imported phones, fill in under <a href="%s">Settings</a> the VPN URL and certificate, the firmware per model and the service URLs: when regenerating, <code>vpnGroup</code>, <code>loadInformation</code>, <code>capfAuthMode</code> and the other elements the module generates come from Settings, not from the original file.'), '?display=ciscosipmanager&amp;view=settings') ?></div>

<?php if (!$scan): ?>
	<div class="alert alert-info"><?= _('There are no SEP files left to import in that directory.') ?></div>
<?php else: ?>
<form method="post">
	<input type="hidden" name="csm_action" value="import_selected">
	<table class="table table-condensed">
		<thead><tr><th style="width:1%"><input type="checkbox" id="csm-imp-all"></th><th><?= _('File') ?></th><th><?= _('Ext.') ?></th><th><?= _('Label') ?></th><th><?= _('Model') ?></th><th><?= _('Location') ?></th><th><?= _('Kept') ?></th><th><?= _('Warnings') ?></th></tr></thead>
		<tbody>
		<?php foreach ($scan as $r): $bad = (bool)$r['errors']; ?>
			<tr class="<?= $bad ? 'danger' : ($r['warnings'] ? 'warning' : '') ?>">
				<td><input type="checkbox" class="csm-imp" name="import[]" value="<?= $h($r['mac']) ?>" <?= $bad ? 'disabled' : '' ?>></td>
				<td><code><?= $h($r['file']) ?></code></td>
				<td><?= $h($r['extension']) ?></td>
				<td><?= $h($r['label']) ?></td>
				<td>
					<select class="form-control input-sm" name="model[<?= $h($r['mac']) ?>]" <?= $bad ? 'disabled' : '' ?>>
						<?php if (!$r['model']): ?><option value="">— <?= _('choose') ?> —</option><?php endif; ?>
						<?php foreach ($models as $k => $m): ?>
							<option value="<?= $h($k) ?>" <?= $r['model'] === (string)$k ? 'selected' : '' ?>><?= $h($m[0]) ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><?= $h(['auto' => 'Auto', 'lan' => 'LAN', 'vpn' => _('Always VPN')][$r['network'] ?? ''] ?? '') ?><?= $r['vpn'] ? ' <i class="fa fa-lock text-info" title="' . $h(_('With VPN')) . '"></i>' : '' ?></td>
				<td><small>
					<?php if ($r['extra_lines']): ?><?= $h(sprintf(_('%d additional line(s)'), (int)$r['extra_lines'])) ?><br><?php endif; ?>
					<?php if ($r['extra_names']): ?><?= $h(implode(', ', $r['extra_names'])) ?><?php endif; ?>
				</small></td>
				<td><small><?= nl2br($h(implode("\n", array_merge($r['errors'], $r['warnings'])))) ?></small></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<button class="btn btn-primary"><?= _('Import selected') ?></button>
	<a class="btn btn-default" href="?display=ciscosipmanager"><?= _('Cancel') ?></a>
</form>
<p class="help-block" style="margin-top:15px"><?= _('After importing, use <i>Actions → Compare with what would be generated</i> on each phone. Only when the diff looks right, <i>Regenerate SEP</i>. "Regenerate all" does not touch imported phones until you regenerate them once.') ?></p>
<script>
document.getElementById('csm-imp-all').addEventListener('change', function () {
	var on = this.checked;
	document.querySelectorAll('.csm-imp:not([disabled])').forEach(function (c) { c.checked = on; });
});
</script>
<?php endif; ?>
</div>
