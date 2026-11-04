<?php
// Phone form: used by the "Add phone" tab and by the edit dialog of the list
if (!isset($h)) {
	$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
}
if (!isset($j)) {
	$j = function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); };
}
$embedded = !empty($embedded);
$e = $edit ?: ['id' => '', 'mac' => '', 'model' => '8845', 'extension' => '', 'label' => '', 'network' => 'auto', 'server_override' => '', 'vpn_enabled' => 0, 'extra_xml' => '', 'extra_lines_xml' => ''];
$used = array_column($phones, 'extension');
?>
<?php if ($edit && (int)$edit['last_generated'] === 0): ?>
	<div class="alert alert-warning" style="max-width:900px"><?= _('This phone was imported and its SEP is still the original one. Saving regenerates the file (the original stays in the history). If you have not reviewed it, use <i>Compare with what would be generated</i> first.') ?></div>
<?php endif; ?>
<form method="post" class="form-horizontal csm-phone-form" style="max-width:900px">
	<input type="hidden" name="csm_action" value="save_phone">
	<input type="hidden" name="id" value="<?= $h($e['id']) ?>">

	<div class="form-group">
		<label class="col-sm-3 control-label">MAC</label>
		<div class="col-sm-5">
			<input class="form-control" name="mac" value="<?= $h($e['mac']) ?>" required placeholder="00:11:22:AA:BB:CC" pattern="[0-9A-Fa-f:\.\-]{12,17}">
			<span class="help-block"><?= sprintf(_('Saved as SEP<em>MAC</em>.cnf.xml in %s'), '<code>' . $h($settings['tftp_dir']) . '</code>') ?></span>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Model') ?></label>
		<div class="col-sm-5">
			<select class="form-control" name="model">
				<?php foreach ($models as $k => $m): ?>
					<option value="<?= $h($k) ?>" <?= $e['model'] === (string)$k ? 'selected' : '' ?>><?= $h($m[0]) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Extension (chan_sip)') ?></label>
		<div class="col-sm-5">
			<select class="form-control" name="extension" required>
				<option value="">— <?= _('choose') ?> —</option>
				<?php foreach ($devices as $d):
					$taken = in_array($d['id'], $used) && $d['id'] !== $e['extension']; ?>
					<option value="<?= $h($d['id']) ?>" <?= $d['id'] === $e['extension'] ? 'selected' : '' ?>>
						<?= $h($d['id'] . ' — ' . $d['description']) ?><?= $taken ? ' ' . $h(_('(already assigned)')) : '' ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="help-block"><?= _('Only chan_sip devices are listed. The password is taken from FreePBX.') ?></span>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Line label') ?></label>
		<div class="col-sm-5"><input class="form-control" name="label" value="<?= $h($e['label']) ?>" maxlength="30" placeholder="Living"></div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Location') ?></label>
		<div class="col-sm-5">
			<select class="form-control" name="network">
				<option value="auto" <?= $e['network'] === 'auto' ? 'selected' : '' ?>><?= _('Auto: LAN at home, VPN when away') ?></option>
				<option value="lan" <?= $e['network'] === 'lan' ? 'selected' : '' ?>><?= _('LAN only') ?></option>
				<option value="vpn" <?= $e['network'] === 'vpn' ? 'selected' : '' ?>><?= _('Always through VPN') ?></option>
			</select>
			<span class="help-block"><?= sprintf(_('<b>Auto</b> (with VPN checked): two servers in callManagerGroup, the PBX IP inside the VPN (%s) and the LAN one (%s), and <code>autoNetDetect</code> 1: the phone only brings the tunnel up when it cannot reach the home network.<br><b>Always through VPN</b>: only the VPN server and <code>autoNetDetect</code> 0. <b>LAN only</b>: only the LAN IP.'), $h(\FreePBX\modules\Ciscosipmanager::vpnServer($settings)), $h($settings['lan_server'])) ?></span>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('SIP server (optional)') ?></label>
		<div class="col-sm-5"><input class="form-control" name="server_override" value="<?= $h($e['server_override']) ?>" placeholder="<?= $h($settings['lan_server']) ?>"></div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<div class="checkbox"><label><input type="checkbox" name="vpn_enabled" value="1" <?= $e['vpn_enabled'] ? 'checked' : '' ?>> <?= _('Include AnyConnect VPN profile (ocserv) — 88xx only') ?></label></div>
			<?php if (empty($settings['vpn_url'])): ?><span class="help-block text-warning"><?= _('The VPN URL is not configured in Settings yet.') ?></span><?php endif; ?>
		</div>
	</div>
	<?php
	$videoOn = isset($e['video']) && $e['video'] !== null ? (int)$e['video'] : (int)($e['video_effective'] ?? 0);
	$videoModels = \FreePBX\modules\Ciscosipmanager\CnfBuilder::videoModelLabels();
	?>
	<div class="form-group" id="csm-video-group">
		<div class="col-sm-offset-3 col-sm-9">
			<input type="hidden" name="video" value="0">
			<div class="checkbox"><label><input type="checkbox" name="video" value="1" id="csm-video" <?= $videoOn ? 'checked' : '' ?>> <?= _('Enable video') ?></label></div>
			<span class="help-block" id="csm-video-help"><?= sprintf(_('Sets <code>videoCapability</code> in the SEP (takes precedence over the extra XML). For video calls, chan_sip needs <code>videosupport=yes</code> and the <code>h264</code> codec enabled on the extension and on the other end. Available on %s.'), $h(implode(', ', array_map(function ($m, $t) { return "$m ($t)"; }, array_keys($videoModels), $videoModels)))) ?></span>
		</div>
	</div>
	<script>
	$(function () {
		var vm = <?= json_encode(array_map("strval", array_keys($videoModels))) ?>;
		function upd() { var ok = vm.indexOf($('select[name=model]').val()) !== -1; $('#csm-video').prop('disabled', !ok); $('#csm-video-group').toggleClass('text-muted', !ok); }
		$('select[name=model]').on('change', upd); upd();
	});
	</script>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Additional lines') ?></label>
		<div class="col-sm-8">
			<div id="csm-lines-editor">
				<table class="table table-condensed" style="margin-bottom:6px">
					<thead><tr><th style="width:30px"><?= _('Key') ?></th><th><?= _('Type') ?></th><th><?= _('Label') ?></th><th><?= _('Extension / number') ?></th><th><?= _('Options') ?></th><th style="width:90px"></th></tr></thead>
					<tbody id="csm-lines-rows"></tbody>
				</table>
				<button type="button" class="btn btn-default btn-xs" id="csm-add-line"><i class="fa fa-plus"></i> <?= _('Line') ?></button>
				<button type="button" class="btn btn-default btn-xs" id="csm-add-blf"><i class="fa fa-plus"></i> BLF / speed dial</button>
				<a href="#" class="small pull-right" id="csm-lines-toggle"><?= _('view / edit XML') ?></a>
				<div id="csm-lines-err" class="text-danger small"></div>
			</div>
			<textarea class="form-control" name="extra_lines_xml" id="csm-lines-xml" rows="6" style="font-family:monospace;display:none;margin-top:6px" placeholder="&lt;line button=&quot;2&quot;&gt;…&lt;/line&gt;"><?= $h($e['extra_lines_xml'] ?? '') ?></textarea>
			<span class="help-block"><?= _('Keys 2 onwards. <b>Line</b>: another extension on the same phone (with "FreePBX password" the password is filled in when generating, it is not stored here). <b>BLF / speed dial</b>: shows the state of an extension and dials it when pressed. Anything the editor does not know is kept as is.') ?></span>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label"><?= _('Extra XML') ?></label>
		<div class="col-sm-8">
			<textarea class="form-control" name="extra_xml" rows="4" style="font-family:monospace" placeholder="&lt;phoneServices&gt;…&lt;/phoneServices&gt;"><?= $h($e['extra_xml']) ?></textarea>
			<span class="help-block"><?= _('Fragment inserted before &lt;/device&gt;; top-level elements with the same name as a generated one replace it.') ?></span>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-3 col-sm-9">
			<button class="btn btn-primary"><?= _('Save and generate SEP') ?></button>
			<?php if ($embedded): ?>
				<button type="button" class="btn btn-default" data-dismiss="modal"><?= _('Cancel') ?></button>
			<?php else: ?>
				<a class="btn btn-default" href="?display=ciscosipmanager"><?= _('Cancel') ?></a>
			<?php endif; ?>
		</div>
	</div>
