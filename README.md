# PS Pen & Paper

PS Pen & Paper entsteht als systemunabhängiger Baukasten für Pen-and-Paper-
Kampagnen auf WordPress und ClassicPress.

## Handbuch und Demorunde

- [Pluginhandbuch mit Screenshots](docs/handbuch.md): Einrichtung, Rollen,
  Charaktere, Freigaben, Würfel, Ressourcen, Farben und bekannte Grenzen.
- [Durchgespielte Demokampagne](docs/demokampagne.md): echte Würfelergebnisse,
  getrennte Rollen und ergänzende Prüfungen von Universen und Kartentisch.

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
Ein erster Szeneneditor mit geschütztem Kartentisch ist seit Version 0.12.0
vorhanden. Seit 0.13.0 ergänzen Kapitel, geschützte Text-/Bild-Handouts und eine
private Spielleitermappe die Abenteuerverwaltung.
Version 0.14.0 ergänzt private Charakterporträts und Bogenhintergründe sowie
PS-Community-gebundene Profilansicht, Gegenstandskataloge und Ausrüstung.
Version 0.15.0 verbindet die vorhandenen Story-Werkzeuge zu einem privaten
Abenteuerplan mit Kapitelreihenfolge, Szenenstatus und szenenbezogener Regie.
Version 0.16.0 ergänzt einstellbare Währungen mit ganzzahligen Guthaben,
protokollierte Spielleiterbuchungen und eingefrorene Gegenstandsbindungen.
Version 0.17.0 ergänzt private Gegner/NPCs, bewusste Universumsaufnahme
und unabhängige Kampagnenkopien wiederverwendbarer Vorlagen.
Version 0.18.0 ergänzt kampagnenbezogene Beutetabellen, eingefrorene
Würfelvorschläge und ausdrücklich bestätigte, einmalige Gegenstands-/Geldvergaben.
Version 0.19.0 ergänzt ein optionales öffentliches PS-Wiki-Weltlexikon mit
bewusster Einrichtung, getrennten Artikeltexten und bestätigter Veröffentlichung.
Version 0.20.0 ergänzt einstellbare Geschenke, Verkäufe und Tauschangebote
innerhalb einer Kampagne mit beiderseitiger Zustimmung und optionaler
zusätzlicher Spielleiterfreigabe.

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

## Konfigurierbarer Handel seit Version 0.20.0

Mit aktivem PS Community stellt die zuständige Spielleitung unter
**Handelsregeln** Geschenk, Verkauf und Tausch unabhängig voneinander ein.
Standardmäßig ist keine Art aktiviert; eine zusätzliche Spielleiterfreigabe
ist voreingestellt. Universumsregeln werden unverändert übernommen,
eigenständige Kampagnen konfigurieren ihre Regeln lokal.

Besitzer bieten am eigenen Charakter ein handelbares, aktiv wie beantragt
abgelegtes Exemplar einem anderen Besitzer derselben Kampagne an.
Ein Verkauf vereinbart einen positiven ganzzahligen Preis; beim Tausch
wählt der Empfänger sein konkretes Gegenexemplar, bevor der Anbieter den
exakten Tausch bestätigt. Die Spielleitung ersetzt keine Besitzerzustimmung.
Empfänger werden nur mit freigegebenem Charakternamen angeboten; fremde Bögen,
weitere Inventargegenstände und Guthaben bleiben privat.

Offene Angebote reservieren Exemplare, nicht Guthaben. Beim Abschluss werden
Mitgliedschaft, Regeln, Bindung, Bestände, Kapazität und aktuelle Konten erneut
geprüft. Beide Inventare einschließlich offener Ausrüstungsanträge, eventuelle
Geldbuchungen und Pflichtereignisse werden gemeinsam gespeichert oder
zurückgenommen. UUID und eingefrorener Gegenstandsstand bleiben erhalten;
empfangene Gegenstände werden nicht automatisch ausgerüstet. Verkäufe können
auch die ausdrücklich eingebundenen gemeinsamen Basisguthaben verwenden.

Zurückziehen, Ablehnen und Spielleiterablehnung geben Reservierungen frei.
Neue Handelsregeln sperren den Abschluss alter Angebote; deren Ablehnung
bleibt möglich. Ohne Community bleiben Daten und lesbare Handelsnachweise
erhalten, wirtschaftliche Änderungen jedoch gesperrt. Pakete, Mengen,
reine Geldgeschenke und kampagnenübergreifender Handel fehlen noch.

## Öffentliches Weltlexikon seit Version 0.19.0

Unter **Universen → Regelwerk & Basischaraktere → Öffentliches Weltlexikon**
kann die Universumsleitung PS Wiki optional anbinden. Einrichtung,
Kategorienänderung und Pausieren benötigen zusätzlich die nativen
PS-Wiki-Veröffentlichungs- und Kategorienrechte, beispielsweise durch die
Administration. Die ausdrückliche Aktivierung erlaubt der Universumsleitung
nur die Artikel dieses Lexikons über den Adapter zu pflegen; sie vergibt
keine allgemeinen Wiki-Rechte oder neuen Rollen.

