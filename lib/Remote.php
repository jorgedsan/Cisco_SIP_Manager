<?php
/**
 * Utilidades sin dependencias de FreePBX: URIs de /CGI/Execute, parseo de DeviceInformationX,
 * List.xml de fondos y RingList.xml de tonos.
 */
namespace FreePBX\modules\Ciscosipmanager;

class Remote
{
	/** Teclas permitidas para Key:<nombre> (las que no existen en un modelo, el teléfono las ignora) */
	public static function keys()
	{
		$k = ['NavUp', 'NavDwn', 'NavLeft', 'NavRight', 'NavSelect', 'NavBack',
			'KeyPadStar', 'KeyPadPound', 'Speaker', 'Headset', 'Mute', 'Hold', 'Transfer', 'Conference',
			'VolUp', 'VolDwn', 'Messages', 'Services', 'Directories', 'Settings', 'Applications', 'Info'];
		for ($i = 0; $i <= 9; $i++) {
			$k[] = 'KeyPad' . $i;
		}
		for ($i = 1; $i <= 5; $i++) {
			$k[] = 'Soft' . $i;
		}
		for ($i = 1; $i <= 10; $i++) {
			$k[] = 'Line' . $i;
		}
		return $k;
	}

	public static function validKey($key)
	{
		return in_array((string)$key, self::keys(), true);
	}

	/** Número a marcar: dígitos, *, #, + (máx. 32) */
	public static function validNumber($n)
	{
		return (bool)preg_match('/^[0-9*#+]{1,32}$/', (string)$n);
	}

	/** <CiscoIPPhoneExecute> con hasta 3 URIs (límite del teléfono) */
	public static function executeXml(array $uris)
	{
		$out = '<CiscoIPPhoneExecute>';
		foreach (array_slice(array_values($uris), 0, 3) as $u) {
			$out .= '<ExecuteItem Priority="0" URL="' . CnfBuilder::x($u) . '"/>';
		}
		return $out . '</CiscoIPPhoneExecute>';
	}

