<?php include __DIR__ . '/_nav.php'; ?>

<p><?= _('Data each phone reports in <code>/DeviceInformationX</code>. It is stored when refreshing; unregistered phones show the last known data.') ?>
<button class="btn btn-primary btn-sm pull-right" id="csm-inv-refresh"><i class="fa fa-refresh"></i> <?= _('Refresh all') ?></button></p>
<div id="csm-inv-result" style="margin:8px 0"></div>

<table class="table table-striped table-condensed">
	<thead><tr><th><?= _('Ext.') ?></th><th><?= _('Name') ?></th><th><?= _('Model (module)') ?></th><th><?= _('Actual model') ?></th><th><?= _('Serial number') ?></th><th>Firmware</th><th>Hostname</th><th>Hardware</th><th><?= _('Read') ?></th><th><?= _('Warnings') ?></th><th></th></tr></thead>
	<tbody>
	<?php foreach ($inventory as $r): $p = $r['phone']; $i = $r['info']; ?>
		<tr class="<?= $r['warnings'] ? 'warning' : '' ?>">
			<td><strong><?= $h($p['extension']) ?></strong></td>
			<td><?= $h($p['label']) ?></td>
			<td><?= $h($models[$p['model']][0] ?? $p['model']) ?></td>
			<td><?= $h($i['modelNumber'] ?? '') ?></td>
			<td><code><?= $h($i['serialNumber'] ?? '') ?></code></td>
			<td><small><?= $h($i['versionID'] ?? ($i['appLoadID'] ?? '')) ?></small></td>
			<td><small><?= $h($i['HostName'] ?? '') ?></small></td>
			<td><small><?= $h($i['hardwareRevision'] ?? '') ?></small></td>
			<td><small class="text-muted"><?= $r['inv'] ? $h(date('d/m H:i', $r['inv']['ts']) . ' · ' . $r['inv']['ip']) : $h(_('never')) ?></small></td>
			<td><small><?= nl2br($h(implode("\n", $r['warnings']))) ?></small></td>
			<td><?php if ($i): ?><a href="#" class="csm-inv-more" data-info="<?= $h(json_encode($i, JSON_UNESCAPED_UNICODE)) ?>"><?= _('all') ?></a><?php endif; ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<div class="modal fade" id="csm-inv-modal" tabindex="-1">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">DeviceInformationX</h4></div>
		<div class="modal-body"><table class="table table-condensed" id="csm-inv-all"></table></div>
	</div></div>
</div>
</div>
<script>
$(function () {
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
	$('#csm-inv-refresh').on('click', function () {
		var $b = $(this).prop('disabled', true);
		$('#csm-inv-result').html('<i class="fa fa-spinner fa-spin"></i> ' + esc(<?= $j(_('Querying phones…')) ?>));
		$.post('ajax.php?module=ciscosipmanager', { command: 'invrefresh' }, null, 'json').done(function (r) {
			var bad = (r.results || []).filter(function (x) { return !x.ok; });
			$('#csm-inv-result').html(bad.length ? '<div class="alert alert-warning">' + bad.map(function (x) { return esc(x.ext + ': ' + x.msg); }).join('<br>') + '</div>' : '');
			setTimeout(function () { location.reload(); }, bad.length ? 2500 : 300);
		}).always(function () { $b.prop('disabled', false); });
	});
	$('.csm-inv-more').on('click', function (e) {
		e.preventDefault();
		var info = $(this).data('info');
		$('#csm-inv-all').html(Object.keys(info).map(function (k) { return '<tr><th>' + esc(k) + '</th><td>' + esc(info[k]) + '</td></tr>'; }).join(''));
		$('#csm-inv-modal').modal('show');
	});
});
</script>
