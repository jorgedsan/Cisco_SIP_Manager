<?php
namespace FreePBX\modules\Ciscosipmanager;

use FreePBX\modules\Backup as Base;

class Restore extends Base\RestoreBase
{
	public function runRestore()
	{
		$configs = $this->getConfigs();
		if (!empty($configs['tables'])) {
			$this->importTables($configs['tables']);
		}
		if (!empty($configs['kvstore'])) {
			$this->importKVStore($configs['kvstore']);
		}

		$bmo = \FreePBX::Ciscosipmanager();

		// Historial: los nombres se validan contra las MAC restauradas
		if (!empty($configs['history']) && is_array($configs['history'])) {
			$dir = $bmo->history()->dir();
			if (!is_dir($dir)) {
				@mkdir($dir, 0750, true);
			}
			$macs = array_column($bmo->getPhones(), 'mac');
			foreach ($configs['history'] as $name => $b64) {
				foreach ($macs as $mac) {
					if (History::validName($name, $mac)) {
						$c = base64_decode($b64, true);
						if ($c !== false) {
							@file_put_contents($dir . '/' . $name, $c);
							@chmod($dir . '/' . $name, 0640);
						}
						break;
					}
				}
			}
		}

		// SEP, sip_notify y authenticate.php se vuelven a generar con los datos restaurados
		$errs = $bmo->postRestore();
		foreach ($errs as $e) {
			if (method_exists($this, 'log')) {
				$this->log('Cisco SIP Manager: ' . $e, 'WARNING');
			} else {
				error_log('Cisco SIP Manager restore: ' . $e);
			}
		}
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables)
	{
		// No hay backups legacy (anteriores a FreePBX 15) para este módulo
	}
}
