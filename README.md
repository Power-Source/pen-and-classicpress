# PS Pen & Paper

PS Pen & Paper entsteht als systemunabhängiger Baukasten für Pen-and-Paper-
Kampagnen auf WordPress und ClassicPress.

## Handbuch und Demorunde

- [Pluginhandbuch mit Screenshots](docs/handbuch.md): Einrichtung, Rollen,
  Charaktere, Freigaben, Würfel, Ressourcen, Farben und bekannte Grenzen.
- [Durchgespielte Demokampagne](docs/demokampagne.md): echte Würfelergebnisse,
  getrennte Rollen, Prüfbericht und noch fehlende Schritte bis zum Story Builder.

Die Demo ist ein Spielstand auf der Entwicklungswebsite, keine automatische
Installation von Beispieldaten oder ein fest eingebautes Regelwerk.

## Aktueller Stand

Kampagnen können angelegt, bearbeitet und archiviert werden. Administratoren
können Mitgliedern dieser Website die Berechtigung erteilen, eigene Kampagnen
zu erstellen. Kampagnenersteller und weitere zugeordnete Spielleiter verwalten
die Kampagne und ihre Mitglieder. Spieler sehen nur Kampagnen, denen sie
zugeordnet sind; Administratoren können alle Kampagnen verwalten.
Netzwerk-Superadmins haben diesen Zugriff auch ohne Mitgliedschaft auf der
jeweiligen Unterwebsite, einschließlich privater Bögen, Protokolle und Chat.

Die Verwaltung ist in **Übersicht**, **Universen**, **Kampagnen**, **Charakterverwaltung**
und **Einstellungen** getrennt. Dieselben Bereiche sind im Frontend erreichbar;
der Spieltisch ist davon getrennt. Die Charakterverwaltung zeigt nur eigene
beziehungsweise als Spielleitung verwaltbare Bögen.

Mitglieder müssen bereits Benutzer dieser Website sein; Einladungen und die
Aufnahme in eine Netzwerk-Website sind noch nicht implementiert. Der Ersteller
bleibt Spielleiter seiner Kampagne. Ein Archivkennzeichen markiert Kampagnen
derzeit nur in der Übersicht; es sperrt keine Aktionen.

Das Plugin kann über die Plugin-Verwaltung aktiviert werden. Angemeldete
Website-Mitglieder finden ihre Kampagnen unter „Pen & Paper“ im Backend. Es
verwendet private Inhalte und Metadaten der jeweiligen Website, aber keine
eigenen Tabellen. Eine Würfelmechanik mit geschütztem Ereignisprotokoll ist vorhanden;
Ein erster digitaler Spieltisch und eine optionale PS-Chat-Anbindung sind vorhanden;
der Story Builder folgt später.

## Geschützter Spielbereich im Frontend

Eine normale Seite mit dem Shortcode `[ps_pen_paper]` veröffentlichen; pro Seite
eine Instanz verwenden. Angemeldete Mitglieder sehen dort ihre Kampagnen,
Regeln und eigenen Charaktere. Spielleiter können Charaktere für Mitglieder
erstellen, bearbeiten und Anträge freigeben oder verwerfen. Es gelten dieselben
Bearbeitungsrechte und Revisionsprüfungen wie im Backend. Nach dem Speichern
bleibt man im Frontend. Zugang, Beitrittsanfragen, Hintergrundbild und Farben
sind über **Rundeneinstellungen** direkt im Frontend erreichbar. Regeln und
persönliche Mitgliederzuordnung werden weiterhin im Backend eingerichtet.
Frontend-Formulare werden direkt auf der veröffentlichten Spielseite verarbeitet,
damit Backend-Weiterleitungen anderer Plugins Spieleraktionen nicht abfangen.
Nonce-, Mitgliedschafts- und Bearbeitungsprüfungen bleiben dabei unverändert.

Der Shortcode verwendet normalerweise das Layout der Seite. Optional steht
die Seitenvorlage **„PS Pen & Paper – Spielbereich“** zur Verfügung. Sie
benötigt kein eingerichtetes Theme-Layout und wird nur für die gewählte Seite
verwendet. Eine Seite wird nicht automatisch angelegt.

