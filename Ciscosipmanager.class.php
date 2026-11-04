<?php
namespace FreePBX\modules;

require_once __DIR__ . '/lib/CnfBuilder.php';
require_once __DIR__ . '/lib/History.php';
require_once __DIR__ . '/lib/Importer.php';
require_once __DIR__ . '/lib/Remote.php';
require_once __DIR__ . '/lib/JsStrings.php';

use FreePBX\modules\Ciscosipmanager\CnfBuilder;
use FreePBX\modules\Ciscosipmanager\History;
use FreePBX\modules\Ciscosipmanager\Importer;
use FreePBX\modules\Ciscosipmanager\Remote;

class Ciscosipmanager extends \FreePBX_Helpers implements \BMO
{
	const TABLE = 'ciscosipmanager_phones';
	const NOTIFY_FILE = 'sip_notify_ciscosipmanager.conf';

	private $flash = [];

	/** Ajustes globales y su valor por defecto */
	const DEFAULTS = [
		'tftp_dir'            => '/tftpboot',
		'history_dir'         => '',
		'history_keep'        => '20',
		'lan_server'          => '192.168.10.1',
		'vpn_server'          => '',
		'members_order'       => 'vpn_first',
		'softkey_file'        => '',
		'dial_template'       => '',
		'lan_cidr'            => '192.168.10.0/24',
		'vpn_cidr'            => '192.168.11.0/24',
		'sip_port'            => '5060',
		'sip_transport'       => '1',
		'rtp_start'           => '16384',
		'rtp_end'             => '32766',
		'register_expires'    => '3600',
		'ntp'                 => '',
		'timezone'            => 'SA Eastern Standard Time',
		'date_template'       => 'D/M/Y',
		'vm_number'           => '*97',
		'ssh_user'            => 'admin',
		'ssh_pass'            => 'admin',
		'ssh_access'          => '0',
		'web_access'          => '1',
		'locale_name'         => 'Spanish_Spain',
		'locale_lang'         => 'es',
		'locale_version'      => '',
		'network_locale'      => 'Argentina',
		'url_authenticationURL' => 'http://{SERVER}:6972/authenticate.php',
		'url_directoryURL'    => '',
		'url_servicesURL'     => '',
		'url_idleURL'         => '',
		'url_informationURL'  => '',
		'url_messagesURL'     => '',
		'url_proxyServerURL'  => '',
		'push_user'           => 'csm',
		'push_pass'           => '',
		'services_dir'        => '',
		'ringlist_file'       => 'RingList.xml',
		'ringlist_wb_file'    => 'RingList-wb.xml',
		'wake_display'        => '1',
		'wake_minutes'        => '1',
		'occtl_cmd'           => 'sudo -n /usr/bin/occtl',
		'bg_dir_88'           => '800x480x24',
		'bg_thumb_88'         => '139x109',
		'bg_dir_79'           => '320x212x16',
		'bg_thumb_79'         => '80x53',
		'bg_dir_7975'         => '320x216x16',
		'bg_thumb_7975'       => '80x53',
		'capf_enabled'        => '1',
		'capf_server'         => '',
		'capf_port'           => '3804',
		'capf_auth_mode'      => '2',
		'vpn_url'             => '',
		'vpn_url2'            => '',
		'vpn_cert_pem'        => '',
		'vpn_cert_hash'       => '',
		'vpn_mtu'             => '1290',
		'vpn_auth_method'     => '2',
		'vpn_pswd_persistent' => '1',
		'vpn_auto_net_detect' => '1',
		'fw_7945' => '', 'fw_7965' => '', 'fw_7975' => '',
		'fw_8811' => '', 'fw_8841' => '', 'fw_8845' => '',
		'fw_8851' => '', 'fw_8861' => '', 'fw_8865' => '',
	];

