<?php include __DIR__ . '/_nav.php'; ?>

<p>Sesiones activas de ocserv (<code><?= $h($settings['occtl_cmd']) ?> -j show users</code>). Se actualiza cada 15 s.
<span class="text-muted" id="csm-vpn-updated"></span></p>
<div id="csm-vpn-msg"></div>
<table class="table table-striped table-condensed">
	<thead><tr><th>Teléfono</th><th>Usuario (certificado)</th><th>IP pública</th><th>IP VPN</th><th>Conectado</th><th>Duración</th><th>RX / TX</th><th>DTLS</th><th>Cliente</th><th></th></tr></thead>
	<tbody id="csm-vpn-rows"><tr><td colspan="10"><i class="fa fa-spinner fa-spin"></i></td></tr></tbody>
</table>
<div class="panel panel-default" style="margin-top:15px"><div class="panel-body"><small>
	El módulo corre como el usuario <code>asterisk</code> y <code>occtl</code> necesita root. Agregá con <code>sudo visudo -f /etc/sudoers.d/ciscosipmanager</code>:
	<pre style="margin-top:6px">asterisk ALL=(root) NOPASSWD: /usr/bin/occtl -j show users, /usr/bin/occtl disconnect id *</pre>
	Si <code>occtl</code> está en otra ruta (<code>command -v occtl</code>), cambiala acá y en <i>Ajustes → Sesiones VPN</i>.
</small></div></div>
</div>
<script>
$(function () {
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
	function load() {
		$.post('ajax.php?module=ciscosipmanager', { command: 'vpn' }, null, 'json').done(function (r) {
			$('#csm-vpn-updated').text('· ' + new Date().toLocaleTimeString());
			if (!r.status) {
				$('#csm-vpn-msg').html('<div class="alert alert-danger">No se pudo consultar occtl: <code>' + esc(r.message) + '</code></div>');
				$('#csm-vpn-rows').empty();
				return;
			}
			$('#csm-vpn-msg').empty();
			if (!r.sessions.length) { $('#csm-vpn-rows').html('<tr><td colspan="10" class="text-muted">No hay sesiones VPN activas.</td></tr>'); return; }
			$('#csm-vpn-rows').html(r.sessions.map(function (u) {
				var ph = u.phone ? '<strong>' + esc(u.phone.extension) + '</strong> ' + esc(u.phone.label || '') : '<span class="text-muted">(no está en el módulo)</span>';
				return '<tr><td>' + ph + '</td><td><code>' + esc(u.user) + '</code></td><td>' + esc(u.real_ip) + '</td><td>' + esc(u.vpn_ip) + '</td>' +
					'<td><small>' + esc(u.since) + '</small></td><td><small>' + esc(u.duration) + '</small></td><td><small>' + esc(u.rx) + ' / ' + esc(u.tx) + '</small></td>' +
					'<td>' + (u.dtls ? '<span class="label label-success" title="' + esc(u.dtls) + '">UDP</span>' : '<span class="label label-warning" title="Sin DTLS: el audio va por TCP">solo TCP</span>') + '</td>' +
					'<td><small>' + esc(u.agent) + '</small></td>' +
					'<td><button class="btn btn-danger btn-xs csm-vpn-kick" data-sid="' + u.id + '" data-user="' + esc(u.user) + '">Desconectar</button></td></tr>';
			}).join(''));
		});
	}
	$('#csm-vpn-rows').on('click', '.csm-vpn-kick', function () {
		if (!confirm('¿Desconectar la sesión de ' + $(this).data('user') + '? El teléfono vuelve a conectar solo.')) { return; }
		$.post('ajax.php?module=ciscosipmanager', { command: 'vpndisconnect', sid: $(this).data('sid') }, null, 'json').done(function (r) {
			fpbxToast(r.message, '', r.status ? 'success' : 'error');
			load();
		});
	});
	load();
	setInterval(load, 15000);
});
</script>