Neue und bisher nicht konfigurierte Kampagnen bleiben geschlossen. Gäste
erhalten eine Anmeldeaufforderung, Nichtmitglieder keine internen Inhalte.
Spielleiter können ausdrücklich **Titel und separaten Teaser** über den
Kampagnenlink freigeben. Die interne Beschreibung, Bilder, Teilnehmerliste,
Charaktere, Würfe und der Chat werden dabei nicht ausgegeben.
Der Spielbereich wird nicht in Feeds, Einbettungen oder REST-Inhaltsausgaben
gerendert. Die Seite erhält `no-store`-/`private`-Cache-Header,
`DONOTCACHEPAGE` und `X-Robots-Tag: noindex, nofollow`. **Bei vorgeschalteten
Seiten-Caches oder CDNs die Spielbereich-Seite einschließlich aller
Abfrageparameter ausdrücklich vom Caching ausschließen.** Solche Systeme
können bereits vor dem Start des Plugins antworten. `noindex` ersetzt keine
Zugriffsprüfung.

## Digitaler Spieltisch

Seit Version 0.9.0 trennt der Spielbereich die große Erzählfläche, die kompakte
Gruppenleiste und den vollwertigen Charakterbogen. Es ist jeweils ein
Arbeitsbereich sichtbar; die Runde bleibt erreichbar. Auf Handy und
Tablet passt sich die Anordnung an; schmale Ansichten wechseln zwischen
**Spiel**, **Mein Bogen**, **Würfel** und **Gruppe**. Regelwerk und Protokoll
werden separat geöffnet, Charaktereingaben erst bei Bedarf eingeblendet.
Spielleiter sehen auch fremde Bögen und Freigaben; Spieler weiterhin nur ihre
eigenen. Die Buchstabenplaketten sind Platzhalter, noch kein Bild-Avatarsystem.

### Teilnahme und Einstieg

1. Mit einem bestehenden Konto dieser Website anmelden. Das Plugin erstellt
   keine Konten; eine Website-Registrierung muss der Betreiber separat anbieten.
2. Den Kampagnenlink öffnen. Bei freigegebenem Teaser und aktivierten Anfragen
   **Teilnahme beantragen** wählen, sonst persönlich aufnehmen lassen.
3. Die Spielleitung entscheidet unter **Rundeneinstellungen → Beitrittsanfragen**.
   Eine Anfrage allein gewährt weder Spiel- noch Chat-Zugang.
4. Nach der Aufnahme den Spieltisch öffnen, **Ersten Charakter erstellen**
   beziehungsweise **Mein Bogen → Neuen Charakter erstellen** wählen.
5. Den Bogen nach dem Regelwerk speichern oder zur Freigabe einreichen.

Abgelehnte Anfragen können nicht unmittelbar erneut gestellt werden; die
Spielleitung kann die Person weiterhin persönlich aufnehmen. Bereits offene
Anfragen können auch nach dem Schließen der Vorschau noch entschieden werden.
Eine Freigabe verleiht nur die Spielerrolle dieser Kampagne. Es gibt keinen
automatischen Beitritt oder automatischen Aufstieg zur Spielleitung.

### Eigene Hintergrundbilder

Unter **Rundeneinstellungen → Bild für den Spieltisch** kann die Spielleitung
PNG, JPEG oder WebP mit höchstens 2 MiB und 3000 × 3000 Pixeln hochladen.
SVG und externe Bildadressen sind ausgeschlossen. Die Daten liegen in einem
nicht öffentlichen `pspp_asset`-Datensatz und werden nur berechtigten Betrachtern
als eingebettete Bilddaten ausgeliefert; es entsteht keine öffentliche
Mediathek-Adresse. Ersetzen, Entfernen und Löschen der Kampagne entfernen
die zugehörigen Bilddaten. Bilder vergrößern entsprechend die Datenbank und
die ausgelieferte Spielseite; möglichst kleine Dateien verwenden.
Das ist kein allgemeines System für private Handouts oder beliebige Dateien.