	public function __construct($freepbx = null)
	{
		if ($freepbx === null) {
			throw new \Exception('Not given a FreePBX Object');
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}

	/* ===================== Ciclo de vida ===================== */

	public function install()
	{
		foreach (self::DEFAULTS as $k => $v) {
			if ($this->getConfig($k) === false) {
				$this->setConfig($k, $v);
			}
		}
		// 17.6.3: los nombres por defecto de las listas de tonos pasan a RingList.xml / RingList-wb.xml
		if ($this->getConfig('ringlist_file') === 'Ringlist.xml') {
			$this->setConfig('ringlist_file', 'RingList.xml');
		}
		if ($this->getConfig('ringlist_wb_file') === 'Ringlist-wb.xml') {
			$this->setConfig('ringlist_wb_file', 'RingList-wb.xml');
		}
		if (!$this->getConfig('push_pass')) {
			$this->setConfig('push_pass', bin2hex(random_bytes(8)));
		}
		$this->writeNotifyConf();
		$this->asteriskCommand('sip reload');
	}

	public function uninstall()
	{
		$etc = $this->etcDir();
		@unlink($etc . '/' . self::NOTIFY_FILE);
		$custom = $etc . '/sip_notify_custom.conf';
		if (is_writable($custom)) {
			$c = file_get_contents($custom);
			$c = preg_replace('/^#include\s+' . preg_quote(self::NOTIFY_FILE, '/') . '\s*$\n?/m', '', $c);
			file_put_contents($custom, $c);
		}
	}

	public function backup() {}
	public function restore($backup) {}

	/** retrieve_conf (Aplicar configuración) */
	public function genConfig()
	{
		return [self::NOTIFY_FILE => CnfBuilder::sipNotifyConf()];
	}

	public function writeConfig($conf)
	{
		$this->writeNotifyConf();
	}

	private function etcDir()
	{
		$d = $this->FreePBX->Config->get('ASTETCDIR');
		return $d ? rtrim($d, '/') : '/etc/asterisk';
	}

	private function writeNotifyConf()
	{
		$etc = $this->etcDir();
		@file_put_contents($etc . '/' . self::NOTIFY_FILE, CnfBuilder::sipNotifyConf());
		$custom = $etc . '/sip_notify_custom.conf';
		$c = file_exists($custom) ? file_get_contents($custom) : '';
		if (strpos($c, self::NOTIFY_FILE) === false) {
			@file_put_contents($custom, rtrim($c) . "\n#include " . self::NOTIFY_FILE . "\n");
		}
	}

	/* ===================== Ajustes ===================== */

	public function getSettings()
	{
		$s = [];
		foreach (self::DEFAULTS as $k => $v) {
			$val = $this->getConfig($k);
			$s[$k] = ($val === false || $val === null) ? $v : $val;
		}
		return $s;
	}

	private function saveSettings(array $in)
	{
		foreach (self::DEFAULTS as $k => $v) {
			if (array_key_exists($k, $in)) {
				$this->setConfig($k, trim((string)$in[$k]));
			}
		}
		foreach (['ssh_access', 'web_access', 'vpn_pswd_persistent', 'capf_enabled', 'wake_display'] as $cb) {
			$this->setConfig($cb, empty($in[$cb]) ? '0' : '1');
		}
		$hash = '';
		if (!empty($in['vpn_cert_pem'])) {
			$hash = CnfBuilder::certHash($in['vpn_cert_pem']);
			if ($hash === false) {
				return _('The VPN certificate is not a valid PEM; the hash was not calculated.');
			}
		}
		$this->setConfig('vpn_cert_hash', $hash);
		$this->writeAuthScript();
		return null;
	}

	/* ===================== Teléfonos (DB) ===================== */

	public function getPhones()
	{
		return $this->db->query('SELECT * FROM ' . self::TABLE . ' ORDER BY extension')->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function getPhone($id)
	{
		$st = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ?');
		$st->execute([(int)$id]);
		return $st->fetch(\PDO::FETCH_ASSOC);
	}

	/**
	 * Valida y guarda. Con $generate=false no toca el archivo SEP (importación).
	 * @return string|null error
	 */
	private function savePhone(array $in, $generate = true, &$newId = null)
	{
		$mac = CnfBuilder::normalizeMac($in['mac'] ?? '');
		if (!$mac) {
			return _('Invalid MAC (12 hexadecimal digits).');
		}
		$model = $in['model'] ?? '';
		if (!isset(CnfBuilder::MODELS[$model])) {
			return $model === '' ? _('Choose the model.') : _('Unsupported model.');
		}
		$ext = trim($in['extension'] ?? '');
		$dev = $this->FreePBX->Core->getDevice($ext);
		if (empty($dev)) {
			return sprintf(_('Extension %s does not exist.'), $ext);
		}
		if (($dev['tech'] ?? '') !== 'sip') {
			return sprintf(_('Extension %s is not chan_sip (tech=%s).'), $ext, $dev['tech']);
		}
		if (!empty($in['vpn_enabled']) && !CnfBuilder::supportsVpn($model)) {
			return sprintf(_('The %s has no AnyConnect VPN client (88xx/89xx/99xx only).'), CnfBuilder::MODELS[$model][0]);
		}
		$network = in_array($in['network'] ?? 'auto', ['auto', 'lan', 'vpn'], true) ? $in['network'] : 'auto';
		$extra = trim($in['extra_xml'] ?? '');
		if ($extra !== '' && !CnfBuilder::validFragment($extra)) {
			return _('The extra XML is not a valid XML fragment.');
		}
		$extraLines = trim($in['extra_lines_xml'] ?? '');
		if ($extraLines !== '' && !CnfBuilder::validFragment($extraLines)) {
			return _('The additional lines are not a valid XML fragment.');
		}
		$row = [
			':mac' => $mac,
			':model' => $model,
			':extension' => $ext,
			':label' => trim($in['label'] ?? ''),
			':network' => $network,
			':server_override' => trim($in['server_override'] ?? ''),
			':vpn_enabled' => empty($in['vpn_enabled']) ? 0 : 1,
			':extra_xml' => $extra,
			':extra_lines_xml' => $extraLines,
			':video' => CnfBuilder::supportsVideo($model) && isset($in['video']) && $in['video'] !== '' ? (int)!empty($in['video']) : null,
		];
		try {
			if (!empty($in['id'])) {
				$old = $this->getPhone($in['id']);
				$row[':id'] = (int)$in['id'];
				$this->db->prepare('UPDATE ' . self::TABLE . ' SET mac=:mac, model=:model, extension=:extension, label=:label, network=:network, server_override=:server_override, vpn_enabled=:vpn_enabled, extra_xml=:extra_xml, extra_lines_xml=:extra_lines_xml, video=:video WHERE id=:id')->execute($row);
				if ($old && $old['mac'] !== $mac) {
					$this->removeCnf($old['mac']);
				}
				$id = (int)$in['id'];
			} else {
				$this->db->prepare('INSERT INTO ' . self::TABLE . ' (mac, model, extension, label, network, server_override, vpn_enabled, extra_xml, extra_lines_xml, video, last_generated) VALUES (:mac,:model,:extension,:label,:network,:server_override,:vpn_enabled,:extra_xml,:extra_lines_xml,:video,0)')->execute($row);
				$id = (int)$this->db->lastInsertId();
			}
		} catch (\PDOException $e) {
			return sprintf(_('Database error (duplicate MAC?): %s'), $e->getMessage());
		}
		$newId = $id;
		return $generate ? $this->generateCnf($this->getPhone($id), 'edit') : null;
	}

	/* ===================== Importación de SEP existentes ===================== */

	/** Ubicación deducida del archivo: 2+ servidores = Auto; uno solo en la red VPN (o autoNetDetect 0) = VPN; si no, LAN */
	private function guessNetwork(array $r, array $s)
	{
		if (count($r['members']) >= 2) {
			return 'auto';
		}
		$one = $r['members'][0] ?? '';
		if (($one !== '' && self::ipInCidr($one, $s['vpn_cidr'])) || ($r['vpn'] && $r['auto_net_detect'] === 0)) {
			return 'vpn';
		}
		return $r['vpn'] ? 'auto' : 'lan';
	}

	/** XML extra para un archivo importado: lo que difiere de lo que generaría el módulo con ese modelo */
	private function importExtras(array $r, $content, $model)
	{
		$model = isset(CnfBuilder::MODELS[$model]) ? $model : ($r['model_hint'] ? '8845' : '7945');
		$p = [
			'mac' => $r['mac'], 'model' => $model, 'extension' => $r['extension'], 'label' => $r['label'],
			'network' => $r['network'], 'server_override' => '', 'vpn_enabled' => $r['vpn'] ? 1 : 0,
			'extra_xml' => '', 'extra_lines_xml' => $r['extra_lines_xml'],
		];
		return Importer::extras($content, $this->buildCnf($p));
	}

	/** SEP en el directorio de provisión que todavía no están en el módulo */
	public function scanExisting()
	{
		$dir = rtrim($this->getSettings()['tftp_dir'], '/');
		$managed = array_column($this->getPhones(), 'mac');
		$used = array_column($this->getPhones(), 'extension');
		$s = $this->getSettings();
		$out = [];
		foreach (glob($dir . '/SEP*.cnf.xml') ?: [] as $f) {
			$content = file_get_contents($f);
			$r = Importer::parse($content, basename($f));
			if (!$r['mac'] || in_array($r['mac'], $managed, true)) {
				continue;
			}
			if ($r['extension'] !== '') {
				$dev = $this->FreePBX->Core->getDevice($r['extension']);
				if (empty($dev)) {
					$r['errors'][] = sprintf(_('Extension %s does not exist in FreePBX'), $r['extension']);
				} elseif (($dev['tech'] ?? '') !== 'sip') {
					$r['errors'][] = sprintf(_('Extension %s is not chan_sip'), $r['extension']);
				} elseif ($r['password'] !== '' && $r['password'] !== ($dev['secret'] ?? '')) {
					$r['warnings'][] = _('The password in the file does not match the FreePBX one: the FreePBX one is used when regenerating');
				}
				if (in_array($r['extension'], $used, true)) {
					$r['warnings'][] = sprintf(_('Extension %s is already assigned to another phone in the module'), $r['extension']);
				}
				// El User-Agent del registro es más confiable que loadInformation
				$st = $this->phoneStatus(['id' => 0, 'extension' => $r['extension']], $s);
				$ua = Importer::modelFromUserAgent($st['useragent']);
				if ($ua) {
					$r['model'] = $ua;
				} elseif (filter_var($st['ip'], FILTER_VALIDATE_IP)) {
					$di = $this->fetchDeviceInfo($st['ip']);
					$sameMac = $di['ok'] && strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $di['info']['MACAddress'] ?? '')) === $r['mac'];
					$real = $sameMac ? Remote::modelFromNumber($di['info']['modelNumber'] ?? '') : '';
					if ($real) {
						$r['model'] = $real;
					}
				}
			}
			if (!$r['model'] && !$r['errors']) {
				$r['warnings'][] = ($r['model_hint'] ? sprintf(_('Choose the model (judging by the firmware it may be %s)'), $r['model_hint']) : _('Choose the model'));
			}
			if ($r['vpn'] && $r['model'] && !CnfBuilder::supportsVpn($r['model'])) {
				$r['vpn'] = false;
				$r['warnings'][] = _('It has a vpnGroup but the model does not support VPN: it will not be included');
			}
			if (!$r['errors']) {
				$r['network'] = $this->guessNetwork($r, $s);
				list($r['extra_xml'], $r['extra_names']) = $this->importExtras($r, $content, $r['model']);
			}
			unset($r['password']);
			$out[] = $r;
		}
		return $out;
	}

	/** Da de alta los seleccionados SIN reescribir el archivo; guarda el original en el historial */
	private function importSelected(array $post)
	{
		$scan = [];
		foreach ($this->scanExisting() as $r) {
			$scan[$r['mac']] = $r;
		}
		$ok = [];
		$errs = [];
		foreach ((array)($post['import'] ?? []) as $mac) {
			if (!isset($scan[$mac])) {
				continue;
			}
			$r = $scan[$mac];
			$model = $post['model'][$mac] ?? $r['model'];
			if (!$r['errors'] && isset(CnfBuilder::MODELS[$model])) {
				list($r['extra_xml']) = $this->importExtras($r, file_get_contents($this->cnfPath($mac)), $model);
			}
			$err = $r['errors'] ? implode('; ', $r['errors']) : $this->savePhone([
				'mac' => $mac,
				'model' => $model,
				'extension' => $r['extension'],
				'label' => $r['label'],
				'network' => $r['network'],
				'vpn_enabled' => $r['vpn'] ? 1 : 0,
				'extra_xml' => $r['extra_xml'],
				'extra_lines_xml' => $r['extra_lines_xml'],
			], false);
			if ($err) {
				$errs[] = "SEP$mac: $err";
				continue;
			}
			$path = $this->cnfPath($mac);
			$this->history()->save($mac, file_get_contents($path), 'import');
			$ok[] = $mac;
		}
		return [$ok, $errs];
	}