Der öffentliche Lexikonname ist unabhängig vom privaten Universumsnamen.
Einrichten erzeugt eine Hauptkategorie und sechs benennbare Unterkategorien:
Personen, Gegner, Orte, Fraktionen, Gegenstände und Regeln. **Kategorienamen
sind sofort öffentlich, auch ohne veröffentlichte Artikel.**

Bestätigte Universums-NPC-/Gegnervorlagen können stabil mit einem Artikel
verknüpft werden; freie Welt-/Regelartikel benötigen keine Figur.
Titel und Text werden ausdrücklich separat eingegeben. Es werden keine
Geheimnisse, internen Beschreibungen, Bilder, Eigenschaften, Werte oder
Beutedaten automatisch kopiert. Reiner Text führt keine Shortcodes aus.
**Artikelentwurf vorbereiten** legt zunächst einen nativen Wiki-Entwurf an.
Auch dort gehören ausschließlich zur Veröffentlichung bestimmte Inhalte hinein:
Wiki-Redakteure können ihn nach den eigenen Wiki-Rechten bearbeiten/veröffentlichen.
Eine eigene Bestätigung veröffentlicht den vorbereiteten Stand über den Adapter.

Vorbereitete Änderungen lassen eine bestehende öffentliche Fassung unverändert.
Direkte redaktionelle Wiki-Änderungen sperren die Synchronisierung, statt
Inhalte zu überschreiben; solche Artikel anschließend im Wiki weiterpflegen.
Umbenennen oder erneutes Anbieten derselben Figur erzeugt keine zweite Zuordnung.
Wiki-Änderungen verändern keine Spielmechanik.

Kategorien, Artikel, Zuordnungen und erforderliche Ereignisse werden innerhalb
derselben geprüften Transaktion gespeichert. Bei dieser Integration müssen
auch die vier beteiligten WordPress-Taxonomietabellen InnoDB unterstützen.
Native PS-Wiki-Benachrichtigungen werden erst nach erfolgreichem Commit ausgelöst.
**Pausieren oder Deaktivieren nimmt keine Veröffentlichung zurück.**
Veröffentlichte Artikel und deren Revisionen gehören zum öffentlichen Wiki.
Private Kampagnen-Wikis, automatische Übernahme aller Figuren, zusätzliche
Kategorieebenen und Verknüpfungen zu einzelnen Szenen/Feldern bleiben Ausbauziele.
Ohne PS Wiki bleiben Kernsystem und gespeicherte Zuordnungen erhalten;
Wiki-Schreibvorgänge sind gesperrt.

## Beutetabellen seit Version 0.18.0

Unter **Spielleitermappe → Beutetabellen** legt die Kampagnenleitung einen
erlaubten Standardwürfel und bis zu 30 lückenlose, überschneidungsfreie
Ergebnisbereiche fest. Jeder Bereich liefert keine Beute, einen Gegenstandstyp,
Geld oder beides. Mengen und Beträge sind ganze Zahlen oder erlaubte
Würfelausdrücke wie `2W6`. Alle möglichen Ergebnisse müssen in den Grenzen
liegen: Gegenstandsmengen 1–100, Geld 0–999999999; ohne Gegenstand gilt Menge 0.
Geldbeute benötigt eine eingerichtete Währung, Änderungen aktives PS Community.

**Beutevorschlag auswürfeln** speichert Tabellenstand, Gegenstand samt Bindung,
Währung und alle Einzelwürfe. Neuladen und dieselbe wiederholte POST-Anfrage
würfeln nicht erneut. Die Spielleitung wählt anschließend einen Empfänger
für die gesamte Beute und bestätigt die Vergabe. Erst dann entstehen konkrete,
nicht automatisch ausgerüstete Exemplare und/oder eine Guthabenbuchung.
Inventar, Guthaben, Vergabezustand und Pflichtereignisse werden gemeinsam
gespeichert oder zurückgenommen. Ein abgeschlossener Vorgang kann nicht
erneut auszahlen; ein bewusst neu gestarteter Wurf ist ein eigener Vorgang.
Die bisherigen Kontorechte gelten auch bei gemeinsamem Universumsguthaben.

Tabellen und Vorschläge bleiben ausschließlich für die Kampagnenleitung
sichtbar. Empfänger sehen ihre tatsächlichen Gegenstände und Buchungen, nicht
geheime Chancen oder Begründungen. Gegner/NPCs können auf eine lokale Tabelle
verweisen; Universumsvorlagen übernehmen diese Kampagnenreferenz nicht.
Bestehende Vorschläge bleiben bei späteren Tabellenänderungen unverändert.

Noch nicht enthalten: Gegenstandsgruppen, verschachtelte Tabellen,
universumsweite Beutekataloge, Aufteilung auf mehrere Empfänger und automatische
Vergabe. `W3` ist kein unterstützter Standardwürfel. Handel besteht separat
seit Version 0.20.0.

## Gegner und NPCs seit Version 0.17.0