### Universen, Basisbögen und Kampagnenkontexte

Seit Version 0.10.0 können berechtigte Spielleiter Universen anlegen, intern
beschreiben und revisionsgeschützt bearbeiten. Die bisherige Berechtigung
zum Erstellen von Kampagnen gilt auch für Universen. Bestehende Universen bleiben
nach Entzug dieser Erstellberechtigung verwaltbar. Nur Ersteller und Administration
sehen die Universumsverwaltung und deren Änderungsverlauf.

Unter **Universen → Kampagne zuordnen oder lösen** zeigt eine Vorschau die alte
und neue Zuordnung. Erst die ausdrückliche Bestätigung speichert sie. Dafür sind
Verwaltungsrechte an der Kampagne und beiden beteiligten Universen erforderlich.
Die Zuordnung steht im geschützten Kampagnenprotokoll. Universumsbesitz gewährt
keinen automatischen Zugriff auf zugeordnete Kampagnen oder deren Chat.
Bestehende Kampagnen werden nicht automatisch zugeordnet. Ihre Regeln, Bögen,
offenen Anträge und alten Würfe werden nicht verändert oder zusammengeführt.
Löschen von Universen und zusätzliche Universumsleiter sind noch keine UI-Aktionen.

Seit Version 0.11.0 öffnet **Universen → Regelwerk & Basischaraktere** die
Regelvorlage und die Basisbögen der Welt. Nur die Universumsleitung bearbeitet
Basisbögen; ihr Besitzer muss ein leseberechtigtes Website-Mitglied sein.
Besitz allein öffnet weder die private Weltbeschreibung noch deren Historie.

Eine zugeordnete Kampagne kann die Vorlage ausdrücklich übernehmen, solange sie
noch kein gespeichertes Regelwerk und keine Charaktere besitzt. Die Feldkennungen
bleiben erhalten; das Kampagnenregelwerk ist danach ein eigenständiger Stand,
keine automatisch synchronisierte Verbindung. Bestehende Regeln werden nicht ersetzt.
Für die Übernahme sind Verwaltungsrechte an Universum und Kampagne erforderlich.
Ein Basisbogen kann nur für einen bereits aufgenommenen Kampagnenteilnehmer und
bei übereinstimmenden Feldschemas übernommen werden:

- **Gemeinsamer Basisstand:** Der Kampagnenbogen folgt dem freigegebenen
  Basisstand. Grundwerte werden im Universum geändert, nicht über den Kampagnenbogen.
  Offene Basisanträge werden nie angezeigt oder übernommen.
- **Unabhängige Kampagnenkopie:** Der übernommene Stand entwickelt sich nach den
  Bearbeitungs- und Freigaberegeln der Kampagne weiter. Spätere Basisänderungen,
  ein fehlender Basisbogen oder das Lösen der Universumszuordnung ändern ihn nicht.

Unter **Kampagnenkontext → Kampagnenauflagen bearbeiten** kann die Kampagnenleitung
absolute ganzzahlige Attribut- und Zahlenwerte innerhalb der Regelgrenzen freigeben.
Leer bedeutet Grundwert übernehmen. Die Auflagen ändern weder den Basisbogen noch
andere Kampagnen; eine frei benannte numerische „Stufe“ ist damit ebenfalls möglich.
Die Notiz und die Auflagenhistorie bleiben Besitzer und aktueller Spielleitung
vorbehalten. Spieler können sich keine eigenen Auflagen erteilen.
Würfe verwenden die effektiven Werte und speichern Basis- und Kontextrevisionen,
aber keine privaten Auflagennotizen. Bereits gespeicherte Würfe bleiben unverändert.

Gemeinsame Bögen sperren derzeit einen Universumswechsel oder das Lösen der
Kampagnenzuordnung, statt verwaiste Verbindungen zu erzeugen. Bei einer fehlenden
Basis erscheint ein Fehler; es werden keine veralteten Ersatzwerte verwendet.
Noch offen sind automatische Migrationen bestehender Kampagnen, Moduswechsel,
universumsweite Mitgliedschaften, Spielerbearbeitung der Basis, Ressourcenauflagen
und ein XP-/Levelautomatismus. Ein numerisches Stufenfeld ist kein solches System.

