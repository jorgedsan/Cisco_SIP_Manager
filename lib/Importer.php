<?php
/**
 * Lee SEP<MAC>.cnf.xml existentes (hechos a mano o por otra herramienta) y arma los datos
 * para darlos de alta en el módulo sin perder lo que el módulo no genera.
 */
namespace FreePBX\modules\Ciscosipmanager;

class Importer
{
	/** Modelo a partir de loadInformation. Devuelve '' si es ambiguo (ej. sip88xx). */
	public static function modelFromLoad($load)
	{
		$load = (string)$load;
		if (preg_match('/^SIP(45|65|75)\./i', $load, $m)) {
			return '79' . $m[1];
		}
		// En 88xx el firmware es compartido (sip8845_65 = 8845/8865, sip88xx = 8811/8841/8851/8861): no alcanza
		return '';
	}

	/** Modelos posibles según loadInformation, para orientar la elección */
	public static function modelHint($load)
	{
		$load = (string)$load;
		if (preg_match('/^sip8845_65/i', $load)) {
			return _('8845 or 8865');
		}
		if (preg_match('/^sip88xx/i', $load)) {
			return _('8811, 8841, 8851 or 8861');
		}
		return '';
	}

	/** Modelo a partir del User-Agent SIP: "Cisco-CP8851/14.2.1" o "Cisco-CP7945G/9.4.2" */
	public static function modelFromUserAgent($ua)
	{
		if (preg_match('/Cisco-CP(\d{4})/i', (string)$ua, $m) && isset(CnfBuilder::MODELS[$m[1]])) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @param string $content    contenido del archivo
	 * @param string $filename   SEPxxxxxxxxxxxx.cnf.xml
	 */
	public static function parse($content, $filename)
	{
		$r = [
			'file' => $filename, 'mac' => '', 'model' => '', 'extension' => '', 'label' => '',
			'password' => '', 'vpn' => false, 'extra_xml' => '', 'extra_lines_xml' => '',
			'model_hint' => '', 'extra_names' => [], 'extra_lines' => 0, 'errors' => [], 'warnings' => [],
		];
		if (!preg_match('/^SEP([0-9A-Fa-f]{12})\.cnf\.xml$/', $filename, $m)) {
			$r['errors'][] = _('The name is not SEP<MAC>.cnf.xml');
			return $r;
		}
		$r['mac'] = strtoupper($m[1]);

		$prev = libxml_use_internal_errors(true);
		$xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if ($xml === false || $xml->getName() !== 'device') {
			$r['errors'][] = _('Not a valid <device> XML');
			return $r;
		}

		$r['model'] = self::modelFromLoad($xml->loadInformation ?? '');
		$r['model_hint'] = self::modelHint($xml->loadInformation ?? '');
		$r['vpn'] = isset($xml->vpnGroup);

		// Línea 1 => extensión; el resto se conserva como líneas extra
		$extraLines = [];
		if (isset($xml->sipProfile->sipLines->line)) {
			foreach ($xml->sipProfile->sipLines->line as $line) {
				$btn = (string)$line['button'];
				if ($btn === '1' && $r['extension'] === '') {
					$r['extension'] = trim((string)$line->name);
					$r['label'] = trim((string)$line->featureLabel);
					$r['password'] = (string)$line->authPassword;
				} else {
					$extraLines[] = '      ' . trim($line->asXML());
				}
			}
		}
		if ($r['extension'] === '') {
			$r['errors'][] = _('It has no <line button="1"> with <name>');
		}
		$r['extra_lines'] = count($extraLines);
		$r['extra_lines_xml'] = implode("\n", $extraLines);

		// Servidores de callManagerGroup y autoNetDetect, para deducir la ubicación
		$r['members'] = [];
		if (isset($xml->devicePool->callManagerGroup->members->member)) {
			foreach ($xml->devicePool->callManagerGroup->members->member as $mb) {
				$r['members'][] = trim((string)$mb->callManager->processNodeName);
			}
		}
		$r['auto_net_detect'] = isset($xml->vpnGroup->autoNetDetect) ? (int)$xml->vpnGroup->autoNetDetect : null;
		return $r;
	}

	/**
	 * Arma el XML extra comparando el archivo con lo que generaría el módulo:
	 *  - elementos que el módulo no genera: se conservan
	 *  - elementos que genera pero con otro contenido (vendorConfig, phoneServices, ...): se conservan y lo reemplazan
	 *  - elementos propios del módulo (CnfBuilder::OWNED): salen siempre de Ajustes
	 * @return array [extra_xml, nombres conservados]
	 */
	public static function extras($content, $generated)
	{
		$their = new \DOMDocument();
		$their->preserveWhiteSpace = false;
		$prev = libxml_use_internal_errors(true);
		$ok = $their->loadXML($content, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		if (!$ok) {
			return ['', []];
		}
		$gen = new \DOMDocument();
		$gen->preserveWhiteSpace = false;
		$gen->loadXML($generated);
		$genByName = [];
		foreach ($gen->documentElement->childNodes as $c) {
			if ($c->nodeType === XML_ELEMENT_NODE && !isset($genByName[$c->nodeName])) {
				$genByName[$c->nodeName] = CnfBuilder::canonical($c);
			}
		}
		$keep = [];
		$names = [];
		foreach ($their->documentElement->childNodes as $c) {
			if ($c->nodeType !== XML_ELEMENT_NODE || in_array($c->nodeName, CnfBuilder::OWNED, true)) {
				continue;
			}
			if (isset($genByName[$c->nodeName]) && $genByName[$c->nodeName] === CnfBuilder::canonical($c)) {
				continue;
			}
			$their->formatOutput = true;
			$keep[] = $their->saveXML($c, LIBXML_NOEMPTYTAG);
			$names[] = $c->nodeName;
		}
		return [implode("\n", $keep), array_values(array_unique($names))];
	}
}