	/** Diff entre el SEP en disco y lo que generaría el módulo ahora */
	public function diffGenerated($p)
	{
		$path = $this->cnfPath($p['mac']);
		$cur = is_file($path) ? file_get_contents($path) : '';
		return History::diff($cur, $this->buildCnf($p));
	}

	private function deletePhone($id)
	{
		$p = $this->getPhone($id);
		if ($p) {
			$this->removeCnf($p['mac']);
			$this->db->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = ?')->execute([(int)$id]);
		}
	}

	/* ===================== Provisión ===================== */

	/** IP del PBX vista desde la VPN: ajuste vpn_server o, si está vacío, la primera IP de la red VPN (ej. 192.168.11.1) */
	public static function vpnServer(array $s)
	{
		if (!empty($s['vpn_server'])) {
			return $s['vpn_server'];
		}
		if (strpos($s['vpn_cidr'], '/') !== false) {
			$net = ip2long(explode('/', $s['vpn_cidr'])[0]);
			if ($net !== false) {
				return long2ip($net + 1);
			}
		}
		return $s['lan_server'];
	}

	/** Host para URLs de servicios, NTP y CAPF: la IP LAN (por el túnel también se llega) */
	public function serverFor(array $p, array $s)
	{
		return !empty($p['server_override']) ? $p['server_override'] : $s['lan_server'];
	}

	/**
	 * Servidores de callManagerGroup en orden de prioridad:
	 *  - servidor propio del teléfono: solo ese
	 *  - LAN: solo la IP LAN
	 *  - Siempre VPN: solo la IP del PBX en la VPN
	 *  - Auto: VPN y LAN (orden según Ajustes); si el teléfono no usa VPN, solo LAN
	 */
	public function membersFor(array $p, array $s)
	{
		if (!empty($p['server_override'])) {
			return [$p['server_override']];
		}
		$lan = $s['lan_server'];
		$vpn = self::vpnServer($s);
		$usesVpn = !empty($p['vpn_enabled']) && CnfBuilder::supportsVpn($p['model']);
		switch ($p['network']) {
			case 'lan':
				return [$lan];
			case 'vpn':
				return [$vpn];
			default:
				if (!$usesVpn || $vpn === $lan) {
					return [$lan];
				}
				return ($s['members_order'] ?? 'vpn_first') === 'lan_first' ? [$lan, $vpn] : [$vpn, $lan];
		}
	}

	/** En las líneas adicionales, <authPassword>{FREEPBX}</authPassword> se reemplaza por la clave de esa extensión */
	public function resolveLinePasswords($xml)
	{
		if (strpos((string)$xml, '{FREEPBX}') === false || !CnfBuilder::validFragment($xml)) {
			return $xml;
		}
		$d = new \DOMDocument();
		$d->loadXML('<r>' . $xml . '</r>');
		foreach ($d->getElementsByTagName('line') as $line) {
			$pw = $line->getElementsByTagName('authPassword')->item(0);
			$nm = $line->getElementsByTagName('name')->item(0);
			if ($pw && trim($pw->textContent) === '{FREEPBX}') {
				$dev = $nm ? $this->FreePBX->Core->getDevice(trim($nm->textContent)) : null;
				$pw->nodeValue = '';
				$pw->appendChild($d->createTextNode($dev['secret'] ?? ''));
			}
		}
		$out = '';
		foreach ($d->documentElement->childNodes as $c) {
			$out .= $d->saveXML($c, LIBXML_NOEMPTYTAG);
		}
		return $out;
	}

	public function buildCnf(array $p)
	{
		$s = $this->getSettings();
		$p['extra_lines_xml'] = $this->resolveLinePasswords($p['extra_lines_xml'] ?? '');
		$dev = $this->FreePBX->Core->getDevice($p['extension']);
		return CnfBuilder::build($p, $dev ?: [], $s, $this->serverFor($p, $s), $this->membersFor($p, $s));
	}

	private function cnfPath($mac)
	{
		$dir = rtrim($this->getSettings()['tftp_dir'], '/');
		return $dir . '/SEP' . $mac . '.cnf.xml';
	}

	public function history()
	{
		$s = $this->getSettings();
		$dir = $s['history_dir'];
		if (!$dir) {
			$var = $this->FreePBX->Config->get('ASTVARLIBDIR') ?: '/var/lib/asterisk';
			$dir = rtrim($var, '/') . '/ciscosipmanager/history';
		}
		return new History($dir, $s['history_keep']);
	}

	/** Si el SEP en disco no coincide con la última versión del historial (editado a mano), lo guarda como 'manual' */
	private function captureManual($mac)
	{
		$path = $this->cnfPath($mac);
		if (is_file($path)) {
			$this->history()->save($mac, file_get_contents($path), 'manual');
		}
	}

	private function writeCnfFile($mac, $content)
	{
		$path = $this->cnfPath($mac);
		$dir = dirname($path);
		if (!is_dir($dir) || !is_writable($dir)) {
			return sprintf(_('Cannot write to %s (check the permissions for the asterisk user).'), $dir);
		}
		if (file_put_contents($path . '.tmp', $content) === false || !rename($path . '.tmp', $path)) {
			return sprintf(_('Error writing %s'), $path);
		}
		@chmod($path, 0644);
		return null;
	}

	/**
	 * Genera el SEP. Guarda la versión anterior (si fue editada a mano) y la nueva en el historial.
	 * @return string|null mensaje de error
	 */
	public function generateCnf($p, $reason = 'regen')
	{
		if (!$p) {
			return _('Phone not found.');
		}
		$content = $this->buildCnf($p);
		$path = $this->cnfPath($p['mac']);
		if (is_file($path) && History::same(file_get_contents($path), $content)) {
			// Sin cambios: no se reescribe ni se crea versión nueva
			$this->history()->save($p['mac'], file_get_contents($path), $reason);
			return null;
		}
		$this->captureManual($p['mac']);
		if ($err = $this->writeCnfFile($p['mac'], $content)) {
			return $err;
		}
		$this->history()->save($p['mac'], $content, $reason);
		$this->db->prepare('UPDATE ' . self::TABLE . ' SET last_generated = ? WHERE id = ?')->execute([time(), $p['id']]);
		return null;
	}

	/** Importado y todavía no regenerado: el archivo en disco es el original del usuario */
	public static function isPending(array $p)
	{
		return (int)$p['last_generated'] === 0;
	}

	public function regenAll($reason = 'regen', &$skipped = [])
	{
		$errs = [];
		foreach ($this->getPhones() as $p) {
			if (self::isPending($p)) {
				$skipped[] = $p['extension'];
				continue;
			}
			if ($e = $this->generateCnf($p, $reason)) {
				$errs[] = $p['extension'] . ': ' . $e;
			}
		}
		return $errs;
	}

	private function removeCnf($mac)
	{
		if (CnfBuilder::normalizeMac($mac)) {
			$this->captureManual($mac);
			@unlink($this->cnfPath($mac));
		}
	}

	/** Info del historial de un teléfono para la UI */
	public function versionsFor($p)
	{
		$h = $this->history();
		$list = $h->listVersions($p['mac']);
		$path = $this->cnfPath($p['mac']);
		$cur = is_file($path) ? file_get_contents($path) : null;
		foreach ($list as &$v) {
			$v['current'] = $cur !== null && History::same($h->get($p['mac'], $v['name']), $cur);
		}
		unset($v);
		return [
			'versions' => $list,
			'on_disk' => $cur !== null,
			'manual_edit' => $cur !== null && (!$list || !History::same($h->get($p['mac'], $list[0]['name']), $cur)),
			'dir' => $h->dir(),
		];
	}

