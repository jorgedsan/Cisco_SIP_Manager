# Cisco SIP Manager — FreePBX 17 module (chan_sip)

**English** · [Español](README.es.md)

**Unofficial** module to manage Cisco phones running SIP firmware from FreePBX.

**Requirements**

- FreePBX 17 with **chan_sip** extensions (Asterisk 20 or earlier; chan_sip does not exist in Asterisk 21+).
- Cisco 7945/7965/7975 phones (SIP firmware 9.x) or 8811/8841/8845/8851/8861/8865 (sip88xx firmware).
- An HTTP server for provisioning (for example on port 6970) and, optionally, ocserv for the VPN.
- Optional: [usecallmanager.nz certutils and daemons](https://usecallmanager.nz/) for CAPF/LSC.

**Download:** get the `.tgz` of the latest version from [Releases](https://github.com/jorgedsan/Cisco_SIP_Manager/releases)
and upload it in *Admin → Module Admin → Upload modules*. Do not use GitHub's "Source code" zip: the folder inside must
be named `ciscosipmanager`.

License: GPLv3 or later (see [LICENSE](LICENSE)). Version history in [CHANGELOG.md](CHANGELOG.md).

Manages Cisco phones with SIP firmware (7945/7965/7975, 88xx) from FreePBX:

- **Provisioning**: generates one `SEP<MAC>.cnf.xml` per phone, taking the extension and password from FreePBX (chan_sip devices only).
- **Status**: registration, IP, latency (qualify) and firmware through AMI, and classifies each phone as **LAN** (192.168.10.0/24), **VPN** (192.168.11.0/24), other network or offline. Refreshed every 15 s.
- **Remote actions**: check-sync (reload config), restart, reset, text messages on the screen (one phone or all).
- **View screen and remote control**: screenshot refreshed every 3 s and a virtual keypad (softkeys, navigation, keypad, speaker, mute, hold, volume…).
- **Call from FreePBX**: the phone dials by itself (`Dial:`).
- **Bulk actions**: *Restart all* / *Reboot all* in the top bar, or select several phones and regenerate, reload, restart (one at a time, waiting for each to register again) or send a message.
- **Inventory**: actual model, serial number, firmware and hostname from `/DeviceInformationX`, with a warning when they do not match the configuration.
- **VPN sessions**: active ocserv sessions (`occtl`), matched to each phone by the `SEP<MAC>` CN, with an option to disconnect.
- **Visual editors**: additional keys (lines, BLF/speed dial), backgrounds (`Desktops/…/List.xml`) and ringtones (`RingList.xml` and `RingList-wb.xml`).
- **Version history**: every generated SEP is kept (last 20 per phone, configurable), with a diff against the current version and one-click restore. It detects when the file was edited by hand outside the module and saves that version before overwriting it.
- **FreePBX Backup & Restore**: phones, settings and history are included in the FreePBX scheduled backups. Restoring regenerates the SEP files, `sip_notify` and `authenticate.php`.
- **AnyConnect VPN** (88xx only; the 79x5 have no AnyConnect client): adds `<vpnGroup>` to the SEP with the ocserv URL and the `certHash1` calculated from the PEM certificate.

## Language

The UI follows the FreePBX language (*Admin → User Management* or *Advanced Settings → UIDEFAULTLANG*).
English (source strings) and Spanish (`i18n/es`) are included; any other language shows English.
The system needs the locale installed (for example `es_ES.UTF-8`). Screen and option names in this README refer to the
English UI.

To add a language: copy `i18n/ciscosipmanager.pot` to `i18n/<lang>/LC_MESSAGES/ciscosipmanager.po`, translate it and run
`python3 i18n/update.py` (needs GNU gettext), which also refreshes the template and compiles the `.mo` files.

## Installation

1. Admin → Module Admin → *Upload modules* → upload `ciscosipmanager-<version>.tgz` → install.
   Or from the console:
   ```
   tar xzf ciscosipmanager-<version>.tgz -C /var/www/html/admin/modules/
   fwconsole chown
   fwconsole ma install ciscosipmanager
   fwconsole reload
   ```
2. On install, the module writes `/etc/asterisk/sip_notify_ciscosipmanager.conf`, adds
   `#include sip_notify_ciscosipmanager.conf` to `sip_notify_custom.conf` and runs `sip reload`.
3. Permissions on the provisioning directory (the one served by HTTP on :6970):
   ```
   chgrp asterisk /tftpboot && chmod g+w /tftpboot
   ```
   The FreePBX web server runs as `asterisk`, so it must be able to write there.

## Configuration

**Settings → Provisioning**: directory (`/tftpboot` or whatever your :6970 uses), PBX IP.

**Settings → XML services**: `authenticationURL` points by default to `http://{SERVER}:6972/authenticate.php`.
Set the DocumentRoot of the :6972 vhost and the module writes `authenticate.php` there; otherwise copy it from the text box.
Without this URL the phones reject pushes to `/CGI/Execute` (messages).

**Settings → Access**: "phone web page" must be enabled for the phones to accept HTTP pushes and for *View screen*.

*View screen* requests `http://IP/CGI/Screenshot` from the PBX with the same push user/password, which the phone checks
against `authenticationURL`. Since FreePBX makes the request, it also works with remote phones over the VPN.

## VPN with ocserv

By default the module sets up the VPN with **certificate authentication** (`authMethod` 2): the phone presents
its LSC (or the MIC if it has no LSC). The flow is:

1. On the LAN, install the LSC from the command line: `sudo -u capf capfctl /var/lib/capf/capf.sqlite3 --install SEP<MAC>`
   and restart the phone so it downloads it from CAPF.
2. Provision it with the VPN profile checked, so it gets the `vpnGroup`.
3. Away from home, enable the VPN on the phone (*Settings → Security setup → VPN configuration*). It does not ask for a user or password.

On the ocserv side:
```
auth = certificate
cert-user-oid = 2.5.4.3          # certificate CN (SEP<MAC>) as the user name
ca-cert = /etc/ocserv/lsc-ca.pem # CA that signs the LSCs (capfd's issuer/capf.pem)
```

In `ocserv.conf` the Cisco phones need compatibility with the old client:
```
cisco-svc-client-compat = true   # ocserv >= 1.2.0
```
and the home LAN must be routed to the clients (`route = 192.168.10.0/255.255.255.0`).
The PBX needs a route to 192.168.11.0/24 (if ocserv runs on another machine) so FreePBX can send push messages to the
remote phones.

### Location of each phone

| Location | callManagerGroup | autoNetDetect |
|---|---|---|
| **Auto** (with VPN checked) | two servers: the PBX IP inside the VPN and the LAN IP (order in Settings, VPN first by default) | 1: the tunnel only comes up when the phone cannot reach the home network |
| **Always through VPN** | only the PBX IP inside the VPN | 0: the tunnel always comes up |
| **LAN only** | only the LAN IP | — |

The PBX IP inside the VPN is *Settings → PBX server (VPN)*; empty = first IP of the VPN network (192.168.11.1 with ocserv on
192.168.11.0/24). Service URLs, NTP and CAPF always use the LAN IP, which is also reachable through the tunnel.

## Import existing SEP files

*Import existing SEP files* lists the `SEP*.cnf.xml` files in the provisioning directory that the module does not manage yet,
and adds them **without modifying the files**:

- It takes the MAC, extension (line 1), label and VPN from the file. The model comes from the User-Agent when the phone is
  registered; otherwise you have to choose it (the 88xx firmware is shared between models).
- Lines 2..n (other lines, speed dials, BLF) go to *Additional lines*, and the elements the module does not generate
  (`phoneServices`, `addOnModules`, etc.) go to the *Extra XML*, so they are kept when regenerating.
- The original file is stored in the history as "import".
- Imported phones are marked as **pending**: *Regenerate all SEP files* does not touch them. Use *Compare with what would be
  generated* and regenerate each one when the diff looks right.
- When regenerating, `vpnGroup`, `loadInformation`, `capfAuthMode`, service URLs, etc. come from Settings: fill them in first.
- If a FreePBX restore finds a phone that is still pending, it writes its original file back from the history.

## Extra XML

Each top-level element of the *Extra XML* **replaces** the element with the same name generated by the module (for example
your own `vendorConfig` or `phoneServices`), or is added when the module does not generate it. When importing, only what
differs from what the module would generate goes to the extra XML; `devicePool`, `sipProfile`, `vpnGroup`, URLs, CAPF,
locale and firmware always come from Settings.

The module calculates `versionStamp` from the content: it only changes when the configuration changes.

## CAPF

With *Settings → CAPF* enabled (the default), every SEP carries `capfAuthMode` 2 (empty password) and a `capfList` pointing
to the PBX on port 3804. That matches installing the LSC without a password:
```
sudo -u capf capfctl /var/lib/capf/capf.sqlite3 --install SEP<MAC>
```
If a phone needs another mode, put `<capfAuthMode>…</capfAuthMode>` or `<capfList>…</capfList>` in its extra XML:
it takes precedence over the global value.

## Remote control, notices and dialing

Everything goes through `http://IP/CGI/Execute` with the push user/password (the phone checks them against `authenticationURL`):

- Keys: `Key:Soft1…5`, `NavUp/NavDwn/NavLeft/NavRight/NavSelect/NavBack`, `KeyPad0…9/Star/Pound`, `Speaker`, `Headset`, `Mute`,
  `Hold`, `Transfer`, `Conference`, `VolUp/VolDwn`, `Messages`, `Directories`, `Settings`, `Line1`. If a model does not have a key, it ignores it.
- Dialing: `Dial:<number>`.
- With *Settings → Turn the display on* (enabled by default), messages and dialing first send `Display:On:<minutes>`,
  so the notice is visible even when the display is off because of its schedule.

## VPN sessions

The module runs as `asterisk` and `occtl` needs root. With `sudo visudo -f /etc/sudoers.d/ciscosipmanager`:
```
asterisk ALL=(root) NOPASSWD: /usr/bin/occtl -j show users, /usr/bin/occtl disconnect id *
```
Adjust the path (`command -v occtl`) here and in *Settings → occtl command*.

## Additional keys (editor)

In *Edit phone → Additional lines*: **Line** (another extension on the same phone; with "FreePBX password" it stores
`<authPassword>{FREEPBX}</authPassword>` and the real password is only filled in when the SEP is generated) and
**BLF / speed dial** (`featureID` 21). Elements the editor does not know are kept; "view / edit XML" shows the full XML.

## Backgrounds and ringtones

- Backgrounds: the image is scaled/cropped into `Desktops/<WxHxD>/` with a thumbnail and added to `List.xml`. Existing entries
  (for example Cisco's `syn_wallpaper` files) are kept as they are; thumbnails are shown from the `Image=` path in `List.xml`.
  Default folders: 88xx `800x480x24` (thumbnail 139x109), 7945/7965 `320x212x16`, 7975 `320x216x16` (80x53); adjustable in Settings.
- Ringtones: two lists in the provisioning directory, `RingList.xml` (narrowband, `.raw` µ-law 8 kHz, 79xx) and
  `RingList-wb.xml` (wideband, `.rwb` 16-bit PCM 16 kHz mono, 88xx). The names are configurable; if they already exist with
  different capitalization, those are used. An uploaded audio file is converted for both lists with `sox` (or `ffmpeg`):
  2 s maximum and a sample count that is a multiple of 240, as the Cisco phones require. Up to 50 ringtones per list,
  names up to 25 characters.

## History and backups

- The history is stored in `/var/lib/asterisk/ciscosipmanager/history/`, **outside** the directory served on :6970,
  because old versions contain SIP passwords. Do not move it inside `/tftpboot`.
- Regenerating without changes does not create a new version.
- *Restoring* a version writes that file for the phone, but does not change the module data: if you regenerate afterwards,
  the SEP is built again from the current data (and the restored version stays in the history).
- In *Admin → Backup & Restore* the module shows up as "Cisco SIP Manager"; check it in your backup jobs.
  The SEP files themselves are not copied: they are regenerated on restore with the passwords of the restored extensions.

## Notes

- The default SIP transport is **TCP** (`transportLayerProtocol` 1): chan_sip needs `tcpenable=yes` and the extension `transport=tcp`.
- Restart events use `service-control` with `RegisterCallId` (`SIPPEER(...,regcallid)`), which is what Cisco SIP phones
  expect; `check-sync` reloads the SEP without restarting on most firmware versions.
- chan_sip was removed in Asterisk 21; this module assumes you are still on Asterisk 20 or earlier. For PJSIP,
  `SIPshowpeer`/`sip notify` would have to be replaced by `PJSIPShowEndpoint`/`pjsip send notify`.
- You can check the generated SEP in *Actions → View generated XML* before restarting the phone.
