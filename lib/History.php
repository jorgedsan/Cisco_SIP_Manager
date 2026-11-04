<?php
/**
 * Historial de versiones de los SEP<MAC>.cnf.xml.
 * Se guarda FUERA del directorio que sirve :6970, porque las versiones viejas contienen claves SIP.
 */
namespace FreePBX\modules\Ciscosipmanager;

class History
{
	const REASONS = ['edit', 'regen', 'delete', 'restore', 'manual', 'import'];

	private $dir;
	private $keep;

	public function __construct($dir, $keep = 20)
	{
		$this->dir = rtrim($dir, '/');
		$this->keep = max(1, (int)$keep);
	}

	public function dir()
	{
		return $this->dir;
	}

	/** Contenido sin la línea de comentario con fecha de generación, para comparar versiones */
	public static function normalize($xml)
	{
		return preg_replace('/^<!-- Generado por FreePBX Cisco SIP Manager [^\n]*-->\n/m', '', (string)$xml);
	}

	public static function same($a, $b)
	{
		return self::normalize($a) === self::normalize($b);
	}

	private function ensureDir()
	{
		if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true)) {
			return false;
		}
		return is_writable($this->dir);
	}

	/** Guarda una versión. @return string|false nombre del archivo guardado */
	public function save($mac, $content, $reason)
	{
		if (!CnfBuilder::normalizeMac($mac) || !in_array($reason, self::REASONS, true) || !$this->ensureDir()) {
			return false;
		}
		$latest = $this->listVersions($mac);
		if ($latest && self::same($this->get($mac, $latest[0]['name']), $content)) {
			return $latest[0]['name'];
		}
		$base = 'SEP' . $mac . '-' . date('Ymd-His');
		// Secuencia por segundo (sin importar el origen) para que el orden sea estable
		$name = $base . '-' . $reason . '.cnf.xml';
		for ($i = 2; glob($this->dir . '/' . ($i == 2 ? $base : $base . '_' . ($i - 1)) . '-*.cnf.xml'); $i++) {
			$name = $base . '_' . $i . '-' . $reason . '.cnf.xml';
		}
		if (file_put_contents($this->dir . '/' . $name, $content) === false) {
			return false;
		}
		@chmod($this->dir . '/' . $name, 0640);
		$this->prune($mac);
		return $name;
	}

	/** @return array [['name','time','reason','size'], ...] más nuevo primero */
	public function listVersions($mac)
	{
		$mac = CnfBuilder::normalizeMac($mac);
		if (!$mac || !is_dir($this->dir)) {
			return [];
		}
		$out = [];
		foreach (glob($this->dir . '/SEP' . $mac . '-*.cnf.xml') ?: [] as $f) {
			$name = basename($f);
			if (!self::validName($name, $mac, $m)) {
				continue;
			}
			$t = \DateTime::createFromFormat('Ymd-His', $m[1] . '-' . $m[2]);
			$out[] = [
				'name' => $name,
				'time' => $t ? $t->getTimestamp() : filemtime($f),
				'seq' => isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 1,
				'reason' => $m[4],
				'size' => filesize($f),
			];
		}
		usort($out, function ($a, $b) {
			return [$b['time'], $b['seq']] <=> [$a['time'], $a['seq']];
		});
		return $out;
	}

	public static function validName($name, $mac, &$m = null)
	{
		return (bool)preg_match('/^SEP' . preg_quote($mac, '/') . '-(\d{8})-(\d{6})(?:_(\d+))?-(' . implode('|', self::REASONS) . ')\.cnf\.xml$/', $name, $m);
	}

	public function get($mac, $name)
	{
		$mac = CnfBuilder::normalizeMac($mac);
		if (!$mac || !self::validName($name, $mac)) {
			return false;
		}
		$f = $this->dir . '/' . $name;
		return is_file($f) ? file_get_contents($f) : false;
	}

	private function prune($mac)
	{
		foreach (array_slice($this->listVersions($mac), $this->keep) as $v) {
			@unlink($this->dir . '/' . $v['name']);
		}
	}

	/**
	 * Diff unificado por líneas (LCS), con $ctx líneas de contexto.
	 * Ignora la línea de fecha de generación.
	 */
	public static function diff($old, $new, $ctx = 3)
	{
		$a = explode("\n", rtrim(self::normalize($old), "\n"));
		$b = explode("\n", rtrim(self::normalize($new), "\n"));
		$n = count($a);
		$m = count($b);
		// Tabla LCS (los SEP tienen ~200 líneas, alcanza de sobra)
		$L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
		for ($i = $n - 1; $i >= 0; $i--) {
			for ($j = $m - 1; $j >= 0; $j--) {
				$L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
			}
		}
		$ops = [];
		$i = $j = 0;
		while ($i < $n || $j < $m) {
			if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
				$ops[] = [' ', $a[$i], $i + 1, $j + 1];
				$i++;
				$j++;
			} elseif ($i < $n && ($j >= $m || $L[$i + 1][$j] >= $L[$i][$j + 1])) {
				$ops[] = ['-', $a[$i], $i + 1, $j + 1];
				$i++;
			} else {
				$ops[] = ['+', $b[$j], $i + 1, $j + 1];
				$j++;
			}
		}
		// Armar hunks
		$changed = [];
		foreach ($ops as $k => $op) {
			if ($op[0] !== ' ') {
				$changed[] = $k;
			}
		}
		if (!$changed) {
			return '';
		}
		$out = [];
		$k = 0;
		while ($k < count($changed)) {
			$start = max(0, $changed[$k] - $ctx);
			$end = $changed[$k];
			while ($k + 1 < count($changed) && $changed[$k + 1] - $end <= 2 * $ctx) {
				$end = $changed[++$k];
			}
			$end = min(count($ops) - 1, $end + $ctx);
			$oa = $ob = 0;
			foreach (array_slice($ops, $start, $end - $start + 1) as $op) {
				if ($op[0] !== '+') { $oa++; }
				if ($op[0] !== '-') { $ob++; }
			}
			$out[] = sprintf('@@ -%d,%d +%d,%d @@', $ops[$start][2], $oa, $ops[$start][3], $ob);
			for ($x = $start; $x <= $end; $x++) {
				$out[] = $ops[$x][0] . $ops[$x][1];
			}
			$k++;
		}
		return implode("\n", $out) . "\n";
	}
}
