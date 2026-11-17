# PS Pen & Paper

PS Pen & Paper entsteht als systemunabhängiger Baukasten für Pen-and-Paper-
Kampagnen auf WordPress und ClassicPress.

## Handbuch und Demorunde

- [Pluginhandbuch mit Screenshots](docs/handbuch.md): Einrichtung, Rollen,
  Charaktere, Freigaben, Würfel, Ressourcen, Farben und bekannte Grenzen.
- [Erweiterung: PS Events](docs/erweiterung-ps-events.md): Spieltermine
  verknüpfen, in der Lounge finden und zu- oder absagen, mit Screenshots.
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

Mitglieder müssen bereits Benutzer dieser Website sein. Spielleitungen und
berechtigte Spieler können sie einladen; Annahme und gegebenenfalls Freigabe
erfolgen in der Game-Lounge beziehungsweise in der Spielrunde. Die Aufnahme in eine Netzwerk-Website ist nicht
implementiert. Der Ersteller
bleibt Spielleiter seiner Kampagne. Ein Archivkennzeichen markiert Kampagnen
derzeit nur in der Übersicht; es sperrt keine Aktionen.

Neue Kampagnen benötigen ein vorhandenes Universum. Genau ein verwaltbares Universum
wird automatisch zugeordnet; bei mehreren ist die Auswahl erforderlich. Im eigenen PS-Community-Profil
stehen die Universumsauswahl zur Charaktererstellung und die Auswahl eigener Bögen
bereit. Das Profil enthält nur eigene Charaktere, Werte, Inventar und erlaubte
Charakteraktionen, keine Einladungen, Abenteuerstarts oder Leitungsfreigaben.
Profil und Spieltisch nutzen denselben Charakterbogen; Abenteuer bleiben im
Spielbereich. Universumsbindungen können nicht mehr entfernt werden.
Auch alte unabhängige Sicherungen benötigen bei der Wiederherstellung ein
ausdrücklich gewähltes Universum; lokale Regeln und Guthaben bleiben erhalten.
Optionale UI-Farben
werden am Universum eingestellt und von dessen Kampagnen übernommen.

Die Kampagnenleitung kann Ressourcen lokal führen oder bei gemeinsamen
Basisbögen ein kampagnenbezogenes Maximum für denselben Vorrat setzen.
Verbrauch und Heilung beachten dieses Modell. Unvereinbare Maxima werden
angezeigt und lassen sich korrigieren; Werte werden nie still abgeschnitten.
Charakternamen und freigegebene Porträts lassen sich ausdrücklich für die
Teilnehmerleiste teilen, ohne Werte und Inventar freizugeben. Porträts werden
bei Bedarf geschützt nachgeladen; bei gemeinsamem Basisstand gilt dessen
aktuelle Freigabe im Profil.
Die Universumsleitung definiert eigene Charakterzustände und deren Auswirkungen:
Aktions-/Beitrittssperren, Wertänderungen und optionale Ressourcengrenzen.
Sie entscheidet, ob Kampagnenleitungen lokale oder dauerhafte Folgen ändern
und vorbereitete Abenteuer diese automatisch auslösen dürfen. Rettung und
Wiederbelebung sind ausdrücklich vorbereitbar; Abenteuerergebnis und
Charaktertod bleiben unabhängig. Es gibt keine feste Nullpunkte-Todesregel.
Für Porträts, Bogen- und Spieltischhintergründe sind Bildfüllung und Position
mit Vorschau einstellbar. Der Rundenchat zeigt zusätzlich die letzten
20 öffentlichen Gruppenwürfe aus dem geschützten Wurfprotokoll, unabhängig
von PS Chat. Private und verdeckte Würfe bleiben ausgeschlossen; es werden
keine zusätzlichen PS-Chat-Nachrichten erzeugt.

Mit installiertem und aktiviertem **PS Events / Events & Bookings**
(`events-and-bookings`) können Spielleitungen Veranstaltungen mit Kampagnen
und einzelnen Spielrunden verknüpfen. Anstehende Spieltermine erscheinen
universumsübergreifend in der Lounge und Verwaltungsübersicht. Termine,
Anmeldungen und Zu-/Absagen bleiben in PS Events; eine Terminzusage erteilt
keinen Spielzugang und wählt keinen Charakter. Ohne PS Events läuft das Spiel
unverändert weiter. [Einrichtung und Grenzen](docs/erweiterung-ps-events.md).

Die drei Spielarten wurden exemplarisch über echte Browserformulare bis zum
Abschluss und Austritt geprüft, einschließlich Pause, Wiedereinstieg und
Figurenwechsel in der Gruppe. Die gezielten nativen Regressionen und
UI-Tests bestehen. Eine vollständige Plattform- und Variantenmatrix bleibt
eine getrennte Abnahme; Details stehen in der Roadmap.

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
Version 0.21.0 ergänzt den ersten Soloabenteuer-Ablauf mit freigegebenen
Szenen, Entscheidungen, optionalen Serverproben und getrennten privaten
Durchläufen. Gruppenabläufe und automatische Belohnungen sind nicht enthalten.
Version 0.22.0 ergänzt optionale Mindestwerte für Soloentscheidungen.
Nicht erfüllte Voraussetzungen bleiben sichtbar, aber gesperrt. Der aktuelle
freigegebene Wert wird serverseitig geprüft und mit dem Fortschritt protokolliert.
Version 0.23.0 ergänzt optionale Ressourcenkosten je Soloentscheidung.
Die Spielleitung wählt Ressource, Betrag und Kostenzeitpunkt: jeden Versuch
bezahlen oder nur erfolgreiche Proben (ohne Probe immer).
Verbrauch und Fortschritt werden gemeinsam mit privaten Pflichtereignissen
gespeichert. Ohne konfigurierte Kosten bleibt der Charakter unverändert.
Der erste Ausbau unterstützt lokale Bögen und unabhängige Kampagnenkopien;
gemeinsame Basisbögen und offene Werteanträge sperren kostenpflichtige Versuche.
Die Ressource muss auch bei erfolgsabhängigen Kosten vor dem Versuch reichen.
Version 0.24.0 ergänzt optionale Belohnungen je Soloentscheidung: feste
Gegenstände und/oder Währung oder ein eingefrorener Beutetabellenstand.
Die Spielleitung entscheidet, ob auch misslungene Proben belohnt werden.
Kosten, Vergaben und Fortschritt werden gemeinsam gespeichert; Wiederholen
zahlt nicht erneut aus. Wirtschaftliche Belohnungen benötigen PS Community,
gemeinsame Guthaben zusätzlich die Autorisierung der Universumsleitung.
Version 0.25.0 ergänzt bis zu 20 Wertebedingungen je Soloentscheidung,
wahlweise mit UND oder ODER: mindestens, größer, höchstens, kleiner,
gleich oder ungleich. Zahlen und Attribute verwenden den freigegebenen
wirksamen Wert; Ressourcen können aktuellen Wert oder Maximum prüfen.
Die Bedingungen verbrauchen nichts und werden mit ihren damaligen Werten
im privaten Verlauf gespeichert. Ältere Mindestwerte bleiben unverändert gültig.
Version 0.26.0 ergänzt einen privaten, versionierten JSON-Kampagnenexport:
lokale Daten mit ausgewiesenen Abhängigkeiten oder zusätzlich benötigte
Universumsdaten bei entsprechender Verwaltungsberechtigung. Eine SHA-256-Prüfsumme
begleitet den unveränderten Datenstand. Version 0.27.0 ergänzt eine private
Importvorschau mit ausdrücklichen Ziel-, Universums-, Benutzer- und
Basisbogenzuordnungen. Seit 0.29.0 lassen sich diese Sicherungen als neue Kampagne
oder als ausdrücklich bestätigter Ersatz wiederherstellen; externe Konten und
Integrationsdaten benötigen weiterhin eigene Backups.
Seit 0.28.0 prüft die Vorschau zusätzlich unterstützte Regel-, Charakter-,
Story- und Wirtschaftsdokumente mit den vorhandenen Fachvalidatoren.
Ungültige Werte werden als Konflikte angezeigt, nicht still korrigiert.

