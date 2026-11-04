<?php
/**
 * Generador de SEP<MAC>.cnf.xml para teléfonos Cisco con firmware SIP
 * (79x5 SIP 9.x y 88xx sip88xx) contra Asterisk chan_sip.
 *
 * Clase pura, sin dependencias de FreePBX, para poder probarla aislada.
 */
namespace FreePBX\modules\Ciscosipmanager;

class CnfBuilder
{
	/** Modelos soportados => [descripción, cantidad de teclas de línea, soporta VPN] */
	const MODELS = [
		'7945' => ['Cisco 7945G', 2, false],
		'7965' => ['Cisco 7965G', 6, false],
		'7975' => ['Cisco 7975G', 8, false],
		'8811' => ['Cisco 8811', 10, true],
		'8841' => ['Cisco 8841', 10, true],
		'8845' => ['Cisco 8845', 10, true],
		'8851' => ['Cisco 8851', 10, true],
		'8861' => ['Cisco 8861', 10, true],
		'8865' => ['Cisco 8865', 10, true],
	];

	public static function x($v)
	{
		return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	public static function normalizeMac($mac)
	{
		$m = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$mac));
		return preg_match('/^[0-9A-F]{12}$/', $m) ? $m : false;
	}

	public static function supportsVpn($model)
	{
		return !empty(self::MODELS[$model][2]);
	}

	/**
	 * callLogBlfEnabled (BLF en el historial de llamadas) según la familia, como lo documenta usecallmanager:
	 *  79xx: 3 = habilitado, el usuario no lo puede cambiar   ·   78xx/88xx: 1 = habilitado (solo admiten 0/1)
	 */
	public static function callLogBlf($model)
	{
		return strpos((string)$model, '79') === 0 ? 3 : 1;
	}

	/** Video: 8845/8865 tienen cámara integrada; 8851/8861 aceptan cámara USB; 8811/8841 no tienen video */
	const VIDEO_MODELS = ['8845' => 'builtin', '8865' => 'builtin', '8851' => 'usb', '8861' => 'usb'];

	/** VIDEO_MODELS con la descripción traducida: ['8845' => 'built-in camera', ...] */
	public static function videoModelLabels()
	{
		$t = ['builtin' => _('built-in camera'), 'usb' => _('USB camera')];
		return array_map(function ($k) use ($t) { return $t[$k]; }, self::VIDEO_MODELS);
	}

	public static function supportsVideo($model)
	{
		return isset(self::VIDEO_MODELS[(string)$model]);
	}

	public static function is88xx($model)
	{
		return strpos((string)$model, '88') === 0;
	}

	/**
	 * Calcula el certHash que esperan los Cisco: base64(SHA1(certificado DER)).
	 * Acepta PEM (texto) y devuelve false si no es un certificado válido.
	 */
	public static function certHash($pem)
	{
		if (!preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', (string)$pem, $m)) {
			return false;
		}
		$der = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
		if ($der === false || $der === '') {
			return false;
		}
		return base64_encode(sha1($der, true));
	}

	/** Elementos que siempre genera el módulo: el importador nunca los pasa al XML extra */
	const OWNED = [
		'devicePool', 'sipProfile', 'vpnGroup', 'loadInformation', 'versionStamp',
		'capfAuthMode', 'capfList', 'transportLayerProtocol', 'deviceSecurityMode',
		'sshUserId', 'sshPassword', 'userLocale', 'networkLocale', 'networkLocaleInfo',
		'authenticationURL', 'servicesURL', 'directoryURL', 'idleURL', 'informationURL',
		'messagesURL', 'proxyServerURL',
	];

	/**
	 * @param array  $phone    mac, model, extension, label, network, vpn_enabled, extra_xml, extra_lines_xml
	 * @param array  $device   secret, description (de Core)
	 * @param array  $s        ajustes globales
	 * @param string $server   host para URLs de servicios, NTP y CAPF (normalmente la IP LAN del PBX)
	 * @param array  $members  processNodeName de callManagerGroup, en orden de prioridad (vacío = [$server])
	 */
	public static function build(array $phone, array $device, array $s, $server, array $members = [])
	{
		$x = [__CLASS__, 'x'];
		$ext = $phone['extension'];
		$name = !empty($device['description']) ? $device['description'] : $ext;
		$label = !empty($phone['label']) ? $phone['label'] : $name;
		$sipPort = !empty($s['sip_port']) ? (int)$s['sip_port'] : 5060;
		$is88 = self::is88xx($phone['model']);
		$members = $members ?: [$server];
		$vpn = !empty($phone['vpn_enabled']) && !empty($s['vpn_url']) && self::supportsVpn($phone['model']);

		$o = [];
		$o[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$o[] = '<!-- Generado por FreePBX Cisco SIP Manager ' . date('Y-m-d H:i:s') . ' -->';
		$o[] = '<device>';
		$o[] = '<fullConfig>true</fullConfig>';
		$o[] = '<deviceProtocol>SIP</deviceProtocol>';
		$o[] = '<ipAddressMode>0</ipAddressMode>';
		$o[] = '<allowAutoConfig>true</allowAutoConfig>';
		if ($is88) {
			$o[] = '<dadEnable>true</dadEnable>';
			$o[] = '<redirectEnable>false</redirectEnable>';
			$o[] = '<echoMultiEnable>false</echoMultiEnable>';
			$o[] = '<ipPreferenceModeControl>0</ipPreferenceModeControl>';
			$o[] = '<ipMediaAddressFamilyPreference>0</ipMediaAddressFamilyPreference>';
		}

		// --- devicePool: uno o dos servidores (Auto = VPN + LAN) ---
		$o[] = '<devicePool>';
		$o[] = '<dateTimeSetting>';
		$o[] = '<dateTemplate>' . $x($s['date_template'] ?? 'D/M/Y') . '</dateTemplate>';
		$o[] = '<timeZone>' . $x($s['timezone'] ?? 'SA Eastern Standard Time') . '</timeZone>';
		$o[] = '<ntps><ntp><name>' . $x(!empty($s['ntp']) ? $s['ntp'] : $server) . '</name><ntpMode>unicast</ntpMode></ntp></ntps>';
		$o[] = '</dateTimeSetting>';
		$o[] = '<callManagerGroup><members>';
		foreach (array_values($members) as $i => $m) {
			$o[] = '<member priority="' . $i . '"><callManager>';
			$o[] = '<ports><sipPort>' . $sipPort . '</sipPort><securedSipPort>' . ($sipPort + 1) . '</securedSipPort></ports>';
			$o[] = '<processNodeName>' . $x($m) . '</processNodeName>';
			$o[] = '</callManager></member>';
		}
		$o[] = '</members></callManagerGroup>';
		$o[] = '</devicePool>';

		// --- VPN AnyConnect (ocserv). autoNetDetect: 1 en Auto (solo levanta el túnel fuera de casa), 0 en "Siempre VPN" ---
		if ($vpn) {
			$o[] = '<vpnGroup>';
			$o[] = '<mtu>' . (int)($s['vpn_mtu'] ?? 1290) . '</mtu>';
			$o[] = '<failConnectTime>30</failConnectTime>';
			$o[] = '<authMethod>' . (int)($s['vpn_auth_method'] ?? 2) . '</authMethod>';
			$o[] = '<pswdPersistent>' . (!empty($s['vpn_pswd_persistent']) ? 1 : 0) . '</pswdPersistent>';
			$o[] = '<autoNetDetect>' . (($phone['network'] ?? 'auto') === 'vpn' ? 0 : 1) . '</autoNetDetect>';
			$o[] = '<enableHostIDCheck>0</enableHostIDCheck>';
			$o[] = '<addresses>';
			$o[] = '<url1>' . $x($s['vpn_url']) . '</url1>';
			if (!empty($s['vpn_url2'])) {
				$o[] = '<url2>' . $x($s['vpn_url2']) . '</url2>';
			}
			$o[] = '</addresses>';
			$o[] = '<credentials>';
			$o[] = '<hashAlg>0</hashAlg>';
			if (!empty($s['vpn_cert_hash'])) {
				$o[] = '<certHash1>' . $x($s['vpn_cert_hash']) . '</certHash1>';
			}
			$o[] = '</credentials>';
			$o[] = '</vpnGroup>';
		}

		// --- SIP ---
		$o[] = '<sipProfile>';
		$o[] = '<sipProxies><registerWithProxy>true</registerWithProxy></sipProxies>';
		$o[] = '<sipCallFeatures>';
		$o[] = '<cnfJoinEnabled>true</cnfJoinEnabled>';
		$o[] = '<callForwardURI>x-cisco-serviceuri-cfwdall</callForwardURI>';
		$o[] = '<callPickupURI>x-cisco-serviceuri-pickup</callPickupURI>';
		$o[] = '<callPickupListURI>x-cisco-serviceuri-opickup</callPickupListURI>';
		$o[] = '<callPickupGroupURI>x-cisco-serviceuri-gpickup</callPickupGroupURI>';
		$o[] = '<meetMeServiceURI>x-cisco-serviceuri-meetme</meetMeServiceURI>';
		$o[] = '<abbreviatedDialURI>x-cisco-serviceuri-abbrdial</abbreviatedDialURI>';
		$o[] = '<rfc2543Hold>false</rfc2543Hold>';
		$o[] = '<callHoldRingback>1</callHoldRingback>';
		$o[] = '<localCfwdEnable>true</localCfwdEnable>';
		$o[] = '<semiAttendedTransfer>true</semiAttendedTransfer>';
		$o[] = '<anonymousCallBlock>0</anonymousCallBlock>';
		$o[] = '<callerIdBlocking>0</callerIdBlocking>';
		$o[] = '<dndControl>0</dndControl>';
		$o[] = '<remoteCcEnable>true</remoteCcEnable>';
		$o[] = '<retainForwardInformation>false</retainForwardInformation>';
		$o[] = '<uriDialingDisplayPreference>1</uriDialingDisplayPreference>';
		$o[] = '</sipCallFeatures>';
		$o[] = '<sipStack>';
		$o[] = '<sipInviteRetx>6</sipInviteRetx>';
		$o[] = '<sipRetx>10</sipRetx>';
		$o[] = '<timerInviteExpires>180</timerInviteExpires>';
		$o[] = '<timerRegisterExpires>' . (int)($s['register_expires'] ?? 3600) . '</timerRegisterExpires>';
		$o[] = '<timerRegisterDelta>5</timerRegisterDelta>';
		$o[] = '<timerKeepAliveExpires>120</timerKeepAliveExpires>';
		$o[] = '<timerSubscribeExpires>120</timerSubscribeExpires>';
		$o[] = '<timerSubscribeDelta>5</timerSubscribeDelta>';
		$o[] = '<timerT1>500</timerT1>';
		$o[] = '<timerT2>4000</timerT2>';
		$o[] = '<maxRedirects>70</maxRedirects>';
		$o[] = '<remotePartyID>true</remotePartyID>';
		$o[] = '<userInfo>Phone</userInfo>';
		$o[] = '</sipStack>';
		$o[] = '<autoAnswerTimer>1</autoAnswerTimer>';
		$o[] = '<autoAnswerAltBehavior>false</autoAnswerAltBehavior>';
		$o[] = '<autoAnswerOverride>true</autoAnswerOverride>';
		$o[] = '<transferOnhookEnabled>true</transferOnhookEnabled>';
		$o[] = '<enableVad>false</enableVad>';
		$o[] = '<preferredCodec>none</preferredCodec>';
		$o[] = '<dtmfAvtPayload>101</dtmfAvtPayload>';
		$o[] = '<dtmfDbLevel>3</dtmfDbLevel>';
		$o[] = '<dtmfOutofBand>avt</dtmfOutofBand>';
		$o[] = '<alwaysUsePrimeLine>false</alwaysUsePrimeLine>';
		$o[] = '<alwaysUsePrimeLineVoiceMail>false</alwaysUsePrimeLineVoiceMail>';
		$o[] = '<kpml>0</kpml>';
		$o[] = '<phoneLabel></phoneLabel>';
		$o[] = '<stutterMsgWaiting>0</stutterMsgWaiting>';
		$o[] = '<callStats>true</callStats>';
		$o[] = '<offhookToFirstDigitTimer>15000</offhookToFirstDigitTimer>';
		$o[] = '<silentPeriodBetweenCallWaitingBursts>10</silentPeriodBetweenCallWaitingBursts>';
		$o[] = '<disableLocalSpeedDialConfig>false</disableLocalSpeedDialConfig>';
		$o[] = '<startMediaPort>' . (int)($s['rtp_start'] ?? 16384) . '</startMediaPort>';
		$o[] = '<stopMediaPort>' . (int)($s['rtp_end'] ?? 32766) . '</stopMediaPort>';
		$o[] = '<natEnabled>false</natEnabled>';
		$o[] = '<natReceivedProcessing>false</natReceivedProcessing>';
		$o[] = '<natAddress></natAddress>';
		$o[] = '<sipLines>';
		$o[] = '<line button="1" lineIndex="1">';
		$o[] = '<featureID>9</featureID>';
		$o[] = '<featureLabel>' . $x($label) . '</featureLabel>';
		$o[] = '<proxy>USECALLMANAGER</proxy>';
		// Con proxy USECALLMANAGER el teléfono usa el sipPort de callManager; se deja 5060 como en los SEP que funcionan
		$o[] = '<port>5060</port>';
		$o[] = '<name>' . $x($ext) . '</name>';
		$o[] = '<displayName>' . $x($name) . '</displayName>';
		$o[] = '<autoAnswer><autoAnswerEnabled>0</autoAnswerEnabled></autoAnswer>';
		$o[] = '<callWaiting>3</callWaiting>';
		$o[] = '<authName>' . $x($ext) . '</authName>';
		$o[] = '<authPassword>' . $x($device['secret'] ?? '') . '</authPassword>';
		$o[] = '<contact>' . $x($ext) . '</contact>';
		$o[] = '<sharedLine>false</sharedLine>';
		$o[] = '<messageWaitingLampPolicy>3</messageWaitingLampPolicy>';
		$o[] = '<messageWaitingAMWI>0</messageWaitingAMWI>';
		$o[] = '<messagesNumber>' . $x($s['vm_number'] ?? '*97') . '</messagesNumber>';
		$o[] = '<ringSettingIdle>4</ringSettingIdle>';
		$o[] = '<ringSettingActive>5</ringSettingActive>';
		$o[] = '<forwardCallInfoDisplay><callerName>true</callerName><callerNumber>true</callerNumber><redirectedNumber>true</redirectedNumber><dialedNumber>true</dialedNumber></forwardCallInfoDisplay>';
		$o[] = '<maxNumCalls>5</maxNumCalls>';
		$o[] = '<busyTrigger>4</busyTrigger>';
		$o[] = '<recordingOption>enable</recordingOption>';
		$o[] = '</line>';
		// Líneas adicionales (botones 2..n: líneas, speed dial, BLF) tal cual las cargó el usuario
		if (!empty($phone['extra_lines_xml']) && self::validFragment($phone['extra_lines_xml'])) {
			$o[] = $phone['extra_lines_xml'];
		}
		$o[] = '</sipLines>';
		$o[] = '<externalNumberMask></externalNumberMask>';
		$o[] = '<voipControlPort>' . $sipPort . '</voipControlPort>';
		$o[] = '<dscpForAudio>184</dscpForAudio>';
		$o[] = '<ringSettingBusyStationPolicy>0</ringSettingBusyStationPolicy>';
		$o[] = '<dialTemplate>' . $x($s['dial_template'] ?? '') . '</dialTemplate>';
		$o[] = '<softKeyFile>' . $x($s['softkey_file'] ?? '') . '</softKeyFile>';
		$o[] = '</sipProfile>';

		$o[] = '<MissedCallLoggingOption>1</MissedCallLoggingOption>';
		$o[] = '<featurePolicyFile></featurePolicyFile>';
		$o[] = '<commonProfile><phonePassword></phonePassword><backgroundImageAccess>true</backgroundImageAccess><callLogBlfEnabled>' . self::callLogBlf($phone['model']) . '</callLogBlfEnabled></commonProfile>';

		$web = !empty($s['web_access']) ? '0' : '1';
		$ssh = !empty($s['ssh_access']) ? '0' : '1';
		if ($is88) {
			$o[] = '<vendorConfig>' . self::vendorConfig88($web, $ssh) . '</vendorConfig>';
		} else {
			$o[] = '<vendorConfig><disableSpeaker>false</disableSpeaker><disableSpeakerAndHeadset>false</disableSpeakerAndHeadset>'
				. '<pcPort>0</pcPort><settingsAccess>1</settingsAccess><garp>0</garp><voiceVlanAccess>0</voiceVlanAccess>'
				. '<autoSelectLineEnable>1</autoSelectLineEnable><webAccess>' . $web . '</webAccess><spanToPCPort>1</spanToPCPort>'
				. '<loggingDisplay>1</loggingDisplay><sshAccess>' . $ssh . '</sshAccess></vendorConfig>';
		}

		$o[] = '<versionStamp></versionStamp>';
		$fwKey = 'fw_' . $phone['model'];
		if (!empty($s[$fwKey])) {
			$o[] = '<loadInformation>' . $x($s[$fwKey]) . '</loadInformation>';
		}

		if ($is88) {
			$o[] = '<phoneServices useHTTPS="false"><provisioning>2</provisioning>';
			foreach ([[1, 'Missed Calls', 'MissedCalls'], [1, 'Received Calls', 'ReceivedCalls'], [1, 'Placed Calls', 'PlacedCalls'], [2, 'Voicemail', 'Voicemail']] as $ps) {
				$o[] = '<phoneService type="' . $ps[0] . '" category="0"><name>' . $ps[1] . '</name><url>Application:Cisco/' . $ps[2] . '</url><vendor></vendor><version></version></phoneService>';
			}
			$o[] = '</phoneServices>';
		}

		if (!empty($s['locale_name'])) {
			$o[] = '<userLocale><name>' . $x($s['locale_name']) . '</name><uid>1</uid><langCode>' . $x($s['locale_lang'] ?? 'es') . '</langCode>'
				. '<version>' . $x($s['locale_version'] ?? '') . '</version><winCharSet>utf-8</winCharSet></userLocale>';
		}
		if (!empty($s['network_locale'])) {
			$o[] = '<networkLocale>' . $x($s['network_locale']) . '</networkLocale>';
			$o[] = '<networkLocaleInfo><name>' . $x($s['network_locale']) . '</name><version>' . $x($s['locale_version'] ?? '') . '</version></networkLocaleInfo>';
		}

		$o[] = '<deviceSecurityMode>1</deviceSecurityMode>';
		$o[] = '<idleTimeout>0</idleTimeout>';
		// URLs de servicios XML (:6972). {SERVER} = IP LAN del PBX (llega igual por el túnel)
		foreach (['authenticationURL', 'messagesURL', 'servicesURL', 'directoryURL', 'idleURL', 'informationURL', 'proxyServerURL'] as $u) {
			$val = isset($s['url_' . $u]) ? str_replace('{SERVER}', $server, $s['url_' . $u]) : '';
			$o[] = '<' . $u . '>' . $x($val) . '</' . $u . '>';
		}
		foreach (['secureAuthenticationURL', 'secureMessagesURL', 'secureServicesURL', 'secureDirectoryURL', 'secureInformationURL', 'secureIdleURL'] as $u) {
			$o[] = '<' . $u . '></' . $u . '>';
		}
		$o[] = '<transportLayerProtocol>' . (int)($s['sip_transport'] ?? 1) . '</transportLayerProtocol>';
		$o[] = '<TLSResumptionTimer>3600</TLSResumptionTimer>';
		$o[] = '<phonePersonalization>1</phonePersonalization>';
		$o[] = '<autoCallPickupEnable>true</autoCallPickupEnable>';
		$o[] = '<blfAudibleAlertSettingOfIdleStation>1</blfAudibleAlertSettingOfIdleStation>';
		$o[] = '<blfAudibleAlertSettingOfBusyStation>1</blfAudibleAlertSettingOfBusyStation>';
		$o[] = '<dndCallAlert>1</dndCallAlert>';
		$o[] = '<dndReminderTimer>5</dndReminderTimer>';
		$o[] = '<advertiseG722Codec>1</advertiseG722Codec>';
		$o[] = '<rollover>0</rollover>';
		$o[] = '<joinAcrossLines>0</joinAcrossLines>';
		if (!empty($s['capf_enabled'])) {
			$o[] = '<capfAuthMode>' . (int)($s['capf_auth_mode'] ?? 2) . '</capfAuthMode>';
			$o[] = '<capfList><capf><phonePort>' . (int)(!empty($s['capf_port']) ? $s['capf_port'] : 3804) . '</phonePort>'
				. '<processNodeName>' . $x(!empty($s['capf_server']) ? $s['capf_server'] : $server) . '</processNodeName></capf></capfList>';
		}
		$o[] = '<certHash></certHash>';
		$o[] = '<encrConfig>false</encrConfig>';
		$o[] = '<userId></userId>';
		$o[] = '<ownerId></ownerId>';
		$o[] = '<sshUserId>' . $x($s['ssh_user'] ?? 'admin') . '</sshUserId>';
		$o[] = '<sshPassword>' . $x($s['ssh_pass'] ?? 'admin') . '</sshPassword>';
		$o[] = '</device>';

		// Video por teléfono: tiene prioridad sobre la plantilla y el XML extra (null = no tocar)
		$over = [];
		if (self::supportsVideo($phone['model']) && isset($phone['video']) && $phone['video'] !== null && $phone['video'] !== '') {
			$v = (int)$phone['video'] ? '1' : '0';
			$over = ['vendorConfig' => ['videoCapability' => $v, 'hideVideoByDefault' => '0']];
		}
		return self::finalize(implode("\n", $o), (string)($phone['extra_xml'] ?? ''), $over);
	}

	/** vendorConfig de los 88xx, tomado de un SEP funcionando */
	private static function vendorConfig88($web, $ssh)
	{
		$v = [
			'defaultWallpaperFile' => '', 'disableSpeaker' => 'false', 'disableSpeakerAndHeadset' => 'false',
			'enableMuteFeature' => 'false', 'enableGroupListen' => 'true', 'holdResumeKey' => '1', 'recentsSoftKey' => '1',
			'dfBit' => '1', 'pcPort' => '0', 'spanToPCPort' => '1', 'garp' => '0', 'rtcp' => '1', 'videoRtcp' => '1',
			'voiceVlanAccess' => '0', 'videoCapability' => '1', 'hideVideoByDefault' => '0', 'separateMute' => '0',
			'ciscoCamera' => '1', 'usb1' => '1', 'usb2' => '1', 'usbClasses' => '0,1,2', 'sdio' => '1', 'wifi' => '1',
			'bluetooth' => '1', 'bluetoothProfile' => '0,1', 'btpbap' => '0', 'bthfu' => '0', 'ehookEnable' => '0',
			'autoSelectLineEnable' => '1', 'autoCallSelect' => '1', 'incomingCallToastTimer' => '10',
			'dialToneFromReleaseKey' => '0', 'joinAndDirectTransferPolicy' => '0', 'minimumRingVolume' => '',
			'simplifiedNewCall' => '0', 'actionableAlert' => '1', 'showCallHistoryForSelectedLine' => '0',
			'kemOneColumn' => '0', 'lineMode' => '0', 'lowerYourVoiceAlert' => '0', 'markCallAsSpam' => '1',
			'callParkMonitor' => '1', 'allCallsOnPrimary' => '0', 'softKeyControl' => '1', 'settingsAccess' => '1',
			'webProtocol' => '0', 'webAccess' => $web, 'webAdmin' => '1', 'adminPassword' => '', 'sshAccess' => $ssh,
			'detectCMConnectionFailure' => '0', 'g722CodecSupport' => '2', 'handsetWidebandEnable' => '2',
			'headsetWidebandEnable' => '2', 'headsetWidebandUIControl' => '1', 'handsetWidebandUIControl' => '1',
			'daysDisplayNotActive' => '1,2,3,4,5,6,7', 'displayOnTime' => '08:00', 'displayOnDuration' => '10:00',
			'displayIdleTimeout' => '00:01', 'displayOnWhenIncomingCall' => '1', 'displayRefreshRate' => '0',
			'daysBacklightNotActive' => '1,2,3,4,5,6,7', 'backlightOnTime' => '08:00', 'backlightOnDuration' => '10:00',
			'backlightIdleTimeout' => '00:01', 'backlightOnWhenIncomingCall' => '1', 'recordingTone' => '0',
			'recordingToneLocalVolume' => '100', 'recordingToneRemoteVolume' => '50', 'recordingToneDuration' => '',
			'moreKeyReversionTimer' => '5', 'peerFirmwareSharing' => '0', 'loadServer' => '', 'problemReportUploadURL' => '',
			'enableCdpSwPort' => '1', 'enableCdpPcPort' => '0', 'enableLldpSwPort' => '1', 'enableLldpPcPort' => '0',
			'cdpEnable' => 'true', 'powerNegotiation' => '0', 'outOfRangeAlert' => '0', 'scanningMode' => '2',
			'applicationURL' => '', 'appButtonTimer' => '0', 'appButtonPriority' => '0', 'specialNumbers' => '',
			'sendKeyAction' => '0', 'powerOffWhenCharging' => '0', 'homeScreen' => '0', 'accessContacts' => '1',
			'accessFavorites' => '1', 'accessVoicemail' => '1', 'accessApps' => '1',
		];
		$out = '';
		foreach ($v as $k => $val) {
			$out .= '<' . $k . '>' . self::x($val) . '</' . $k . '>';
		}
		return $out;
	}

	/**
	 * Aplica el XML extra (cada elemento de primer nivel REEMPLAZA al generado con el mismo nombre,
	 * o se agrega si el módulo no lo genera), calcula versionStamp y da formato.
	 */
	public static function finalize($xml, $extra = '', array $override = [])
	{
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;
		$dom->loadXML($xml);
		$root = $dom->documentElement;
		$applyOverride = function () use ($dom, $root, $override) {
			foreach ($override as $parent => $children) {
				$p = null;
				foreach ($root->childNodes as $c) {
					if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === $parent) {
						$p = $c;
						break;
					}
				}
				if (!$p) {
					$p = $root->appendChild($dom->createElement($parent));
				}
				foreach ($children as $name => $val) {
					$el = null;
					foreach ($p->childNodes as $c) {
						if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === $name) {
							$el = $c;
							break;
						}
					}
					if (!$el) {
						$el = $p->appendChild($dom->createElement($name));
					}
					$el->nodeValue = $val;
				}
			}
		};

		if (trim($extra) !== '' && self::validFragment($extra)) {
			$frag = new \DOMDocument('1.0', 'UTF-8');
			$frag->preserveWhiteSpace = false;
			$frag->loadXML('<r>' . $extra . '</r>');
			foreach (iterator_to_array($frag->documentElement->childNodes) as $node) {
				if ($node->nodeType !== XML_ELEMENT_NODE) {
					continue;
				}
				$imp = $dom->importNode($node, true);
				$same = [];
				foreach ($root->childNodes as $c) {
					if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === $node->nodeName) {
						$same[] = $c;
					}
				}
				if ($same) {
					$root->replaceChild($imp, $same[0]);
					foreach (array_slice($same, 1) as $dup) {
						$root->removeChild($dup);
					}
				} else {
					$root->appendChild($imp);
				}
			}
		}

		$applyOverride();

		// versionStamp determinístico: cambia solo si cambia el contenido (el teléfono lo usa para saber si recargar)
		$stampNode = null;
		foreach ($root->childNodes as $c) {
			if ($c->nodeType === XML_ELEMENT_NODE && $c->nodeName === 'versionStamp') {
				$stampNode = $c;
				break;
			}
		}
		if (!$stampNode) {
			$stampNode = $root->appendChild($dom->createElement('versionStamp'));
		}
		$stampNode->nodeValue = '';
		$h = md5($root->C14N());
		$stampNode->nodeValue = substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);

		$dom->formatOutput = true;
		// Etiquetas vacías como <x></x> (igual que los SEP de Cisco), no <x/>
		$out = $dom->saveXML(null, LIBXML_NOEMPTYTAG);
		$out = preg_replace('/^(<\?xml[^>]*\?>)\s*/', "$1\n", $out);
		return $out;
	}

	/** Forma canónica de un elemento (para comparar sin importar espacios ni formato) */
	public static function canonical(\DOMNode $node)
	{
		$d = new \DOMDocument();
		$d->preserveWhiteSpace = false;
		$d->loadXML($node->ownerDocument->saveXML($node));
		return $d->documentElement->C14N();
	}

	public static function validFragment($xml)
	{
		$prev = libxml_use_internal_errors(true);
		$ok = simplexml_load_string('<r>' . $xml . '</r>') !== false;
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return $ok;
	}

	/** Contenido de sip_notify para chan_sip con los eventos que entienden los Cisco */
	public static function sipNotifyConf()
	{
		$stamps = [
			'ConfigVersionStamp={00000000-0000-0000-0000-000000000000}',
			'DialplanVersionStamp={00000000-0000-0000-0000-000000000000}',
			'SoftkeyVersionStamp={00000000-0000-0000-0000-000000000000}',
			'FeatureControlVersionStamp={00000000-0000-0000-0000-000000000000}',
			'HeadsetVersionStamp={0-0000000000}',
		];
		$out = "; Generado por FreePBX Cisco SIP Manager. No editar a mano.\n\n";
		foreach (['restart' => 'cisco-csm-restart', 'reset' => 'cisco-csm-reset'] as $action => $name) {
			$out .= "[$name]\n";
			$out .= "Event=>service-control\n";
			$out .= "Subscription-State=>active\n";
			$out .= "Content-Type=>text/plain\n";
			$out .= "Content=>action=$action\n";
			$out .= "Content=>RegisterCallId={\${SIPPEER(\${PEERNAME},regcallid)}}\n";
			foreach ($stamps as $st) {
				$out .= "Content=>$st\n";
			}
			$out .= "\n";
		}
		$out .= "[cisco-csm-check-cfg]\nEvent=>check-sync\nContent-Length=>0\n";
		return $out;
	}
}
