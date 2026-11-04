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

	<h3><?= _('Provisioning') ?></h3>
	<?php
	$field('tftp_dir', _('Provisioning directory'), _('The one your HTTP server serves on port 6970. It must be writable by the <code>asterisk</code> user.'));
	$field('history_dir', _('History directory'), _('Empty = <code>/var/lib/asterisk/ciscosipmanager/history</code>. It must <b>not</b> be inside the directory served on :6970: old versions contain SIP passwords.'));
	$field('history_keep', _('Versions to keep per phone'));
	$field('lan_server', _('PBX server (LAN)'), _('IP of the FreePBX used by the local phones.'));
	$field('vpn_server', _('PBX server (VPN)'), sprintf(_('IP of the PBX inside the VPN. Empty = first IP of the VPN network (%s).'), '<code>' . $h(\FreePBX\modules\Ciscosipmanager::vpnServer(['vpn_server' => ''] + $s)) . '</code>'));
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Order in Auto mode') ?></label>
		<div class="col-sm-6">
			<select class="form-control" name="members_order">
				<option value="vpn_first" <?= $s['members_order'] === 'vpn_first' ? 'selected' : '' ?>><?= _('VPN first, LAN second') ?></option>
				<option value="lan_first" <?= $s['members_order'] === 'lan_first' ? 'selected' : '' ?>><?= _('LAN first, VPN second') ?></option>
			</select>
			<span class="help-block"><?= _('Priority of the two callManagerGroup servers on phones with Auto location.') ?></span>
		</div>
	</div>
	<?php
	$field('softkey_file', _('Softkey file'), _('E.g. <code>SoftKeys.xml</code> (in the provisioning directory). Empty = the phone default.'));
	$field('dial_template', _('Dial template'), _('E.g. <code>dialplan.xml</code>. Empty = none.'));
	$field('sip_port', _('SIP port'));
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('SIP transport') ?></label>
		<div class="col-sm-6">
			<select class="form-control" name="sip_transport">
				<option value="2" <?= $s['sip_transport'] === '2' ? 'selected' : '' ?>>UDP</option>
				<option value="1" <?= $s['sip_transport'] === '1' ? 'selected' : '' ?>><?= _('TCP (recommended; requires tcpenable=yes and transport=tcp on the extension)') ?></option>
			</select>
		</div>
	</div>
	<?php
	$field('rtp_start', _('RTP start'));
	$field('rtp_end', _('RTP end'));
	$field('register_expires', _('Registration expiry (s)'), _('Lower values (300) make remote phones show up sooner after a VPN outage.'));
	?>

	<h3><?= _('Networks') ?></h3>
	<?php
	$field('lan_cidr', _('LAN network (CIDR)'));
	$field('vpn_cidr', _('ocserv VPN network (CIDR)'));
	?>

	<h3><?= _('Regional') ?></h3>
	<?php
	$field('timezone', _('Cisco time zone'), _('Buenos Aires = <code>SA Eastern Standard Time</code>'));
	$field('date_template', _('Date format'));
	$field('ntp', _('NTP server'), _('Empty = the same PBX server.'));
	$field('locale_name', _('User locale'), _('E.g. <code>Spanish_Spain</code>. Empty = English (requires the locale files in the provisioning directory).'));
	$field('locale_lang', _('Language code'));
	$field('locale_version', _('Locale version'));
	$field('network_locale', _('Network locale'), _('E.g. <code>Argentina</code> (tones). Empty = United States.'));
	$field('vm_number', _('Voicemail number'));
	?>

	<h3><?= _('Firmware (loadInformation)') ?></h3>
	<p class="help-block col-sm-offset-3"><?= _('Empty = do not force firmware. E.g. 7945: <code>SIP45.9-4-2SR3-1S</code>, 88xx: <code>sip8845_65.14-x-x-xxxx-x</code>') ?></p>
	<?php foreach ($models as $k => $m) { $field('fw_' . $k, $m[0]); } ?>

	<h3><?= _('Access') ?></h3>
	<?php
	$field('ssh_user', _('SSH user'));
	$field('ssh_pass', _('SSH password'));
	$check('ssh_access', _('Enable SSH on the phone'));
	$check('web_access', _('Enable the phone web page (needed to see the status from here)'));
	?>

	<h3><?= _('XML services (vhost :6972)') ?></h3>
	<?php
	$field('url_authenticationURL', 'authenticationURL', _('<code>{SERVER}</code> is replaced by the IP of the phone PBX. It is required to send messages/push.'));
	$field('url_servicesURL', 'servicesURL');
	$field('url_directoryURL', 'directoryURL');
	$field('url_idleURL', 'idleURL');
	$field('url_informationURL', 'informationURL');
	$field('url_messagesURL', 'messagesURL');
	$field('url_proxyServerURL', 'proxyServerURL');
	$field('push_user', _('Push user'));
	$field('push_pass', _('Push password'));
	$field('services_dir', _('DocumentRoot of the :6972 vhost'), _('If set and writable, the module writes <code>authenticate.php</code> there when saving.'));
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">authenticate.php</label>
		<div class="col-sm-8">
			<textarea class="form-control" rows="8" readonly style="font-family:monospace;font-size:11px"><?= $h($authScript) ?></textarea>
			<span class="help-block"><?= _('If you do not set the DocumentRoot, copy this by hand into the :6972 vhost.') ?></span>
		</div>
	</div>

	<h3><?= _('Notices and remote control') ?></h3>
	<?php
	$check('wake_display', _('Turn the display on when sending a message or dialing (Display:On)'));
	$field('wake_minutes', _('Minutes on'), _('Afterwards it goes back to the phone display schedule.'));
	?>

	<h3><?= _('VPN sessions') ?></h3>
	<?php
	$field('occtl_cmd', _('occtl command'), _('With <code>sudo -n</code> and the sudoers rule shown in the <i>VPN sessions</i> tab.'));
	?>

	<h3><?= _('Ringtones') ?></h3>
	<?php
	$field('ringlist_file', _('Narrowband list (.raw)'), _('Default <code>RingList.xml</code>. If it does not exist with that capitalization but does with another one, that one is used.'));
	$field('ringlist_wb_file', _('Wideband list (.rwb)'), _('Default <code>RingList-wb.xml</code> (88xx).'));
	?>

	<h3><?= _('Backgrounds') ?></h3>
	<p class="help-block col-sm-offset-3"><?= _('<code>Desktops/WidthxHeightxColor</code> folder and thumbnail size per family. Only change them if your firmware uses different ones.') ?></p>
	<?php
	foreach (\FreePBX\modules\Ciscosipmanager\Remote::BG_FAMILIES as $k => $f) {
		$field('bg_dir_' . $k, sprintf(_('%s · folder'), $f['label']));
		$field('bg_thumb_' . $k, sprintf(_('%s · thumbnail'), $f['label']));
	}
	?>

	<h3><?= _('CAPF (LSC certificates)') ?></h3>
	<?php
	$check('capf_enabled', _('Include capfAuthMode and capfList in the SEP files'));
	$field('capf_server', _('CAPF server'), _('Empty = the same PBX server as the phone.'));
	$field('capf_port', _('CAPF port'));
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label">capfAuthMode</label>
		<div class="col-sm-6">
			<select class="form-control" name="capf_auth_mode">
				<?php foreach (['1' => _('1 — Password'), '2' => _('2 — Empty password'), '3' => _('3 — LSC, falling back to MIC'), '4' => _('4 — MIC')] as $k => $t): ?>
					<option value="<?= $k ?>" <?= $s['capf_auth_mode'] === (string)$k ? 'selected' : '' ?>><?= $h($t) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="help-block"><?= _('It must match <code>capfctl --install</code>: 2 = without <code>--password</code>; 1 = with <code>--password</code>; 3/4 = with <code>--certificate</code> and <code>capfd --verify cmca2.pem</code>. A <code>capfAuthMode</code> in the extra XML of the phone takes precedence.') ?></span>
		</div>
	</div>

	<h3><?= _('AnyConnect VPN (ocserv)') ?></h3>
	<?php
	$field('vpn_url', _('VPN server URL'), _('Format <code>https://host/svc</code> (or <code>https://host:4443/svc</code>). Only 88xx/89xx/99xx have a VPN client.'));
	$field('vpn_url2', _('Alternate URL (optional)'));
	$field('vpn_mtu', 'MTU');
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Authentication method') ?></label>
		<div class="col-sm-6">
			<select class="form-control" name="vpn_auth_method">
				<option value="0" <?= $s['vpn_auth_method'] === '0' ? 'selected' : '' ?>><?= _('User and password') ?></option>
				<option value="1" <?= $s['vpn_auth_method'] === '1' ? 'selected' : '' ?>><?= _('Password only') ?></option>
				<option value="2" <?= $s['vpn_auth_method'] === '2' ? 'selected' : '' ?>><?= _('Phone certificate (MIC or LSC)') ?></option>
			</select>
		</div>
	</div>
	<?php
	$check('vpn_pswd_persistent', _('Remember password on the phone'));
	?>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('ocserv server certificate (PEM)') ?></label>
		<div class="col-sm-8">
			<textarea class="form-control" name="vpn_cert_pem" rows="6" style="font-family:monospace;font-size:11px" placeholder="-----BEGIN CERTIFICATE-----"><?= $h($s['vpn_cert_pem']) ?></textarea>
			<span class="help-block"><?= _('Paste the ocserv <code>server-cert</code> certificate. The certHash (SHA1/base64) the phone requires is calculated from it.') ?>
			<?php if ($s['vpn_cert_hash']): ?><br><?= _('Current certHash:') ?> <code><?= $h($s['vpn_cert_hash']) ?></code><?php endif; ?></span>
		</div>
	</div>

	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9"><button class="btn btn-primary"><?= _('Save settings') ?></button></div>
	</div>
</form>
</div>