Unter **Spielleitermappe → Gegner und NPCs verwalten** erstellt die
Kampagnenleitung strukturierte Figuren: Name, Beschreibung, geschütztes Bild,
getrennte Geheimnisse, freie Texteigenschaften und ganzzahlige Werte.
Eine Szenenverknüpfung zeigt die Figur zusätzlich in der privaten Regie des
Abenteuerplans. Vorhandene Personenkarten bleiben unveränderte Erzählnotizen.
Es gibt keine fest vorgeschriebenen Attribute, Klassen oder Kampfabläufe.

**Ins Universum aufnehmen** teilt bewusst den gespeicherten Stand samt Bild,
Werten und Geheimnissen. Leitet der Ersteller nicht auch das Universum,
entsteht zunächst ein privater Vorschlag. Die Universumsleitung prüft und
bestätigt ihn unter **Universen → Regelwerk & Basischaraktere → Gegner & NPCs**.
Erst dann können Spielleitungen anderer zugeordneter Kampagnen die Vorlage
sehen und als eigenständige Figur übernehmen. Die Universumsleitung kann
auch direkt Vorlagen anlegen. Erneutes Anbieten derselben lokalen Figur
erzeugt keine zweite Vorlage, auch nicht nach einer Umbenennung.

Kopien übernehmen einen eingefrorenen Stand einschließlich eigenständiger
Bildkopie, aber keine Szenenzuordnung. Lokale Verletzungen oder andere
Werteänderungen beeinflussen weder Vorlage noch weitere Figuren.
Vorlagenänderungen werden nicht automatisch nachgezogen. Nach dem Lösen
einer Universumszuordnung entfällt der Katalogzugriff; lokale Kopien bleiben.
**Figuren und Vorlagen neu laden** holt bewusst den aktuellen Stand.

Figuren, Aufnahmevorschläge und ihre Historie bleiben für Spieler und Gäste
gesperrt. Änderungen und Pflichtereignisse werden gemeinsam gespeichert
oder zurückgenommen. Die Figurenverwaltung funktioniert ohne PS Community
und PS Wiki. Lokale Beutetabellen sind seit 0.18.0 optional verknüpfbar;
automatische Kämpfe sind noch nicht implementiert. Seit 0.19.0 lassen sich
bestätigte Universumsvorlagen bewusst mit öffentlichen Wiki-Artikeln verbinden.

## Währung und Gegenstandsbindung seit Version 0.16.0

Bei aktivem PS Community stellt die Universumsleitung unter **Universen →
Regelwerk & Basischaraktere → Währung & Guthaben** Name, Kürzel und Kontomodell
ein. Die Administration darf diese Einstellungen ebenfalls verwalten:

- **Getrennt je Kampagnencharakter:** Auch bei gemeinsamen Basiswerten hat
  jede Runde ein eigenes Guthaben. Es bucht die jeweilige Kampagnenleitung.
- **Gemeinsam am Universumscharakter:** Aufgenommene gemeinsame Basisbögen
  zeigen dasselbe Konto. Nur Universumsleitung und Administration dürfen
  buchen; eine Kampagnenleitung erhält dadurch keine universumsweiten Rechte.
  Lokale Bögen und unabhängige Kopien bekommen in diesem Modell kein
  stillschweigendes Ersatzkonto.

Kampagnen ohne Universum konfigurieren ihre eigene Währung unter
**Rundeneinstellungen** und führen getrennte Konten. Zugeordnete Kampagnen
übernehmen die Währungseinstellungen des Universums ohne lokale Abweichung.
Nach der ersten Buchung ist das Kontomodell fest; Name und Kürzel bleiben
änderbar. Eine andere Universumszuordnung bestehender Kampagnenkonten
benötigt eine noch nicht implementierte Migration und wird ausdrücklich gesperrt.

Im privaten Charakterbogen beziehungsweise Community-Profil steht das
**Guthaben**. Die zuständige Spielleitung kann Beträge vergeben oder abziehen,
mit Begründung und Bestätigung. Es gelten ausschließlich ganze Einheiten von
0 bis 999999999 als Kontostand, keine Nachkommastellen oder Überziehung.
Revisionsprüfungen verhindern wiederholte Buchungen veralteter Formulare;
Guthaben, gegebenenfalls die erstmalige Festlegung des Kontomodells und
Pflichtereignisse werden gemeinsam gespeichert oder zurückgenommen.

Kampagnenbuchungen sind privat im jeweiligen Protokoll sichtbar, für Besitzer
und aktuelle Kampagnenleitung. Gemeinsame Konten werden im Universum
protokolliert; diesen Verlauf verwaltet die Universumsleitung. Historische
Buchungen behalten die damaligen Währungsbezeichnungen und Begründungen.

Gegenstandsvorlagen besitzen **Charaktergebunden** oder **Handelbar**. Jede
neue Ausgabe friert diese Auswahl im konkreten Exemplar ein. Ändern der
Vorlage verändert keine ausgegebenen Bindungen. Ältere interne
Demo-Snapshots ohne Bindungsfeld werden gebunden gelesen, nicht umgeschrieben.
Die gesondert aktivierbare Handelsfunktion seit 0.20.0 berücksichtigt
diese eingefrorene Bindung.
Ohne verwendbares PS Community sind wirtschaftliche Änderungen gesperrt;
gespeicherte Guthaben und Gegenstände bleiben erhalten.

## Charaktergestaltung und PS Community seit Version 0.14.0