Die Spielleitung kann einen Teilnehmer als **am Zug** markieren oder
**Freies Spiel** wählen. Änderungen sind revisionsgeschützt und werden mit
Vorher/Nachher im gemeinsamen Protokoll gespeichert. AFK schaltet den Zug
nicht automatisch weiter. Eine getrennte Sitzungsanmeldung gibt es noch nicht:
Die aktuelle Runde entspricht den eingetragenen Kampagnenmitgliedern.

**Anwesenheit teilen** aktiviert freiwillige Meldungen aus dem Spielbereich.
Bei sichtbarer Seite wird alle 30 Sekunden aktualisiert; nach mehr als
120 Sekunden ohne Meldung gilt die Verbindung als offline. „Nicht gemeldet“
ist ein eigener Zustand, keine behauptete Offline-Erkennung.
**Kurze Pause / AFK** erlaubt eine kurze optionale Textnotiz; **Wieder da**
hebt die Pause auf. **Status nicht mehr teilen** beendet die Anzeige.
Anwesenheit und Pausennotiz liegen nur als aktueller, höchstens einen Tag
gültiger Zustand vor, ohne Anwesenheitshistorie. Hintergrundaktualisierungen
heben AFK nicht auf. Browser-Ruhemodus kann die Anzeige verzögern.

Der eigene Spieltisch verwendet Vanilla JavaScript. Ohne JavaScript bleiben
Charakterformulare, Würfel und Protokoll untereinander zugänglich;
Anwesenheit und Zugsteuerung benötigen JavaScript und die REST-API.

### Optionaler Rundenchat mit PS Chat

PS Chat liefert Nachrichten, Polling und die bestehende Moderationsoberfläche.
Der Spieltisch bettet sie als einklappbare Chatbox ein, mobil als größeres Panel.
Ein Raum gehört genau zu einer Kampagne und Website. Spielleiter erhalten
raumbezogene Moderation, Spieler Zugang durch ihre aktuelle Mitgliedschaft;
Website-Administratoren behalten ihren administrativen Zugriff.
Entfernte Mitglieder verlieren den Zugriff bei der nächsten Anfrage, ehemalige
Spielleiter auch ihre Moderation. AFK verändert diese Rechte nicht.

**Benötigt die ergänzten Spieltisch-Schnittstellen im PS-Chat-Plugin:**
`psource_chat_session_access()` und die Filter `psource_chat_session_access`
und `psource_chat_session_moderator`, einschließlich der Prüfungen im
Legacy- und REST/AJAX-Pfad. Beim Übertragen auf eine andere Website müssen
beide Plugins mit diesen Änderungen ausgeliefert werden.
Geschützte Räume tragen `access_provider`; ohne aktive Integration werden
sie abgewiesen. Bestehende normale Chat-Räume behalten ihr Rollenmodell.

Die Chat-Integration baut kein eigenes Nachrichten- oder Moderationssystem.
Das vorhandene PS Chat verwendet weiterhin seine eigenen Skripte und seine
eigene Datenhaltung. Spielereignisse und Würfe werden nicht automatisch in den
Chat kopiert; insbesondere werden keine privaten Ergebnisse übertragen.
Ohne passendes PS Chat zeigt die Chatbox einen Hinweis; das Spiel bleibt nutzbar.

## Kampagnenfarben

Unter **Rundeneinstellungen → Farben & Lesbarkeit anpassen** legen Spielleiter Hintergrund, Inhaltsflächen,
Text, Schaltflächen, Links/Akzente und Rahmen fest. Die Farben gelten nur im
Spielbereich dieser Kampagne; Theme und andere Kampagnen bleiben unverändert.
Gespeichert werden ausschließlich sechsstellige Hex-Farben, kein eigenes CSS.
Gleichzeitige Änderungen werden anhand einer Revisionsnummer erkannt.

