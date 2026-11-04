$(function () {
	var $table = $('#csm-table');
	if (!$table.length) { return; }

	var ajax = function (data) {
		return $.ajax({ url: 'ajax.php?module=ciscosipmanager', method: 'POST', data: data, dataType: 'json' });
	};
	var netLabel = {
		lan: '<span class="label label-success">LAN</span>',
		vpn: '<span class="label label-info">VPN</span>',
		otra: '<span class="label label-warning">Otra</span>',
		offline: '<span class="label label-default">Offline</span>'
	};
	var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };

	function refresh() {
		ajax({ command: 'status' }).done(function (r) {
			if (!r || !r.phones) { return; }
			r.phones.forEach(function (p) {
				var $tr = $table.find('tr[data-id="' + p.id + '"]');
				var $net = $tr.find('.csm-net');
				$net.find('.label').replaceWith(p.registered ? netLabel[p.net] : (p.ip ? '<span class="label label-danger" title="' + esc(p.status) + '">' + esc(p.status) + '</span>' : netLabel.offline));
				$tr.find('.csm-ip').html(p.ip ? '<a href="http://' + esc(p.ip) + '/" target="_blank">' + esc(p.ip) + '</a>' : '');
				$tr.find('.csm-lat').text(p.latency !== '' ? p.latency + ' ms' : '');
				$tr.find('.csm-ua small').text(p.useragent);
			});
			$('#csm-updated').text('actualizado ' + new Date().toLocaleTimeString());
		});
	}
	refresh();
	setInterval(refresh, 15000);

	$table.on('click', '.csm-act', function (e) {
		e.preventDefault();
		var $a = $(this), id = $a.closest('tr').data('id');
		if ($a.data('confirm') && !confirm('¿Reiniciar por completo el teléfono?')) { return; }
		if ($a.data('confirm-import') && !confirm('Este teléfono fue importado y el archivo es el original. ¿Ya revisaste "Comparar con lo que se generaría"? El original queda en el historial.')) { return; }
		ajax({ command: 'action', id: id, act: $a.data('act') }).done(function (r) {
			fpbxToast(r.message || (r.status ? 'OK' : 'Error'), '', r.status ? 'success' : 'error');
			if ($a.data('act') === 'regen' && r.status) { setTimeout(function () { location.reload(); }, 800); }
		});
	});

	$table.on('click', '.csm-preview', function (e) {
		e.preventDefault();
		ajax({ command: 'preview', id: $(this).closest('tr').data('id') }).done(function (r) {
			$('#csm-xml').text(r.xml || 'Error');
			$('#csm-xml-modal').modal('show');
		});
	});

	// ---------- Historial ----------
	var reasonTxt = { edit: 'Edición', regen: 'Regeneración', restore: 'Restauración', manual: 'Editado a mano', 'delete': 'Borrado' };
	var histId = null;

	function fmtDate(ts) { return new Date(ts * 1000).toLocaleString(); }

	function renderDiff(d) {
		if (!d) { return '<span class="text-muted">Sin diferencias.</span>'; }
		return d.split('\n').map(function (l) {
			var c = l[0] === '+' ? 'color:#2e7d32;background:rgba(46,125,50,.08)' : l[0] === '-' ? 'color:#c62828;background:rgba(198,40,40,.08)' : l.indexOf('@@') === 0 ? 'color:#1565c0' : '';
			return '<span style="display:block;' + c + '">' + esc(l) + '</span>';
		}).join('');
	}

	function loadHistory() {
		ajax({ command: 'versions', id: histId }).done(function (r) {
			if (!r.status) { $('#csm-hist-rows').html('<tr><td colspan="5">' + esc(r.message) + '</td></tr>'); return; }
			$('#csm-hist-dir').text(r.dir);
			$('#csm-hist-warn').html(r.manual_edit ? '<div class="alert alert-warning">El archivo en disco no coincide con la última versión del historial (fue editado fuera del módulo). Se va a guardar como "Editado a mano" antes del próximo cambio.</div>' : '');
			if (!r.versions.length) { $('#csm-hist-rows').html('<tr><td colspan="5" class="text-muted">Todavía no hay versiones. Se empiezan a guardar con la próxima generación.</td></tr>'); return; }
			$('#csm-hist-rows').html(r.versions.map(function (v) {
				return '<tr data-name="' + esc(v.name) + '">' +
					'<td>' + esc(fmtDate(v.time)) + '</td>' +
					'<td>' + esc(reasonTxt[v.reason] || v.reason) + '</td>' +
					'<td>' + Math.round(v.size / 102.4) / 10 + ' KB</td>' +
					'<td>' + (v.current ? '<span class="label label-success">actual</span>' : '') + '</td>' +
					'<td style="white-space:nowrap">' +
						'<button class="btn btn-default btn-xs csm-hv-view">Ver</button> ' +
						(v.current ? '' : '<button class="btn btn-default btn-xs csm-hv-diff">Diff vs actual</button> <button class="btn btn-warning btn-xs csm-hv-restore">Restaurar</button>') +
					'</td></tr>';
			}).join(''));
		});
	}

	$table.on('click', '.csm-history', function (e) {
		e.preventDefault();
		var $tr = $(this).closest('tr');
		histId = $tr.data('id');
		$('#csm-hist-title').text($tr.data('ext') + ' · ' + $tr.find('code').text());
		$('#csm-hist-view').hide();
		$('#csm-hist-rows').html('<tr><td colspan="5"><i class="fa fa-spinner fa-spin"></i></td></tr>');
		$('#csm-hist-modal').modal('show');
		loadHistory();
	});

	$('#csm-hist-rows').on('click', '.csm-hv-view', function () {
		var name = $(this).closest('tr').data('name');
		ajax({ command: 'version', id: histId, name: name }).done(function (r) {
			$('#csm-hist-view-title').text(name);
			$('#csm-hist-pre').text(r.xml || r.message);
			$('#csm-hist-view').show();
		});
	}).on('click', '.csm-hv-diff', function () {
		var name = $(this).closest('tr').data('name');
		ajax({ command: 'diff', id: histId, name: name, against: 'current' }).done(function (r) {
			$('#csm-hist-view-title').text('Cambios de ' + name + ' → actual');
			$('#csm-hist-pre').html(r.status ? renderDiff(r.diff) : esc(r.message));
			$('#csm-hist-view').show();
		});
	}).on('click', '.csm-hv-restore', function () {
		var name = $(this).closest('tr').data('name');
		if (!confirm('¿Restaurar esta versión del SEP? Después tenés que reiniciar o recargar la config del teléfono.')) { return; }
		ajax({ command: 'restoreversion', id: histId, name: name }).done(function (r) {
			fpbxToast(r.message, '', r.status ? 'success' : 'error');
			loadHistory();
		});
	});

	// ---------- Ver pantalla ----------
	var screenId = null, screenTimer = null, screenBusy = false;

	function loadScreen() {
		if (screenBusy || screenId === null) { return; }
		screenBusy = true;
		if (!$('#csm-screen-img').is(':visible')) { $('#csm-screen-msg').html('<i class="fa fa-spinner fa-spin"></i> Pidiendo captura…'); }
		ajax({ command: 'screenshot', id: screenId }).done(function (r) {
			if (r.status && r.image) {
				$('#csm-screen-img').attr('src', r.image).show();
				$('#csm-screen-msg').empty();
				$('#csm-screen-time').text((r.ip ? r.ip + ' · ' : '') + 'captura ' + r.time);
			} else {
				$('#csm-screen-img').hide();
				$('#csm-screen-msg').html('<div class="alert alert-danger" style="text-align:left">' + esc(r.message) + '</div>');
				$('#csm-screen-auto').prop('checked', false).trigger('change');
			}
		}).fail(function () {
			$('#csm-screen-msg').html('<div class="alert alert-danger">Error de red</div>');
		}).always(function () { screenBusy = false; });
	}

	$table.on('click', '.csm-screen', function (e) {
		e.preventDefault();
		var $tr = $(this).closest('tr');
		screenId = $tr.data('id');
		$('#csm-screen-title').text($tr.data('ext') + ' · ' + ($tr.data('label') || ''));
		$('#csm-screen-img').hide().attr('src', '');
		$('#csm-screen-time').text('');
		$('#csm-screen-modal').modal('show');
		loadScreen();
	});
	$('#csm-screen-refresh').on('click', loadScreen);
	$('#csm-screen-auto').on('change', function () {
		clearInterval(screenTimer);
		screenTimer = this.checked ? setInterval(loadScreen, 3000) : null;
	});
	$('#csm-screen-modal').on('hidden.bs.modal', function () {
		clearInterval(screenTimer);
		screenTimer = null;
		screenId = null;
		$('#csm-screen-auto').prop('checked', false);
	});

	// ---------- Control remoto ----------
	var keyBusy = false;
	$('#csm-screen-modal').on('click', '.csm-key', function () {
		if (keyBusy || screenId === null) { return; }
		keyBusy = true;
		var $b = $(this).addClass('active');
		ajax({ command: 'key', id: screenId, key: $b.data('key') }).done(function (r) {
			if (!r.status) { fpbxToast(r.message, '', 'error'); }
			setTimeout(loadScreen, 600);
		}).always(function () { keyBusy = false; $b.removeClass('active'); });
	});
	function dialFrom(id, number, $out) {
		return ajax({ command: 'dial', id: id, number: number }).done(function (r) {
			var html = '<div class="text-' + (r.status ? 'success' : 'danger') + '">' + esc(r.status ? 'Marcando ' + number + '…' : r.message) + '</div>';
			if ($out) { $out.html(html); } else { fpbxToast(r.status ? 'Marcando ' + number : r.message, '', r.status ? 'success' : 'error'); }
		});
	}
	$('#csm-screen-dial-btn').on('click', function () {
		var n = $.trim($('#csm-screen-dial').val());
		if (n && screenId !== null) { dialFrom(screenId, n).always(function () { setTimeout(loadScreen, 1500); }); }
	});
	$('#csm-screen-dial').on('keydown', function (e) { if (e.which === 13) { $('#csm-screen-dial-btn').click(); } });

	// ---------- Llamar ----------
	var callId = null;
	$table.on('click', '.csm-call', function (e) {
		e.preventDefault();
		var $tr = $(this).closest('tr');
		callId = $tr.data('id');
		$('#csm-call-title').text($tr.data('ext'));
		$('#csm-call-result').empty();
		$('#csm-call-modal').modal('show');
		setTimeout(function () { $('#csm-call-number').focus(); }, 300);
	});
	$('#csm-call-go').on('click', function () {
		var n = $.trim($('#csm-call-number').val());
		if (n) { dialFrom(callId, n, $('#csm-call-result')); }
	});
	$('#csm-call-number').on('keydown', function (e) { if (e.which === 13) { $('#csm-call-go').click(); } });

	// ---------- Acciones masivas ----------
	function selectedIds() {
		return $table.find('.csm-sel:checked').map(function () { return $(this).closest('tr').data('id'); }).get();
	}
	function updateBulk() {
		var n = selectedIds().length;
		$('#csm-bulk-count').text(n);
		$('#csm-bulk').toggle(n > 0);
	}
	$('#csm-sel-all').on('change', function () { $table.find('.csm-sel').prop('checked', this.checked); updateBulk(); });
	$table.on('change', '.csm-sel', updateBulk);

	var bulkStop = false;
	var actName = { regen: 'Regenerar SEP', checkcfg: 'Recargar config', restart: 'Reiniciar', reset: 'Reset completo', message: 'Mensaje' };
	function log(txt, cls) { $('#csm-bulk-log').append('<div class="' + (cls || '') + '">' + esc(new Date().toLocaleTimeString() + '  ' + txt) + '</div>').scrollTop(1e6); }
	function wait(ms) { return $.Deferred(function (d) { setTimeout(d.resolve, ms); }).promise(); }

	// Espera que el teléfono vuelva a estar registrado (mín. 30 s, máx. 4 min)
	function waitRegistered(id) {
		var start = Date.now();
		var d = $.Deferred();
		(function poll() {
			if (bulkStop) { return d.resolve(false); }
			var elapsed = Date.now() - start;
			if (elapsed < 30000) { return setTimeout(poll, 5000); }
			if (elapsed > 240000) { return d.resolve(false); }
			ajax({ command: 'status' }).done(function (r) {
				var st = (r.phones || []).filter(function (p) { return p.id === id; })[0];
				if (st && st.registered) { d.resolve(true); } else { setTimeout(poll, 5000); }
			}).fail(function () { setTimeout(poll, 5000); });
		})();
		return d.promise();
	}

	function runBulk(act, ids, extra, staggerOpt) {
		if (!ids.length) { return; }
		var rows = ids.map(function (id) { return $table.find('tr[data-id="' + id + '"]'); });
		var pending = rows.filter(function ($r) { return $r.data('pending') == 1; });
		if (act === 'regen' && pending.length && !confirm(pending.length + ' de los seleccionados son importados sin revisar. ¿Regenerarlos igual? (el original queda en el historial)')) {
			ids = ids.filter(function (id) { return $table.find('tr[data-id="' + id + '"]').data('pending') != 1; });
			if (!ids.length) { return; }
		}
		if ((act === 'restart' || act === 'reset') && !confirm('¿' + actName[act] + ' ' + ids.length + ' teléfono(s)?')) { return; }
		var stagger = (act === 'restart' || act === 'reset') && (typeof staggerOpt === 'boolean' ? staggerOpt : $('#csm-bulk-stagger').is(':checked'));
		bulkStop = false;
		$('#csm-bulk-title').text(actName[act] + ' · ' + ids.length + ' teléfono(s)');
		$('#csm-bulk-log').empty();
		$('#csm-bulk-bar').css('width', '0%').removeClass('progress-bar-danger');
		$('#csm-bulk-close').prop('disabled', true);
		$('#csm-bulk-stop').prop('disabled', false);
		$('#csm-bulk-modal').modal('show');
		var i = 0, fails = 0;
		(function next() {
			if (bulkStop || i >= ids.length) {
				log(bulkStop ? 'Detenido.' : 'Terminado: ' + (ids.length - fails) + ' OK, ' + fails + ' con error.', 'text-info');
				$('#csm-bulk-close').prop('disabled', false);
				$('#csm-bulk-stop').prop('disabled', true);
				if (act === 'regen') { $('#csm-bulk-close').one('click', function () { location.reload(); }); }
				return;
			}
			var id = ids[i], ext = $table.find('tr[data-id="' + id + '"]').data('ext');
			var data = $.extend({ command: 'action', id: id, act: act }, extra || {});
			ajax(data).done(function (r) {
				log(ext + ': ' + (r.status ? 'OK' : r.message), r.status ? 'text-success' : 'text-danger');
				if (!r.status) { fails++; }
				var after = (stagger && r.status) ? (log(ext + ': esperando que vuelva a registrar…', 'text-muted'), waitRegistered(id).done(function (ok) {
					log(ext + ': ' + (ok ? 'registrado de nuevo' : 'no volvió a registrar a tiempo'), ok ? 'text-success' : 'text-warning');
					if (!ok && !bulkStop && i < ids.length - 1 && !confirm(ext + ' no volvió a registrar. ¿Seguir con los demás?')) { bulkStop = true; }
				})) : wait(300);
				$.when(after).always(function () { i++; $('#csm-bulk-bar').css('width', Math.round(100 * i / ids.length) + '%'); next(); });
			}).fail(function () { log(ext + ': error de red', 'text-danger'); fails++; i++; next(); });
		})();
	}
	$('#csm-bulk-stop').on('click', function () { bulkStop = true; $(this).prop('disabled', true); log('Deteniendo después del teléfono actual…', 'text-warning'); });
	// Restart / Reboot de todos los teléfonos (barra superior)
	$('.csm-all').on('click', function () {
		var ids = $table.find('tr[data-id]').map(function () { return $(this).data('id'); }).get();
		runBulk($(this).data('act'), ids, null, $('#csm-all-stagger').is(':checked'));
	});
	$('#csm-bulk').on('click', '.csm-bulk', function () {
		var act = $(this).data('act');
		if (act === 'message') {
			$('#csm-msg-modal').modal('show', $('<a data-id="sel">')[0]);
			$('#csm-msg-id').val('sel');
			return;
		}
		runBulk(act, selectedIds());
	});

	$table.on('click', '.csm-diffgen', function (e) {
		e.preventDefault();
		ajax({ command: 'diffgen', id: $(this).closest('tr').data('id') }).done(function (r) {
			$('#csm-xml-modal .modal-title').text('Archivo actual → lo que generaría el módulo');
			$('#csm-xml').html(r.status ? renderDiff(r.diff) : esc(r.message));
			$('#csm-xml-modal').modal('show');
		});
	});
	$('#csm-xml-modal').on('hidden.bs.modal', function () { $(this).find('.modal-title').text('SEP generado'); });

	// ---------- Editar en un cuadro ----------
	$table.on('click', '.csm-edit', function (e) {
		if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) { return; } // abrir en pestaña nueva sigue funcionando
		e.preventDefault();
		var $tr = $(this).closest('tr');
		$('#csm-edit-title').text($tr.data('ext') + ' · ' + ($tr.data('label') || ''));
		$('#csm-edit-msg').empty();
		$('#csm-edit-body').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>');
		$('#csm-edit-modal').modal('show');
		ajax({ command: 'phoneform', id: $tr.data('id') }).done(function (r) {
			if (r.status) { $('#csm-edit-body').html(r.html); } else { $('#csm-edit-body').html('<div class="alert alert-danger">' + esc(r.message) + '</div>'); }
		}).fail(function () { $('#csm-edit-body').html('<div class="alert alert-danger">Error de red</div>'); });
	});
	$('#csm-edit-modal').on('submit', 'form.csm-phone-form', function (e) {
		e.preventDefault();
		var $f = $(this), $btn = $f.find('button.btn-primary').prop('disabled', true);
		$.ajax({ url: 'ajax.php?module=ciscosipmanager&command=savephone', method: 'POST', data: $f.serialize(), dataType: 'json' }).done(function (r) {
			if (r.status) {
				$('#csm-edit-modal').modal('hide');
				fpbxToast(r.message, '', 'success');
				setTimeout(function () { location.reload(); }, 500);
			} else {
				$('#csm-edit-msg').html('<div class="alert alert-danger">' + esc(r.message) + '</div>');
				$('#csm-edit-modal').scrollTop(0);
			}
		}).fail(function () {
			$('#csm-edit-msg').html('<div class="alert alert-danger">Error de red</div>');
		}).always(function () { $btn.prop('disabled', false); });
	});

	$table.on('click', '.csm-del', function (e) {
		e.preventDefault();
		if (!confirm('¿Eliminar este teléfono y su archivo SEP?')) { return; }
		var $f = $('#csm-del-form');
		$f.find('[name=id]').val($(this).closest('tr').data('id'));
		$f.submit();
	});

	$('#csm-msg-modal').on('show.bs.modal', function (e) {
		$('#csm-msg-id').val($(e.relatedTarget).data('id'));
		$('#csm-msg-result').empty();
	});

	$('#csm-msg-send').on('click', function () {
		var id = $('#csm-msg-id').val();
		var data = { title: $('#csm-msg-title').val(), text: $('#csm-msg-text').val(), beep: $('#csm-msg-beep').is(':checked') ? 1 : '' };
		if (id === 'sel') { $('#csm-msg-modal').modal('hide'); runBulk('message', selectedIds(), data); return; }
		if (id === 'all') { data.command = 'messageall'; } else { data.command = 'action'; data.act = 'message'; data.id = id; }
		var $res = $('#csm-msg-result').html('<i class="fa fa-spinner fa-spin"></i> Enviando…');
		ajax(data).done(function (r) {
			if (r.results) {
				$res.html(r.results.map(function (x) {
					return '<div class="text-' + (x.ok ? 'success' : 'danger') + '">' + esc(x.ext) + ': ' + esc(x.msg) + '</div>';
				}).join(''));
			} else {
				$res.html('<div class="text-' + (r.status ? 'success' : 'danger') + '">' + esc(r.message) + '</div>');
			}
		}).fail(function () { $res.html('<div class="text-danger">Error de red</div>'); });
	});
});