Charakterporträts und dekorative Kampagnen-Bogenhintergründe gehören zum Kern.
Porträts sind nur für Besitzer und Spielleitung sichtbar, nicht als öffentliche
Benutzeravatare oder Bilder in der gemeinsamen Teilnehmerleiste.
Spieleränderungen folgen der Charakterpolitik: Freigabe, direkt oder nur
Spielleitung. Porträtanträge bleiben von Werteanträgen getrennt.
Den Bogenhintergrund richtet die Spielleitung unter **Rundeneinstellungen** ein.
Er wird dezent hinter dem gültigen Bogen dargestellt. Geschützte PNG-/JPEG-/WebP-
Bilder bis 2 MiB und 3000 × 3000 Pixel liegen ohne öffentliche Mediathek-URL
in der Datenbank. Historische Bilder bleiben bis zur Löschung ihres Ursprungs
erhalten; kleine Dateien und Datenbanksicherungen sind wichtig.

Mit aktivem **PS Community** und geladenen Profil-Tab-Schnittstellen erscheint
im **eigenen** Profil der Tab **Pen & Paper**. In der lokalen Community-Version
benötigt das die Module Profil und Aktivität sowie eine veröffentlichte,
in Community zugeordnete Profilseite. Dort wählt der Besitzer seinen
Kampagnencharakter, bearbeitet den Bogen und verwaltet Porträt und Ausrüstung.
Formulare werden direkt auf der Profilseite verarbeitet; eine Backend-Freigabe
ist dafür nicht nötig. Fremde Profilaufrufe und native Community-AJAX-Aufrufe
erhalten keine fremden Bögen. Freundschaften vergeben keine Kampagnenrechte.
Auch die Profilseite einschließlich ihrer Parameter vom Seiten-Cache ausschließen.

**Inventar, Gegenstandskatalog und Ausrüstung benötigen PS Community.**
Ohne diese Integration bleiben Daten erhalten, Bestandsänderungen gesperrt
und Ausrüstungsboni inaktiv. Charaktere, Würfel, Porträts und Geschichten
funktionieren weiter. Der optionale Handel benötigt ebenfalls PS Community.

Die Spielleitung konfiguriert unter **Gegenstände**:

- Bis zu 30 Slots mit stabiler Kennung, eigenem Namen und passendem
  Gegenstandstyp, etwa `kopf | Kopf | kopfbedeckung`.
- Bis zu 30 frei benannte Seltenheitsstufen mit Farbe und Text. Eine Stufe wie
  „Episch“ erzeugt selbst keine Multiplikatoren oder anderen Regeln.
- Gegenstandsvorlagen mit Beschreibung, geschützter Grafik, freien
  Eigenschaften und ausdrücklich eingetragenen ganzzahligen Boni.
- Einen gemeinsamen Universumskatalog und ergänzende lokale Kampagnenvorlagen.
  Kampagnen konfigurieren ihre Slots selbst; ohne Universum gilt nur ihr Katalog.

Über den jeweiligen Charakter gibt die Spielleitung **konkrete Exemplare**
aus. Jedes Exemplar behält seinen damaligen Vorlagenstand; spätere
Vorlagenänderungen verändern ausgegebene Gegenstände nicht.
Ein Charakter besitzt zunächst höchstens 100 einzelne Exemplare.
Ein Exemplar darf genau einen passenden Slot belegen. Slotauswahl, Besitz
und Freigabepolitik werden auf dem Server geprüft.

Angelegte, freigegebene Boni werden auf Zahlen-/Attributfelder addiert, ohne
Grundwerte zu überschreiben. Bogen und Würfe verwenden dieselben effektiven
Werte; Wurfergebnisse speichern die damaligen Boni und Revisionen.
Feldgrenzen gelten auch nach Addition. Unzulässige Ausrüstung, Grundwert-
oder Auflagenänderungen werden abgelehnt. Führt ein späterer gemeinsamer
Basisfortschritt zu einem unzulässigen lokalen Ausrüstungsstand, erscheint
ein Fehler statt stiller Kürzung oder erfundener Werte. Im eigenen Profil
bleibt **Ausrüstung prüfen** erreichbar: Besitzer beantragen das Ablegen,
die Spielleitung kann es freigeben oder unmittelbar korrigieren.

Schema- und Vorlagenhistorie bleiben der Spielleitung vorbehalten;
Inventar- und Porträthistorie sehen nur Besitzer und aktuelle Spielleitung.
Es gibt keine Stapelmengen, automatische Gegenstandssynchronisierung,
Mehrfachslot-Belegung, Lösch-/Entzugsoberfläche oder frei positionierbaren
grafischen Slotdesigner. Die erste Slotoberfläche verwendet zugängliche Auswahlfelder.

## Digitaler Spieltisch

### Abenteuerplan und Spielleitung seit Version 0.15.0

Unter **Abenteuer & Szenen** sieht die Spielleitung Kapitel mit ihrer gespeicherten
Szenenfolge, Szenen ohne Kapitel sowie den Stand **Privater Entwurf**,
**Freigegeben, nicht aktiv** oder **Jetzt am Tisch**. Die auf die aktive Szene
folgende Szene wird innerhalb jedes zugeordneten Kapitels hervorgehoben.
Das ist eine Orientierung, kein automatischer Abenteuerablauf.