Der Regelwerkseditor ergänzt **Bearbeitungsrechte je Charakterfeld**:
Spieler direkt, mit Freigabe oder nur Spielleitung. Ohne eigene Auswahl gilt
die bisherige Bearbeitungspolitik; gemeinsame Basisbögen bleiben leitungsverwaltet.
Direkte Änderungen bleiben auch bei Ablehnung eines parallel offenen Antrags erhalten.
Optionale **Erfahrungs-/Stufenregeln** verbinden zwei frei benannte Zahlenfelder
mit aufsteigenden Schwellen. Stufen folgen dem jeweiligen Erfahrungsstand,
ohne vorgegebene Klassen oder automatische Attributboni. Vorhandene Bögen
werden nur über die bestätigte Änderungsvorschau angepasst.
Mit **Startpunkte und Punkte je Level** legt die Leitung das verteilbare
Attributbudget fest: Startpunkte + (Level - 1) × Punkte je Aufstieg.
Nicht verteilte Punkte bleiben verfügbar; der Bogen zeigt sie direkt an.
Erfahrung vergibt dabei die Leitung. Spieler können Ressourcen, Maxima und
Level nicht selbst hochsetzen. Im Profil gilt dies auch für Leitungskonten;
bewusste Korrekturen gehören in die Spielleitungsverwaltung.
Bei jeder eigenen Charaktererstellung sind Zahlen außerhalb des freigegebenen
Attributpools feste Startvorgaben, keine freien Eingaben.
Die Würfelbühne bietet nach ausdrücklicher Freigabe **Erfolgspools** sowie
**höchsten oder niedrigsten Einzelwürfel**. Würfelsummen bleiben Standard;
Abenteuerproben und Beutetabellen ändern ihre bisherige Berechnung nicht.

## Kampagnenexport seit Version 0.26.0

Unter **Rundeneinstellungen → Kampagne sichern** können Spielleitungen
eine JSON-Datei herunterladen. Der Export verändert keine Spielstände,
Guthaben, Vergaben oder IDs und legt keine öffentlich erreichbare Datei an.
Er enthält private Inhalte einschließlich Geheimnissen und muss geschützt
aufbewahrt werden. Der Download benötigt eine Nonce und die ausdrückliche
Bestätigung dieses Datenschutzhinweises.
Die Anfrage öffnet sich separat in einer neuen Registerkarte; ungespeicherte
Eingaben und deren Änderungswarnungen bleiben auf der bisherigen Seite erhalten.

- **Lokal:** Alle kampagneneigenen Plugin-Inhalte und `_pspp_`-Metadaten,
  einschließlich Entwürfen, Inventaren, offenen Anträgen, Wurf-/Ereignishistorien,
  Handouts, Szenen, Soloabenteuern und Durchläufen sowie gespeicherten Bildern.
  Gemeinsame Universums-/Basisdaten werden als nicht enthaltene Abhängigkeiten
  ausgewiesen.
- **Vollständig:** Zusätzlich das zugeordnete Universumsdokument mit Regeln,
  Währung und weiteren Einstellungen, gemeinsame Gegenstands-/NPC-/Wiki-Kataloge,
  tatsächlich zugeordnete gemeinsame Basisbögen sowie deren benötigte Bilder
  und zugehörige Ereignisse. Dafür muss die exportierende Person auch das
  Universum verwalten dürfen. Eine unabhängige Kampagnenkopie nimmt ihren
  ursprünglichen Basisbogen nicht mit. Andere Kampagnen, unbeteiligte Basisbögen
  und deren private Protokolle bleiben ausgeschlossen.

Fehlende benötigte Basisbögen oder Bilder sperren die vollständige Variante;
die lokale Sicherung weist diese Bild-/Basisabhängigkeiten ausdrücklich aus.
Bereits entfernte Bilder aus alten Ereignisprotokollen werden als nicht
enthaltene historische Abhängigkeiten festgehalten und sperren den Export
des aktuellen Stands nicht.

Benutzerkonten, Zugangsdaten, Community-Profile, PS-Chat-Nachrichten und externe
Wiki-Artikel/-Kategorien werden nicht exportiert. Benutzer- und
Integrationsverweise beziehungsweise vorhandene plugininterne Wiki-Stände
bleiben als Herkunft erhalten. **Die Datei ersetzt kein vollständiges Website-Backup.**

## Private Importvorschau seit Version 0.27.0

Unter **Einstellungen → Sicherung prüfen und Wiederherstellung vorbereiten**
prüfen berechtigte Administration und Spielleitung eine JSON-Sicherung.
Die Vorschau ist konten- und websitegebunden, höchstens eine Stunde verfügbar
und liegt nicht in der öffentlichen Mediathek. Die Dateigröße richtet sich
nach den Upload- und verfügbaren Speichergrenzen des Hosts.

Die verantwortliche Person wählt **pro Vorgang**, innerhalb ihrer Rechte:

- neue Kampagne oder Ersatz einer verwaltbaren bestehenden Kampagne;
- enthaltenes Universum getrennt wiederherstellen oder vorhandenes Universum
  und passende gemeinsame Basisbögen zuordnen; ohne Quelluniversum ist auch
  eine eigenständige Kampagne möglich;
- vorhandene Websitekonten ausdrücklich zuordnen; rein historische Konten
  können ohne aktive Mitgliedschaft bleiben; Charakterbesitzer und benötigte
  Autorisierungen brauchen eine Zuordnung.

Dateiformat, Prüfsumme, Datentypen, private Bilddaten und Kampagnenabgrenzung
werden geprüft. Fehlende Zuordnungen/Bilder, unpassende Basisbesitzer,
Feldstrukturen oder lokale Guthabenwährungen erscheinen als Konflikte.
Vorhandene Universumsdaten, Basiswerte und gemeinsame Guthaben bleiben
bei einer Zuordnung unverändert. Die Zielkampagne kann aus der Vorschau
privat gesichert und die Vorschau ausdrücklich verworfen werden.

Seit **0.29.0** folgt nach der Dokument- und Referenzprüfung die ausdrückliche
Wiederherstellung:

- Neue Inhalte erhalten neue IDs; ein Ersatz behält Kampagnen-ID und bisherigen
  Ersteller. Die importierten Zuordnungen und das ausführende Konto erhalten
  die vorgesehenen kampagneninternen Rechte, keine globalen Rollen.
- Beim Ersatz werden alte lokale Datensätze unter einer privaten,
  nicht spielbaren Sicherung aufbewahrt. Die unveränderte JSON-Zielsicherung
  dürfen nur bisheriger Ersteller, ausführende Person und Administration
  herunterladen, nicht automatisch jede neu importierte Spielleitung.
- Neue Inhalte, Rechte, Protokoll und Wiederholungsnachweis werden gemeinsam
  in einer InnoDB-Transaktion gespeichert. Fehler übernehmen keinen Teilstand.
  Bereits bestätigte Vorgänge werden auch nach einer späteren Wiederherstellung
  nicht erneut ausgeführt; geänderte Ziel-/Universumsstände benötigen eine
  aktuelle Prüfung und Bestätigung.
- Der Zugang bleibt standardmäßig privat und geschlossen. Die verantwortliche
  Person kann die ursprünglichen Zugangseinstellungen ausdrücklich übernehmen.
  Der ursprüngliche Zugang bleibt als Herkunft privat archiviert.
