<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
// HOOKS
// ONLINE LOCATION
$plugins->add_hook("fetch_wol_activity_end", "timeline_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "timeline_online_location");
// ZEITSTRAHL SEITEN
$plugins->add_hook("misc_start", "timeline_misc");
// TEAM-BENACHRICHTIGUNG
$plugins->add_hook('global_start', 'timeline_global');
// MODCP
$plugins->add_hook('modcp_nav', 'timeline_modcp_nav');
$plugins->add_hook("modcp_start", "timeline_modcp");


// Die Informationen, die im Pluginmanager angezeigt werden
function timeline_info()
{
	return array(
		"name"		=> "Zeitstrahl",
		"description"	=> "Dieses Plugin erweitert das Board um einen Zeitstrahl. Ausgewählte Gruppen können Ereignisse zur Timeline hinzufügen und diese werden auf einer gesonderten Seite in einem Zeitstrahl dargestellt. Ereignisse von User müssen vorher vom Team freigeschaltet werden. Ereignisse klnnen auch wieder gelöscht und bearbeitet werden.",
		"website"	=> "https://github.com/little-evil-genius/Timeline",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function timeline_install()
{
    global $db, $cache, $mybb;

    // Datenbank-Tabelle erstellen
	$db->query("CREATE TABLE ".TABLE_PREFIX."timeline(
        `tid` int(10) NOT NULL AUTO_INCREMENT,
		`day` VARCHAR(255) NOT NULL,
        `month` VARCHAR(255) NOT NULL,
        `year` VARCHAR(255) NOT NULL,
        `title` VARCHAR(1000) COLLATE utf8_general_ci NOT NULL,
  		`description` VARCHAR(5000) COLLATE utf8_general_ci NOT NULL,
        `type` VARCHAR(100) COLLATE utf8_general_ci NOT NULL,
        `accepted` int(1) NOT NULL,
        `sendedby` int(11) NOT NULL,
        PRIMARY KEY(`tid`),
        KEY `tid` (`tid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1    
     ");

     // EINSTELLUNGEN HINZUFÜGEN
    $setting_group = array(
        'name'          => 'timeline',
        'title'         => 'Zeitstrahl',
        'description'   => 'Einstellungen für den Zeitstrahl',
        'disporder'     => 1,
        'isdefault'     => 0
    );
        
        $gid = $db->insert_query("settinggroups", $setting_group); 
        
    $setting_array = array(
        'timeline_allow_groups' => array(
            'title' => 'Erlaubte Gruppen',
            'description' => 'Welche Gruppen dürfen Einträge machen?',
            'optionscode' => 'groupselect',
            'value' => '4', // Default
            'disporder' => 1
        ),

        'timeline_user_edit' => array(
            'title' => 'Bearbeitung durch User',
            'description' => 'Dürfen User, falls diese Ereignisse erstellen dürfen, ihre Ereignisse bearbeiten? Bearbeitet Ereignise werden nicht nochmal vom Team kontrolliert!',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 2
        ),

        'timeline_user_delete' => array(
            'title' => 'Löschung durch User',
            'description' => 'Dürfen User, falls diese Ereignisse erstellen dürfen, ihre Ereignisse löschen?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 3
        ),

        'timeline_sorting' => array(
            'title' => 'Sortierung',
            'description' => 'Wie sollen die Ereignisse innerhalb des Zeitstrahls sortiert werden? Aufsteigend, jüngstes Ereignis oben und ältestes Ereignis am Ende (z.B. 2020,2000,1956) oder Absteigend, das älteste Ereignis oben und das jüngste Ereignis am Ende (z.B. 1956,2000,2020)?',
            'optionscode' => 'radio
DESC=Aufsteigend
ASC=Absteigend',
			'value' => 'DESC',
            'disporder' => 4
        ),
    );
        
        foreach($setting_array as $name => $setting)
        {
            $setting['name'] = $name;
            $setting['gid']  = $gid;
            $db->insert_query('settings', $setting);
        }
    
        rebuild_settings();
	
        
        
    // TEMPLATES ERSTELLEN
    // Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "timeline",
        "title" => $db->escape_string("Zeitstrahl"),
    );

    $db->insert_query("templategroups", $templategroup);

    // TIMELINE SEITE
    $insert_array = array(
        'title'        => 'timeline',
        'template'    => $db->escape_string('<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} - {$lang->timeline}</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table border="0" cellspacing="5" width="100%" cellpadding="5" class="tborder">
                <tbody>
                    <tr>
                        <td class="thead">{$lang->timeline}</td>
                    </tr>
                    <tr>
                        <td>{$timeline_add}</td>
                    </tr>
                    <tr>
                        <td>						
                            <div id="timeline">
                                {$timeline_bit}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            {$footer}
        </body>
    </html>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EINZELNES EVENT
    $insert_array = array(
        'title'        => 'timeline_bit',
        'template'    => $db->escape_string('<div class="event">
        <div class="content">
			<div class="type">{$type}</div>
            <div class="date">{$date}</div>
            <div class="title">{$title}</div>
            <div class="description">{$description}</div>
            <div class="option">{$edit}{$delete}</div>
        </div>    
    </div>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EVENT HINZUFÜGEN
    $insert_array = array(
        'title'        => 'timeline_add',
        'template'    => $db->escape_string('<form id="add_timeline" method="post" action="misc.php?action=add_timeline">
        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
            <tr>
                <td valign="top" width="50%"> 
                    <div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_name}</div>
                    <input type="text" class="textbox" name="title" id="title" style="width: 98%; height: 25px;margin-left: -1px; margin-bottom: 10px;" placeholder="{$lang->timeline_add_name_desc}" />
                    {$timeline_add_date}
                    {$timeline_add_type}
                    <br /> 
                    <center>  
                        <input type="hidden" name="action" value="add_timeline">
                        <input type="submit" value="{$lang->timeline_add}" name="add_timeline" class="button">
                    </center>
                </td>
            
                    <td valign="top"> 
                        <div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_desc}</div>
                        <textarea class="textbox" name="description" id="description" style="width: 99%;height: 170px;margin-left: -1px;" placeholder="{$lang->timeline_add_desc_desc}"></textarea>
                    </td>       
                </tr>
        </table>
    </form>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

     // EVENT HINZUFÜGEN - DATUM
     $insert_array = array(
        'title'        => 'timeline_add_date',
        'template'    => $db->escape_string('<div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_date}</div>
        <table width="100%">				
            <tr>		
                <td align="center">
                    <span class="smalltext">
                        <select name="day">
							<option value="">Tag</option>
                            <option value="01">1</option>
                            <option value="02">2</option>
                            <option value="03">3</option>
                            <option value="04">4</option>
                            <option value="05">5</option>
                            <option value="06">6</option>
                            <option value="07">7</option>
                            <option value="08">8</option>
                            <option value="09">9</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
                            <option value="13">13</option>
                            <option value="14">14</option>
                            <option value="15">15</option>
                            <option value="16">16</option>
                            <option value="17">17</option>
                            <option value="18">18</option>
                            <option value="19">19</option>
                            <option value="20">20</option>
                            <option value="21">21</option>
                            <option value="22">22</option>
                            <option value="23">23</option>
                            <option value="24">24</option>
                            <option value="25">25</option>
                            <option value="26">26</option>
                            <option value="27">27</option>
                            <option value="28">28</option>
                            <option value="29">29</option>
                            <option value="30">30</option>
                            <option value="31">31</option>
                        </select> 
                        <select name="month">
							<option value="">Monat</option>
                            <option value="01">Januar</option>
                            <option value="02">Februar</option>
                            <option value="03">März</option>
                            <option value="04">April</option>
                            <option value="05">Mai</option>
                            <option value="06">Juni</option>
                            <option value="07">Juli</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Dezember</option>
                        </select>
                        <input type="text" class="textbox" name="year" placeholder="Jahreszahl"></span>
                </td>			
            </tr>				
        </table>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EVENT HINZUFÜGEN - TYP
    $insert_array = array(
        'title'        => 'timeline_add_type',
        'template'    => $db->escape_string('<div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_type}</div>
        <table width="100%">				
            <tr>		
                <td align="center">
                    <span class="smalltext">
                     <select name=\'type\' id=\'type\' style="width: 100%;" required>
                      <option value="">Timeline wählen</option>
                      <option value="Sparrow Academy">Sparrow Academy</option>
                      <option value="Archer Academy">Archer Academy</option>
                      <option value="Umbrella Academy">Umbrella Academy</option>	
                      </select>
                 </span>
                </td>			
            </tr>				
        </table>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EVENT BEARBEITEN
    $insert_array = array(
        'title'        => 'timeline_edit',
        'template'    => $db->escape_string('<<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} - {$lang->timeline_edit}</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                <tr>
                    <td colspan="2">
                        <div class="tcat">{$lang->timeline_edit}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <form method="post" action="misc.php?action=timeline_edit&tid={$tid}">	
                            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                                <tr>
                                    <td valign="top" width="50%"> 
                                        <div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_name}</div>
                                        <input type="text" class="textbox" name="title" id="title" style="width: 98%; height: 25px;margin-left: -1px; margin-bottom: 10px;" value="{$title}" />
                                        {$timeline_edit_date}
                                        {$timeline_edit_type}
                                        <br /> 
                                        <center>  
                                            <input type="hidden" name="tid" id="tid" value="{$tid}" class="textbox" />
                                            <input type="submit" name="edit_timeline" value="{$lang->timeline_edit}" id="submit" class="button">
                                        </center>
                                    </td>
                                    <td valign="top"> 
                                        <div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_desc}</div>
                                        <textarea class="textbox" name="description" id="description" style="width: 99%;height: 170px;margin-left: -1px;">{$description}</textarea>
                                    </td>      
                                </tr>
                            </table>
                        </form>	
                    </td>
                </tr>
            </table>		
            {$footer}
        </body>
    </html>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EVENT BEARBEITEN - TYP
    $insert_array = array(
        'title'        => 'timeline_edit_type',
        'template'    => $db->escape_string('<div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_type}</div>
        <table width="100%">				
            <tr>		
                <td align="center">
                    <span class="smalltext">
                     <select name=\'type\' id=\'type\' style="width: 100%;" required>
                      <option value="{$type}">{$type}</option>
                      <option value="Sparrow Academy">Sparrow Academy</option>
                      <option value="Archer Academy">Archer Academy</option>
                      <option value="Umbrella Academy">Umbrella Academy</option>	
                      </select>
                 </span>
                </td>			
            </tr>				
        </table>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // EVENT BEARBEITEN - DATUM
    $insert_array = array(
        'title'        => 'timeline_edit_date',
        'template'    => $db->escape_string('<div class="tcat" style="margin-bottom: 5px;text-align: center;">{$lang->timeline_add_name}</div>
        <table width="100%">				
            <tr>		
                <td align="center">
                    <span class="smalltext">
                        <select name="day">
							<option value="{$day}">{$day}</option>
                            <option value="">kein Tag</option>
                            <option value="01">1</option>
                            <option value="02">2</option>
                            <option value="03">3</option>
                            <option value="04">4</option>
                            <option value="05">5</option>
                            <option value="06">6</option>
                            <option value="07">7</option>
                            <option value="08">8</option>
                            <option value="09">9</option>
                            <option value="10">10</option>
                            <option value="11">11</option>
                            <option value="12">12</option>
                            <option value="13">13</option>
                            <option value="14">14</option>
                            <option value="15">15</option>
                            <option value="16">16</option>
                            <option value="17">17</option>
                            <option value="18">18</option>
                            <option value="19">19</option>
                            <option value="20">20</option>
                            <option value="21">21</option>
                            <option value="22">22</option>
                            <option value="23">23</option>
                            <option value="24">24</option>
                            <option value="25">25</option>
                            <option value="26">26</option>
                            <option value="27">27</option>
                            <option value="28">28</option>
                            <option value="29">29</option>
                            <option value="30">30</option>
                            <option value="31">31</option>
                        </select> 
                        <select name="month">
							<option value="{$month}">{$monthname}</option>
                            <option value="">kein Monat</option>
                            <option value="01">Januar</option>
                            <option value="02">Februar</option>
                            <option value="03">März</option>
                            <option value="04">April</option>
                            <option value="05">Mai</option>
                            <option value="06">Juni</option>
                            <option value="07">Juli</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Dezember</option>
                        </select>
                        <input type="text" class="textbox" name="year" value="{$year}"></span>
                </td>			
            </tr>				
        </table>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // MODCP
    $insert_array = array(
        'title'        => 'timeline_modcp',
        'template'    => $db->escape_string('<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} -  {$lang->timeline_modcp}</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table width="100%" border="0" align="center">
                <tr>
                    {$modcp_nav}
                    <td valign="top">
                        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                            <tr>
                                <td class="thead" colspan="3">
                                    <strong>{$lang->timeline_modcp}</strong>
                                </td>
                            </tr>
                            {$timeline_modcp_bit}
                        </table>
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
    </html>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // MODCP - BIT
    $insert_array = array(
        'title'        => 'timeline_modcp_bit',
        'template'    => $db->escape_string('<table width="100%" border="0">
        <tbody>
            <tr>
                <td class="thead" colspan="2">{$title}</td>
            </tr>
            <tr>
                <td align="center" colspan="2">{$date} in Timeline {$type}</td>
            </tr>
            <tr>
                <td class="trow2" colspan="2" align="justify">
                    {$description}
                </td> 
            </tr>
            <tr>
                <td class="trow2" align="center" width="50%">
                    <a href="modcp.php?action=timeline&accept={$tid}" class="button">{$lang->timeline_modcp_accepted}</a>
                </td>
            
                <td class="trow2" align="center" width="50%">
                    <a href="modcp.php?action=timeline&delete={$tid}" class="button">{$lang->timeline_modcp_delete}</a> 
                </td>
            </tr>
        </tbody>
    </table>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    // MODCP - NAVIGATION
    $insert_array = array(
        'title'        => 'timeline_modcp_nav',
        'template'    => $db->escape_string('<tr>
        <td class="trow1 smalltext"><a href="modcp.php?action=timeline" class="modcp_nav_item modcp_timeline_control">{$lang->timeline_modcp_nav}</td>
    </tr>'),
        'sid'        => '-2',
        'version'    => '',
        'dateline'    => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function timeline_is_installed()
{
    global $db, $cache, $mybb;
  
      if($db->table_exists("timeline"))  {
        return true;
      }
        return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function timeline_uninstall()
{
    global $db;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("timeline"))
    {
        $db->drop_table("timeline");
    }
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'timeline%'");
    $db->delete_query('settinggroups', "name = 'timeline'");

    rebuild_settings();

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE '%timeline%'");
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function timeline_activate()
{
    global $db, $cache;
    
    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    
    // VARIABLEN EINFÜGEN
	find_replace_templatesets('header', '#'.preg_quote('{$bbclosedwarning}').'#', '{$new_timeline_alert} {$bbclosedwarning}');
    find_replace_templatesets('modcp_nav_users', '#'.preg_quote('{$nav_ipsearch}').'#', '{$nav_ipsearch} {$nav_timeline}');

    // STYLESHEET HINZUFÜGEN
    $css = array(
		'name' => 'timeline.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" =>	':root {
            --vertikaler-Strich: #C7CFD9;
            --pfeil: #8596a6;
            --kreis-innen: #8596a6;
            --kreis-border: #293340;
            --event-box: #8596a6;
            --title: #293340;
            --text: #C7CFD9;
            --datum: #293340;
            --link: #C7CFD9;
        }
                    
        #timeline {
            position: relative;
            max-width: 880px;
            margin: 0 auto;    
        }
                            
        #timeline::after {
            content: \\\'\\\';    
            position: absolute;    
            width: 6px;    
            background-color: var(--vertikaler-Strich);    
            top: 0;    
            bottom: 0;    
            margin-left: -3px;    
        }
                            
        #timeline .event {
            padding: 10px 40px 10px 40px;
            position: relative;
            background-color: inherit;
        }
                            
        #timeline .event::before {
            content: \\\'\\\';
            height: 0;
            position: absolute;
            top: 22px;
            width: 0;
            z-index: 1;
            left: 30px;
            border: medium solid var(--pfeil);
            border-width: 10px 10px 10px 0;
            border-color: transparent var(--pfeil) transparent transparent;
        }
                            
        #timeline .event::after {
            content: \\\'\\\';
            position: absolute;
            width: 25px;
            height: 25px;
            left: -17px;
            background-color: var(--kreis-innen);
            border: 4px solid var(--kreis-border);
            top: 15px;
            border-radius: 50%;
            z-index: 1;
        }
                            
        #timeline .event .content {
            padding: 30px;
            background-color: var(--event-box);
            position: relative;
            border-radius: 6px;
            overflow: hidden;
        }
        
        #timeline .event .content .type {
            position: absolute;
            left: 5px;
            font-family: Playfair Display;
            font-size: 60px;
            bottom: -15px;
            top: auto;
            opacity: .09;
            color: var(--datum);
        }                
        
        #timeline .event .content .date {
            font-size: 20px;
            text-transform: uppercase;
            font-weight: bold;
            color: var(--datum);    
        }
                            
        #timeline .event .content .title {
            font: 8px arial;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--title);
            line-height: 200%;
        }
                            
        #timeline .event .content .description {
            line-height: 20px;
            font-size: 13px;
            font-family: tahoma;
            text-align: justify;
            color: var(--text);
        }
                            
        #timeline .event .content .option {
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-family: calibri;
            font-size: 12px;
            opacity: .7;
            padding-right: 5px;
        }
                            
        #timeline .event .content .option i {
            padding: 0 3px
        }
                    
        #timeline .event .content .option a:link, 
        #timeline .event .content .option a:visited, 
        #timeline .event .content .option a:active, 
        #timeline .event .content .option a:hover {
            color: var(--link);
        }',
            'cachefile' => $db->escape_string(str_replace('/', '', 'timeline.css')),
            'lastmodified' => time()
        );
    
        require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
    
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while ($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        }
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function timeline_deactivate()
{
    global $db, $cache;

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
    find_replace_templatesets("header", "#".preg_quote('{$new_timeline_alert}')."#i", '', 0);
    find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_timeline}')."#i", '', 0);

    // STYLESHEET ENTFERNEN
	$db->delete_query("themestylesheets", "name = 'timeline.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}

// FUNKTIONEN - THE MAGIC

###########################
##### ONLINE LOCATION #####
###########################
function timeline_online_activity($user_activity) 
{
    global $parameters;

    $split_loc = explode(".php", $user_activity['location']);
    if($split_loc[0] == $user['location']) {
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
    }
    
    switch ($filename) {
        case 'misc':
        if($parameters['action'] == "timeline" && empty($parameters['site'])) {
            $user_activity['activity'] = "timeline";
        }
        if($parameters['action'] == "timeline_edit" && empty($parameters['site'])) {
            $user_activity['activity'] = "timeline_edit";
        }
        break;
    }
      
return $user_activity;
}

function timeline_online_location($plugin_array) 
{
    global $mybb, $theme, $lang;

    if($plugin_array['user_activity']['activity'] == "timeline") {
		$plugin_array['location_name'] = "Sieht sich den <a href=\"misc.php?action=timeline\">Zeitstrahl</a> an.";
	}
    if($plugin_array['user_activity']['activity'] == "timeline_edit") {
		$plugin_array['location_name'] = "Bearbeitet gerade einen Eintrag vom Zeitstrahl.";
	}

return $plugin_array;
}

// TEAMHINWEIS ÜBER EINEN NEUEN EINTRAG
function timeline_global()
{
    global $db, $cache, $mybb, $templates, $new_timeline_alert;

     // NEUER EINTRAG IN DER TIMELINE
     $new_timeline = $db->query("SELECT *
        FROM " . TABLE_PREFIX . "timeline
        where accepted = 0
        ");

    $count_timeline = mysqli_num_rows($new_timeline);
     
     if($mybb->usergroup['canmodcp'] == "1" && $count_timeline == "1"){
         $new_timeline_alert = "<div class=\"red_alert\"><a href=\"modcp.php?action=timeline\">{$count_timeline} neuer Eintrag für den Zeitstrahl muss freigeschaltet werden</a></div>";
     } elseif ($mybb->usergroup['canmodcp'] == "1" && $count_timeline > "1") {
        $new_timeline_alert = "<div class=\"red_alert\"><a href=\"modcp.php?action=timeline\">{$count_timeline} neue Einträge für den Zeitstrahl müssen freigeschaltet werden</a></div>";
    }

}

// DIE SEITEN
function timeline_misc() {
    global $db, $cache, $mybb, $lang, $templates, $sorting, $theme, $header, $headerinclude, $footer, $timeline_add, $option_timeline, $timeline_add_date;

    // HTML & BBC ERLAUBEN/DARSTELLEN
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;

    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    // SPRACHDATEI LADEN
    $lang->load('timeline');

    // EINSTELLUNGEN ZIEHEN
    $allow_groups =  $mybb->settings['timeline_allow_groups'];
    $user_edit =  $mybb->settings['timeline_user_edit'];
    $user_delete =  $mybb->settings['timeline_user_delete'];
    $sorting = $mybb->settings['timeline_sorting'];
    
    // USER-ID
    $own_uid = $mybb->user['uid'];

    // ACTION-BAUM BAUEN
    $mybb->input['action'] = $mybb->get_input('action');

    // ZEITSTRAHL - SEITE
    if($mybb->input['action'] == "timeline") {

    // NAVIGATION
    add_breadcrumb($lang->timeline, "misc.php?action=timeline");

    //Nur den Gruppen, die es erlaubt ist, neue Einträge zu machen, ist es erlaubt, den Link zu sehen.
   if(is_member($allow_groups)) {
        eval("\$timeline_add_date = \"".$templates->get("timeline_add_date")."\";");
        eval("\$timeline_add_type = \"".$templates->get("timeline_add_type")."\";");
        eval("\$timeline_add = \"".$templates->get("timeline_add")."\";");     
    }

    // EREIGNISSE ABFRAGEN
    $allevent = $db->query("
    SELECT * FROM ".TABLE_PREFIX."timeline
    WHERE accepted = '1'
    ORDER BY year+0 ".$sorting.", month ".$sorting.", day ".$sorting
    );

    // EREIGNIS AUSLESEN
    while($event = $db->fetch_array($allevent)) {
   
     // Alles leer laufen lassen
      $tid = "";
      $day = "";
      $month = "";
      $year = "";
      $type = "";
      $title = "";
      $description = "";
      $accepted = "";
      $sendedby = "";

      // Füllen wir mal alles mit Informationen
      $tid = $event['tid'];
      $day = $event['day'];
      $year = $event['year'];
      $title = $event['title'];
      $type = $event['type'];
      $description = $event['description'];
      $accepted = $event['accepted'];
      $sendedby = $event['sendedby'];

       // DATUM-ANZEIGE

       // Monate als Namen ausgeben
       if ($event['month'] == "01") {
        $month = "{$lang->timline_month_january}";
       } elseif ($event['month'] == "02") {
        $month = "{$lang->timline_month_february}";
       } elseif ($event['month'] == "03") {
        $month = "{$lang->timline_month_march}";
       } elseif ($event['month'] == "04") {
        $month = "{$lang->timline_month_april}";
       } elseif ($event['month'] == "05") {
        $month = "{$lang->timline_month_may}";
       } elseif ($event['month'] == "06") {
        $month = "{$lang->timline_month_june}";
       } elseif ($event['month'] == "07") {
        $month = "{$lang->timline_month_july}";
       } elseif ($event['month'] == "08") {
        $month = "{$lang->timline_month_august}";
       } elseif ($event['month'] == "09") {
        $month = "{$lang->timline_month_september}";
       } elseif ($event['month'] == "10") {
        $month = "{$lang->timline_month_october}";
       } elseif ($event['month'] == "11") {
        $month = "{$lang->timline_month_november}";
       } elseif($event['month'] == "12") {
        $month = "{$lang->timline_month_december}";
       }

       // Komplettes Datum
       if (!empty($event['day'])) {
        $date = "{$day}. {$month} {$year}";
    } elseif ($event['day'] == "" && $event['month'] != "") {
        $date = "{$month} {$year}";
    } elseif ($event['day'] == "" && $event['month'] == "") {
        $date = "{$year}";
    } 
     

      // OPTIONEN
      // Team darf löschen und bearbeiten
      if($mybb->usergroup['canmodcp'] == "1"){
        $edit = "<a href=\"misc.php?action=timeline_edit&tid={$tid}\"><i class=\"fas fa-edit\" original-title=\"Ereignis bearbeiten\"></i></a>";
        $delete = "<a href=\"misc.php?action=timeline&delete={$tid}\"><i class=\"fas fa-trash\" original-title=\"Ereignis löschen\"></i></a>";
      } 
      // Wenn User nur eigene Events bearbeiten dürfen || Nicht löschen
      elseif($own_uid == $sendedby) {
        // Wenn User bearbeiten dürfen
        if ($user_edit == '1') {
            $edit = "<a href=\"misc.php?action=timeline_edit&tid={$tid}\"><i class=\"fas fa-edit\" original-title=\"Ereignis bearbeiten\"></i></a>";
        } else {
            $edit = "";
        }
        // Wenn User löschen dürfen
        if ($user_delete == '1') {
            $delete = "<a href=\"misc.php?action=timeline&delete={$tid}\"><i class=\"fas fa-trash\" original-title=\"Ereignis löschen\"></i></a>";
        } else {
            $delete = "";
        }
      } elseif($own_uid != $sendedby) {
        $edit = "";
        $delete = "";
      }
        
     eval("\$timeline_bit .= \"".$templates->get("timeline_bit")."\";");
    }

    // EREIGNIS LÖSCHEN
    $delete = $mybb->input['delete'];
    if($delete) {
        $db->delete_query("timeline", "tid = '$delete'");
        redirect("misc.php?action=timeline", "{$lang->timline_delete_event}");
    }

    eval("\$page = \"".$templates->get("timeline")."\";");
    output_page($page);
    die();
  }

  // EREIGNIS HINZUFÜGEN
  elseif($mybb->input['action'] == "add_timeline") {
    if($mybb->input['title'] == "")
    {
        error("Es muss ein Titel eingetragen werden!");
    }
    else if($mybb->input['description'] == "")
    {
        error("Es muss eine Beschreibung eingetragen werden!");
    }
    else if($mybb->input['year'] == "")
    {
        error("Es muss ein Jahr eingetragen werden!");
    }
    else if($mybb->input['type'] == "")
    {
        error("Es muss eine Timeline ausgewählt werden!");
    }else{

        //Wenn das Team Einträge erstellt, dann wink doch einfach durch. Sonst bitte nochmal zum Prüfung :D
        if($mybb->usergroup['canmodcp'] == '1'){
            $accepted = 1;
        } else {
            $accepted = 0;
        }

        $new_event = array(
            "day" => $db->escape_string($mybb->get_input('day')),
            "month" => $db->escape_string($mybb->get_input('month')),
            "year" => $db->escape_string($mybb->get_input('year')),
            "type" => $db->escape_string($mybb->get_input('type')),
            "title" => $db->escape_string($mybb->get_input('title')),
            "description" => $db->escape_string($mybb->get_input('description')),
            "sendedby" => (int)$mybb->user['uid'],
            "accepted" => $accepted,
        );
        $db->insert_query("timeline", $new_event);
        redirect("misc.php?action=timeline", "{$lang->timeline_new_event}");
    }
  }

  // EREIGNIS BEARBEITEN
  elseif($mybb->input['action'] == "timeline_edit") {

      // NAVIGATION
      add_breadcrumb ($lang->timeline, "misc.php?action=timeline");
      add_breadcrumb ($lang->timeline_edit, "misc.php?action=timeline_edit");

      $tid =  $mybb->get_input('tid', MyBB::INPUT_INT);

      $edit_query = $db->query("
      SELECT * FROM ".TABLE_PREFIX."timeline
      WHERE tid = '".$tid."'
      ");

      $edit = $db->fetch_array($edit_query);

      // Alles leer laufen lassen
      $tid = "";
      $day = "";
      $month = "";
      $year = "";
      $type = "";
      $title = "";
      $description = "";

      // Füllen wir mal alles mit Informationen
      $tid = $edit['tid'];
      $day = $edit['day'];
      $month = $edit['month'];
      $year = $edit['year'];
      $type = $edit['type'];
      $title = $edit['title'];
      $description = $edit['description'];

      // Monate als Namen ausgeben
      if ($edit['month'] == "01") {
        $monthname = "{$lang->timline_month_january}";
       } elseif ($edit['month'] == "02") {
        $monthname = "{$lang->timline_month_february}";
       } elseif ($edit['month'] == "03") {
        $monthname = "{$lang->timline_month_march}";
       } elseif ($edit['month'] == "04") {
        $monthname = "{$lang->timline_month_april}";
       } elseif ($edit['month'] == "05") {
        $monthname = "{$lang->timline_month_may}";
       } elseif ($edit['month'] == "06") {
        $monthname = "{$lang->timline_month_june}";
       } elseif ($edit['month'] == "07") {
        $monthname = "{$lang->timline_month_july}";
       } elseif ($edit['month'] == "08") {
        $monthname = "{$lang->timline_month_august}";
       } elseif ($edit['month'] == "09") {
        $monthname = "{$lang->timline_month_september}";
       } elseif ($edit['month'] == "10") {
        $monthname = "{$lang->timline_month_october}";
       } elseif ($edit['month'] == "11") {
        $monthname = "{$lang->timline_month_november}";
       } elseif($edit['month'] == "12") {
        $monthname = "{$lang->timline_month_december}";
       }

      eval("\$timeline_edit_date = \"".$templates->get("timeline_edit_date")."\";");
      eval("\$timeline_edit_type = \"".$templates->get("timeline_edit_type")."\";");

      //Der neue Inhalt wird nun in die Datenbank eingefügt bzw. die alten Daten überschrieben.
      if($_POST['edit_timeline']){
          $tid = $mybb->input['tid'];
          $edit_event = array(
            "day" => $db->escape_string($mybb->get_input('day')),
            "month" => $db->escape_string($mybb->get_input('month')),
            "year" => $db->escape_string($mybb->get_input('year')),
            "type" => $db->escape_string($mybb->get_input('type')),
            "title" => $db->escape_string($mybb->get_input('title')),
            "description" => $db->escape_string($mybb->get_input('description')),
          );

          $db->update_query("timeline", $edit_event, "tid = '".$tid."'");
          redirect("misc.php?action=timeline", "{$lang->timeline_edit_event}");
      }

      eval("\$page = \"".$templates->get("timeline_edit")."\";");
      output_page($page);
      die();

  }
}

// MOD-CP
function timeline_modcp_nav()
{
    global $db, $mybb, $templates, $theme, $header, $headerinclude, $footer, $lang, $modcp_nav, $nav_timeline;
    
    $lang->load('timeline');

    eval("\$nav_timeline = \"".$templates->get ("timeline_modcp_nav")."\";");
}

function timeline_modcp() {
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $db, $page, $modcp_nav;
    
    require_once MYBB_ROOT."inc/datahandlers/pm.php";
    $pmhandler = new PMDataHandler();

    if($mybb->get_input('action') == 'timeline') {

        // SPRACHDATEI
        $lang->load('timeline');

        // Add a breadcrumb
        add_breadcrumb($lang->timeline_modcp_nav, "modcp.php?action=timeline");

        // EREIGNISSE ABFRAGEN
    $modevent = $db->query("
    SELECT * FROM ".TABLE_PREFIX."timeline
    WHERE accepted = '0'
    ORDER BY tid ASC
    ");

    // EREIGNIS AUSLESEN
    while($modcp = $db->fetch_array($modevent)) {
   
     // Alles leer laufen lassen
      $tid = "";
      $day = "";
      $month = "";
      $year = "";
      $title = "";
      $type = "";
      $description = "";
      $accepted = "";
      $sendedby = "";

      // Füllen wir mal alles mit Informationen
      $tid = $modcp['tid'];
      $day = $modcp['day'];
      $year = $modcp['year'];
      $title = $modcp['title'];
      $type = $modcp['type'];
      $description = $modcp['description'];
      $accepted = $modcp['accepted'];

      // User der das eingesendet hat
      $modcp['sendedby'] = htmlspecialchars_uni($modcp['sendedby']);
      $user = get_user($modcp['sendedby']);
      $user['username'] = htmlspecialchars_uni($user['username']);
      $sendedby = build_profile_link($user['username'], $modcp['sendedby']);

      // DATUM-ANZEIGE
       // Monate als Namen ausgeben
       if ($modcp['month'] == "01") {
        $month = "{$lang->timline_month_january}";
       } elseif ($modcp['month'] == "02") {
        $month = "{$lang->timline_month_february}";
       } elseif ($modcp['month'] == "03") {
        $month = "{$lang->timline_month_march}";
       } elseif ($modcp['month'] == "04") {
        $month = "{$lang->timline_month_april}";
       } elseif ($modcp['month'] == "05") {
        $month = "{$lang->timline_month_may}";
       } elseif ($modcp['month'] == "06") {
        $month = "{$lang->timline_month_june}";
       } elseif ($modcp['month'] == "07") {
        $month = "{$lang->timline_month_july}";
       } elseif ($modcp['month'] == "08") {
        $month = "{$lang->timline_month_august}";
       } elseif ($modcp['month'] == "09") {
        $month = "{$lang->timline_month_september}";
       } elseif ($modcp['month'] == "10") {
        $month = "{$lang->timline_month_october}";
       } elseif ($modcp['month'] == "11") {
        $month = "{$lang->timline_month_november}";
       } elseif($modcp['month'] == "12") {
        $month = "{$lang->timline_month_december}";
       }

       // Komplettes Datum
       if (!empty($modcp['day'])) {
        $date = "{$day}. {$month} {$year}";
    } elseif ($modcp['day'] == "" && $modcp['month'] != "") {
        $date = "{$month} {$year}";
    } elseif ($modcp['day'] == "" && $modcp['month'] == "") {
        $date = "{$year}";
    } 

      eval("\$timeline_modcp_bit .= \"".$templates->get("timeline_modcp_bit")."\";");
    }

    // PN ABSENDER
    $team_uid = $mybb->user['uid'];

    // Der Eintrag wurde vom Team abgelehnt 
    $del = $mybb->input['delete'];
    if($del){

    $delete_event = $db->query("SELECT sendedby FROM ".TABLE_PREFIX."timeline
    WHERE tid = '".$del."'        
    ");
            
    $owner_uid = $db->fetch_array($delete_event);
    $sendedby = $owner_uid['sendedby'];

    $pm_change = array(
        "subject" => "{$lang->timeline_modcp_pm_delete_subject}",
        "message" => "{$lang->timeline_modcp_pm_delete_message}",
        //from: von wem kommt die PN
        "fromid" => $team_uid,
        //to: an wen geht die PN
        "toid" => $sendedby
    );
       
    // $pmhandler->admin_override = true;
    $pmhandler->set_data ($pm_change);
    if (!$pmhandler->validate_pm ())
    return false;
    else {
        $pmhandler->insert_pm ();
    }

    $db->delete_query("timeline", "tid = '$del'");
    redirect("modcp.php?action=timeline", "{$lang->timline_modcp_delete_event}");    
    }

     
     // Der Eintag wurde vom Team angenommen 
     if($acc = $mybb->input['accept']){

        $accept_event = $db->query("SELECT sendedby FROM ".TABLE_PREFIX."timeline
        WHERE tid = '".$acc."'
        ");

        $owner_uid = $db->fetch_array($accept_event);
        $sendedby = $owner_uid['sendedby'];

        $pm_change = array(
            "subject" => "{$lang->timeline_modcp_pm_accept_subject}",
            "message" => "{$lang->timeline_modcp_pm_accept_message}",
            //from: von wem kommt die PN
            "fromid" => $team_uid,
            //to: an wen geht die PN
            "toid" => $sendedby
        );

        // $pmhandler->admin_override = true;
        $pmhandler->set_data ($pm_change);
        if (!$pmhandler->validate_pm ())
        return false;
        else {
            $pmhandler->insert_pm ();
        }

        $db->query("UPDATE ".TABLE_PREFIX."timeline SET accepted = 1 WHERE tid = '".$acc."'");
        redirect("modcp.php?action=timeline", "{$lang->timline_modcp_accept_event}");    
    }

        eval("\$page = \"".$templates->get("timeline_modcp")."\";");
        output_page($page);
        die();

    }
}