**Vorbereiten / Vorschau / Freigabe** öffnet den bisherigen Szeneneditor;
**Am Tisch anzeigen** wechselt nur zur bereits freigegebenen Fassung. Weichen
Entwurf und Spielstand voneinander ab, weist der Plan darauf hin. Dazu können
auch zwischenzeitlich bewegte Marker gehören. Eine Auswahl veröffentlicht
keinen neueren Entwurf und gibt keine verbundenen Handouts frei.

**Aktuelle Szene leiten** bündelt die gespeicherten Geheimnisse sowie direkt
mit dieser Szene verknüpfte Personen, Orte, Notizen und Handouts. Ein noch
privates Handout lässt sich dort nach ausdrücklicher Bestätigung auf den Tisch
legen. Überarbeitungen und Rücknahmen bereits veröffentlichter Handouts bleiben
in der Spielleitermappe. Spieler erhalten weder den Plan noch dessen private
Titel, Reihenfolge oder Geheimnisse.

Der Plan wird beim Laden aufgebaut. Nach Änderungen durch weitere
Spielleitungen **Abenteuerplan und Notizen neu laden** verwenden.

### Spielleitermappe und Handouts seit Version 0.13.0

**Spielleitermappe** im Frontend oder in der Backend-Kampagnenverwaltung sammelt
Kapitel, Personen, Orte, Notizen und Handouts. Einträge sind kampagnenbezogen,
revisionsgeschützt und ausschließlich für die Spielleitung sichtbar.
Kapitel besitzen eine sortierbare Szenenfolge; Verknüpfungen zu Kapiteln und
Szenen geben weder Szenen noch andere Einträge automatisch frei.
Personen und Orte sind erzählerische Karteikarten, kein neues Kampfregelsystem.

Nur **Handouts** lassen sich nach privater Vorschau und Bestätigung für die
gesamte Runde freigeben. Dabei werden ausschließlich Titel, Text und optionales
Bild übernommen, niemals Geheimnisse oder interne Verknüpfungen. Entwurfsänderungen
ändern den freigegebenen Stand erst nach erneuter Freigabe. Unter **Handouts**
bleiben Hinweise auch nach einem Szenenwechsel zugänglich, bis die Spielleitung
sie zurücknimmt. Spieler laden den Bereich für neue Freigaben oder Rücknahmen
neu; geöffnete Seiten und bereits gespeicherte Inhalte lassen sich nicht
nachträglich zurückholen.

Handoutbilder verwenden den bestehenden geschützten Bildspeicher: PNG/JPEG/WebP,
höchstens 2 MiB und 3000 × 3000 Pixel, ohne öffentliche Mediathek-URL.
Historische Bildstände bleiben bis zur Kampagnenlöschung erhalten. Beliebige
Dateien und PDFs, Einzelspielerfreigaben und ein vollständiger NPC-Editor
sind nicht enthalten. Mappenänderungen und Freigaben stehen mit privaten
Vorher-/Nachher-Ständen im Spielleiterprotokoll.

Neue Szenen beginnen ohne Raster. Ohne Bild, Raster und Marker bleibt nur
der Erzähltext sichtbar; die Kartenoptionen sind im Editor eingeklappt.
Die Karte ist eine optionale Hilfe, nicht das Zentrum des Spiels.

### Szenen und schlanker Kartentisch seit Version 0.12.0

Die Spielleitung öffnet **Abenteuer & Szenen** im Spielbereich oder in der
Backend-Kampagnenverwaltung. Jede Szene besitzt Titel, freizugebenden Text,
getrennte Geheimnisse und optional eine Karte. Der Ablauf ist bewusst getrennt:
**Entwurf speichern → private Inhaltsvorschau prüfen → gespeicherten Entwurf
freigeben → freigegebene Szene am Tisch anzeigen**. Änderungen am Entwurf
verändern nie automatisch einen bereits freigegebenen Stand.
Die Freigabe lässt sich zurücknehmen; alternativ kehrt die Gruppe zur
Rundeneinleitung zurück. Spieler können nur die aktuell ausgewählte,
weiterhin freigegebene Szene abrufen, nicht den übrigen Szenenvorrat.

Der Kartentisch bietet ohne zusätzliche Bibliothek:

- Geschützte PNG-/JPEG-/WebP-Karten bis 2 MiB und 3000 × 3000 Pixel;
  ohne Bild eine neutrale Kartenfläche.
- Zoomen, Verschieben, Einpassen und Zwei-Finger-Zoom; keine WebSocket-Pflicht.
- Bis zu 30 benannte Spieler-, NPC- oder Gegenstandsmarker mit wählbarer Farbe.
  Aktuelle Rundenteilnehmer dürfen nur ausdrücklich zugewiesene Marker bewegen,
  die Spielleitung alle. Marker sind keine öffentlich gemachten Charakterbögen.
- Bewegung durch Ziehen, Auswahl und Zielklick oder Prozentfelder. Die
  Positionsfelder sind auch über die Tastatur erreichbar. Ein optionales
  Quadratraster mit 10–500 Bildpixeln lässt Bewegungen einrasten; 0 schaltet es aus.