Farbwähler und Hex-Eingaben aktualisieren eine Live-Vorschau mit Vanilla
JavaScript. Die Schaltflächen-Schrift wird automatisch schwarz oder weiß
gewählt. Kontrasthinweise prüfen Text und Links auf Hintergrund und
Inhaltsflächen gegen den Richtwert 4,5:1. Hinweise blockieren das Speichern
nicht und ersetzen keine vollständige Barrierefreiheitsprüfung.
**„Standardfarben laden“** setzt Eingaben und Vorschau zurück; erst
**„Gestaltung speichern“** übernimmt die Änderung. Ohne JavaScript bleiben
Hex-Eingaben und serverseitiges Speichern nutzbar.

## Würfelbühne und Spielprotokoll

Im Frontend und in der Kampagnenverwaltung stehen W4, W6, W8, W10, W12, W20
und W100 zur Verfügung, soweit sie im Regelwerk erlaubt sind. Beispiele:
`3W6 + 2`, `W20 + 2W6 - 1` oder `D100`. Erlaubt sind höchstens zehn
addierte Würfelgruppen mit insgesamt 100 Würfeln sowie ein abschließender
ganzzahliger Modifikator zwischen −1000000 und 1000000. Negative Würfel,
Multiplikation, Erfolgszählung und regelwerksabhängige Sondermechaniken sind
noch nicht enthalten. Ohne erlaubte Würfel ist die Kampagne würfellos.

Optional wird für einen eigenen, bereits freigegebenen Charakter gewürfelt.
Spielleiter können auch Charaktere anderer Kampagnenmitglieder auswählen.
Ein Zahlen- oder Attributfeld lässt sich als unveränderter Wert addieren;
Systemboni werden nicht automatisch abgeleitet und Ressourcen nicht verbraucht.
Offene Änderungsanträge beeinflussen den verwendeten gültigen Wert nicht.
Veraltete Regel- oder Charakterstände werden abgelehnt.

Spielleiter legen im Regelwerk die erlaubten Sichtbarkeiten für neue Würfe fest:

- **Gruppe:** alle aktuellen Kampagnenmitglieder, niemals Besucher im Internet.
- **Privat:** Würfelnder, Besitzer des ausgewählten Charakters und Spielleiter.
- **Verdeckt:** ausschließlich Spielleiter, auch wenn ein Spieler würfelt.
  Der Spieler erhält nur eine Speicherbestätigung, kein Ergebnis.

Ohne Auswahl ist Würfeln deaktiviert. Bestehende Kampagnen erlauben zunächst
Gruppe und Privat; verdeckte Proben müssen ausdrücklich freigegeben werden.
Eine Änderung dieser Auswahl verändert ältere Würfe nicht. Aktuelle
Spielleiter und Administratoren können alle Kampagnenwürfe lesen. Entfernte
Mitglieder verlieren den Zugriff auch auf eigene ältere Würfe.
Bei Gruppenwürfen werden auch der ausgewählte Charaktername und Attributwert
mit der Gruppe geteilt; darauf weist das Formular hin.

Jeder Wurf wird serverseitig ausgewertet und vor der Ergebnisanzeige als
privater Protokolleintrag gespeichert. Einzelwerte, Modifikator, Gesamtwert,
Sichtbarkeit, ausführender Benutzer, Regelrevision und gegebenenfalls Charakter-
und Attributstand bleiben nachvollziehbar. Spätere Änderungen schreiben alte
Würfe nicht um. Das gemeinsame Protokoll zeigt 20 berechtigte Ereignisse pro Seite.
Bearbeiten und Löschen von Würfen ist in der Oberfläche nicht vorgesehen.

Die Würfelbühne verwendet leichtgewichtige SVG-Formen und eine Vanilla-JavaScript-
gesteuerte CSS-Wurfanimation, keine 3D-Physik oder externe Spieleplattform.
Kampagnenfarben werden übernommen. Pro Ergebnis werden höchstens zwölf Würfel
animiert; sämtliche Einzelwerte bleiben in den Details erhalten. Die Animation
verändert keine Zahlen und berücksichtigt `prefers-reduced-motion`.
Ohne JavaScript bleiben Formular, Ergebnis und Protokoll nutzbar.
Neuladen einer Ergebnisseite erzeugt keinen weiteren Wurf.

