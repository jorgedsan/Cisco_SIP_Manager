<?php include __DIR__ . '/_nav.php';
$s = $settings;
$field = function ($name, $label, $help = '', $type = 'text') use ($s, $h) {
	echo '<div class="form-group"><label class="col-sm-3 control-label">' . $h($label) . '</label><div class="col-sm-6">'
		. '<input class="form-control" type="' . $type . '" name="' . $h($name) . '" value="' . $h($s[$name]) . '">'
		. ($help ? '<span class="help-block">' . $help . '</span>' : '') . '</div></div>';
};
$check = function ($name, $label) use ($s, $h) {
	echo '<div class="form-group"><div class="col-sm-offset-3 col-sm-6"><div class="checkbox"><label><input type="checkbox" name="' . $h($name) . '" value="1" '
		. (!empty($s[$name]) ? 'checked' : '') . '> ' . $h($label) . '</label></div></div></div>';
};
?>
<form method="post" class="form-horizontal" style="max-width:1000px">
	<input type="hidden" name="csm_action" value="save_settings">

	<h3>Provisión</h3>
	<?php
	$field('tftp_dir', 'Directorio de provisión', 'El que sirve tu HTTP en el puerto 6970. Debe ser escribible por el usuario <code>asterisk</code>.');
	$field('history_dir', 'Directorio del historial', 'Vacío = <code>/var/lib/asterisk/ciscosipmanager/history</code>. Que <b>no</b> esté dentro del directorio servido en :6970: las versiones viejas tienen claves SIP.');
	$field('history_keep', 'Versiones a conservar por teléfono');
	$field('lan_server', 'Servidor PBX (LAN)', 'IP del FreePBX que usan los teléfonos locales.');
	$field('vpn_server', 'Servidor PBX (VPN)', 'IP del PBX dentro de la VPN. Vacío = primera IP de la red VPN (<code>' . $h(\FreePBX\modules\Ciscosipmanager::vpnServer(['vpn_server' => ''] + $s)) . '</code>).');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">Orden en modo Auto</label>
		<div class="col-sm-6">
			<select class="form-control" name="members_order">
				<option value="vpn_first" <?= $s['members_order'] === 'vpn_first' ? 'selected' : '' ?>>VPN primero, LAN segundo</option>
				<option value="lan_first" <?= $s['members_order'] === 'lan_first' ? 'selected' : '' ?>>LAN primero, VPN segundo</option>
			</select>
			<span class="help-block">Prioridad de los dos servidores de callManagerGroup en los teléfonos con ubicación Auto.</span>
		</div>
	</div>
	<?php
	$field('softkey_file', 'Archivo de softkeys', 'Ej. <code>SoftKeys.xml</code> (en el directorio de provisión). Vacío = el del teléfono.');
	$field('dial_template', 'Dial template', 'Ej. <code>dialplan.xml</code>. Vacío = ninguno.');
	$field('sip_port', 'Puerto SIP');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">Transporte SIP</label>
		<div class="col-sm-6">
			<select class="form-control" name="sip_transport">
				<option value="2" <?= $s['sip_transport'] === '2' ? 'selected' : '' ?>>UDP</option>
				<option value="1" <?= $s['sip_transport'] === '1' ? 'selected' : '' ?>>TCP (recomendado; requiere tcpenable=yes y transport=tcp en la extensión)</option>
			</select>
		</div>
	</div>
	<?php
	$field('rtp_start', 'RTP inicio');
	$field('rtp_end', 'RTP fin');
	$field('register_expires', 'Expiración de registro (s)', 'Valores más bajos (300) hacen que los remotos reaparezcan antes tras una caída de la VPN.');
	?>

	<h3>Redes</h3>
	<?php
	$field('lan_cidr', 'Red LAN (CIDR)');
	$field('vpn_cidr', 'Red VPN ocserv (CIDR)');
	?>

	<h3>Regional</h3>
	<?php
	$field('timezone', 'Zona horaria Cisco', 'Buenos Aires = <code>SA Eastern Standard Time</code>');
	$field('date_template', 'Formato de fecha');
	$field('ntp', 'Servidor NTP', 'Vacío = el mismo servidor PBX.');
	$field('locale_name', 'User locale', 'Ej. <code>Spanish_Spain</code>. Vacío = inglés (requiere los archivos de locale en el directorio de provisión).');
	$field('locale_lang', 'Código de idioma');
	$field('locale_version', 'Versión del locale');
	$field('network_locale', 'Network locale', 'Ej. <code>Argentina</code> (tonos). Vacío = Estados Unidos.');
	$field('vm_number', 'Número de buzón de voz');
	?>

	<h3>Firmware (loadInformation)</h3>
	<p class="help-block col-sm-offset-3">Vacío = no forzar firmware. Ej. 7945: <code>SIP45.9-4-2SR3-1S</code>, 88xx: <code>sip8845_65.14-x-x-xxxx-x</code></p>
	<?php foreach ($models as $k => $m) { $field('fw_' . $k, $m[0]); } ?>

	<h3>Acceso</h3>
	<?php
	$field('ssh_user', 'Usuario SSH');
	$field('ssh_pass', 'Clave SSH');
	$check('ssh_access', 'Habilitar SSH en el teléfono');
	$check('web_access', 'Habilitar página web del teléfono (necesario para ver estado desde acá)');
	?>

	<h3>Servicios XML (vhost :6972)</h3>
	<?php
	$field('url_authenticationURL', 'authenticationURL', '<code>{SERVER}</code> se reemplaza por la IP del PBX del teléfono. Es obligatoria para poder enviar mensajes/push.');
	$field('url_servicesURL', 'servicesURL');
	$field('url_directoryURL', 'directoryURL');
	$field('url_idleURL', 'idleURL');
	$field('url_informationURL', 'informationURL');
	$field('url_messagesURL', 'messagesURL');
	$field('url_proxyServerURL', 'proxyServerURL');
	$field('push_user', 'Usuario push');
	$field('push_pass', 'Clave push');
	$field('services_dir', 'DocumentRoot del vhost :6972', 'Si se indica y es escribible, el módulo escribe ahí <code>authenticate.php</code> al guardar.');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">authenticate.php</label>
		<div class="col-sm-8">
			<textarea class="form-control" rows="8" readonly style="font-family:monospace;font-size:11px"><?= $h($authScript) ?></textarea>
			<span class="help-block">Si no indicás el DocumentRoot, copiá esto a mano en el vhost de :6972.</span>
		</div>
	</div>

	<h3>Avisos y control remoto</h3>
	<?php
	$check('wake_display', 'Encender la pantalla al mandar un mensaje o marcar (Display:On)');
	$field('wake_minutes', 'Minutos encendida', 'Después vuelve al horario de pantalla del teléfono.');
	?>

	<h3>Sesiones VPN</h3>
	<?php
	$field('occtl_cmd', 'Comando occtl', 'Con <code>sudo -n</code> y la regla de sudoers que se muestra en la pestaña <i>Sesiones VPN</i>.');
	?>

	<h3>Tonos</h3>
	<?php
	$field('ringlist_file', 'Lista de banda angosta (.raw)', 'Por defecto <code>RingList.xml</code>. Si no existe con esas mayúsculas pero sí con otras, se usa ese.');
	$field('ringlist_wb_file', 'Lista de banda ancha (.rwb)', 'Por defecto <code>RingList-wb.xml</code> (88xx).');
	?>

	<h3>Fondos de pantalla</h3>
	<p class="help-block col-sm-offset-3">Carpeta <code>Desktops/AnchoxAltoxColor</code> y tamaño de miniatura por familia. Solo cambialos si tu firmware usa otros.</p>
	<?php
	foreach (\FreePBX\modules\Ciscosipmanager\Remote::BG_FAMILIES as $k => $f) {
		$field('bg_dir_' . $k, $f['label'] . ' · carpeta');
		$field('bg_thumb_' . $k, $f['label'] . ' · miniatura');
	}
	?>

	<h3>CAPF (certificados LSC)</h3>
	<?php
	$check('capf_enabled', 'Incluir capfAuthMode y capfList en los SEP');
	$field('capf_server', 'Servidor CAPF', 'Vacío = el mismo servidor PBX del teléfono.');
	$field('capf_port', 'Puerto CAPF');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">capfAuthMode</label>
		<div class="col-sm-6">
			<select class="form-control" name="capf_auth_mode">
				<?php foreach (['1' => '1 — Contraseña', '2' => '2 — Contraseña vacía', '3' => '3 — LSC, con respaldo en MIC', '4' => '4 — MIC'] as $k => $t): ?>
					<option value="<?= $k ?>" <?= $s['capf_auth_mode'] === (string)$k ? 'selected' : '' ?>><?= $h($t) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="help-block">Tiene que coincidir con <code>capfctl --install</code>: 2 = sin <code>--password</code>; 1 = con <code>--password</code>;
			3/4 = con <code>--certificate</code> y <code>capfd --verify cmca2.pem</code>. Un <code>capfAuthMode</code> en el XML extra del teléfono tiene prioridad.</span>
		</div>
	</div>

	<h3>VPN AnyConnect (ocserv)</h3>
	<?php
	$field('vpn_url', 'URL del servidor VPN', 'Formato <code>https://host/svc</code> (o <code>https://host:4443/svc</code>). Solo 88xx/89xx/99xx tienen cliente VPN.');
	$field('vpn_url2', 'URL alternativa (opcional)');
	$field('vpn_mtu', 'MTU');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">Método de autenticación</label>
		<div class="col-sm-6">
			<select class="form-control" name="vpn_auth_method">
				<option value="0" <?= $s['vpn_auth_method'] === '0' ? 'selected' : '' ?>>Usuario y contraseña</option>
				<option value="1" <?= $s['vpn_auth_method'] === '1' ? 'selected' : '' ?>>Solo contraseña</option>
				<option value="2" <?= $s['vpn_auth_method'] === '2' ? 'selected' : '' ?>>Certificado del teléfono (MIC o LSC)</option>
			</select>
		</div>
	</div>
	<?php
	$check('vpn_pswd_persistent', 'Recordar contraseña en el teléfono');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">Certificado del servidor ocserv (PEM)</label>
		<div class="col-sm-8">
			<textarea class="form-control" name="vpn_cert_pem" rows="6" style="font-family:monospace;font-size:11px" placeholder="-----BEGIN CERTIFICATE-----"><?= $h($s['vpn_cert_pem']) ?></textarea>
			<span class="help-block">Pegá el certificado de <code>server-cert</code> de ocserv. Se calcula el certHash (SHA1/base64) que exige el teléfono.
			<?php if ($s['vpn_cert_hash']): ?><br>certHash actual: <code><?= $h($s['vpn_cert_hash']) ?></code><?php endif; ?></span>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary">Guardar ajustes</button></div>
	</div>
</form>
</div>