- Geteilte Karten-Pings mit serverseitig bestimmtem Absender, spätestens nach
  15 Sekunden abgelaufen. Pings werden nicht dauerhaft protokolliert.

Positionen bleiben gespeichert. Sichtbare Browserseiten fragen den Kartenstand
alle fünf Sekunden ab; das ist eine angenäherte Aktualisierung, kein
Echtzeitserver. Bilder werden nicht bei jedem Abruf erneut übertragen.
Konkurrierende Änderungen werden mit Revisionen abgelehnt statt überschrieben.
Eine Neufreigabe ersetzt den veröffentlichten Stand einschließlich seiner
vorbereiteten Startpositionen. Die Entwurfsvorschau zeigt den gespeicherten
Entwurf, nicht noch ungespeicherte Formulareingaben.

Kartenbilder liegen wie Hintergründe in privaten Datensätzen ohne öffentliche
Mediathek-URL. Alte Kartenbildstände bleiben für die Historie bis zum endgültigen
Löschen der Kampagne geschützt gespeichert; Uploads vergrößern die Datenbank.
Szenenvorbereitung, Freigabe, Rücknahme und Bewegungen werden mit geschützten
Vorher-/Nachher-Ständen für die Spielleitung protokolliert. Die Gruppe sieht
Szenenwechsel mit freigegebenem Titel, aber keine privaten Entwürfe oder Geheimnisse.

Noch nicht enthalten sind Zeichenwerkzeuge, Nebel des Krieges,
versteckte Marker, Entfernungsprüfung, Kollisionen und automatische Kampfregeln.
Alle veröffentlichten Marker sind für die Runde sichtbar. Eine Rücknahme
verhindert neue Abrufe, kann aber bereits betrachtete oder gespeicherte Inhalte
nicht nachträglich zurückholen.

Seit Version 0.9.0 trennt der Spielbereich die große Erzählfläche, die kompakte
Gruppenleiste und den vollwertigen Charakterbogen. Es ist jeweils ein
Arbeitsbereich sichtbar; die Runde bleibt erreichbar. Auf Handy und
Tablet passt sich die Anordnung an; schmale Ansichten wechseln zwischen
**Spiel**, **Mein Bogen**, **Würfel** und **Gruppe**. Regelwerk und Protokoll
werden separat geöffnet, Charaktereingaben erst bei Bedarf eingeblendet.
Spielleiter sehen auch fremde Bögen und Freigaben; Spieler weiterhin nur ihre
eigenen. Die gemeinsame Teilnehmerleiste verwendet weiterhin Buchstabenplaketten;
private Charakterporträts erscheinen nur auf berechtigten Charakterbögen.

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
Text-/Bild-Handouts werden getrennt über die Spielleitermappe verwaltet;
beliebige private Dateien werden weiterhin nicht unterstützt.

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

Mitgliederaufnahme und -entfernung sowie Beitrittsanfragen einschließlich
Annahme/Ablehnung werden nun ebenfalls erfasst. Ihre Rollen- und Antragsstände
sehen ausschließlich aktuelle Spielleiter und Administratoren, nicht die
betroffenen Spieler. Namen und Benutzerkennungen bleiben im damaligen
Protokollstand erhalten. Ein Rollenentzug entzieht auch den Zugriff auf diese
Historie. Frühere Mitgliedschaftsänderungen werden nicht nachträglich erfunden.
Manuelle Änderungen und Antragsentscheidungen aus Verwaltungsformularen prüfen
eine gemeinsame Mitgliederrevision; veraltete Formulare müssen neu geladen
werden. Ein erneut übermittelter bereits offener eigener Beitrittsantrag erzeugt
keinen zusätzlichen Eintrag.

Einträge werden vollständig als nicht lesbare Entwürfe vorbereitet und erst
innerhalb des gemeinsamen Speichervorgangs veröffentlicht.
Auch neue Charaktere bleiben bis zur Speicherung ihres gültigen Datenstands
beziehungsweise Freigabeantrags verborgen.
Die zentrale Dokument-/Ereignisspeicherung übernimmt Änderung und zugehörigen
Eintrag innerhalb derselben Datenbanktransaktion. Bei einem Fehler oder
Prozessabbruch vor dem Abschluss werden beide zurückgenommen; nach dem
Abschluss sind beide gespeichert. Dafür müssen die Beitrags- und Metadatentabelle
InnoDB verwenden und die Standard-Datenbankverbindung mit `mysqli` verfügbar
sein. Fehlen diese Voraussetzungen oder ist bereits eine fremde Transaktion
aktiv, wird die Änderung mit Fehlermeldung abgewiesen.
Ist die Bestätigung des Abschlusses nicht eindeutig, erscheint eine ausdrückliche
Aufforderung zur Administratorprüfung statt einer Erfolgsmeldung.
Dies ist keine Garantie für Mehrparteientransfers, externe Hook-Nebenwirkungen
oder mehrere Datenbanken und kein manipulationssicheres Audit-System;
Datenbanksicherungen bleiben erforderlich. Private vorbereitete Objekte oder
Bilder außerhalb der Transaktion können nach einem Abbruch eine Bereinigung
benötigen.