## Gemeinsames Ereignisprotokoll

Unter **Kampagne → Spielprotokoll** stehen Würfe und neue Änderungen in einer
gemeinsamen chronologischen Ansicht im Frontend und Backend. Bereits vorhandene
Würfe bleiben ohne Kopieren oder Umschreiben sichtbar. Der Änderungsverlauf für
Charaktere und Regeln beginnt erst mit Version 0.7.0; frühere Aktionen werden
nicht nachträglich erfunden.

Erfasst werden Regelwerksänderungen, Charaktererstellung, direkte Änderungen
einschließlich Ressourcen sowie Freigabeanträge, Freigaben und Ablehnungen.
Jeder Änderungseintrag enthält Zeitpunkt, handelnden Benutzer, betroffenes
Objekt, Revisionen und unveränderte Vorher-/Nachher-Stände. Gültige Werte und
offene Anträge bleiben getrennt. Damalige Feldkennungen und Bezeichnungen werden
mitgespeichert; spätere Umbenennungen verändern die Historie nicht.

Regeländerungen sind wie das Regelwerk für die Kampagne sichtbar.
Charakteränderungen sehen ausschließlich der Charakterbesitzer und aktuelle
Spielleiter beziehungsweise Administratoren. Ein früherer Spielleiter behält
nicht allein durch seine damalige Bearbeitung Zugriff auf fremde Charakterdaten.
Für Würfe gelten weiterhin deren eigene Sichtbarkeiten. Entfernte Mitglieder
verlieren auch den Zugriff auf frühere Einträge; Seitenzahlen berücksichtigen
nur die jeweils berechtigten Ereignisse.

Einträge werden vor der Änderung zunächst vollständig als nicht lesbare
Entwürfe vorbereitet und erst nach erfolgreicher Speicherung veröffentlicht.
Auch neue Charaktere bleiben bis zur Speicherung ihres gültigen Datenstands
beziehungsweise Freigabeantrags verborgen.
Bei erkannten Speicherfehlern wird abgebrochen beziehungsweise die eigene
Änderung revisionsgeschützt zurückgenommen. Hat inzwischen jemand weiter
bearbeitet, wird dessen Stand nicht überschrieben: Eine ausdrückliche
Fehlermeldung fordert die Administratorprüfung an; der vorbereitete Eintrag
bleibt als Entwurf erhalten. Bei einem Prozessabbruch zwischen den
Speicherschritten kann ebenfalls eine solche Prüfung nötig sein. Dies ist
keine datenbankübergreifende Transaktionsgarantie und kein manipulationssicheres
Audit-System; Datenbanksicherungen bleiben erforderlich.

Es gibt keine Oberfläche zum Überschreiben alter Protokolleinträge. Eine neue
Korrektur erzeugt ein neues Ereignis. Szenenereignisse folgen mit dem Story Builder;
Mitgliederverwaltung und Style-Änderungen werden derzeit noch nicht protokolliert.

## Charaktere und Bearbeitungsrechte

Nach dem Speichern des Regelwerks können Charaktere aus der Kampagnenvorlage
erstellt werden. Textfelder, Attribute und Ressourcen werden serverseitig
validiert. Ressourcen besitzen einen aktuellen Wert und ein persönliches
Maximum innerhalb des vorgegebenen Wertebereichs. Charakterbögen sind zunächst
nur für ihren Besitzer und die Spielleiter sichtbar.

Der Spielleiter wählt im Regelwerks-Editor pro Kampagne zwischen:

- **Mit Freigabe** (Voreinstellung): Spieler erstellen und bearbeiten eigene
  Charaktere. Name und sämtliche Werte werden als Antrag gespeichert; der
  gültige Stand bleibt bis zur Freigabe unverändert.
- **Direkt:** Spieler erstellen und bearbeiten eigene Charaktere unmittelbar.
- **Nur Spielleiter:** Spieler können ihre Charaktere lesen, aber nicht
  erstellen oder bearbeiten.