</form>
<script>
$(function () {
	var $ta = $('#csm-lines-xml'), $rows = $('#csm-lines-rows'), lines = [], doc = document.implementation.createDocument(null, 'r', null);
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
	var L = <?= $j([
		'intercom' => _('Intercom (auto-answers on the speaker)'),
		'fpbxpw' => _('FreePBX password'),
		'ownpw' => _('(it currently has its own password)'),
		'mask' => _('featureOptionMask 1 (BLF with state/pickup)'),
		'kept' => _('featureID %s: kept as is'),
		'line' => _('Line'),
		'other' => _('Other'),
		'none' => _('No additional keys.'),
		'notlist' => _('The XML is not a valid list of <line> elements: fix it before going back to the editor.'),
		'invalid' => _('Invalid XML: the editor shows the last valid version.'),
		'unparsed' => _('The current XML could not be parsed: edit it by hand.'),
	]) ?>;
	function child(el, name) { for (var c = el.firstElementChild; c; c = c.nextElementSibling) { if (c.nodeName === name) { return c; } } return null; }
	function get(el, name) { var c = child(el, name); return c ? c.textContent : ''; }
	function set(el, name, val) { var c = child(el, name); if (!c) { c = doc.createElement(name); el.appendChild(c); } c.textContent = val; }
	function del(el, name) { var c = child(el, name); if (c) { el.removeChild(c); } }
	function type(el) { var f = get(el, 'featureID'); return f === '9' ? 'line' : (f === '21' ? 'blf' : 'other'); }

	function parse() {
		var xml = $.trim($ta.val());
		lines = [];
		if (!xml) { return true; }
		var d = new DOMParser().parseFromString('<r>' + xml + '</r>', 'application/xml');
		if (d.getElementsByTagName('parsererror').length) { return false; }
		doc = d;
		for (var c = d.documentElement.firstElementChild; c; c = c.nextElementSibling) {
			if (c.nodeName !== 'line') { return false; }
			lines.push(c);
		}
		return true;
	}
	function serialize() {
		var s = new XMLSerializer();
		$ta.val(lines.map(function (el, i) { el.setAttribute('button', i + 2); return s.serializeToString(el); }).join('\n'));
	}
	function render() {
		$rows.html(lines.length ? lines.map(function (el, i) {
			var t = type(el), opts = '';
			if (t === 'line') {
				var pw = get(el, 'authPassword'), own = pw !== '' && pw !== '{FREEPBX}';
				var aa = child(el, 'autoAnswer') && get(child(el, 'autoAnswer'), 'autoAnswerEnabled') === '1';
				opts = '<label class="small" style="font-weight:normal;display:block"><input type="checkbox" class="csm-l-aa"' + (aa ? ' checked' : '') + '> ' + esc(L.intercom) + '</label>' +
					'<label class="small" style="font-weight:normal;display:block"><input type="checkbox" class="csm-l-pw"' + (pw === '{FREEPBX}' ? ' checked' : '') + '> ' + esc(L.fpbxpw) + (own ? ' <span class="text-muted">' + esc(L.ownpw) + '</span>' : '') + '</label>';
			} else if (t === 'blf') {
				opts = '<label class="small" style="font-weight:normal"><input type="checkbox" class="csm-l-mask"' + (get(el, 'featureOptionMask') === '1' ? ' checked' : '') + '> ' + esc(L.mask) + '</label>';
			} else {
				opts = '<span class="small text-muted">' + esc(L.kept.replace('%s', get(el, 'featureID'))) + '</span>';
			}
			var num = t === 'line' ? get(el, 'name') : get(el, 'speedDialNumber');
			return '<tr data-i="' + i + '"><td>' + (i + 2) + '</td>' +
				'<td>' + esc(({ line: L.line, blf: 'BLF / speed dial', other: L.other })[t]) + '</td>' +
				'<td><input class="form-control input-sm csm-l-label" value="' + esc(get(el, 'featureLabel')) + '" maxlength="30"' + (t === 'other' ? ' disabled' : '') + '></td>' +
				'<td><input class="form-control input-sm csm-l-num" value="' + esc(num) + '" maxlength="32"' + (t === 'other' ? ' disabled' : '') + '></td>' +
				'<td>' + opts + '</td>' +
				'<td style="white-space:nowrap"><button type="button" class="btn btn-default btn-xs csm-l-up"><i class="fa fa-arrow-up"></i></button> ' +
				'<button type="button" class="btn btn-default btn-xs csm-l-dn"><i class="fa fa-arrow-down"></i></button> ' +
				'<button type="button" class="btn btn-danger btn-xs csm-l-del"><i class="fa fa-trash"></i></button></td></tr>';
		}).join('') : '<tr><td colspan="6" class="text-muted small">' + esc(L.none) + '</td></tr>');
	}
	function mk(xml) { return new DOMParser().parseFromString(xml, 'application/xml').documentElement; }
	function add(tpl) { var el = doc.importNode(mk(tpl), true); lines.push(el); serialize(); render(); }

	$('#csm-add-line').on('click', function () {
		add('<line button="0"><featureID>9</featureID><featureLabel></featureLabel><proxy>USECALLMANAGER</proxy><port>5060</port><name></name><displayName></displayName>' +
			'<authName></authName><authPassword>{FREEPBX}</authPassword><contact></contact><sharedLine>false</sharedLine><callWaiting>3</callWaiting><maxNumCalls>2</maxNumCalls><busyTrigger>1</busyTrigger></line>');
	});
	$('#csm-add-blf').on('click', function () {
		add('<line button="0"><featureID>21</featureID><featureLabel></featureLabel><featureOptionMask>1</featureOptionMask><speedDialNumber></speedDialNumber></line>');
	});
	$rows.on('input change', 'input', function () {
		var el = lines[$(this).closest('tr').data('i')], t = type(el), $tr = $(this).closest('tr');
		set(el, 'featureLabel', $tr.find('.csm-l-label').val());
		var n = $tr.find('.csm-l-num').val();
		if (t === 'line') {
			set(el, 'name', n); set(el, 'contact', n);
			if (!get(el, 'displayName') || $(this).hasClass('csm-l-label')) { set(el, 'displayName', $tr.find('.csm-l-label').val()); }
			if ($tr.find('.csm-l-pw').is(':checked')) { set(el, 'authName', n); set(el, 'authPassword', '{FREEPBX}'); }
			else if (get(el, 'authPassword') === '{FREEPBX}') { del(el, 'authName'); del(el, 'authPassword'); }
			var aa = child(el, 'autoAnswer');
			if ($tr.find('.csm-l-aa').is(':checked')) {
				if (!aa) { aa = doc.createElement('autoAnswer'); el.appendChild(aa); }
				set(aa, 'autoAnswerEnabled', '1'); set(aa, 'autoAnswerMode', 'Auto Answer with Speakerphone');
			} else if (aa) { set(aa, 'autoAnswerEnabled', '0'); }
		} else if (t === 'blf') {
			set(el, 'speedDialNumber', n);
			set(el, 'featureOptionMask', $tr.find('.csm-l-mask').is(':checked') ? '1' : '0');
		}
		serialize();
	});
	$rows.on('click', '.csm-l-up, .csm-l-dn, .csm-l-del', function () {
		var i = $(this).closest('tr').data('i');
		if ($(this).hasClass('csm-l-del')) { lines.splice(i, 1); }
		else { var j = i + ($(this).hasClass('csm-l-up') ? -1 : 1); if (j < 0 || j >= lines.length) { return; } var t = lines[i]; lines[i] = lines[j]; lines[j] = t; }
		serialize(); render();
	});
	$('#csm-lines-toggle').on('click', function (e) {
		e.preventDefault();
		if ($ta.is(':visible')) {
			if (!parse()) { $('#csm-lines-err').text(L.notlist); return; }
			$('#csm-lines-err').text(''); $ta.hide(); render(); $('#csm-lines-editor table, #csm-add-line, #csm-add-blf').show();
		} else { $ta.show(); }
	});
	$ta.on('input', function () { if (parse()) { $('#csm-lines-err').text(''); render(); } else { $('#csm-lines-err').text(L.invalid); } });
	if (parse()) { render(); } else { $ta.show(); $('#csm-lines-err').text(L.unparsed); }
});
</script>