Es gibt keine Oberfläche zum Überschreiben alter Protokolleinträge. Eine neue
Korrektur erzeugt ein neues Ereignis. Szenenereignisse und Kartenbewegungen werden
seit Version 0.12.0 geschützt protokolliert. Style-Änderungen sowie Name,
Beschreibung und Archivstatus der Kampagne werden derzeit noch nicht protokolliert.

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

Lokale Änderungen an vorhandenen Charakterwerten, Freigaben, Kampagnenauflagen
und Ausrüstung verwenden eine gemeinsame Datenbanksperre je Charakter.
Konkurrierende Schreibvorgänge warten nicht, sondern melden einen Konflikt;
anschließend muss der aktuelle Stand neu geladen werden. Bereits gelesene
Charaktermetadaten werden nach dem Sperrerwerb aus dem Cache entfernt, damit
die Prüfung nicht mit einem früheren Ausrüstungsstand arbeitet.
Interne Schreibwege verwenden dafür `ps_pen_paper_save_character()`,
`ps_pen_paper_review_character()`, `ps_pen_paper_save_character_context()` und
`ps_pen_paper_save_gear()`, nicht deren `_locked`-Implementierungen oder direkte
Metadatenänderungen.

Diese Absicherung benötigt MySQL-/MariaDB-Verbindungssperren (`GET_LOCK` und
`RELEASE_LOCK`) auf der gemeinsamen Schreibverbindung. Bei nicht verfügbarer
Sperre wird nichts geschrieben; ein Freigabefehler wird ausdrücklich als
möglicherweise bereits gespeicherter Stand gemeldet. Datenbank-Drop-ins mit
getrennten Lese-/Schreibverbindungen sind dafür noch nicht geprüft.
Die Sperre ersetzt nicht die separate Transaktion der zentralen
Dokument-/Ereignisspeicherung und sichert keine Mehrparteientransfers ab.
Gemeinsamer Universumsfortschritt kann weiterhin eine lokale
Ausrüstungsanpassung erfordern.

`ps_pen_paper_save_document_event()` prüft die InnoDB-Tabellen, verweigert
fremde Transaktionen und verhindert automatische Wiederverbindungen während
der Speicherung. Die interne Funktion `ps_pen_paper_write_document_event()`
darf nicht direkt aufgerufen werden. Cache-Ergänzungen und Abfrage-Caches
bleiben innerhalb der Transaktion deaktiviert; nach Abschluss oder Rücknahme
werden die betroffenen Beitrags-Caches invalidiert. Externe Objekt-Caches
müssen die WordPress-Cache-Sperre respektieren. Ein Abbruch unmittelbar nach
dem Commit kann noch alte Cache-Stände hinterlassen; die Datenbank enthält
bereits Änderung und Ereignis zusammen. Fremde Hooks dürfen innerhalb der
Speicherung keine eigenen Commit-/DDL-Anweisungen ausführen und keine bereits
erfolgreiche Verarbeitung nach außen melden.

Mitgliederänderungen verwenden `ps_pen_paper_change_membership()`. Für manuelle
Aufnahme/Entfernung ist die aktuelle Mitgliederrevision erforderlich;
`ps_pen_paper_request_membership()` und `ps_pen_paper_review_membership()`
bleiben die Anfrageschnittstellen. Die vorhandenen Rollen-/Antragsmetadaten
bleiben kompatibel. `ps_pen_paper_atomic_event()` bündelt diese zusätzlichen
Schreibvorgänge mit dem Ereignis unter derselben Kampagnenzeilensperre und
prüft die Berechtigung erneut mit frischen Kampagnendaten.
Der interne Schreib-Callback liefert eine Ereignis-ID oder `0` ausschließlich
für eine idempotente Wiederholung ohne Änderung; ein Fehler wird als `WP_Error`
zurückgegeben. Direkte Rollen-Metadatenänderungen umgehen diese Historie und
sind kein unterstützter Schreibweg für neue Integrationen.

`tests/equipment-integration.php` prüft die Community-Sperre mit erhaltenen
Beständen, echte Community-Profil-Tab-Schnittstellen, private Porträts und
Freigaben, Vorlagenstände, Slot-/Besitzprüfungen, additive Boni und Grenzen,
Wurfherkunft, gemeinsame Basisbögen, reparierbare ungültige Ausrüstung sowie
Protokollrücknahme und Bildbereinigung. Zusätzlich prüft er ineinandergreifende
Charakter-/Ausrüstungs- und Auflagenänderungen, veraltete Metadaten,
Sperrkonflikte mit einer unabhängigen Datenbankverbindung und die Ablehnung
von Änderungen bei nicht verfügbarer Sperre. Der Test lädt die lokale
PS-Community-Profil-API; das Community-Plugin muss daneben installiert sein.
Ausführung wie bei den übrigen Integrationstests mit
`--skip-plugins --skip-themes eval-file`.

