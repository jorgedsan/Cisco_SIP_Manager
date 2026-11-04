<?php include __DIR__ . '/_nav.php'; ?>

<div class="row">
<div class="col-md-7">
	<h3><?= _('Backgrounds') ?></h3>
	<p class="help-block"><?= sprintf(_('Stored in %s with their thumbnail, and the <code>List.xml</code> is built. The image is scaled and cropped to the screen size. On the phone: <i>Settings → Preferences → Wallpaper</i>.'), '<code>' . $h($settings['tftp_dir']) . '/Desktops/&lt;' . $h(_('resolution')) . '&gt;/</code>') ?></p>
	<form method="post" enctype="multipart/form-data" class="form-inline" style="margin-bottom:12px">
		<input type="hidden" name="csm_action" value="bg_upload">
		<select class="form-control input-sm" name="fam">
			<?php foreach ($families as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f['label'] . ' · ' . $f['dir']) ?></option><?php endforeach; ?>
		</select>
		<input type="file" name="image" accept="image/*" class="form-control input-sm" required>
		<button class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> <?= _('Upload background') ?></button>
	</form>
	<div id="csm-bg"></div>
</div>
<div class="col-md-5">
	<h3><?= _('Ringtones') ?></h3>
	<p class="help-block"><?= sprintf(_('Two lists in %s: %s (narrowband, <code>.raw</code> µ-law 8 kHz, used by 79xx) and %s (wideband, <code>.rwb</code> 16-bit PCM 16 kHz, used by 88xx). Upload WAV, MP3 or other audio and it is converted for each list (2 s maximum, with sox or ffmpeg). Up to 50 ringtones per list. On the phone: <i>Settings → Preferences → Ringtone</i>.'),
		'<code>' . $h($settings['tftp_dir']) . '</code>',
		'<code>' . $h(basename(\FreePBX::Ciscosipmanager()->ringlistFile('nb'))) . '</code>',
		'<code>' . $h(basename(\FreePBX::Ciscosipmanager()->ringlistFile('wb'))) . '</code>') ?></p>
	<form method="post" enctype="multipart/form-data" style="margin-bottom:12px">
		<input type="hidden" name="csm_action" value="ring_upload">
		<input class="form-control input-sm" name="display" placeholder="<?= $h(_('Name on the phone (max. 25)')) ?>" maxlength="25" style="margin-bottom:6px">
		<select class="form-control input-sm" name="target" style="margin-bottom:6px">
			<option value="nb,wb"><?= _('Both lists (recommended)') ?></option>
			<option value="wb"><?= _('Wideband only (88xx)') ?></option>
			<option value="nb"><?= _('Narrowband only (79xx)') ?></option>
		</select>
		<input type="file" name="ring" accept="audio/*,.raw,.rwb" class="form-control input-sm" required style="margin-bottom:6px">
		<button class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> <?= _('Upload ringtone') ?></button>
		<span class="help-block small"><?= _('An already converted <code>.raw</code> only goes to narrowband and an <code>.rwb</code> only to wideband.') ?></span>
	</form>
	<div id="csm-rings"></div>
</div>
</div>
</div>
</div>
<script>
$(function () {
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
	var post = function (d) { return $.post('ajax.php?module=ciscosipmanager', d, null, 'json'); };
	var L = <?= $j([
		'nofmt' => _('format not viewable'),
		'missing' => _('%s is missing'),
		'nothumb' => _('no thumbnail in List.xml'),
		'nopng' => _('(the .png is missing)'),
		'nobg' => _('No backgrounds.'),
		'thumb' => _('thumbnail'),
		'noexist' => _('(does not exist)'),
		'empty' => _('Empty list.'),
		'notyet' => _('It does not exist yet; it is created when the first ringtone is uploaded.'),
		'name' => _('Name'),
		'file' => _('File'),
		'delbg' => _('Delete the background %s?'),
		'delring' => _('Remove %s from the ringtone list?'),
		'delfile' => _('Also delete the file %s? (Cancel = only remove it from the list)'),
	]) ?>;
	function loadBg() {
		post({ command: 'bglist' }).done(function (r) {
			$('#csm-bg').html(r.families.map(function (f) {
				var imgs = f.images.length ? f.images.map(function (i) {
					return '<div class="thumbnail" style="display:inline-block;width:160px;margin:0 6px 6px 0;vertical-align:top">' +
						(i.thumb ? '<img src="' + i.thumb + '" style="max-width:100%">' : '<div class="text-muted small" style="height:60px;padding-top:20px;text-align:center">' + esc(i.thumb_file ? (i.thumb_found ? L.nofmt : L.missing.replace('%s', i.thumb_file)) : L.nothumb) + '</div>') +
						'<div class="caption" style="padding:4px"><small>' + esc(i.name) + (i.exists ? '' : ' <span class="text-danger">' + esc(L.nopng) + '</span>') + '</small>' +
						'<button class="btn btn-link btn-xs text-danger pull-right csm-bg-del" data-fam="' + esc(f.key) + '" data-name="' + esc(i.name) + '"><i class="fa fa-trash"></i></button></div></div>';
				}).join('') : '<p class="text-muted small">' + esc(L.nobg) + '</p>';
				return '<h4>' + esc(f.label) + ' <small>Desktops/' + esc(f.dir) + ' · ' + esc(L.thumb) + ' ' + esc(f.thumb) + '</small></h4>' + imgs;
			}).join(''));
		});
	}
	function loadRings() {
		post({ command: 'ringlist' }).done(function (r) {
			$('#csm-rings').html(r.lists.map(function (l) {
				var rows = l.rings.length ? l.rings.map(function (g) {
					return '<tr><td><input class="form-control input-sm csm-ring-name" data-kind="' + l.kind + '" data-file="' + esc(g.file) + '" value="' + esc(g.name) + '" maxlength="25"></td>' +
						'<td><code>' + esc(g.file) + '</code>' + (g.exists ? '' : ' <span class="text-danger small">' + esc(L.noexist) + '</span>') + '</td>' +
						'<td><button class="btn btn-link btn-xs text-danger csm-ring-del" data-kind="' + l.kind + '" data-file="' + esc(g.file) + '"><i class="fa fa-trash"></i></button></td></tr>';
				}).join('') : '<tr><td colspan="3" class="text-muted small">' + esc(l.exists ? L.empty : L.notyet) + '</td></tr>';
				return '<h4>' + esc(l.label) + ' <small><code>' + esc(l.file) + '</code> · ' + l.rings.length + '/50</small></h4>' +
					'<table class="table table-condensed"><thead><tr><th>' + esc(L.name) + '</th><th>' + esc(L.file) + '</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
			}).join(''));
		});
	}
	$('#csm-bg').on('click', '.csm-bg-del', function () {
		if (!confirm(L.delbg.replace('%s', $(this).data('name')))) { return; }
		post({ command: 'bgdelete', fam: $(this).data('fam'), name: $(this).data('name') }).done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); loadBg(); });
	});
	$('#csm-rings').on('change', '.csm-ring-name', function () {
		post({ command: 'ringrename', kind: $(this).data('kind'), file: $(this).data('file'), name: $(this).val() }).done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); });
	}).on('click', '.csm-ring-del', function () {
		var f = $(this).data('file'), k = $(this).data('kind');
		if (!confirm(L.delring.replace('%s', f))) { return; }
		post({ command: 'ringdelete', kind: k, file: f, delfile: confirm(L.delfile.replace('%s', f)) ? 1 : '' })
			.done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); loadRings(); });
	});
	loadBg();
	loadRings();
});
</script>