Spielleiter können für Kampagnenmitglieder Charaktere anlegen und deren Werte
unmittelbar korrigieren. Sie sehen gültigen Stand und offenen Antrag getrennt
und können Anträge freigeben oder verwerfen. Ein verworfener Antrag verändert
keinen bereits gültigen Stand. Bei einer abgelehnten Erstanlage bleibt ein
Charakter ohne freigegebenen Stand zurück, der erneut bearbeitet werden kann.
Das Bearbeiten eines Antrags ersetzt ihn beim Speichern; eine sofortige
Speicherung ersetzt auch einen eventuell offenen Antrag.

Ein Wechsel des Bearbeitungsmodus genehmigt oder verwirft offene Anträge nicht
automatisch. Die aktuell gespeicherte Option wird bei jedem Schreibzugriff
serverseitig geprüft. Veraltete Charakterstände und veraltete Regelwerksstände
werden beim Speichern abgelehnt. Neue Änderungen werden im Ereignisprotokoll
festgehalten. Punktepool und feldweise Rechte sind noch nicht implementiert.

## Regelwerk und mobile Bedienung

Jede Kampagne besitzt einen eigenen Regelwerks-Editor für Administratoren und
zugeordnete Spielleiter. Mitglieder können das gespeicherte Regelwerk lesen.
Konfigurierbar sind:

- Name und Beschreibung des Regelwerks sowie erlaubte Würfel
  (W4, W6, W8, W10, W12, W20, W100); keine Auswahl erlaubt würfellose Systeme.
- Bis zu 30 Charakterfelder: kurze und lange Texte, Zahlen, Attribute und
  Ressourcen, jeweils mit eigener Bezeichnung, Erläuterung und Pflichtfeldstatus.
- Ganzzahlige Minima, Maxima und Startwerte für numerische Felder.
- Reihenfolge der Felder; Kennungen bleiben beim Umbenennen und Umsortieren
  erhalten.

Punktepool und komplexe Würfelmechaniken folgen später. Neue Regeländerungen
sind mit Vorher-/Nachher-Ständen im Ereignisprotokoll nachvollziehbar.
Sobald Charaktere existieren, sind Änderungen
an Feldliste, Typen, Pflichtstatus und Wertebereichen vorläufig gesperrt, damit
gültige Stände und offene Anträge nicht unbemerkt ungültig werden. Bezeichnungen,
Reihenfolge, Startwerte für neue Charaktere und Bearbeitungsmodus bleiben
änderbar. Eine kontrollierte Übernahme struktureller Regeländerungen folgt
später. Veraltete Speichervorgänge werden anhand einer Revisionsnummer abgelehnt.

Die Verwaltungsoberfläche verwendet responsives CSS und Vanilla JavaScript
ohne zusätzliche Bibliotheken. Felder werden per Schaltfläche hinzugefügt,
entfernt und verschoben, nicht ausschließlich per Drag-and-drop. Beschriftete
Eingaben, mindestens 44 Pixel hohe Bedienelemente und einspaltige Anordnung auf
schmalen Ansichten unterstützen die Einrichtung auf Handy und Tablet.
Ohne JavaScript bleiben vorhandene Felder und allgemeine Regeln bearbeitbar;
Änderungen an der Feldliste erfordern JavaScript.

## Übersetzungen

Textdomain: `ps-pen-paper`, lokale Kataloge: `languages/ps-pen-paper-<locale>.po`
und `.mo`, beispielsweise `ps-pen-paper-en_US.mo`. Deutsche Originaltexte
bilden die Rückfallsprache; eine fertige englische Übersetzung liegt noch nicht bei.
Der POT-Katalog lässt sich mit dem vorhandenen WP-CLI-i18n-Werkzeug erneuern:

```sh
wp i18n make-pot . languages/ps-pen-paper.pot --slug=ps-pen-paper --domain=ps-pen-paper --exclude=INSPIRATION,tests,docs,languages
```

JavaScript erhält seine UI-Texte bereits übersetzt aus PHP über Datenattribute.
Spieler- und Spielleiterinhalte wie Namen, Regeln und Geschichten bleiben eigene
Inhalte und werden nicht durch gettext übersetzt.