`tests/folio-integration.php` prüft die private Spielleitermappe, geordnete
Kapitel, kampagnenbezogene Verknüpfungen, ausdrückliche Handoutfreigaben,
geschützte Bildausgabe, unveränderte veröffentlichte Stände bei
Entwurfsänderungen, Revisionskonflikte, Protokollschutz und Fehler-Rücknahme.
Der Abenteuerplan wird auf aktive/nächste und nicht zugeordnete Szenen,
getrennte Entwurfsstände, fehlende Wechselaktionen für private Szenen,
bestätigte Handoutformulare und ausschließlich privaten Zugriff geprüft.
Gäste, Nichtmitglieder und entfernte Mitglieder erhalten keine Handouts.
Die Prüfung erstellt und entfernt ausschließlich eigene temporäre Daten.

`tests/economy-integration.php` prüft ganzzahlige Betragsgrenzen, Vergabe und
Abzug, fehlgeschlagene Erstbuchungen einschließlich Rücknahme der
Kontomodellfestlegung, Wiederholungs-/Revisionskonflikte, getrennte und
gemeinsame Konten, Universumsrechte, gemeinsame Basissperren, private
Historie und entzogene Mitgliedschaften. Die Ausrüstungsprüfung ergänzt
eingefrorene Bindungen und die Ablehnung ungültiger Bindungswerte.

`tests/npcs-integration.php` prüft private strukturierte Figuren, freie Werte,
geschützte Bildkopien, bestätigte Universumsaufnahme, private Vorschläge,
Schutz vor doppelten Angeboten, unabhängige Wiederverwendung, Szenenregie,
Rücknahme bei Protokollfehlern und entzogenen Katalogzugriff.

`tests/loot-integration.php` prüft vollständige Ergebnisbereiche, erlaubte
Mengen und Würfelgrenzen, eingefrorene Tabellen und Gegenstandsbindungen,
wiederholte Anfragen, bestätigte einmalige Vergaben, volle Inventare,
Sperrkonflikte, gemeinsame Kontorechte und private Historie.
Protokollfehler müssen Inventar, Guthaben, erste Kontomodellfestlegung und
Vergabezustand zusammen zurücknehmen. Temporäre Prüfdaten werden entfernt.

`tests/wiki-integration.php` lädt die echte lokale PS-Wiki-API und prüft
native Aktivierungsrechte, begrenzte Delegation, öffentliche Kategorien,
getrennte Artikeltexte, Entwürfe, ausdrückliche Veröffentlichung, stabile
Figurenzuordnungen und redaktionelle Konflikte. Fehler im Pflichtprotokoll
müssen auch Kategorien und native Wiki-Artikel zurücknehmen. Ergänzt werden
Schutz vor ausgeführten Shortcodes, unveränderte öffentliche Fassungen bei
vorbereiteten Änderungen, Pausieren und gesperrte Fremdzugriffe.
PS Wiki muss daneben installiert sein; temporäre Prüfdaten werden entfernt.

`tests/maps-integration.php` prüft Entwürfe, getrennte Geheimnisse, private
Kartenbilder, ausdrückliche Freigabe und Szenenauswahl, aktuelle Markerrechte,
persistente Positionen und tatsächliches Pixelraster-Einrasten, Revisionskonflikte,
Ping-Absender und Ablauf, Rücknahme sowie Rückabwicklung bei Protokollfehlern.
Auch diese Prüfung erstellt und entfernt ausschließlich eigene temporäre Daten.

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
mit transaktionaler Rücknahme. Zusätzlich prüft er parallele Leser, geschützte
Cache-Zugriffe, Ausnahmen, ungeeignete Speicher-Engines und fremde Transaktionen.
Er startet `tests/events-crash-worker.php` in separaten PHP-Prozessen, die vor
beziehungsweise nach dem Commit ohne normale Aufräumlogik enden; dafür muss
`proc_open` für CLI-PHP verfügbar sein. Der übergeordnete Test entfernt seine
eigenen temporären Daten anschließend.

`tests/table-integration.php` prüft Tischrechte, freiwillige Anwesenheit,
AFK/Rückkehr, Verbindungsablauf, Zugkonflikte und Historie sowie kampagnenbezogene
Chat-Moderation und Rechteentzug. Für die Chat-Schnittstellentests muss PS Chat
als benachbartes Plugin installiert sein; es muss dafür nicht aktiviert werden.
Auch diese Prüfung entfernt ausschließlich ihre eigenen Testdaten.

`tests/access-integration.php` prüft geschlossene Standardwerte, die ausdrückliche
Teaserfreigabe ohne interne Daten, Anträge und Spielleiterentscheidungen,
geschützte Bilddaten, Revisionskonflikte, atomare Rollen-/Antragsänderungen bei
Protokollfehlern, private Mitgliederhistorie und entzogene Spielleiterrechte
einschließlich veralteter Cache-Stände sowie den Zugriff eines Netzwerk-Superadmins
ohne Mitgliedschaft auf der Unterwebsite. Temporäre Benutzer, Kampagnen,
Protokolle und Bilder werden anschließend entfernt.

Die unterstützten Versionen von WordPress, ClassicPress und PHP werden noch
festgelegt und getestet.

Die geplanten Funktionen und offenen Entscheidungen stehen in der
[Roadmap](ROADMAP.md).
