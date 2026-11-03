<?php
namespace FreePBX\modules\Ciscosipmanager;

use FreePBX\modules\Backup as Base;

/**
 * Integración con Backup & Restore de FreePBX 17.
 * Guarda: tabla de teléfonos, ajustes (kvstore) e historial de versiones de SEP.
 * Los SEP actuales no se copian: se regeneran al restaurar (dependen de las claves de Core).
 */
class Backup extends Base\BackupBase
{
	public function runBackup($id, $transaction)
	{
		$this->addDependency('core');

		$history = [];
		$bmo = \FreePBX::Ciscosipmanager();
		$h = $bmo->history();
		foreach ($bmo->getPhones() as $p) {
			foreach ($h->listVersions($p['mac']) as $v) {
				$c = $h->get($p['mac'], $v['name']);
				if ($c !== false) {
					$history[$v['name']] = base64_encode($c);
				}
			}
		}

		$this->addConfigs([
			'tables' => $this->dumpTables(),
			'kvstore' => $this->dumpKVStore(),
			'history' => $history,
		]);
	}
}