- Unbekannte Plugin-Metadaten sperren den Import, bis ihre rein private
  Archivierung ausdrücklich gewählt wird. Bekannte ungültige Dokumente werden
  dadurch nicht akzeptiert. Externe Wiki-Verknüpfungen bleiben deaktiviert.
- Ein Ersatz beginnt eine getrennte Chatsitzung und Anwesenheit. Alte Formulare
  und Spieltischverbindungen müssen neu geladen werden. Alte externe Chatdaten
  werden nicht gelöscht und brauchen weiterhin eine eigene Sicherung.

Inventare, Guthaben, UUIDs und Abenteuerdurchläufe werden als Spielstände
übernommen. Historische Würfe, Verbräuche und Belohnungen werden nicht erneut
ausgeführt. Die Vorschau bleibt für die Wiederherstellung höchstens eine Stunde
verfügbar; der gespeicherte Abschlussnachweis bleibt unabhängig davon erhalten.

## Geschützter Spielbereich im Frontend

Eine normale Seite mit dem Shortcode `[ps_pen_paper]` veröffentlichen; pro Seite
eine Instanz verwenden. Die Startseite ist die **Game-Lounge**: Universum wählen,
Kampagnen und erhaltene Einladungen sehen und eigene Spiele über alle Universen
hinweg öffnen. Der Universumsfilter verändert nicht die globale Übersicht.
Weltwiki, Story/Einleitung, Regelwerk, Handouts und Spielverlauf sind verlinkt;
ein Wiki-Link erscheint nur bei vorhandener Anbindung. Die optionale
**Welteinleitung für die Game-Lounge** ist getrennt von der geheimen internen
Weltbeschreibung. Die Lounge und Kampagnenkarten übernehmen die Universumsfarben.
Einladungen lassen sich hier annehmen oder ablehnen; sie gewähren vorher keine
internen Inhalte. Eigene Charaktere bleiben im Charakterprofil.

Der Standardbereich **Spielen** bleibt auch für Administratoren und
Spielleitungen die Spieler-Lounge. Berechtigte öffnen **Spielleitung** ausdrücklich:
Dort liegen die Verwaltungsnavigation und unter **Kampagnen → Vorbereiten**
Regeln, Szenen, NPCs/Gegner, Bögen, Spielangebote und Zugangseinstellungen.
**Spielrunden leiten** öffnet die Rundenverwaltung. Links und Formulare erhalten
den gewählten Bereich; **Spielen** wechselt bewusst zurück.
Auch die eingebetteten Bogen-, Inventar-, Handels-, Verbrauchs- und Porträtansichten
zeigen Leitungswerkzeuge nur im Verwaltungsbereich. Unter **Spielen** bleiben
Würfelfiguren auf eigene Charaktere begrenzt; Protokolle, Gruppenstände und
Kartenaktualisierungen verwenden die Spieleransicht statt erweiterter Leitungsrechte.
Auch Chatmoderation und die Liste laufender Spiele beachten diesen Bereich.
Der direkte Soloeinstieg, Freundesgruppen und vorbereitete NPC-/Gegnerreaktionen sind umgesetzt.
Die abschließende Prüfung sämtlicher Altansichten, weiterführende Automatik
und die beiden weiteren
vereinfachten Spielerwege bleiben Ziele der [Roadmap](ROADMAP.md).

Unter **Spielarten anbieten** legt die Leitung fest, ob die Kampagne geleitet,
solo oder als automatisches Gruppenabenteuer gespielt werden darf und ob Spieler
selbst neue automatische Runden anlegen dürfen. Automatische Abenteuer benötigen
eine passende Veröffentlichung ohne erforderliche Spielleiterentscheidungen.
Unter **Solo-Abenteuer** wählst du das freigegebene Abenteuer und deinen eigenen
freigegebenen Universumscharakter. **Solo spielen** führt direkt zur ersten Szene.
Runde, Teilnahme und Spielbeginn werden gemeinsam gespeichert; bei einem Fehler
bleibt kein halbfertiger Wartebereich zurück. Aktionen bleiben im Abenteuer;
über die Lounge lässt sich der gespeicherte Stand auch nach erneuter Anmeldung öffnen.
Ist das Anlegen neuer Spiele deaktiviert, bleiben bestehende Spiele erreichbar.
Unter **Automatisches Gruppenabenteuer** wählst du Abenteuer, eigene Figur und
bestätigte PS-Community-Freunde dieser Website. Die eigene Teilnahme und die
Einladungen werden zusammen gespeichert. Freunde öffnen die Einladung in der
Game-Lounge und nehmen mit einer eigenen freigegebenen Figur aus demselben
Universum an. Gleiche Regeln allein erlauben keine universumsfremde Figur;
Besitzer, Universumszuordnung und Revision werden auch serverseitig geprüft.
Blockierungen gelten in beiden Richtungen. Fehlender Kampagnenzugang wird über
die vorhandene Kampagneneinladung einschließlich erforderlicher Leitungsfreigabe
und persönlicher Annahme behandelt, nicht automatisch erteilt.
Der Gastgeber wartet auf Antworten oder zieht offene Gruppeneinladungen
ausdrücklich zurück und startet danach das gemeinsame vorbereitete Abenteuer.
Neue Freundesgruppen sind nur für Eingeladene und Teilnehmer sichtbar; die
Leitung verwaltet sie bewusst im Leitungsbereich.
Für diesen Einstieg muss das Freundschaftsmodul von PS Community aktiviert sein.
Es gibt keine zweite Freundesverwaltung. Bestehende offene Gruppen bleiben
unverändert nutzbar.

Die bisherige Rundenverwaltung für geleitete Spiele bleibt erhalten:
Im Wartebereich wählen die Teilnehmer ihre Charaktere; erst **Spiel starten**
beginnt die Runde.

**Vorbereitete NPC-Reaktionen:** Die Leitung kann pro Entscheidung einen NPC
oder Gegner samt ausgewähltem Zahlenwert übernehmen, eine optionale Serverprobe
mit Zielwert und öffentliche Reaktionen für Erfolg/Misserfolg festlegen.
Reaktionen können nach jeder Ausführung oder nur nach Spielererfolg/-misserfolg
auslösen. Ihre Zustandsfolgen schalten beispielsweise einen Zugang oder die
nächste Aktion frei. Zahlenwert und Reaktion werden ausdrücklich freigegeben
und versioniert; Geheimnisse und andere NPC-Werte bleiben intern. Das ist keine
vorgegebene Kampfmechanik: Initiative, eigenständige NPC-Züge und automatische
NPC-Ressourcenänderungen werden dadurch nicht eingeführt.

Dieselbe vorbereitete Kampagne kann mehrere unabhängige Runden haben:
Szenenauswahl, Karten, Spielzüge, Abenteuer und Protokolle bleiben getrennt.
Charakterwerte, Ressourcen und Inventar bleiben mit dem Universumsbogen verbunden;
Gegenstände werden beim Beitritt nicht kopiert. Geleitete Runden startet die
Spielleitung. Automatische Gruppen organisiert der jeweilige Gastgeber, ohne
Spielleiterrechte zu erhalten; eine Abenteuerentscheidung wird direkt ausgeführt.
In einer Pause sind Beitritt und Charakterwechsel möglich. Derselbe Charakter
kann nicht gleichzeitig in mehreren laufenden Runden spielen.