## Entwicklungstest

`tests/character-contexts-integration.php` prüft Basisvorlagen mit stabilen
Feldkennungen, bestätigte Übernahmen, gemeinsame und unabhängige Charakterstände,
lokale Zahlenauflagen, effektive Würfel-Snapshots, Revisionen, private Welt- und
Charakterdaten, beschädigte Kontexte und Rücknahme bei Protokollfehlern.
Auch diese Prüfung erstellt und entfernt ausschließlich eigene temporäre Daten.
Ausführung wie bei den übrigen Integrationstests, beispielsweise:

```sh
wp --url=https://example.test/ --skip-plugins --skip-themes eval-file wp-content/plugins/pen-and-classicpress/tests/character-contexts-integration.php
```

`tests/universes-integration.php` prüft Universumsrechte, geschützte Verzeichnisse
und Historien, Vorschau ohne Schreibzugriff, bestätigte Zuordnung und Lösung,
Revisionskonflikte, unveränderte Kampagnendaten und Rücknahme bei Protokollfehlern.
Auch diese Prüfung erstellt und entfernt ausschließlich eigene temporäre Daten.

`tests/campaigns-integration.php` kann auf einer Entwicklungswebsite mit WP-CLI
und `--skip-plugins --skip-themes` ausgeführt werden. Der Test erstellt eigene
temporäre Benutzer, Kampagnen und Charaktere und entfernt sie anschließend. Er prüft
Kampagnenrechte, Regelvalidierung, stabile Feldkennungen, alle drei
Charakter-Bearbeitungsmodi, Freigaben, Besitzerrechte und Konflikte bei
veralteten Speicher- oder Freigabeanfragen. Zusätzlich prüft er
Frontend-Zugriffsrechte, Formular-Rücksprünge, die optionale Seitenvorlage,
Farbvalidierung, kampagnenbezogene Gestaltung und das Zurücksetzen der Farben.

`tests/dice-integration.php` wird auf dieselbe Weise ausgeführt. Er prüft
Würfelnotation und Grenzen, Einzelwerte und Gesamtergebnis, freigegebene
Attribut-Snapshots, Sichtbarkeiten, Kampagnentrennung, Speicherfehler,
Protokollseiten und die Begrenzung animierter Würfel. Auch dieser Test entfernt
seine temporären Benutzer und Inhalte anschließend.

`tests/events-integration.php` prüft auf dieselbe Weise Vorher-/Nachher-Stände,
Ressourcen und Freigabeentscheidungen, gemeinsame Anzeige vorhandener Würfe,
historische Feldbezeichnungen, Besitzerrechte einschließlich entzogener
Spielleiterrollen, berechtigungsabhängige Seitennavigation sowie Speicherfehler
mit Rücknahme und Schutz gleichzeitiger Änderungen. Auch dieser Test räumt
seine eigenen temporären Daten auf.

`tests/table-integration.php` prüft Tischrechte, freiwillige Anwesenheit,
AFK/Rückkehr, Verbindungsablauf, Zugkonflikte und Historie sowie kampagnenbezogene
Chat-Moderation und Rechteentzug. Für die Chat-Schnittstellentests muss PS Chat
als benachbartes Plugin installiert sein; es muss dafür nicht aktiviert werden.
Auch diese Prüfung entfernt ausschließlich ihre eigenen Testdaten.

`tests/access-integration.php` prüft geschlossene Standardwerte, die ausdrückliche
Teaserfreigabe ohne interne Daten, Anträge und Spielleiterentscheidungen,
geschützte Bilddaten, Revisionskonflikte und den Zugriff eines Netzwerk-Superadmins
ohne Mitgliedschaft auf der Unterwebsite. Temporäre Benutzer, Kampagnen,
Protokolle und Bilder werden anschließend entfernt.

Die unterstützten Versionen von WordPress, ClassicPress und PHP werden noch
festgelegt und getestet.

Die geplanten Funktionen und offenen Entscheidungen stehen in der
[Roadmap](ROADMAP.md).
