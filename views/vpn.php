<?php include __DIR__ . '/_nav.php'; ?>

<p><?= sprintf(_('Active ocserv sessions (%s). Refreshed every 15 s.'), '<code>' . $h($settings['occtl_cmd']) . ' -j show users</code>') ?> 
<span class="text-muted" id="csm-vpn-updated"></span></p>
<div id="csm-vpn-msg"></div>
<table class="table table-striped table-condensed">
	<thead><tr><th><?= _('Phone') ?></th><th><?= _('User (certificate)') ?></th><th><?= _('Public IP') ?></th><th><?= _('VPN IP') ?></th><th><?= _('Connected') ?></th><th><?= _('Duration') ?></th><th>RX / TX</th><th>DTLS</th><th><?= _('Client') ?></th><th></th></tr></thead>
	<tbody id="csm-vpn-rows"><tr><td colspan="10"><i class="fa fa-spinner fa-spin"></i></td></tr></tbody>
</table>
<div class="panel panel-default" style="margin-top:15px"><div class="panel-body"><small>
	<?= _('The module runs as the <code>asterisk</code> user and <code>occtl</code> needs root. Add with <code>sudo visudo -f /etc/sudoers.d/ciscosipmanager</code>:') ?>
	<pre style="margin-top:6px">asterisk ALL=(root) NOPASSWD: /usr/bin/occtl -j show users, /usr/bin/occtl disconnect id *</pre>
	<?= _('If <code>occtl</code> is in another path (<code>command -v occtl</code>), change it here and in <i>Settings → VPN sessions</i>.') ?>
</small></div></div>
</div>
<script>
$(function () {
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
	var L = <?= $j([
		'fail' => _('Could not query occtl:'),
		'none' => _('There are no active VPN sessions.'),
		'notin' => _('(not in the module)'),
		'nodtls' => _('No DTLS: audio goes over TCP'),
		'tcp' => _('TCP only'),
		'kick' => _('Disconnect'),
		'confirm' => _('Disconnect the session of %s? The phone reconnects by itself.'),
	]) ?>;
	function load() {
		$.post('ajax.php?module=ciscosipmanager', { command: 'vpn' }, null, 'json').done(function (r) {
			$('#csm-vpn-updated').text('· ' + new Date().toLocaleTimeString());
			if (!r.status) {
				$('#csm-vpn-msg').html('<div class="alert alert-danger">' + esc(L.fail) + ' <code>' + esc(r.message) + '</code></div>');
				$('#csm-vpn-rows').empty();
				return;
			}
			$('#csm-vpn-msg').empty();
			if (!r.sessions.length) { $('#csm-vpn-rows').html('<tr><td colspan="10" class="text-muted">' + esc(L.none) + '</td></tr>'); return; }
			$('#csm-vpn-rows').html(r.sessions.map(function (u) {
				var ph = u.phone ? '<strong>' + esc(u.phone.extension) + '</strong> ' + esc(u.phone.label || '') : '<span class="text-muted">' + esc(L.notin) + '</span>';
				return '<tr><td>' + ph + '</td><td><code>' + esc(u.user) + '</code></td><td>' + esc(u.real_ip) + '</td><td>' + esc(u.vpn_ip) + '</td>' +
					'<td><small>' + esc(u.since) + '</small></td><td><small>' + esc(u.duration) + '</small></td><td><small>' + esc(u.rx) + ' / ' + esc(u.tx) + '</small></td>' +
					'<td>' + (u.dtls ? '<span class="label label-success" title="' + esc(u.dtls) + '">UDP</span>' : '<span class="label label-warning" title="' + esc(L.nodtls) + '">' + esc(L.tcp) + '</span>') + '</td>' +
					'<td><small>' + esc(u.agent) + '</small></td>' +
					'<td><button class="btn btn-danger btn-xs csm-vpn-kick" data-sid="' + u.id + '" data-user="' + esc(u.user) + '">' + esc(L.kick) + '</button></td></tr>';
			}).join(''));
		});
	}
	$('#csm-vpn-rows').on('click', '.csm-vpn-kick', function () {
		if (!confirm(L.confirm.replace('%s', $(this).data('user')))) { return; }
		$.post('ajax.php?module=ciscosipmanager', { command: 'vpndisconnect', sid: $(this).data('sid') }, null, 'json').done(function (r) {
			fpbxToast(r.message, '', r.status ? 'success' : 'error');
			load();
		});
	});
	load();
	setInterval(load, 15000);
});
</script>