	public function restoreVersion($p, $name)
	{
		$h = $this->history();
		$content = $h->get($p['mac'], $name);
		if ($content === false) {
			return _('Version not found.');
		}
		$this->captureManual($p['mac']);
		if ($err = $this->writeCnfFile($p['mac'], $content)) {
			return $err;
		}
		$h->save($p['mac'], $content, 'restore');
		return null;
	}

	/** Llamado por Restore.php después de una restauración de FreePBX */
	public function postRestore()
	{
		$this->writeNotifyConf();
		$this->writeAuthScript();
		$errs = $this->regenAll('restore');
		// Importados sin regenerar: se reescribe el archivo original desde el historial
		$h = $this->history();
		foreach ($this->getPhones() as $p) {
			if (self::isPending($p) && ($v = $h->listVersions($p['mac']))) {
				if ($e = $this->writeCnfFile($p['mac'], $h->get($p['mac'], $v[0]['name']))) {
					$errs[] = $p['extension'] . ': ' . $e;
				}
			}
		}
		$this->asteriskCommand('sip reload');
		return $errs;
	}

	/** authenticate.php para el vhost de servicios XML (:6972) */
	/** authenticate.php para el vhost de servicios XML (:6972) */
	public function authScript()
	{
		$s = $this->getSettings();
		$u = var_export($s['push_user'], true);
		$h = var_export(password_hash($s['push_pass'], PASSWORD_DEFAULT), true);
		return "<?php\n// Generado por FreePBX Cisco SIP Manager. Los Cisco lo consultan antes de aceptar un push a /CGI/Execute.\n"
			. "\$u = \$_REQUEST['UserID'] ?? '';\n\$p = \$_REQUEST['Password'] ?? '';\n"
			. "header('Content-Type: text/plain');\n"
			. "echo (hash_equals($u, \$u) && password_verify(\$p, $h)) ? 'AUTHORIZED' : 'UN-AUTHORIZED';\n";
	}

	private function writeAuthScript()
	{
		$dir = $this->getSettings()['services_dir'];
		if ($dir && is_dir($dir) && is_writable($dir)) {
			@file_put_contents(rtrim($dir, '/') . '/authenticate.php', $this->authScript());
		}
	}

	/* ===================== Estado (AMI / chan_sip) ===================== */

	private function astman()
	{
		$a = $this->FreePBX->astman;
		return ($a && $a->connected()) ? $a : null;
	}

	private function asteriskCommand($cmd)
	{
		$a = $this->astman();
		if (!$a) {
			return false;
		}
		$r = $a->send_request('Command', ['Command' => $cmd]);
		return $r['data'] ?? '';
	}

