# Cisco SIP Manager — módulo FreePBX 17 (chan_sip)

Módulo **no oficial** para administrar teléfonos Cisco con firmware SIP desde FreePBX.

**Requisitos**

- FreePBX 17 con extensiones **chan_sip** (Asterisk 20 o anterior; chan_sip no existe en Asterisk 21+).
- Teléfonos Cisco 7945/7965/7975 (firmware SIP 9.x) u 8811/8841/8845/8851/8861/8865 (firmware sip88xx).
- Servidor HTTP para la provisión (por ejemplo en el puerto 6970) y, opcionalmente, ocserv para la VPN.
- Opcional: [certutils y daemons de usecallmanager.nz](https://usecallmanager.nz/) para CAPF/LSC.

**Descarga:** bajá el `.tgz` de la última versión en [Releases](https://github.com/jorgedsan/Cisco_SIP_Manager/releases)
y subilo en *Admin → Module Admin → Upload modules*. No uses el zip "Source code" de GitHub: la carpeta interna debe
llamarse `ciscosipmanager`.

Licencia: GPLv3 o posterior (ver [LICENSE](LICENSE)). Historial de versiones en [CHANGELOG.md](CHANGELOG.md).

Gestiona teléfonos Cisco con firmware SIP (7945/7965/7975, 88xx) desde FreePBX:

- **Provisión**: genera `SEP<MAC>.cnf.xml` por teléfono, tomando extensión y clave de FreePBX (solo dispositivos chan_sip).
- **Estado**: registro, IP, latencia (qualify) y firmware vía AMI, y clasifica cada teléfono en **LAN** (192.168.10.0/24), **VPN** (192.168.11.0/24), otra red u offline. Se refresca cada 15 s.
- **Acciones remotas**: check-sync (recargar config), restart, reset, mensajes de texto en pantalla (individual o a todos).
- **Ver pantalla y control remoto**: captura con actualización cada 3 s y teclado virtual (softkeys, navegación, teclado, altavoz, mute, espera, volumen…).
- **Llamar desde FreePBX**: el teléfono marca solo (`Dial:`).
- **Acciones masivas**: *Restart todos* / *Reboot todos* en la barra superior, o seleccionar varios teléfonos y regenerar, recargar, reiniciar (de a uno, esperando que vuelvan a registrar) o mandar mensaje.
- **Inventario**: modelo real, número de serie, firmware y hostname desde `/DeviceInformationX`, con aviso si no coinciden con lo configurado.
- **Sesiones VPN**: sesiones activas de ocserv (`occtl`), asociadas a cada teléfono por el CN `SEP<MAC>`, con opción de desconectar.
- **Editores visuales**: teclas adicionales (líneas, BLF/speed dial), fondos de pantalla (`Desktops/…/List.xml`) y tonos (`RingList.xml` y `RingList-wb.xml`).
- **Historial de versiones**: cada SEP generado queda guardado (últimas 20 por teléfono, configurable), con diff contra la versión actual y restauración en un clic. Detecta si el archivo fue editado a mano fuera del módulo y guarda esa versión antes de pisarla.
- **Backup & Restore de FreePBX**: teléfonos, ajustes e historial entran en los backups programados de FreePBX. Al restaurar se regeneran los SEP, `sip_notify` y `authenticate.php`.
- **VPN AnyConnect** (solo 88xx; los 79x5 no tienen cliente AnyConnect): agrega `<vpnGroup>` al SEP con la URL de ocserv y el `certHash1` calculado desde el certificado PEM.

## Idioma / Language

La interfaz sigue el idioma de FreePBX (*Admin → User Management* o *Advanced Settings → UIDEFAULTLANG*).
Incluye inglés (textos del código) y castellano (`i18n/es`). Cualquier otro idioma se ve en inglés.
El sistema tiene que tener instalado el locale (por ejemplo `es_ES.UTF-8`). Los textos de este README
corresponden a la interfaz en castellano.

The UI follows the FreePBX language. English (source strings) and Spanish are included. To add a language,
copy `i18n/ciscosipmanager.pot` to `i18n/<lang>/LC_MESSAGES/ciscosipmanager.po`, translate it and run
`python3 i18n/update.py` (needs GNU gettext), which also refreshes the template and compiles the `.mo` files.

## Instalación

1. Admin → Module Admin → *Upload modules* → subir `ciscosipmanager-17.6.8.tgz` → instalar.
   O por consola:
   ```
   tar xzf ciscosipmanager-17.6.8.tgz -C /var/www/html/admin/modules/
   fwconsole chown
   fwconsole ma install ciscosipmanager
   fwconsole reload
   ```
2. Al instalar, el módulo escribe `/etc/asterisk/sip_notify_ciscosipmanager.conf` y agrega
   `#include sip_notify_ciscosipmanager.conf` a `sip_notify_custom.conf`, luego hace `sip reload`.
3. Permisos del directorio de provisión (el que sirve el HTTP en :6970):
   ```
   chgrp asterisk /tftpboot && chmod g+w /tftpboot
   ```
   El servidor web de FreePBX corre como `asterisk`, así que necesita poder escribir ahí.

## Configuración

**Ajustes → Provisión**: directorio (`/tftpboot` o el que use tu :6970), IP del PBX.

**Ajustes → Servicios XML**: `authenticationURL` apunta por defecto a `http://{SERVER}:6972/authenticate.php`.
Indicá el DocumentRoot del vhost :6972 y el módulo escribe ahí `authenticate.php`; si no, copialo desde la caja de texto.
Sin esta URL los teléfonos rechazan los push a `/CGI/Execute` (mensajes).

**Ajustes → Acceso**: "página web del teléfono" tiene que estar habilitada para que acepten push HTTP y para *Ver pantalla*.

*Ver pantalla* pide `http://IP/CGI/Screenshot` desde la Raspberry con el mismo usuario/clave push, que el teléfono valida
contra `authenticationURL`. Como la pide FreePBX, funciona también con los teléfonos remotos por VPN.

## VPN con ocserv

Por defecto el módulo configura la VPN con **autenticación por certificado** (`authMethod` 2): el teléfono presenta
su LSC (o el MIC si no tiene LSC). El flujo es:

1. En la LAN, instalar el LSC por línea de comandos: `sudo -u capf capfctl /var/lib/capf/capf.sqlite3 --install SEP<MAC>`
   y reiniciar el teléfono para que lo descargue de CAPF.
2. Provisionarlo con el perfil VPN tildado, para que baje el `vpnGroup`.
3. Fuera de casa, en *Ajustes → Seguridad → Configuración VPN*, habilitar VPN. No pide usuario ni clave.

Del lado de ocserv:
```
auth = certificate
cert-user-oid = 2.5.4.3          # CN del certificado (SEP<MAC>) como usuario
ca-cert = /etc/ocserv/lsc-ca.pem # CA que firma los LSC (el issuer/capf.pem de capfd)
```

En `ocserv.conf` los Cisco necesitan compatibilidad con el cliente viejo:
```
cisco-svc-client-compat = true   # ocserv >= 1.2.0
```
y rutear la LAN de la casa hacia los clientes (`route = 192.168.10.0/255.255.255.0`).
Desde la Raspberry tiene que haber ruta hacia 192.168.11.0/24 (si ocserv corre en otra máquina) para que FreePBX
pueda enviar los mensajes push a los teléfonos remotos.

### Ubicación de cada teléfono

| Ubicación | callManagerGroup | autoNetDetect |
|---|---|---|
| **Auto** (con VPN tildada) | dos servidores: IP del PBX en la VPN y IP LAN (orden en Ajustes, por defecto VPN primero) | 1: el túnel solo se levanta si el teléfono no llega a la red de casa |
| **Siempre por VPN** | solo la IP del PBX en la VPN | 0: el túnel se levanta siempre |
| **Solo LAN** | solo la IP LAN | — |

La IP del PBX en la VPN es *Ajustes → Servidor PBX (VPN)*; vacío = primera IP de la red VPN (192.168.11.1 con ocserv en
192.168.11.0/24). Las URLs de servicios, NTP y CAPF usan siempre la IP LAN, que también se alcanza por el túnel.

## Importar SEP existentes

*Importar SEP existentes* lista los `SEP*.cnf.xml` del directorio de provisión que el módulo todavía no gestiona y los da
de alta **sin modificar los archivos**:

- Toma MAC, extensión (línea 1), etiqueta y VPN del archivo. El modelo sale del User-Agent si el teléfono está registrado;
  si no, hay que elegirlo (el firmware de los 88xx es compartido entre modelos).
- Las líneas 2..n (otras líneas, speed dial, BLF) pasan a *Líneas adicionales* y los elementos que el módulo no genera
  (`phoneServices`, `addOnModules`, etc.) al *XML extra*, así que se conservan al regenerar.
- El archivo original queda en el historial como "importado".
- Los importados quedan marcados como **pendientes**: *Regenerar todos* no los toca. Usá *Comparar con lo que se generaría*
  y regenerá cada uno cuando el diff esté bien.
- Al regenerar, `vpnGroup`, `loadInformation`, `capfAuthMode`, URLs de servicios, etc. salen de Ajustes: completalos antes.
- Si un restore de FreePBX encuentra un teléfono todavía pendiente, vuelve a escribir su archivo original desde el historial.

## XML extra

Cada elemento de primer nivel del *XML extra* **reemplaza** al que genera el módulo con el mismo nombre (por ejemplo un
`vendorConfig` o `phoneServices` propio), o se agrega si el módulo no lo genera. Al importar, solo pasa al XML extra lo
que difiere de lo que generaría el módulo; `devicePool`, `sipProfile`, `vpnGroup`, URLs, CAPF, locale y firmware salen
siempre de Ajustes.

`versionStamp` lo calcula el módulo a partir del contenido: cambia solo cuando cambia la configuración.

## CAPF

Con *Ajustes → CAPF* activado (por defecto), cada SEP lleva `capfAuthMode` 2 (contraseña vacía) y `capfList` apuntando al
PBX en el puerto 3804. Coincide con instalar el LSC sin contraseña:
```
sudo -u capf capfctl /var/lib/capf/capf.sqlite3 --install SEP<MAC>
```
Si un teléfono necesita otro modo, poné `<capfAuthMode>…</capfAuthMode>` o `<capfList>…</capfList>` en su XML extra:
tiene prioridad sobre el valor global.

## Control remoto, avisos y marcado

Todo va por `http://IP/CGI/Execute` con el usuario/clave push (el teléfono los valida contra `authenticationURL`):

- Teclas: `Key:Soft1…5`, `NavUp/NavDwn/NavLeft/NavRight/NavSelect/NavBack`, `KeyPad0…9/Star/Pound`, `Speaker`, `Headset`, `Mute`,
  `Hold`, `Transfer`, `Conference`, `VolUp/VolDwn`, `Messages`, `Directories`, `Settings`, `Line1`. Si un modelo no tiene una tecla, la ignora.
- Marcar: `Dial:<número>`.
- Con *Ajustes → Encender la pantalla* (activado por defecto), los mensajes y el marcado mandan antes `Display:On:<minutos>`,
  así el aviso se ve aunque la pantalla esté apagada por horario.

## Sesiones VPN

El módulo corre como `asterisk` y `occtl` necesita root. Con `sudo visudo -f /etc/sudoers.d/ciscosipmanager`:
```
asterisk ALL=(root) NOPASSWD: /usr/bin/occtl -j show users, /usr/bin/occtl disconnect id *
```
Ajustá la ruta (`command -v occtl`) aquí y en *Ajustes → Comando occtl*.

## Teclas adicionales (editor)

En *Editar teléfono → Líneas adicionales*: **Línea** (otra extensión en el mismo teléfono; con "Clave de FreePBX" se guarda
`<authPassword>{FREEPBX}</authPassword>` y la clave real se completa recién al generar el SEP) y **BLF / speed dial**
(`featureID` 21). Los elementos que el editor no conoce se conservan; "ver / editar XML" muestra el XML completo.

## Fondos y tonos

- Fondos: la imagen se escala/recorta a `Desktops/<WxHxD>/` con miniatura y se agrega al `List.xml`. Las entradas existentes
  (por ejemplo los `syn_wallpaper` de Cisco) se conservan tal cual; las miniaturas se muestran según la ruta `Image=` del `List.xml`. Carpetas por defecto:
  88xx `800x480x24` (miniatura 139x109), 7945/7965 `320x212x16`, 7975 `320x216x16` (80x53); ajustables en Ajustes.
- Tonos: dos listas en el directorio de provisión, `RingList.xml` (banda angosta, `.raw` µ-law 8 kHz, 79xx) y
  `RingList-wb.xml` (banda ancha, `.rwb` PCM 16 bits 16 kHz mono, 88xx). Nombres configurables; si ya existen con otras
  mayúsculas se usan esos. Un audio subido se convierte para las dos. Hasta 50 tonos por lista, nombres de hasta 25 caracteres. El audio se convierte a µ-law 8 kHz mono con `sox` (o `ffmpeg`),
  máximo 2 s y cantidad de muestras múltiplo de 240, como piden los Cisco.

## Historial y backups

- El historial se guarda en `/var/lib/asterisk/ciscosipmanager/history/`, **fuera** del directorio que sirve :6970,
  porque las versiones viejas contienen claves SIP. No lo muevas adentro de `/tftpboot`.
- Regenerar sin cambios no crea una versión nueva.
- *Restaurar* una versión escribe ese archivo en el teléfono, pero no cambia los datos del módulo: si después regenerás,
  el SEP se vuelve a armar desde los datos actuales (y la versión restaurada queda en el historial).
- En *Admin → Backup & Restore*, el módulo aparece como "Cisco SIP Manager"; tildalo en tus trabajos de backup.
  Los SEP en sí no se copian: se regeneran al restaurar con las claves de las extensiones restauradas.

## Notas

- El transporte SIP por defecto es **TCP** (`transportLayerProtocol` 1): chan_sip necesita `tcpenable=yes` y la extensión `transport=tcp`.

- Los eventos de reinicio usan `service-control` con `RegisterCallId` (`SIPPEER(...,regcallid)`), que es lo que
  piden los Cisco SIP; `check-sync` recarga el SEP sin reiniciar en la mayoría de los firmwares.
- chan_sip se quitó de Asterisk 21; este módulo asume que seguís en Asterisk 20 o anterior. Para PJSIP habría que
  reemplazar `SIPshowpeer`/`sip notify` por `PJSIPShowEndpoint`/`pjsip send notify`.
- El SEP generado se puede ver en *Acciones → Ver XML generado* antes de reiniciar el teléfono.
