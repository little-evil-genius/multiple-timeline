# Timeline 1.0 (Mehre Timelines)
Dieses Plugin erweitert das Board um einen Zeitstrahl. Ausgewählte Gruppen können Ereignisse zur Timeline hinzufügen und diese werden in einem Zeitstrahl dargestellt.
Ereignisse vom Team werden automatisch freigeschaltet, doch Ereignisse von Usern müssen erst im Modcp freigeschaltet werden. Es kann eingestellt werden, ob User eigene Ereignisse bearbeiten und/oder löschen können. Das Team hat immer diese Möglichkeit, bei allen Ereignissen.
Auch kann entschieden werden, ob der Zeitstrahl absteigend oder aufsteigend sein soll.
Beim erstellen eines Ereignis kann man ein ganzes Datum (TT.MM.JJJJ) oder nur Monat und das Jahr oder nur eine Jahreszahl angegeben werden.
Die Monate werden innerhalb der Sprachdatei definiert, sollten eure Monate anders heißen, so könnt ihr sie dort ändern. 

# Änderungen zur anderen Version
In dieser Version kann man bei einem Ereignis noch die Timeline angeben, in der dieses Ereignis passiert. Wie in meinem Beispiel von einem The Umbrella Academy Board. 
Die Typen müssen in den Templates timeline_add_type und timeline_edit_type angepasst werden.

# Datenbank-Änderungen
Hinzugefügte Tabellen:
- PRÄFIX_timeline

# Neue Template-Gruppe innerhalb der Design-Templates
- Zeistrahl

# Neue Templates (nicht global!)
- timeline	
- timeline_add	
- timeline_add_date	
- timeline_add_type
- timeline_bit	
- timeline_edit	
- timeline_edit_date
- timeline_edit_type	
- timeline_modcp	
- timeline_modcp_bit	
- timeline_modcp_nav

# Template Änderungen - neue Variablen
- header - {$new_timeline_alert}
- modcp_nav_users - {$nav_timeline}

# ACP-Einstellungen - Zeitstrahl
- Erlaubte Gruppen
- Bearbeitung durch User
- Löschung durch User
- Sortierung

# Sonstiges
- Neues Stylesheet "timeline.css" in jedem Theme

# Links
- https://euerforum.de/misc.php?action=timeline
- https://euerforum.de/modcp.php?action=timeline

# Demo
  Zeitstrahl
  <img src="https://www.bilder-hochladen.net/files/big/m4bn-83-6094.png" />
  
  Maske beim Hinzufügen
  <img src="https://www.bilder-hochladen.net/files/big/m4bn-84-63bd.png" />
  
  Team-Alert auf dem Index
  <img src="https://www.bilder-hochladen.net/files/m4bn-7u-a06d.png" />
  
  Mod-CP
  <img src="https://www.bilder-hochladen.net/files/big/m4bn-7v-9efb.png" />
  
  Einstellungen
  <img src="https://www.bilder-hochladen.net/files/big/m4bn-7w-5558.png" />

Meine Quelle für das Tutorial rund um ein Timeline Code:
https://www.w3schools.com/howto/howto_css_timeline.asp

# Erweiterung
Der Name der Timeline wird nun innerhalb der Box angezeigt, sollte euch das nicht gefallen und ihr wollt, das die boxen unterschiedlich farbig angezeigt werden, dann müsst ihr ein bisschen was in den Tpls und CSS umstellen.
Ganz wichtig dafür ist, die Name der Timeline die in der Datenbank gespeichert werden, dürfen nur ein Wort beinhalten! Also in meinem Fall würde aus Umbrella Academy nur Umbrella werden.
Warum? Damit man im CSS damit arbeiten kann.

Im Template timeline_bit müsst ihr die Variable {$type} hinter <div class="event und <div class="content setzen. Und abspeichern. 
Nun kommt der wichtige Teil im CSS. Ich bleibe bei meinem Beispiel Umbrella einmal, damit es deutlicher wird.
Bei Ereignissen wo die Timeline Umbrella ist wird nun die Box anders angezeigt. Warum? Weil mit der Variable type Umbrella ausgelesen wird und die Klasse erweitert und nun dieses CSS ausgelesen wird, wo Umbrella mitdabei ist.
Dies könnt ihr für so viele Typen machen wie ihr benötigt. Da bei einem anderen Hintergrund die Farben vom Datum, Titel und co evtl nicht mehr lesbar sind, habe ich auch die Farben dort angepasst. Ihr könnt auch eine andere Schriftart oder Größe angeben, wichtig ist nur das !important dahinter.
Da ich generell schon mit einem root Verzeichnis arbeite in dem Plugin, habe ich es auch für diese Erweiterung eingefügt. Also ihr müsst ganz oben einfach nur erweitern.

<blockquote>
        #timeline .event.Umbrella::before {
             border-color: transparent var(--pfeil-umbrella)  transparent transparent !important;     
        }
        
        #timeline .event.Umbrella .content.tUmbrella {
            background-color: var(--event-box-umbrella) !important;    
        }
        
        #timeline .event .content.Umbrella .date {
            color: var(--datum-umbrella) !important;    
        }
                            
        #timeline .event .content.Umbrella .title {
            color: var(--title-umbrella) !important;
        }
                            
        #timeline .event .content.Umbrella .description {
            color: var(--text-umbrella) !important;
        }</blockquote>