	public static function ipInCidr($ip, $cidr)
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || strpos($cidr, '/') === false) {
			return false;
		}
		list($net, $bits) = explode('/', $cidr, 2);
		$mask = $bits == 0 ? 0 : (~0 << (32 - (int)$bits)) & 0xFFFFFFFF;
		return (ip2long($ip) & $mask) === (ip2long($net) & $mask);
	}

	public function phoneStatus(array $p, array $s)
	{
		$st = ['id' => (int)$p['id'], 'registered' => false, 'ip' => '', 'net' => 'offline', 'latency' => '', 'useragent' => '', 'status' => 'Sin AMI'];
		$a = $this->astman();
		if (!$a) {
			return $st;
		}
		$r = $a->send_request('SIPshowpeer', ['Peer' => $p['extension']]);
		$ip = $r['Address-IP'] ?? '';
		$status = $r['Status'] ?? 'UNKNOWN';
		$st['status'] = $status;
		$st['useragent'] = $r['SIP-Useragent'] ?? '';
		if ($ip && $ip !== '(null)' && $ip !== '(Unspecified)') {
			$st['ip'] = $ip;
			$st['registered'] = strpos($status, 'OK') === 0;
			if (preg_match('/\((\d+) ms\)/', $status, $m)) {
				$st['latency'] = (int)$m[1];
			}
			if (self::ipInCidr($ip, $s['vpn_cidr'])) {
				$st['net'] = 'vpn';
			} elseif (self::ipInCidr($ip, $s['lan_cidr'])) {
				$st['net'] = 'lan';
			} else {
				$st['net'] = 'otra';
			}
		}
		return $st;
	}

	public function allStatus()
	{
		$s = $this->getSettings();
		$out = [];
		foreach ($this->getPhones() as $p) {
			$out[] = $this->phoneStatus($p, $s);
		}
		return $out;
	}

	/* ===================== Acciones remotas ===================== */

	public function sipNotify($p, $kind)
	{
		$map = ['restart' => 'cisco-csm-restart', 'reset' => 'cisco-csm-reset', 'checkcfg' => 'cisco-csm-check-cfg'];
		if (!isset($map[$kind])) {
			return ['ok' => false, 'msg' => _('Unknown action')];
		}
		$r = $this->asteriskCommand('sip notify ' . $map[$kind] . ' ' . $p['extension']);
		if ($r === false) {
			return ['ok' => false, 'msg' => _('No AMI connection')];
		}
		return ['ok' => stripos($r, 'Sending NOTIFY') !== false, 'msg' => trim(preg_replace('/^Privilege:.*$/m', '', $r))];
	}

	/**
	 * Captura de pantalla: GET http://IP/CGI/Screenshot con el usuario/clave push
	 * (el teléfono los valida contra authenticationURL, igual que los mensajes).
	 * Los 88xx devuelven PNG; los 79x5, BMP.
	 * @return array ['ok' => bool, 'msg' => string, 'data' => data-URI de la imagen]
	 */
	public function fetchScreenshot($host)
	{
		$s = $this->getSettings();
		$ch = curl_init('http://' . $host . '/CGI/Screenshot');
		curl_setopt_array($ch, [
			CURLOPT_USERPWD => $s['push_user'] . ':' . $s['push_pass'],
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 10,
		]);
		$body = curl_exec($ch);
		$err = curl_error($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body === false) {
			return ['ok' => false, 'msg' => sprintf(_('Could not connect to the phone: %s'), $err)];
		}
		if ($code == 401) {
			return ['ok' => false, 'msg' => _('The phone rejected the push user/password (HTTP 401). Check authenticationURL and that it answers AUTHORIZED.')];
		}
		$mime = null;
		if (strncmp($body, "\x89PNG", 4) === 0) {
			$mime = 'image/png';
		} elseif (strncmp($body, 'BM', 2) === 0) {
			$mime = 'image/bmp';
		} elseif (strncmp($body, "\xFF\xD8", 2) === 0) {
			$mime = 'image/jpeg';
		}
		if (!$mime && preg_match('/<CiscoIPPhoneError\s+Number="(\d+)"/i', $body, $em)) {
			$why = ['4' => ' ' . _('(usually an authentication error: check authenticationURL and the push user/password)')][$em[1]] ?? '';
			return ['ok' => false, 'msg' => sprintf(_('The phone answered CiscoIPPhoneError %s.'), $em[1] . $why)];
		}
		if (!$mime) {
			$txt = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
			return ['ok' => false, 'msg' => sprintf(_('The phone did not return an image (HTTP %s). Is the phone web page enabled?'), $code . ($txt !== '' ? ': ' . mb_substr($txt, 0, 200) : ''))];
		}
		return ['ok' => true, 'msg' => 'OK', 'data' => 'data:' . $mime . ';base64,' . base64_encode($body)];
	}

	public function screenshotPhone($p)
	{
		$st = $this->phoneStatus($p, $this->getSettings());
		if (!filter_var($st['ip'], FILTER_VALIDATE_IP)) {
			return ['ok' => false, 'msg' => _('The phone is not registered (no IP).')];
		}
		return ['ip' => $st['ip']] + $this->fetchScreenshot($st['ip']);
	}

	/** POST a http://IP/CGI/Execute (el teléfono valida user/pass contra authenticationURL) */
	public function pushXml($ip, $xml)
	{
		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			return ['ok' => false, 'msg' => _('Phone without IP (not registered)')];
		}
		$s = $this->getSettings();
		$ch = curl_init('http://' . $ip . '/CGI/Execute');
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => 'XML=' . rawurlencode($xml),
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_USERPWD => $s['push_user'] . ':' . $s['push_pass'],
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 6,
		]);
		$body = curl_exec($ch);
		$err = curl_error($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body === false) {
			return ['ok' => false, 'msg' => sprintf(_('HTTP error: %s'), $err)];
		}
		if ($code == 401 || stripos($body, 'CiscoIPPhoneError') !== false) {
			return ['ok' => false, 'msg' => sprintf(_('The phone rejected the push (HTTP %s): %s — check authenticationURL and user/password.'), $code, trim(strip_tags($body)))];
		}
		return ['ok' => $code == 200, 'msg' => "HTTP $code"];
	}

	public function textXml($title, $text, $beep)
	{
		$x = [CnfBuilder::class, 'x'];
		$xml = '<CiscoIPPhoneText><Title>' . $x($title) . '</Title><Prompt></Prompt><Text>' . $x($text) . '</Text>'
			. '<SoftKeyItem><Name>Cerrar</Name><URL>SoftKey:Exit</URL><Position>1</Position></SoftKeyItem></CiscoIPPhoneText>';
		return $xml;
	}

	public function pushMessage($p, $ip, $title, $text, $beep)
	{
		$s = $this->getSettings();
		$pre = [];
		if (!empty($s['wake_display'])) {
			$pre[] = 'Display:On:' . max(1, (int)$s['wake_minutes']);
		}
		if ($beep) {
			$pre[] = 'Play:Classic2.raw';
		}
		if ($pre) {
			$this->pushXml($ip, Remote::executeXml($pre));
		}
		return $this->pushXml($ip, $this->textXml($title, $text, $beep));
	}

	/* ===================== Control remoto y marcado ===================== */

	public function executeOnPhone($p, array $uris)
	{
		$st = $this->phoneStatus($p, $this->getSettings());
		return $this->pushXml($st['ip'], Remote::executeXml($uris));
	}

	public function sendKey($p, $key)
	{
		if (!Remote::validKey($key)) {
			return ['ok' => false, 'msg' => _('Key not allowed')];
		}
		return $this->executeOnPhone($p, ['Key:' . $key]);
	}

	public function dial($p, $number)
	{
		if (!Remote::validNumber($number)) {
			return ['ok' => false, 'msg' => _('Invalid number (digits, *, # and + only)')];
		}
		$s = $this->getSettings();
		$uris = !empty($s['wake_display']) ? ['Display:On:' . max(1, (int)$s['wake_minutes'])] : [];
		$uris[] = 'Dial:' . $number;
		return $this->executeOnPhone($p, $uris);
	}

	/* ===================== Inventario (DeviceInformationX) ===================== */

	public function fetchDeviceInfo($host)
	{
		$ch = curl_init('http://' . $host . '/DeviceInformationX');
		curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6]);
		$body = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($body === false) {
			return ['ok' => false, 'msg' => sprintf(_('Could not connect: %s'), $err)];
		}
		$info = $code == 200 ? Remote::parseDeviceInfo($body) : null;
		return $info ? ['ok' => true, 'info' => $info] : ['ok' => false, 'msg' => sprintf(_('Invalid response (HTTP %s). Is the phone web page enabled?'), $code)];
	}

	public function inventoryOf($mac)
	{
		$v = $this->getConfig('inv_' . $mac);
		return is_array($v) ? $v : null;
	}

	public function refreshInventory($p)
	{
		$st = $this->phoneStatus($p, $this->getSettings());
		if (!filter_var($st['ip'], FILTER_VALIDATE_IP)) {
			return ['ok' => false, 'msg' => _('not registered')];
		}
		$r = $this->fetchDeviceInfo($st['ip']);
		if ($r['ok']) {
			$this->setConfig('inv_' . $p['mac'], ['ts' => time(), 'ip' => $st['ip'], 'info' => $r['info']]);
		}
		return $r;
	}

	/** Filas para la pestaña Inventario, con chequeos contra lo configurado */
	public function inventoryRows()
	{
		$s = $this->getSettings();
		$rows = [];
		foreach ($this->getPhones() as $p) {
			$inv = $this->inventoryOf($p['mac']);
			$info = $inv['info'] ?? [];
			$real = Remote::modelFromNumber($info['modelNumber'] ?? '');
			$fwWant = $s['fw_' . $p['model']] ?? '';
			$fwHave = $info['versionID'] ?? ($info['appLoadID'] ?? '');
			$warn = [];
			if ($real && $real !== $p['model']) {
				$warn[] = sprintf(_('The phone reports model %s and the module has it as %s'), $real, $p['model']);
			}
			if ($fwWant !== '' && $fwHave !== '' && stripos($fwHave, $fwWant) === false && stripos($fwWant, $fwHave) === false) {
				$warn[] = sprintf(_('Firmware differs from the configured one (%s)'), $fwWant);
			}
			if (!empty($info['MACAddress']) && strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $info['MACAddress'])) !== $p['mac']) {
				$warn[] = _('The reported MAC does not match: the registered IP may belong to another device');
			}
			$rows[] = ['phone' => $p, 'inv' => $inv, 'info' => $info, 'warnings' => $warn];
		}
		return $rows;
	}

	/* ===================== Sesiones VPN (occtl) ===================== */

	private function occtl($args)
	{
		$cmd = trim($this->getSettings()['occtl_cmd']);
		if ($cmd === '' || !preg_match('#^[A-Za-z0-9_./ -]+$#', $cmd)) {
			return [false, _('Invalid occtl command in Settings')];
		}
		$out = [];
		$rc = 0;
		exec($cmd . ' ' . $args . ' 2>&1', $out, $rc);
		return [$rc === 0, implode("\n", $out)];
	}

	/** Parsea `occtl -j show users` (tolera la coma final de versiones viejas) */
	public static function parseOcctlUsers($json)
	{
		$data = json_decode($json, true);
		if (!is_array($data)) {
			$data = json_decode(preg_replace('/,\s*([\]}])/', '$1', $json), true);
		}
		if (!is_array($data)) {
			return null;
		}
		$out = [];
		foreach ($data as $u) {
			if (!is_array($u)) {
				continue;
			}
			$out[] = [
				'id' => (int)($u['ID'] ?? 0),
				'user' => (string)($u['Username'] ?? ''),
				'state' => (string)($u['State'] ?? ''),
				'real_ip' => (string)($u['Remote IP'] ?? ''),
				'vpn_ip' => (string)($u['IPv4'] ?? ''),
				'since' => (string)($u['Connected at'] ?? ''),
				'duration' => (string)($u['_Connected at'] ?? ''),
				'rx' => (string)($u['RX'] ?? ''),
				'tx' => (string)($u['TX'] ?? ''),
				'agent' => (string)($u['User agent'] ?? ''),
				'dtls' => (string)($u['DTLS cipher'] ?? ''),
			];
		}
		return $out;
	}

	public function vpnSessions()
	{
		list($ok, $out) = $this->occtl('-j show users');
		if (!$ok) {
			return ['ok' => false, 'msg' => $out ?: _('occtl failed')];
		}
		$users = self::parseOcctlUsers($out);
		if ($users === null) {
			return ['ok' => false, 'msg' => _('Could not parse the occtl output')];
		}
		$byMac = [];
		foreach ($this->getPhones() as $p) {
			$byMac[$p['mac']] = $p;
		}
		foreach ($users as &$u) {
			$mac = preg_match('/^SEP([0-9A-Fa-f]{12})$/', $u['user'], $m) ? strtoupper($m[1]) : '';
			$u['phone'] = $byMac[$mac] ?? null;
		}
		unset($u);
		return ['ok' => true, 'sessions' => $users];
	}

	public function vpnDisconnect($id)
	{
		if (!ctype_digit((string)$id)) {
			return ['ok' => false, 'msg' => _('Invalid ID')];
		}
		list($ok, $out) = $this->occtl('disconnect id ' . (int)$id);
		return ['ok' => $ok, 'msg' => $ok ? _('Session disconnected') : $out];
	}

	/* ===================== Fondos y tonos ===================== */

	public function bgFamilies()
	{
		$s = $this->getSettings();
		$out = [];
		foreach (Remote::BG_FAMILIES as $k => $f) {
			$dir = Remote::validDirSpec($s['bg_dir_' . $k] ?? '') ? $s['bg_dir_' . $k] : $f['dir'];
			$th = Remote::dims($s['bg_thumb_' . $k] ?? '') ? $s['bg_thumb_' . $k] : $f['thumb'];
			$out[$k] = ['label' => $f['label'], 'dir' => $dir, 'thumb' => $th];
		}
		return $out;
	}

	private function bgPath($fam)
	{
		$f = $this->bgFamilies()[$fam] ?? null;
		return $f ? rtrim($this->getSettings()['tftp_dir'], '/') . '/Desktops/' . $f['dir'] : null;
	}

	/** Archivo dentro del directorio de provisión; si no existe con esas mayúsculas, busca otra combinación */
	private function provFile($rel)
	{
		if ($rel === null) {
			return null;
		}
		$root = rtrim($this->getSettings()['tftp_dir'], '/');
		$f = $root . '/' . $rel;
		if (is_file($f)) {
			return $f;
		}
		foreach (glob(dirname($f) . '/*') ?: [] as $g) {
			if (strcasecmp(basename($g), basename($f)) === 0 && is_file($g)) {
				return $g;
			}
		}
		return null;
	}

	private function bgItems($fam)
	{
		$path = $this->bgPath($fam);
		return ($path && is_file($path . '/List.xml')) ? Remote::parseListItems(file_get_contents($path . '/List.xml')) : [];
	}

	public function bgList($fam)
	{
		$out = [];
		foreach ($this->bgItems($fam) as $i) {
			$tn = $this->provFile($i['image']);
			$data = $tn && filesize($tn) < 2 * 1024 * 1024 ? Remote::imageDataUri(file_get_contents($tn)) : null;
			$out[] = ['name' => $i['name'], 'thumb' => $data, 'exists' => $this->provFile($i['url']) !== null,
				'thumb_file' => $i['image'] ? basename($i['image']) : '', 'thumb_found' => $tn !== null];
		}
		return $out;
	}

	private function writeList($fam, array $items)
	{
		return file_put_contents($this->bgPath($fam) . '/List.xml', Remote::listXmlItems($items)) !== false;
	}

	public function bgUpload($fam, $tmp, $origName)
	{
		$f = $this->bgFamilies()[$fam] ?? null;
		if (!$f) {
			return _('Invalid screen family.');
		}
		$img = Remote::loadImage($tmp);
		if (!$img) {
			return _('Not a valid image (PNG, JPEG, GIF, BMP or WebP, up to 20 MB).');
		}
		$path = $this->bgPath($fam);
		if (!is_dir($path) && !@mkdir($path, 0755, true)) {
			return sprintf(_('Could not create %s'), $path);
		}
		list($w, $h) = Remote::dims($f['dir']);
		list($tw, $th) = Remote::dims($f['thumb']);
		$items = $this->bgItems($fam);
		$names = array_map('strtolower', array_column($items, 'name'));
		$base = Remote::slug($origName);
		$name = $base;
		for ($i = 2; in_array(strtolower($name), $names, true) || is_file($path . '/' . $name . '.png'); $i++) {
			$name = $base . '-' . $i;
		}
		$full = Remote::cover($img, $w, $h);
		$thumb = Remote::cover($img, $tw, $th);
		$ok = imagepng($full, $path . '/' . $name . '.png') && imagepng($thumb, $path . '/' . $name . '-tn.png');
		if (!$ok) {
			return sprintf(_('Could not write to %s'), $path);
		}
		@chmod($path . '/' . $name . '.png', 0644);
		@chmod($path . '/' . $name . '-tn.png', 0644);
		$rel = 'Desktops/' . $f['dir'] . '/';
		$items[] = ['name' => $name, 'image' => $rel . $name . '-tn.png', 'url' => $rel . $name . '.png'];
		return $this->writeList($fam, $items) ? null : _('Could not write List.xml');
	}

	public function bgDelete($fam, $name)
	{
		$items = $this->bgItems($fam);
		$keep = [];
		$del = null;
		foreach ($items as $i) {
			if ($del === null && $i['name'] === $name) {
				$del = $i;
			} else {
				$keep[] = $i;
			}
		}
		if (!$del) {
			return _('Image not found.');
		}
		// Solo se borran archivos que estén dentro de la carpeta de esta familia
		$dir = realpath($this->bgPath($fam));
		foreach ([$del['url'], $del['image']] as $rel) {
			$file = $this->provFile($rel);
			if ($file && $dir && strpos(realpath($file), $dir . '/') === 0) {
				@unlink($file);
			}
		}
		return $this->writeList($fam, $keep) ? null : _('Could not write List.xml');
	}

	private function ringPath()
	{
		return rtrim($this->getSettings()['tftp_dir'], '/');
	}

	private static function ringKind($kind)
	{
		return isset(Remote::RING_KINDS[$kind]) ? $kind : 'nb';
	}

	/**
	 * Archivo de cada lista: RingList.xml (banda angosta, .raw) y RingList-wb.xml (banda ancha, .rwb).
	 * En Linux importan las mayúsculas: si ya existe con otra combinación (RingList.xml, ringlist.xml) se usa ese.
	 */
	public function ringlistFile($kind = 'nb')
	{
		$kind = self::ringKind($kind);
		$name = $this->getSettings()[$kind === 'wb' ? 'ringlist_wb_file' : 'ringlist_file'];
		if (!preg_match('/^[A-Za-z0-9._-]{1,40}\.xml$/', (string)$name)) {
			$name = Remote::RING_KINDS[$kind]['default'];
		}
		$dir = $this->ringPath();
		if (!is_file($dir . '/' . $name)) {
			foreach (glob($dir . '/*.xml') ?: [] as $f) {
				if (strcasecmp(basename($f), $name) === 0) {
					return $f;
				}
			}
		}
		return $dir . '/' . $name;
	}

	public function ringList($kind = 'nb')
	{
		$f = $this->ringlistFile($kind);
		$rings = is_file($f) ? Remote::parseRinglist(file_get_contents($f)) : [];
		foreach ($rings as &$r) {
			$r['exists'] = Remote::validRingFile($r['file']) && is_file($this->ringPath() . '/' . $r['file']);
		}
		unset($r);
		return $rings;
	}

	private function writeRinglist($kind, array $rings)
	{
		$clean = array_map(function ($r) { return ['name' => $r['name'], 'file' => $r['file']]; }, $rings);
		return file_put_contents($this->ringlistFile($kind), Remote::ringlistXml($clean)) !== false;
	}

	/**
	 * Convierte audio al formato de cada lista (sox, o ffmpeg si no hay sox), máximo 2 s:
	 *  nb: µ-law 8 kHz mono crudo (.raw)      wb: PCM 16 bits con signo 16 kHz mono crudo (.rwb)
	 */
	public static function convertRing($in, $kind)
	{
		$wb = $kind === 'wb';
		$tmp = tempnam(sys_get_temp_dir(), 'csmring');
		$out = [];
		$rc = 1;
		$sox = trim((string)@shell_exec('command -v sox'));
		$ff = trim((string)@shell_exec('command -v ffmpeg'));
		if ($sox) {
			$fmt = $wb ? '-t raw -e signed-integer -b 16 -L -r 16000 -c 1' : '-t ul -r 8000 -c 1';
			exec(escapeshellarg($sox) . ' ' . escapeshellarg($in) . ' ' . $fmt . ' ' . escapeshellarg($tmp) . ' trim 0 2.01 2>&1', $out, $rc);
		} elseif ($ff) {
			$fmt = $wb ? '-ar 16000 -ac 1 -f s16le' : '-ar 8000 -ac 1 -f mulaw';
			exec(escapeshellarg($ff) . ' -y -v error -i ' . escapeshellarg($in) . ' -t 2.01 ' . $fmt . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
		} else {
			@unlink($tmp);
			return [null, _('Neither sox nor ffmpeg is available on the server to convert the audio.')];
		}
		$raw = $rc === 0 ? file_get_contents($tmp) : false;
		@unlink($tmp);
		if (!$raw) {
			return [null, sprintf(_('Could not convert the audio: %s'), implode(' ', $out))];
		}
		return [$wb ? Remote::fitRingWb($raw) : Remote::fitRing($raw), null];
	}

	/**
	 * Sube un tono a una o ambas listas. Un .raw ya convertido solo va a la de banda angosta
	 * y un .rwb solo a la de banda ancha; cualquier otro audio se convierte para cada una.
	 */
	public function ringUpload($tmp, $origName, $display, array $kinds = ['nb', 'wb'])
	{
		$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
		if ($ext === 'raw') {
			$kinds = ['nb'];
		} elseif ($ext === 'rwb') {
			$kinds = ['wb'];
		}
		$kinds = array_values(array_intersect(array_keys(Remote::RING_KINDS), $kinds));
		if (!$kinds) {
			return _('Choose at least one list.');
		}
		$display = trim($display) !== '' ? mb_substr(trim($display), 0, 25) : mb_substr(pathinfo($origName, PATHINFO_FILENAME), 0, 25);
		$base = substr(Remote::slug($origName), 0, 18); // FileName: máximo 25 caracteres con la extensión
		$errs = [];
		foreach ($kinds as $kind) {
			$rings = $this->ringList($kind);
			if (count($rings) >= 50) {
				$errs[] = sprintf(_('%s: it already has 50 ringtones, the maximum.'), basename($this->ringlistFile($kind)));
				continue;
			}
			if ($ext === 'raw') {
				$data = Remote::fitRing(file_get_contents($tmp));
			} elseif ($ext === 'rwb') {
				$data = Remote::fitRingWb(file_get_contents($tmp));
			} else {
				list($data, $err) = self::convertRing($tmp, $kind);
				if ($err) {
					$errs[] = $err;
					continue;
				}
			}
			$fext = Remote::RING_KINDS[$kind]['ext'];
			$file = $base . '.' . $fext;
			$files = array_column($rings, 'file');
			for ($i = 2; in_array($file, $files, true) || is_file($this->ringPath() . '/' . $file); $i++) {
				$file = $base . '-' . $i . '.' . $fext;
			}
			if (file_put_contents($this->ringPath() . '/' . $file, $data) === false) {
				$errs[] = sprintf(_('Could not write %s in the provisioning directory.'), $file);
				continue;
			}
			@chmod($this->ringPath() . '/' . $file, 0644);
			$rings[] = ['name' => $display, 'file' => $file];
			if (!$this->writeRinglist($kind, $rings)) {
				$errs[] = sprintf(_('Could not write %s'), basename($this->ringlistFile($kind)));
			}
		}
		return $errs ? implode("\n", $errs) : null;
	}

	public function ringDelete($kind, $file, $deleteFile)
	{
		$rings = $this->ringList($kind);
		$left = array_values(array_filter($rings, function ($r) use ($file) { return $r['file'] !== $file; }));
		if (count($left) === count($rings)) {
			return _('Ringtone not found.');
		}
		if ($deleteFile && Remote::validRingFile($file)) {
			@unlink($this->ringPath() . '/' . $file);
		}
		return $this->writeRinglist($kind, $left) ? null : sprintf(_('Could not write %s'), basename($this->ringlistFile($kind)));
	}

	public function ringRename($kind, $file, $name)
	{
		$rings = $this->ringList($kind);
		$found = false;
		foreach ($rings as &$r) {
			if ($r['file'] === $file) {
				$r['name'] = mb_substr(trim($name), 0, 25);
				$found = true;
			}
		}
		unset($r);
		return !$found ? _('Ringtone not found.') : ($this->writeRinglist($kind, $rings) ? null : sprintf(_('Could not write %s'), basename($this->ringlistFile($kind))));
	}

	/* ===================== FreePBX UI / AJAX ===================== */

	public function ajaxRequest($req, &$setting)
	{
		return in_array($req, ['status', 'action', 'messageall', 'preview', 'versions', 'version', 'diff', 'restoreversion', 'diffgen', 'screenshot', 'phoneform', 'savephone',
			'key', 'dial', 'invrefresh', 'vpn', 'vpndisconnect', 'bglist', 'bgdelete', 'ringlist', 'ringdelete', 'ringrename'], true);
	}

	public function ajaxHandler()
	{
		$cmd = $_REQUEST['command'] ?? '';
		switch ($cmd) {
			case 'status':
				return ['status' => true, 'phones' => $this->allStatus()];

			case 'versions':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				return $p ? ['status' => true] + $this->versionsFor($p) : ['status' => false, 'message' => _('Phone not found')];

			case 'version':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				$c = $p ? $this->history()->get($p['mac'], $_REQUEST['name'] ?? '') : false;
				return $c === false ? ['status' => false, 'message' => _('Version not found')] : ['status' => true, 'xml' => $c];

			case 'diff':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				if (!$p) {
					return ['status' => false, 'message' => _('Phone not found')];
				}
				$h = $this->history();
				$old = $h->get($p['mac'], $_REQUEST['name'] ?? '');
				$against = $_REQUEST['against'] ?? 'current';
				if ($against === 'current') {
					$path = $this->cnfPath($p['mac']);
					$new = is_file($path) ? file_get_contents($path) : '';
				} else {
					$new = $h->get($p['mac'], $against);
				}
				if ($old === false || $new === false) {
					return ['status' => false, 'message' => _('Version not found')];
				}
				return ['status' => true, 'diff' => History::diff($old, $new)];

			case 'restoreversion':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				$e = $p ? $this->restoreVersion($p, $_REQUEST['name'] ?? '') : _('Phone not found');
				return ['status' => $e === null, 'message' => $e ?: _('Version restored on the phone. Remember that regenerating builds it again from the current data.')];

			case 'key':
			case 'dial':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				if (!$p) {
					return ['status' => false, 'message' => _('Phone not found')];
				}
				$r = $cmd === 'key' ? $this->sendKey($p, $_REQUEST['key'] ?? '') : $this->dial($p, $_REQUEST['number'] ?? '');
				return ['status' => $r['ok'], 'message' => $r['msg']];

			case 'invrefresh':
				$res = [];
				$ids = isset($_REQUEST['id']) ? [(int)$_REQUEST['id']] : array_column($this->getPhones(), 'id');
				foreach ($ids as $id) {
					$p = $this->getPhone($id);
					if ($p) {
						$r = $this->refreshInventory($p);
						$res[] = ['ext' => $p['extension'], 'ok' => $r['ok'], 'msg' => $r['ok'] ? 'OK' : $r['msg']];
					}
				}
				return ['status' => true, 'results' => $res];

			case 'vpn':
				$r = $this->vpnSessions();
				$list = [];
				foreach ($r['sessions'] ?? [] as $u) {
					$u['phone'] = $u['phone'] ? ['id' => (int)$u['phone']['id'], 'extension' => $u['phone']['extension'], 'label' => $u['phone']['label']] : null;
					$list[] = $u;
				}
				return ['status' => $r['ok'], 'message' => $r['msg'] ?? '', 'sessions' => $list];

			case 'vpndisconnect':
				$r = $this->vpnDisconnect($_REQUEST['sid'] ?? '');
				return ['status' => $r['ok'], 'message' => $r['msg']];

			case 'bglist':
				$out = [];
				foreach ($this->bgFamilies() as $k => $f) {
					$out[] = ['key' => $k, 'label' => $f['label'], 'dir' => $f['dir'], 'thumb' => $f['thumb'], 'images' => $this->bgList($k)];
				}
				return ['status' => true, 'families' => $out];

			case 'bgdelete':
				$e = $this->bgDelete($_REQUEST['fam'] ?? '', $_REQUEST['name'] ?? '');
				return ['status' => $e === null, 'message' => $e ?: _('Image deleted')];

			case 'ringlist':
				$lists = [];
				foreach (Remote::RING_KINDS as $k => $info) {
					$lists[] = ['kind' => $k, 'label' => Remote::ringKindLabel($k), 'file' => basename($this->ringlistFile($k)),
						'exists' => is_file($this->ringlistFile($k)), 'rings' => $this->ringList($k)];
				}
				return ['status' => true, 'lists' => $lists];

			case 'ringdelete':
				$e = $this->ringDelete($_REQUEST['kind'] ?? 'nb', $_REQUEST['file'] ?? '', !empty($_REQUEST['delfile']));
				return ['status' => $e === null, 'message' => $e ?: _('Ringtone removed')];

			case 'ringrename':
				$e = $this->ringRename($_REQUEST['kind'] ?? 'nb', $_REQUEST['file'] ?? '', $_REQUEST['name'] ?? '');
				return ['status' => $e === null, 'message' => $e ?: _('Name updated')];

			case 'phoneform':
				$html = $this->phoneFormHtml((int)($_REQUEST['id'] ?? 0));
				return $html === null ? ['status' => false, 'message' => _('Phone not found')] : ['status' => true, 'html' => $html];

			case 'savephone':
				$err = $this->savePhone($_POST);
				return ['status' => $err === null, 'message' => $err ?: _('Phone saved and SEP generated.')];

			case 'screenshot':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				if (!$p) {
					return ['status' => false, 'message' => _('Phone not found')];
				}
				$r = $this->screenshotPhone($p);
				return ['status' => $r['ok'], 'message' => $r['msg'], 'image' => $r['data'] ?? null, 'ip' => $r['ip'] ?? null, 'time' => date('H:i:s')];

			case 'diffgen':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				return $p ? ['status' => true, 'diff' => $this->diffGenerated($p)] : ['status' => false, 'message' => _('Phone not found')];

			case 'preview':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				return $p ? ['status' => true, 'xml' => $this->buildCnf($p)] : ['status' => false];

			case 'action':
				$p = $this->getPhone($_REQUEST['id'] ?? 0);
				if (!$p) {
					return ['status' => false, 'message' => _('Phone not found')];
				}
				$act = $_REQUEST['act'] ?? '';
				if ($act === 'regen') {
					$e = $this->generateCnf($p, 'regen');
					return ['status' => $e === null, 'message' => $e ?: _('File regenerated')];
				}
				if (in_array($act, ['restart', 'reset', 'checkcfg'], true)) {
					$r = $this->sipNotify($p, $act);
					return ['status' => $r['ok'], 'message' => $r['msg']];
				}
				if ($act === 'message') {
					$st = $this->phoneStatus($p, $this->getSettings());
					$r = $this->pushMessage($p, $st['ip'], $_REQUEST['title'] ?? _('Notice'), $_REQUEST['text'] ?? '', !empty($_REQUEST['beep']));
					return ['status' => $r['ok'], 'message' => $r['msg']];
				}
				return ['status' => false, 'message' => _('Unknown action')];

			case 'messageall':
				$s = $this->getSettings();
				$res = [];
				foreach ($this->getPhones() as $p) {
					$st = $this->phoneStatus($p, $s);
					if (!$st['registered']) {
						$res[] = ['ext' => $p['extension'], 'ok' => false, 'msg' => _('not registered')];
						continue;
					}
					$r = $this->pushMessage($p, $st['ip'], $_REQUEST['title'] ?? _('Notice'), $_REQUEST['text'] ?? '', !empty($_REQUEST['beep']));
					$res[] = ['ext' => $p['extension'], 'ok' => $r['ok'], 'msg' => $r['msg']];
				}
				return ['status' => true, 'results' => $res];
		}
		return ['status' => false, 'message' => _('Unknown command')];
	}

	public function doConfigPageInit($page)
	{
		$this->flash = [];
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			return;
		}
		$action = $_POST['csm_action'] ?? '';
		$err = null;
		switch ($action) {
			case 'save_phone':
				$err = $this->savePhone($_POST);
				$this->flash[] = $err ? ['danger', $err] : ['success', _('Phone saved and SEP generated.')];
				break;
			case 'delete_phone':
				$this->deletePhone($_POST['id'] ?? 0);
				$this->flash[] = ['success', _('Phone deleted.')];
				break;
			case 'save_settings':
				$err = $this->saveSettings($_POST);
				$this->flash[] = $err ? ['warning', $err] : ['success', _('Settings saved. Regenerate the SEP files to apply them.')];
				break;
			case 'bg_upload':
				$f = $_FILES['image'] ?? null;
				$e = ($f && $f['error'] === UPLOAD_ERR_OK) ? $this->bgUpload($_POST['fam'] ?? '', $f['tmp_name'], $f['name']) : _('The image was not received.');
				$this->flash[] = $e ? ['danger', $e] : ['success', _('Background added. Phones see it under Settings → Preferences → Wallpaper.')];
				break;
			case 'ring_upload':
				$f = $_FILES['ring'] ?? null;
				$e = ($f && $f['error'] === UPLOAD_ERR_OK) ? $this->ringUpload($f['tmp_name'], $f['name'], $_POST['display'] ?? '', explode(',', $_POST['target'] ?? 'nb,wb')) : _('The file was not received.');
				$this->flash[] = $e ? ['danger', $e] : ['success', _('Ringtone added.')];
				break;
			case 'import_selected':
				list($ok, $errs) = $this->importSelected($_POST);
				if ($ok) {
					$this->flash[] = ['success', sprintf(_('%d phone(s) imported. The files were not modified: check "Compare with what would be generated" before regenerating.'), count($ok))];
				}
				if ($errs) {
					$this->flash[] = ['danger', implode("\n", $errs)];
				}
				if (!$ok && !$errs) {
					$this->flash[] = ['warning', _('You did not select any file.')];
				}
				break;
			case 'regen_all':
				$skipped = [];
				$errs = $this->regenAll('regen', $skipped);
				$this->flash[] = $errs ? ['danger', implode("\n", $errs)] : ['success', _('All SEP files regenerated.')];
				if ($skipped) {
					$this->flash[] = ['warning', sprintf(_('Imported phones pending review were not touched (ext. %s). Regenerate them one by one from Actions after comparing.'), implode(', ', $skipped))];
				}
				break;
		}
	}

	/** Teléfono para el formulario de edición, con el estado real del video en el SEP */
	private function editData($id)
	{
		$p = $this->getPhone($id);
		if ($p) {
			$x = @simplexml_load_string($this->buildCnf($p));
			$p['video_effective'] = $x && isset($x->vendorConfig->videoCapability) ? (int)$x->vendorConfig->videoCapability : 0;
		}
		return $p;
	}

	/** HTML del formulario de teléfono para el cuadro de edición */
	public function phoneFormHtml($id)
	{
		$edit = $id ? $this->editData($id) : null;
		if ($id && !$edit) {
			return null;
		}
		return load_view(__DIR__ . '/views/_phoneform.php', [
			'settings' => $this->getSettings(),
			'phones' => $this->getPhones(),
			'models' => CnfBuilder::MODELS,
			'devices' => $this->FreePBX->Core->getAllDevicesByType('sip'),
			'edit' => $edit,
			'embedded' => true,
		]);
	}

	public function showPage()
	{
		$view = $_GET['view'] ?? 'list';
		$vars = [
			'flash' => $this->flash ?? [],
			'settings' => $this->getSettings(),
			'phones' => $this->getPhones(),
			'models' => CnfBuilder::MODELS,
			'devices' => $this->FreePBX->Core->getAllDevicesByType('sip'),
			'authScript' => $this->authScript(),
			'edit' => null,
		];
		if ($view === 'form' && !empty($_GET['id'])) {
			$vars['edit'] = $this->editData($_GET['id']);
		}
		if ($view === 'import') {
			$vars['scan'] = $this->scanExisting();
		}
		if ($view === 'inventory') {
			$vars['inventory'] = $this->inventoryRows();
		}
		if ($view === 'media') {
			$vars['families'] = $this->bgFamilies();
		}
		$tpl = in_array($view, ['list', 'form', 'settings', 'import', 'inventory', 'vpn', 'media'], true) ? $view : 'list';
		return load_view(__DIR__ . '/views/' . $tpl . '.php', $vars);
	}

	public function getActionBar($request)
	{
		return [];
	}
}