	/** DeviceInformationX → array plano (MACAddress, HostName, modelNumber, serialNumber, versionID, ...) */
	public static function parseDeviceInfo($xml)
	{
		$prev = libxml_use_internal_errors(true);
		$x = simplexml_load_string((string)$xml, 'SimpleXMLElement', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if ($x === false) {
			return null;
		}
		$out = [];
		foreach ($x->children() as $c) {
			if (count($c->children()) === 0) {
				$v = trim((string)$c);
				if ($v !== '') {
					$out[$c->getName()] = $v;
				}
			}
		}
		return $out ?: null;
	}

	/** "CP-8845" / "CP-7945G" / "CP-8851-K9" → "8845" / "7945" / "8851" (si es un modelo soportado) */
	public static function modelFromNumber($modelNumber)
	{
		if (preg_match('/(\d{4})/', (string)$modelNumber, $m) && isset(CnfBuilder::MODELS[$m[1]])) {
			return $m[1];
		}
		return '';
	}

	/* ---------------- Fondos de pantalla ---------------- */

	/** Familias de pantalla: carpeta Desktops/<WxHxD> y tamaño de miniatura (ajustables en Ajustes) */
	const BG_FAMILIES = [
		'88' => ['label' => '88xx (8841/8845/8851/8861/8865)', 'models' => ['8841', '8845', '8851', '8861', '8865'], 'dir' => '800x480x24', 'thumb' => '139x109'],
		'79' => ['label' => '7945 / 7965', 'models' => ['7945', '7965'], 'dir' => '320x212x16', 'thumb' => '80x53'],
		'7975' => ['label' => '7975', 'models' => ['7975'], 'dir' => '320x216x16', 'thumb' => '80x53'],
	];

	/** "800x480x24" → [800, 480] */
	public static function dims($spec)
	{
		if (preg_match('/^(\d{2,4})x(\d{2,4})(?:x\d+)?$/', (string)$spec, $m)) {
			return [(int)$m[1], (int)$m[2]];
		}
		return null;
	}

	public static function validDirSpec($spec)
	{
		return (bool)preg_match('/^\d{2,4}x\d{2,4}x\d{1,2}$/', (string)$spec);
	}

	/** Nombre de archivo seguro a partir del nombre original */
	public static function slug($name)
	{
		$s = strtolower(preg_replace('/\.[^.]*$/', '', (string)$name));
		$s = preg_replace('/[^a-z0-9]+/', '-', $s);
		$s = trim($s, '-');
		return $s !== '' ? substr($s, 0, 40) : 'img';
	}

	/** Escala y recorta (cover) una imagen GD a $w x $h */
	public static function cover($src, $w, $h)
	{
		$sw = imagesx($src);
		$sh = imagesy($src);
		$scale = max($w / $sw, $h / $sh);
		$cw = (int)round($w / $scale);
		$ch = (int)round($h / $scale);
		$dst = imagecreatetruecolor($w, $h);
		imagecopyresampled($dst, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
		return $dst;
	}

	/** Lee una imagen subida (PNG, JPEG, GIF, BMP, WebP) con GD */
	public static function loadImage($path)
	{
		$data = @file_get_contents($path);
		if ($data === false || strlen($data) > 20 * 1024 * 1024) {
			return null;
		}
		$img = @imagecreatefromstring($data);
		return $img ?: null;
	}

	/** Ruta relativa al directorio de provisión a partir de "TFTP:Desktops/..." (null si no es segura) */
	public static function listPath($attr)
	{
		$p = ltrim(preg_replace('/^TFTP:/i', '', trim((string)$attr)), '/');
		if ($p === '' || strpos($p, '..') !== false || strpos($p, "\0") !== false || !preg_match('#^[A-Za-z0-9._/ -]+$#', $p)) {
			return null;
		}
		return $p;
	}

	/**
	 * Entradas de un List.xml tal como están: [['name', 'image' => ruta miniatura, 'url' => ruta fondo], ...]
	 * (los fondos de Cisco usan otros nombres de miniatura, por eso se respetan las rutas del archivo)
	 */
	public static function parseListItems($xml)
	{
		$prev = libxml_use_internal_errors(true);
		$x = simplexml_load_string((string)$xml, 'SimpleXMLElement', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		$out = [];
		if ($x === false) {
			return $out;
		}
		foreach ($x->ImageItem as $it) {
			$url = self::listPath($it['URL'] ?? '');
			if ($url === null) {
				continue;
			}
			$out[] = ['name' => pathinfo($url, PATHINFO_FILENAME), 'image' => self::listPath($it['Image'] ?? ''), 'url' => $url];
		}
		return $out;
	}

	public static function listXmlItems(array $items)
	{
		$out = "<CiscoIPPhoneImageList>\n";
		foreach ($items as $i) {
			$out .= '  <ImageItem Image="TFTP:' . CnfBuilder::x($i['image'] ?? '') . '" URL="TFTP:' . CnfBuilder::x($i['url']) . '"/>' . "\n";
		}
		return $out . "</CiscoIPPhoneImageList>\n";
	}

	/** data: URI de una imagen según su firma (PNG, JPEG, BMP, GIF) */
	public static function imageDataUri($bytes)
	{
		$mime = strncmp($bytes, "\x89PNG", 4) === 0 ? 'image/png'
			: (strncmp($bytes, "\xFF\xD8", 2) === 0 ? 'image/jpeg'
			: (strncmp($bytes, 'BM', 2) === 0 ? 'image/bmp'
			: (strncmp($bytes, 'GIF8', 4) === 0 ? 'image/gif' : null)));
		return $mime ? 'data:' . $mime . ';base64,' . base64_encode($bytes) : null;
	}

	/* ---------------- Tonos ---------------- */

	/** RingList.xml → [['name' => 'Clásico', 'file' => 'Ring1.raw'], ...] */
	public static function parseRinglist($xml)
	{
		$out = [];
		$prev = libxml_use_internal_errors(true);
		$x = simplexml_load_string((string)$xml, 'SimpleXMLElement', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if ($x === false) {
			return $out;
		}
		foreach ($x->Ring as $r) {
			$out[] = ['name' => trim((string)$r->DisplayName), 'file' => trim((string)$r->FileName)];
		}
		return $out;
	}

	public static function ringlistXml(array $rings)
	{
		$out = "<CiscoIPPhoneRingList>\n";
		foreach ($rings as $r) {
			$out .= '  <Ring><DisplayName>' . CnfBuilder::x($r['name']) . '</DisplayName><FileName>' . CnfBuilder::x($r['file']) . "</FileName></Ring>\n";
		}
		return $out . "</CiscoIPPhoneRingList>\n";
	}

	/** Listas de tonos: banda angosta (79xx, µ-law 8 kHz) y banda ancha (88xx, PCM 16 bits 16 kHz) */
	const RING_KINDS = [
		'nb' => ['label' => 'Banda angosta (79xx)', 'ext' => 'raw', 'default' => 'RingList.xml'],
		'wb' => ['label' => 'Banda ancha (88xx)', 'ext' => 'rwb', 'default' => 'RingList-wb.xml'],
	];

	public static function validRingFile($f)
	{
		return (bool)preg_match('/^[A-Za-z0-9._-]{1,21}\.(raw|rwb)$/', (string)$f) && strpos($f, '..') === false;
	}

	/**
	 * Tono de banda ancha: PCM 16 bits 16 kHz crudo, máximo ~2 s (31920 muestras),
	 * con cantidad de muestras múltiplo de 240 (rellena con silencio).
	 */
	public static function fitRingWb($pcm)
	{
		$pcm = substr($pcm, 0, 63840); // 31920 muestras: múltiplo de 240, justo debajo de 2 s
		$pcm = substr($pcm, 0, strlen($pcm) - strlen($pcm) % 2);
		$pad = (480 - strlen($pcm) % 480) % 480;
		if (strlen($pcm) === 0) {
			$pad = 480;
		}
		return $pcm . str_repeat("\0", $pad);
	}

	/**
	 * Ajusta un tono µ-law 8 kHz crudo al formato de los Cisco: entre 240 y 16080 muestras (~2 s)
	 * y cantidad de muestras múltiplo de 240.
	 */
	public static function fitRing($raw)
	{
		$raw = substr($raw, 0, 16080);
		$pad = (240 - strlen($raw) % 240) % 240;
		if (strlen($raw) === 0) {
			$pad = 240;
		}
		return $raw . str_repeat("\xFF", $pad); // 0xFF = silencio en µ-law
	}
}