Nach dem Ende zeigt der Spieltisch **Deine Abschlussübersicht**: die persönliche
Bilanz von Ressourcen, Zahlenwerten (auch frei definierten Erfahrungspunkten),
Gegenständen, Guthaben und Charakterfolgen. Neue Runden speichern dafür private
Vergleichsstände beim Einstieg und beim Ende der Teilnahme. Austritt und
Wiedereinstieg bilden getrennte Abschnitte; Änderungen dazwischen zählen nicht.
Spätere Profiländerungen schreiben die Abrechnung nicht um. Bei älteren Runden
werden ausschließlich vorhandene Abenteuerkosten und ausgegebene Belohnungen
ausgewertet; fehlende historische Daten werden ausdrücklich kenntlich gemacht.
**Zurück zur Lounge** beendet keine Mitgliedschaft und zahlt nichts erneut aus.
Unter **Abgeschlossene Runden → Ergebnis ansehen** lässt sich die Übersicht
wieder öffnen. Die Rückkehr funktioniert für Solo-, Gruppen- und geleitete Runden.

Mitglieder können **Kampagne verlassen** ausdrücklich bestätigen. Das beendet
auch ihre aktiven Rundenteilnahmen und verwirft offene Gruppenvorschläge,
ohne Charaktere oder bisherigen Fortschritt zu löschen. Der Kampagnenersteller
bleibt verantwortlich und kann sich nicht selbst entfernen.
Spielleiter können Charaktere für Mitglieder
erstellen, bearbeiten und Anträge freigeben oder verwerfen. Es gelten dieselben
Bearbeitungsrechte und Revisionsprüfungen wie im Backend. Nach dem Speichern
bleibt man im Frontend. Zugang, Beitrittsanfragen, Hintergrundbild und Farben
sind über **Rundeneinstellungen** direkt im Frontend erreichbar. Dort können
Spielleiter auch Name, Beschreibung und Archivstatus ändern sowie bestehende
Website-Benutzer als Spieler oder Spielleiter aufnehmen und entfernen.
Das **Regelwerk** lässt sich unter **Spielleitung → Kampagnen → Vorbereiten**
einrichten und bearbeiten; Spieler behalten die lesende Ansicht.
Berechtigte können unter **Spielleitung → Kampagnen** eine neue Kampagne
anlegen und anschließend direkt deren Regelwerk einrichten. Dafür ist kein
Wechsel ins Backend erforderlich; die Erstellberechtigung vergibt weiterhin
die Website-Administration.
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
**Handelsregeln** Gegenstandsgeschenk, Verkauf, Tausch und Geldgeschenk
unabhängig voneinander ein.
Standardmäßig ist keine Art aktiviert; eine zusätzliche Spielleiterfreigabe
ist voreingestellt. Universumsregeln werden unverändert übernommen,
eigenständige Kampagnen konfigurieren ihre Regeln lokal.

Besitzer bieten am eigenen Charakter ein handelbares, aktiv wie beantragt
abgelegte Exemplare einem anderen Besitzer derselben Kampagne an
(höchstens 100 je Seite). Ein Verkauf vereinbart einen positiven ganzzahligen
Gesamtpreis; beim Tausch wählt der Empfänger seine konkreten Gegenexemplare, bevor der Anbieter den
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
Beim **Geldgeschenk** werden keine Exemplare ausgewählt: Der Anbieter bezahlt
den positiven ganzzahligen Betrag an den zustimmenden Empfänger.
Inventare bleiben unverändert; gemeinsame Basisguthaben werden ebenfalls unterstützt.

Die Spielleitung kann unter **Gegenstände entziehen / entfernen** konkrete
Exemplare nach ausdrücklicher Bestätigung entfernen, auch gebundene oder
ausgerüstete. Aktive und beantragte Bestände und Slotbelegungen werden gemeinsam
bereinigt; das Protokoll bleibt erhalten. Handelsreservierte Exemplare können
erst nach dem Zurückziehen oder Ablehnen des Angebots entfernt werden.

Zurückziehen, Ablehnen und Spielleiterablehnung geben Reservierungen frei.
Neue Handelsregeln sperren den Abschluss alter Angebote; deren Ablehnung
bleibt möglich. Ohne Community bleiben Daten und lesbare Handelsnachweise
erhalten, wirtschaftliche Änderungen jedoch gesperrt.

### Kontrollierte Übertragungen innerhalb eines Universums

Die Universumsleitung aktiviert unter **Handelsregeln** ausdrücklich
**Kontrollierte Übertragungen zwischen Kampagnen dieses Universums erlauben**.
Standardmäßig bleiben sie ausgeschaltet. Eine für beide Kampagnen berechtigte
Spielleitung oder Administration öffnet den Quellbogen und bestätigt unter
**In eine andere Kampagne dieses Universums übertragen** die Exemplare,
den Geldbetrag und den Zielcharakter. Auch eigene Figuren können so
kontrolliert übertragen werden; Spielerhandel bleibt kampagnenintern.

Nur handelbare, aktiv und beantragt abgelegte, nicht reservierte Exemplare
werden übertragen. Identitäten und eingefrorene Werte bleiben erhalten;
geschützte lokale Gegenstandsbilder werden in die Zielkampagne kopiert.
Aktive und beantragte Inventare, Geld und beide Protokolle ändern sich atomar.
Die Zielregeln müssen die enthaltenen Bonusfelder unterstützen.

Geld benötigt dieselbe Universumswährung und die bestehenden Kontorechte.
Lokale Währungen werden nicht umgerechnet. Verwenden beide Bögen bereits
dasselbe Basisguthaben, wird keine Geldübertragung ausgeführt oder ein
Ersatzkonto angelegt. Reine Geldübertragungen verändern kein Inventar.
Veraltete Formulare, entzogene Freigaben und fehlendes Guthaben werden
abgewiesen. Wiederholtes Absenden führt nicht zu doppelten Vergaben;
native Sicherungen erhalten den Wiederholungsschutz.
Übertragungen zwischen getrennten Universen sind ausgeschlossen.

## Soloabenteuer seit Version 0.21.0

Spielleitungen können im Bereich **Abenteuer & Szenen** ein kleines
Soloabenteuer aus bereits freigegebenen Szenen vorbereiten. Spieler treffen
Entscheidungen; optionale Würfel-/Attributproben und Folgeszenen werden serverseitig
geprüft und pro Charakter als privater Durchlauf gespeichert. Eine ausdrücklich
freigegebene Fassung bleibt für jeden begonnenen Durchlauf unverändert.

