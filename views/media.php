<?php include __DIR__ . '/_nav.php'; ?>

<div class="row">
<div class="col-md-7">
	<h3>Fondos de pantalla</h3>
	<p class="help-block">Se guardan en <code><?= $h($settings['tftp_dir']) ?>/Desktops/&lt;resolución&gt;/</code> con su miniatura y se arma el <code>List.xml</code>.
	La imagen se escala y recorta al tamaño de la pantalla. En el teléfono: <i>Ajustes → Preferencias → Fondo de pantalla</i>.</p>
	<form method="post" enctype="multipart/form-data" class="form-inline" style="margin-bottom:12px">
		<input type="hidden" name="csm_action" value="bg_upload">
		<select class="form-control input-sm" name="fam">
			<?php foreach ($families as $k => $f): ?><option value="<?= $h($k) ?>"><?= $h($f['label'] . ' · ' . $f['dir']) ?></option><?php endforeach; ?>
		</select>
		<input type="file" name="image" accept="image/*" class="form-control input-sm" required>
		<button class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> Subir fondo</button>
	</form>
	<div id="csm-bg"></div>
</div>
<div class="col-md-5">
	<h3>Tonos de llamada</h3>
	<p class="help-block">Dos listas en <code><?= $h($settings['tftp_dir']) ?></code>: <code><?= $h(basename(\FreePBX::Ciscosipmanager()->ringlistFile('nb'))) ?></code>
	(banda angosta, <code>.raw</code> µ-law 8 kHz, la usan los 79xx) y <code><?= $h(basename(\FreePBX::Ciscosipmanager()->ringlistFile('wb'))) ?></code>
	(banda ancha, <code>.rwb</code> PCM 16 bits 16 kHz, la usan los 88xx). Subí WAV, MP3 u otro audio y se convierte para cada lista (máximo 2 s, con sox o ffmpeg).
	Hasta 50 tonos por lista. En el teléfono: <i>Ajustes → Preferencias → Tono de llamada</i>.</p>
	<form method="post" enctype="multipart/form-data" style="margin-bottom:12px">
		<input type="hidden" name="csm_action" value="ring_upload">
		<input class="form-control input-sm" name="display" placeholder="Nombre en el teléfono (máx. 25)" maxlength="25" style="margin-bottom:6px">
		<select class="form-control input-sm" name="target" style="margin-bottom:6px">
			<option value="nb,wb">Las dos listas (recomendado)</option>
			<option value="wb">Solo banda ancha (88xx)</option>
			<option value="nb">Solo banda angosta (79xx)</option>
		</select>
		<input type="file" name="ring" accept="audio/*,.raw,.rwb" class="form-control input-sm" required style="margin-bottom:6px">
		<button class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> Subir tono</button>
		<span class="help-block small">Un <code>.raw</code> ya convertido va solo a banda angosta y un <code>.rwb</code> solo a banda ancha.</span>
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
	function loadBg() {
		post({ command: 'bglist' }).done(function (r) {
			$('#csm-bg').html(r.families.map(function (f) {
				var imgs = f.images.length ? f.images.map(function (i) {
					return '<div class="thumbnail" style="display:inline-block;width:160px;margin:0 6px 6px 0;vertical-align:top">' +
						(i.thumb ? '<img src="' + i.thumb + '" style="max-width:100%">' : '<div class="text-muted small" style="height:60px;padding-top:20px;text-align:center">' + (i.thumb_file ? (i.thumb_found ? 'formato no visible' : 'no está ' + esc(i.thumb_file)) : 'sin miniatura en List.xml') + '</div>') +
						'<div class="caption" style="padding:4px"><small>' + esc(i.name) + (i.exists ? '' : ' <span class="text-danger">(falta el .png)</span>') + '</small>' +
						'<button class="btn btn-link btn-xs text-danger pull-right csm-bg-del" data-fam="' + esc(f.key) + '" data-name="' + esc(i.name) + '"><i class="fa fa-trash"></i></button></div></div>';
				}).join('') : '<p class="text-muted small">Sin fondos.</p>';
				return '<h4>' + esc(f.label) + ' <small>Desktops/' + esc(f.dir) + ' · miniatura ' + esc(f.thumb) + '</small></h4>' + imgs;
			}).join(''));
		});
	}
	function loadRings() {
		post({ command: 'ringlist' }).done(function (r) {
			$('#csm-rings').html(r.lists.map(function (l) {
				var rows = l.rings.length ? l.rings.map(function (g) {
					return '<tr><td><input class="form-control input-sm csm-ring-name" data-kind="' + l.kind + '" data-file="' + esc(g.file) + '" value="' + esc(g.name) + '" maxlength="25"></td>' +
						'<td><code>' + esc(g.file) + '</code>' + (g.exists ? '' : ' <span class="text-danger small">(no existe)</span>') + '</td>' +
						'<td><button class="btn btn-link btn-xs text-danger csm-ring-del" data-kind="' + l.kind + '" data-file="' + esc(g.file) + '"><i class="fa fa-trash"></i></button></td></tr>';
				}).join('') : '<tr><td colspan="3" class="text-muted small">' + (l.exists ? 'Lista vacía.' : 'Todavía no existe; se crea al subir el primer tono.') + '</td></tr>';
				return '<h4>' + esc(l.label) + ' <small><code>' + esc(l.file) + '</code> · ' + l.rings.length + '/50</small></h4>' +
					'<table class="table table-condensed"><thead><tr><th>Nombre</th><th>Archivo</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>';
			}).join(''));
		});
	}
	$('#csm-bg').on('click', '.csm-bg-del', function () {
		if (!confirm('¿Borrar el fondo ' + $(this).data('name') + '?')) { return; }
		post({ command: 'bgdelete', fam: $(this).data('fam'), name: $(this).data('name') }).done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); loadBg(); });
	});
	$('#csm-rings').on('change', '.csm-ring-name', function () {
		post({ command: 'ringrename', kind: $(this).data('kind'), file: $(this).data('file'), name: $(this).val() }).done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); });
	}).on('click', '.csm-ring-del', function () {
		var f = $(this).data('file'), k = $(this).data('kind');
		if (!confirm('¿Quitar ' + f + ' de la lista de tonos?')) { return; }
		post({ command: 'ringdelete', kind: k, file: f, delfile: confirm('¿Borrar también el archivo ' + f + '? (Cancelar = solo quitarlo de la lista)') ? 1 : '' })
			.done(function (r) { fpbxToast(r.message, '', r.status ? 'success' : 'error'); loadRings(); });
	});
	loadBg();
	loadRings();
});
</script>