Soloentscheidungen verändern die gemeinsame Kampagnenszene nicht.
Ressourcen und Bestände ändern sich ausschließlich durch ausdrücklich
konfigurierte Kosten beziehungsweise Belohnungen. Ein Durchlauf gehört genau
einem Charakter und kann derzeit nicht zurückgesetzt werden. Gemeinsames Spielen
verwendet einen getrennten Gruppendurchlauf. Die Spielleitung kann neue Starts pausieren, ohne bestehende Durchläufe
zu löschen. Details und Grenzen stehen im
[Pluginhandbuch](docs/handbuch.md#21-soloabenteuer).

Seit **0.22.0** kann jede Entscheidung zusätzlich einen Mindestwert für ein
Zahlen- oder Attributfeld verlangen, unabhängig von einer Würfelprobe.
Maßgeblich ist der aktuelle freigegebene Wert einschließlich wirksamer
Ausrüstungsboni, nicht ein offener Änderungsantrag. Gesperrte Entscheidungen
bleiben mit Voraussetzung und aktuellem Wert sichtbar. Wiederholte oder
veraltete Formulare umgehen die Serverprüfung nicht. Der private Verlauf
speichert den tatsächlich verwendeten Wert; ältere Durchläufe ohne
Voraussetzungen bleiben unverändert. Mindestwerte allein verbrauchen keine Ressourcen.

Seit **0.25.0** stehen unter **Optionale Wertebedingungen** mehrere Zahlen-,
Attribut- und Ressourcenvergleiche mit UND/ODER zur Verfügung. Auch einzelne
Mindestwerte werden für neue Entscheidungen dort konfiguriert. Nicht erfüllte
Entscheidungen bleiben sichtbar und gesperrt; bei ODER genügt eine erfüllte
Bedingung. Zusätzlich unterstützen Textfelder gleich/ungleich und
enthält/enthält nicht; vorherige Entscheidungen lassen sich für denselben
Durchlauf mit 0 (nicht ausgeführt) oder 1 (ausgeführt) prüfen.
Gegenstandsbedingungen, frei gesetzte Abenteuerzustände und
Entscheidungsfolgen sowie geordnete Begegnungsphasen sind ergänzt.

Für hybride Einzeldurchläufe kann die Spielleitung je Entscheidung eine
**ausdrückliche Spielleiterfreigabe** verlangen. Der Besitzer fragt an;
unter **Abenteuer & Szenen → Ausstehende Abenteuerentscheidungen** wird
freigegeben oder abgelehnt. Erst danach führt der Besitzer die Entscheidung
aus. Anfrage und Freigabe verbrauchen nichts und würfeln nicht.
Eine Freigabe wird einmalig verwendet und im Verlauf dokumentiert.
Zurückziehen und erneut anfragen ist möglich, auch nach entzogenen
Spielleiterrechten. Import/Export erhält die Zustände und ordnet die beteiligten
Benutzer ausdrücklich zu. Gruppendurchläufe verwenden dieselbe optionale
Freigabepflicht.

Seit **0.24.0** kann jede Entscheidung eine feste Gegenstands-/Geldbelohnung
oder eine vorhandene Beutetabelle verwenden: nach jeder ausgeführten
Entscheidung oder nur bei erfolgreicher Probe; ohne Probe immer.
Die letzte Entscheidung kann die Abschlussbelohnung vergeben.
Gewählte Vorlagen und Tabellen werden eingefroren. Mit der Abenteuerfreigabe
wird dieser Stand Teil des lesbaren Soloabenteuers; andere Tabellen bleiben
privat. Inventar und Guthaben müssen vor dem Würfeln jedes mögliche Ergebnis
aufnehmen können. Fehlende Integration, geänderte Währung oder entzogene
Vergaberechte blockieren die Entscheidung ausdrücklich, ohne Teilvergaben.

## Gemeinsame und hybride Gruppenabenteuer

Unter **Abenteuer → Gruppendurchlauf anlegen** legt die Spielleitung einen Gruppendurchlauf
aus einem freigegebenen Abenteuer an. Je Gruppe wählt sie Einzelentscheidung,
Mehrheit, Einstimmigkeit oder Spielleiterfreigabe. Jeder Besitzer tritt mit
einer eigenen freigegebenen Figur bei; pro Benutzer ist eine Figur gleichzeitig
aktiv. Vorschläge und Stimmen verbrauchen nichts. Nur der im Vorschlag benannte
Besitzer führt die vereinbarte Entscheidung aus. Voraussetzungen, Kosten,
Belohnungen und aktuelle Rechte werden dabei erneut geprüft.

Alle Teilnehmer teilen Szene und Fortschritt. Persönliche Kosten und Belohnungen
betreffen ausschließlich die ausführende Figur; fremde Charakterwerte bleiben
privat. Austritt, Pausieren, Wiedereinstieg und Regelwechsel erhalten den Verlauf
und verwerfen offene Vorschläge und Freigaben. Neue Fassungen ändern laufende
Gruppen nicht. Import/Export erhält Teilnehmer, Stimmen, Freigaben und historische
Nachweise, ohne Kosten oder Belohnungen erneut auszuführen; zusammengelegte
Benutzer dürfen keine Doppelstimmen erzeugen.

Spielleitung/Admin stellt Universen, Kampagnen und Abenteuer auf der Website
bereit. Spieler öffnen ihre Kampagne und spielen die freigegebenen Abenteuer
mit ihrem Charakter nach den dort festgelegten Regeln. Es gibt keine separate
Paketbibliothek und keinen Abenteuerdatei-Import oder -Download.

### Kapitelgeschichten und frei spielbare Szenarien

Im Abenteuereditor bestimmt die Spielleitung optional Kapitel und Anzeigeordnung,
Einzel- oder Gruppenspiel, Voraussetzungen und Wiederholungen. Ohne Vorgänger
bleiben Szenarien unabhängig spielbar. Für eine Kapitelgeschichte kann das nächste
Szenario einen Abschluss oder Sieg im vorherigen Szenario voraussetzen; zusätzlich
ist ein Mindestwert eines frei gewählten Zahlenfelds möglich.

Enden werden als gewonnen, verloren oder neutral gekennzeichnet. Belohnungen
gelten nur im ersten Durchlauf, bei jeder Wiederholung gleich oder ab der
Wiederholung anders. Der erste gestartete oder beigetretene Durchlauf zählt je
Spielerkonto und Szenario, auch bei Niederlage. Andere Figuren und erneute
Veröffentlichungen setzen diesen Zähler nicht zurück. Bereits laufende
Durchläufe behalten ihre eingefrorenen Einstellungen.

Gemeinsame Basisressourcen werden bei kostenpflichtigen Entscheidungen
am maßgeblichen Universumsbasisbogen verbraucht, nicht an einer unabhängigen
Schattenkopie.

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

Tabellen und Vorschläge bleiben ausschließlich für die zuständige Spielleitung
sichtbar. Empfänger sehen ihre tatsächlichen Gegenstände und Buchungen, nicht
geheime Chancen oder Begründungen. Gegner/NPCs können auf eine Kampagnen- oder Universumstabelle
verweisen; Universumsvorlagen übernehmen diese Kampagnenreferenz nicht.
Bestehende Vorschläge bleiben bei späteren Tabellenänderungen unverändert.

Die Universumsleitung pflegt **Beutetabellen** unter **Regelwerk &
Basischaraktere**. Angeschlossene Kampagnen sehen die Tabellen in ihrer
Spielleitermappe und können sie auswürfeln, als Untertabellen verwenden oder
Abenteuerbelohnungen zuordnen. Nur die Universumsleitung verändert diese
gemeinsamen Vorlagen; lokale Kampagnentabellen bleiben eigenständig.
Jeder Vorgang friert seinen damaligen Stand ein, auch nach Vorlagenänderungen.
Die vollständige Kampagnensicherung enthält Universumstabellen und ihre
verschachtelten Bezüge. Gegenstandsgruppen, Aufteilung auf mehrere Empfänger
und ausdrücklich freigegebene automatische Abenteuervergaben sind vorhanden.
`W3` ist kein unterstützter Standardwürfel. Handel besteht separat seit Version 0.20.0.

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

Ältere Kampagnen ohne Universum konfigurieren ihre eigene Währung unter
**Rundeneinstellungen** und führen getrennte Konten. Zugeordnete Kampagnen
übernehmen standardmäßig die Währungseinstellungen des Universums. Bei der
Zuordnung kann die verantwortliche Person ausdrücklich **Lokale Währung und
Handelsregeln behalten** wählen. Bestehende lokale Konten, Guthaben und
Handelsregeln bleiben dann unverändert und nutzbar; das Universum wird nicht geändert.
Nach der ersten Buchung ist das Kontomodell fest; Name und Kürzel bleiben
änderbar. Eine andere Universumszuordnung bestehender Kampagnenkonten
ist mit bewusst beibehaltenen lokalen Einstellungen möglich. Die Übertragung
bestehender Universumskonten, die Währungsumrechnung und der Wechsel gemeinsamer
Basisbögen benötigen weiterhin eine gesonderte Migration.

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
in Community zugeordnete Profilseite. Dort wählt der Besitzer sein Universum,
erstellt seinen Charakter und öffnet eigene Universums- oder Kampagnenbögen
über die Charakterauswahl. Einladungen einschließlich Leitungsfreigaben stehen
direkt im Profil mit Spielrunde beziehungsweise Universum und Absender.
Dort werden erhaltene Einladungen angenommen oder abgelehnt, nicht verwaltet.
Mitspieler werden in der jeweiligen Spielrunde eingeladen; Spieleauswahl
und Spielnavigation bleiben im Spielbereich.
Porträt, Werte und Inventar verwenden dieselben Charaktertabs wie im Spiel.
Ein vorhandener eigener Charakter wird beim Öffnen des Profils direkt angezeigt.
Ein eingerichtetes Universum erlaubt standardmäßig die eigene Charaktererstellung
für Website-Mitglieder, ohne zusätzliche Universumsmitgliedschaft.
Eine ausdrücklich von der Leitung gespeicherte Zugangsbeschränkung bleibt gültig.
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
  Kampagnen konfigurieren ihre Slots selbst; bei älteren Kampagnen ohne
  Universum gilt nur ihr eigener Katalog.

Über den jeweiligen Charakter gibt die Spielleitung **konkrete Exemplare**
aus. Jedes Exemplar behält seinen damaligen Vorlagenstand; spätere
Vorlagenänderungen verändern ausgegebene Gegenstände nicht.
Ein Charakter besitzt zunächst höchstens 100 einzelne Exemplare.
Ein Exemplar darf genau einen passenden Slot belegen. Slotauswahl, Besitz
und Freigabepolitik werden auf dem Server geprüft.

Verbrauchsgüter erhalten statt einer Slotbelegung eine ausdrückliche
Nutzungsregel: sofort, Leitungsfreigabe, bestandene Probe oder eine echte
Spielrunde aussetzen. Die Leitung definiert dauerhafte oder temporäre
Änderungen auf Zahlen, Attribute und aktuelle Ressourcenwerte.
Ein erfolgreich benutztes Exemplar wird genau einmal verbraucht; Grenzen,
Nachweise und Änderungen werden gemeinsam gespeichert. Währungen bleiben
in den Geldkonten, nicht in erfundenen Goldgegenständen.

Unter **Ausrüstung und Verbrauch außerhalb des Spiels** erlaubt oder verbietet
die Leitung Profilaktionen ausdrücklich, getrennt für Ausrüsten und Verbrauch.
Zusätzliche Slot- und Gegenstandsregeln können einschränken oder eine Freigabe
verlangen; ein Verbot hat Vorrang. Im Profil gibt es keine Probe und kein
Rundenaussetzen. Während einer gestarteten Sitzung bleiben Profilaktionen
standardmäßig gesperrt. Gemeinsame Basisressourcen wirken in verbundenen
Kampagnen, deren Ausrüstung bleibt getrennt. Temporäre Basiseffekte beendet
die Universumsleitung ausdrücklich; Kampagnenrunden ersetzen das nicht.

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

Solo, automatische Gruppen und geleitete Runden verwenden denselben Spieltisch.
Ohne Grafik dient eine Holzoberfläche als Hintergrund. Automatische Abenteuer
zeigen ihre gespeicherte Szene, die letzte öffentliche NPC-Antwort und die
erlaubten Aktionen direkt auf dem Tisch statt in einer getrennten Textansicht.
Szenengrafiken stammen aus dem freigegebenen Abenteuersnapshot; die interaktive
Karte geleiteter Runden bleibt erhalten.
Teilnahmeverwaltung und vollständige Verläufe sind eingeklappt.
**Mein Bogen** öffnet direkt die gewählte Rundenfigur mit Porträtbereich und
Ausrüstungsslots; **Bogen schließen** kehrt zum Tisch zurück.
Aktuelle [Screenshots und Bedienhinweise](docs/handbuch.md#gemeinsame-spielfläche)
stehen im Handbuch.

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
Die Vorschau zeigt zusätzlich, ob Währung und Handelsregeln vom Zieluniversum
verwendet oder lokal beibehalten werden. Lokale Guthaben allein verhindern
eine ausdrücklich lokale organisatorische Zuordnung nicht mehr.
Die Zuordnung steht im geschützten Kampagnenprotokoll. Universumsbesitz gewährt
keinen automatischen Zugriff auf zugeordnete Kampagnen oder deren Chat.
Bestehende Kampagnen werden nicht automatisch zugeordnet. Ihre Regeln, Bögen,
offenen Anträge und alten Würfe werden nicht verändert oder zusammengeführt.
Löschen von Universen und zusätzliche Universumsleiter sind noch keine UI-Aktionen.

Seit Version 0.11.0 öffnet **Universen → Regelwerk & Basischaraktere** die
Regelvorlage und die Basisbögen der Welt. Nur die Universumsleitung bearbeitet
Basisbögen; ihr Besitzer muss ein leseberechtigtes Website-Mitglied sein.
Besitz allein öffnet weder die private Weltbeschreibung noch deren Historie.

Neue Kampagnen erhalten beim Anlegen eine Verbindung zum Universumsregelwerk.
Die Universumsleitung gibt dort flexible Regeln und Feldgrenzen frei; nicht
freigegebene Vorgaben sind fest. Kompatible Änderungen werden übernommen,
kampagneneigene Abweichungen bleiben erhalten. Konflikte sperren neue Spiele;
Charakteränderungen benötigen eine bestätigte Vorschau. Vorbereitete Inhalte
können die Übernahme derzeit ebenfalls blockieren, weil ihre Auswirkungen noch
nicht automatisch geprüft werden. Bereits laufende Runden bleiben unverändert.
Ältere Kampagnen werden nicht automatisch in diese Verbindung aufgenommen.
Eine ältere leere Kampagne kann die Vorlage ausdrücklich als Kopie übernehmen;
dafür sind Verwaltungsrechte an Universum und Kampagne erforderlich.
Weitere Regelbereiche wie Wirtschaft und Zustände besitzen weiterhin eigene
Vorgaben und sind nicht Bestandteil dieser Regelwerkvererbung.
Beim Import mit Universumsbindung muss die gesicherte Regelbasis genau zum
gewählten Universumsregelwerk passen; andernfalls wird die Bindung abgelehnt.
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

Unter **Kampagnenkontext → Als unabhängigen Kampagnenbogen weiterführen** kann die
Kampagnenleitung den aktuellen freigegebenen Basisstand nach Vorschau und
Bestätigung unabhängig weiterführen. Kampagnenauflagen werden dabei zu eigenen
Grundwerten; Ausrüstung, Inventar, Notizen und persönliche Konten bleiben erhalten.
Ausrüstungsboni und offene Basisanträge werden nicht in den Grundstand kopiert.
Gemeinsame Universumskonten sperren diesen Schritt bis zu einer gesonderten
Kontenmigration. Nach dem Ablösen aller gemeinsamen Bögen kann ein anderes
Universum zugeordnet werden, sofern vorhandene Konten weiter zu ihrer Währung
passen. Eine Zuordnung ohne Zieluniversum ist nicht möglich.
Bei einer fehlenden Basis erscheint ein Fehler; es werden keine veralteten
Ersatzwerte verwendet.
Unabhängige Kopien können ohne das ursprüngliche Universum und dessen Basisbogen
importiert werden, benötigen aber ein ausdrücklich gewähltes Zieluniversum;
deren alte Kennungen bleiben reine historische Herkunftsnachweise.
Unabhängige Kopien können ausdrücklich mit dem aktuellen Basisbogen
wieder verbunden werden. Die Vorschau verlangt eine Lösung für abweichende
Werte; Zahlen, Attribute und Ressourcen können als lokale Auflagen erhalten bleiben.
Spieler können in einer zugeordneten Kampagne einen eigenen Universumsbasisbogen
beantragen. Die Universumsleitung prüft diesen Antrag; die Aufnahme des
freigegebenen Bogens in eine Kampagne bleibt ein eigener Vorgang.
Universumsmitgliedschaften, Erfahrungs-/Stufenschwellen und ein verteilbarer
Level-Punktepool sind inzwischen ergänzt. Ein bloßes Zahlenfeld namens
„Stufe“ aktiviert diese Regeln nicht. Ressourcenauflagen erlauben lokale Vorräte
oder gemeinsame Vorräte mit kampagneneigenem Maximum.

Die Spielleitung kann einen Teilnehmer als **am Zug** markieren oder
**Freies Spiel** wählen. Änderungen sind revisionsgeschützt und werden mit
Vorher/Nachher im gemeinsamen Protokoll gespeichert. AFK schaltet den Zug
nicht automatisch weiter. Spielrunden haben eigene Teilnehmer und werden in
der Game-Lounge unabhängig von der Kampagnenvorlage gestartet.

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
Im Frontend-Bereich **Spielen** erhalten auch Leitungskonten keine Chatmoderation.
Erst **Spielleitung** stellt sie wieder bereit. Der Ansichtskontext wird für
Legacy-AJAX und REST pro Anfrage übermittelt, nicht als gemeinsame
Raumeinstellung gespeichert; er kann keine zusätzlichen Rechte vergeben.

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
festgehalten. Ein optionaler Attributpunktepool ist verfügbar; separate
feldweise Bearbeitungsrechte sind noch nicht implementiert.

## Regelwerk und mobile Bedienung

Jede Kampagne besitzt einen eigenen Regelwerks-Editor für Administratoren und
zugeordnete Spielleiter. Mitglieder können das gespeicherte Regelwerk lesen.
Konfigurierbar sind:

- Name und Beschreibung des Regelwerks sowie erlaubte Würfel
  (W4, W6, W8, W10, W12, W20, W100); keine Auswahl erlaubt würfellose Systeme.
- Bis zu 30 Charakterfelder: kurze und lange Texte, Auswahlfelder, Zahlen, Attribute und
  Ressourcen, jeweils mit eigener Bezeichnung, Erläuterung und Pflichtfeldstatus.
- Ganzzahlige Minima, Maxima und Startwerte für numerische Felder.
- Reihenfolge der Felder; Kennungen bleiben beim Umbenennen und Umsortieren
  erhalten.

Die feste Attribut-Punkteverteilung prüft die genaue Summe der ausgewählten
Attribute. Der Level-Punktepool begrenzt stattdessen das Budget anhand von
Startpunkten und erreichten Leveln; Restpunkte bleiben verfügbar.
Auswahlfelder erlauben nur die konfigurierten Optionen.
Verwendete Feldlisten, Typen, Pflichtstatus und Wertebereiche lassen sich nach
Änderungsvorschau und ausdrücklicher Konfliktauflösung migrieren. Gültige Stände
und offene Anträge werden nicht stillschweigend angepasst. Vorher-/Nachher-
Stände bleiben nachvollziehbar; veraltete Vorschauen und Speichervorgänge
werden abgelehnt. Komplexe Würfelmechaniken bleiben spätere Erweiterungen.

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

### Interne Exportschnittstelle und Dateiformat

`ps_pen_paper_export_campaign(int $campaign_id, string $mode)` liefert ein
Array oder `WP_Error`; `mode` ist `local` oder `complete`. Rechte gelten
auch für PHP-Aufrufer. Der Export liest Beiträge und Metadaten einschließlich
der berechtigten Universumsabhängigkeiten in einer gemeinsamen, ungecachten
SQL-Abfrage. Anschließend werden Berechtigung und Universumszuordnung anhand
dieses Stands erneut geprüft. Er schreibt keine Dokumente oder Ereignisse.
Nichtstandard-Datenbankverbindungen und Replikationsmodelle sind noch nicht geprüft.

Der äußere JSON-Umschlag enthält:

- `format: "ps-pen-paper-campaign"`, `format_version: 1`, `plugin_version`
  und UTC-Zeitstempel `created_at`;
- `payload`: `source` mit Websiteadresse, Blog-/Kampagnen-ID, `mode`,
  `meta_encoding: "wordpress-raw"`, `records`, `dependencies` und `external_data`;
- `payload_sha256`: SHA-256 der mit
  `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` kodierten `payload`,
  unter Erhalt der Schlüssel- und Listenreihenfolge.

Jeder Eintrag in `records` enthält `id`, `type`, `parent_id`, `author_id`,
`status`, `title`, `content`, `excerpt`, `mime_type`, `date_gmt`, `modified_gmt`,
`menu_order` und `meta`. `meta` ist eine Liste aus `{key, value}`; `value`
enthält den unveränderten Datenbankstring, gegebenenfalls PHP-serialisiert.
Mehrfache Rollenmetadaten, gespeicherte Schlüsselreihenfolgen, numerische
Strings, UUIDs und eingefrorene historische Dokumente bleiben damit erhalten.
Bilddaten stehen unverändert als Base64 im Inhalt ihrer `pspp_asset`-Datensätze.
Die Formatversion betrifft dieses Sicherungsformat, nicht eine Migration
gespeicherter Spielstände. Neue kampagneneigene Inhaltstypen müssen in der
Exportsammlung ergänzt werden.

`dependencies` weist Universen, gemeinsame Basisbögen und Bildverweise mit
`included` aus. Bildverweise enthalten zusätzlich `referenced_by` und
`historical`; historische Fehlbestände sind ausdrücklich sichtbar.
`external_data` beschreibt die nicht enthaltenen Konten, Profile, Chatdaten
und externen Wiki-Daten. Alte IDs sind Herkunftsverweise, keine Erlaubnis,
vorhandene Zieldaten bei einem Import zu überschreiben.

Die Prüfsumme erkennt Beschädigungen, ist aber keine Signatur und kein
Vertrauens- oder Berechtigungsnachweis. Der Import prüft Format,
Dokumente, Benutzer-/Objektzuordnungen und Zugriffsrechte unabhängig.
Fremde Metadaten werden nicht ungeprüft deserialisiert oder aktiviert;
historische Geld-/Gegenstandsaktionen werden nicht erneut ausgeführt.

`tests/export-integration.php` prüft lokale und vollständige Varianten,
Gemeinschaftsbögen gegenüber unabhängigen Kopien, unveränderte Metadaten und
Bildbytes, Prüfsumme, Fremddatenausschluss, fehlende Abhängigkeiten,
geänderte Berechtigungen/Zuordnungen und Datenbankfehler:

```bash
wp --url=https://example.test/ --skip-plugins --skip-themes eval-file wp-content/plugins/pen-and-classicpress/tests/export-integration.php
```

Mit `PSPP_TEST_HTTP=1` werden zusätzlich echte Backend-/Frontend-Downloads
einschließlich Nonce-/Bestätigungsprüfung und privater Downloadheader geprüft.
Dafür muss das Plugin in HTTP-Anfragen aktiv sein und die Website vom
Testprozess erreichbar sein. Es werden ausschließlich temporäre Testkonten,
Kampagnen und eine Testseite verwendet; eine vorhandene Easy-Blogging-
Installation erhält im Test die übliche Modus-Cookie-Kennung, damit nicht
deren Erststartseite statt des Downloads antwortet.
`node tests/export-ui.js` prüft, dass Downloads ungespeicherte Änderungen
und deren Warnung erhalten, ohne das Verhalten normaler Formulare zu verändern.

### Interne Importvorschau

`ps_pen_paper_parse_import(string $json)` liefert geprüfte Archivstruktur,
Datensätze, sicher dekodierte Metadaten, Benutzer-/Basisbedarf und fehlende
Bilder oder `WP_Error`. PHP-Objekte, Enums und Referenzen werden vor einer
Deserialisierung abgewiesen; normale Texte innerhalb serialisierter Strings
bleiben erhalten. Die Prüfsumme ersetzt keine Berechtigungsprüfung.

`ps_pen_paper_import_configuration($parsed, $options)` erwartet ein erfolgreiches
Parsergebnis und prüft die aktuellen Rechte und ausdrücklich gewählten Ziele.
Die Optionen enthalten `operation` (`copy`/`replace`), `target_id`,
`universe_mode` (`none`/`copy`/`bind`), `universe_id`, `users` und `bases`.
Bei Konten bedeutet `0` noch nicht zugeordnet und `-1` bewusst ohne aktives Konto;
positive Werte sind bestehende Website-IDs. Leere Auswahlen werden als Konflikte
angezeigt, nicht durch fachliche Standardentscheidungen ersetzt.
Das Ergebnis enthält `conflicts`, `warnings`, `target_hash` und `universe_hash`,
aber ausdrücklich keine Schreibfreigabe.

Seit 0.28.0 enthält es außerdem `documents`: `checked` zählt fachlich geprüfte
Dokumente, `conflicts` enthält Fehler und `deferred` die noch separat zu prüfenden
Metadaten. `ps_pen_paper_import_documents($parsed)` nutzt vorhandene Validatoren
für Regeln, Charakterstände, Szenen, Mappeneinträge, Figuren, Gegenstände,
Inventare, Währung/Guthaben, Handel, Beute, Abenteuer und Wiki-Stände.
Die reinen Szenen-, Mappen- und Inventarvalidatoren werden auch von den
bisherigen Datenbank-Lesern verwendet. Originalwerte bleiben unverändert.
Der Bericht ist die erste Dokumentprüfung, keine alleinige Schreibfreigabe.
Seit 0.29.0 prüft `ps_pen_paper_prepare_import($parsed, $options, $id_map,
$archive_unknown)` zusätzlich Metadaten, Protokolle und typisierte Beziehungen.
Es liefert schreibfertige Datensätze, nach Quell-ID indiziert, mit dekodierten
Metadatenwerten oder `WP_Error`. Live-Verweise werden von eingefrorenen
Herkunftsverweisen unterschieden; ausgeschlossene Universumsdaten werden
bei einer Bindung nicht überschrieben.

`ps_pen_paper_create_import_preview($json)` und
`ps_pen_paper_get_import_preview($token)` verwalten private Transients mit
Besitzer, Blog, Ablaufzeit, Originaldatei und gewählten Optionen. Änderungen
verlängern die einstündige Verfügbarkeit nicht.

`ps_pen_paper_restore_campaign($token, $fingerprint, $archive_unknown,
$access_policy)` erwartet den Fingerprint der aktuellen privaten Vorschau,
eine boolesche Archiventscheidung und `private` oder `preserve`.
Es liefert `campaign_id`, `backup_id` und den gespeicherten Bestätigungsnachweis
oder `WP_Error`. Berechtigungen und Fingerprint werden unter Rundensperren erneut
geprüft. `ps_pen_paper_get_import_backup($id)` liefert ausschließlich berechtigten
Personen die geprüfte private Zielsicherung. HTTP-Aktionen benötigen zusätzlich
Nonce und ausdrückliche Bestätigung.
Nach einem Kampagnenersatz schützt ein neuer Epochenmarker ältere Formulare
und bereits laufende Anfragen. Interne PHP-Aufrufer dürfen alte Parameter nicht
ohne erneute Prüfung als aktuellen Spielstand behandeln.

`tests/import-write-integration.php` prüft tatsächliche Kopien und Ersetzungen,
lesbare Historien, neue IDs, private Sicherung und Rücksicherung, geschützte
Ersteller, unveränderte Quelldaten, wiederholte Anfragen, veraltete Bestätigungen,
atomaren Rollback und ausdrücklich archivierte unbekannte Metadaten.
Die Datenbank-Integrationssuiten werden seriell ausgeführt.

`tests/import-preview-integration.php` prüft reale lokale/vollständige Exporte,
gemeinsame und unabhängige Bögen, Laufzeitoptionen, Konten-/Besitzerprüfung,
fehlerhafte Dateien, gefährliche Serialisierungen, genaue Dateigrößengrenzen,
fehlende Bilder trotz entferntem Manifest und unveränderte Spielstände.
Seit 0.28.0 prüft er zusätzlich neu berechnete Prüfsummen bei ungültigen
Regel-/Charakterwerten, Geheimnisse in veröffentlichten Szenen, Guthabengrenzen
und unveränderte Quelldokumente bei der Fachprüfung.
Ausführung wie bei den übrigen WP-CLI-Integrationstests. Mit `PSPP_TEST_HTTP=1`
werden echte Multipart-Uploads, wiederholte Konfigurationsformulare,
Nonceprüfung, Verwerfen und die Vorschauseiten geprüft. Testkonten und
Vorschautickets werden anschließend entfernt.
Für die hier vorhandene externe Easy-Blogging-Startseitenregel kann zusätzlich
`PSPP_TEST_HTTP_ADMIN_REDIRECT=1` gesetzt werden: Dann wird der bekannte
Backend-GET-Redirect ausdrücklich geprüft und gemeldet, statt eine erfolgreiche
Backendansicht zu behaupten. Backendaktionen und direkte Darstellung bleiben
geprüft; die echte Frontend-Vorschauseite muss weiterhin funktionieren.

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
`tests/character-conditions-integration.php` prüft frei definierte Zustände,
Universumsbefugnisse und deren Entzug, lokale und dauerhafte Folgen,
Wertänderungen, optionale Ressourcengrenzen, Beitritts-/Aktionssperren,
Rettung in Solo- und Gruppenabenteuern, Sieg trotz Charaktertod sowie
Rückabwicklung und Sicherungsaufbereitung. Auch hier bleiben reale
Figuren und reale Demospielstände unberührt.
`tests/play-rounds-integration.php` prüft zusätzlich gespeicherte persönliche
Abschlussbilanzen, verbrauchte Gegenstände, Guthaben und Folgen, getrennte
Teilnahmeabschnitte, Gruppenprivatsphäre, Altstände, Unveränderlichkeit beim
Anzeigen und nach späteren Profiländerungen, Rollback und Sicherungsaufbereitung.
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

`tests/play-rounds-integration.php` prüft zusätzlich den direkten Soloeinstieg:
eigenen Charakter, sofortigen Spielbeginn, gespeicherte Aktionen, doppelte
Absendeversuche, bereits belegte Charaktere und vollständige Rücknahme bei
veraltetem Bogen oder fehlgeschlagener Durchlauf-Veröffentlichung.
Der Test prüft außerdem Freundesgruppen: bestätigte statt ausstehender
Freundschaften, beidseitige Blockierungen, Annahme/Ablehnung und Zurückziehen,
Kampagnenfreigaben, universumsfremde Figuren trotz identischer Regeln,
Besitzer-/Revisionsprüfung, atomare Rücknahme samt Kampagneneinladung sowie
gemeinsamen Start und vorbereitete NPC-Reaktionen. PS Community muss hierfür
als benachbartes Plugin installiert, aber nicht aktiviert sein.
`node tests/chat-ui.js` prüft die Ansichtskontexte beider Chat-Transporte;
fremde Domains und nicht zugehörige Anfragen erhalten keinen solchen Header.

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
